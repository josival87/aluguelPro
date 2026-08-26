<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0757e8">
    <title>Solicitar acesso — AlugaPro</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-page">
    <section class="auth-visual">
        <div>
            <span class="brand-mark">A</span>
            <h1>Seu imóvel.<br>Sua rotina.<br>Mais simples.</h1>
            <p>Confirme seu WhatsApp para criar uma senha e acessar sua área com segurança.</p>
        </div>
    </section>
    <section class="auth-form">
        <div>
            <a class="brand" href="{{ route('properties.index') }}"><span class="brand-mark">A</span>Aluga<strong>Pro</strong></a>

            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

            @if($step === 'phone')
                <p class="auth-step">Etapa 1 de 3</p>
                <h1>Solicitar acesso</h1>
                <p class="auth-description">Informe o telefone que está cadastrado no AlugaPro. Enviaremos um código pelo WhatsApp.</p>
                <form method="post" action="{{ route('access.send') }}" class="stack">
                    @csrf
                    <div class="field">
                        <label for="phone">Telefone / WhatsApp</label>
                        <input id="phone" name="phone" value="{{ old('phone') }}" required autofocus inputmode="tel" autocomplete="tel" placeholder="(81) 99999-9999">
                    </div>
                    <button class="btn btn-block" type="submit">Enviar código pelo WhatsApp</button>
                </form>
            @elseif($step === 'code')
                <p class="auth-step">Etapa 2 de 3</p>
                <h1>Confirme o código</h1>
                <p class="auth-description">Digite o código de 6 números enviado para <strong>{{ $maskedPhone }}</strong>.</p>
                @if(session('dev_otp'))<div class="alert auth-dev-code">Código de desenvolvimento: <strong>{{ session('dev_otp') }}</strong></div>@endif
                <form method="post" action="{{ route('access.code.verify') }}" class="stack">
                    @csrf
                    <div class="field">
                        <label for="code">Código de confirmação</label>
                        <input class="otp-input" id="code" name="code" value="{{ old('code') }}" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000">
                    </div>
                    <button class="btn btn-block" type="submit">Confirmar código</button>
                </form>
                <a class="auth-text-link" href="{{ route('access.request') }}">Informar outro telefone</a>
            @else
                <p class="auth-step">Etapa 3 de 3</p>
                <h1>Cadastre sua senha</h1>
                <p class="auth-description">Crie uma nova senha com pelo menos 8 caracteres para entrar na sua área.</p>
                <form method="post" action="{{ route('access.password.store') }}" class="stack">
                    @csrf
                    <div class="field">
                        <label for="password">Nova senha</label>
                        <input id="password" type="password" name="password" required autofocus minlength="8" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirme a nova senha</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </div>
                    <button class="btn btn-block" type="submit">Cadastrar nova senha</button>
                </form>
            @endif

            @if($step !== 'code')
                <a class="auth-text-link" href="{{ route('login') }}">Voltar para entrar</a>
            @endif
        </div>
    </section>
</div>
</body>
</html>
