@props(['value'])
@php
$labels = ['available'=>'Disponível','rented'=>'Alugado','paused'=>'Paralisado','pending'=>'Pendente','active'=>'Ativo','inactive'=>'Inativo','rejected'=>'Rejeitado','awaiting_completion'=>'Aguardando finalização','in_production'=>'Em produção','finalized'=>'Finalizado','awaiting_signatures'=>'Esperando assinatura','finished'=>'Finalizado','cancelled'=>'Cancelado','open'=>'Em aberto','paid'=>'Pago','signed'=>'Assinado','draft'=>'Rascunho'];
$class = in_array($value, ['available','active','paid','signed'], true) ? 'success' : (in_array($value, ['cancelled','rejected','inactive'], true) ? 'danger' : 'warning');
@endphp
<span {{ $attributes->merge(['class'=>'badge badge-'.$class]) }}>{{ $labels[$value] ?? ucfirst(str_replace('_',' ',$value)) }}</span>
