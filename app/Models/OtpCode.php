<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['contract_id', 'signer_type', 'phone', 'code_hash', 'attempts', 'expires_at', 'used_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime']; }
    public function contract() { return $this->belongsTo(LeaseContract::class, 'contract_id'); }
}
