<?php

namespace App\Models;

use App\Services\MoneyCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WhatsAppAutomation extends Model
{
    protected $table = 'whatsapp_automations';

    public const DUE_IN_5_DAYS = 'due_in_5_days';

    public const DUE_TODAY = 'due_today';

    public const OVERDUE = 'overdue';

    public const GROUP_DUE_TODAY = 'responsible_due_today';

    public const DEFINITIONS = [
        self::DUE_IN_5_DAYS => [
            'name' => '5 dias antes do vencimento',
            'schedule' => 'Todos os dias, para cobranças que vencem em 5 dias',
            'recipient' => 'Cliente',
            'default_message' => 'Olá, {{cliente}}! Sua cobrança de {{valor}} vence em {{vencimento}}. Imóvel: {{imovel}}.',
            'template_parameters' => ['cliente', 'valor', 'vencimento', 'imovel'],
        ],
        self::DUE_TODAY => [
            'name' => 'No dia do vencimento',
            'schedule' => 'No dia do vencimento da cobrança',
            'recipient' => 'Cliente',
            'default_message' => 'Olá, {{cliente}}! Sua cobrança de {{valor}} vence hoje ({{vencimento}}). Imóvel: {{imovel}}.',
            'template_parameters' => ['cliente', 'valor', 'vencimento', 'imovel'],
        ],
        self::OVERDUE => [
            'name' => 'Cobrança em atraso',
            'schedule' => 'A cada 3 dias enquanto a cobrança permanecer vencida e em aberto',
            'recipient' => 'Cliente',
            'default_message' => 'Olá, {{cliente}}! Sua cobrança de {{descricao}}, vencida em {{vencimento}}, permanece em atraso. Dias de atraso: {{dias_atraso}}. Valor atualizado: {{valor_atualizado}}. Imóvel: {{imovel}}.',
            'template_parameters' => ['cliente', 'descricao', 'vencimento', 'dias_atraso', 'valor_atualizado', 'imovel'],
        ],
        self::GROUP_DUE_TODAY => [
            'name' => 'Vencimento para o grupo',
            'schedule' => 'No dia do vencimento da cobrança',
            'recipient' => 'Responsável pelo grupo',
            'default_message' => 'Vencimento hoje: {{imovel}}, cliente {{cliente}}, valor {{valor}}. Grupo: {{grupo}}.',
            'template_parameters' => ['imovel', 'cliente', 'valor', 'grupo'],
        ],
    ];

    protected $fillable = ['key', 'message'];

    public static function configured(): Collection
    {
        $stored = static::query()
            ->whereIn('key', array_keys(self::DEFINITIONS))
            ->get()
            ->keyBy('key');

        return collect(self::DEFINITIONS)->map(function (array $definition, string $key) use ($stored): self {
            return $stored->get($key) ?? new self([
                'key' => $key,
                'message' => $definition['default_message'],
            ]);
        })->values();
    }

    public static function for(string $key): self
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException("Rotina de WhatsApp desconhecida: {$key}");
        }

        return static::query()->where('key', $key)->first()
            ?? new self(['key' => $key, 'message' => $definition['default_message']]);
    }

    public function definition(): array
    {
        return self::DEFINITIONS[$this->key];
    }

    public function render(Charge $charge): string
    {
        return strtr($this->message, collect($this->values($charge))
            ->mapWithKeys(fn (string $value, string $key): array => ["{{{$key}}}" => $value])
            ->all());
    }

    public function templateParameters(Charge $charge): array
    {
        $values = $this->values($charge);

        return array_map(
            fn (string $key): string => $values[$key],
            $this->definition()['template_parameters'],
        );
    }

    private function values(Charge $charge): array
    {
        $charge->loadMissing('client', 'lease.property.group');
        $payable = app(MoneyCalculator::class)->payable(
            $charge,
            now(config('business.billing_timezone', 'America/Sao_Paulo')),
        );

        return [
            'cliente' => $charge->client->name,
            'valor' => 'R$ '.number_format((float) $charge->amount, 2, ',', '.'),
            'valor_atualizado' => 'R$ '.number_format($payable['total'], 2, ',', '.'),
            'vencimento' => $charge->due_date->format('d/m/Y'),
            'dias_atraso' => (string) $payable['days_late'],
            'imovel' => $charge->lease->property->title,
            'grupo' => $charge->lease->property->group->name,
            'descricao' => $charge->description ?: 'Cobrança de aluguel',
        ];
    }
}
