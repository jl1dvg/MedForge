<?php

namespace Modules\IdentityVerification\Services;

class SignatureAnalysisService
{
    private const TARGET_WIDTH = 64;
    private const TARGET_HEIGHT = 32;

    private ?PythonBiometricClient $pythonClient;

    public function __construct(?PythonBiometricClient $pythonClient = null)
    {
        $this->pythonClient = $pythonClient;
    }

    public function createTemplateFromFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return null;
        }

        return $this->createTemplateFromBinary($data);
    }

    public function createTemplateFromBinary(string $binary): ?array
    {
        if ($binary === '') {
            return null;
        }

        $pythonFallback = null;
        if ($this->pythonClient) {
            $template = $this->pythonClient->generateTemplate('signature', $binary);
            if (is_array($template)) {
                if (($template['algorithm'] ?? null) !== 'hash-only' && !empty($template['vector'])) {
                    return $template;
                }

                $pythonFallback = $template;
            }
        }

        if (!function_exists('imagecreatefromstring')) {
            $legacyTemplate = [
                'algorithm' => 'hash-only',
                'hash' => hash('sha256', $binary),
            ];

            return $pythonFallback ?: $legacyTemplate;
        }

        $image = @imagecreatefromstring($binary);
        if (!$image) {
            $legacyTemplate = [
                'algorithm' => 'hash-only',
                'hash' => hash('sha256', $binary),
            ];

            return $pythonFallback ?: $legacyTemplate;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $target = imagecreatetruecolor(self::TARGET_WIDTH, self::TARGET_HEIGHT);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
        imagefilledrectangle($target, 0, 0, self::TARGET_WIDTH, self::TARGET_HEIGHT, $transparent);

        imagecopyresampled($target, $image, 0, 0, 0, 0, self::TARGET_WIDTH, self::TARGET_HEIGHT, $width, $height);

        $vector = [];
        for ($y = 0; $y < self::TARGET_HEIGHT; $y++) {
            for ($x = 0; $x < self::TARGET_WIDTH; $x++) {
                $rgba = imagecolorat($target, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $opacity = 1 - ($alpha / 127);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $gray = ($r * 0.3 + $g * 0.59 + $b * 0.11) / 255;
                $vector[] = round($gray * $opacity, 4);
            }
        }

        imagedestroy($target);
        imagedestroy($image);

        $template = [
            'algorithm' => 'signature-grid',
            'vector' => $vector,
        ];

        return $template ?: ($pythonFallback ?: null);
    }

    public function compareTemplates(?array $reference, ?array $sample): ?float
    {
        if (empty($reference) || empty($sample)) {
            return null;
        }

        $pythonScore = null;
        if ($this->pythonClient) {
            $score = $this->pythonClient->compare('signature', $reference, $sample);
            if ($score !== null) {
                $pythonScore = round($score, 2);
                if ($pythonScore > 0) {
                    return $pythonScore;
                }
            }
        }

        if (isset($reference['hash'], $sample['hash'])) {
            return $reference['hash'] === $sample['hash'] ? 100.0 : 0.0;
        }

        if (!isset($reference['vector'], $sample['vector'])) {
            return null;
        }

        $a = $reference['vector'];
        $b = $sample['vector'];
        $length = min(count($a), count($b));
        if ($length === 0) {
            return $pythonScore !== null ? $pythonScore : 0.0;
        }

        $diff = 0.0;
        for ($i = 0; $i < $length; $i++) {
            $diff += abs(((float) $a[$i]) - ((float) $b[$i]));
        }

        $maxDiff = $length;
        $score = 1 - min(1.0, $diff / $maxDiff);

        $legacyScore = round($score * 100, 2);

        return $pythonScore !== null ? max($pythonScore, $legacyScore) : $legacyScore;
    }
}
