@extends('layouts.base', ['area' => 'public'])

@section('title', 'Candidatura — '.$property->title)

@push('head')
<link rel="stylesheet" href="{{ asset('css/property-media.css') }}">
@endpush

@section('content')
<div class="page-head"><div><h1>Vamos começar sua proposta</h1><p>Preencha seus dados e anexe um documento. O responsável analisará o cadastro.</p></div></div>
<div class="application-shell">
    <aside class="card summary-property">
        @php($cover = $property->media->first())
        <div class="property-photo" style="height:175px;border-radius:12px">
            @if($cover?->is_image)
                <img src="{{ route('property-media.show', $cover) }}" alt="{{ $property->title }}">
            @elseif($cover?->is_video)
                <video src="{{ route('property-media.show', $cover) }}" controls preload="metadata"></video>
            @endif
        </div>
        <h3 style="margin:15px 0 4px">{{ $property->title }}</h3>
        <p style="color:var(--muted);margin-top:0">{{ $property->neighborhood }}, {{ $property->city }}</p>
        <strong style="font-size:21px;color:var(--blue)">R$ {{ number_format((float) $property->rent_amount, 2, ',', '.') }}/mês</strong>
    </aside>

    <form class="card" method="post" action="{{ route('properties.apply', $property) }}" enctype="multipart/form-data">
        @csrf
        <h2>Dados pessoais</h2>
        <div class="form-grid">
            <div class="field"><label>Nome completo</label><input name="name" value="{{ old('name') }}" required autocomplete="name"></div>
            <div class="field"><label>CPF</label><input name="cpf" value="{{ old('cpf') }}" required inputmode="numeric" placeholder="000.000.000-00"></div>
            <div class="field"><label>RG</label><input name="rg" value="{{ old('rg') }}" required maxlength="30" placeholder="Número e órgão expedidor"></div>
            <div class="field"><label>Profissão</label><input name="profession" value="{{ old('profession') }}" required maxlength="255" placeholder="Ex.: Analista de sistemas"></div>
            <div class="field"><label>WhatsApp</label><input name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel"></div>
            <div class="field"><label>Renda familiar</label><input name="family_income" value="{{ old('family_income') }}" required inputmode="decimal" type="number" step="0.01" min="0"></div>
            <div class="field span-2"><label>E-mail para contato</label><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"><small>O acesso à área do cliente será feito com seu CPF.</small></div>
            <div class="field"><label>Crie uma senha</label><input type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="password-help"><small id="password-help">Use pelo menos 8 caracteres.</small></div>
            <div class="field"><label>Confirme a senha</label><input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"><small>Digite exatamente a mesma senha.</small></div>
            <div class="field span-2"><label>Documento de identificação</label><input type="file" name="document" accept="image/jpeg,image/png,application/pdf" required><small>RG ou CNH em PDF, JPG ou PNG. Até 8 MB.</small></div>
            <div class="field span-2"><label class="check-pill"><input type="checkbox" name="privacy" value="1" required> Autorizo o tratamento destes dados para análise da locação e li a política de privacidade.</label></div>
        </div>
        <div class="form-actions"><a class="btn btn-ghost" href="{{ route('properties.show', $property) }}">Cancelar</a><button class="btn" type="submit">Enviar proposta <x-icon name="arrow"/></button></div>
    </form>
</div>
@endsection
