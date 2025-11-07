#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function connect(): PDO
{
    try {
        /** @var PDO $pdo */
        $pdo = require __DIR__ . '/../config/database.php';
        return $pdo;
    } catch (Throwable $e) {
        fwrite(STDERR, "[error] No fue posible conectar a la base de datos: {$e->getMessage()}" . PHP_EOL);
        fwrite(STDERR, "        Verifica las variables de entorno DB_HOST, DB_NAME, DB_USER y DB_PASSWORD." . PHP_EOL);
        exit(1);
    }
}

function scenarios(): array
{
    return [
        'protocolos_detalle' => [
            'description' => 'JOIN entre patient_data, protocolo_data y procedimiento_proyectado usado en ProtocoloModel::obtenerProtocolo',
            'sql' => <<<SQL
                SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.afiliacion, p.sexo, p.ciudad,
                       pr.form_id, pr.fecha_inicio, pr.hora_inicio, pr.fecha_fin, pr.hora_fin, pr.cirujano_1, pr.instrumentista,
                       pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, pr.segundo_ayudante,
                       pr.ayudante_anestesia, pr.tercer_ayudante, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo,
                       pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos, pr.lateralidad,
                       pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado, pr.procedimiento_id, pr.insumos
                FROM patient_data p
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                WHERE pr.form_id = :form_id AND p.hc_number = :hc_number
                LIMIT 1
                SQL,
            'params' => [
                [':form_id' => 'FORM-FAKE-001', ':hc_number' => 'HC-FAKE-001'],
            ],
        ],
        'dashboard_diagnosticos' => [
            'description' => 'Lectura masiva de diagnosticos en consulta_data usada en DashboardModel::getDiagnosticosFrecuentes',
            'sql' => <<<SQL
                SELECT hc_number, diagnosticos
                FROM consulta_data
                WHERE diagnosticos IS NOT NULL AND diagnosticos != ''
                SQL,
            'params' => [
                [],
            ],
        ],
        'crm_leads_list' => [
            'description' => 'Listado principal de leads con filtros y ordenamiento por updated_at',
            'sql' => <<<SQL
                SELECT
                    l.id,
                    l.name,
                    l.email,
                    l.phone,
                    l.status,
                    l.source,
                    l.notes,
                    l.customer_id,
                    l.assigned_to,
                    l.created_by,
                    l.created_at,
                    l.updated_at,
                    u.nombre AS assigned_name,
                    c.name AS customer_name
                FROM crm_leads l
                LEFT JOIN users u ON l.assigned_to = u.id
                LEFT JOIN crm_customers c ON l.customer_id = c.id
                WHERE 1 = 1
                  AND (:status IS NULL OR l.status = :status)
                  AND (:assigned_to IS NULL OR l.assigned_to = :assigned_to)
                  AND (:source IS NULL OR l.source = :source)
                  AND (
                        :search IS NULL
                        OR l.name LIKE :search_pattern
                        OR l.email LIKE :search_pattern
                        OR l.phone LIKE :search_pattern
                      )
                ORDER BY l.updated_at DESC
                LIMIT :limit
                SQL,
            'params' => [
                [
                    ':status' => 'nuevo',
                    ':assigned_to' => 123,
                    ':source' => 'Landing',
                    ':search' => 'Maria',
                    ':search_pattern' => '%Maria%',
                    ':limit' => 50,
                ],
                [
                    ':status' => null,
                    ':assigned_to' => null,
                    ':source' => null,
                    ':search' => null,
                    ':search_pattern' => null,
                    ':limit' => 100,
                ],
            ],
        ],
        'crm_tasks_list' => [
            'description' => 'Listado principal de tareas CRM con orden compuesto por due_date y updated_at',
            'sql' => <<<SQL
                SELECT
                    t.id,
                    t.project_id,
                    t.title,
                    t.description,
                    t.status,
                    t.assigned_to,
                    t.created_by,
                    t.due_date,
                    t.completed_at,
                    t.created_at,
                    t.updated_at,
                    assignee.nombre AS assigned_name,
                    creator.nombre AS created_name,
                    p.title AS project_title
                FROM crm_tasks t
                LEFT JOIN users assignee ON t.assigned_to = assignee.id
                LEFT JOIN users creator ON t.created_by = creator.id
                LEFT JOIN crm_projects p ON t.project_id = p.id
                WHERE 1 = 1
                  AND (:project_id IS NULL OR t.project_id = :project_id)
                  AND (:assigned_to IS NULL OR t.assigned_to = :assigned_to)
                  AND (:status IS NULL OR t.status = :status)
                ORDER BY t.due_date IS NULL, t.due_date ASC, t.updated_at DESC
                LIMIT :limit
                SQL,
            'params' => [
                [
                    ':project_id' => 42,
                    ':assigned_to' => 7,
                    ':status' => 'pendiente',
                    ':limit' => 200,
                ],
                [
                    ':project_id' => null,
                    ':assigned_to' => null,
                    ':status' => null,
                    ':limit' => 100,
                ],
            ],
        ],
        'procedimientos_por_fecha' => [
            'description' => 'Consulta de visitas en GuardarProyeccionController para detectar visitas existentes',
            'sql' => <<<SQL
                SELECT id
                FROM visitas
                WHERE hc_number = :hc_number
                  AND fecha_visita = :fecha_visita
                LIMIT 1
                SQL,
            'params' => [
                [
                    ':hc_number' => 'HC-FAKE-001',
                    ':fecha_visita' => '2024-06-10',
                ],
            ],
        ],
        'billing_form_lookup' => [
            'description' => 'Consulta individual de billing_main por form_id usada al validar facturas existentes',
            'sql' => <<<SQL
                SELECT id
                FROM billing_main
                WHERE form_id = :form_id
                LIMIT 1
                SQL,
            'params' => [
                [
                    ':form_id' => 'FORM-FAKE-001',
                ],
            ],
        ],
        'billing_facturas_mes' => [
            'description' => 'Listado de facturas disponibles con joins hacia protocolo_data y procedimiento_proyectado',
            'sql' => <<<SQL
                SELECT
                    bm.id,
                    bm.form_id,
                    bm.hc_number,
                    COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_ordenada
                FROM billing_main bm
                LEFT JOIN protocolo_data pd ON bm.form_id = pd.form_id
                LEFT JOIN procedimiento_proyectado pp ON bm.form_id = pp.form_id
                WHERE (:filtrar_mes = 0)
                   OR (COALESCE(pd.fecha_inicio, pp.fecha) BETWEEN :start_date AND :end_date)
                ORDER BY fecha_ordenada DESC
                LIMIT :limit
                SQL,
            'params' => [
                [
                    ':filtrar_mes' => 0,
                    ':start_date' => null,
                    ':end_date' => null,
                    ':limit' => 100,
                ],
                [
                    ':filtrar_mes' => 1,
                    ':start_date' => '2024-12-01',
                    ':end_date' => '2024-12-31',
                    ':limit' => 50,
                ],
            ],
        ],
        'guardar_proyeccion_form_id' => [
            'description' => 'Validación de existencia previa por form_id en procedimiento_proyectado',
            'sql' => <<<SQL
                SELECT COUNT(*)
                FROM procedimiento_proyectado
                WHERE form_id = :form_id
                SQL,
            'params' => [
                [
                    ':form_id' => 'FORM-FAKE-001',
                ],
            ],
        ],
        'guardar_proyeccion_horario' => [
            'description' => 'Determinación de hora más temprana por paciente y fecha en procedimiento_proyectado',
            'sql' => <<<SQL
                SELECT MIN(hora)
                FROM procedimiento_proyectado
                WHERE hc_number = :hc_number
                  AND fecha = :fecha
                SQL,
            'params' => [
                [
                    ':hc_number' => 'HC-FAKE-001',
                    ':fecha' => '2024-06-10',
                ],
            ],
        ],
    ];
}

function printTable(array $rows): void
{
    if (!$rows) {
        echo "(sin filas)" . PHP_EOL;
        return;
    }

    $columns = array_keys($rows[0]);
    $widths = [];

    foreach ($columns as $column) {
        $widths[$column] = strlen((string) $column);
    }

    foreach ($rows as $row) {
        foreach ($columns as $column) {
            $widths[$column] = max($widths[$column], strlen((string) ($row[$column] ?? '')));
        }
    }

    $header = [];
    foreach ($columns as $column) {
        $header[] = str_pad((string) $column, $widths[$column]);
    }
    echo implode(' | ', $header) . PHP_EOL;
    echo implode(' | ', array_map(fn($width) => str_repeat('-', $width), $widths)) . PHP_EOL;

    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            $line[] = str_pad((string) $value, $widths[$column]);
        }
        echo implode(' | ', $line) . PHP_EOL;
    }
}

function main(array $argv): void
{
    $options = getopt('', ['scenario::', 'list', 'json']);

    $allScenarios = scenarios();

    if (isset($options['list'])) {
        echo "Escenarios disponibles:" . PHP_EOL;
        foreach ($allScenarios as $name => $config) {
            echo " - {$name}: {$config['description']}" . PHP_EOL;
        }
        exit(0);
    }

    $selected = $allScenarios;

    if (!empty($options['scenario'])) {
        $requested = explode(',', (string) $options['scenario']);
        $selected = [];
        foreach ($requested as $scenarioName) {
            $scenarioName = trim($scenarioName);
            if (!isset($allScenarios[$scenarioName])) {
                fwrite(STDERR, "[error] Escenario desconocido: {$scenarioName}" . PHP_EOL);
                fwrite(STDERR, "        Ejecuta con --list para ver las opciones disponibles." . PHP_EOL);
                exit(1);
            }
            $selected[$scenarioName] = $allScenarios[$scenarioName];
        }
    }

    $asJson = isset($options['json']);

    $pdo = connect();

    foreach ($selected as $name => $scenario) {
        $paramsSets = $scenario['params'] ?? [[]];

        foreach ($paramsSets as $index => $params) {
            $label = count($paramsSets) > 1 ? sprintf('%s (caso %d)', $name, $index + 1) : $name;
            echo PHP_EOL . "== {$label} ==" . PHP_EOL;
            echo $scenario['description'] . PHP_EOL;

            try {
                $stmt = $pdo->prepare('EXPLAIN ' . $scenario['sql']);

                foreach ($params as $key => $value) {
                    $param = is_string($key) && $key !== '' && $key[0] !== ':' ? ':' . $key : $key;

                    $type = PDO::PARAM_STR;
                    if ($value === null) {
                        $type = PDO::PARAM_NULL;
                    } elseif (is_int($value)) {
                        $type = PDO::PARAM_INT;
                    } elseif (is_bool($value)) {
                        $type = PDO::PARAM_BOOL;
                    }

                    $stmt->bindValue($param, $value, $type);
                }

                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($asJson) {
                    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                } else {
                    printTable($rows);
                }
            } catch (PDOException $e) {
                fwrite(STDERR, "[error] No se pudo ejecutar EXPLAIN para {$label}: {$e->getMessage()}" . PHP_EOL);
            }
        }
    }
}

main($argv);
