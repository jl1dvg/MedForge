<?php

declare(strict_types=1);

namespace App\Modules\Codes\Services;

use App\Models\AfiliacionCategoriaMap;
use App\Models\Price;
use App\Models\PriceLevel;
use Illuminate\Support\Facades\DB;

class CodePriceService
{
    private ?int $levelKeyMaxLength = null;

    /**
     * @return array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}>
     */
    public function levels(): array
    {
        $affiliationLevels = AfiliacionCategoriaMap::query()
            ->select(['afiliacion_norm', 'afiliacion_raw', 'categoria'])
            ->whereRaw("TRIM(COALESCE(afiliacion_norm, '')) <> ''")
            ->orderBy('categoria')
            ->orderBy('afiliacion_raw')
            ->get();

        if ($affiliationLevels->isNotEmpty()) {
            return $affiliationLevels->map(function (AfiliacionCategoriaMap $row): array {
                $levelKey = trim((string) ($row->afiliacion_norm ?? ''));
                $title = trim((string) ($row->afiliacion_raw ?? ''));

                return [
                    'level_key' => $levelKey,
                    'storage_key' => $this->storageKey($levelKey),
                    'title' => $title !== '' ? $title : $levelKey,
                    'category' => trim((string) ($row->categoria ?? '')),
                    'source' => 'afiliacion_categoria_map',
                ];
            })->all();
        }

        $legacyLevels = PriceLevel::query()
            ->where('active', 1)
            ->orderBy('seq')
            ->orderBy('title')
            ->get(['level_key', 'title']);

        return $legacyLevels->map(static function (PriceLevel $row): array {
            $levelKey = trim((string) ($row->level_key ?? ''));
            $title = trim((string) ($row->title ?? ''));

            return [
                'level_key' => $levelKey,
                'storage_key' => $levelKey,
                'title' => $title !== '' ? $title : $levelKey,
                'category' => '',
                'source' => 'price_levels',
            ];
        })->filter(static fn (array $row): bool => $row['level_key'] !== '')->values()->all();
    }

    /**
     * @param array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}> $levels
     * @return array<string, true>
     */
    public function levelKeyMap(array $levels): array
    {
        $map = [];
        foreach ($levels as $level) {
            $key = trim((string) ($level['level_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $map[$key] = true;
        }

        return $map;
    }

    /**
     * @param array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}> $levels
     */
    public function resolveLevelKey(string $affiliation, array $levels = []): ?string
    {
        $normalizedAffiliation = $this->normalizeLookupText($affiliation);
        if ($normalizedAffiliation === '') {
            return null;
        }

        foreach ($levels as $level) {
            $canonicalKey = trim((string) ($level['level_key'] ?? ''));
            if ($canonicalKey === '') {
                continue;
            }

            $title = trim((string) ($level['title'] ?? ''));
            $titleMatch = $title !== '' && $this->normalizeLookupText($title) === $normalizedAffiliation;
            $keyMatch = $this->normalizeLookupText($canonicalKey) === $normalizedAffiliation;

            if ($titleMatch || $keyMatch) {
                return $canonicalKey;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}> $levels
     * @return array<string, float>
     */
    public function pricesForCode(int $codeId, array $levels = []): array
    {
        $storageToCanonical = $this->storageToCanonicalMap($levels);

        $rows = Price::query()
            ->where('code_id', $codeId)
            ->get(['level_key', 'price']);

        $prices = [];
        foreach ($rows as $row) {
            $storageKey = trim((string) ($row->level_key ?? ''));
            if ($storageKey === '') {
                continue;
            }

            $canonicalKey = $storageToCanonical[$storageKey] ?? $storageKey;
            $prices[$canonicalKey] = (float) ($row->price ?? 0);
        }

        return $prices;
    }

    /**
     * @param array<string, mixed> $prices
     * @param array<string, true> $allowedLevelKeys
     */
    public function syncPricesForCode(int $codeId, array $prices, array $allowedLevelKeys = []): void
    {
        foreach ($prices as $canonicalLevelKey => $rawPrice) {
            $canonicalLevelKey = trim((string) $canonicalLevelKey);
            if ($canonicalLevelKey === '') {
                continue;
            }

            if ($allowedLevelKeys !== [] && !isset($allowedLevelKeys[$canonicalLevelKey])) {
                continue;
            }

            $storageKey = $this->storageKey($canonicalLevelKey);
            $priceValue = $this->normalizePrice($rawPrice);

            if ($priceValue === null) {
                Price::query()
                    ->where('code_id', $codeId)
                    ->where('level_key', $storageKey)
                    ->delete();
                continue;
            }

            Price::query()->updateOrCreate(
                [
                    'code_id' => $codeId,
                    'level_key' => $storageKey,
                ],
                [
                    'price' => $priceValue,
                ]
            );
        }
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}> $levels
     * @return array<string, string>
     */
    private function storageToCanonicalMap(array $levels): array
    {
        $map = [];
        foreach ($levels as $level) {
            $canonical = trim((string) ($level['level_key'] ?? ''));
            if ($canonical === '') {
                continue;
            }

            $storage = trim((string) ($level['storage_key'] ?? ''));
            if ($storage === '') {
                $storage = $this->storageKey($canonical);
            }
            if ($storage === '') {
                continue;
            }

            $map[$storage] = $canonical;
        }

        return $map;
    }

    private function storageKey(string $levelKey): string
    {
        $levelKey = trim($levelKey);
        if ($levelKey === '') {
            return '';
        }

        $maxLength = $this->levelKeyMaxLength();
        if (strlen($levelKey) <= $maxLength) {
            return $levelKey;
        }

        $hash = sha1($levelKey);
        $prefix = 'af_';
        $roomForHash = $maxLength - strlen($prefix);

        if ($roomForHash <= 0) {
            return substr($hash, 0, $maxLength);
        }

        return $prefix . substr($hash, 0, $roomForHash);
    }

    private function levelKeyMaxLength(): int
    {
        if ($this->levelKeyMaxLength !== null) {
            return $this->levelKeyMaxLength;
        }

        $default = 32;
        try {
            $row = DB::selectOne(
                "SELECT CHARACTER_MAXIMUM_LENGTH AS max_len
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'prices'
                   AND COLUMN_NAME = 'level_key'
                 LIMIT 1"
            );

            $resolved = isset($row->max_len) ? (int) $row->max_len : $default;
            $this->levelKeyMaxLength = $resolved > 0 ? $resolved : $default;
        } catch (\Throwable) {
            $this->levelKeyMaxLength = $default;
        }

        return $this->levelKeyMaxLength;
    }

    private function normalizeLookupText(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $normalized = mb_strtolower($value, 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');

        return $normalized;
    }
}
