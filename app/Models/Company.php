<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class Company extends Model
{
    protected $fillable = ['name', 'cnpj', 'phone', 'email', 'logo_base64', 'pix_key'];

    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            $company->singleton = true;
        });

        static::deleting(function (): void {
            throw new LogicException('O cadastro permanente da empresa não pode ser excluído.');
        });
    }
}
