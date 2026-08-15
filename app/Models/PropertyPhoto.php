<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    protected $fillable = ['property_id', 'mime_type', 'photo_base64', 'sort_order'];
    public function property() { return $this->belongsTo(Property::class); }
    public function getDataUriAttribute(): string { return 'data:'.$this->mime_type.';base64,'.$this->photo_base64; }
}
