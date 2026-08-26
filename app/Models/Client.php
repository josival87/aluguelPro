<?php

namespace App\Models;

use App\Support\Cpf;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['user_id', 'name', 'phone', 'cpf', 'rg', 'profession', 'email', 'family_income', 'status'];

    protected function casts(): array
    {
        return ['family_income' => 'decimal:2'];
    }

    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => Cpf::digits($value),
        );
    }

    protected function cpfFormatted(): Attribute
    {
        return Attribute::get(fn () => Cpf::format($this->cpf));
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
