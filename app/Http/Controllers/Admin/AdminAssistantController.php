<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAiConversation;
use App\Models\AdminAiMessage;
use App\Services\AdminAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AdminAssistantController extends Controller
{
    public function index(Request $request, ?AdminAiConversation $conversation = null)
    {
        abort_if($conversation && $conversation->user_id !== $request->user()->id, 404);

        $conversations = AdminAiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $messages = $conversation
            ? $conversation->messages()->orderBy('id')->get()
            : collect();
        $actions = $conversation
            ? $conversation->actions()->get()->keyBy('id')
            : collect();

        return view('admin.assistant.index', compact('conversation', 'conversations', 'messages', 'actions'));
    }

    public function store(Request $request, AdminAssistantService $assistant)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $conversation = isset($data['conversation_id'])
            ? AdminAiConversation::query()->where('user_id', $request->user()->id)->findOrFail($data['conversation_id'])
            : AdminAiConversation::query()->create([
                'user_id' => $request->user()->id,
                'title' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $data['prompt'])), 70),
                'last_message_at' => now(),
            ]);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => trim($data['prompt']),
        ]);

        try {
            $result = $assistant->respond($conversation, $request->user(), $userMessage);
        } catch (Throwable $exception) {
            Log::error('Erro ao processar comando do assistente administrativo.', [
                'conversation_id' => $conversation->id,
                'exception' => $exception->getMessage(),
            ]);
            $result = [
                'message' => 'Não consegui concluir esse comando. Nenhuma nova alteração foi realizada; tente novamente ou confira os dados informados.',
                'provider' => 'error',
                'action_ids' => [],
            ];
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['message'],
            'metadata' => ['provider' => $result['provider'], 'action_ids' => $result['action_ids']],
        ]);
        $conversation->update(['last_message_at' => now()]);

        return redirect()->route('admin.assistant.index', $conversation)->withFragment('ultima-mensagem');
    }
}
