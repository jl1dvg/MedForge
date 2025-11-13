<?php
namespace Controllers;

use PDO;

class DashboardController
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function getAuthenticatedUser()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit();
        }

        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $username = $stmt->fetchColumn();

        return $username;
    }

    public function totalPacientes()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM patient_data");
        return $stmt->fetchColumn() ?? 0;
    }

    public function totalUsuarios()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM users");
        return $stmt->fetchColumn() ?? 0;
    }

    public function totalProtocolos()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM protocolo_data");
        return $stmt->fetchColumn() ?? 0;
    }

    public function getRecentCirugias($limit = 8)
    {
        $stmt = $this->db->prepare("SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                                        pr.fecha_inicio, pr.id, pr.membrete, pr.form_id
                                    FROM patient_data p 
                                    INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                                    ORDER BY pr.fecha_inicio DESC, pr.id DESC
                                    LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDiagnosticosFrecuentes(): array
    {
        $sql = "SELECT hc_number, diagnosticos FROM consulta_data WHERE diagnosticos IS NOT NULL AND diagnosticos != ''";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $conteoDiagnosticos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hc = $row['hc_number'];
            $diagnosticos = json_decode($row['diagnosticos'], true);
            if (is_array($diagnosticos)) {
                foreach ($diagnosticos as $dx) {
                    $id = isset($dx['idDiagnostico']) ? strtoupper(str_replace('.', '', $dx['idDiagnostico'])) : 'SINID';
                    $desc = is_array($dx) && array_key_exists('descripcion', $dx) ? $dx['descripcion'] : 'Sin descripción';

                    if (stripos($id, 'Z') === 0) continue; // Excluir diagnósticos tipo Z

                    // Agrupación específica: unificar H25 y H251 como un solo diagnóstico
                    if ($id === 'H25' || $id === 'H251') {
                        $key = 'H25 | Catarata senil';
                    } else {
                        $key = $id . ' | ' . $desc;
                    }

                    $conteoDiagnosticos[$key][$hc] = true;
                }
            }
        }

        // Calcular cuántos pacientes únicos por diagnóstico
        $prevalencias = [];
        foreach ($conteoDiagnosticos as $key => $pacientes) {
            $prevalencias[$key] = count($pacientes);
        }

        // Ordenar y tomar los 9 más frecuentes
        arsort($prevalencias);
        return array_slice($prevalencias, 0, 9, true);
    }

    public function getProcedimientosPorDia()
    {
        $sql = "SELECT DATE(fecha_inicio) as fecha, COUNT(*) as total_procedimientos 
            FROM protocolo_data 
            GROUP BY DATE(fecha_inicio)
            ORDER BY fecha DESC 
            LIMIT 12";

        $stmt = $this->db->query($sql);

        $fechas = [];
        $totales = [];

        if ($stmt && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fechas[] = date('Y-m-d', strtotime($row['fecha']));
                $totales[] = $row['total_procedimientos'];
            }
        } else {
            $fechas = ['No data'];
            $totales = [0];
        }

        return [
            'fechas' => $fechas,
            'totales' => $totales,
        ];
    }

    public function getTopProcedimientosDelMes()
    {
        $current_year = date('Y');

        $sql = "SELECT procedimiento_id, COUNT(*) as total_procedimientos 
                FROM protocolo_data 
                WHERE YEAR(fecha_inicio) = :year 
                  AND procedimiento_id IS NOT NULL 
                  AND procedimiento_id != ''
                GROUP BY procedimiento_id
                ORDER BY total_procedimientos DESC
                LIMIT 5";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $current_year]);

        $membretes = [];
        $totales = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $membretes[] = $row['procedimiento_id'];
            $totales[] = $row['total_procedimientos'];
        }

        return [
            'membretes' => $membretes ?: ['No data'],
            'totales' => $totales ?: [0],
        ];
    }

    public function getCirugiasRecientes($limit = 8)
    {
        $sql = "SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                   pr.fecha_inicio, pr.id, pr.membrete, pr.form_id
            FROM patient_data p 
            INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
            WHERE p.afiliacion != 'ALQUILER' 
            ORDER BY pr.fecha_inicio DESC, pr.id DESC
            LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalCirugias()
    {
        $sql = "SELECT COUNT(*) as total FROM protocolo_data";
        $stmt = $this->db->query($sql);
        return $stmt->fetchColumn();
    }

    public function getPlantillasRecientes($limit = 20)
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

    public function getUltimasSolicitudes($limit = 5)
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
        $total = $totalStmt->fetchColumn();

        return [
            'solicitudes' => $result,
            'total' => $total
        ];
    }

    public function getTopDoctores()
    {
        $sql = "SELECT
                pr.cirujano_1,
                COUNT(*) AS total,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ''
                      AND (
                        LOWER(TRIM(u.nombre)) = LOWER(TRIM(pr.cirujano_1))
                        OR LOWER(TRIM(pr.cirujano_1)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
                        OR LOWER(TRIM(u.username)) = LOWER(TRIM(pr.cirujano_1))
                        OR LOWER(TRIM(u.email)) = LOWER(TRIM(pr.cirujano_1))
                      )
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS avatar_path
            FROM protocolo_data pr
            WHERE pr.cirujano_1 IS NOT NULL
              AND pr.cirujano_1 != ''
              AND pr.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            GROUP BY pr.cirujano_1
            ORDER BY total DESC
            LIMIT 5";

        $stmt = $this->db->query($sql);
        $doctores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($doctores as &$doctor) {
            $doctor['avatar'] = $this->formatProfilePhoto($doctor['avatar_path'] ?? null);
            unset($doctor['avatar_path']);
        }

        return $doctores;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $path)) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    public function getEstadisticasPorAfiliacion()
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
            $totales[] = $row['total_procedimientos'];
        }

        return [
            'afiliaciones' => $afiliaciones ?: ['No data'],
            'totales' => $totales ?: [0],
        ];
    }

    public function getEstadosRevisionProtocolos()
    {
        $sql = "SELECT pr.status, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio,
                   pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos,
                   pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado,
                   pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante,
                   pr.anestesiologo, pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante
            FROM protocolo_data pr
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $stmt = $this->db->query($sql);

        $incompletos = 0;
        $revisados = 0;
        $no_revisados = 0;

        $invalidValues = ['CENTER', 'undefined'];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

            if ($row['status'] == 1) {
                $revisados++;
            } else {
                $invalid = false;
                foreach ($required as $field) {
                    foreach ($invalidValues as $v) {
                        if (!empty($field) && stripos($field, $v) !== false) {
                            $invalid = true;
                            break 2;
                        }
                    }
                }

                $staffCount = 0;
                if (!empty($row['cirujano_1'])) {
                    foreach ($staff as $field) {
                        foreach ($invalidValues as $v) {
                            if (!empty($field) && stripos($field, $v) !== false) {
                                $invalid = true;
                                break 2;
                            }
                        }
                        if (!empty($field)) $staffCount++;
                    }
                } else {
                    $invalid = true;
                }

                if (!$invalid && $staffCount >= 5) {
                    $no_revisados++;
                } else {
                    $incompletos++;
                }
            }
        }

        return [
            'incompletos' => $incompletos,
            'revisados' => $revisados,
            'no_revisados' => $no_revisados
        ];
    }
}
