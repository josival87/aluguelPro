<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarReading extends Model
{
    protected $fillable = [
        'solar_config_id', 'charge_id', 'reference_month', 'previous_reading', 'meter_reading',
        'consumption_kwh', 'amount', 'photo_base64', 'photo_mime_type', 'ocr_reading',
        'ocr_confidence', 'ocr_status', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'date', 'previous_reading' => 'decimal:3', 'meter_reading' => 'decimal:3',
            'consumption_kwh' => 'decimal:3', 'amount' => 'decimal:2', 'ocr_reading' => 'decimal:3',
            'ocr_confidence' => 'decimal:4',
        ];
    }

    public function solarConfig() { return $this->belongsTo(SolarConfig::class); }
    public function charge() { return $this->belongsTo(Charge::class); }
    public function confirmer() { return $this->belongsTo(User::class, 'confirmed_by'); }
}
