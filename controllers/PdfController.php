<?php

namespace Controllers;

use PDO;
use Models\ProtocoloModel;
use Helpers\ProtocoloHelper;
use PdfGenerator;

class PdfController
{
    private PDO $db;
    private ProtocoloModel $protocoloModel; // ✅ propiedad faltante

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo); // ✅ inicializarla aquí
    }

    public function generarProtocolo(string $form_id, string $hc_number, bool $soloDatos = false)
    {
        $model = new ProtocoloModel($this->db);

        $datos = $model->obtenerProtocolo($form_id, $hc_number);

        if (!$datos) {
            die('No se encontró el protocolo.');
        }

        // Procesamientos adicionales
        $datos['edad'] = (new \DateTime($datos['fecha_nacimiento']))->diff(new \DateTime($datos['fecha_inicio']))->y;

        $fechaInicioParts = explode(' ', $datos['fecha_inicio']);
        $fechaPart = $fechaInicioParts[0] ?? '';
        [$datos['anio'], $datos['mes'], $datos['dia']] = explode('-', $fechaPart);

        $datos['nombre_procedimiento_proyectado'] = $model->obtenerNombreProcedimientoProyectado($datos['procedimiento_proyectado'] ?? '');
        $datos['codigos_concatenados'] = $model->obtenerCodigosProcedimientos($datos['procedimientos']);

        $datos['diagnosticos_previos'] = ProtocoloHelper::obtenerDiagnosticosAnteriores($this->db, $hc_number, $form_id, ProtocoloHelper::obtenerIdProcedimiento($this->db, $datos['membrete']));
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
        $medicamentosArray = $this->protocoloModel->obtenerMedicamentos($datos['procedimiento_id']);
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
                $fin->modify('-30 minutes'); // Ajuste de 30 minutos
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

        $htmlTotal = '';

        foreach ($paginas as $index => $pagina) {
            ob_start();
            extract($datos);
            include __DIR__ . '/../views/pdf/' . $pagina;
            $htmlSeccion = ob_get_clean();

            $htmlTotal .= $htmlSeccion;

            // 👇 Agregar un salto de página solo si no es la última
            if ($index < count($paginas) - 1) {
                $htmlTotal .= '<pagebreak>';
            }
        }

        $htmlTotal .= '<pagebreak orientation="L">'; // 👈 cambio a Landscape

        ob_start();
        extract($datos);
        include __DIR__ . '/../views/pdf/transanestesico.php';
        $htmlTransanestesico = ob_get_clean();
        $htmlTotal .= $htmlTransanestesico;

// (si quieres volver a Portrait después)
// $htmlTotal .= '<pagebreak orientation="P">';
        if ($soloDatos) {
            return $datos;
        }
// Ahora generas el PDF
        PdfGenerator::generarDesdeHtml(
            $htmlTotal,
            'protocolo_' . $form_id . '_' . $hc_number . '.pdf',
            __DIR__ . '/../public/css/pdf/styles.css'
        );
    }
}

?>