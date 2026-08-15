@extends('layouts.base', ['area'=>'admin'])
@section('title','Contratos — AlugaPro')
@section('content')
<div class="page-head">
    <div><h1>Contratos</h1><p>Modelos-base usados para gerar uma versão independente em cada aluguel.</p></div>
    <a class="btn" href="{{ route('admin.contracts.create') }}"><x-icon name="plus"/> Novo contrato</a>
</div>
<section class="card">
    <div class="table-wrap"><table class="responsive">
        <thead><tr><th>Título</th><th>Imóveis</th><th>Versões geradas</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($contracts as $contract)
            <tr>
                <td data-label="Título"><strong>{{ $contract->title }}</strong><small style="display:block;color:var(--muted)">{{ Str::limit(strip_tags($contract->content), 90) }}</small></td>
                <td data-label="Imóveis">{{ $contract->properties_count }}</td>
                <td data-label="Versões geradas">{{ $contract->lease_contracts_count }}</td>
                <td data-label="Status"><x-status :value="$contract->active?'active':'inactive'"/></td>
                <td><div class="head-actions"><a class="btn btn-ghost btn-sm" href="{{ route('admin.contracts.show',$contract) }}">Visualizar</a><a class="btn btn-outline btn-sm" href="{{ route('admin.contracts.edit',$contract) }}"><x-icon name="edit"/> Editar</a></div></td>
            </tr>
        @empty<tr><td colspan="5" class="empty">Nenhum contrato-base cadastrado.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $contracts->links() }}
</section>
@endsection
