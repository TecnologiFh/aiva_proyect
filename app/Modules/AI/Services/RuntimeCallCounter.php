<?php

namespace App\Modules\AI\Services;

class RuntimeCallCounter
{
    private static int $visionCalls = 0;
    private static int $multimodalCalls = 0;
    private static int $ocrCalls = 0;
    private static array $callLog = [];

    private static bool $firewallActive = true;
    private static int $blockedAttempts = 0;

    public static function reset(): void
    {
        self::$visionCalls = 0;
        self::$multimodalCalls = 0;
        self::$ocrCalls = 0;
        self::$blockedAttempts = 0;
        self::$callLog = [];
        self::$firewallActive = true;
    }

    public static function setFirewallActive(bool $active): void
    {
        self::$firewallActive = $active;
    }

    public static function isFirewallActive(): bool
    {
        return self::$firewallActive;
    }

    /**
     * Intercepta y BLOQUEA hostilmente cualquier invocación visual en runtime.
     * Si el firewall está activo, arroja PixelFirewallDeniedException (DENIED)
     * y mantiene las llamadas ejecutadas en 0 absoluto.
     */
    public static function enforceRuntimeFirewall(string $channel, string $operation, array $payload = []): void
    {
        if (self::$firewallActive) {
            self::$blockedAttempts++;
            self::$callLog[] = [
                'type' => strtoupper($channel),
                'operation' => $operation,
                'status' => 'DENIED_BY_PIXEL_FIREWALL',
                'time' => microtime(true),
            ];

            throw new \App\Modules\AI\Exceptions\PixelFirewallDeniedException(
                "PIXEL FIREWALL HOSTILE DENIAL: Runtime invocation of '{$channel}' ({$operation}) is strictly prohibited under Constitutional Rule 8 / AG-33. All queries must be resolved from compiled MySQL canonical catalog."
            );
        }
    }

    public static function recordVisionCall(string $reason, array $context = []): void
    {
        self::enforceRuntimeFirewall('VISION', $reason, $context);
        self::$visionCalls++;
        self::$callLog[] = ['type' => 'VISION', 'reason' => $reason, 'context' => $context, 'time' => microtime(true)];
    }

    public static function recordMultimodalCall(string $reason, array $context = []): void
    {
        self::enforceRuntimeFirewall('MULTIMODAL', $reason, $context);
        self::$multimodalCalls++;
        self::$callLog[] = ['type' => 'MULTIMODAL', 'reason' => $reason, 'context' => $context, 'time' => microtime(true)];
    }

    public static function recordOcrCall(string $reason, array $context = []): void
    {
        self::enforceRuntimeFirewall('OCR', $reason, $context);
        self::$ocrCalls++;
        self::$callLog[] = ['type' => 'OCR', 'reason' => $reason, 'context' => $context, 'time' => microtime(true)];
    }

    public static function getStats(): array
    {
        return [
            'VISION_RUNTIME_CALLS' => self::$visionCalls,
            'MULTIMODAL_RUNTIME_CALLS' => self::$multimodalCalls,
            'OCR_RUNTIME_CALLS' => self::$ocrCalls,
            'TOTAL_RUNTIME_PIXEL_CALLS' => self::$visionCalls + self::$multimodalCalls + self::$ocrCalls,
            'BLOCKED_HOSTILE_ATTEMPTS' => self::$blockedAttempts,
            'FIREWALL_ACTIVE' => self::$firewallActive,
            'call_log' => self::$callLog,
        ];
    }

    public static function assertZeroRuntimeCalls(): bool
    {
        return (self::$visionCalls === 0 && self::$multimodalCalls === 0 && self::$ocrCalls === 0);
    }
}
