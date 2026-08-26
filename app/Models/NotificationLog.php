<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'lease_id', 'charge_id', 'recipient', 'recipient_type', 'event', 'message', 'provider_reference',
        'status', 'error', 'sent_at',
    ];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function charge() { return $this->belongsTo(Charge::class); }
}
