@extends('layouts.base', ['area' => 'admin'])
@section('title', 'WhatsApp — AlugaPro')

@push('head')
<style>
    .whatsapp-status{display:flex;align-items:center;gap:14px;padding:16px;border:1px solid var(--line);border-radius:14px;background:#f8faff;margin-bottom:18px}
    .whatsapp-status-dot{width:13px;height:13px;border-radius:50%;background:var(--yellow);box-shadow:0 0 0 5px rgba(217,148,0,.12)}
    .whatsapp-status[data-status="connected"] .whatsapp-status-dot{background:var(--green);box-shadow:0 0 0 5px rgba(10,155,104,.12)}
    .whatsapp-status[data-status="error"] .whatsapp-status-dot,.whatsapp-status[data-status="disconnected"] .whatsapp-status-dot{background:var(--red);box-shadow:0 0 0 5px rgba(217,45,32,.1)}
    .whatsapp-status strong,.whatsapp-status small{display:block}.whatsapp-status small{color:var(--muted)}
    .whatsapp-qr{min-height:330px;display:grid;place-items:center;text-align:center;border:2px dashed #c7d5ea;border-radius:16px;background:#f8faff;padding:22px}
    .whatsapp-qr img{width:min(280px,100%);height:auto;background:#fff;border-radius:12px;padding:10px;box-shadow:var(--shadow)}
    .whatsapp-qr p{max-width:360px;color:var(--muted);margin:10px auto 0}
    .whatsapp-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.whatsapp-actions .btn{flex:1}
    .whatsapp-test-grid{margin-top:20px}.whatsapp-test-grid .card{height:max-content}
    .whatsapp-help{margin:0;padding-left:20px;color:var(--muted)}.whatsapp-help li+li{margin-top:7px}
    @media(max-width:820px){.whatsapp-qr{min-height:260px}.whatsapp-test-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-head">
    <div>
        <h1>Configuração do WhatsApp</h1>
        <p>Conecte o número da empresa ao WPPConnect e valide os envios.</p>
    </div>
</div>

<div class="grid-2">
    <form class="card" method="post" action="{{ route('admin.whatsapp.update') }}">
        @csrf
        @method('PUT')
        <h2>Servidor WPPConnect</h2>
        <div class="form-grid">
            <div class="field span-2">
                <label for="api_url">URL da API</label>
                <input id="api_url" name="api_url" type="url" value="{{ old('api_url', $setting->api_url) }}" placeholder="https://whatsapp.seudominio.com" required>
                <small>Informe somente a URL base, sem <code>/api</code> no final.</small>
            </div>
            <div class="field span-2">
                <label for="session_name">Nome da sessão</label>
                <input id="session_name" name="session_name" value="{{ old('session_name', $setting->session_name ?: 'alugapro') }}" maxlength="100" required>
                <small>Identifica esta conexão no WPPConnect. Exemplo: <code>alugapro</code>.</small>
            </div>
            <div class="field span-2">
                <label for="secret_key">Secret key</label>
                <input id="secret_key" name="secret_key" type="password" autocomplete="new-password" placeholder="{{ filled($setting->secret_key) ? 'Deixe em branco para manter a atual' : 'Secret key do WPPConnect' }}" {{ filled($setting->secret_key) ? '' : 'required' }}>
                <small>A chave e o token gerado são armazenados criptografados.</small>
            </div>
        </div>
        <div class="form-actions"><button class="btn"><x-icon name="check"/> Salvar configuração</button></div>
    </form>

    <section class="card">
        <h2>Conexão do aparelho</h2>
        <div id="whatsapp-status" class="whatsapp-status" data-status="{{ $setting->connection_status ?: 'configured' }}">
            <span class="whatsapp-status-dot"></span>
            <span>
                <strong id="whatsapp-status-title">{{ $setting->connection_status === 'connected' ? 'Conectado' : 'Não conectado' }}</strong>
                <small id="whatsapp-status-message">
                    @if($setting->connected_phone)
                        Número conectado: +{{ $setting->connected_phone }}
                    @elseif($setting->isConfigured())
                        Inicie a sessão para gerar o QR Code.
                    @else
                        Salve os dados do servidor primeiro.
                    @endif
                </small>
            </span>
        </div>

        <div id="whatsapp-qr" class="whatsapp-qr">
            <div id="whatsapp-qr-placeholder">
                <x-icon name="whatsapp" size="52"/>
                <strong id="whatsapp-qr-title">QR Code ainda não gerado</strong>
                <p id="whatsapp-qr-message">Clique em “Conectar WhatsApp” e depois leia o código em Aparelhos conectados no aplicativo.</p>
            </div>
            <img id="whatsapp-qr-image" alt="QR Code para conectar o WhatsApp" hidden>
        </div>

        <div class="whatsapp-actions">
            <button id="whatsapp-connect" class="btn" type="button" @disabled(! $setting->isConfigured())><x-icon name="whatsapp"/> Conectar WhatsApp</button>
            <button id="whatsapp-refresh" class="btn btn-outline" type="button" @disabled(! $setting->isConfigured())>Atualizar status</button>
        </div>
    </section>
</div>

<div class="grid-2 whatsapp-test-grid">
    <form class="card" method="post" action="{{ route('admin.whatsapp.test.text') }}">
        @csrf
        <h2>Testar mensagem de texto</h2>
        <div class="field">
            <label for="text_phone">Destino com DDI</label>
            <input id="text_phone" name="phone" value="{{ old('phone') }}" placeholder="+5581999999999" required>
        </div>
        <div class="field" style="margin-top:14px">
            <label for="message">Mensagem</label>
            <textarea id="message" name="message" required>{{ old('message', 'Teste de comunicação do AlugaPro via WPPConnect.') }}</textarea>
        </div>
        <div class="form-actions"><button class="btn"><x-icon name="send"/> Enviar texto</button></div>
    </form>

    <form class="card" method="post" enctype="multipart/form-data" action="{{ route('admin.whatsapp.test.image') }}">
        @csrf
        <h2>Testar mensagem com imagem</h2>
        <div class="field">
            <label for="image_phone">Destino com DDI</label>
            <input id="image_phone" name="phone" value="{{ old('phone') }}" placeholder="+5581999999999" required>
        </div>
        <div class="field" style="margin-top:14px">
            <label for="image">Imagem</label>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
            <small>JPG, PNG ou WebP de até 8 MB.</small>
        </div>
        <div class="field" style="margin-top:14px">
            <label for="caption">Legenda</label>
            <textarea id="caption" name="caption">{{ old('caption') }}</textarea>
        </div>
        <div class="form-actions"><button class="btn"><x-icon name="send"/> Enviar imagem</button></div>
    </form>
</div>

<section class="card" style="margin-top:20px">
    <h2>Como conectar</h2>
    <ol class="whatsapp-help">
        <li>Salve a URL, o nome da sessão e a secret key do servidor WPPConnect.</li>
        <li>Clique em “Conectar WhatsApp” para criar a sessão e gerar o QR Code.</li>
        <li>No celular, abra WhatsApp → Aparelhos conectados → Conectar um aparelho.</li>
        <li>Leia o QR Code. O status e o número conectado serão atualizados automaticamente.</li>
    </ol>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const configured = @json($setting->isConfigured());
    if (!configured) return;

    const statusBox = document.querySelector('#whatsapp-status');
    const statusTitle = document.querySelector('#whatsapp-status-title');
    const statusMessage = document.querySelector('#whatsapp-status-message');
    const qrImage = document.querySelector('#whatsapp-qr-image');
    const qrPlaceholder = document.querySelector('#whatsapp-qr-placeholder');
    const qrTitle = document.querySelector('#whatsapp-qr-title');
    const qrMessage = document.querySelector('#whatsapp-qr-message');
    const connectButton = document.querySelector('#whatsapp-connect');
    const refreshButton = document.querySelector('#whatsapp-refresh');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let connecting = @json($setting->connection_status === 'awaiting_qr');

    function render(result) {
        const labels = {
            connected: 'Conectado',
            awaiting_qr: 'Aguardando QR Code',
            disconnected: 'Desconectado',
            not_started: 'Sessão não iniciada',
            error: 'Falha na conexão'
        };
        statusBox.dataset.status = result.status || 'error';
        statusTitle.textContent = labels[result.status] || 'Verificando';
        statusMessage.textContent = result.phone
            ? `Número conectado: +${String(result.phone).replace(/\D/g, '')}`
            : (result.message || 'Estado da sessão atualizado.');

        if (result.qr_code) {
            qrImage.src = result.qr_code;
            qrImage.hidden = false;
            qrPlaceholder.hidden = true;
            connecting = true;
        } else if (result.connected) {
            qrImage.hidden = true;
            qrPlaceholder.hidden = false;
            qrTitle.textContent = 'WhatsApp conectado';
            qrMessage.textContent = 'Os disparos utilizarão o número exibido acima.';
            connecting = false;
        }
    }

    async function call(url, options = {}) {
        const response = await fetch(url, {
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            ...options
        });
        const result = await response.json().catch(() => ({message: 'Resposta inválida do servidor.'}));
        if (!response.ok) throw new Error(result.message || 'Não foi possível comunicar com o WPPConnect.');
        render(result);
        return result;
    }

    async function refreshStatus() {
        refreshButton.disabled = true;
        try {
            await call(@json(route('admin.whatsapp.status')));
        } catch (error) {
            render({status: 'error', connected: false, message: error.message});
        } finally {
            refreshButton.disabled = false;
        }
    }

    connectButton.addEventListener('click', async () => {
        connectButton.disabled = true;
        connectButton.textContent = 'Gerando QR Code…';
        connecting = true;
        try {
            await call(@json(route('admin.whatsapp.connect')), {method: 'POST'});
        } catch (error) {
            connecting = false;
            render({status: 'error', connected: false, message: error.message});
        } finally {
            connectButton.disabled = false;
            connectButton.textContent = 'Conectar WhatsApp';
        }
    });

    refreshButton.addEventListener('click', refreshStatus);
    refreshStatus();
    window.setInterval(() => { if (connecting) refreshStatus(); }, 4000);
});
</script>
@endpush
