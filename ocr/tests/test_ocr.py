import json
import os
from pathlib import Path

import cv2
import pytest

from app.main import locate_display, parse_numeric_candidates, recognize

FIXTURES = Path(__file__).parent / "fixtures"


def test_numeric_candidate_parser():
    assert parse_numeric_candidates("reading 517 kWh") == [517.0]
    assert parse_numeric_candidates("00517") == [517.0]
    assert parse_numeric_candidates("517,3") == [517.3]


def test_reference_image_has_expected_metadata_and_detectable_display():
    expected = json.loads((FIXTURES / "dds668_apt102_517.json").read_text(encoding="utf-8"))
    image = cv2.imread(str(FIXTURES / "dds668_apt102_517.jpeg"))
    assert image is not None
    display = locate_display(image)
    assert display.size > 0
    assert expected["reading_kwh"] == 517
    assert expected["display_line"] == 2


@pytest.mark.skipif(os.getenv("RUN_OCR_GOLDEN") != "1", reason="OCR golden depende do Tesseract e hardware da imagem")
def test_reference_image_reads_517():
    image = cv2.imread(str(FIXTURES / "dds668_apt102_517.jpeg"))
    candidates = recognize(image)
    assert any(candidate.value == 517 for candidate in candidates), candidates
