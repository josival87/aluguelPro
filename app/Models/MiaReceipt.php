<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiaReceipt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'charge_id', 'mia_client_id', 'external_id', 'payload', 'status', 'mia_receipt_id',
        'attempts', 'last_http_status', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'mia_client_id' => 'integer',
            'payload' => 'array',
            'mia_receipt_id' => 'integer',
            'attempts' => 'integer',
            'last_http_status' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }
}
