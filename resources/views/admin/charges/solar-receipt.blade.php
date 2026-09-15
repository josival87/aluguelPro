@extends('layouts.base', ['area' => 'admin'])

@section('title', 'Extrato de energia solar — AlugaPro')

@push('head')
<style>
    .solar-receipt-shell { max-width: 760px; margin: 0 auto; }
    .solar-receipt { overflow: hidden; padding: 0; }
    .solar-receipt-header { display: flex; justify-content: space-between; gap: 20px; padding: 24px; color: #fff; background: linear-gradient(135deg, #071a3a, #0757e8); }
    .solar-receipt-header h1 { margin: 0; font-size: 24px; }
    .solar-receipt-header p { margin: 5px 0 0; color: #dbe8ff; }
    .solar-receipt-content { display: grid; grid-template-columns: 230px 1fr; gap: 24px; padding: 24px; }
    .solar-receipt-photo { width: 100%; height: 230px; object-fit: cover; border-radius: 14px; background: #eef3fb; }
    .solar-receipt-placeholder { display: grid; place-items: center; height: 230px; padding: 18px; border-radius: 14px; color: var(--muted); text-align: center; background: #eef3fb; }
    .solar-receipt-info { display: grid; gap: 9px; }
    .solar-receipt-line { display: flex; justify-content: space-between; gap: 14px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
    .solar-receipt-line span { color: var(--muted); }
    .solar-receipt-line strong { text-align: right; }
    .solar-receipt-total { margin-top: 4px; padding-top: 12px; border-top: 2px dashed #cbd6e7; color: var(--blue); font-size: 19px; }
    .solar-receipt-footer { padding: 0 24px 24px; color: var(--muted); font-size: 12px; }
    @media (max-width: 650px) { .solar-receipt-content { grid-template-columns: 1fr; } .solar-receipt-photo, .solar-receipt-placeholder { height: 250px; } }
</style>
@endpush

@section('content')
<div class="solar-receipt-shell">
    <div class="page-head">
        <div>
            <a style="color:var(--blue);font-weight:700" href="{{ route('admin.charges.index', ['month' => $charge->reference_month->format('Y-m')]) }}">← Cobranças</a>
            <h1 style="margin-top:8px">Extrato de energia solar</h1>
            <p>Resumo da medição pronto para compartilhar com o cliente.</p>
        </div>
        <div class="head-actions">
            <button class="btn btn-outline btn-sm" type="button" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <article class="card solar-receipt">
        <header class="solar-receipt-header">
            <div>
                <h1>Extrato de energia solar</h1>
                <p>{{ $reading->reference_month->translatedFormat('F/Y') }}</p>
            </div>
            <x-icon name="sun" size="32" />
        </header>
        <div class="solar-receipt-content">
            <div>
                @if($reading->photo_base64)
                    <img class="solar-receipt-photo" src="data:{{ $reading->photo_mime_type }};base64,{{ $reading->photo_base64 }}" alt="Foto do medidor da leitura de {{ $reading->reference_month->translatedFormat('F/Y') }}">
                @else
                    <div class="solar-receipt-placeholder">Esta medição não possui foto do medidor.</div>
                @endif
            </div>
            <div class="solar-receipt-info">
                <div class="solar-receipt-line"><span>Cliente</span><strong>{{ $charge->client->name }}</strong></div>
                <div class="solar-receipt-line"><span>Imóvel</span><strong>{{ $charge->lease->property->title }}</strong></div>
                <div class="solar-receipt-line"><span>Leitura anterior</span><strong>{{ number_format((float) $reading->previous_reading, 3, ',', '.') }} kWh<br><small>{{ $previousReading?->created_at?->timezone(config('business.billing_timezone', 'America/Sao_Paulo'))->format('d/m/Y') ?? 'Leitura inicial' }}</small></strong></div>
                <div class="solar-receipt-line"><span>Leitura atual</span><strong>{{ number_format((float) $reading->meter_reading, 3, ',', '.') }} kWh<br><small>{{ $reading->created_at?->timezone(config('business.billing_timezone', 'America/Sao_Paulo'))->format('d/m/Y') ?? 'Não informada' }}</small></strong></div>
                <div class="solar-receipt-line"><span>Consumo</span><strong>{{ number_format((float) $reading->consumption_kwh, 3, ',', '.') }} kWh</strong></div>
                <div class="solar-receipt-line"><span>Valor do kWh</span><strong>R$ {{ number_format((float) $reading->solarConfig->price_per_kwh, 4, ',', '.') }}</strong></div>
                <div class="solar-receipt-line solar-receipt-total"><span>Total</span><strong>R$ {{ number_format((float) $reading->amount, 2, ',', '.') }}</strong></div>
                <div class="solar-receipt-line"><span>Vencimento</span><strong>{{ $charge->due_date->format('d/m/Y') }}</strong></div>
            </div>
        </div>
        <div class="solar-receipt-footer">Extrato referente à cobrança de energia solar do imóvel {{ $charge->lease->property->title }}.</div>
    </article>

    <div class="form-actions" style="position:static">
        @if($reading->photo_base64)
            <form method="post" action="{{ route('admin.charges.solar-receipt.whatsapp', $charge) }}">
                @csrf
                <button class="btn" type="submit"><x-icon name="whatsapp"/> Enviar extrato pelo WhatsApp</button>
            </form>
        @else
            <span class="alert" style="margin:0;background:#fff6db;color:#805800"><x-icon name="camera"/> Adicione uma foto à medição para enviar o extrato.</span>
        @endif
    </div>
</div>
@endsection
