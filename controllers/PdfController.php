<?php

namespace Controllers;

use PDO;
use Models\ProtocoloModel;
use Helpers\ProtocoloHelper;
use PdfGenerator;

class PdfController
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function generarProtocolo(string $form_id, string $hc_number)
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
        $datos['cirujano2_data'] = ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['cirujano_2']);
        $datos['ayudante_data'] = ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['primer_ayudante']);
        $datos['anestesiologo_data'] = ProtocoloHelper::buscarUsuarioPorNombre($this->db, $datos['anestesiologo']);
        if (!empty($datos['procedimiento_id'])) {
            $datos['id_procedimiento'] = $datos['procedimiento_id'];
        } else {
            $datos['id_procedimiento'] = ProtocoloHelper::obtenerIdProcedimiento($this->db, $datos['membrete']);
        }
        $datos['imagen_link'] = ProtocoloHelper::mostrarImagenProcedimiento($this->db, $datos['id_procedimiento']);

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

        ob_start();
        extract($datos);
        include __DIR__ . '/../views/pdf/protocolo.php';
        $html = ob_get_clean();

        PdfGenerator::generarDesdeHtml(
            $html,
            'protocolo_' . $form_id . '_' . $hc_number . '.pdf',
            __DIR__ . '/../public/css/pdf/styles.css' // <-- ruta real de tu CSS
        );
    }
}

?>