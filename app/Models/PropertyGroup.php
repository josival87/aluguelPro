<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyGroup extends Model
{
    protected $table = 'groups';

    protected $fillable = ['name', 'responsible_name', 'phone', 'pix_key'];

    public function properties() { return $this->hasMany(Property::class, 'group_id'); }
    public function rules() { return $this->hasMany(CondominiumRule::class, 'group_id'); }
}
