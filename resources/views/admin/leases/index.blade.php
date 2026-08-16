@extends('layouts.base', ['area' => 'admin'])

@section('title', 'Aluguéis — AlugaPro')

@section('content')
<div class="page-head">
    <div><h1>Aluguéis</h1><p>Propostas e contratos ordenados do mais recente.</p></div>
    <a class="btn" href="{{ route('admin.leases.create') }}"><x-icon name="plus"/> <span>Novo aluguel</span></a>
</div>

<form class="filter-bar">
    <div class="field">
        <label>Status</label>
        <select name="status" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach([
                'awaiting_completion' => 'Aguardando finalização',
                'awaiting_signatures' => 'Aguardando assinaturas',
                'active' => 'Ativo',
                'closed' => 'Encerrado',
                'cancelled' => 'Cancelado',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="table-wrap">
    <table class="responsive">
        <thead><tr><th>ID</th><th>Cliente</th><th>Imóvel / grupo</th><th>Período</th><th>Valor</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($leases as $lease)
                <tr>
                    <td data-label="ID"><strong>#{{ $lease->id }}</strong></td>
                    <td data-label="Cliente">{{ $lease->client->name }}</td>
                    <td data-label="Imóvel"><strong>{{ $lease->property->title }}</strong><small style="display:block;color:var(--muted)">{{ $lease->property->group->name }}</small></td>
                    <td data-label="Período">{{ $lease->start_date?->format('d/m/Y') ?? 'A definir' }}<small style="display:block;color:var(--muted)">até {{ $lease->end_date?->format('d/m/Y') ?? 'a definir' }}</small></td>
                    <td data-label="Valor">R$ {{ number_format((float) $lease->rent_amount, 2, ',', '.') }}</td>
                    <td data-label="Status"><x-status :value="$lease->status"/></td>
                    <td><a class="icon-btn" href="{{ route('admin.leases.show', $lease) }}"><x-icon name="chevron" size="17"/></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Nenhum aluguel cadastrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $leases->links() }}</div>
@endsection
