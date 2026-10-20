@extends('layouts.base', ['area' => 'admin'])

@section('title', 'Pix da cobrança — AlugaPro')

@section('content')
<div class="page-head">
    <div>
        <a style="color:var(--blue);font-weight:700" href="{{ route('admin.leases.show', $charge->lease) }}#cobrancas">← Aluguel #{{ $charge->lease_id }}</a>
        <h1 style="margin-top:8px">Pix da cobrança</h1>
        <p>{{ $charge->type === 'solar' ? 'Energia solar' : 'Aluguel' }} · {{ $charge->reference_month->translatedFormat('F/Y') }} · {{ $charge->lease->property->title }}</p>
    </div>
    <x-status :value="$charge->status"/>
</div>

<section class="card" style="margin-bottom:20px">
    <div class="grid-3">
        <div><small style="color:var(--muted)">Tipo</small><strong style="display:block;font-size:18px">{{ $charge->type === 'solar' ? 'Energia solar' : 'Aluguel' }}</strong></div>
        <div><small style="color:var(--muted)">Referência</small><strong style="display:block;font-size:18px">{{ $charge->reference_month->translatedFormat('F/Y') }}</strong></div>
        <div><small style="color:var(--muted)">Valor</small><strong style="display:block;font-size:18px">R$ {{ number_format((float) $charge->amount, 2, ',', '.') }}</strong></div>
    </div>

    @if($charge->status === 'open')
        @if($pixReady)
            <form method="post" action="{{ route('admin.charges.pix', $charge) }}" class="form-actions" style="position:static">
                @csrf
                <button class="btn" type="submit"><x-icon name="money"/> Gerar novo Pix</button>
            </form>
        @else
            <div class="alert" style="background:#fff6db;color:#805800;margin-top:20px;margin-bottom:0">
                <x-icon name="money"/>
                <span>A chave Pix deste grupo ainda não tem um formato válido. <a href="{{ route('admin.groups.edit', $charge->lease->property->group) }}" style="font-weight:800;text-decoration:underline">Corrigir chave Pix</a>.</span>
            </div>
        @endif
    @else
        <div class="alert alert-success" style="margin-top:20px;margin-bottom:0"><x-icon name="check"/> Esta cobrança já foi baixada. O histórico permanece disponível para consulta.</div>
    @endif
</section>

<section class="card">
    <div class="page-head">
        <div><h2>Histórico de Pix gerados</h2><p>Todos os códigos emitidos para esta cobrança.</p></div>
        <span class="badge badge-success">{{ $charge->pixPayments->count() }} registro(s)</span>
    </div>
    <div class="table-wrap"><table class="responsive">
        <thead><tr><th>Gerado por</th><th>Data e hora</th><th>Código Pix</th><th>Status</th><th>Comprovante</th></tr></thead>
        <tbody>
        @forelse($charge->pixPayments as $pix)
            <tr>
                <td data-label="Gerado por"><strong>{{ $pix->generator_label }}</strong></td>
                <td data-label="Data e hora">{{ $pix->created_at->format('d/m/Y H:i') }}</td>
                <td data-label="Código Pix">
                    <textarea id="pix-history-code-{{ $pix->id }}" class="pix-code pix-code-compact" readonly>{{ $pix->br_code }}</textarea>
                    <button class="btn btn-outline btn-sm" type="button" data-copy="#pix-history-code-{{ $pix->id }}">Copiar código</button>
                </td>
                <td data-label="Status"><span class="badge {{ $pix->status === 'paid' ? 'badge-success' : ($pix->display_status === 'Ativo' ? 'badge-warning' : 'badge-danger') }}">{{ $pix->display_status }}</span></td>
                <td data-label="Comprovante">
                    @if($pix->receipt_original_name)
                        <a class="btn btn-outline btn-sm" href="{{ route('admin.pix.receipt', $pix) }}" target="_blank"><x-icon name="file"/> Ver documento</a>
                        <small style="display:block;color:var(--muted);margin-top:5px">{{ $pix->receipt_uploaded_at?->format('d/m/Y H:i') }}</small>
                    @else
                        <span style="color:var(--muted)">Não anexado</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Nenhum Pix foi gerado para esta cobrança.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
