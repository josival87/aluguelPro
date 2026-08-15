<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractSignature extends Model
{
    protected $fillable = [
        'contract_id', 'signer_type', 'signer_name', 'signer_document', 'verification_channel',
        'photo_base64', 'photo_mime_type', 'photo_sha256', 'ip_address', 'user_agent',
        'evidence_hash', 'signed_at',
    ];
    protected $hidden = ['photo_base64'];
    protected function casts(): array { return ['signed_at' => 'datetime']; }
    public function contract() { return $this->belongsTo(LeaseContract::class, 'contract_id'); }
}
