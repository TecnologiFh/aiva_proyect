<?php

namespace App\Modules\Intelligence\DTOs;

use App\Core\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AnalyticsFilterSet
{
    public const TIMEZONE_UNRESOLVED = 'TIMEZONE_UNRESOLVED';
    public const CURRENCY_UNRESOLVED = 'CURRENCY_UNRESOLVED';

    public readonly int $tenantId;
    public readonly ?string $dateFrom;
    public readonly ?string $dateTo;
    public readonly string $timezone;
    public readonly bool $isTimezoneResolved;
    public readonly string $currency;
    public readonly bool $isCurrencyResolved;
    public readonly ?string $channel;
    public readonly ?string $paymentMethod;
    public readonly array $statusFilters;
    public readonly ?array $comparisonPeriod;

    public function __construct(
        ?int $tenantId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $timezone = null,
        ?string $currency = null,
        ?string $channel = null,
        ?string $paymentMethod = null,
        array $statusFilters = [],
        ?array $comparisonPeriod = null
    ) {
        $resolvedTenantId = $tenantId ?? TenantContext::getTenantId();
        if (!$resolvedTenantId) {
            throw new HttpException(403, 'Contexto de tenant requerido para ejecutar consultas analíticas.');
        }
        $this->tenantId = (int) $resolvedTenantId;

        // Resolver metadata autorizada del Tenant desde la tabla `tenants`
        $tenantRecord = DB::table('tenants')->where('id', $this->tenantId)->first();

        // Resolución de Timezone con prevalencia estricta de tenants.timezone
        // Si tenants.timezone es NULL, vacío o inválido, NO utilizar UTC, APP_TIMEZONE ni America/Lima como sustituto silencioso.
        $rawTz = null;
        if ($timezone !== null && trim($timezone) !== '') {
            $rawTz = trim($timezone);
        } elseif ($tenantRecord && !empty($tenantRecord->timezone) && trim($tenantRecord->timezone) !== '') {
            $rawTz = trim($tenantRecord->timezone);
        }

        if ($rawTz !== null && in_array($rawTz, \DateTimeZone::listIdentifiers(), true)) {
            $this->timezone = $rawTz;
            $this->isTimezoneResolved = true;
        } else {
            $this->timezone = self::TIMEZONE_UNRESOLVED;
            $this->isTimezoneResolved = false;
        }

        // Resolución de Moneda con regla de CURRENCY_UNRESOLVED
        // Si tenants.currency es NULL o vacío, NO asumir PEN, USD ni APP_DEFAULT.
        $rawCurrency = null;
        if ($currency !== null && trim($currency) !== '') {
            $rawCurrency = trim($currency);
        } elseif ($tenantRecord && !empty($tenantRecord->currency) && trim($tenantRecord->currency) !== '') {
            $rawCurrency = trim($tenantRecord->currency);
        }

        if ($rawCurrency !== null && $rawCurrency !== self::CURRENCY_UNRESOLVED) {
            $this->currency = strtoupper($rawCurrency);
            $this->isCurrencyResolved = true;
        } else {
            $this->currency = self::CURRENCY_UNRESOLVED;
            $this->isCurrencyResolved = false;
        }

        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->channel = $channel;
        $this->paymentMethod = $paymentMethod;
        $this->statusFilters = $statusFilters;
        $this->comparisonPeriod = $comparisonPeriod;
    }

    /**
     * Devuelve el inicio del rango temporal normalizado en el timezone del tenant.
     * Retorna null si el timezone no está resuelto.
     */
    public function getStartDateTimeUtcString(): ?string
    {
        if (!$this->dateFrom || !$this->isTimezoneResolved) {
            return null;
        }
        return Carbon::parse($this->dateFrom, $this->timezone)
            ->startOfDay()
            ->setTimezone('UTC')
            ->toDateTimeString();
    }

    /**
     * Devuelve el fin del rango temporal normalizado en el timezone del tenant.
     * Retorna null si el timezone no está resuelto.
     */
    public function getEndDateTimeUtcString(): ?string
    {
        if (!$this->dateTo || !$this->isTimezoneResolved) {
            return null;
        }
        return Carbon::parse($this->dateTo, $this->timezone)
            ->endOfDay()
            ->setTimezone('UTC')
            ->toDateTimeString();
    }

    /**
     * Serialización para auditoría y firmas de caché.
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'timezone' => $this->timezone,
            'is_timezone_resolved' => $this->isTimezoneResolved,
            'currency' => $this->currency,
            'is_currency_resolved' => $this->isCurrencyResolved,
            'channel' => $this->channel,
            'payment_method' => $this->paymentMethod,
            'status_filters' => $this->statusFilters,
            'comparison_period' => $this->comparisonPeriod,
        ];
    }
}
