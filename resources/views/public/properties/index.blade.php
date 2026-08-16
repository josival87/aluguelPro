@extends('layouts.base', ['area' => 'public'])

@section('title', 'Imóveis — AlugaPro')

@push('head')
<link rel="stylesheet" href="{{ asset('css/property-media.css') }}">
@endpush

@section('content')
<section class="hero">
    <div class="hero-copy"><span class="badge" style="background:rgba(255,255,255,.14);color:#fff">Seu próximo endereço começa aqui</span><h1>Encontre um lugar para chamar de seu.</h1><p>Imóveis selecionados, cadastro digital e acompanhamento completo pelo celular.</p></div>
    <div class="hero-art"><x-icon name="building" size="130"/></div>
</section>

<form class="filter-bar" method="get"><div class="field"><label>Tipo de imóvel</label><div class="tabs"><a class="{{ $type === 'residential' ? 'active' : '' }}" href="{{ route('properties.index', ['type' => 'residential']) }}">Residencial</a><a class="{{ $type === 'commercial' ? 'active' : '' }}" href="{{ route('properties.index', ['type' => 'commercial']) }}">Comercial</a></div></div></form>

@if($neighborhoods->isNotEmpty())
    <div class="neighborhoods">
        <a class="neighborhood-chip {{ !$neighborhood ? 'active' : '' }}" href="{{ route('properties.index', ['type' => $type]) }}">Todos ({{ $neighborhoods->sum('total') }})</a>
        @foreach($neighborhoods as $item)
            <a class="neighborhood-chip {{ $neighborhood === $item->neighborhood ? 'active' : '' }}" href="{{ route('properties.index', ['type' => $type, 'neighborhood' => $item->neighborhood]) }}">{{ $item->neighborhood }} ({{ $item->total }})</a>
        @endforeach
    </div>
@endif

<div class="page-head"><div><h1>{{ $neighborhood ?: 'Imóveis disponíveis' }}</h1><p>{{ $properties->total() }} opção(ões) encontrada(s).</p></div></div>

@if($properties->isEmpty())
    <div class="card empty"><div class="metric-icon"><x-icon name="search"/></div><strong>Nenhum imóvel disponível aqui</strong><p>Escolha outro bairro ou tipo de imóvel.</p></div>
@else
    <div class="property-grid">
        @foreach($properties as $property)
            @php($cover = $property->media->first())
            <a class="property-card" href="{{ route('properties.show', $property) }}">
                <div class="property-photo">
                    @if($cover?->is_image)
                        <img src="{{ route('property-media.show', $cover) }}" alt="{{ $property->title }}" loading="lazy">
                    @elseif($cover?->is_video)
                        <video src="{{ route('property-media.show', $cover) }}" muted playsinline preload="metadata"></video>
                    @endif
                    <span class="badge badge-success">Disponível</span>
                </div>
                <div class="property-body">
                    <h3>{{ $property->title }}</h3>
                    <div class="property-location">{{ $property->neighborhood }}, {{ $property->city }} - {{ $property->state }}</div>
                    <div class="property-facts"><span>{{ $property->bedrooms }} quarto(s)</span><span>{{ $property->bathrooms }} banheiro(s)</span><span>{{ $property->parking_spaces }} vaga(s)</span></div>
                    <div class="property-price"><div><strong>R$ {{ number_format((float) $property->rent_amount, 2, ',', '.') }}</strong><small>/mês</small></div><x-icon name="chevron"/></div>
                </div>
            </a>
        @endforeach
    </div>
    <div class="pagination">{{ $properties->links() }}</div>
@endif
@endsection
