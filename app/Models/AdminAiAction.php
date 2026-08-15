<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAiAction extends Model
{
    protected $fillable = [
        'conversation_id', 'message_id', 'user_id', 'action', 'parameters',
        'target_type', 'target_id', 'status', 'result', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'result' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(AdminAiConversation::class, 'conversation_id');
    }

    public function message()
    {
        return $this->belongsTo(AdminAiMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
