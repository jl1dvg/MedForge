<?php

if (!function_exists('mf_loadImageAny')) {
    /**
     * @return resource|object|null
     */
    function mf_loadImageAny(string $src)
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        // If source is URL, try downloading bytes first.
        if (preg_match('~^https?://~i', $src) === 1) {
            $data = @file_get_contents($src);
            if ($data === false || !function_exists('imagecreatefromstring')) {
                return null;
            }

            $im = @imagecreatefromstring($data);

            return $im ?: null;
        }

        // Resolve local candidates from raw path, docroot and current directory.
        $candidates = [$src];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($src, '/');
        }
        $candidates[] = __DIR__ . '/' . ltrim($src, '/');

        foreach ($candidates as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'png' && function_exists('imagecreatefrompng')) {
                $im = @imagecreatefrompng($path);

                return $im ?: null;
            }
            if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg')) {
                $im = @imagecreatefromjpeg($path);

                return $im ?: null;
            }

            if (!function_exists('imagecreatefromstring')) {
                continue;
            }

            $data = @file_get_contents($path);
            if ($data === false) {
                continue;
            }

            $im = @imagecreatefromstring($data);
            if ($im !== false) {
                return $im;
            }
        }

        return null;
    }
}

if (!function_exists('mf_renderMergedSignature')) {
    /**
     * Render signature + seal as one image to improve PDF compatibility.
     *
     * @param array<string, int|string> $opt
     */
    function mf_renderMergedSignature(string $topSrc, string $bottomSrc, array $opt = []): void
    {
        $padTop = (int) ($opt['padTop'] ?? 100);
        $maxHeight = (int) ($opt['maxHeight'] ?? 70);
        $boxW = (int) ($opt['boxW'] ?? 320);
        $boxH = (int) ($opt['boxH'] ?? 80);
        $alt = (string) ($opt['alt'] ?? 'Firma y sello');

        $mergedSignatureSrc = null;

        $topIm = mf_loadImageAny($topSrc);
        $bottomIm = mf_loadImageAny($bottomSrc);

        if (
            $topIm
            && $bottomIm
            && function_exists('imagecreatetruecolor')
            && function_exists('imagesx')
            && function_exists('imagesy')
            && function_exists('imagealphablending')
            && function_exists('imagesavealpha')
            && function_exists('imagecolorallocatealpha')
            && function_exists('imagefilledrectangle')
            && function_exists('imagecopy')
            && function_exists('imagepng')
            && function_exists('imagedestroy')
        ) {
            $w = max(imagesx($topIm), imagesx($bottomIm));
            $h = max(imagesy($topIm), imagesy($bottomIm)) + max(0, $padTop);

            $canvas = imagecreatetruecolor($w, $h);
            if ($canvas !== false) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $w, $h, $transparent);

                imagealphablending($canvas, true);
                imagecopy($canvas, $bottomIm, 0, max(0, $padTop), 0, 0, imagesx($bottomIm), imagesy($bottomIm));
                imagecopy($canvas, $topIm, 0, 0, 0, 0, imagesx($topIm), imagesy($topIm));

                $cacheDir = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'medforge_sig';
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0775, true);
                }

                $key = sha1($topSrc . '|' . $bottomSrc . '|' . $padTop . '|' . $maxHeight);
                $tmpPng = $cacheDir . DIRECTORY_SEPARATOR . 'sig_' . $key . '.png';

                if (!is_file($tmpPng)) {
                    @imagepng($canvas, $tmpPng);
                }

                if (is_file($tmpPng) && filesize($tmpPng) > 0) {
                    $mergedSignatureSrc = $tmpPng;
                }

                imagedestroy($canvas);
            }
        }

        if (function_exists('imagedestroy')) {
            $isTopDestroyable = is_resource($topIm) || (class_exists('GdImage') && $topIm instanceof GdImage);
            $isBottomDestroyable = is_resource($bottomIm) || (class_exists('GdImage') && $bottomIm instanceof GdImage);
            if ($isTopDestroyable) {
                imagedestroy($topIm);
            }
            if ($isBottomDestroyable) {
                imagedestroy($bottomIm);
            }
        }

        if ($mergedSignatureSrc !== null) {
            echo "<img src='" . htmlspecialchars($mergedSignatureSrc, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . "' style='max-height: {$maxHeight}px;'>";

            return;
        }

        echo "<div style='position: relative; height: {$boxH}px; width: {$boxW}px;'>";
        echo "<img src='" . htmlspecialchars($topSrc, ENT_QUOTES, 'UTF-8') . "' alt='Imagen de la firma' style='position: absolute; top: 0; left: 0; max-height: {$maxHeight}px; z-index: 2;'>";
        echo "<img src='" . htmlspecialchars($bottomSrc, ENT_QUOTES, 'UTF-8') . "' alt='Imagen del sello' style='position: absolute; top: 0; left: 0; max-height: {$maxHeight}px; z-index: 1;'>";
        echo '</div>';
    }
}

if (!function_exists('mf_user_value')) {
    function mf_user_value($userData, string $field): string
    {
        if (!is_array($userData)) {
            return '';
        }

        $value = $userData[$field] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
