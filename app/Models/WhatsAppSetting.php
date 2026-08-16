<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'api_url',
        'session_name',
        'secret_key',
        'api_token',
        'connected_phone',
        'connection_status',
        'last_error',
        'last_connected_at',
    ];

    protected $attributes = [
        'singleton' => true,
        'session_name' => 'alugapro',
        'connection_status' => 'configured',
    ];

    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
            'secret_key' => 'encrypted',
            'api_token' => 'encrypted',
            'last_connected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WhatsAppSetting $setting): void {
            $setting->singleton = true;
            $setting->api_url = rtrim($setting->api_url, '/');
        });
    }

    public static function current(): self
    {
        $setting = static::query()->firstOrNew(['singleton' => true]);

        $defaults = [
            'api_url' => config('services.wppconnect.url'),
            'session_name' => config('services.wppconnect.session', 'alugapro'),
            'secret_key' => config('services.wppconnect.secret_key'),
        ];

        foreach ($defaults as $attribute => $value) {
            if (blank($setting->{$attribute}) && filled($value)) {
                $setting->setAttribute($attribute, $value);
            }
        }

        return $setting;
    }

    public function isConfigured(): bool
    {
        return filled($this->api_url)
            && filled($this->session_name)
            && filled($this->secret_key);
    }
}
