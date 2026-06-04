<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class ImagenesNasListCacheService
{
    private const KEY_PREFIX = 'imagenes:nas-list:v1:';

    public function __construct(
        private ?Repository $cache = null,
        private ?int $ttlSeconds = null
    ) {
        $this->cache ??= Cache::store();
        $this->ttlSeconds ??= max(30, (int) env('IMAGENES_NAS_LIST_CACHE_SECONDS', 300));
    }

    /**
     * @param Closure():array<string, mixed> $resolver
     * @return array<string, mixed>
     */
    public function remember(string $hcNumber, string $formId, Closure $resolver): array
    {
        $key = $this->key($hcNumber, $formId);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            $cached['cached'] = true;

            return $cached;
        }

        $value = $resolver();
        if (($value['success'] ?? false) === true) {
            $this->cache->put($key, $value, $this->ttlSeconds);
        }
        $value['cached'] = false;

        return $value;
    }

    public function forget(string $hcNumber, string $formId): void
    {
        $this->cache->forget($this->key($hcNumber, $formId));
    }

    private function key(string $hcNumber, string $formId): string
    {
        return self::KEY_PREFIX . sha1(trim($hcNumber) . '|' . trim($formId));
    }
}
