<?php

namespace Modules\Reporting\Support;

use Stringable;

final class PdfDestinationNormalizer
{
    private function __construct()
    {
    }

    /**
     * @param mixed $value
     */
    public static function normalize($value): string
    {
        while (is_array($value)) {
            $next = reset($value);

            if ($next === false && $next !== 0) {
                $value = null;
                break;
            }

            $value = $next;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value !== '') {
                return strtoupper($value);
            }
        } elseif (is_scalar($value) && $value !== null) {
            $value = trim((string) $value);
            if ($value !== '') {
                return strtoupper($value);
            }
        }

        return 'I';
    }
}
