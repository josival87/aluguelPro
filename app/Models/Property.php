<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'group_id', 'contract_id', 'title', 'slug', 'description', 'type', 'usable_area', 'bedrooms',
        'bathrooms', 'parking_spaces', 'street', 'number', 'complement', 'neighborhood',
        'city', 'state', 'postal_code', 'rent_amount', 'status', 'has_solar_energy',
    ];

    protected function casts(): array
    {
        return ['rent_amount' => 'decimal:2', 'usable_area' => 'decimal:2', 'has_solar_energy' => 'boolean'];
    }

    public function group()
    {
        return $this->belongsTo(PropertyGroup::class, 'group_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class);
    }

    public function media()
    {
        return $this->hasMany(PropertyMedia::class)->orderBy('sort_order');
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
