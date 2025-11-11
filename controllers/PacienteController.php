<?php

namespace Controllers;

use PDO;
use PDOException;

class PacienteController
{
    private $db;
    /** @var array<string, bool> */
    private array $tablaDisponibleCache = [];

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    // Obtiene los pacientes con su última fecha de consulta
    public function obtenerPacientesConUltimaConsulta()
    {
        $sql = "
            SELECT 
                p.hc_number,
            CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS full_name, 
            MAX(cd.fecha) AS ultima_fecha,  -- Obtener la fecha más reciente
            cd.diagnosticos, 
            (SELECT pp.doctor 
             FROM consulta_data cd2 
             INNER JOIN procedimiento_proyectado pp 
             ON cd2.form_id = pp.form_id 
             WHERE cd2.hc_number = p.hc_number 
             ORDER BY cd2.fecha DESC 
             LIMIT 1) AS doctor,  -- Subconsulta para obtener el último doctor
            p.fecha_caducidad, 
            p.afiliacion 
        FROM patient_data p
        INNER JOIN consulta_data cd ON p.hc_number = cd.hc_number
        GROUP BY p.hc_number
            ORDER BY 
                ultima_fecha DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiagnosticosPorPaciente($hc_number)
    {
        $uniqueDiagnoses = [];

        if ($this->tablaDisponible('prefactura_detalle_diagnosticos')) {
            $stmtPref = $this->db->prepare(
                "SELECT d.diagnostico_codigo, d.descripcion, pp.fecha_creacion, pp.fecha_registro\n"
                . "FROM prefactura_detalle_diagnosticos d\n"
                . "INNER JOIN prefactura_paciente pp ON pp.id = d.prefactura_id\n"
                . "WHERE pp.hc_number = ?\n"
                . "ORDER BY pp.fecha_creacion DESC, d.posicion ASC"
            );
            $stmtPref->execute([$hc_number]);

            while ($row = $stmtPref->fetch(PDO::FETCH_ASSOC)) {
                $codigo = $row['diagnostico_codigo'] ?: ($row['descripcion'] ?? null);
                if (!$codigo || isset($uniqueDiagnoses[$codigo])) {
                    continue;
                }

                $fechaEvento = $row['fecha_creacion'] ?? $row['fecha_registro'] ?? null;
                $timestamp = $fechaEvento ? strtotime((string) $fechaEvento) : false;

                $uniqueDiagnoses[$codigo] = [
                    'idDiagnostico' => $row['diagnostico_codigo'] ?: $codigo,
                    'fecha' => $timestamp ? date('d M Y', $timestamp) : null,
                ];
            }
        }

        $stmt = $this->db->prepare("SELECT fecha, diagnosticos FROM consulta_data WHERE hc_number = ? ORDER BY fecha DESC");
        $stmt->execute([$hc_number]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $diagnosticos = json_decode($row['diagnosticos'], true) ?: [];
            $timestamp = strtotime((string) $row['fecha']);
            $fecha = $timestamp ? date('d M Y', $timestamp) : null;

            foreach ($diagnosticos as $diagnostico) {
                $id = $diagnostico['idDiagnostico'] ?? null;
                if ($id && !isset($uniqueDiagnoses[$id])) {
                    $uniqueDiagnoses[$id] = [
                        'idDiagnostico' => $id,
                        'fecha' => $fecha
                    ];
                }
            }
        }

        return $uniqueDiagnoses;
    }

    public function getDoctoresAsignados($hc_number)
    {
        $stmt = $this->db->prepare("SELECT doctor, form_id FROM procedimiento_proyectado WHERE hc_number = ? AND doctor IS NOT NULL AND doctor != '' AND doctor NOT LIKE '%optometría%' ORDER BY form_id DESC");
        $stmt->execute([$hc_number]);

        $doctores = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $doctor = $row['doctor'];
            if (!isset($doctores[$doctor])) {
                $doctores[$doctor] = [
                    'doctor' => $doctor,
                    'form_id' => $row['form_id']
                ];
            }
        }
        return $doctores;
    }

    public function getSolicitudesPorPaciente($hc_number)
    {
        $stmt = $this->db->prepare("SELECT procedimiento, created_at, tipo, form_id FROM solicitud_procedimiento WHERE hc_number = ? AND procedimiento != '' AND procedimiento != 'SELECCIONE' ORDER BY created_at DESC");
        $stmt->execute([$hc_number]);

        $solicitudes = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $solicitudes[] = [
                'nombre' => $row['procedimiento'],
                'fecha' => $row['created_at'],
                'tipo' => strtolower($row['tipo'] ?? 'otro'),
                'form_id' => $row['form_id']
            ];
        }

        return $solicitudes;
    }

    public function getDetalleSolicitud($hc_number, $form_id)
    {
        $stmt = $this->db->prepare("
        SELECT 
            sp.*, 
            cd.* 
        FROM solicitud_procedimiento sp
        LEFT JOIN consulta_data cd 
            ON sp.hc_number = cd.hc_number 
            AND sp.form_id = cd.form_id
        WHERE sp.hc_number = ? AND sp.form_id = ?
        LIMIT 1
    ");
        $stmt->execute([$hc_number, $form_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDocumentosDescargables($hc_number)
    {
        $stmt1 = $this->db->prepare("SELECT form_id, hc_number, membrete, fecha_inicio FROM protocolo_data WHERE hc_number = ? AND status = 1");
        $stmt1->execute([$hc_number]);
        $protocolos = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $this->db->prepare("SELECT form_id, hc_number, procedimiento, created_at 
                                 FROM solicitud_procedimiento 
                                 WHERE hc_number = ? 
                                   AND procedimiento IS NOT NULL 
                                   AND procedimiento != '' 
                                   AND procedimiento != 'SELECCIONE'");
        $stmt2->execute([$hc_number]);
        $solicitudes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $documentos = array_merge($protocolos, $solicitudes);

        usort($documentos, function ($a, $b) {
            $fechaA = $a['fecha_inicio'] ?? $a['created_at'];
            $fechaB = $b['fecha_inicio'] ?? $b['created_at'];
            return strtotime($fechaB) - strtotime($fechaA);
        });

        return $documentos;
    }

    public function getPatientDetails($hc_number)
    {
        $stmt = $this->db->prepare("SELECT * FROM patient_data WHERE hc_number = ?");
        $stmt->execute([$hc_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getEventosTimeline($hc_number)
    {
        $stmt = $this->db->prepare("
        SELECT pp.procedimiento_proyectado, pp.form_id, pp.hc_number, 
               COALESCE(cd.fecha, pr.fecha_inicio) AS fecha, 
               COALESCE(cd.examen_fisico, pr.membrete) AS contenido
        FROM procedimiento_proyectado pp
        LEFT JOIN consulta_data cd ON pp.hc_number = cd.hc_number AND pp.form_id = cd.form_id
        LEFT JOIN protocolo_data pr ON pp.hc_number = pr.hc_number AND pp.form_id = pr.form_id
        WHERE pp.hc_number = ? AND pp.procedimiento_proyectado NOT LIKE '%optometría%'
        ORDER BY fecha ASC
    ");
        $stmt->execute([$hc_number]);
        $eventos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['fecha']) && strtotime($row['fecha'])) {
                $eventos[] = $row;
            }
        }
        return $eventos;
    }


    public function getEstadisticasProcedimientos($hc_number)
    {
        $stmt = $this->db->prepare("SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE hc_number = ?");
        $stmt->execute([$hc_number]);
        $procedimientos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = explode(' - ', $row['procedimiento_proyectado']);
            $categoria = strtoupper($parts[0]);
            if (in_array($categoria, ['CIRUGIAS', 'PNI', 'IMAGENES'])) {
                $nombre = $categoria;
            } else {
                $nombre = $parts[2] ?? $categoria;
            }
            if (!isset($procedimientos[$nombre])) {
                $procedimientos[$nombre] = 0;
            }
            $procedimientos[$nombre]++;
        }

        $total = array_sum($procedimientos);
        $porcentajes = [];
        foreach ($procedimientos as $nombre => $cantidad) {
            $porcentajes[$nombre] = ($cantidad / $total) * 100;
        }

        return $porcentajes;
    }

    public function calcularEdad($fechaNacimiento, $fechaActual = null)
    {
        try {
            $fechaNacimiento = new \DateTime($fechaNacimiento);
            $fechaActual = $fechaActual ? new \DateTime($fechaActual) : new \DateTime();
            $edad = $fechaActual->diff($fechaNacimiento);
            return $edad->y;
        } catch (\Exception $e) {
            return null; // Retorna null si ocurre un error con la fecha
        }
    }

    public function verificarCoberturaPaciente($hc_number)
    {
        $stmt = $this->db->prepare("
            SELECT cod_derivacion, fecha_vigencia
            FROM prefactura_paciente
            WHERE hc_number = ?
              AND cod_derivacion IS NOT NULL AND cod_derivacion != ''
            ORDER BY fecha_vigencia DESC
            LIMIT 1
        ");
        $stmt->execute([$hc_number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return 'N/A';
        }

        $fechaVigencia = strtotime($row['fecha_vigencia']);
        $fechaActual = time();

        return $fechaVigencia >= $fechaActual ? 'Con Cobertura' : 'Sin Cobertura';
    }

    public function getPrefacturasPorPaciente($hc_number)
    {
        $stmt = $this->db->prepare("SELECT * FROM prefactura_paciente WHERE hc_number = ? AND cod_derivacion IS NOT NULL AND cod_derivacion != '' ORDER BY fecha_creacion DESC");
        $stmt->execute([$hc_number]);

        $prefacturas = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $detalles = $this->obtenerProcedimientosNormalizados((int) ($row['id'] ?? 0));
            $procedimientos = [];

            if ($detalles !== null) {
                $procedimientos = $detalles;
                $row['procedimientos_detalle'] = $detalles;
            } elseif (!empty($row['procedimientos']) && is_string($row['procedimientos'])) {
                $procedimientos = json_decode($row['procedimientos'], true) ?: [];
            }

            $nombreProcedimientos = 'Procedimientos no disponibles';
            if (is_array($procedimientos) && $procedimientos !== []) {
                $lineas = [];
                foreach ($procedimientos as $index => $proc) {
                    $descripcion = $proc['descripcion']
                        ?? $proc['procedimiento']
                        ?? $proc['procInterno']
                        ?? $proc['procDetalle']
                        ?? $proc['codigo']
                        ?? 'Procedimiento';
                    $linea = ($index + 1) . '. ' . $descripcion;

                    $lateralidad = $proc['lateralidad'] ?? $proc['ojoId'] ?? null;
                    if (!empty($lateralidad)) {
                        $linea .= ' - Ojo: ' . $lateralidad;
                    }

                    if (!empty($proc['observaciones'])) {
                        $linea .= ' (' . $proc['observaciones'] . ')';
                    }

                    $lineas[] = $linea;
                }
                $nombreProcedimientos = implode("\n", $lineas);
            }

            $prefacturas[] = [
                'nombre' => "Prefactura\n" . $nombreProcedimientos,
                'fecha' => $row['fecha_creacion'],
                'tipo' => 'prefactura',
                'form_id' => $row['form_id'] ?? null,
                'detalle' => $row
            ];
        }

        return $prefacturas;
    }

    public function obtenerStaffPorEspecialidad(): array
    {
        $especialidades = ['Cirujano Oftalmólogo', 'Anestesiologo', 'Asistente'];
        $staff = [];

        foreach ($especialidades as $especialidad) {
            $sql = "SELECT nombre FROM users WHERE especialidad LIKE ? ORDER BY nombre";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$especialidad]);
            $staff[$especialidad] = array_map(fn($row) => $row['nombre'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        return $staff;
    }

    public function actualizarPaciente($hc_number, $fname, $mname, $lname, $lname2, $afiliacion, $fecha_nacimiento, $sexo, $celular)
    {
        $stmt = $this->db->prepare("
            UPDATE patient_data 
            SET fname = :fname, 
                mname = :mname, 
                lname = :lname, 
                lname2 = :lname2, 
                afiliacion = :afiliacion,
                fecha_nacimiento = :fecha_nacimiento,
                sexo = :sexo,
                celular = :celular
            WHERE hc_number = :hc_number
        ");
        $stmt->execute([
            ':fname' => $fname,
            ':mname' => $mname,
            ':lname' => $lname,
            ':lname2' => $lname2,
            ':afiliacion' => $afiliacion,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':sexo' => $sexo,
            ':celular' => $celular,
            ':hc_number' => $hc_number
        ]);
    }

    // Obtiene todas las afiliaciones únicas disponibles desde patient_data, filtrando las que no comiencen con un número
    public function getAfiliacionesDisponibles()
    {
        $stmt = $this->db->query("
            SELECT DISTINCT afiliacion 
            FROM patient_data 
            WHERE afiliacion IS NOT NULL 
              AND afiliacion != '' 
              AND afiliacion REGEXP '^[A-Za-z]' 
            ORDER BY afiliacion ASC
        ");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'afiliacion');
    }

    public function getAtencionesParticularesPorSemana($fechaInicio, $fechaFin)
    {
        // Excluir todas las afiliaciones de seguros generales e IESS relacionados
        $sql = "
SELECT p.hc_number, CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS nombre_completo,
       'consulta' AS tipo, cd.form_id, cd.fecha, LOWER(p.afiliacion) AS afiliacion, pp.procedimiento_proyectado, pp.doctor
FROM patient_data p
JOIN consulta_data cd ON cd.hc_number = p.hc_number
JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = cd.form_id
WHERE cd.fecha BETWEEN :inicio1 AND :fin1
  AND LOWER(p.afiliacion) NOT IN ('isspol', 'issfa', 'iess', 'msp',
                                  'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino', 
                                  'seguro campesino jubilado', 'seguro general', 'seguro general jubilado', 
                                  'seguro general por montepío', 'seguro general tiempo parcial')

UNION ALL

SELECT p.hc_number, CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS nombre_completo,
       'protocolo' AS tipo, pd.form_id, pd.fecha_inicio AS fecha, LOWER(p.afiliacion) AS afiliacion, pp.procedimiento_proyectado, pp.doctor
FROM patient_data p
JOIN protocolo_data pd ON pd.hc_number = p.hc_number
JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = pd.form_id
WHERE pd.fecha_inicio BETWEEN :inicio2 AND :fin2
  AND LOWER(p.afiliacion) NOT IN ('isspol', 'issfa', 'iess', 'msp',
                                  'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino', 
                                  'seguro campesino jubilado', 'seguro general', 'seguro general jubilado', 
                                  'seguro general por montepío', 'seguro general tiempo parcial')
";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio1' => $fechaInicio,
            ':fin1' => $fechaFin,
            ':inicio2' => $fechaInicio,
            ':fin2' => $fechaFin,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPacientesPaginados($start, $length, $search = '', $orderColumn = 'hc_number', $orderDir = 'ASC')
    {
        $columns = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
        $orderBy = in_array($orderColumn, $columns) ? $orderColumn : 'hc_number';

        $searchSql = '';
        $params = [];

        if (!empty($search)) {
            $searchSql = "WHERE p.hc_number LIKE :search1 OR p.fname LIKE :search2 OR p.lname LIKE :search3 OR p.afiliacion LIKE :search4";
            $params[':search1'] = "%$search%";
            $params[':search2'] = "%$search%";
            $params[':search3'] = "%$search%";
            $params[':search4'] = "%$search%";
        }

        $countTotal = $this->db->query("SELECT COUNT(*) FROM patient_data")->fetchColumn();

        $stmtFiltered = $this->db->prepare("
            SELECT COUNT(*) 
            FROM patient_data p
            $searchSql
        ");
        if (!empty($params)) {
            $stmtFiltered->execute($params);
        } else {
            $stmtFiltered->execute();
        }
        $countFiltered = $stmtFiltered->fetchColumn();

        $sql = "
            SELECT 
                p.hc_number,
                CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS full_name,
                (SELECT MAX(fecha) FROM consulta_data WHERE hc_number = p.hc_number) AS ultima_fecha,
                p.afiliacion
            FROM patient_data p
            $searchSql
            ORDER BY $orderBy $orderDir
            LIMIT :start, :length
        ";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $estado = $this->verificarCoberturaPaciente($row['hc_number']);
            $data[] = [
                'hc_number' => $row['hc_number'],
                'ultima_fecha' => $row['ultima_fecha']
                    ? date('d/m/Y', strtotime($row['ultima_fecha']))
                    : '', 'full_name' => $row['full_name'],
                'afiliacion' => $row['afiliacion'],
                'estado_html' => $estado === 'Con Cobertura'
                    ? "<span class='badge bg-success'>Con Cobertura</span>"
                    : "<span class='badge bg-danger'>Sin Cobertura</span>",
                'acciones_html' => "<a href='/pacientes/detalles?hc_number={$row['hc_number']}' class='btn btn-sm btn-primary'>Ver</a>"
            ];
        }

        return [
            'recordsTotal' => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data' => $data
        ];
    }

    private function obtenerProcedimientosNormalizados(int $prefacturaId): ?array
    {
        if ($prefacturaId === 0 || !$this->tablaDisponible('prefactura_detalle_procedimientos')) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT posicion, external_id, proc_interno, codigo, descripcion, lateralidad, observaciones, precio_base, precio_tarifado\n"
            . "FROM prefactura_detalle_procedimientos\n"
            . "WHERE prefactura_id = ?\n"
            . "ORDER BY posicion ASC"
        );
        $stmt->execute([$prefacturaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tablaDisponible(string $tabla): bool
    {
        if (isset($this->tablaDisponibleCache[$tabla])) {
            return $this->tablaDisponibleCache[$tabla];
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$tabla]);
                $exists = (bool) $stmt->fetchColumn();
            } else {
                $stmt = $this->db->prepare(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
                );
                $stmt->execute([$tabla]);
                $exists = (bool) $stmt->fetchColumn();
            }
        } catch (PDOException) {
            $exists = false;
        }

        return $this->tablaDisponibleCache[$tabla] = $exists;
    }
}

