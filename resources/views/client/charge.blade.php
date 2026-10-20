@extends('layouts.base', ['area'=>'client'])
@section('title','Cobrança — AlugaPro')
@section('content')
@php($staticPayments = $charge->pixPayments->where('provider', 'local_emv_static'))
@php($payment = session('payment_id')
    ? $staticPayments->firstWhere('id', session('payment_id'))
    : ($staticPayments->first(fn ($pix) => $pix->receipt_original_name || $pix->status === 'paid')
        ?? $staticPayments->first(fn ($pix) => $pix->status === 'active' && $pix->expires_at?->isFuture())))
<div class="page-head">
    <div>
        <a style="color:var(--blue);font-weight:700" href="{{ route('client.dashboard') }}">← Voltar</a>
        <h1 style="margin-top:8px">{{ $charge->type === 'solar' ? 'Energia solar' : 'Aluguel' }}</h1>
        <p>{{ $charge->lease->property->title }} · {{ $charge->reference_month->translatedFormat('F/Y') }}</p>
    </div>
    <x-status :value="$charge->status"/>
</div>
<div class="grid-2">
    <section class="card">
        <h2>Resumo da cobrança</h2>
        <div class="money-breakdown">
            <div class="money-line"><span>Valor original</span><strong>R$ {{ number_format($payable['original'], 2, ',', '.') }}</strong></div>
            @if($payable['days_late'] > 0 && $charge->status === 'open')
                <div class="money-line"><span>Multa ({{ config('business.late_fee_percent') }}%)</span><strong>R$ {{ number_format($payable['fine'], 2, ',', '.') }}</strong></div>
                <div class="money-line"><span>Juros ({{ $payable['days_late'] }} dias)</span><strong>R$ {{ number_format($payable['interest'], 2, ',', '.') }}</strong></div>
            @endif
            <div class="money-line total"><span>Total</span><strong>R$ {{ number_format($payable['total'], 2, ',', '.') }}</strong></div>
        </div>
        <p style="color:var(--muted)">Vencimento: {{ $charge->due_date->format('d/m/Y') }}</p>
        @if($charge->status === 'open')
            <form method="post" action="{{ route('client.pix', $charge) }}">@csrf
                <button class="btn btn-block"><x-icon name="money"/> {{ $payment ? 'Gerar novo Pix' : 'Gerar Pix' }}</button>
            </form>
        @endif
    </section>
    <section class="card">
        @if($payment)
            <div class="page-head" style="margin-bottom:14px">
                <div><h2>Pix gerado</h2><p>{{ $payment->generator_label }} · {{ $payment->created_at->format('d/m/Y H:i') }}</p></div>
                <span class="badge {{ $payment->status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ $payment->display_status }}</span>
            </div>
            <textarea id="pix-code" class="pix-code" readonly aria-label="Código Pix copia e cola">{{ $payment->br_code }}</textarea>
            <button class="btn btn-block" style="margin-top:12px" type="button" data-copy="#pix-code">Copiar código Pix</button>
            @if($payment->receipt_original_name)
                <div class="alert alert-success" style="margin-top:20px"><x-icon name="check"/><span>Comprovante enviado em {{ $payment->receipt_uploaded_at?->format('d/m/Y H:i') }}.<br><a href="{{ route('client.pix.receipt', $payment) }}" target="_blank" style="font-weight:800;text-decoration:underline">Ver comprovante</a></span></div>
            @else
                <div class="payment-proof-callout">
                    <div><strong>Após pagar, envie o comprovante</strong><p>O envio é necessário para registrar a baixa desta cobrança no sistema. São aceitos PDF, JPG e PNG de até 8 MB.</p></div>
                    <form method="post" enctype="multipart/form-data" action="{{ route('client.pix.receipt.store', $payment) }}">@csrf
                        <div class="field"><label for="pix-receipt">Comprovante do pagamento</label><input id="pix-receipt" type="file" name="receipt" accept="application/pdf,image/jpeg,image/png" required></div>
                        <button class="btn btn-block" style="margin-top:12px" type="submit"><x-icon name="file"/> Enviar comprovante e dar baixa</button>
                    </form>
                </div>
            @endif
        @elseif($charge->status === 'paid')
            <div class="empty"><div class="metric-icon"><x-icon name="check"/></div><h2>Pagamento confirmado</h2><p>Pago em {{ $charge->paid_at?->format('d/m/Y H:i') }}. Esta baixa não possui comprovante anexado.</p></div>
        @else
            <div class="empty"><div class="metric-icon"><x-icon name="money"/></div><h2>Pague pelo seu banco</h2><p>Gere um código Pix para copiar e colar.</p></div>
        @endif
    </section>
</div>
@endsection
