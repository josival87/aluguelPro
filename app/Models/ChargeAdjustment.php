<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeAdjustment extends Model
{
    protected $fillable = [
        'charge_id', 'user_id', 'previous_amount', 'new_amount', 'action',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
        ];
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
