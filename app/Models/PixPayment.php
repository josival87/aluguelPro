<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PixPayment extends Model
{
    public const SUMMARY_COLUMNS = [
        'id', 'charge_id', 'generated_by_user_id', 'generated_by_type', 'generated_by_name',
        'txid', 'original_amount', 'fine_amount', 'interest_amount', 'total_amount', 'br_code',
        'provider', 'provider_reference', 'status', 'expires_at', 'paid_at',
        'receipt_uploaded_by_user_id', 'receipt_original_name', 'receipt_mime_type',
        'receipt_size_bytes', 'receipt_checksum_sha256', 'receipt_uploaded_at', 'created_at', 'updated_at',
    ];

    protected $fillable = [
        'charge_id', 'generated_by_user_id', 'generated_by_type', 'generated_by_name', 'txid',
        'original_amount', 'fine_amount', 'interest_amount', 'total_amount', 'br_code', 'provider',
        'provider_reference', 'status', 'expires_at', 'paid_at', 'receipt_uploaded_by_user_id',
        'receipt_original_name', 'receipt_mime_type', 'receipt_size_bytes', 'receipt_checksum_sha256',
        'receipt_document_base64', 'receipt_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'receipt_uploaded_at' => 'datetime',
            'receipt_size_bytes' => 'integer',
        ];
    }

    public function charge() { return $this->belongsTo(Charge::class); }
    public function generatedBy() { return $this->belongsTo(User::class, 'generated_by_user_id'); }
    public function receiptUploadedBy() { return $this->belongsTo(User::class, 'receipt_uploaded_by_user_id'); }

    public function scopeAudited(Builder $query): Builder
    {
        return $query->whereIn('generated_by_type', ['admin', 'client']);
    }

    public function getGeneratorLabelAttribute(): string
    {
        $type = match ($this->generated_by_type) {
            'client' => 'Cliente',
            'admin' => 'Admin',
            default => 'Não informado',
        };
        $name = $this->generated_by_name ?: $this->generatedBy?->name;

        return $name ? $type.' · '.$name : $type;
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Pago',
            'cancelled' => 'Cancelado',
            default => $this->expires_at?->isPast() ? 'Expirado' : 'Ativo',
        };
    }
}
