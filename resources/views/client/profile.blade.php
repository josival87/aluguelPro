@extends('layouts.base', ['area' => 'client'])

@section('title', 'Dados pessoais — Minha área')

@section('content')
<div class="page-head">
    <div>
        <a style="color:var(--blue);font-weight:700" href="{{ route('client.dashboard') }}">← Minha área</a>
        <h1 style="margin-top:8px">Dados pessoais</h1>
        <p>Consulte seus dados cadastrais e mantenha suas informações de contato atualizadas.</p>
    </div>
</div>

<form class="card form-card" method="post" action="{{ route('client.profile.update') }}">
    @csrf
    @method('PUT')

    <div class="form-grid">
        <div class="field span-2">
            <label for="profile-name">Nome completo</label>
            <input id="profile-name" value="{{ $client->name }}" readonly aria-readonly="true">
            <small>Para alterar o nome, entre em contato com a administradora.</small>
        </div>
        <div class="field">
            <label for="profile-cpf">CPF</label>
            <input id="profile-cpf" value="{{ $client->cpf_formatted }}" readonly aria-readonly="true">
            <small>O CPF não pode ser alterado pelo portal.</small>
        </div>
        <div class="field">
            <label for="profile-rg">RG</label>
            <input id="profile-rg" name="rg" value="{{ old('rg', $client->rg) }}" required maxlength="30" placeholder="Número e órgão expedidor">
        </div>
        <div class="field">
            <label for="profile-profession">Profissão</label>
            <input id="profile-profession" name="profession" value="{{ old('profession', $client->profession) }}" maxlength="255">
        </div>
        <div class="field">
            <label for="profile-phone">WhatsApp</label>
            <input id="profile-phone" name="phone" value="{{ old('phone', $client->phone) }}" required maxlength="20" inputmode="tel" autocomplete="tel">
        </div>
        <div class="field">
            <label for="profile-email">E-mail</label>
            <input id="profile-email" type="email" name="email" value="{{ old('email', $client->email) }}" maxlength="255" autocomplete="email">
        </div>
        <div class="field">
            <label for="profile-family-income">Renda familiar</label>
            <input id="profile-family-income" type="number" step="0.01" min="0" name="family_income" value="{{ old('family_income', $client->family_income) }}">
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('client.dashboard') }}">Cancelar</a>
        <button class="btn" type="submit">Salvar alterações</button>
    </div>
</form>

<section class="card form-card" style="margin-top:20px" id="documentos">
    <div class="page-head" style="margin-bottom:16px">
        <div>
            <h2>Meus documentos</h2>
            <p>Envie documentos em PDF, JPG ou PNG e consulte os arquivos já cadastrados.</p>
        </div>
        <span class="badge badge-success">{{ $client->documents->count() }} arquivo(s)</span>
    </div>

    <form method="post" enctype="multipart/form-data" action="{{ route('client.documents.store') }}">
        @csrf
        <div class="field">
            <label for="profile-documents">Adicionar documentos</label>
            <input id="profile-documents" type="file" name="documents[]" accept="application/pdf,image/jpeg,image/png" multiple required>
            <small>Até 5 arquivos por envio e no máximo 8 MB por arquivo.</small>
        </div>
        <div class="form-actions">
            <button class="btn btn-outline" type="submit"><x-icon name="plus"/> Enviar documentos</button>
        </div>
    </form>

    <div style="margin-top:20px">
        <h3>Documentos cadastrados</h3>
        @forelse($client->documents->sortByDesc('created_at') as $document)
            <a class="list-row" href="{{ route('client.documents.show', $document) }}" target="_blank" rel="noopener">
                <span class="metric-icon"><x-icon name="file"/></span>
                <span class="list-row-main">
                    <strong>{{ $document->original_name }}</strong>
                    <small>Enviado em {{ $document->created_at->format('d/m/Y') }}</small>
                </span>
                <span style="color:var(--blue);font-weight:700">Abrir</span>
                <x-icon name="chevron"/>
            </a>
        @empty
            <div class="empty">Nenhum documento cadastrado.</div>
        @endforelse
    </div>
</section>
@endsection
