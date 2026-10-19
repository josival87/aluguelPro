<div class="charge-action-menu">
    @if($charge->status !== 'paid')
        <form method="post" action="{{ route('admin.charges.paid', $charge) }}">
            @csrf
            @method('PATCH')
            <button class="charge-action" type="submit">Dar baixa</button>
        </form>
    @else
        <span class="charge-action charge-action-paid">Pago</span>
    @endif

    @if($charge->status === 'open' && $charge->due_date->toDateString() < now(config('business.billing_timezone', 'America/Sao_Paulo'))->toDateString())
        <form method="post" action="{{ route('admin.charges.overdue-notice', $charge) }}" onsubmit="return confirm('Enviar agora a mensagem de cobrança de atraso para {{ addslashes($charge->client->name) }}?')">
            @csrf
            <button class="charge-action" type="submit">Enviar cobrança de atraso</button>
        </form>
    @endif

    @if($charge->type === 'solar' && $charge->solarReading)
        <a class="charge-action" href="{{ route('admin.charges.solar-receipt', $charge) }}">Extrato de energia solar</a>
    @endif

    <a class="charge-action" href="{{ route('admin.leases.show', $charge->lease) }}">Ver ficha</a>
</div>
