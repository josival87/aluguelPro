@props(['value'])

@php
$labels = [
    'available' => 'Disponível',
    'rented' => 'Alugado',
    'paused' => 'Paralisado',
    'pending' => 'Pendente',
    'active' => 'Ativo',
    'active_expired' => 'Ativo - vencido',
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
    'waived' => 'Baixada sem valor',
    'signed' => 'Assinado',
    'draft' => 'Rascunho',
    'queued' => 'Na fila',
    'sent' => 'Enviada',
    'delivered' => 'Entregue',
    'read' => 'Lida',
    'simulated' => 'Simulada',
    'failed' => 'Falhou',
];
$class = in_array($value, ['available', 'active', 'paid', 'waived', 'signed', 'sent', 'delivered', 'read'], true)
    ? 'success'
    : (in_array($value, ['active_expired', 'closed', 'cancelled', 'rejected', 'inactive', 'failed'], true) ? 'danger' : 'warning');
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-'.$class]) }}>{{ $labels[$value] ?? ucfirst(str_replace('_', ' ', $value)) }}</span>
