@extends('layouts.base', ['area' => 'admin'])
@section('title', 'Assistente IA — AlugaPro')
@push('head')<link rel="stylesheet" href="{{ asset('css/admin-assistant.css') }}">@endpush
@section('content')
<div class="page-head assistant-page-head">
    <div><h1>Assistente IA</h1><p>Converse em linguagem natural para consultar e operar o AlugaPro.</p></div>
    <a class="btn btn-outline" href="{{ route('admin.assistant.index') }}"><x-icon name="plus"/> Nova conversa</a>
</div>

<div class="assistant-layout">
    <aside class="card assistant-history">
        <h2>Conversas</h2>
        <div class="assistant-history-list">
            @forelse($conversations as $item)
                <a class="assistant-history-item {{ $conversation?->is($item) ? 'active' : '' }}" href="{{ route('admin.assistant.index', $item) }}">
                    <x-icon name="sparkles" size="17"/>
                    <span><strong>{{ $item->title }}</strong><small>{{ $item->last_message_at?->diffForHumans() }}</small></span>
                </a>
            @empty
                <p class="assistant-empty-history">Sua primeira conversa aparecerá aqui.</p>
            @endforelse
        </div>
    </aside>

    <section class="card assistant-chat">
        <header class="assistant-chat-head">
            <span class="assistant-bot-icon"><x-icon name="sparkles" size="24"/></span>
            <div><strong>Agente administrativo</strong><small>Operações autorizadas e auditadas</small></div>
        </header>

        <div class="assistant-messages" id="assistant-messages" aria-live="polite">
            @if($messages->isEmpty())
                <div class="assistant-welcome">
                    <span class="assistant-bot-icon large"><x-icon name="sparkles" size="30"/></span>
                    <h2>O que você precisa fazer?</h2>
                    <p>Uma cobrança única é alterada imediatamente. Se houver mais de uma possibilidade, nada será modificado até você esclarecer.</p>
                    <div class="assistant-suggestions">
                        <button type="button" data-assistant-prompt="Dar baixa no pagamento EBM 02 do mês de setembro">Dar baixa em um pagamento</button>
                        <button type="button" data-assistant-prompt="Liste as cobranças em aberto deste mês">Ver cobranças em aberto</button>
                        <button type="button" data-assistant-prompt="Mostre o resumo financeiro deste mês">Resumo financeiro mensal</button>
                    </div>
                </div>
            @else
                @foreach($messages as $item)
                    <article class="assistant-message {{ $item->role }}" @if($loop->last) id="ultima-mensagem" @endif>
                        <div class="assistant-message-label">{{ $item->role === 'user' ? 'Você' : 'Assistente' }}</div>
                        <div class="assistant-bubble">{!! nl2br(e($item->content)) !!}</div>
                        @if($item->role === 'assistant' && filled(data_get($item->metadata, 'action_ids')))
                            <div class="assistant-audits">
                                @foreach(data_get($item->metadata, 'action_ids', []) as $actionId)
                                    @php($action = $actions->get($actionId))
                                    @if($action)
                                        <span class="assistant-audit {{ $action->status }}">
                                            <x-icon name="{{ in_array($action->status, ['completed','no_op']) ? 'check' : 'sparkles' }}" size="14"/>
                                            {{ match($action->status) { 'completed' => 'Operação concluída', 'no_op' => 'Já estava atualizado', 'needs_clarification' => 'Aguardando esclarecimento', 'not_found' => 'Não encontrado', default => 'Operação registrada' } }}
                                            · protocolo #{{ $action->id }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            @endif
        </div>

        <form class="assistant-composer" method="post" action="{{ route('admin.assistant.messages') }}">
            @csrf
            @if($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
            <label for="assistant-prompt">Comando para o assistente</label>
            <div class="assistant-input-row">
                <textarea id="assistant-prompt" name="prompt" maxlength="2000" rows="2" required placeholder="Ex.: dar baixa no aluguel EBM 02 de setembro">{{ old('prompt') }}</textarea>
                <button class="btn assistant-send" type="submit" aria-label="Enviar comando"><x-icon name="send"/></button>
            </div>
            <small>O assistente acessa somente operações permitidas e mantém um histórico de auditoria.</small>
        </form>
    </section>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const prompt = document.getElementById('assistant-prompt');
    document.querySelectorAll('[data-assistant-prompt]').forEach(function (button) {
        button.addEventListener('click', function () {
            prompt.value = button.dataset.assistantPrompt;
            prompt.focus();
        });
    });
    const messages = document.getElementById('assistant-messages');
    if (messages) messages.scrollTop = messages.scrollHeight;
});
</script>
@endpush
