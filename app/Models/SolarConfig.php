<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarConfig extends Model
{
    protected $fillable = ['lease_id', 'initial_reading', 'price_per_kwh'];
    protected function casts(): array { return ['initial_reading' => 'decimal:3', 'price_per_kwh' => 'decimal:4']; }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function readings() { return $this->hasMany(SolarReading::class)->orderByDesc('reference_month'); }
}
