<?php

namespace Controllers;

require_once dirname(__DIR__) . '/modules/Reporting/Support/LegacyLoader.php';

use PDO;
use Models\ProtocoloModel;
use Helpers\PdfGenerator;
use Controllers\SolicitudController;
use Modules\Reporting\Controllers\ReportController as ReportingReportController;
use Modules\Reporting\Services\ReportService;
use Modules\Reporting\Services\ProtocolReportService;

reporting_bootstrap_legacy();

class PdfController
{
    private PDO $db;
    private ProtocoloModel $protocoloModel;
    private SolicitudController $solicitudController; // ✅ nueva propiedad
    private ReportingReportController $reportController;
    private ProtocolReportService $protocolReportService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->solicitudController = new SolicitudController($this->db);
        $reportService = new ReportService();
        $this->reportController = new ReportingReportController($this->db, $reportService);
        $this->protocolReportService = new ProtocolReportService(
            $this->db,
            $this->reportController,
            $this->protocoloModel,
            $this->solicitudController
        );
    }

    public function generarProtocolo(string $form_id, string $hc_number, bool $soloDatos = false, string $modo = 'completo')
    {
        if ($soloDatos) {
            return $this->protocolReportService->buildProtocolData($form_id, $hc_number);
        }

        if ($modo === 'separado') {
            $paginaSolicitada = $_GET['pagina'] ?? null;

            if ($paginaSolicitada) {
                $documento = $this->protocolReportService->renderProtocolPage($paginaSolicitada, $form_id, $hc_number);

                if ($documento === null) {
                    http_response_code(404);
                    echo 'Plantilla no encontrada';
                    return;
                }

                PdfGenerator::generarDesdeHtml(
                    $documento['html'],
                    $documento['filename'],
                    $documento['css'],
                    'D',
                    $documento['orientation']
                );
                return;
            }
        }

        $documento = $this->protocolReportService->generateProtocolDocument($form_id, $hc_number);

        PdfGenerator::generarDesdeHtml(
            $documento['html'],
            $documento['filename'],
            $documento['css']
        );
    }

    public function generateCobertura(string $form_id, string $hc_number)
    {
        $documento = $this->protocolReportService->generateCoberturaDocument($form_id, $hc_number);

        PdfGenerator::generarDesdeHtml(
            $documento['html'],
            $documento['filename'],
            $documento['css']
        );
    }

}

