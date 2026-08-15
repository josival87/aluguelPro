<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAiMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversation()
    {
        return $this->belongsTo(AdminAiConversation::class, 'conversation_id');
    }

    public function actions()
    {
        return $this->hasMany(AdminAiAction::class, 'message_id');
    }
}
