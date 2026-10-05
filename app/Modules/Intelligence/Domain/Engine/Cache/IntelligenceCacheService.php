<?php

namespace App\Modules\Intelligence\Domain\Engine\Cache;

use Illuminate\Support\Facades\Cache;

class IntelligenceCacheService
{
    private const PREFIX = 'intelligence';
    private const DEFAULT_TTL_SECONDS = 300; // 5 minutos

    /**
     * Construye una clave estricta tenant-isolated.
     */
    public function buildKey(int $tenantId, string $metricKey, string $periodKey, array $filters = []): string
    {
        ksort($filters);
        $filterHash = md5(json_encode($filters));
        return sprintf('%s:%d:%s:%s:%s', self::PREFIX, $tenantId, $metricKey, $periodKey, $filterHash);
    }

    public function get(int $tenantId, string $metricKey, string $periodKey, array $filters = []): ?array
    {
        $key = $this->buildKey($tenantId, $metricKey, $periodKey, $filters);
        return Cache::get($key);
    }

    public function put(int $tenantId, string $metricKey, string $periodKey, array $filters, array $data, int $ttl = self::DEFAULT_TTL_SECONDS): void
    {
        $key = $this->buildKey($tenantId, $metricKey, $periodKey, $filters);
        Cache::put($key, $data, $ttl);
    }

    public function forget(int $tenantId, string $metricKey, string $periodKey, array $filters = []): void
    {
        $key = $this->buildKey($tenantId, $metricKey, $periodKey, $filters);
        Cache::forget($key);
    }
}
