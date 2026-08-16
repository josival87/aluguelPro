@extends('layouts.base', ['area' => 'admin'])

@section('title', 'Dashboard — AlugaPro')

@push('head')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="page-head">
    <div>
        <h1>Visão geral</h1>
        <p>{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</p>
    </div>
    <div class="head-actions dashboard-actions">
        <a class="btn btn-outline" href="{{ route('admin.solar.create') }}"><x-icon name="sun"/><span>Nova medição</span></a>
        <details class="dashboard-charge-menu">
            <summary class="btn btn-outline"><x-icon name="calendar"/><span>Cobranças</span><x-icon name="chevron" size="16"/></summary>
            <div class="dashboard-charge-options">
                <strong>Selecione o grupo</strong>
                @forelse($groups as $group)
                    <a href="{{ route('admin.charges.index', ['group' => $group->id]) }}">{{ $group->name }}</a>
                @empty
                    <span>Nenhum grupo cadastrado</span>
                @endforelse
            </div>
        </details>
        <a class="btn" href="{{ route('admin.properties.create') }}"><x-icon name="plus"/><span>Novo imóvel</span></a>
    </div>
</div>

<div class="metrics">
    <div class="card metric">
        <div class="metric-top"><span>Recebido no mês</span><span class="metric-icon"><x-icon name="money"/></span></div>
        <strong>R$ {{ number_format((float) $metrics['received'], 2, ',', '.') }}</strong>
        <small>Valores baixados</small>
    </div>
    <div class="card metric">
        <div class="metric-top"><span>A receber</span><span class="metric-icon"><x-icon name="calendar"/></span></div>
        <strong>R$ {{ number_format((float) $metrics['receivable'], 2, ',', '.') }}</strong>
        <small>Em aberto no mês</small>
    </div>
    <div class="card metric">
        <div class="metric-top"><span>Total de imóveis</span><span class="metric-icon"><x-icon name="building"/></span></div>
        <strong>{{ $metrics['properties'] }}</strong>
        <small>{{ number_format($metrics['occupancy'], 1, ',', '.') }}% ocupados</small>
    </div>
    <div class="card metric">
        <div class="metric-top"><span>Aluguéis vigentes</span><span class="metric-icon"><x-icon name="key"/></span></div>
        <strong>{{ $metrics['active_leases'] }}</strong>
        <small>Contratos ativos no momento</small>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="page-head">
            <div><h2>Propostas pendentes</h2><p>Aguardando finalização</p></div>
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.leases.index', ['status' => 'awaiting_completion']) }}">Ver todas</a>
        </div>
        @forelse($pendingLeases as $lease)
            <a class="list-row" href="{{ route('admin.leases.show', $lease) }}">
                <span class="metric-icon"><x-icon name="user"/></span>
                <span class="list-row-main"><strong>{{ $lease->client->name }}</strong><small>{{ $lease->property->title }} · há {{ $lease->created_at->diffForHumans(null, true) }}</small></span>
                <x-icon name="chevron"/>
            </a>
        @empty
            <div class="empty">Nenhuma proposta pendente.</div>
        @endforelse
    </section>

    <section class="card">
        <div class="page-head">
            <div><h2>Próximos vencimentos</h2><p>Cobranças em aberto</p></div>
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.charges.index') }}">Calendário</a>
        </div>
        @forelse($upcoming as $charge)
            <a class="list-row" href="{{ route('admin.leases.show', $charge->lease) }}">
                <span class="metric-icon"><x-icon name="{{ $charge->type === 'solar' ? 'sun' : 'home' }}"/></span>
                <span class="list-row-main"><strong>{{ $charge->lease->property->title }}</strong><small>{{ $charge->client->name }} · {{ $charge->due_date->format('d/m') }}</small></span>
                <span class="amount">R$ {{ number_format((float) $charge->amount, 2, ',', '.') }}</span>
            </a>
        @empty
            <div class="empty">Sem cobranças em aberto.</div>
        @endforelse
    </section>
</div>
@endsection
