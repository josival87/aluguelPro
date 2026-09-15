<?php

namespace App\Models;

use App\Models\Concerns\HasAdminGroupScope;
use App\Models\Scopes\AdminGroupScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Lease extends Model
{
    use HasAdminGroupScope;

    protected const ADMIN_GROUP_SCOPE_MODE = AdminGroupScope::RELATION;

    protected const ADMIN_GROUP_SCOPE_KEY = 'property';

    public const IN_FORCE_STATUSES = ['active', 'active_expired'];

    public const CLOSED_STATUSES = ['closed', 'cancelled'];

    protected $fillable = [
        'property_id', 'client_id', 'nickname', 'start_date', 'end_date', 'contract_months', 'due_day',
        'rent_amount', 'status', 'has_solar_energy', 'utility_number', 'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (Lease $lease): void {
            if ($lease->isInForce()) {
                $lease->status = $lease->end_date && $lease->end_date->toDateString() < static::contractToday()->toDateString()
                    ? 'active_expired'
                    : 'active';
            }
        });
    }

    public static function contractToday(): Carbon
    {
        return Carbon::today(config('business.billing_timezone', 'America/Sao_Paulo'));
    }

    public static function markExpiredActiveLeases(): int
    {
        return static::query()
            ->where('status', 'active')
            ->whereDate('end_date', '<', static::contractToday()->toDateString())
            ->update(['status' => 'active_expired']);
    }

    /** @return array{label: string, expired: bool}|null */
    public function contractExpiration(): ?array
    {
        if (! $this->end_date) {
            return null;
        }

        $today = static::contractToday();
        $endDate = Carbon::parse($this->end_date->toDateString(), $today->timezone);
        $expired = $endDate->lt($today);
        $months = (int) ($expired ? $endDate->diffInMonths($today) : $today->diffInMonths($endDate));
        $duration = $months === 0 ? 'menos de 1 mês' : $months.' '.($months === 1 ? 'mês' : 'meses');

        return [
            'label' => $endDate->eq($today) ? 'Vence hoje' : ($expired ? 'Vencido há ' : 'Vence em ').$duration,
            'expired' => $expired,
        ];
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'rent_amount' => 'decimal:2',
            'has_solar_energy' => 'boolean',
        ];
    }

    public function isInForce(): bool
    {
        return in_array($this->status, self::IN_FORCE_STATUSES, true);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function solarConfig()
    {
        return $this->hasOne(SolarConfig::class);
    }

    public function contract()
    {
        return $this->hasOne(LeaseContract::class);
    }

    public function documents()
    {
        return $this->hasMany(LeaseDocument::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }
}
