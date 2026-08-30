<?php

namespace App\Models;

use App\Models\Concerns\HasAdminGroupScope;
use App\Models\Scopes\AdminGroupScope;
use Illuminate\Database\Eloquent\Model;

class LeaseContract extends Model
{
    use HasAdminGroupScope;

    protected const ADMIN_GROUP_SCOPE_MODE = AdminGroupScope::RELATION;

    protected const ADMIN_GROUP_SCOPE_KEY = 'lease.property';

    protected $fillable = [
        'lease_id', 'template_id', 'final_content', 'content_hash', 'status',
        'generated_at', 'signed_at', 'tenant_signature', 'landlord_signature',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'signed_at' => 'datetime',
            'tenant_signature' => 'array',
            'landlord_signature' => 'array',
        ];
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function template()
    {
        return $this->belongsTo(Contract::class, 'template_id');
    }

    public function signatures()
    {
        return $this->hasMany(ContractSignature::class, 'contract_id');
    }

    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class, 'contract_id');
    }
}
