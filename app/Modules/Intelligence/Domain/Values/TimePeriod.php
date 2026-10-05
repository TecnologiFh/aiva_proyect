<?php

namespace App\Modules\Intelligence\Domain\Values;

use Carbon\Carbon;
use InvalidArgumentException;

class TimePeriod
{
    public const TODAY = 'today';
    public const YESTERDAY = 'yesterday';
    public const THIS_WEEK = 'this_week';
    public const LAST_WEEK = 'last_week';
    public const THIS_MONTH = 'this_month';
    public const LAST_MONTH = 'last_month';
    public const CUSTOM_RANGE = 'custom_range';
    public const ALL_TIME = 'all_time';

    public readonly string $key;
    public readonly string $timezone;
    public readonly ?string $dateFrom; // Y-m-d in tenant local time
    public readonly ?string $dateTo;   // Y-m-d in tenant local time

    public function __construct(
        string $key = self::THIS_MONTH,
        string $timezone = 'America/Lima',
        ?string $dateFrom = null,
        ?string $dateTo = null
    ) {
        $this->key = strtolower(trim($key));
        $this->timezone = $timezone;

        // Validar timezone
        if (!in_array($this->timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Zona horaria inválida: {$this->timezone}");
        }

        $now = Carbon::now($this->timezone);

        switch ($this->key) {
            case self::TODAY:
                $this->dateFrom = $now->toDateString();
                $this->dateTo = $now->toDateString();
                break;

            case self::YESTERDAY:
                $yesterday = $now->copy()->subDay();
                $this->dateFrom = $yesterday->toDateString();
                $this->dateTo = $yesterday->toDateString();
                break;

            case self::THIS_WEEK:
                $this->dateFrom = $now->copy()->startOfWeek()->toDateString();
                $this->dateTo = $now->copy()->endOfWeek()->toDateString();
                break;

            case self::LAST_WEEK:
                $lastWeek = $now->copy()->subWeek();
                $this->dateFrom = $lastWeek->startOfWeek()->toDateString();
                $this->dateTo = $lastWeek->endOfWeek()->toDateString();
                break;

            case self::THIS_MONTH:
                $this->dateFrom = $now->copy()->startOfMonth()->toDateString();
                $this->dateTo = $now->copy()->endOfMonth()->toDateString();
                break;

            case self::LAST_MONTH:
                $lastMonth = $now->copy()->subMonth();
                $this->dateFrom = $lastMonth->startOfMonth()->toDateString();
                $this->dateTo = $lastMonth->endOfMonth()->toDateString();
                break;

            case self::CUSTOM_RANGE:
                if (!$dateFrom || !$dateTo) {
                    throw new InvalidArgumentException("Para custom_range se requieren dateFrom y dateTo.");
                }
                $this->dateFrom = $dateFrom;
                $this->dateTo = $dateTo;
                break;

            case self::ALL_TIME:
                $this->dateFrom = null;
                $this->dateTo = null;
                break;

            default:
                // Si viene un periodo desconocido pero trae dateFrom y dateTo, fallback a custom_range
                if ($dateFrom && $dateTo) {
                    $this->dateFrom = $dateFrom;
                    $this->dateTo = $dateTo;
                } else {
                    $this->dateFrom = $now->copy()->startOfMonth()->toDateString();
                    $this->dateTo = $now->copy()->endOfMonth()->toDateString();
                }
                break;
        }
    }

    /**
     * Calcula el periodo equivalente previo para comparación.
     */
    public function getPreviousPeriod(): ?self
    {
        if ($this->key === self::ALL_TIME) {
            return null;
        }

        $now = Carbon::now($this->timezone);

        switch ($this->key) {
            case self::TODAY:
                return new self(self::YESTERDAY, $this->timezone);

            case self::YESTERDAY:
                $dayBefore = $now->copy()->subDays(2)->toDateString();
                return new self(self::CUSTOM_RANGE, $this->timezone, $dayBefore, $dayBefore);

            case self::THIS_WEEK:
                return new self(self::LAST_WEEK, $this->timezone);

            case self::LAST_WEEK:
                $twoWeeksAgo = $now->copy()->subWeeks(2);
                return new self(self::CUSTOM_RANGE, $this->timezone, $twoWeeksAgo->startOfWeek()->toDateString(), $twoWeeksAgo->endOfWeek()->toDateString());

            case self::THIS_MONTH:
                return new self(self::LAST_MONTH, $this->timezone);

            case self::LAST_MONTH:
                $twoMonthsAgo = $now->copy()->subMonths(2);
                return new self(self::CUSTOM_RANGE, $this->timezone, $twoMonthsAgo->startOfMonth()->toDateString(), $twoMonthsAgo->endOfMonth()->toDateString());

            case self::CUSTOM_RANGE:
            default:
                $start = Carbon::parse($this->dateFrom, $this->timezone);
                $end = Carbon::parse($this->dateTo, $this->timezone);
                $durationDays = $start->diffInDays($end) + 1;

                $prevEnd = $start->copy()->subDay();
                $prevStart = $prevEnd->copy()->subDays($durationDays - 1);

                return new self(self::CUSTOM_RANGE, $this->timezone, $prevStart->toDateString(), $prevEnd->toDateString());
        }
    }

    /**
     * Inicio en UTC para queries SQL.
     */
    public function getStartUtcString(): ?string
    {
        if (!$this->dateFrom) {
            return null;
        }
        return Carbon::parse($this->dateFrom, $this->timezone)
            ->startOfDay()
            ->setTimezone('UTC')
            ->toDateTimeString();
    }

    /**
     * Fin en UTC para queries SQL.
     */
    public function getEndUtcString(): ?string
    {
        if (!$this->dateTo) {
            return null;
        }
        return Carbon::parse($this->dateTo, $this->timezone)
            ->endOfDay()
            ->setTimezone('UTC')
            ->toDateTimeString();
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'timezone' => $this->timezone,
            'from' => $this->dateFrom ?? 'ALL_TIME',
            'to' => $this->dateTo ?? 'ALL_TIME',
            'start_utc' => $this->getStartUtcString(),
            'end_utc' => $this->getEndUtcString(),
        ];
    }
}
