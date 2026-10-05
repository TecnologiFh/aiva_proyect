<?php

namespace App\Modules\Intelligence\Domain\Values;

class PeriodComparison
{
    public readonly float $currentValue;
    public readonly float $previousValue;
    public readonly float $absoluteChange;
    public readonly ?float $percentageChange;
    public readonly string $trend; // 'UP', 'DOWN', 'NEUTRAL'
    public readonly array $previousPeriod;

    public function __construct(
        float $currentValue,
        float $previousValue,
        array $previousPeriod = []
    ) {
        $this->currentValue = round($currentValue, 4);
        $this->previousValue = round($previousValue, 4);
        $this->previousPeriod = $previousPeriod;

        $this->absoluteChange = round($this->currentValue - $this->previousValue, 4);

        if ($this->previousValue == 0.0) {
            if ($this->currentValue == 0.0) {
                $this->percentageChange = 0.0;
                $this->trend = 'NEUTRAL';
            } else {
                $this->percentageChange = 100.0;
                $this->trend = 'UP';
            }
        } else {
            $change = (($this->currentValue - $this->previousValue) / abs($this->previousValue)) * 100.0;
            $this->percentageChange = round($change, 2);
            if ($this->percentageChange > 0) {
                $this->trend = 'UP';
            } elseif ($this->percentageChange < 0) {
                $this->trend = 'DOWN';
            } else {
                $this->trend = 'NEUTRAL';
            }
        }
    }

    public function toArray(): array
    {
        return [
            'current_value' => $this->currentValue,
            'previous_value' => $this->previousValue,
            'absolute_change' => $this->absoluteChange,
            'percentage_change' => $this->percentageChange,
            'trend' => $this->trend,
            'previous_period' => $this->previousPeriod,
        ];
    }
}
