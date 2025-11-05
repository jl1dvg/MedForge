<?php

namespace Modules\Reporting\Support;

final class RenderContext
{
    /**
     * @var array<int, bool>
     */
    private static array $fragmentStack = [];

    public static function isFragment(): bool
    {
        if (self::$fragmentStack === []) {
            return false;
        }

        return end(self::$fragmentStack) === true;
    }

    /**
     * Execute the callback while marking the current render as a fragment.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function withFragment(callable $callback)
    {
        self::$fragmentStack[] = true;

        try {
            return $callback();
        } finally {
            array_pop(self::$fragmentStack);
        }
    }
}
