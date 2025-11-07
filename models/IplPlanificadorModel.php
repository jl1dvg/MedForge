<?php

namespace Models;

use PDO;

class IplPlanificadorModel
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // Nuevo método estático para obtener todas las cirugías
    public static function obtenerTodas(PDO $db): array
    {
        $sql = "SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                       pr.fecha_inicio, pr.id, pr.membrete, pr.form_id, pr.hora_inicio, pr.hora_fin, pr.printed,
                       pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, 
                       pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado,
                       pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, 
                       pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante, pr.status,
                       CASE WHEN bm.id IS NOT NULL THEN 1 ELSE 0 END AS existeBilling
                FROM patient_data p 
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
                WHERE pr.procedimiento_id LIKE 'sondaje_via_lagrimal' 
                AND p.afiliacion COLLATE utf8mb4_unicode_ci IN (
                    'isspol', 'issfa', 'iess', 'msp',
                    'contribuyente voluntario', 'conyuge', 'conyuge pensionista',
                    'seguro campesino', 'seguro campesino jubilado',
                    'seguro general', 'seguro general jubilado',
                    'seguro general por montepío', 'seguro general tiempo parcial',
                    'sin cobertura'
                )
                ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new self($row), $rows);
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getNombreCompleto(): string
    {
        return trim("{$this->data['fname']} {$this->data['lname']} {$this->data['lname2']}");
    }

    public function getEstado(): string
    {
        if ($this->data['status'] == 1) return 'revisado';

        $invalid = ['CENTER', 'undefined'];
        $required = [
            $this->data['membrete'], $this->data['dieresis'], $this->data['exposicion'], $this->data['hallazgo'],
            $this->data['operatorio'], $this->data['complicaciones_operatorio'], $this->data['datos_cirugia'],
            $this->data['procedimientos'], $this->data['lateralidad'], $this->data['tipo_anestesia'],
            $this->data['diagnosticos'], $this->data['procedimiento_proyectado'],
            $this->data['fecha_inicio'], $this->data['hora_inicio'], $this->data['hora_fin']
        ];

        foreach ($required as $field) {
            if (!empty($field)) {
                foreach ($invalid as $inv) {
                    if (stripos($field ?? '', $inv) !== false) return 'incompleto';
                }
            }
        }

        $staff = [
            $this->data['cirujano_1'], $this->data['instrumentista'], $this->data['cirujano_2'],
            $this->data['circulante'], $this->data['primer_ayudante'], $this->data['anestesiologo'],
            $this->data['segundo_ayudante'], $this->data['ayudante_anestesia'], $this->data['tercer_ayudante']
        ];

        $staffCount = 0;
        foreach ($staff as $s) {
            if (!empty($s) && !in_array(strtoupper($s), $invalid)) $staffCount++;
        }

        if (!empty($this->data['cirujano_1']) && $staffCount >= 5) {
            return 'no revisado';
        }

        return 'incompleto';
    }

    public static function verificarOInsertarDerivacion(PDO $db, string $form_id, string $hc_number, array $scraperResponse): void
    {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM derivaciones_form_id WHERE form_id = ? AND cod_derivacion = ?");
        $stmtCheck->execute([trim($form_id, "'"), $scraperResponse['cod_derivacion'] ?? '']);
        $existe = $stmtCheck->fetchColumn();

        if ($existe == 0 && !empty($scraperResponse['cod_derivacion'])) {
            $stmtInsert = $db->prepare("
                INSERT INTO derivaciones_form_id (cod_derivacion, form_id, hc_number, fecha_registro, fecha_vigencia, diagnostico)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([
                $scraperResponse['cod_derivacion'],
                trim($form_id, "'"),
                trim($hc_number, "'"),
                $scraperResponse['fecha_registro'] ?? null,
                $scraperResponse['fecha_vigencia'] ?? null,
                $scraperResponse['diagnostico'] ?? null
            ]);
        }
    }
}