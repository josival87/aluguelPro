<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = ['title', 'content', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function properties() { return $this->hasMany(Property::class); }
    public function leaseContracts() { return $this->hasMany(LeaseContract::class, 'template_id'); }
}
