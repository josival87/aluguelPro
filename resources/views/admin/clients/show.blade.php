@extends('layouts.base', ['area' => 'admin'])

@section('title', $client->name.' — AlugaPro')

@section('content')
<div class="page-head">
    <div>
        <a style="color:var(--blue);font-weight:700" href="{{ route('admin.clients.index') }}">← Clientes</a>
        <h1 style="margin-top:8px">{{ $client->name }}</h1>
        <p>{{ $client->cpf }} · {{ $client->phone }}</p>
    </div>
    <div class="head-actions">
        <x-status :value="$client->status"/>
        <a class="btn btn-outline" href="{{ route('admin.clients.edit', $client) }}"><x-icon name="edit"/> Editar</a>
    </div>
</div>

<div class="metrics">
    <div class="card metric"><div class="metric-top"><span>Em aberto</span><span class="metric-icon"><x-icon name="calendar"/></span></div><strong>R$ {{ number_format((float) $summary['open'], 2, ',', '.') }}</strong></div>
    <div class="card metric"><div class="metric-top"><span>Total pago</span><span class="metric-icon"><x-icon name="money"/></span></div><strong>R$ {{ number_format((float) $summary['paid'], 2, ',', '.') }}</strong></div>
    <div class="card metric"><div class="metric-top"><span>Contratos</span><span class="metric-icon"><x-icon name="file"/></span></div><strong>{{ $client->leases->count() }}</strong></div>
    <div class="card metric"><div class="metric-top"><span>Documentos</span><span class="metric-icon"><x-icon name="file"/></span></div><strong>{{ $client->documents->count() }}</strong></div>
</div>

<section class="card" style="margin-bottom:20px">
    <h2>Dados pessoais</h2>
    <div class="form-grid">
        <div class="field"><label>CPF</label><span>{{ $client->cpf }}</span></div>
        <div class="field"><label>RG</label><span>{{ $client->rg ?: 'Não informado' }}</span></div>
        <div class="field"><label>Profissão</label><span>{{ $client->profession ?: 'Não informada' }}</span></div>
        <div class="field"><label>WhatsApp</label><span>{{ $client->phone }}</span></div>
        <div class="field"><label>E-mail</label><span>{{ $client->email ?: 'Não informado' }}</span></div>
        <div class="field"><label>Renda familiar</label><span>{{ $client->family_income ? 'R$ '.number_format((float) $client->family_income, 2, ',', '.') : 'Não informada' }}</span></div>
    </div>
</section>

<section class="card" style="margin-bottom:20px">
    <h2>Documentos</h2>
    @forelse($client->documents->sortByDesc('created_at') as $document)
        <a class="list-row" href="{{ route('admin.clients.documents.show', [$client, $document]) }}" target="_blank" rel="noopener">
            <span class="metric-icon"><x-icon name="file"/></span>
            <span class="list-row-main">
                <strong>{{ $document->original_name }}</strong>
                <small>{{ $document->type === 'identification' ? 'Documento de identificação' : 'Documento pessoal' }}</small>
            </span>
            <span style="color:var(--blue);font-weight:700">Abrir documento</span>
            <x-icon name="chevron"/>
        </a>
    @empty
        <div class="empty">Nenhum documento anexado.</div>
    @endforelse
</section>

<section class="card">
    <h2>Histórico de aluguéis</h2>
    @forelse($client->leases as $lease)
        <a class="list-row" href="{{ route('admin.leases.show', $lease) }}">
            <span class="metric-icon"><x-icon name="home"/></span>
            <span class="list-row-main">
                <strong>{{ $lease->property->title }}</strong>
                <small>{{ $lease->start_date?->format('d/m/Y') ?? 'Início pendente' }} — {{ $lease->end_date?->format('d/m/Y') ?? 'Fim pendente' }}</small>
            </span>
            <span><small style="display:block;color:var(--muted)">Em aberto</small><strong>R$ {{ number_format((float) $lease->charges->where('status', 'open')->sum('amount'), 2, ',', '.') }}</strong></span>
            <span><small style="display:block;color:var(--muted)">Pago</small><strong>R$ {{ number_format((float) $lease->charges->where('status', 'paid')->sum('amount'), 2, ',', '.') }}</strong></span>
            <x-status :value="$lease->status"/>
        </a>
    @empty
        <div class="empty">Cliente sem aluguéis.</div>
    @endforelse
</section>
@endsection
