@extends('layouts.base', ['area' => 'admin'])

@section('title', $property->title.' — AlugaPro')

@push('head')
<link rel="stylesheet" href="{{ asset('css/property-media.css') }}">
@endpush

@section('content')
<div class="page-head property-record-head">
    <div>
        <a class="back-link" href="{{ route('admin.properties.index') }}">← Imóveis</a>
        <h1>{{ $property->title }}</h1>
        <p>{{ $property->street }}{{ $property->number ? ', '.$property->number : '' }} · {{ $property->neighborhood }}, {{ $property->city }}/{{ $property->state }}</p>
    </div>
    <div class="head-actions">
        <x-status :value="$property->status"/>
        <a class="btn btn-outline" href="{{ route('admin.properties.edit', $property) }}"><x-icon name="edit"/> Editar</a>
    </div>
</div>

<div class="grid-2 property-record-grid">
    <section class="card">
        <h2>Dados do imóvel</h2>
        <dl class="property-data-list">
            <div class="span-full"><dt>Descrição</dt><dd>{{ $property->description }}</dd></div>
            <div><dt>Grupo</dt><dd>{{ $property->group->name }}</dd></div>
            <div><dt>Tipo</dt><dd>{{ $property->type === 'commercial' ? 'Comercial' : 'Residencial' }}</dd></div>
            <div><dt>Valor do aluguel</dt><dd>R$ {{ number_format((float) $property->rent_amount, 2, ',', '.') }}</dd></div>
            <div><dt>Área útil</dt><dd>{{ $property->usable_area !== null ? number_format((float) $property->usable_area, 2, ',', '.').' m²' : 'Não informada' }}</dd></div>
            <div><dt>Quartos</dt><dd>{{ $property->bedrooms }}</dd></div>
            <div><dt>Banheiros</dt><dd>{{ $property->bathrooms }}</dd></div>
            <div><dt>Vagas</dt><dd>{{ $property->parking_spaces }}</dd></div>
            <div><dt>Energia solar</dt><dd>{{ $property->has_solar_energy ? 'Sim' : 'Não' }}</dd></div>
            <div><dt>Status</dt><dd><x-status :value="$property->status"/></dd></div>
            <div><dt>Tipo de contrato</dt><dd>@if($property->contract)<a class="record-link" href="{{ route('admin.contracts.show', $property->contract) }}">{{ $property->contract->title }}</a>@else Não configurado @endif</dd></div>
            <div><dt>Cadastrado em</dt><dd>{{ $property->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Atualizado em</dt><dd>{{ $property->updated_at->format('d/m/Y H:i') }}</dd></div>
        </dl>

        <h3 class="record-subtitle">Características</h3>
        <div class="feature-list">
            @forelse($property->features as $feature)
                <span>{{ $feature->name }}</span>
            @empty
                <span>Nenhuma característica cadastrada</span>
            @endforelse
        </div>
    </section>

    <section class="card">
        <h2>Endereço</h2>
        <dl class="property-data-list">
            <div class="span-full"><dt>Rua</dt><dd>{{ $property->street }}</dd></div>
            <div><dt>Número</dt><dd>{{ $property->number ?: 'Não informado' }}</dd></div>
            <div><dt>Complemento</dt><dd>{{ $property->complement ?: 'Não informado' }}</dd></div>
            <div><dt>Bairro</dt><dd>{{ $property->neighborhood }}</dd></div>
            <div><dt>Cidade</dt><dd>{{ $property->city }}</dd></div>
            <div><dt>UF</dt><dd>{{ $property->state }}</dd></div>
            <div><dt>CEP</dt><dd>{{ $property->postal_code ?: 'Não informado' }}</dd></div>
        </dl>
    </section>
</div>

<section class="card property-media-section">
    <div class="property-section-head">
        <div>
            <h2>Mídias do imóvel</h2>
            <p>Todas as imagens e vídeos cadastrados para este imóvel.</p>
        </div>
        <span class="badge">{{ $property->media->count() }} / {{ \App\Models\PropertyMedia::MAX_ITEMS }}</span>
    </div>

    @if($property->media->count() < \App\Models\PropertyMedia::MAX_ITEMS)
        <form class="property-media-upload" method="post" enctype="multipart/form-data" action="{{ route('admin.properties.media.store', $property) }}">
            @csrf
            <div class="field">
                <label for="property-media">Adicionar mídias</label>
                <input id="property-media" type="file" name="media[]" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,video/mp4,video/webm,video/quicktime" multiple required>
                <small>Imagens ou vídeos (JPG, PNG, WebP, GIF, AVIF, MP4, WebM ou MOV), até 50 MB por arquivo.</small>
            </div>
            <button class="btn" type="submit"><x-icon name="plus"/> Adicionar</button>
        </form>
    @endif

    <div class="property-media-list">
        @forelse($property->media as $index => $medium)
            <article class="property-media-row">
                <div class="property-media-preview">
                    @if($medium->is_image)
                        <img src="{{ route('property-media.show', $medium) }}" alt="Imagem {{ $index + 1 }} do imóvel {{ $property->title }}" loading="lazy">
                    @elseif($medium->is_video)
                        <video src="{{ route('property-media.show', $medium) }}" controls preload="metadata">Seu navegador não consegue exibir este vídeo.</video>
                    @endif
                </div>
                <div class="property-media-info">
                    <strong>{{ $medium->is_video ? 'Vídeo' : 'Imagem' }} {{ $index + 1 }}</strong>
                    <small>{{ $medium->mime_type }}</small>
                    <small>Adicionada em {{ $medium->created_at->format('d/m/Y H:i') }}</small>
                </div>
                <form method="post" action="{{ route('admin.properties.media.destroy', [$property, $medium]) }}" onsubmit="return confirm('Deseja apagar esta mídia?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm" type="submit"><x-icon name="trash"/> Apagar</button>
                </form>
            </article>
        @empty
            <div class="empty">Nenhuma mídia cadastrada.</div>
        @endforelse
    </div>
</section>

<section class="card property-history-section">
    <h2>Histórico de aluguéis</h2>
    @forelse($property->leases as $lease)
        <a class="list-row" href="{{ route('admin.leases.show', $lease) }}">
            <span class="list-row-main">
                <strong>#{{ $lease->id }} · {{ $lease->client->name }}</strong>
                <small>{{ $lease->start_date?->format('d/m/Y') ?? 'Data pendente' }}</small>
            </span>
            <x-status :value="$lease->status"/>
        </a>
    @empty
        <div class="empty">Nenhum aluguel vinculado.</div>
    @endforelse
</section>
@endsection
