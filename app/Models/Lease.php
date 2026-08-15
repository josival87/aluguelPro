<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lease extends Model
{
    protected $fillable = [
        'property_id', 'client_id', 'start_date', 'end_date', 'contract_months', 'due_day',
        'rent_amount', 'status', 'has_solar_energy', 'utility_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'rent_amount' => 'decimal:2',
            'has_solar_energy' => 'boolean',
        ];
    }

    public function property() { return $this->belongsTo(Property::class); }
    public function client() { return $this->belongsTo(Client::class); }
    public function charges() { return $this->hasMany(Charge::class); }
    public function solarConfig() { return $this->hasOne(SolarConfig::class); }
    public function contract() { return $this->hasOne(LeaseContract::class); }
    public function documents() { return $this->hasMany(LeaseDocument::class); }
}
