<?php

namespace Controllers;

use PDO;
use Models\ProtocoloModel;
use Models\SolicitudModel;
use Helpers\ProtocoloHelper;
use Helpers\SolicitudHelper;
use PdfGenerator;
use Controllers\SolicitudController;
use Modules\Reporting\Controllers\ReportController as ReportingReportController;
use Modules\Reporting\Services\ReportService;

class PdfController
{
    private PDO $db;
    private ProtocoloModel $protocoloModel;
    private SolicitudModel $solicitudModel;
    private SolicitudController $solicitudController; // ✅ nueva propiedad
    private ReportingReportController $reportController;


    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->solicitudModel = new SolicitudModel($pdo);
        $this->solicitudController = new SolicitudController($this->db);
        $reportService = new ReportService();
        $this->reportController = new ReportingReportController($this->db, $reportService);
    }

    public function generarProtocolo(string $form_id, string $hc_number, bool $soloDatos = false, string $modo = 'completo')
    {
        $model = new ProtocoloModel($this->db);

        $datos = $model->obtenerProtocolo($form_id, $hc_number);

        if (!$datos) {
            throw new \Exception('No se encontró el protocolo.');
        }

        // Procesamientos adicionales
        if (!empty($datos['fecha_nacimiento']) && !empty($datos['fecha_inicio'])) {
            $datos['edad'] = (new \DateTime($datos['fecha_nacimiento']))->diff(new \DateTime($datos['fecha_inicio']))->y;
        } else {
            $datos['edad'] = null;
        }

        $fechaInicioParts = explode(' ', $datos['fecha_inicio']);
        $fechaPart = $fechaInicioParts[0] ?? '';
        [$datos['anio'], $datos['mes'], $datos['dia']] = explode('-', $fechaPart);

        $datos['nombre_procedimiento_proyectado'] = $model->obtenerNombreProcedimientoProyectado($datos['procedimiento_proyectado'] ?? '');
        $datos['codigos_concatenados'] = $model->obtenerCodigosProcedimientos($datos['procedimientos']);

        $datos['diagnosticos_previos'] = ProtocoloHelper::obtenerDiagnosticosPrevios($this->db, $hc_number, $form_id);
        // Adaptar al formato de la vista: "CCCC - DESCRIPCION" para que substr($x, 0, 4) y substr($x, 6) funcionen
        $previosObjs = is_array($datos['diagnosticos_previos']) ? $datos['diagnosticos_previos'] : [];
        $previosFmt = [];
        foreach ($previosObjs as $d) {
            $cie = isset($d['cie10']) ? strtoupper(trim((string)$d['cie10'])) : '';
            $desc = isset($d['descripcion']) ? strtoupper(trim((string)$d['descripcion'])) : '';
            // Asegurar que el código ocupe exactamente 4 caracteres para que substr(..., 0, 4) funcione
            $ciePad = str_pad($cie, 4, ' ', STR_PAD_RIGHT);
            $previosFmt[] = $ciePad . ' - ' . $desc;
        }
        // Garantizar 3 entradas (la vista espera 3)
        while (count($previosFmt) < 3) {
            $previosFmt[] = '';
        }
        // Reemplazar para que la vista siga usando $diagnosticos_previos[0..2]
        $datos['diagnosticos_previos'] = [
            $previosFmt[0] ?? '',
            $previosFmt[1] ?? '',
            $previosFmt[2] ?? '',
        ];
        $datos['cirujano_data'] = ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['cirujano_1']);
        $datos['cirujano2_data'] = isset($datos['cirujano_2']) && $datos['cirujano_2'] !== ''
            ? ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['cirujano_2'])
            : null;
        $datos['ayudante_data'] = isset($datos['primer_ayudante']) && $datos['primer_ayudante'] !== ''
            ? ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['primer_ayudante'])
            : null;
        $datos['anestesiologo_data'] = isset($datos['anestesiologo']) && $datos['anestesiologo'] !== ''
            ? ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['anestesiologo'])
            : null;
        $datos['ayudante_anestesia_data'] = isset($datos['ayudante_anestesia']) && $datos['ayudante_anestesia'] !== ''
            ? ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['ayudante_anestesia'])
            : null;
        if (!empty($datos['procedimiento_id'])) {
            $datos['id_procedimiento'] = $datos['procedimiento_id'];
        } else {
            $datos['id_procedimiento'] = ProtocoloHelper::obtenerIdProcedimiento($this->db, $datos['membrete']);
        }
        $datos['imagen_link'] = ProtocoloHelper::mostrarImagenProcedimiento($this->db, $datos['procedimiento_id']);

        // Add edadPaciente and diagnostics before extracting $datos for view
        $datos['edadPaciente'] = $datos['edad'] ?? null;

        $diagnosesArray = json_decode($datos['diagnosticos'], true);

        $datos['diagnostic1'] = $diagnosesArray[0]['idDiagnostico'] ?? '';
        $datos['diagnostic2'] = $diagnosesArray[1]['idDiagnostico'] ?? '';
        $datos['diagnostic3'] = $diagnosesArray[2]['idDiagnostico'] ?? '';

        $datos['realizedProcedure'] = $datos['membrete'] ?? '';
        $datos['codes_concatenados'] = $datos['codigos_concatenados'] ?? '';
        $datos['mainSurgeon'] = $datos['cirujano_1'] ?? '';
        $datos['assistantSurgeon1'] = $datos['cirujano_2'] ?? '';
        $datos['ayudante'] = $datos['primer_ayudante'] ?? '';

        $datos['fechaDia'] = $datos['dia'] ?? '';
        $datos['fechaMes'] = $datos['mes'] ?? '';
        $datos['fechaAno'] = $datos['anio'] ?? '';

        $datos['evolucion005'] = $this->protocoloModel->obtenerEvolucion005($datos['id_procedimiento'] ?? '');

        // Separar fecha inicio en partes para 005
        $fechaInicioParts = explode(' ', $datos['fecha_inicio']);
        if (!empty($fechaInicioParts[0])) {
            list($datos['fechaAno'], $datos['fechaMes'], $datos['fechaDia']) = explode('-', $fechaInicioParts[0]);
        }

// Ajustar horas
        if (!empty($datos['hora_inicio'])) {
            $horaInicioObj = new \DateTime($datos['hora_inicio']);
            $horaInicioObj->modify('-45 minutes');
            $datos['horaInicioModificada'] = $horaInicioObj->format('H:i');
        }

        if (!empty($datos['hora_fin'])) {
            $horaFinObj = new \DateTime($datos['hora_fin']);
            $horaFinObj->modify('+30 minutes');
            $datos['horaFinModificada'] = $horaFinObj->format('H:i');
        }

        // Obtener medicamentos
        $medicamentosArray = $this->protocoloModel->obtenerMedicamentos($datos['procedimiento_id'], $form_id, $hc_number);
        $datos['medicamentos'] = ProtocoloHelper::procesarMedicamentos(
            $medicamentosArray,
            $datos['horaInicioModificada'],
            $datos['mainSurgeon'],
            $datos['anestesiologo'],
            $datos['ayudante_anestesia']
        );

        $datos['insumos'] = !empty($datos['insumos']) && is_string($datos['insumos'])
            ? ProtocoloHelper::procesarInsumos($datos['insumos'])
            : [];

        // ✅ Calcular duración de anestesia y cirugía
        $totalHoras = '';
        $totalHorasConDescuento = '';

        try {
            if (!empty($datos['hora_inicio']) && !empty($datos['hora_fin'])) {
                $inicio = new \DateTime($datos['hora_inicio']);
                $fin = new \DateTime($datos['hora_fin']);
                $interval = $inicio->diff($fin);

                // Formato ejemplo: "1h 30min"
                $totalHoras = $interval->h . 'h ' . $interval->i . 'min';

                // Para totalHorasConDescuento simplemente restamos, por ejemplo 30 minutos
                $fin->modify('-10 minutes'); // Ajuste de 30 minutos
                $intervalConDescuento = $inicio->diff($fin);
                $totalHorasConDescuento = $intervalConDescuento->h . 'h ' . $intervalConDescuento->i . 'min';
            }
        } catch (\Exception $e) {
            $totalHoras = 'No disponible';
            $totalHorasConDescuento = 'No disponible';
        }

// Inyectarlos también a $datos para que estén disponibles en transanestesico.php
        $datos['totalHoras'] = $totalHoras;
        $datos['totalHorasConDescuento'] = $totalHorasConDescuento;

        $paginas = [
            'protocolo.php',
            '005.php',
            'medicamentos.php',
            'signos_vitales.php',
            'insumos.php',
            'saveqx.php',
        ];

        if ($modo === 'separado') {
            $paginaSolicitada = $_GET['pagina'] ?? null;

            if ($paginaSolicitada) {
                $slugPagina = pathinfo($paginaSolicitada, PATHINFO_FILENAME);
                $orientation = ($slugPagina === 'transanestesico') ? 'L' : 'P';
                $html = $this->renderReportSegment($paginaSolicitada, $datos);

                if ($html === null) {
                    http_response_code(404);
                    echo 'Plantilla no encontrada';
                    return;
                }

                $nombrePdf = "{$slugPagina}_{$form_id}_{$hc_number}.pdf";
                PdfGenerator::generarDesdeHtml(
                    $html,
                    $nombrePdf,
                    dirname(__DIR__) . '/modules/Reporting/Templates/assets/pdf.css',
                    'D',
                    $orientation
                );
                return;
            }
        } else {
            // 🔁 COMPORTAMIENTO ACTUAL
            $htmlTotal = '';

            foreach ($paginas as $index => $pagina) {
                $segment = $this->renderReportSegment($pagina, $datos);

                if ($segment === null) {
                    continue;
                }

                $htmlTotal .= $segment;

                if ($index < count($paginas) - 1) {
                    $htmlTotal .= '<pagebreak>';
                }
            }

            $transanestesico = $this->renderReportSegment('transanestesico', $datos);
            if ($transanestesico !== null) {
                $htmlTotal .= '<pagebreak orientation="L">' . $transanestesico;
            }

            PdfGenerator::generarDesdeHtml(
                $htmlTotal,
                'protocolo_' . $form_id . '_' . $hc_number . '.pdf',
                dirname(__DIR__) . '/modules/Reporting/Templates/assets/pdf.css'
            );
        }
    }

    public function generateCobertura(string $form_id, string $hc_number)
    {
        $datos = $this->solicitudController->obtenerDatosParaVista($hc_number, $form_id); // ✅ uso correcto

        // Calcular edadPaciente según fecha_nacimiento y created_at
        if (!empty($datos['paciente']['fecha_nacimiento']) && !empty($datos['solicitud']['created_at'])) {
            $datos['edadPaciente'] = (new \DateTime($datos['paciente']['fecha_nacimiento']))->diff(new \DateTime($datos['solicitud']['created_at']))->y;
        } else {
            $datos['edadPaciente'] = null;
        }

        //echo '<pre>';
        //print_r($datos);
        //echo '</pre>';
        //Paginas
        $paginas = [
            '007.php',
            '010.php',
        ];
        $htmlTotal = '';

        foreach ($paginas as $index => $pagina) {
            $segment = $this->renderReportSegment($pagina, $datos);

            if ($segment === null) {
                continue;
            }

            $htmlTotal .= $segment;

            if ($index < count($paginas) - 1) {
                $htmlTotal .= '<pagebreak>';
            }
        }

        $referencia = $this->renderReportSegment('referencia', $datos);
        if ($referencia !== null) {
            $htmlTotal .= '<pagebreak orientation="P">' . $referencia;
        }

        PdfGenerator::generarDesdeHtml(
            $htmlTotal,
            'cobertura_' . $form_id . '_' . $hc_number . '.pdf',
            dirname(__DIR__) . '/modules/Reporting/Templates/assets/pdf.css');
    }

    private function renderReportSegment(string $identifier, array $data): ?string
    {
        $slug = pathinfo($identifier, PATHINFO_FILENAME);

        return $this->reportController->renderIfExists($slug, $data);
    }

}

?>

