<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Cpf;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'login', 'cpf', 'phone', 'role', 'active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
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

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function adminAiConversations()
    {
        return $this->hasMany(AdminAiConversation::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }
}
