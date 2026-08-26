<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAccessCode extends Model
{
    protected $fillable = [
        'client_id',
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
