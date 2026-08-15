from __future__ import annotations

import re
from dataclasses import dataclass
from typing import Annotated

import cv2
import numpy as np
import pytesseract
from fastapi import FastAPI, File, HTTPException, UploadFile
from pydantic import BaseModel

MAX_IMAGE_BYTES = 10 * 1024 * 1024


class Candidate(BaseModel):
    value: float
    confidence: float
    source: str
    raw_text: str


class MeterResult(BaseModel):
    reading: float | None
    confidence: float
    candidates: list[Candidate]
    requires_confirmation: bool = True
    model: str = "generic-lcd-v1"


@dataclass(frozen=True)
class Region:
    image: np.ndarray
    name: str
    priority: float


app = FastAPI(title="AlugaPro Meter OCR", version="1.0.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/read-meter", response_model=MeterResult)
async def read_meter(file: Annotated[UploadFile, File(description="Foto do visor do medidor")]) -> MeterResult:
    if file.content_type not in {"image/jpeg", "image/png", "image/webp"}:
        raise HTTPException(status_code=422, detail="Envie uma imagem JPG, PNG ou WebP.")

    payload = await file.read(MAX_IMAGE_BYTES + 1)
    if len(payload) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=413, detail="A imagem deve ter no máximo 10 MB.")

    image = cv2.imdecode(np.frombuffer(payload, dtype=np.uint8), cv2.IMREAD_COLOR)
    if image is None or min(image.shape[:2]) < 120:
        raise HTTPException(status_code=422, detail="Imagem inválida ou pequena demais.")

    candidates = recognize(image)
    best = candidates[0] if candidates else None
    return MeterResult(
        reading=best.value if best else None,
        confidence=best.confidence if best else 0.0,
        candidates=candidates[:5],
    )


def recognize(image: np.ndarray) -> list[Candidate]:
    display = locate_display(image)
    height = display.shape[0]
    regions = [
        Region(display, "display_full", 0.86),
        # No DDS668 usado como referência, a leitura de consumo está na segunda linha.
        Region(display[int(height * 0.34):int(height * 0.92), :], "display_second_line", 1.0),
        Region(display[int(height * 0.48):int(height * 0.96), :], "display_lower_line", 0.96),
    ]
    found: list[Candidate] = []
    for region in regions:
        for variant_name, variant in preprocessing_variants(region.image):
            found.extend(run_tesseract(variant, f"{region.name}:{variant_name}", region.priority))
    return consolidate(found)


def locate_display(image: np.ndarray) -> np.ndarray:
    height, width = image.shape[:2]
    upper = image[: int(height * 0.62), :]
    gray = cv2.cvtColor(upper, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (7, 7), 0)
    _, dark = cv2.threshold(gray, 78, 255, cv2.THRESH_BINARY_INV)
    dark = cv2.morphologyEx(dark, cv2.MORPH_CLOSE, np.ones((19, 19), np.uint8), iterations=2)
    contours, _ = cv2.findContours(dark, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    expected_x, expected_y = width * 0.52, height * 0.29
    choices: list[tuple[float, tuple[int, int, int, int]]] = []
    for contour in contours:
        x, y, w, h = cv2.boundingRect(contour)
        area_ratio = (w * h) / (width * height)
        aspect = w / max(h, 1)
        if not (0.035 <= area_ratio <= 0.35 and 1.15 <= aspect <= 3.2):
            continue
        distance = np.hypot((x + w / 2 - expected_x) / width, (y + h / 2 - expected_y) / height)
        choices.append((area_ratio * 2.5 - distance, (x, y, w, h)))

    if choices:
        _, (x, y, w, h) = max(choices, key=lambda item: item[0])
        margin_x, margin_y = int(w * 0.04), int(h * 0.05)
        return image[max(0, y + margin_y):min(height, y + h - margin_y), max(0, x + margin_x):min(width, x + w - margin_x)]

    # Fallback calibrado pela posição física típica do LCD na foto frontal de um DDS668.
    return image[int(height * 0.15):int(height * 0.45), int(width * 0.24):int(width * 0.81)]


def preprocessing_variants(region: np.ndarray) -> list[tuple[str, np.ndarray]]:
    gray = cv2.cvtColor(region, cv2.COLOR_BGR2GRAY) if region.ndim == 3 else region
    scale = max(2.5, 1200 / max(gray.shape[1], 1))
    resized = cv2.resize(gray, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
    clahe = cv2.createCLAHE(clipLimit=4.0, tileGridSize=(8, 8)).apply(resized)
    normalized = cv2.normalize(clahe, None, 0, 255, cv2.NORM_MINMAX)
    denoised = cv2.bilateralFilter(normalized, 9, 55, 55)
    _, otsu = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    adaptive = cv2.adaptiveThreshold(denoised, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 41, 7)
    blackhat = cv2.morphologyEx(denoised, cv2.MORPH_BLACKHAT, cv2.getStructuringElement(cv2.MORPH_RECT, (19, 7)))
    blackhat = cv2.normalize(blackhat, None, 0, 255, cv2.NORM_MINMAX)
    return [
        ("clahe", denoised),
        ("otsu", otsu),
        ("otsu_inverted", cv2.bitwise_not(otsu)),
        ("adaptive", adaptive),
        ("blackhat", blackhat),
    ]


def run_tesseract(image: np.ndarray, source: str, priority: float) -> list[Candidate]:
    found: list[Candidate] = []
    config = "--oem 3 --psm 7 -c tessedit_char_whitelist=0123456789.,"
    data = pytesseract.image_to_data(image, config=config, output_type=pytesseract.Output.DICT)
    tokens: list[str] = []
    confidences: list[float] = []
    for text, raw_confidence in zip(data.get("text", []), data.get("conf", []), strict=False):
        if not text.strip():
            continue
        tokens.append(text.strip())
        try:
            confidence = float(raw_confidence)
            if confidence >= 0:
                confidences.append(confidence / 100)
        except (TypeError, ValueError):
            pass
    raw = " ".join(tokens)
    base_confidence = (sum(confidences) / len(confidences)) if confidences else 0.18
    for value in parse_numeric_candidates(raw):
        adjusted = min(0.99, max(0.05, base_confidence * priority))
        found.append(Candidate(value=value, confidence=round(adjusted, 4), source=source, raw_text=raw[:80]))
    return found


def parse_numeric_candidates(text: str) -> list[float]:
    values: list[float] = []
    for token in re.findall(r"\d[\d.,]{0,8}", text):
        normalized = token.replace(" ", "")
        if normalized.count(",") + normalized.count(".") > 1:
            normalized = re.sub(r"[.,]", "", normalized)
        else:
            normalized = normalized.replace(",", ".")
        try:
            value = float(normalized)
        except ValueError:
            continue
        if 0 <= value <= 99_999_999:
            values.append(value)
    return values


def consolidate(candidates: list[Candidate]) -> list[Candidate]:
    grouped: dict[float, list[Candidate]] = {}
    for candidate in candidates:
        grouped.setdefault(candidate.value, []).append(candidate)
    consolidated: list[Candidate] = []
    for value, group in grouped.items():
        agreement_bonus = min(0.22, 0.04 * (len(group) - 1))
        best = max(group, key=lambda item: item.confidence)
        consolidated.append(Candidate(
            value=value,
            confidence=round(min(0.99, best.confidence + agreement_bonus), 4),
            source=best.source,
            raw_text=best.raw_text,
        ))
    return sorted(consolidated, key=lambda item: item.confidence, reverse=True)
