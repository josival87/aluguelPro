<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['user_id', 'name', 'phone', 'cpf', 'rg', 'profession', 'email', 'family_income', 'status'];

    protected function casts(): array
    {
        return ['family_income' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }
}
