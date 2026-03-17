<?php

namespace App\Modules\Shared\Support;

final class MedforgeAssets
{
    public static function hasViteBuild(): bool
    {
        return file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
    }
}
