@extends('layouts.base', ['area' => 'admin'])

@section('title', ($lease->exists ? 'Editar' : 'Novo').' aluguel — AlugaPro')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $lease->exists ? 'Finalizar / editar aluguel' : 'Novo aluguel' }}</h1>
        <p>Defina os dados contratuais antes de gerar o documento.</p>
    </div>
</div>

<form class="card form-card" method="post" action="{{ $lease->exists ? route('admin.leases.update', $lease) : route('admin.leases.store') }}">
    @csrf
    @if($lease->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field">
            <label>Cliente</label>
            <select name="client_id" required>
                <option value="">Selecione</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $lease->client_id) == $client->id)>{{ $client->name }} - {{ $client->cpf }} - {{ $client->active_leases_count }} {{ (int) $client->active_leases_count === 1 ? 'aluguel ativo' : 'aluguéis ativos' }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Imóvel</label>
            <select name="property_id" id="property_id" required>
                <option value="">Selecione</option>
                @foreach($properties as $property)
                    <option value="{{ $property->id }}" data-solar="{{ $property->has_solar_energy ? 1 : 0 }}" data-rent="{{ $property->rent_amount }}" @selected(old('property_id', $lease->property_id) == $property->id)>{{ $property->title }} · {{ $property->neighborhood }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Data de início</label>
            <input type="date" name="start_date" value="{{ old('start_date', $lease->start_date?->format('Y-m-d') ?? ($contractDates['start_date'] ?? null)) }}">
            @if(! $lease->start_date && ($contractDates['start_date'] ?? null))<small>Data identificada no contrato.</small>@endif
        </div>
        <div class="field">
            <label>Data de fim</label>
            <input type="date" name="end_date" value="{{ old('end_date', $lease->end_date?->format('Y-m-d') ?? ($contractDates['end_date'] ?? null)) }}">
            <small>{{ ! $lease->end_date && ($contractDates['end_date'] ?? null) ? 'Data identificada no contrato.' : 'Se vazia, será calculada pelo prazo.' }}</small>
        </div>
        <div class="field">
            <label>Prazo (meses)</label>
            <input type="number" min="1" max="120" name="contract_months" value="{{ old('contract_months', $lease->contract_months ?: 12) }}" required>
        </div>
        <div class="field">
            <label>Dia de vencimento</label>
            <input type="number" min="1" max="28" name="due_day" value="{{ old('due_day', $lease->due_day ?: 10) }}" required>
        </div>
        <div class="field">
            <label>Valor do aluguel</label>
            <input id="rent_amount" type="number" step="0.01" min="0" name="rent_amount" value="{{ old('rent_amount', $lease->rent_amount) }}" required>
        </div>
        <div class="field">
            <label>Status</label>
            @php
                $statusOptions = [
                    'awaiting_completion' => 'Aguardando finalização',
                    'active' => 'Ativo',
                    'closed' => 'Encerrado',
                    'cancelled' => 'Cancelado',
                ] + ($lease->status === 'awaiting_signatures'
                    ? ['awaiting_signatures' => 'Aguardando assinaturas']
                    : []);
            @endphp
            <select name="status">
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $lease->status ?: 'awaiting_completion') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <small>Ativo representa um contrato em vigência e mantém as cobranças. Encerrado libera o imóvel e interrompe novas cobranças.</small>
        </div>
        <div class="field span-2">
            <label class="check-pill"><input id="has_solar" type="checkbox" name="has_solar_energy" value="1" @checked(old('has_solar_energy', $lease->has_solar_energy))> Possui cobrança de energia solar</label>
        </div>
        <div id="solar-fields" class="field span-2">
            <div class="form-grid">
                <div class="field"><label>Leitura inicial (kWh)</label><input type="number" step="0.001" min="0" name="initial_reading" value="{{ old('initial_reading', $lease->solarConfig?->initial_reading) }}"></div>
                <div class="field"><label>Valor por kWh (R$)</label><input type="number" step="0.0001" min="0" name="price_per_kwh" value="{{ old('price_per_kwh', $lease->solarConfig?->price_per_kwh) }}"></div>
            </div>
        </div>
        <div class="field">
            <label>Número da unidade de energia</label>
            <input name="utility_number" value="{{ old('utility_number', $lease->utility_number) }}" placeholder="Ex.: CELPE/Neoenergia">
        </div>
        <div class="field span-2">
            <label>Observações</label>
            <textarea name="notes">{{ old('notes', $lease->notes) }}</textarea>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('admin.leases.index') }}">Cancelar</a>
        <button class="btn">Salvar aluguel</button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const check = document.querySelector('#has_solar');
    const fields = document.querySelector('#solar-fields');
    const property = document.querySelector('#property_id');
    const rent = document.querySelector('#rent_amount');
    const sync = () => fields.style.display = check.checked ? 'block' : 'none';

    check.addEventListener('change', sync);
    property.addEventListener('change', () => {
        const option = property.selectedOptions[0];
        if (option?.dataset.rent && ! rent.value) rent.value = option.dataset.rent;
        if (option?.dataset.solar === '1') check.checked = true;
        sync();
    });
    sync();
});
</script>
@endpush
@endsection
