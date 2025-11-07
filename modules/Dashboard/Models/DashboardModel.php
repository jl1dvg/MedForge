<?php

namespace Modules\Dashboard\Models;

use PDO;

class DashboardModel
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function getTotalCirugias(): int
    {
        // 👇 fuerza entero
        return (int)$this->db->query("SELECT COUNT(*) as total FROM protocolo_data")->fetchColumn();
    }

    public function totalPacientes(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM patient_data")->fetchColumn();
    }

    public function totalUsuarios(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function totalProtocolos(): int
    {
        $result = $this->db->query("SELECT COUNT(*) FROM protocolo_data")->fetchColumn();
        return (int)$result;
    }

    public function getProcedimientosPorDia(int $limit = 12): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE(fecha_inicio) AS fecha, COUNT(*) AS total 
            FROM protocolo_data 
            GROUP BY DATE(fecha_inicio)
            ORDER BY fecha DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 👇 fuerza enteros
        $totales = array_map(static fn($v) => (int)$v, array_column($rows, 'total'));

        return [
            'fechas' => array_column($rows, 'fecha'),
            'totales' => $totales,
        ];
    }

    public function getTopProcedimientosDelMes(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT procedimiento_id, COUNT(*) AS total 
            FROM protocolo_data 
            WHERE YEAR(fecha_inicio) = YEAR(CURDATE())
            GROUP BY procedimiento_id
            ORDER BY total DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 👇 fuerza enteros
        $totales = array_map(static fn($v) => (int)$v, array_column($rows, 'total'));

        return [
            'membretes' => array_column($rows, 'procedimiento_id'),
            'totales' => $totales,
        ];
    }

    public function getCirugiasRecientes(int $limit = 8, ?string $desde = null, ?string $hasta = null): array
    {
        try {
            $sql = "
            SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                   pr.fecha_inicio, pr.id, pr.membrete, pr.form_id
            FROM patient_data p 
            INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
            WHERE p.afiliacion != 'ALQUILER'
        ";

            if ($desde && $hasta) {
                $sql .= " AND pr.fecha_inicio BETWEEN :desde AND :hasta";
            }

            $sql .= " ORDER BY pr.fecha_inicio DESC, pr.id DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);

            if ($desde && $hasta) {
                $stmt->bindValue(':desde', $desde);
                $stmt->bindValue(':hasta', $hasta);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total' => count($rows),
                'data' => $rows
            ];

        } catch (\PDOException $e) {
            file_put_contents(__DIR__ . '/../../../../logs/dashboard_errors.log',
                date('Y-m-d H:i:s') . " getCirugiasRecientes() → " . $e->getMessage() . "\n",
                FILE_APPEND
            );
            return ['total' => 0, 'data' => []];
        }
    }

    public function getPlantillasRecientes(int $limit = 20): array
    {
        $sql = "SELECT id, membrete, cirugia, 
                       COALESCE(fecha_actualizacion, fecha_creacion) AS fecha,
                       CASE 
                           WHEN fecha_actualizacion IS NOT NULL THEN 'Modificado'
                           ELSE 'Creado'
                       END AS tipo
                FROM procedimientos
                ORDER BY fecha DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUltimasSolicitudes(int $limit = 5): array
    {
        $sql = "SELECT sp.id, sp.fecha, sp.procedimiento, p.fname, p.lname, p.hc_number
                FROM solicitud_procedimiento sp
                JOIN patient_data p 
                  ON sp.hc_number COLLATE utf8mb4_unicode_ci = p.hc_number COLLATE utf8mb4_unicode_ci
                WHERE sp.procedimiento IS NOT NULL 
                  AND sp.procedimiento != '' 
                  AND sp.procedimiento != 'SELECCIONE'
                ORDER BY sp.fecha DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalStmt = $this->db->query("SELECT COUNT(*) as total 
            FROM solicitud_procedimiento 
            WHERE procedimiento IS NOT NULL 
              AND procedimiento != '' 
              AND procedimiento != 'SELECCIONE'");
        // 👇 fuerza entero
        $total = (int)$totalStmt->fetchColumn();

        return [
            'solicitudes' => $result,
            'total' => $total
        ];
    }

    public function getTopDoctores(): array
    {
        $sql = "SELECT cirujano_1, COUNT(*) as total
                FROM protocolo_data
                WHERE cirujano_1 IS NOT NULL 
                  AND cirujano_1 != ''
                  AND fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                GROUP BY cirujano_1
                ORDER BY total DESC
                LIMIT 5";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 👇 fuerza enteros en 'total'
        foreach ($rows as &$r) {
            $r['total'] = (int)$r['total'];
        }
        unset($r);

        return $rows;
    }

    public function getEstadisticasPorAfiliacion(): array
    {
        $inicioMes = (new \DateTime('first day of this month'))->format('Y-m-01');
        $finMes = (new \DateTime('first day of next month'))->format('Y-m-01');

        $sql = "SELECT p.afiliacion, COUNT(*) as total_procedimientos
                FROM protocolo_data pr
                INNER JOIN patient_data p ON pr.hc_number = p.hc_number
                WHERE pr.fecha_inicio >= ? AND pr.fecha_inicio < ?
                GROUP BY p.afiliacion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inicioMes, $finMes]);

        $afiliaciones = [];
        $totales = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $afiliaciones[] = $row['afiliacion'];
            // 👇 fuerza entero
            $totales[] = (int)$row['total_procedimientos'];
        }

        return [
            'afiliaciones' => $afiliaciones ?: ['No data'],
            'totales' => $totales ?: [0],
        ];
    }

    public function getEstadosRevisionProtocolos(): array
    {
        $sql = "SELECT pr.status, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio,
                       pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos,
                       pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado,
                       pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante,
                       pr.anestesiologo, pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante
                FROM protocolo_data pr
                LEFT JOIN procedimiento_proyectado pp 
                  ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $stmt = $this->db->query($sql);

    // Fuerza tipo numérico inicial
        $incompletos = 0;
        $revisados = 0;
        $no_revisados = 0;

        $invalidValues = ['CENTER', 'undefined'];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fuerza status a entero (puede venir como string)
        $row['status'] = (int)($row['status'] ?? 0);

            $required = [
                $row['membrete'], $row['dieresis'], $row['exposicion'], $row['hallazgo'], $row['operatorio'],
                $row['complicaciones_operatorio'], $row['datos_cirugia'], $row['procedimientos'],
                $row['lateralidad'], $row['tipo_anestesia'], $row['diagnosticos'], $row['procedimiento_proyectado']
            ];
            $staff = [
                $row['cirujano_1'], $row['instrumentista'], $row['cirujano_2'], $row['circulante'],
                $row['primer_ayudante'], $row['anestesiologo'], $row['segundo_ayudante'],
                $row['ayudante_anestesia'], $row['tercer_ayudante']
            ];

        if ($row['status'] === 1) {
            $revisados = (int)$revisados + 1;
            continue;
        }

                $invalid = false;

        // Validar campos obligatorios
                foreach ($required as $field) {
                    foreach ($invalidValues as $v) {
                        if (!empty($field) && stripos($field, $v) !== false) {
                            $invalid = true;
                            break 2;
                        }
                    }
                }

        // Contar staff válido
                $staffCount = 0;
                if (!empty($row['cirujano_1'])) {
                    foreach ($staff as $field) {
                        foreach ($invalidValues as $v) {
                            if (!empty($field) && stripos($field, $v) !== false) {
                                $invalid = true;
                                break 2;
                            }
                        }
                if (!empty($field)) {
                    $staffCount = (int)$staffCount + 1;
                }
                    }
                } else {
                    $invalid = true;
                }

                if (!$invalid && $staffCount >= 5) {
            $no_revisados = (int)$no_revisados + 1;
                } else {
            $incompletos = (int)$incompletos + 1;
            }
        }

        return [
        'incompletos' => (int)$incompletos,
        'revisados' => (int)$revisados,
        'no_revisados' => (int)$no_revisados
        ];
    }

    public function getDiagnosticosFrecuentes(): array
    {
        $sql = "SELECT hc_number, diagnosticos 
            FROM consulta_data 
            WHERE diagnosticos IS NOT NULL AND diagnosticos != ''";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $conteoDiagnosticos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hc = $row['hc_number'];
            $diagnosticos = json_decode($row['diagnosticos'], true);

            if (is_array($diagnosticos)) {
                foreach ($diagnosticos as $dx) {
                    $id = isset($dx['idDiagnostico'])
                        ? strtoupper(str_replace('.', '', $dx['idDiagnostico']))
                        : 'SINID';

                    $desc = is_array($dx) && array_key_exists('descripcion', $dx)
                        ? $dx['descripcion']
                        : 'Sin descripción';

                    // Excluir diagnósticos tipo Z
                    if (stripos($id, 'Z') === 0) continue;

                    // Agrupar H25 y H251 como un mismo diagnóstico
                    if ($id === 'H25' || $id === 'H251') {
                        $key = 'H25 | Catarata senil';
                    } else {
                        $key = $id . ' | ' . $desc;
                    }

                    // Contar pacientes únicos por diagnóstico
                    $conteoDiagnosticos[$key][$hc] = true;
                }
            }
        }

        // Calcular prevalencia (número de pacientes únicos)
        $prevalencias = [];
        foreach ($conteoDiagnosticos as $key => $pacientes) {
            $prevalencias[$key] = count($pacientes);
        }

        // Ordenar y limitar a los 9 diagnósticos más frecuentes
        arsort($prevalencias);
        return array_slice($prevalencias, 0, 9, true);
    }

    public function getAuthenticatedUser(): ?string
    {
        // Si no hay sesión iniciada, devolvemos null (el controller decide qué hacer)
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', (int)$_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $username = $stmt->fetchColumn();
        // Si no encontramos username, devolvemos un fallback amigable
        return $username !== false ? $username : 'Invitado';
    }
}