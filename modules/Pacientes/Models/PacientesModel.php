<?php
namespace Modules\Pacientes\Models;

use PDO;

class PacientesModel
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    // === Bloque: utilidades que ya usabas ===

    public function verificarCoberturaPaciente(string $hc_number): string
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

        if (!$row) return 'N/A';

        return (strtotime($row['fecha_vigencia']) >= time()) ? 'Con Cobertura' : 'Sin Cobertura';
    }

    // === DataTables server-side (lo que hoy hace tu PacienteController::obtenerPacientesConUltimaConsultaDataTable) ===
    public function fetchDataTable(array $input): array
    {
        $start  = isset($input['start'])  ? (int)$input['start']  : 0;
        $length = isset($input['length']) ? (int)$input['length'] : 10;
        $search = trim($input['search']['value'] ?? '');

        // Orden
        $columnsMap = [
            0 => 'p.hc_number',
            1 => 'ultima_fecha',
            2 => 'full_name',
            3 => 'p.afiliacion'
        ];
        $orderIdx = (int)($input['order'][0]['column'] ?? 0);
        $orderDir = strtoupper($input['order'][0]['dir'] ?? 'ASC');
        $orderCol = $columnsMap[$orderIdx] ?? 'p.hc_number';
        $orderDir = ($orderDir === 'DESC') ? 'DESC' : 'ASC';

        // Búsqueda
        $params    = [];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = " AND (p.hc_number LIKE :q OR CONCAT(p.fname,' ',p.mname,' ',p.lname,' ',p.lname2) LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        // Conteo total
        $countTotal = (int)$this->db->query("SELECT COUNT(*) FROM patient_data")->fetchColumn();

        // Conteo filtrado
        $sqlCount = "
            SELECT COUNT(*) FROM (
                SELECT p.hc_number
                FROM patient_data p
                LEFT JOIN (
                    SELECT hc_number, MAX(fecha) AS ultima_fecha
                    FROM consulta_data
                    GROUP BY hc_number
                ) u ON u.hc_number = p.hc_number
                WHERE 1=1 {$searchSql}
            ) t
        ";
        $stmt = $this->db->prepare($sqlCount);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $countFiltered = (int)$stmt->fetchColumn();

        // Datos
        $sql = "
            SELECT
                p.hc_number,
                CONCAT(p.fname,' ',IFNULL(p.mname,''),' ',p.lname,' ',IFNULL(p.lname2,'')) AS full_name,
                p.afiliacion,
                u.ultima_fecha
            FROM patient_data p
            LEFT JOIN (
                SELECT hc_number, MAX(fecha) AS ultima_fecha
                FROM consulta_data
                GROUP BY hc_number
            ) u ON u.hc_number = p.hc_number
            WHERE 1=1 {$searchSql}
            ORDER BY {$orderCol} {$orderDir}
            LIMIT :start, :length
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $estado = $this->verificarCoberturaPaciente($row['hc_number']);

            $data[] = [
                'hc_number'      => $row['hc_number'],
                'ultima_fecha'   => $row['ultima_fecha'] ? date('d/m/Y', strtotime($row['ultima_fecha'])) : '',
                'full_name'      => trim(preg_replace('/\s+/', ' ', $row['full_name'])),
                'afiliacion'     => $row['afiliacion'] ?? '',
                'estado_html'    => $estado === 'Con Cobertura'
                    ? "<span class='badge bg-success'>Con Cobertura</span>"
                    : ($estado === 'Sin Cobertura'
                        ? "<span class='badge bg-danger'>Sin Cobertura</span>"
                        : "<span class='badge bg-secondary'>N/A</span>"),
                'acciones_html'  => "<a href=\"/pacientes/detalle/{$row['hc_number']}\" class=\"btn btn-sm btn-primary\">Ver</a>"
            ];
        }

        return [
            'draw'            => (int)($input['draw'] ?? 1),
            'recordsTotal'    => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data'            => $data,
        ];
    }

    // Para la ruta /pacientes/detalle/{hc}
    public function getDetallePaciente(string $hc_number): array
    {
        // Unifica lo que hoy obtienes con tus métodos (consulta_data, solicitud_procedimiento, protocolo_data, etc.)
        $stmt = $this->db->prepare("
            SELECT p.hc_number,
                   CONCAT(p.fname,' ',IFNULL(p.mname,''),' ',p.lname,' ',IFNULL(p.lname2,'')) AS full_name,
                   p.afiliacion, p.fecha_nacimiento, p.sexo
            FROM patient_data p
            WHERE p.hc_number = ?
            LIMIT 1
        ");
        $stmt->execute([$hc_number]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Últimas consultas
        $stmt2 = $this->db->prepare("
            SELECT form_id, fecha, diagnosticos
            FROM consulta_data
            WHERE hc_number = ?
            ORDER BY fecha DESC
            LIMIT 10
        ");
        $stmt2->execute([$hc_number]);
        $consultas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        return [
            'paciente'  => $paciente,
            'consultas' => $consultas,
        ];
    }
}