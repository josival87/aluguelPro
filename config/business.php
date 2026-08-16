<?php

return [
    /* Percentuais contratuais: valide com assessoria jurídica antes da produção. */
    'late_fee_percent' => (float) env('LATE_FEE_PERCENT', 2.0),
    'monthly_interest_percent' => (float) env('MONTHLY_INTEREST_PERCENT', 1.0),
    'pix_expiration_minutes' => (int) env('PIX_EXPIRATION_MINUTES', 30),
    'otp_expiration_minutes' => (int) env('OTP_EXPIRATION_MINUTES', 10),
    'ocr_min_confidence' => (float) env('OCR_MIN_CONFIDENCE', 0.70),
    'billing_timezone' => env('BILLING_TIMEZONE', 'America/Sao_Paulo'),
];
