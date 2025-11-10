<?php

namespace Modules\IdentityVerification\Services;

class FaceRecognitionService
{
    private const TARGET_SIZE = 32;

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
            $template = $this->pythonClient->generateTemplate('face', $binary);
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
        $target = imagecreatetruecolor(self::TARGET_SIZE, self::TARGET_SIZE);
        imagecopyresampled($target, $image, 0, 0, 0, 0, self::TARGET_SIZE, self::TARGET_SIZE, $width, $height);

        $vector = [];
        for ($y = 0; $y < self::TARGET_SIZE; $y++) {
            for ($x = 0; $x < self::TARGET_SIZE; $x++) {
                $rgb = imagecolorat($target, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = ($r * 0.3 + $g * 0.59 + $b * 0.11) / 255;
                $vector[] = round($gray, 4);
            }
        }

        imagedestroy($target);
        imagedestroy($image);

        $norm = $this->euclideanNorm($vector);
        if ($norm > 0) {
            foreach ($vector as &$value) {
                $value = round($value / $norm, 6);
            }
            unset($value);
        }

        $template = [
            'algorithm' => 'gd-grayscale-' . self::TARGET_SIZE,
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
            $score = $this->pythonClient->compare('face', $reference, $sample);
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
            return null;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0 || $normB <= 0) {
            return $pythonScore !== null ? $pythonScore : 0.0;
        }

        $similarity = $dot / (sqrt($normA) * sqrt($normB));
        $score = max(0.0, min(1.0, $similarity));

        $legacyScore = round($score * 100, 2);

        return $pythonScore !== null ? max($pythonScore, $legacyScore) : $legacyScore;
    }

    private function euclideanNorm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += ((float) $value) ** 2;
        }

        return sqrt($sum);
    }
}
