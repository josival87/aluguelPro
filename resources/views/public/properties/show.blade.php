@extends('layouts.base', ['area' => 'public'])

@section('title', $property->title.' — AlugaPro')

@section('content')
<div class="page-head">
    <div>
        <a style="color:var(--blue);font-weight:700" href="{{ route('properties.index', ['type' => $property->type, 'neighborhood' => $property->neighborhood]) }}">← Voltar aos imóveis</a>
        <h1 style="margin-top:10px">{{ $property->title }}</h1>
        <p>{{ $property->street }}{{ $property->number ? ', '.$property->number : '' }} · {{ $property->neighborhood }}, {{ $property->city }}/{{ $property->state }}</p>
    </div>
    <x-status :value="$property->status"/>
</div>

<div class="property-gallery">
    @forelse($property->photos->take(3) as $photo)
        <img src="{{ $photo->data_uri }}" alt="Foto do imóvel {{ $property->title }}">
    @empty
        <div style="display:grid;place-items:center;background:#dce8fa;grid-row:span 2"><x-icon name="building" size="100"/></div>
    @endforelse
</div>

<div class="property-detail-grid">
    <div class="stack">
        <section class="card">
            <h2>Sobre este imóvel</h2>
            <p>{{ $property->description }}</p>
            <div class="grid-3">
                <div><strong>{{ $property->usable_area ?: '—' }} m²</strong><small style="display:block;color:var(--muted)">Área útil</small></div>
                <div><strong>{{ $property->bedrooms }}</strong><small style="display:block;color:var(--muted)">Quartos</small></div>
                <div><strong>{{ $property->bathrooms }}</strong><small style="display:block;color:var(--muted)">Banheiros</small></div>
            </div>
        </section>

        <section class="card">
            <h2>O que o imóvel oferece</h2>
            <div class="feature-list">
                @foreach($property->features as $feature)
                    <span><x-icon name="check" size="15"/> {{ $feature->name }}</span>
                @endforeach
                @if($property->has_solar_energy)
                    <span><x-icon name="sun" size="15"/> Energia solar</span>
                @endif
            </div>
        </section>
    </div>

    <aside class="card sticky-card">
        <small style="color:var(--muted)">Valor mensal</small>
        <h2 style="color:var(--blue);font-size:29px;margin:3px 0">R$ {{ number_format((float) $property->rent_amount, 2, ',', '.') }}</h2>
        <p style="color:var(--muted)">Grupo responsável: {{ $property->group->name }}</p>
        @if($property->has_solar_energy)
            <div class="alert" style="background:#fff7dc;color:#885c00"><x-icon name="sun"/> Energia solar calculada conforme o consumo mensal.</div>
        @endif
        <a class="btn btn-block" href="{{ route('properties.application', $property) }}">Quero alugar <x-icon name="arrow"/></a>
        <small style="display:block;text-align:center;color:var(--muted);margin-top:12px">Cadastro seguro e 100% digital</small>
    </aside>
</div>
@endsection
