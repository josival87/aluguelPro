@extends('layouts.base', ['area'=>'admin'])
@section('title','Cobranças — AlugaPro')
@push('head')<link rel="stylesheet" href="{{ asset('css/charges.css') }}">@endpush
@section('content')
@php($previous=$month->copy()->subMonth()->format('Y-m')) @php($next=$month->copy()->addMonth()->format('Y-m'))
<div class="page-head"><div><h1>Cobranças</h1><p>Calendário financeiro por grupo.</p></div><form method="post" action="{{ route('admin.charges.generate') }}">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><button class="btn"><x-icon name="plus"/><span>Gerar aluguéis do mês</span></button></form></div>
<form class="filter-bar"><a class="icon-btn" href="{{ route('admin.charges.index',['month'=>$previous,'group'=>$groupId]) }}">←</a><div class="field"><label>Mês</label><input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()"></div><div class="field"><label>Grupo</label><select name="group" onchange="this.form.submit()" @disabled(!auth()->user()->hasAllGroupsAccess())>@if(auth()->user()->hasAllGroupsAccess())<option value="">Todos os grupos</option>@endif @foreach($groups as $group)<option value="{{ $group->id }}" @selected($groupId===$group->id)>{{ $group->name }}</option>@endforeach</select>@if(!auth()->user()->hasAllGroupsAccess())<input type="hidden" name="group" value="{{ $groupId }}">@endif</div><a class="icon-btn" href="{{ route('admin.charges.index',['month'=>$next,'group'=>$groupId]) }}">→</a></form>
<div class="metrics" style="grid-template-columns:repeat(3,1fr)"><div class="card metric"><span>Total do mês({{ $summary['total_count'] }})</span><strong>R$ {{ number_format((float)$summary['total'],2,',','.') }}</strong></div><div class="card metric"><span>Recebidos({{ $summary['received_count'] }})</span><strong style="color:var(--green)">R$ {{ number_format((float)$summary['received'],2,',','.') }}</strong></div><div class="card metric"><span>Em aberto({{ $summary['open_count'] }})</span><strong style="color:var(--red)">R$ {{ number_format((float)$summary['open'],2,',','.') }}</strong></div></div>

@if($overdueCharges->isNotEmpty())
<section class="overdue-carryover" aria-labelledby="overdue-carryover-title">
    <div class="overdue-carryover-head">
        <span class="overdue-alert-icon"><x-icon name="alert-triangle" size="22"/></span>
        <div>
            <h2 id="overdue-carryover-title">Cobranças anteriores em atraso</h2>
            <p>Valores de meses anteriores que continuam sem pagamento.</p>
        </div>
        <span class="overdue-total">{{ $summary['overdue_count'] }} {{ $summary['overdue_count'] === 1 ? 'atrasada' : 'atrasadas' }} · R$ {{ number_format((float) $summary['overdue'], 2, ',', '.') }}</span>
    </div>

    <div class="overdue-list">
        @foreach($overdueCharges as $charge)
            <details class="charge-actions overdue-charge">
                <summary class="overdue-charge-summary">
                    <span class="overdue-charge-icon"><x-icon name="alert-triangle" size="18"/></span>
                    <span class="overdue-charge-main">
                        <strong>{{ $charge->type === 'solar' ? 'Energia Solar · '.$charge->lease->property->title : ($charge->lease->nickname ?: $charge->lease->property->title) }}</strong>
                        <small>Referência {{ $charge->reference_month->translatedFormat('M/Y') }} · venceu em {{ $charge->due_date->format('d/m/Y') }}</small>
                    </span>
                    <strong class="overdue-charge-amount">R$ {{ number_format((float) $charge->amount, 2, ',', '.') }}</strong>
                    <span class="overdue-label"><x-icon name="alert-triangle" size="13"/> Em atraso</span>
                    <x-icon class="overdue-chevron" name="chevron" size="17"/>
                </summary>
                @include('admin.charges._actions', ['charge' => $charge])
            </details>
        @endforeach
    </div>
</section>
@endif

@php($cursor=$month->copy()->startOfMonth()->startOfWeek(0))
<div class="calendar-wrap"><div class="calendar-head">@foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $day)<div>{{ $day }}</div>@endforeach</div><div class="calendar-grid">@for($i=0;$i<42;$i++)@php($date=$cursor->copy()->addDays($i))<div class="calendar-day {{ $date->month!==$month->month?'muted':'' }}"><span class="day-number">{{ $date->day }}</span>
@foreach($charges->get($date->day,collect())->filter(fn($c)=>$c->due_date->isSameDay($date)) as $charge)
<details class="charge-actions">
    <summary class="charge-chip {{ $charge->status==='paid'?'paid':($charge->due_date->isPast()?'overdue':'') }}"><strong>{{ $charge->type === 'solar' ? 'Energia Solar · '.$charge->lease->property->title : ($charge->lease->nickname ?: $charge->lease->property->title) }} - R${{ number_format((float)$charge->amount,0,',','.') }}</strong></summary>
    @include('admin.charges._actions', ['charge' => $charge])
</details>
@endforeach</div>@endfor</div></div><p style="color:var(--muted);font-size:12px"><span class="badge badge-warning">A vencer</span> <span class="badge badge-danger">Vencido</span> <span class="badge badge-success">Pago</span></p>
@endsection
