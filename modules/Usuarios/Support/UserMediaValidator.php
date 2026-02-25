<?php

namespace Modules\Usuarios\Support;

class UserMediaValidator
{
    public const TYPE_SEAL = 'seal';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_SEAL_SIGNATURE = 'seal_signature';

    private const MAX_BYTES = 2 * 1024 * 1024; // 2MB
    private const MIN_WIDTH = 64;
    private const MIN_HEIGHT = 32;
    private const MAX_WIDTH = 1600;
    private const MAX_HEIGHT = 900;

    private const ALLOWED_MIME = [
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const TYPE_DIRECTORIES = [
        self::TYPE_SEAL => 'seals',
        self::TYPE_SIGNATURE => 'signatures',
        self::TYPE_SEAL_SIGNATURE => 'seal-signatures',
    ];

    /**
     * @return array{error: string|null, extension: string|null, mime: string|null, size: int|null, hash: string|null, width: int|null, height: int|null}
     */
    public function validate(array $file): array
    {
        if (!isset($file['tmp_name'], $file['size'], $file['error'])) {
            return $this->result('El archivo subido no es válido.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->result($this->uploadErrorMessage($file['error']));
        }

        $size = (int) $file['size'];
        if ($size <= 0) {
            return $this->result('El archivo está vacío.');
        }

        if ($size > self::MAX_BYTES) {
            return $this->result('El archivo excede el tamaño máximo permitido (2 MB).');
        }

        $tmpPath = (string) $file['tmp_name'];
        if (!is_file($tmpPath)) {
            return $this->result('No se pudo acceder al archivo temporal.');
        }

        $mime = $this->detectMimeType($tmpPath);
        if ($mime === null || !isset(self::ALLOWED_MIME[$mime])) {
            return $this->result('El archivo debe ser una imagen PNG, WEBP o SVG.');
        }

        $extension = self::ALLOWED_MIME[$mime];
        [$width, $height] = $this->resolveDimensions($tmpPath, $mime);

        if ($width !== null && $height !== null) {
            if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
                return $this->result('La imagen es demasiado pequeña.');
            }

            if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
                return $this->result('La imagen excede las dimensiones máximas permitidas.');
            }
        }

        return [
            'error' => null,
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
            'hash' => sha1_file($tmpPath) ?: null,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{absolute: string, public: string}
     */
    public function destinationFor(string $type, string $filename, string $basePath): array
    {
        $directory = self::TYPE_DIRECTORIES[$type] ?? self::TYPE_DIRECTORIES[self::TYPE_SEAL];
        $relative = '/uploads/users/' . $directory;
        $absoluteDir = rtrim($basePath, '/') . '/public' . $relative;

        return [
            'absolute' => $absoluteDir . '/' . $filename,
            'public' => $relative . '/' . $filename,
        ];
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    public function dimensionLimits(): array
    {
        return ['width' => self::MAX_WIDTH, 'height' => self::MAX_HEIGHT];
    }

    public function generateFilename(string $extension): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $random = bin2hex(openssl_random_pseudo_bytes(16));
        }

        return date('YmdHis') . '_' . $random . '.' . $extension;
    }

    private function detectMimeType(string $path): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }

        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);

        return $mime;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveDimensions(string $path, string $mime): array
    {
        if ($mime === 'image/svg+xml') {
            $svg = @file_get_contents($path);
            if ($svg === false) {
                return [null, null];
            }

            $width = $this->extractSvgSize($svg, 'width');
            $height = $this->extractSvgSize($svg, 'height');
            return [$width, $height];
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return [null, null];
        }

        return [$info[0] ?? null, $info[1] ?? null];
    }

    private function extractSvgSize(string $contents, string $attribute): ?int
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*"([0-9]+)(?:px)?"/i';
        if (preg_match($pattern, $contents, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array{error: string|null, extension: string|null, mime: string|null, size: int|null, hash: string|null, width: int|null, height: int|null}
     */
    private function result(string $error): array
    {
        return [
            'error' => $error,
            'extension' => null,
            'mime' => null,
            'size' => null,
            'hash' => null,
            'width' => null,
            'height' => null,
        ];
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'La carga del archivo fue interrumpida.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal para procesar archivos.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión del servidor bloqueó la carga del archivo.',
            default => 'No se pudo cargar el archivo. Intenta nuevamente.',
        };
    }
}
