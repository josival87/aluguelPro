<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    public const TEMPLATE_EVENTS = [
        'client_access_otp' => [
            'name' => 'Código para criar acesso',
            'parameters' => ['Código', 'Validade em minutos'],
        ],
        'signature_otp' => [
            'name' => 'Código para assinar contrato',
            'parameters' => ['Código', 'Validade em minutos'],
        ],
        'new_applicant' => [
            'name' => 'Novo interessado em imóvel',
            'parameters' => ['Nome do interessado', 'Imóvel', 'Número da proposta'],
        ],
    ];

    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'graph_api_version',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'app_secret',
        'webhook_verify_token',
        'message_templates',
        'connected_phone',
        'connection_status',
        'last_error',
        'last_connected_at',
        'webhook_subscribed_at',
    ];

    protected $attributes = [
        'singleton' => true,
        'graph_api_version' => 'v26.0',
        'connection_status' => 'configured',
    ];

    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'webhook_verify_token' => 'encrypted',
            'message_templates' => 'array',
            'last_connected_at' => 'datetime',
            'webhook_subscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WhatsAppSetting $setting): void {
            $setting->singleton = true;
            $setting->graph_api_version = strtolower(trim($setting->graph_api_version));
        });
    }

    public static function current(): self
    {
        $setting = static::query()->firstOrNew(['singleton' => true]);

        $defaults = [
            'graph_api_version' => config('services.meta_whatsapp.graph_api_version', 'v26.0'),
            'phone_number_id' => config('services.meta_whatsapp.phone_number_id'),
            'business_account_id' => config('services.meta_whatsapp.business_account_id'),
            'access_token' => config('services.meta_whatsapp.access_token'),
            'app_secret' => config('services.meta_whatsapp.app_secret'),
            'webhook_verify_token' => config('services.meta_whatsapp.webhook_verify_token'),
        ];

        foreach ($defaults as $attribute => $value) {
            if ((! $setting->exists || blank($setting->{$attribute})) && filled($value)) {
                $setting->setAttribute($attribute, $value);
            }
        }

        return $setting;
    }

    public function isConfigured(): bool
    {
        return filled($this->graph_api_version)
            && filled($this->phone_number_id)
            && filled($this->business_account_id)
            && filled($this->access_token);
    }

    public function hasWebhookSecurity(): bool
    {
        return filled($this->app_secret) && filled($this->webhook_verify_token);
    }

    public function templateFor(string $event): ?array
    {
        $template = data_get($this->message_templates, $event);

        if (! is_array($template) || blank($template['name'] ?? null)) {
            return null;
        }

        return [
            'name' => (string) $template['name'],
            'language' => (string) ($template['language'] ?? 'pt_BR'),
        ];
    }
}
