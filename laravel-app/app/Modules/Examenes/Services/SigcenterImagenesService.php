<?php

namespace App\Modules\Examenes\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SigcenterImagenesService
{
    private ?string $dbConnection;
    private ?string $dbHost;
    private int $dbPort;
    private ?string $dbDatabase;
    private ?string $dbUsername;
    private ?string $dbPassword;
    private ?string $filesHost;
    private int $filesPort;
    private ?string $filesUser;
    private ?string $filesPass;
    private ?string $filesBasePath;
    private ?string $ocrUrl;
    private ?string $ocrToken;
    private int $ocrTimeout;
    private ?string $lastError = null;
    private ?\phpseclib3\Net\SFTP $sftp = null;
    private ?\phpseclib3\Net\SSH2 $ssh = null;
    private ?bool $directDbAvailable = null;

    public function __construct()
    {
        $this->dbConnection = $this->readEnv('SIGCENTER_DB_CONNECTION_NAME') ?: 'sigcenter';
        $this->dbHost = $this->readEnv('SIGCENTER_DB_HOST') ?: '127.0.0.1';
        $this->dbPort = (int) ($this->readEnv('SIGCENTER_DB_PORT') ?: 3306);
        $this->dbDatabase = $this->readEnv('SIGCENTER_DB_DATABASE') ?: 'inmicrocsa';
        $this->dbUsername = $this->readEnv('SIGCENTER_DB_USERNAME');
        $this->dbPassword = $this->readEnv('SIGCENTER_DB_PASSWORD');
        $this->filesHost = $this->readEnv('SIGCENTER_FILES_SSH_HOST');
        $this->filesPort = (int) ($this->readEnv('SIGCENTER_FILES_SSH_PORT') ?: 22);
        $this->filesUser = $this->readEnv('SIGCENTER_FILES_SSH_USER');
        $this->filesPass = $this->readEnv('SIGCENTER_FILES_SSH_PASS');
        $this->filesBasePath = $this->readEnv('SIGCENTER_FILES_BASE_PATH') ?: '/img';
        $this->ocrUrl = $this->readEnv('SIGCENTER_OCR_REMOTE_URL') ?: 'http://127.0.0.1:8091/ocr/microespecular';
        $this->ocrToken = $this->readEnv('SIGCENTER_OCR_TOKEN');
        $this->ocrTimeout = (int) ($this->readEnv('SIGCENTER_OCR_TIMEOUT') ?: 30);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function isAvailable(): bool
    {
        if ($this->isDirectDbAvailable()) {
            return true;
        }

        if ($this->canQueryViaSsh()) {
            $this->lastError = null;
            return true;
        }

        return false;
    }

    public function canVerifyFiles(): bool
    {
        return $this->filesHost !== null
            && $this->filesUser !== null
            && $this->filesPass !== null
            && class_exists('\\phpseclib3\\Net\\SFTP');
    }

    public function canQueryViaSsh(): bool
    {
        return $this->filesHost !== null
            && $this->filesUser !== null
            && $this->filesPass !== null
            && $this->dbDatabase !== null
            && $this->dbUsername !== null
            && class_exists('\\phpseclib3\\Net\\SSH2');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function requestMicroespecularOcr(array $payload): array
    {
        $this->lastError = null;

        if (!$this->canQueryViaSsh()) {
            $this->lastError = 'Consulta por SSH a Sigcenter no configurada.';
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        if ($this->ocrUrl === null || $this->ocrUrl === '') {
            $this->lastError = 'URL OCR de Sigcenter no configurada.';
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        if ($this->ocrToken === null || $this->ocrToken === '') {
            $this->lastError = 'Token OCR de Sigcenter no configurado.';
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        $ssh = $this->ssh();
        if ($ssh === null) {
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError ?? 'No se pudo abrir SSH hacia Sigcenter.',
            ];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            $this->lastError = 'No se pudo serializar la solicitud OCR.';
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        $command = sprintf(
            "curl -sS -o - -w '\n__HTTP_CODE__:%s' --max-time %d -X POST %s -H %s -H %s --data-binary %s 2>&1",
            '%{http_code}',
            max(5, $this->ocrTimeout),
            escapeshellarg($this->ocrUrl),
            escapeshellarg('Authorization: Bearer ' . $this->ocrToken),
            escapeshellarg('Content-Type: application/json'),
            escapeshellarg($jsonPayload)
        );

        $output = (string) $ssh->exec($command);
        $exitStatus = $ssh->getExitStatus();
        $marker = '__HTTP_CODE__:';
        $markerPos = strrpos($output, $marker);
        if ($markerPos === false) {
            $this->lastError = 'Respuesta OCR inválida desde Sigcenter: ' . trim($output);
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        $body = substr($output, 0, $markerPos);
        $httpCode = (int) trim(substr($output, $markerPos + strlen($marker)));
        if ($exitStatus !== 0 || $httpCode < 200 || $httpCode >= 300) {
            $message = trim($body);
            $this->lastError = 'OCR remoto de Sigcenter falló'
                . ($httpCode > 0 ? ' (HTTP ' . $httpCode . ')' : '')
                . ($message !== '' ? ': ' . $message : '.');

            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        $decoded = json_decode(trim($body), true);
        if (!is_array($decoded)) {
            $this->lastError = 'La respuesta OCR de Sigcenter no fue JSON válido.';
            return [
                'success' => false,
                'payload' => [],
                'warnings' => [],
                'files_used' => [],
                'error' => $this->lastError,
            ];
        }

        $this->lastError = null;
        return $decoded;
    }

    /**
     * @param list<string> $docSolicitudIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchAttachmentsByDocSolicitudIds(array $docSolicitudIds): array
    {
        $this->lastError = null;
        $docSolicitudIds = array_values(array_unique(array_filter(array_map(
            fn ($value): string => trim((string) $value),
            $docSolicitudIds
        ), static fn (string $value): bool => $value !== '')));

        if ($docSolicitudIds === []) {
            return [];
        }

        $rows = $this->fetchAttachmentRows($docSolicitudIds);
        if ($rows === []) {
            return [];
        }

        $attachments = [];
        foreach ($rows as $row) {
            $foto = trim((string) ($row['foto'] ?? ''));
            $tipoDoc = trim((string) ($row['tipo_doc'] ?? ''));
            if ($foto === '' || !in_array($tipoDoc, ['0', '1'], true)) {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', $foto), '/');
            $verification = $this->verifyRemoteRelativePath($relativePath);
            $attachments[] = [
                'doc_solicitud_id' => trim((string) ($row['doc_solicitud_id'] ?? '')),
                'orden_examen_id' => trim((string) ($row['orden_examen_id'] ?? '')),
                'resultado_examen_id' => trim((string) ($row['resultado_examen_id'] ?? '')),
                'tipo_doc' => $tipoDoc,
                'tipo' => $tipoDoc === '1' ? 'pdf' : 'image',
                'foto' => $foto,
                'relative_path' => $relativePath,
                'verified' => $verification['verified'],
                'exists' => $verification['exists'],
                'size' => $verification['size'],
                'mtime' => $verification['mtime'],
                'verification_error' => $verification['error'],
            ];
        }

        return $attachments;
    }

    /**
     * @param list<string> $docSolicitudIds
     * @return array<int, array{doc_solicitud_id:string,orden_examen_id:string,resultado_examen_id:string,tipo_doc:string,foto:string}>
     */
    private function fetchAttachmentRows(array $docSolicitudIds): array
    {
        if ($this->isDirectDbAvailable()) {
            $rows = DB::connection($this->dbConnection)
                ->table('orden_examen as oe')
                ->leftJoin('resultado_examen as re', 're.examen_id', '=', 'oe.id')
                ->selectRaw('
                    CAST(oe.docSolicitudProcedimiento_id AS CHAR) as doc_solicitud_id,
                    CAST(oe.id AS CHAR) as orden_examen_id,
                    CAST(re.id AS CHAR) as resultado_examen_id,
                    CAST(re.tipoDoc AS CHAR) as tipo_doc,
                    TRIM(COALESCE(re.foto, "")) as foto
                ')
                ->whereIn('oe.docSolicitudProcedimiento_id', $docSolicitudIds)
                ->get();

            return $rows->map(static function ($row): array {
                return [
                    'doc_solicitud_id' => trim((string) ($row->doc_solicitud_id ?? '')),
                    'orden_examen_id' => trim((string) ($row->orden_examen_id ?? '')),
                    'resultado_examen_id' => trim((string) ($row->resultado_examen_id ?? '')),
                    'tipo_doc' => trim((string) ($row->tipo_doc ?? '')),
                    'foto' => trim((string) ($row->foto ?? '')),
                ];
            })->all();
        }

        return $this->fetchAttachmentRowsViaSsh($docSolicitudIds);
    }

    private function isDirectDbAvailable(): bool
    {
        if ($this->directDbAvailable !== null) {
            return $this->directDbAvailable;
        }

        try {
            DB::connection($this->dbConnection)->getPdo();
            $this->directDbAvailable = true;
            return true;
        } catch (\Throwable $e) {
            $this->directDbAvailable = false;
            $this->lastError = 'Conexión Sigcenter no disponible: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * @param list<string> $docSolicitudIds
     * @return array<int, array{doc_solicitud_id:string,orden_examen_id:string,resultado_examen_id:string,tipo_doc:string,foto:string}>
     */
    private function fetchAttachmentRowsViaSsh(array $docSolicitudIds): array
    {
        $ssh = $this->ssh();
        if ($ssh === null) {
            return [];
        }

        $quotedIds = implode(', ', array_map(static fn (string $id): string => "'" . str_replace("'", "''", $id) . "'", $docSolicitudIds));
        $sql = <<<SQL
SELECT
    CAST(oe.docSolicitudProcedimiento_id AS CHAR) AS doc_solicitud_id,
    CAST(oe.id AS CHAR) AS orden_examen_id,
    CAST(COALESCE(re.id, '') AS CHAR) AS resultado_examen_id,
    CAST(COALESCE(re.tipoDoc, '') AS CHAR) AS tipo_doc,
    REPLACE(REPLACE(TRIM(COALESCE(re.foto, '')), CHAR(9), ' '), CHAR(10), ' ') AS foto
FROM orden_examen oe
LEFT JOIN resultado_examen re ON re.examen_id = oe.id
WHERE oe.docSolicitudProcedimiento_id IN ({$quotedIds});
SQL;

        $mysqlCommand = sprintf(
            'mysql --batch --raw --skip-column-names -h %s -P %d -u %s -p%s %s -e %s',
            escapeshellarg((string) $this->dbHost),
            $this->dbPort,
            escapeshellarg((string) $this->dbUsername),
            escapeshellarg((string) $this->dbPassword),
            escapeshellarg((string) $this->dbDatabase),
            escapeshellarg($sql)
        );

        $output = $ssh->exec($mysqlCommand);
        $exitStatus = $ssh->getExitStatus();
        if ($exitStatus !== 0 && trim($output) === '') {
            $this->lastError = 'Consulta remota Sigcenter por SSH falló.';
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r?\n/', trim($output)) as $line) {
            if ($line === null || trim($line) === '') {
                continue;
            }

            $parts = explode("\t", $line);
            $rows[] = [
                'doc_solicitud_id' => trim((string) ($parts[0] ?? '')),
                'orden_examen_id' => trim((string) ($parts[1] ?? '')),
                'resultado_examen_id' => trim((string) ($parts[2] ?? '')),
                'tipo_doc' => trim((string) ($parts[3] ?? '')),
                'foto' => trim((string) ($parts[4] ?? '')),
            ];
        }

        $this->lastError = null;
        return $rows;
    }

    /**
     * @return array{verified:bool,exists:bool,size:int,mtime:?string,error:?string}
     */
    private function verifyRemoteRelativePath(string $relativePath): array
    {
        if ($relativePath === '') {
            return [
                'verified' => false,
                'exists' => false,
                'size' => 0,
                'mtime' => null,
                'error' => 'Ruta vacía.',
            ];
        }

        if (!$this->canVerifyFiles()) {
            return [
                'verified' => false,
                'exists' => false,
                'size' => 0,
                'mtime' => null,
                'error' => null,
            ];
        }

        $remotePath = rtrim((string) $this->filesBasePath, '/');
        $remotePath .= '/' . ltrim($relativePath, '/');

        try {
            $sftp = $this->sftp();
            if ($sftp === null) {
                return [
                    'verified' => false,
                    'exists' => false,
                    'size' => 0,
                    'mtime' => null,
                    'error' => $this->lastError,
                ];
            }

            $stat = $sftp->stat($remotePath);
            if (!is_array($stat)) {
                return [
                    'verified' => true,
                    'exists' => false,
                    'size' => 0,
                    'mtime' => null,
                    'error' => null,
                ];
            }

            $mtime = isset($stat['mtime']) ? Carbon::createFromTimestamp((int) $stat['mtime'])->toDateTimeString() : null;

            return [
                'verified' => true,
                'exists' => true,
                'size' => (int) ($stat['size'] ?? 0),
                'mtime' => $mtime,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'No se pudo verificar archivo remoto Sigcenter: ' . $e->getMessage();

            return [
                'verified' => false,
                'exists' => false,
                'size' => 0,
                'mtime' => null,
                'error' => $this->lastError,
            ];
        }
    }

    /**
     * @return array{stream:resource,size:int,ext:string,type:string,name:string}|null
     */
    public function openRelativeFile(string $relativePath): ?array
    {
        $this->lastError = null;
        $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        if ($relativePath === '') {
            $this->lastError = 'Ruta relativa vacía.';
            return null;
        }

        if (!$this->canVerifyFiles()) {
            $this->lastError = 'Verificación remota Sigcenter no configurada.';
            return null;
        }

        $remotePath = rtrim((string) $this->filesBasePath, '/');
        $remotePath .= '/' . $relativePath;

        $sftp = $this->sftp();
        if ($sftp === null) {
            return null;
        }

        $contents = $sftp->get($remotePath);
        if (!is_string($contents) || $contents === '') {
            $this->lastError = 'Archivo Sigcenter no encontrado.';
            return null;
        }

        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            $this->lastError = 'No se pudo preparar el stream temporal.';
            return null;
        }

        fwrite($handle, $contents);
        rewind($handle);

        $filename = basename($relativePath);
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return [
            'stream' => $handle,
            'size' => strlen($contents),
            'ext' => $ext,
            'type' => $this->mapMime($ext),
            'name' => $filename,
        ];
    }

    public function downloadRelativeFileToPath(string $relativePath, string $localPath): bool
    {
        $this->lastError = null;
        $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        $localPath = trim($localPath);

        if ($relativePath === '' || $localPath === '') {
            $this->lastError = 'Parámetros inválidos.';
            return false;
        }

        if (!$this->canVerifyFiles()) {
            $this->lastError = 'Verificación remota Sigcenter no configurada.';
            return false;
        }

        $remotePath = rtrim((string) $this->filesBasePath, '/');
        $remotePath .= '/' . $relativePath;

        $dir = dirname($localPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->lastError = 'No se pudo preparar la carpeta de caché local.';
            return false;
        }

        $sftp = $this->sftp();
        if ($sftp === null) {
            return false;
        }

        if (!$sftp->get($remotePath, $localPath)) {
            $this->lastError = 'No se pudo descargar archivo Sigcenter.';
            return false;
        }

        return is_file($localPath) && (int) (filesize($localPath) ?: 0) > 0;
    }

    private function sftp(): ?\phpseclib3\Net\SFTP
    {
        if ($this->sftp instanceof \phpseclib3\Net\SFTP) {
            return $this->sftp;
        }

        if (!$this->canVerifyFiles()) {
            $this->lastError = 'Verificación remota Sigcenter no configurada.';
            return null;
        }

        $sftp = new \phpseclib3\Net\SFTP((string) $this->filesHost, $this->filesPort, 20);
        if (!$sftp->login((string) $this->filesUser, (string) $this->filesPass)) {
            $this->lastError = 'No se pudo autenticar por SFTP contra Sigcenter.';
            return null;
        }

        $this->sftp = $sftp;
        return $this->sftp;
    }

    private function mapMime(string $ext): string
    {
        return match (strtolower($ext)) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    private function ssh(): ?\phpseclib3\Net\SSH2
    {
        if ($this->ssh instanceof \phpseclib3\Net\SSH2) {
            return $this->ssh;
        }

        if (!$this->canQueryViaSsh()) {
            $this->lastError = 'Consulta por SSH a Sigcenter no configurada.';
            return null;
        }

        $ssh = new \phpseclib3\Net\SSH2((string) $this->filesHost, $this->filesPort, 20);
        if (!$ssh->login((string) $this->filesUser, (string) $this->filesPass)) {
            $this->lastError = 'No se pudo autenticar por SSH contra Sigcenter.';
            return null;
        }

        $this->ssh = $ssh;
        return $this->ssh;
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
}
