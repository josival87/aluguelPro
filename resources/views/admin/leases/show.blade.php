@extends('layouts.base', ['area'=>'admin'])
@section('title','Aluguel #'.$lease->id.' — AlugaPro')
@section('content')
<div class="page-head"><div><a style="color:var(--blue);font-weight:700" href="{{ route('admin.leases.index') }}">← Aluguéis</a><h1 style="margin-top:8px">Aluguel #{{ $lease->id }}</h1><p>{{ $lease->property->title }} · {{ $lease->client->name }}</p></div><div class="head-actions"><x-status :value="$lease->status"/><a class="btn btn-outline" href="{{ route('admin.leases.edit',$lease) }}"><x-icon name="edit"/><span>Finalizar / editar</span></a></div></div>
@if($lease->status==='awaiting_completion')<div class="alert" style="background:#fff6db;color:#805800"><x-icon name="file"/> Complete datas, vencimento e dados solares; depois altere o status para “Gerar e aguardar assinaturas”.</div>@endif
<div class="metrics"><div class="card metric"><div class="metric-top"><span>Valor mensal</span><span class="metric-icon"><x-icon name="money"/></span></div><strong>R$ {{ number_format((float)$lease->rent_amount,2,',','.') }}</strong></div><div class="card metric"><div class="metric-top"><span>Em aberto</span><span class="metric-icon"><x-icon name="calendar"/></span></div><strong>R$ {{ number_format((float)$lease->charges->where('status','open')->sum('amount'),2,',','.') }}</strong></div><div class="card metric"><div class="metric-top"><span>Pago</span><span class="metric-icon"><x-icon name="check"/></span></div><strong>R$ {{ number_format((float)$lease->charges->where('status','paid')->sum('amount'),2,',','.') }}</strong></div><div class="card metric"><div class="metric-top"><span>Energia solar</span><span class="metric-icon"><x-icon name="sun"/></span></div><strong>{{ $lease->has_solar_energy?'Sim':'Não' }}</strong></div></div>
<div class="grid-2">
<section class="card">
    <h2>Contrato e assinaturas</h2>
    @if($lease->contract)
        <div class="list-row">
            <span class="metric-icon"><x-icon name="file"/></span>
            <span class="list-row-main"><strong>{{ $lease->contract->template?->title ?? 'Contrato importado' }}</strong><small>Versão #{{ $lease->contract->id }} · {{ $lease->contract->signatures->count() }}/2 assinaturas</small></span>
            <x-status :value="$lease->contract->status"/>
        </div>
        <div class="form-actions" style="justify-content:flex-start">
            @if($lease->contract->status==='in_production')
                <a class="btn" href="{{ route('admin.leases.contract.edit',$lease) }}"><x-icon name="edit"/> Ajustar contrato</a>
                <a class="btn btn-ghost" href="{{ route('contracts.show',$lease->contract) }}">Pré-visualizar</a>
            @elseif($lease->contract->status==='finalized')
                <a class="btn btn-ghost" href="{{ route('contracts.show',$lease->contract) }}">Visualizar versão final</a>
                <form method="post" action="{{ route('admin.leases.contract.signatures',$lease) }}">@csrf<button class="btn"><x-icon name="check"/> Enviar para assinatura</button></form>
            @else
                <a class="btn btn-sm" href="{{ route('contracts.show',$lease->contract) }}">Abrir contrato</a>
            @endif
        </div>
    @else
        <p style="color:var(--muted)">O contrato-base ainda não foi carregado.</p>
        <form method="post" action="{{ route('admin.contracts.generate',$lease) }}">@csrf<button class="btn" @disabled(!$lease->start_date)><x-icon name="file"/> Carregar contrato-base</button></form>
    @endif
</section>
<section class="card"><h2>Cliente</h2><a class="list-row" href="{{ route('admin.clients.show',$lease->client) }}"><span class="metric-icon"><x-icon name="user"/></span><span class="list-row-main"><strong>{{ $lease->client->name }}</strong><small>{{ $lease->client->cpf }} · {{ $lease->client->phone }}</small></span><x-icon name="chevron"/></a><p>{{ $lease->client->documents->count() }} documento(s) anexado(s).</p></section></div>
@php($documentCategories=['legacy_contract'=>'Contrato antigo','addendum'=>'Aditivo','inspection'=>'Vistoria','receipt'=>'Comprovante','other'=>'Outro documento'])
<section class="card" style="margin-top:20px">
    <div class="page-head">
        <div><h2>Documentos do aluguel</h2><p>Contratos antigos e outros arquivos ficam vinculados permanentemente a esta ficha.</p></div>
        <span class="badge badge-success">{{ $lease->documents->count() }} arquivo(s)</span>
    </div>
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.leases.documents.store',$lease) }}">
        @csrf
        <div class="form-grid">
            <div class="field"><label>Tipo de documento</label><select name="category" required>@foreach($documentCategories as $value=>$label)<option value="{{ $value }}" @selected(old('category','legacy_contract')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>Descrição opcional</label><input name="description" maxlength="255" value="{{ old('description') }}" placeholder="Ex.: Contrato assinado em 2024"></div>
            <div class="field span-2"><label>Arquivos</label><input type="file" name="documents[]" multiple required><small>Selecione até 5 arquivos por envio, com no máximo 10 MB cada. Os arquivos são salvos no banco de dados.</small></div>
        </div>
        <div class="form-actions"><button class="btn"><x-icon name="plus"/> Anexar documentos</button></div>
    </form>
    <div class="table-wrap" style="margin-top:20px"><table class="responsive">
        <thead><tr><th>Documento</th><th>Tipo</th><th>Tamanho</th><th>Enviado em</th><th></th></tr></thead>
        <tbody>@forelse($lease->documents->sortByDesc('created_at') as $document)
            <tr>
                <td data-label="Documento"><strong>{{ $document->original_name }}</strong>@if($document->description)<small style="display:block;color:var(--muted)">{{ $document->description }}</small>@endif</td>
                <td data-label="Tipo">{{ $documentCategories[$document->category] ?? 'Outro documento' }}</td>
                <td data-label="Tamanho">{{ $document->formatted_size }}</td>
                <td data-label="Enviado em">{{ $document->created_at->format('d/m/Y H:i') }}@if($document->uploader)<small style="display:block;color:var(--muted)">por {{ $document->uploader->name }}</small>@endif</td>
                <td><div class="head-actions"><a class="btn btn-outline btn-sm" href="{{ route('admin.leases.documents.download',[$lease,$document]) }}">Baixar</a><form method="post" action="{{ route('admin.leases.documents.destroy',[$lease,$document]) }}" onsubmit="return confirm('Remover este documento da ficha do aluguel?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm"><x-icon name="trash"/> Excluir</button></form></div></td>
            </tr>
        @empty<tr><td colspan="5" class="empty">Nenhum documento anexado a este aluguel.</td></tr>@endforelse</tbody>
    </table></div>
</section>
<section class="card" style="margin-top:20px">
    <div class="page-head"><div><h2>Cobranças</h2><p>Aluguel e energia baixados separadamente.</p></div></div>
    @if(!$pixReady && $lease->charges->contains('status', 'open'))
        <div class="alert" style="background:#fff6db;color:#805800">
            <x-icon name="money"/>
            <span>A chave Pix deste grupo ainda não tem um formato válido. <a href="{{ route('admin.groups.edit', $lease->property->group) }}" style="font-weight:800;text-decoration:underline">Corrigir chave Pix</a>.</span>
        </div>
    @endif
    @if($pixPayment)
        <div id="pix-gerado" class="card" style="margin-bottom:20px;background:#f7faff;border-color:#c8d8f8;box-shadow:none">
            <div class="page-head" style="margin-bottom:14px">
                <div>
                    <h3>Pix copia e cola</h3>
                    <p>{{ $pixPayment->charge->type === 'solar' ? 'Energia solar' : 'Aluguel' }} · {{ $pixPayment->charge->reference_month->translatedFormat('F/Y') }}</p>
                </div>
                <span class="badge badge-success">Pix estático</span>
            </div>
            <div class="money-line total" style="margin-bottom:14px"><span>Valor gerado</span><strong>R$ {{ number_format((float) $pixPayment->total_amount, 2, ',', '.') }}</strong></div>
            <textarea id="admin-pix-code" class="pix-code" readonly aria-label="Código Pix copia e cola">{{ $pixPayment->br_code }}</textarea>
            <button class="btn" type="button" style="margin-top:12px" data-copy="#admin-pix-code">Copiar código Pix</button>
            <small style="display:block;color:var(--muted);margin-top:10px">Código EMVCo gerado localmente, sem API de terceiros. TXID: {{ $pixPayment->txid }}</small>
        </div>
    @endif
    <div class="table-wrap"><table class="responsive">
        <thead><tr><th>Referência</th><th>Tipo</th><th>Vencimento</th><th>Valor</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($lease->charges->sortByDesc('due_date') as $charge)
            <tr>
                <td data-label="Referência">{{ $charge->reference_month->translatedFormat('M/Y') }}</td>
                <td data-label="Tipo">{{ $charge->type==='solar'?'Energia solar':'Aluguel' }}</td>
                <td data-label="Vencimento">{{ $charge->due_date->format('d/m/Y') }}</td>
                <td data-label="Valor"><strong>R$ {{ number_format((float)$charge->amount,2,',','.') }}</strong></td>
                <td data-label="Status"><x-status :value="$charge->status"/></td>
                <td data-label="Ações">
                    <div class="head-actions">
                        @if($charge->status==='open')
                            <form method="post" action="{{ route('admin.charges.pix',$charge) }}">@csrf<button class="btn btn-outline btn-sm" type="submit" @disabled(!$pixReady)><x-icon name="money"/> Gerar Pix</button></form>
                            <form method="post" action="{{ route('admin.charges.paid',$charge) }}">@csrf @method('PATCH')<button class="btn btn-success btn-sm" type="submit">Dar baixa</button></form>
                        @else
                            <form method="post" action="{{ route('admin.charges.reopen',$charge) }}">@csrf @method('PATCH')<button class="btn btn-ghost btn-sm" type="submit">Reabrir</button></form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty<tr><td colspan="6" class="empty">Nenhuma cobrança gerada.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@php($notificationEvents = [
    'due_in_5_days' => 'Lembrete: vence em 5 dias',
    'due_today' => 'Lembrete: vence hoje',
    'overdue' => 'Cobrança de atraso',
    'signature_otp' => 'Código de assinatura',
])
<section class="card" style="margin-top:20px">
    <div class="page-head">
        <div><h2>Histórico de mensagens WhatsApp</h2><p>Relatório permanente dos envios e tentativas vinculados a este aluguel.</p></div>
        <span class="badge badge-success">{{ $lease->notificationLogs->count() }} mensagem(ns)</span>
    </div>
    <div class="table-wrap"><table class="responsive">
        <thead><tr><th>Data e hora</th><th>Tipo</th><th>Cobrança</th><th>Destino</th><th>Mensagem</th><th>Status</th></tr></thead>
        <tbody>@forelse($lease->notificationLogs as $log)
            <tr>
                <td data-label="Data e hora">{{ ($log->sent_at ?? $log->created_at)->timezone(config('business.billing_timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i') }}</td>
                <td data-label="Tipo">{{ $notificationEvents[$log->event] ?? str($log->event)->replace('_', ' ')->title() }}</td>
                <td data-label="Cobrança">
                    @if($log->charge)
                        {{ $log->charge->type === 'solar' ? 'Energia solar' : 'Aluguel' }} · {{ $log->charge->reference_month->translatedFormat('M/Y') }}
                    @else
                        —
                    @endif
                </td>
                <td data-label="Destino">{{ $log->recipient }}</td>
                <td data-label="Mensagem" style="min-width:280px;white-space:normal">{{ $log->message }}@if($log->error)<small style="display:block;color:var(--red);margin-top:5px">{{ $log->error }}</small>@endif</td>
                <td data-label="Status"><x-status :value="$log->status"/></td>
            </tr>
        @empty<tr><td colspan="6" class="empty">Nenhuma mensagem WhatsApp foi registrada para este cliente neste aluguel.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@if($lease->has_solar_energy)<section class="card" style="margin-top:20px"><div class="page-head"><div><h2>Histórico solar</h2><p>Leituras e consumo mensal.</p></div><a class="btn btn-outline btn-sm" href="{{ route('admin.solar.create',['lease'=>$lease->id]) }}"><x-icon name="camera"/> Nova medição</a></div>@forelse($lease->solarConfig?->readings ?? [] as $reading)<div class="list-row"><span class="metric-icon"><x-icon name="sun"/></span><span class="list-row-main"><strong>{{ $reading->reference_month->translatedFormat('F/Y') }}</strong><small>{{ $reading->previous_reading }} → {{ $reading->meter_reading }} kWh · OCR {{ $reading->ocr_status }}</small></span><span class="amount">{{ $reading->consumption_kwh }} kWh<br>R$ {{ number_format((float)$reading->amount,2,',','.') }}</span></div>@empty<div class="empty">Nenhuma medição registrada.</div>@endforelse</section>@endif
@endsection
