<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class ImagenesPdfCacheService
{
    private const KEY_PREFIX = 'imagenes:pdf-cache:v1:';
    private const FORM_PREFIX = 'imagenes:pdf-cache-form:v1:';

    public function __construct(
        private ?Repository $cache = null,
        private ?string $cacheDir = null,
        private ?int $ttlSeconds = null
    ) {
        $this->cache ??= Cache::store();
        $this->cacheDir ??= storage_path('app/imagenes-pdf-cache');
        $this->ttlSeconds ??= max(60, (int) env('IMAGENES_PDF_CACHE_SECONDS', 1800));
    }

    /**
     * @param array<string, mixed> $params
     * @param Closure():array<string, mixed>|null $resolver
     * @return array<string, mixed>|null
     */
    public function remember(string $type, array $params, ?string $formId, Closure $resolver): ?array
    {
        $key = $this->cacheKey($type, $params);
        $cached = $this->read($key);
        if ($cached !== null) {
            $cached['cached'] = true;

            return $cached;
        }

        $pdf = $resolver();
        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return $pdf;
        }

        $content = (string) $pdf['content'];
        $filename = (string) $pdf['filename'];
        if ($content === '' || $filename === '') {
            return $pdf;
        }

        $path = $this->pathForKey($key);
        $this->ensureCacheDir();
        $tmpPath = $path . '.part.' . bin2hex(random_bytes(4));
        file_put_contents($tmpPath, $content);
        rename($tmpPath, $path);

        $metadata = [
            'path' => $path,
            'filename' => $filename,
            'bytes' => strlen($content),
        ];
        $this->cache->put($key, $metadata, $this->ttlSeconds);
        $this->trackFormKey($formId, $key);

        $pdf['cached'] = false;

        return $pdf;
    }

    public function forgetForm(string $formId): void
    {
        $formId = trim($formId);
        if ($formId === '') {
            return;
        }

        $indexKey = self::FORM_PREFIX . sha1($formId);
        $keys = $this->cache->get($indexKey, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $metadata = $this->cache->get($key);
            if (is_array($metadata) && is_string($metadata['path'] ?? null)) {
                @unlink($metadata['path']);
            }
            $this->cache->forget($key);
        }

        $this->cache->forget($indexKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $key): ?array
    {
        $metadata = $this->cache->get($key);
        if (!is_array($metadata)) {
            return null;
        }

        $path = is_string($metadata['path'] ?? null) ? $metadata['path'] : '';
        $filename = is_string($metadata['filename'] ?? null) ? $metadata['filename'] : '';
        if ($path === '' || $filename === '' || !is_file($path)) {
            $this->cache->forget($key);

            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->cache->forget($key);

            return null;
        }

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function cacheKey(string $type, array $params): string
    {
        ksort($params);

        return self::KEY_PREFIX . sha1($type . '|' . json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    private function pathForKey(string $key): string
    {
        return rtrim((string) $this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sha1($key) . '.pdf';
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir((string) $this->cacheDir)) {
            mkdir((string) $this->cacheDir, 0775, true);
        }
    }

    private function trackFormKey(?string $formId, string $key): void
    {
        $formId = trim((string) $formId);
        if ($formId === '') {
            return;
        }

        $indexKey = self::FORM_PREFIX . sha1($formId);
        $keys = $this->cache->get($indexKey, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        $keys[] = $key;
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));
        $this->cache->put($indexKey, $keys, $this->ttlSeconds);
    }
}
