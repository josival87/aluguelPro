@extends('layouts.base', ['area'=>'admin'])
@section('title','Cobranças — AlugaPro')
@push('head')<link rel="stylesheet" href="{{ asset('css/charges.css') }}">@endpush
@section('content')
@php($previous=$month->copy()->subMonth()->format('Y-m')) @php($next=$month->copy()->addMonth()->format('Y-m'))
<div class="page-head"><div><h1>Cobranças</h1><p>Calendário financeiro por grupo.</p></div><form method="post" action="{{ route('admin.charges.generate') }}">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><button class="btn"><x-icon name="plus"/><span>Gerar aluguéis do mês</span></button></form></div>
<form class="filter-bar"><a class="icon-btn" href="{{ route('admin.charges.index',['month'=>$previous,'group'=>$groupId]) }}">←</a><div class="field"><label>Mês</label><input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()"></div><div class="field"><label>Grupo</label><select name="group" onchange="this.form.submit()"><option value="">Todos os grupos</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected($groupId===$group->id)>{{ $group->name }}</option>@endforeach</select></div><a class="icon-btn" href="{{ route('admin.charges.index',['month'=>$next,'group'=>$groupId]) }}">→</a></form>
<div class="metrics" style="grid-template-columns:repeat(3,1fr)"><div class="card metric"><span>Total do mês</span><strong>R$ {{ number_format((float)$summary['total'],2,',','.') }}</strong></div><div class="card metric"><span>Recebido</span><strong style="color:var(--green)">R$ {{ number_format((float)$summary['received'],2,',','.') }}</strong></div><div class="card metric"><span>Em aberto</span><strong style="color:var(--red)">R$ {{ number_format((float)$summary['open'],2,',','.') }}</strong></div></div>
@php($cursor=$month->copy()->startOfMonth()->startOfWeek(0))
<div class="calendar-wrap"><div class="calendar-head">@foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $day)<div>{{ $day }}</div>@endforeach</div><div class="calendar-grid">@for($i=0;$i<42;$i++)@php($date=$cursor->copy()->addDays($i))<div class="calendar-day {{ $date->month!==$month->month?'muted':'' }}"><span class="day-number">{{ $date->day }}</span>
@foreach($charges->get($date->day,collect())->filter(fn($c)=>$c->due_date->isSameDay($date)) as $charge)
<details class="charge-actions">
    <summary class="charge-chip {{ $charge->status==='paid'?'paid':($charge->due_date->isPast()?'overdue':'') }}"><strong>{{ $charge->lease->property->title }} - R${{ number_format((float)$charge->amount,0,',','.') }}</strong></summary>
    <div class="charge-action-menu">
        @if($charge->status !== 'paid')<form method="post" action="{{ route('admin.charges.paid',$charge) }}">@csrf @method('PATCH')<button class="charge-action" type="submit">Dar baixa</button></form>@else<span class="charge-action charge-action-paid">Pago</span>@endif
        <a class="charge-action" href="{{ route('admin.leases.show',$charge->lease) }}">Ver ficha</a>
    </div>
</details>
@endforeach</div>@endfor</div></div><p style="color:var(--muted);font-size:12px"><span class="badge badge-warning">A vencer</span> <span class="badge badge-danger">Vencido</span> <span class="badge badge-success">Pago</span></p>
@endsection
