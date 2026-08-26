@extends('layouts.base', ['area' => 'admin'])

@section('title', ($client->exists ? 'Editar' : 'Novo').' cliente — AlugaPro')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $client->exists ? 'Editar cliente' : 'Novo cliente' }}</h1>
        <p>Dados de identificação e acesso à área do cliente.</p>
    </div>
</div>

<form class="card form-card" method="post" enctype="multipart/form-data" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}">
    @csrf
    @if($client->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field span-2">
            <label>Nome completo</label>
            <input name="name" value="{{ old('name', $client->name) }}" required>
        </div>
        <div class="field">
            <label>CPF</label>
            <input name="cpf" value="{{ \App\Support\Cpf::format(old('cpf', $client->cpf)) }}" required inputmode="numeric" maxlength="14" placeholder="000.000.000-00" data-cpf-mask>
        </div>
        <div class="field">
            <label>RG</label>
            <input name="rg" value="{{ old('rg', $client->rg) }}" required maxlength="30" placeholder="Número e órgão expedidor">
        </div>
        <div class="field">
            <label>Profissão (opcional)</label>
            <input name="profession" value="{{ old('profession', $client->profession) }}" maxlength="255">
        </div>
        <div class="field">
            <label>WhatsApp</label>
            <input name="phone" value="{{ old('phone', $client->phone) }}" required>
        </div>
        <div class="field">
            <label>E-mail para contato</label>
            <input type="email" name="email" value="{{ old('email', $client->email) }}">
            <small>O cliente entrará na área dele usando o CPF.</small>
        </div>
        <div class="field">
            <label>Renda familiar</label>
            <input type="number" step="0.01" min="0" name="family_income" value="{{ old('family_income', $client->family_income) }}">
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                @foreach(['pending' => 'Pendente', 'active' => 'Ativo', 'inactive' => 'Inativo', 'rejected' => 'Rejeitado'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $client->status ?: 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" id="documents-upload">
            <label>{{ $client->exists ? 'Adicionar documentos' : 'Documentos' }}</label>
            <input type="file" name="documents[]" accept="application/pdf,image/jpeg,image/png" multiple>
            <small>PDF, JPG ou PNG. Até 5 arquivos por envio e no máximo 8 MB por arquivo.</small>
        </div>
        <div class="field">
            <label>Senha (opcional)</label>
            <input type="password" name="password" autocomplete="new-password">
            <small>Deixe em branco para cadastrar o cliente sem acesso ao portal por enquanto.</small>
        </div>
        <div class="field">
            <label>Confirmar senha</label>
            <input type="password" name="password_confirmation" autocomplete="new-password">
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('admin.clients.index') }}">Cancelar</a>
        <button class="btn">Salvar cliente</button>
    </div>
</form>

@if($client->exists)
<section class="card" style="margin-top:20px">
    <div class="page-head" style="margin-bottom:8px">
        <div>
            <h2>Documentos existentes</h2>
            <p>Os arquivos vinculados ao cliente podem ser visualizados, mas não excluídos.</p>
        </div>
        <span class="badge badge-success">{{ $client->documents->count() }} arquivo(s)</span>
    </div>
    @forelse($client->documents->sortByDesc('created_at') as $document)
        <div class="list-row">
            <span class="metric-icon"><x-icon name="file"/></span>
            <span class="list-row-main">
                <strong>{{ $document->original_name }}</strong>
                <small>Documento de identificação</small>
            </span>
            <div class="head-actions">
                <a class="btn btn-outline btn-sm" href="{{ route('admin.clients.documents.show', [$client, $document]) }}" target="_blank" rel="noopener">Abrir</a>
            </div>
        </div>
    @empty
        <div class="empty">Nenhum documento anexado.</div>
    @endforelse
</section>
@endif
@endsection
