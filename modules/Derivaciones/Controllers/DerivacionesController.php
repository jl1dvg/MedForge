<?php

namespace Modules\Derivaciones\Controllers;

use Core\BaseController;
use Helpers\SecurityAuditLogger;
use Modules\Derivaciones\Services\DerivacionesService;
use PDO;

class DerivacionesController extends BaseController
{
    private DerivacionesService $service;
    private string $pythonPath = '/usr/bin/python3';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new DerivacionesService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render(__DIR__ . '/../views/index.php', [
            'pageTitle' => 'Derivaciones',
            'styles' => [
                'assets/vendor_components/datatable/datatables.min.css',
            ],
            'scripts' => [
                'assets/vendor_components/datatable/datatables.min.js',
                'js/pages/derivaciones.js',
            ],
        ]);
    }

    public function ejecutarScraper(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $formId = $_POST['form_id'] ?? null;
        $hcNumber = $_POST['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            $this->json(['success' => false, 'message' => 'Faltan form_id o hc_number'], 400);
            return;
        }

        $script = BASE_PATH . '/scrapping/scrape_derivacion.py';
        $cmd = sprintf(
            '%s %s %s %s --quiet',
            escapeshellcmd($this->pythonPath),
            escapeshellarg($script),
            escapeshellarg((string) $formId),
            escapeshellarg((string) $hcNumber)
        );

        $output = shell_exec($cmd);
        $decoded = null;
        if ($output) {
            $decoded = json_decode(trim($output), true);
        }

        if ($decoded === null) {
            $this->json([
                'success' => false,
                'message' => 'No se pudo procesar la salida del scraper',
                'raw_output' => $output,
            ], 500);
            return;
        }

        $this->json([
            'success' => true,
            'message' => 'Scrapping ejecutado',
            'data' => $decoded,
            'raw_output' => $output,
        ]);
    }

    public function datatable(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'draw' => isset($_POST['draw']) ? (int) $_POST['draw'] : 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesión expirada',
            ], 401);
            return;
        }

        $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;
        $start = isset($_POST['start']) ? (int) $_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int) $_POST['length'] : 25;
        $search = $_POST['search']['value'] ?? '';
        $orderColumnIndex = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;
        $orderDir = $_POST['order'][0]['dir'] ?? 'desc';

        $columnMap = [
            0 => 'fecha_creacion',
            1 => 'cod_derivacion',
            2 => 'form_id',
            3 => 'hc_number',
            4 => 'paciente_nombre',
            5 => 'referido',
            6 => 'fecha_registro',
            7 => 'fecha_vigencia',
            8 => 'archivo',
            9 => 'diagnostico',
            10 => 'sede',
            11 => 'parentesco',
        ];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'fecha_creacion';

        try {
            $resultado = $this->service->obtenerPaginadas(
                $start,
                $length,
                $search,
                $orderColumn,
                $orderDir
            );

            $this->json([
                'draw' => $draw,
                'recordsTotal' => $resultado['total'],
                'recordsFiltered' => $resultado['filtrados'],
                'data' => $resultado['datos'],
            ]);
        } catch (\Throwable $e) {
            error_log('Derivaciones datatable error: ' . $e->getMessage());
            $logPath = BASE_PATH . '/storage/derivaciones_error.log';
            $logMsg = sprintf(
                "[%s] Datatable error: %s\nTrace: %s\n\n",
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $e->getTraceAsString()
            );
            @file_put_contents($logPath, $logMsg, FILE_APPEND);
            $this->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudo cargar derivaciones',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }

    public function descargarArchivo(int $id): void
    {
        $this->requireAuth();

        $derivacion = $this->service->buscarPorId($id);
        if (!$derivacion) {
            http_response_code(404);
            echo 'Derivación no encontrada';
            $this->logArchivoError('Derivación no encontrada', [
                'derivacion_id' => $id,
            ]);
            return;
        }

        $rutaRelativa = $derivacion['archivo_derivacion_path'] ?? null;
        if (!$rutaRelativa) {
            http_response_code(404);
            echo 'La derivación no tiene archivo asociado';
            $this->logArchivoError('Derivación sin archivo asociado', [
                'derivacion_id' => $id,
                'archivo_path' => $rutaRelativa,
            ]);
            return;
        }

        $rutaNormalizada = ltrim($rutaRelativa, '/');
        $rutaAbsoluta = BASE_PATH . '/' . $rutaNormalizada;

        // Evitar accesos fuera del proyecto
        $real = realpath($rutaAbsoluta);
        $baseReal = realpath(BASE_PATH . '/storage/derivaciones');
        if (
            !$real
            || !$baseReal
            || strpos($real, $baseReal) !== 0
            || !file_exists($real)
            || !is_file($real)
        ) {
            http_response_code(404);
            echo 'Archivo de derivación no encontrado en disco';
            $this->logArchivoError('Archivo de derivación no encontrado en disco', [
                'derivacion_id' => $id,
                'archivo_path' => $rutaRelativa,
                'ruta_absoluta' => $rutaAbsoluta,
                'ruta_real' => $real,
            ]);
            return;
        }

        $filename = basename($real);
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($real));
        }

        SecurityAuditLogger::log('derivacion_file_download', [
            'derivacion_id' => $id,
            'filename' => $filename,
        ]);

        readfile($real);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logArchivoError(string $mensaje, array $context = []): void
    {
        $payload = sprintf(
            "[%s] %s | %s\n",
            date('Y-m-d H:i:s'),
            $mensaje,
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $logPath = BASE_PATH . '/storage/derivaciones_archivo.log';
        @file_put_contents($logPath, $payload, FILE_APPEND);
        error_log($payload);
    }
}
