@props(['value'])

@php
$labels = [
    'available' => 'Disponível',
    'rented' => 'Alugado',
    'paused' => 'Paralisado',
    'pending' => 'Pendente',
    'active' => 'Ativo',
    'inactive' => 'Inativo',
    'rejected' => 'Rejeitado',
    'awaiting_completion' => 'Aguardando finalização',
    'in_production' => 'Em produção',
    'finalized' => 'Finalizado',
    'awaiting_signatures' => 'Esperando assinatura',
    'closed' => 'Encerrado',
    'cancelled' => 'Cancelado',
    'open' => 'Em aberto',
    'paid' => 'Pago',
    'signed' => 'Assinado',
    'draft' => 'Rascunho',
    'queued' => 'Na fila',
    'sent' => 'Enviada',
    'simulated' => 'Simulada',
    'failed' => 'Falhou',
];
$class = in_array($value, ['available', 'active', 'paid', 'signed', 'sent'], true)
    ? 'success'
    : (in_array($value, ['closed', 'cancelled', 'rejected', 'inactive', 'failed'], true) ? 'danger' : 'warning');
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-'.$class]) }}>{{ $labels[$value] ?? ucfirst(str_replace('_', ' ', $value)) }}</span>
