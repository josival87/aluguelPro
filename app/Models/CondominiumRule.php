<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CondominiumRule extends Model
{
    protected $fillable = ['group_id', 'title', 'content'];
    public function group() { return $this->belongsTo(PropertyGroup::class, 'group_id'); }
}
