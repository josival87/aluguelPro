<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixPayment extends Model
{
    protected $fillable = [
        'charge_id', 'txid', 'original_amount', 'fine_amount', 'interest_amount', 'total_amount',
        'br_code', 'provider', 'provider_reference', 'status', 'expires_at', 'paid_at',
    ];
    protected function casts(): array { return ['expires_at' => 'datetime', 'paid_at' => 'datetime']; }
    public function charge() { return $this->belongsTo(Charge::class); }
}
