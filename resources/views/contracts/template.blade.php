<article class="contract-content">
    <h1>CONTRATO DE LOCAÇÃO DE IMÓVEL</h1>
    <p><strong>LOCADOR/ADMINISTRADOR:</strong> {{ $company?->name ?? $lease->property->group->responsible_name }}, inscrito no CNPJ {{ $company?->cnpj ?? 'a informar' }}, responsável pelo grupo {{ $lease->property->group->name }}.</p>
    <p><strong>LOCATÁRIO:</strong> {{ $lease->client->name }}, CPF {{ $lease->client->cpf }}, RG {{ $lease->client->rg }}, profissão {{ $lease->client->profession }}.</p>
    <p><strong>IMÓVEL:</strong> {{ $lease->property->title }}, situado à {{ $lease->property->street }}{{ $lease->property->number ? ', '.$lease->property->number : '' }}, {{ $lease->property->neighborhood }}, {{ $lease->property->city }}/{{ $lease->property->state }}.</p>

    <h2>1. Prazo e valor</h2>
    <p>A locação vigorará de {{ $lease->start_date?->format('d/m/Y') ?? '[data a definir]' }} a {{ $lease->end_date?->format('d/m/Y') ?? '[data a definir]' }}, pelo aluguel mensal de R$ {{ number_format((float) $lease->rent_amount, 2, ',', '.') }}, com vencimento no dia {{ $lease->due_day }}.</p>

    <h2>2. Mora</h2>
    <p>Após o vencimento, incidirão multa contratual de {{ config('business.late_fee_percent') }}% e juros simples de {{ config('business.monthly_interest_percent') }}% ao mês, calculados proporcionalmente aos dias de atraso, sem prejuízo de revisão da cláusula pelas partes e assessoria jurídica.</p>

    @if($lease->has_solar_energy)
        <h2>3. Energia solar</h2>
        <p>O consumo será apurado pela diferença entre a leitura atual e a anterior do medidor individual, multiplicada pelo valor de R$ {{ number_format((float) $lease->solarConfig?->price_per_kwh, 4, ',', '.') }} por kWh, e cobrado separadamente do aluguel.</p>
    @endif

    <h2>4. Deveres e regras</h2>
    <p>O locatário declara ciência das regras do condomínio disponibilizadas em sua área e se obriga a conservar o imóvel e pagar pontualmente aluguel e encargos contratualmente exigíveis.</p>

    <h2>5. Assinatura eletrônica</h2>
    <p>As partes concordam com a assinatura eletrônica autenticada por código de uso único enviado ao WhatsApp cadastrado. O sistema registrará timestamp, endereço IP, agente do dispositivo e hash de integridade deste documento.</p>
    <p>As partes declaram ter lido e aceitado as condições acima.</p>
</article>
