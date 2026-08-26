<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    protected $fillable = [
        'lease_id', 'client_id', 'type', 'generation_key', 'reference_month', 'due_date', 'amount',
        'status', 'description', 'paid_at', 'payment_method',
    ];

    protected function casts(): array
    {
        return ['reference_month' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function pixPayments()
    {
        return $this->hasMany(PixPayment::class);
    }

    public function adjustments()
    {
        return $this->hasMany(ChargeAdjustment::class)->latest();
    }

    public function solarReading()
    {
        return $this->hasOne(SolarReading::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'paid') {
            return 'Pago';
        }

        return $this->due_date->isPast() ? 'Vencido' : 'Em aberto';
    }
}
