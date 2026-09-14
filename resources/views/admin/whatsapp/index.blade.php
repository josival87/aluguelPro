@extends('layouts.base')
@section('title', 'WhatsApp — AlugaPro')

@push('styles')
<style>
    .whatsapp-status{display:flex;align-items:flex-start;gap:14px;padding:16px;border:1px solid var(--line);border-radius:14px;background:#f8faff;margin:18px 0}
    .whatsapp-status-dot{width:13px;height:13px;margin-top:4px;flex:0 0 auto;border-radius:50%;background:var(--yellow);box-shadow:0 0 0 5px rgba(217,148,0,.12)}
    .whatsapp-status[data-status="connected"] .whatsapp-status-dot{background:var(--green);box-shadow:0 0 0 5px rgba(10,155,104,.12)}
    .whatsapp-status[data-status="error"] .whatsapp-status-dot{background:var(--red);box-shadow:0 0 0 5px rgba(217,45,32,.1)}
    .whatsapp-status strong,.whatsapp-status small{display:block}.whatsapp-status small{color:var(--muted);margin-top:3px}
    .whatsapp-automation-card,.whatsapp-test-grid{margin-top:20px}.whatsapp-test-grid .card{height:max-content}
    .whatsapp-automation-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:18px}
    .whatsapp-automation-head p{margin:4px 0 0;color:var(--muted)}.whatsapp-automation-list{display:grid;gap:14px}
    .whatsapp-automation-item{border:1px solid var(--line);border-radius:16px;padding:18px;background:#fbfcff}
    .whatsapp-automation-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}
    .whatsapp-automation-title h3{margin:0 0 4px}.whatsapp-automation-title p{margin:0;color:var(--muted)}
    .whatsapp-automation-recipient{display:inline-flex;white-space:nowrap;border-radius:999px;padding:6px 10px;background:#eaf1ff;color:#174ea6;font-size:.84rem;font-weight:700}
    .whatsapp-automation-item textarea{min-height:115px}.whatsapp-variables{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px;color:var(--muted);font-size:.86rem}
    .whatsapp-variables code{border:1px solid var(--line);border-radius:7px;padding:3px 6px;background:#fff;color:var(--ink)}
    .whatsapp-template-fields{display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-top:14px}
    .whatsapp-help{margin:0;padding-left:20px;color:var(--muted)}.whatsapp-help li+li{margin-top:7px}
    .whatsapp-code{overflow-wrap:anywhere;padding:10px 12px;border-radius:10px;background:#f3f6fb;border:1px solid var(--line);font-size:.9rem}
    @media(max-width:820px){.whatsapp-test-grid,.whatsapp-template-fields{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-head">
    <div>
        <h1>Configuração do WhatsApp</h1>
        <p>Integração direta com a WhatsApp Cloud API oficial da Meta.</p>
    </div>
</div>

<form class="card" method="post" action="{{ route('admin.whatsapp.update') }}">
    @csrf
    @method('PUT')
    <h2>Credenciais da Meta</h2>
    <p class="muted">As credenciais sensíveis são criptografadas no banco e nunca voltam preenchidas para o navegador.</p>

    <div class="form-grid">
        <div class="field">
            <label for="graph_api_version">Versão da Graph API</label>
            <input id="graph_api_version" name="graph_api_version" value="{{ old('graph_api_version', $setting->graph_api_version) }}" placeholder="v26.0" required>
        </div>
        <div class="field">
            <label for="phone_number_id">Identificação do número de telefone</label>
            <input id="phone_number_id" name="phone_number_id" inputmode="numeric" value="{{ old('phone_number_id', $setting->phone_number_id) }}" required>
        </div>
        <div class="field span-2">
            <label for="business_account_id">WhatsApp Business Account ID (WABA)</label>
            <input id="business_account_id" name="business_account_id" inputmode="numeric" value="{{ old('business_account_id', $setting->business_account_id) }}" required>
        </div>
        <div class="field span-2">
            <label for="access_token">Token de acesso</label>
            <input id="access_token" name="access_token" type="password" autocomplete="new-password" placeholder="{{ filled($setting->access_token) ? 'Deixe em branco para manter o token atual' : 'Token de usuário do sistema ou token temporário' }}" {{ filled($setting->access_token) ? '' : 'required' }}>
            <small>Para produção, use um token permanente de usuário do sistema com <code>whatsapp_business_messaging</code> e <code>whatsapp_business_management</code>.</small>
        </div>
        <div class="field span-2">
            <label for="app_secret">Chave secreta do aplicativo (App Secret) — para webhook</label>
            <input id="app_secret" name="app_secret" type="password" autocomplete="new-password" placeholder="{{ filled($setting->app_secret) ? 'Deixe em branco para manter a chave atual' : 'Opcional para o primeiro envio; encontrada em Configurações do app → Básico' }}">
            <small>Usada somente no servidor para validar a assinatura <code>X-Hub-Signature-256</code> dos webhooks.</small>
        </div>
        <div class="field span-2">
            <label for="webhook_verify_token">Token de verificação do webhook — para webhook</label>
            <input id="webhook_verify_token" name="webhook_verify_token" type="password" autocomplete="new-password" minlength="16" placeholder="{{ filled($setting->webhook_verify_token) ? 'Deixe em branco para manter o token atual' : 'Opcional para o primeiro envio; use pelo menos 16 caracteres' }}">
            <small>Você escolherá este valor e informará o mesmo token no painel da Meta.</small>
        </div>
    </div>

    <div class="form-actions"><button class="btn">Salvar credenciais</button></div>
</form>

<section class="card" style="margin-top:20px">
    <h2>Validação e webhook</h2>
    <div id="whatsapp-status" class="whatsapp-status" data-status="{{ $setting->connection_status ?: 'configured' }}">
        <span class="whatsapp-status-dot"></span>
        <div>
            <strong id="whatsapp-status-title">{{ $setting->connection_status === 'connected' ? 'Integração validada' : ($setting->connection_status === 'error' ? 'Erro na integração' : 'Aguardando validação') }}</strong>
            <small id="whatsapp-status-message">
                @if($setting->last_error)
                    {{ $setting->last_error }}
                @elseif($setting->last_connected_at)
                    Última validação em {{ $setting->last_connected_at->format('d/m/Y H:i') }}{{ $setting->connected_phone ? ' — '.$setting->connected_phone : '' }}.
                @else
                    Salve as credenciais e valide o acesso à conta da Meta.
                @endif
            </small>
        </div>
    </div>

    <div class="field">
        <label>URL de retorno para configurar na Meta</label>
        <div class="whatsapp-code">{{ route('webhooks.meta.whatsapp') }}</div>
        <small>Assine o campo <code>messages</code>. O botão abaixo também vincula este aplicativo à WABA informada.</small>
    </div>
    <div class="form-actions">
        <button id="whatsapp-verify" class="btn" type="button" @disabled(! $setting->isConfigured() || ! $setting->hasWebhookSecurity())>Validar integração e assinar webhook</button>
    </div>
</section>

<form class="card whatsapp-automation-card" method="post" action="{{ route('admin.whatsapp.automations.update') }}">
    @csrf
    @method('PUT')
    <div class="whatsapp-automation-head">
        <div>
            <h2>Mensagens automáticas e modelos</h2>
            <p>O texto mantém a prévia e o histórico. O nome do modelo aprovado permite envio proativo fora da janela de 24 horas.</p>
        </div>
        <button class="btn">Salvar mensagens e modelos</button>
    </div>

    <div class="whatsapp-automation-list">
        @foreach($automations as $automation)
            @php($definition = $automation->definition())
            <article class="whatsapp-automation-item">
                <div class="whatsapp-automation-title">
                    <div><h3>{{ $definition['name'] }}</h3><p>{{ $definition['schedule'] }}</p></div>
                    <span class="whatsapp-automation-recipient">{{ $definition['recipient'] }}</span>
                </div>
                <div class="field">
                    <label for="message_{{ $automation->key }}">Prévia registrada no AlugaPro</label>
                    <textarea id="message_{{ $automation->key }}" name="messages[{{ $automation->key }}]" required>{{ old('messages.'.$automation->key, $automation->message) }}</textarea>
                    <div class="whatsapp-variables">
                        @foreach(['cliente','valor','valor_atualizado','vencimento','dias_atraso','imovel','grupo','descricao'] as $variable)
                            @php($marker = '{'.'{'.$variable.'}'.'}')
                            @if(str_contains($automation->message, $marker))<code>{{ $marker }}</code>@endif
                        @endforeach
                    </div>
                </div>
                <div class="whatsapp-template-fields">
                    <div class="field">
                        <label>Nome do modelo aprovado na Meta</label>
                        <input name="templates[{{ $automation->key }}][name]" value="{{ old('templates.'.$automation->key.'.name', data_get($setting->message_templates, $automation->key.'.name')) }}" placeholder="Ex.: alugapro_vencimento_hoje">
                    </div>
                    <div class="field">
                        <label>Idioma</label>
                        <input name="templates[{{ $automation->key }}][language]" value="{{ old('templates.'.$automation->key.'.language', data_get($setting->message_templates, $automation->key.'.language', 'pt_BR')) }}" placeholder="pt_BR">
                    </div>
                </div>
                <small>Parâmetros do corpo, nesta ordem: {{ implode(', ', $definition['template_parameters']) }}.</small>
            </article>
        @endforeach

        @foreach($templateEvents as $key => $definition)
            <article class="whatsapp-automation-item">
                <div class="whatsapp-automation-title">
                    <div><h3>{{ $definition['name'] }}</h3><p>Modelo usado pelo fluxo interno correspondente.</p></div>
                    <span class="whatsapp-automation-recipient">Sistema</span>
                </div>
                <div class="whatsapp-template-fields">
                    <div class="field">
                        <label>Nome do modelo aprovado na Meta</label>
                        <input name="templates[{{ $key }}][name]" value="{{ old('templates.'.$key.'.name', data_get($setting->message_templates, $key.'.name')) }}" placeholder="Ex.: alugapro_{{ $key }}">
                    </div>
                    <div class="field">
                        <label>Idioma</label>
                        <input name="templates[{{ $key }}][language]" value="{{ old('templates.'.$key.'.language', data_get($setting->message_templates, $key.'.language', 'pt_BR')) }}" placeholder="pt_BR">
                    </div>
                </div>
                <small>Parâmetros do corpo, nesta ordem: {{ implode(', ', $definition['parameters']) }}.</small>
            </article>
        @endforeach
    </div>
</form>

<div class="grid-2 whatsapp-test-grid">
    <form class="card" method="post" action="{{ route('admin.whatsapp.test.template') }}">
        @csrf
        <h2>Primeiro teste com o número da Meta</h2>
        <p class="muted">Use o modelo oficial <code>hello_world</code>, disponível no ambiente de teste.</p>
        <div class="field"><label for="template_phone">Seu número autorizado como destinatário</label><input id="template_phone" name="phone" value="{{ old('phone') }}" placeholder="+55 81 99999-9999" required></div>
        <div class="field"><label for="template_name">Modelo</label><input id="template_name" name="template_name" value="{{ old('template_name', 'hello_world') }}" required></div>
        <div class="field"><label for="language_code">Idioma</label><input id="language_code" name="language_code" value="{{ old('language_code', 'en_US') }}" required></div>
        <div class="form-actions"><button class="btn">Enviar modelo de teste</button></div>
    </form>

    <form class="card" method="post" action="{{ route('admin.whatsapp.test.text') }}">
        @csrf
        <h2>Testar texto livre</h2>
        <p class="muted">Disponível quando o destinatário iniciou uma conversa e a janela de atendimento de 24 horas está aberta.</p>
        <div class="field"><label for="text_phone">Destino</label><input id="text_phone" name="phone" value="{{ old('phone') }}" placeholder="+55 81 99999-9999" required></div>
        <div class="field"><label for="message">Mensagem</label><textarea id="message" name="message" required>{{ old('message', 'Teste de comunicação do AlugaPro via WhatsApp Cloud API.') }}</textarea></div>
        <div class="form-actions"><button class="btn">Enviar texto</button></div>
    </form>

    <form class="card" method="post" enctype="multipart/form-data" action="{{ route('admin.whatsapp.test.image') }}">
        @csrf
        <h2>Testar imagem</h2>
        <p class="muted">JPEG ou PNG de até 5 MB; também depende da janela de atendimento aberta.</p>
        <div class="field"><label for="image_phone">Destino</label><input id="image_phone" name="phone" value="{{ old('phone') }}" placeholder="+55 81 99999-9999" required></div>
        <div class="field"><label for="image">Imagem</label><input id="image" name="image" type="file" accept="image/jpeg,image/png" required></div>
        <div class="field"><label for="caption">Legenda</label><textarea id="caption" name="caption">{{ old('caption') }}</textarea></div>
        <div class="form-actions"><button class="btn">Enviar imagem</button></div>
    </form>

    <section class="card">
        <h2>Sequência de ativação</h2>
        <ol class="whatsapp-help">
            <li>Salve o Phone Number ID, WABA ID e token de acesso para habilitar os envios.</li>
            <li>Para acompanhar entregas, adicione o App Secret e um token de verificação; na Meta, configure a URL de retorno acima e assine o campo <code>messages</code>.</li>
            <li>Clique em “Validar integração e assinar webhook”.</li>
            <li>No ambiente de teste da Meta, autorize seu número como destinatário.</li>
            <li>Envie o modelo <code>hello_world</code>. Textos e imagens livres funcionam depois que você responder ao número de teste.</li>
        </ol>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.querySelector('#whatsapp-verify');
    const box = document.querySelector('#whatsapp-status');
    const title = document.querySelector('#whatsapp-status-title');
    const message = document.querySelector('#whatsapp-status-message');
    if (!button) return;

    button.addEventListener('click', async () => {
        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'Validando...';
        try {
            const response = await fetch(@json(route('admin.whatsapp.verify')), {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Não foi possível validar a integração.');
            box.dataset.status = 'connected';
            title.textContent = 'Integração validada';
            message.textContent = `${result.message}${result.phone ? ` Número: ${result.phone}.` : ''}`;
        } catch (error) {
            box.dataset.status = 'error';
            title.textContent = 'Erro na integração';
            message.textContent = error.message;
        } finally {
            button.disabled = false;
            button.textContent = original;
        }
    });
})();
</script>
@endpush
