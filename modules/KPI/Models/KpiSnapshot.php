<?php

declare(strict_types=1);

namespace Modules\KPI\Models;

use DateTimeImmutable;

final class KpiSnapshot
{
    /**
     * @param array<string, scalar> $dimensions
     * @param array<string, mixed>|null $extra
     */
    public function __construct(
        public readonly string $kpiKey,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly string $granularity,
        public readonly array $dimensions,
        public readonly float $value,
        public readonly ?float $numerator = null,
        public readonly ?float $denominator = null,
        public readonly ?array $extra = null,
        public readonly ?DateTimeImmutable $computedAt = null,
        public readonly ?string $sourceVersion = null,
    ) {
    }

    public function dimensionHash(): string
    {
        if ($this->dimensions === []) {
            return hash('sha256', '[]');
        }

        return hash('sha256', json_encode($this->normalizedDimensions(), JSON_THROW_ON_ERROR));
    }

    public function dimensionsJson(): ?string
    {
        if ($this->dimensions === []) {
            return null;
        }

        return json_encode($this->normalizedDimensions(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function extraJson(): ?string
    {
        if ($this->extra === null) {
            return null;
        }

        return json_encode($this->extra, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, scalar>
     */
    private function normalizedDimensions(): array
    {
        $dimensions = $this->dimensions;
        ksort($dimensions);

        return $dimensions;
    }
}
