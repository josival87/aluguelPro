@extends('layouts.base', ['area'=>'admin'])
@section('title',$contract->title.' — AlugaPro')
@section('content')
<div class="page-head"><div><a style="color:var(--blue);font-weight:700" href="{{ route('admin.contracts.index') }}">← Contratos</a><h1 style="margin-top:8px">{{ $contract->title }}</h1><p>{{ $contract->properties_count }} imóvel(is) · {{ $contract->lease_contracts_count }} versão(ões) gerada(s)</p></div><div class="head-actions"><x-status :value="$contract->active?'active':'inactive'"/><a class="btn btn-outline" href="{{ route('admin.contracts.edit',$contract) }}"><x-icon name="edit"/> Editar</a></div></div>
<div class="contract-paper">{!! $contract->content !!}</div>
<section class="card" style="margin-top:20px"><h2>Variáveis usadas neste modelo</h2><div class="variable-list">@foreach($placeholders as $key=>$label)@if(str_contains($contract->content,'{{'.$key.'}}'))@php($token='{{'.$key.'}}')<span class="variable-chip static"><code>{{ $token }}</code><span>{{ $label }}</span></span>@endif @endforeach</div></section>
@endsection
