<?php

namespace Modules\Examenes\Services;

class NasImagenesService
{
    private ?string $mountPath;
    private ?string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private ?string $basePath;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->mountPath = $this->readEnv('NAS_IMAGES_MOUNT');
        $this->host = $this->readEnv('NAS_IMAGES_SSH_HOST');
        $this->port = (int)($this->readEnv('NAS_IMAGES_SSH_PORT') ?: 22);
        $this->username = $this->readEnv('NAS_IMAGES_SSH_USER');
        $this->password = $this->readEnv('NAS_IMAGES_SSH_PASS');
        $this->basePath = $this->readEnv('NAS_IMAGES_BASE_PATH') ?: '/volume1/Imagenes';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function isAvailable(): bool
    {
        if ($this->mountPath !== null && $this->mountPath !== '' && is_dir($this->mountPath)) {
            return true;
        }

        if ($this->host && $this->username && $this->password && function_exists('ssh2_connect')) {
            return true;
        }

        if ($this->host && $this->username && $this->password && class_exists('\\phpseclib3\\Net\\SFTP')) {
            return true;
        }

        if ($this->host && $this->username && $this->password) {
            $this->lastError = 'SFTP no disponible en este servidor.';
            return false;
        }

        $this->lastError = 'NAS no configurado.';
        return false;
    }

    /**
     * @return array<int, array{name: string, size: int, mtime: int, ext: string, type: string}>
     */
    public function listFiles(string $hcNumber, string $formId): array
    {
        $this->lastError = null;
        $hcNumber = $this->sanitizeSegment($hcNumber);
        $formId = $this->sanitizeSegment($formId);
        if ($hcNumber === '' || $formId === '') {
            $this->lastError = 'Parámetros inválidos.';
            return [];
        }

        $path = $this->buildPath($hcNumber, $formId);
        if ($path === null) {
            $this->lastError = 'No se pudo resolver la ruta del NAS.';
            return [];
        }

        if ($this->mountPath !== null && $this->mountPath !== '' && is_dir($this->mountPath)) {
            return $this->listFilesLocal($path);
        }

        if ($this->host && $this->username && $this->password && function_exists('ssh2_connect')) {
            return $this->listFilesSftp($path);
        }

        if ($this->host && $this->username && $this->password && class_exists('\\phpseclib3\\Net\\SFTP')) {
            return $this->listFilesPhpseclib($path);
        }

        $this->lastError = 'NAS no disponible.';
        return [];
    }

    /**
     * @return array{stream: resource, size: int, ext: string, type: string, name: string}|null
     */
    public function openFile(string $hcNumber, string $formId, string $filename): ?array
    {
        $this->lastError = null;
        $hcNumber = $this->sanitizeSegment($hcNumber);
        $formId = $this->sanitizeSegment($formId);
        $filename = $this->sanitizeFilename($filename);
        if ($hcNumber === '' || $formId === '' || $filename === '') {
            $this->lastError = 'Parámetros inválidos.';
            return null;
        }

        $path = $this->buildPath($hcNumber, $formId);
        if ($path === null) {
            $this->lastError = 'No se pudo resolver la ruta del NAS.';
            return null;
        }

        if ($this->mountPath !== null && $this->mountPath !== '' && is_dir($this->mountPath)) {
            $fullPath = rtrim($path, '/') . '/' . $filename;
            if (!is_file($fullPath)) {
                $this->lastError = 'Archivo no encontrado.';
                return null;
            }
            $stream = fopen($fullPath, 'rb');
            if (!$stream) {
                $this->lastError = 'No se pudo abrir el archivo.';
                return null;
            }
            $size = filesize($fullPath) ?: 0;
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return [
                'stream' => $stream,
                'size' => (int)$size,
                'ext' => $ext,
                'type' => $this->mapMime($ext),
                'name' => $filename,
            ];
        }

        if ($this->host && $this->username && $this->password && function_exists('ssh2_connect')) {
            return $this->openFileSftp($path, $filename);
        }

        if ($this->host && $this->username && $this->password && class_exists('\\phpseclib3\\Net\\SFTP')) {
            return $this->openFilePhpseclib($path, $filename);
        }

        $this->lastError = 'NAS no disponible.';
        return null;
    }

    private function readEnv(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function sanitizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/[^0-9A-Za-z_-]/', '', $value);
        return $value ?? '';
    }

    private function sanitizeFilename(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = basename($value);
        if (str_contains($value, '..') || str_contains($value, '/')
            || str_contains($value, '\\')) {
            return '';
        }
        return $value;
    }

    private function buildPath(string $hcNumber, string $formId): ?string
    {
        $base = $this->mountPath !== null && $this->mountPath !== ''
            ? rtrim($this->mountPath, '/')
            : ($this->basePath !== null ? rtrim($this->basePath, '/') : '');
        if ($base === '') {
            return null;
        }
        return $base . '/' . $hcNumber . '/' . $formId;
    }

    /**
     * @return array<int, array{name: string, size: int, mtime: int, ext: string, type: string}>
     */
    private function listFilesLocal(string $path): array
    {
        if (!is_dir($path)) {
            $this->lastError = 'Carpeta no encontrada en NAS.';
            return [];
        }
        $entries = scandir($path);
        if ($entries === false) {
            $this->lastError = 'No se pudo leer la carpeta.';
            return [];
        }
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $path . '/' . $entry;
            if (!is_file($fullPath)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!$this->isAllowedExtension($ext)) {
                continue;
            }
            $files[] = [
                'name' => $entry,
                'size' => (int)(filesize($fullPath) ?: 0),
                'mtime' => (int)(filemtime($fullPath) ?: 0),
                'ext' => $ext,
                'type' => $this->mapMime($ext),
            ];
        }
        usort($files, static fn($a, $b) => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
        return $files;
    }

    /**
     * @return array<int, array{name: string, size: int, mtime: int, ext: string, type: string}>
     */
    private function listFilesSftp(string $path): array
    {
        $connection = @ssh2_connect($this->host, $this->port);
        if (!$connection) {
            $this->lastError = 'No se pudo conectar al NAS.';
            return [];
        }
        if (!@ssh2_auth_password($connection, $this->username, $this->password)) {
            $this->lastError = 'No se pudo autenticar en NAS.';
            return [];
        }
        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            $this->lastError = 'No se pudo iniciar SFTP.';
            return [];
        }

        $dir = @opendir("ssh2.sftp://{$sftp}{$path}");
        if (!$dir) {
            $this->lastError = 'Carpeta no encontrada en NAS.';
            return [];
        }

        $files = [];
        while (($entry = readdir($dir)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $remotePath = $path . '/' . $entry;
            $stat = @ssh2_sftp_stat($sftp, $remotePath);
            if (!$stat || (($stat['mode'] ?? 0) & 0x4000)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!$this->isAllowedExtension($ext)) {
                continue;
            }
            $files[] = [
                'name' => $entry,
                'size' => (int)($stat['size'] ?? 0),
                'mtime' => (int)($stat['mtime'] ?? 0),
                'ext' => $ext,
                'type' => $this->mapMime($ext),
            ];
        }
        closedir($dir);
        usort($files, static fn($a, $b) => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
        return $files;
    }

    /**
     * @return array<int, array{name: string, size: int, mtime: int, ext: string, type: string}>
     */
    private function listFilesPhpseclib(string $path): array
    {
        $sftp = $this->connectPhpseclib();
        if (!$sftp) {
            return [];
        }

        [$listing, $warning] = $this->withWarningTrap(
            static fn() => $sftp->rawlist($path),
            'Error al listar archivos en NAS'
        );
        if ($warning) {
            return [];
        }
        if ($listing === false) {
            $this->lastError = 'Carpeta no encontrada en NAS.';
            return [];
        }

        $files = [];
        foreach ($listing as $name => $meta) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (!is_array($meta)) {
                continue;
            }
            $type = $meta['type'] ?? null;
            if ($type !== null && (int)$type !== 1) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!$this->isAllowedExtension($ext)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'size' => (int)($meta['size'] ?? 0),
                'mtime' => (int)($meta['mtime'] ?? 0),
                'ext' => $ext,
                'type' => $this->mapMime($ext),
            ];
        }

        usort($files, static fn($a, $b) => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
        return $files;
    }

    /**
     * @return array{stream: resource, size: int, ext: string, type: string, name: string}|null
     */
    private function openFileSftp(string $path, string $filename): ?array
    {
        $connection = @ssh2_connect($this->host, $this->port);
        if (!$connection) {
            $this->lastError = 'No se pudo conectar al NAS.';
            return null;
        }
        if (!@ssh2_auth_password($connection, $this->username, $this->password)) {
            $this->lastError = 'No se pudo autenticar en NAS.';
            return null;
        }
        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            $this->lastError = 'No se pudo iniciar SFTP.';
            return null;
        }

        $remotePath = $path . '/' . $filename;
        $stat = @ssh2_sftp_stat($sftp, $remotePath);
        if (!$stat) {
            $this->lastError = 'Archivo no encontrado.';
            return null;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!$this->isAllowedExtension($ext)) {
            $this->lastError = 'Extensión no permitida.';
            return null;
        }

        $stream = @fopen("ssh2.sftp://{$sftp}{$remotePath}", 'rb');
        if (!$stream) {
            $this->lastError = 'No se pudo abrir el archivo.';
            return null;
        }

        return [
            'stream' => $stream,
            'size' => (int)($stat['size'] ?? 0),
            'ext' => $ext,
            'type' => $this->mapMime($ext),
            'name' => $filename,
        ];
    }

    /**
     * @return array{stream: resource, size: int, ext: string, type: string, name: string}|null
     */
    private function openFilePhpseclib(string $path, string $filename): ?array
    {
        $sftp = $this->connectPhpseclib();
        if (!$sftp) {
            return null;
        }

        $remotePath = $path . '/' . $filename;
        [$stat, $warning] = $this->withWarningTrap(
            static fn() => $sftp->stat($remotePath),
            'Error al consultar archivo en NAS'
        );
        if ($warning) {
            return null;
        }
        if ($stat === false) {
            $this->lastError = 'Archivo no encontrado.';
            return null;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!$this->isAllowedExtension($ext)) {
            $this->lastError = 'Extensión no permitida.';
            return null;
        }

        [$content, $warning] = $this->withWarningTrap(
            static fn() => $sftp->get($remotePath),
            'Error al descargar archivo en NAS'
        );
        if ($warning) {
            return null;
        }
        if ($content === false) {
            $this->lastError = 'No se pudo leer el archivo.';
            return null;
        }

        $stream = fopen('php://temp', 'rb+');
        if (!$stream) {
            $this->lastError = 'No se pudo crear buffer temporal.';
            return null;
        }
        fwrite($stream, $content);
        rewind($stream);

        return [
            'stream' => $stream,
            'size' => (int)($stat['size'] ?? strlen((string)$content)),
            'ext' => $ext,
            'type' => $this->mapMime($ext),
            'name' => $filename,
        ];
    }

    private function connectPhpseclib(): ?\phpseclib3\Net\SFTP
    {
        if (!$this->host || !$this->username || !$this->password) {
            $this->lastError = 'NAS no configurado.';
            return null;
        }

        if (!class_exists('\\phpseclib3\\Net\\SFTP')) {
            $this->lastError = 'phpseclib no disponible.';
            return null;
        }

        [$sftp, $warning] = $this->withWarningTrap(
            fn() => new \phpseclib3\Net\SFTP($this->host, $this->port),
            'No se pudo iniciar sesión SFTP'
        );
        if ($warning || !$sftp) {
            return null;
        }

        [$logged, $warning] = $this->withWarningTrap(
            fn() => $sftp->login($this->username, $this->password),
            'No se pudo autenticar en NAS'
        );
        if ($warning || !$logged) {
            if (!$this->lastError) {
                $this->lastError = 'No se pudo autenticar en NAS.';
            }
            return null;
        }

        return $sftp;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return array{0: T|null, 1: string|null}
     */
    private function withWarningTrap(callable $fn, string $context): array
    {
        $warning = null;
        set_error_handler(function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        });

        try {
            $result = $fn();
        } catch (\Throwable $e) {
            $warning = $e->getMessage();
            $result = null;
        } finally {
            restore_error_handler();
        }

        if ($warning) {
            if (str_contains($warning, 'Expected NET_SFTP_VERSION')) {
                $this->lastError = 'El servidor NAS no tiene SFTP habilitado o el puerto no es SSH.';
            } else {
                $this->lastError = $context . ': ' . $warning;
            }
        }

        return [$result, $warning];
    }

    private function isAllowedExtension(string $ext): bool
    {
        return in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true);
    }

    private function mapMime(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }
}
