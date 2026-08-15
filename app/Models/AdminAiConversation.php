<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAiConversation extends Model
{
    protected $fillable = ['user_id', 'title', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(AdminAiMessage::class, 'conversation_id');
    }

    public function actions()
    {
        return $this->hasMany(AdminAiAction::class, 'conversation_id');
    }
}
