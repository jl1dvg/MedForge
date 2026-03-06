<?php

use Core\Router;
use Modules\Billing\Controllers\BillingController;
use Modules\Billing\Controllers\InformesController;
use Controllers\DerivacionController;

return function (Router $router) {
    $billingV2UiEnabled = static function (): bool {
        $rawFlag = null;
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $envPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

        // Prefer explicit value from .env to avoid stale process-level env vars.
        if (is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                if (trim($key) !== 'BILLING_V2_UI_ENABLED') {
                    continue;
                }

                $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                break;
            }
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $rawFlag = $_ENV['BILLING_V2_UI_ENABLED'] ?? getenv('BILLING_V2_UI_ENABLED') ?? null;
        }

        return filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);
    };

    $redirectToV2IfEnabled = static function (string $target) use ($billingV2UiEnabled): void {
        if (!$billingV2UiEnabled()) {
            return;
        }

        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        header('Location: ' . $target, true, 302);
        exit;
    };

    $router->get('/billing', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/billing');
        (new BillingController($pdo))->index();
    });

    $router->get('/billing/detalle', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/billing/detalle');
        (new BillingController($pdo))->detalle();
    });

    $router->get('/billing/no-facturados', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/billing/no-facturados');
        (new BillingController($pdo))->noFacturados();
    });

    $router->get('/billing/dashboard', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/billing/dashboard');
        (new BillingController($pdo))->dashboard();
    });

    $router->post('/billing/dashboard-data', function (\PDO $pdo) {
        (new BillingController($pdo))->dashboardData();
    });

    $router->get('/billing/honorarios', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/billing/honorarios');
        (new BillingController($pdo))->honorarios();
    });

    $router->post('/billing/honorarios-data', function (\PDO $pdo) {
        (new BillingController($pdo))->honorariosData();
    });

    $router->post('/billing/no-facturados/crear', function (\PDO $pdo) {
        (new BillingController($pdo))->crearDesdeNoFacturado();
    });

    $router->get('/api/billing/no-facturados', function (\PDO $pdo) {
        (new BillingController($pdo))->apiNoFacturados();
    });

    $router->get('/api/billing/afiliaciones', function (\PDO $pdo) {
        (new BillingController($pdo))->apiAfiliaciones();
    });

    $router->get('/api/billing/sedes', function (\PDO $pdo) {
        (new BillingController($pdo))->apiSedes();
    });

    $router->get('/views/billing/no_facturados.php', function () {
        header('Location: /billing/no-facturados', true, 302);
        exit;
    });

    $router->post('/views/billing/components/crear_desde_no_facturado.php', function (\PDO $pdo) {
        (new BillingController($pdo))->crearDesdeNoFacturado();
    });

    $router->match(['GET', 'POST'], '/informes/iess', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIess();
    });

    $router->match(['GET', 'POST'], '/informes/isspol', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIsspol();
    });

    $router->match(['GET', 'POST'], '/informes/issfa', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIssfa();
    });

    $router->match(['GET', 'POST'], '/informes/msp', function (\PDO $pdo) {
        (new InformesController($pdo))->informeMsp();
    });

    $router->match(['GET', 'POST'], '/informes/particulares', function (\PDO $pdo) {
        (new InformesController($pdo))->informeParticulares();
    });

    $router->match(['GET', 'POST'], '/informes/iess/prueba', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIessPrueba();
    });

    $router->get('/informes/iess/consolidado', function (\PDO $pdo) {
        (new InformesController($pdo))->generarConsolidadoIess();
    });

    $router->get('/informes/isspol/consolidado', function (\PDO $pdo) {
        (new InformesController($pdo))->generarConsolidadoIsspol();
    });

    $router->get('/informes/issfa/consolidado', function (\PDO $pdo) {
        (new InformesController($pdo))->generarConsolidadoIssfa();
    });

    $router->get('/informes/iess/excel-lote', function (\PDO $pdo) {
        (new InformesController($pdo))->generarExcelIessLote();
    });

    $router->match(['GET', 'POST'], '/informes/api/detalle-factura', function (\PDO $pdo) {
        (new InformesController($pdo))->ajaxDetalleFactura();
    });

    $router->post('/informes/api/eliminar-factura', function (\PDO $pdo) {
        (new InformesController($pdo))->ajaxEliminarFactura();
    });

    $router->post('/informes/api/scrapear-codigo', function (\PDO $pdo) {
        (new InformesController($pdo))->ajaxScrapearCodigoDerivacion();
    });

    // Endpoints legacy utilizados por la extensión CIVE (deben responder JSON limpio)
    $router->post('/api/billing/verificacion_derivacion.php', function (\PDO $pdo) {
        ini_set('display_errors', 0);
        ini_set('html_errors', 0);
        header('Content-Type: application/json');

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'],
                ]);
                exit;
            }
        });

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => "$errstr in $errfile on line $errline",
            ]);
            exit;
        });

        try {
            $formIds = $_POST['form_ids'] ?? [];
            if (!is_array($formIds)) {
                $formIds = [$formIds];
            }

            $controller = new DerivacionController($pdo);
            echo json_encode($controller->verificarFormIds($formIds));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    });

    $router->post('/api/billing/insertar_billing_main.php', function (\PDO $pdo) {
        ini_set('display_errors', 0);
        ini_set('html_errors', 0);
        header('Content-Type: application/json');

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'],
                ]);
                exit;
            }
        });

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => "$errstr in $errfile on line $errline",
            ]);
            exit;
        });

        try {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw ?: 'null', true);

            if (!is_array($payload)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'JSON inválido o vacío',
                    'raw' => $raw,
                ]);
                return;
            }

            $procedimientos = $payload['procedimientos'] ?? [];
            if (!is_array($procedimientos)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Formato inválido: se esperaba "procedimientos" como arreglo',
                ]);
                return;
            }

            $controller = new DerivacionController($pdo);
            echo json_encode($controller->registrarProcedimientoCompleto($procedimientos));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    });
};
