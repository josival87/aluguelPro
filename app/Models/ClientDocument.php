<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDocument extends Model
{
    protected $fillable = ['client_id', 'type', 'original_name', 'mime_type', 'document_base64'];
    public function client() { return $this->belongsTo(Client::class); }
}
