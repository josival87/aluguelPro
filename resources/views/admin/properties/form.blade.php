@extends('layouts.base', ['area' => 'admin'])

@section('title', ($property->exists ? 'Editar imóvel' : 'Novo imóvel').' — AlugaPro')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $property->exists ? 'Editar imóvel' : 'Novo imóvel' }}</h1>
        <p>Informações apresentadas na vitrine de interessados.</p>
    </div>
</div>

<form class="card form-card" method="post" enctype="multipart/form-data" action="{{ $property->exists ? route('admin.properties.update', $property) : route('admin.properties.store') }}">
    @csrf
    @if($property->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field span-2"><label>Título</label><input name="title" value="{{ old('title', $property->title) }}" required placeholder="Ex.: Apartamento 102"></div>
        <div class="field"><label>Grupo</label><select name="group_id" required><option value="">Selecione</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id', $property->group_id) == $group->id)>{{ $group->name }}</option>@endforeach</select></div>
        <div class="field"><label>Tipo do imóvel</label><select name="type"><option value="residential" @selected(old('type', $property->type) === 'residential')>Residencial</option><option value="commercial" @selected(old('type', $property->type) === 'commercial')>Comercial</option></select></div>
        <div class="field span-2"><label>Tipo de contrato</label><select name="contract_id" required><option value="">Selecione o contrato-base</option>@foreach($contracts as $contract)<option value="{{ $contract->id }}" @selected(old('contract_id', $property->contract_id) == $contract->id)>{{ $contract->title }}{{ $contract->active ? '' : ' (inativo)' }}</option>@endforeach</select><small>Este modelo será copiado e preenchido automaticamente em cada novo aluguel deste imóvel.</small></div>
        <div class="field span-2"><label>Descrição</label><textarea name="description" required>{{ old('description', $property->description) }}</textarea></div>
        <div class="field"><label>Área útil (m²)</label><input type="number" step="0.01" min="0" name="usable_area" value="{{ old('usable_area', $property->usable_area) }}"></div>
        <div class="field"><label>Valor do aluguel</label><input type="number" step="0.01" min="0" name="rent_amount" value="{{ old('rent_amount', $property->rent_amount) }}" required></div>
        <div class="field"><label>Quartos</label><input type="number" min="0" name="bedrooms" value="{{ old('bedrooms', $property->bedrooms ?? 0) }}" required></div>
        <div class="field"><label>Banheiros</label><input type="number" min="0" name="bathrooms" value="{{ old('bathrooms', $property->bathrooms ?? 0) }}" required></div>
        <div class="field"><label>Vagas</label><input type="number" min="0" name="parking_spaces" value="{{ old('parking_spaces', $property->parking_spaces ?? 0) }}" required></div>
        <div class="field"><label>Status</label><select name="status">@foreach(['available' => 'Disponível', 'rented' => 'Alugado', 'paused' => 'Paralisado'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $property->status ?: 'available') === $value)>{{ $label }}</option>@endforeach</select></div>

        <div class="field span-2"><h3>Endereço</h3></div>
        <div class="field span-2"><label>Rua</label><input name="street" value="{{ old('street', $property->street) }}" required></div>
        <div class="field"><label>Número</label><input name="number" value="{{ old('number', $property->number) }}"></div>
        <div class="field"><label>Complemento</label><input name="complement" value="{{ old('complement', $property->complement) }}"></div>
        <div class="field"><label>Bairro</label><input name="neighborhood" value="{{ old('neighborhood', $property->neighborhood) }}" required></div>
        <div class="field"><label>Cidade</label><input name="city" value="{{ old('city', $property->city) }}" required></div>
        <div class="field"><label>UF</label><input name="state" maxlength="2" value="{{ old('state', $property->state ?: 'PE') }}" required></div>
        <div class="field"><label>CEP</label><input name="postal_code" value="{{ old('postal_code', $property->postal_code) }}"></div>

        <div class="field span-2">
            <label>Características</label>
            <div class="check-grid">
                @foreach($features as $feature)
                    <label class="check-pill"><input type="checkbox" name="features[]" value="{{ $feature->id }}" @checked(in_array($feature->id, old('features', $property->exists ? $property->features->pluck('id')->all() : [])))> {{ $feature->name }}</label>
                @endforeach
            </div>
        </div>
        <div class="field span-2"><label class="check-pill"><input type="checkbox" name="has_solar_energy" value="1" @checked(old('has_solar_energy', $property->has_solar_energy))> Possui energia solar</label></div>
        <div class="field span-2">
            <label>Mídias</label>
            <input type="file" name="media[]" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,video/mp4,video/webm,video/quicktime" multiple>
            <small>Até 10 imagens ou vídeos no total, com no máximo 50 MB por arquivo.</small>
            @if($property->exists && $property->media->isNotEmpty())
                <small>Este imóvel já tem {{ $property->media->count() }} mídia(s). A exclusão pode ser feita na ficha do imóvel.</small>
            @endif
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $property->exists ? route('admin.properties.show', $property) : route('admin.properties.index') }}">Cancelar</a>
        <button class="btn">Salvar imóvel</button>
    </div>
</form>
@endsection
