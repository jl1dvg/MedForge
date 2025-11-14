<?php

namespace Modules\Billing\Controllers;

use Controllers\BillingController as LegacyBillingController;
use Core\BaseController;
use Modules\Pacientes\Services\PacienteService;
use PDO;

class InformesController extends BaseController
{
    /** @var LegacyBillingController */
    private $billingController;

    /** @var PacienteService */
    private $pacienteService;

    /** @var array<string, array> */
    private $grupoConfigs = [];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->billingController = new LegacyBillingController($pdo);
        $this->pacienteService = new PacienteService($pdo);
        $this->grupoConfigs = [
            'iess' => [
                'slug' => 'iess',
                'titulo' => 'Informe IESS',
                'basePath' => '/informes/iess',
                'afiliaciones' => [
                    'contribuyente voluntario',
                    'conyuge',
                    'conyuge pensionista',
                    'seguro campesino',
                    'seguro campesino jubilado',
                    'seguro general',
                    'seguro general jubilado',
                    'seguro general por montepio',
                    'seguro general tiempo parcial',
                    'hijos dependientes',
                ],
                'excelButtons' => [
                    [
                        'grupo' => 'IESS',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                    [
                        'grupo' => 'IESS_SOAM',
                        'label' => 'Descargar SOAM',
                        'class' => 'btn btn-outline-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Ver todas las atenciones por cobrar',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes IESS',
            ],
            'isspol' => [
                'slug' => 'isspol',
                'titulo' => 'Informe ISSPOL',
                'basePath' => '/informes/isspol',
                'afiliaciones' => ['isspol'],
                'excelButtons' => [
                    [
                        'grupo' => 'ISSPOL',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Obtener código de derivación',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes ISSPOL',
                'enableApellidoFilter' => true,
            ],
            'issfa' => [
                'slug' => 'issfa',
                'titulo' => 'Informe ISSFA',
                'basePath' => '/informes/issfa',
                'afiliaciones' => ['issfa'],
                'excelButtons' => [
                    [
                        'grupo' => 'ISSFA',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Obtener código de derivación',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes ISSFA',
                'enableApellidoFilter' => true,
            ],
        ];
    }

    public function informeIess(): void
    {
        $this->renderInformeGrupo('iess');
    }

    public function informeIsspol(): void
    {
        $this->renderInformeGrupo('isspol');
    }

    public function informeIssfa(): void
    {
        $this->renderInformeGrupo('issfa');
    }

    public function informeParticulares(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('informe_particulares.php');
    }

    public function informeIessPrueba(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('informe_iess_prueba.php');
    }

    public function generarConsolidadoIess(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('generar_consolidado_iess.php');
    }

    public function generarConsolidadoIsspol(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('generar_consolidado_isspol.php');
    }

    public function generarConsolidadoIssfa(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('generar_consolidado_issfa.php');
    }

    public function generarExcelIessLote(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('generar_excel_iess_lote.php');
    }

    public function ajaxDetalleFactura(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('ajax/ajax_detalle_factura.php');
    }

    public function ajaxEliminarFactura(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('components/eliminar_factura.php');
    }

    public function ajaxScrapearCodigoDerivacion(): void
    {
        $this->requireAuth();
        $this->includeLegacyView('ajax/scrapear_codigo_derivacion.php');
    }

    private function includeLegacyView(string $relativePath): void
    {
        $pdo = $this->pdo;
        $username = $_SESSION['username'] ?? 'Invitado';
        $path = BASE_PATH . '/modules/Billing/views/informes/' . ltrim($relativePath, '/');

        if (!is_file($path)) {
            http_response_code(404);
            echo 'Vista legacy no encontrada';
            return;
        }

        include $path;
    }

    /**
     * Genera y renderiza un informe consolidado/detallado para el grupo solicitado.
     */
    private function renderInformeGrupo(string $grupo): void
    {
        if (!isset($this->grupoConfigs[$grupo])) {
            http_response_code(404);
            echo 'Informe no disponible';
            return;
        }

        $this->requireAuth();

        $config = $this->grupoConfigs[$grupo];
        $formIdScrape = $_POST['form_id_scrape'] ?? $_GET['form_id'] ?? null;
        $hcNumberScrape = $_POST['hc_number_scrape'] ?? $_GET['hc_number'] ?? null;
        $scrapingOutput = null;

        if (isset($_POST['scrape_derivacion']) && $formIdScrape && $hcNumberScrape) {
            $script = BASE_PATH . '/scrapping/scrape_log_admision.py';
            $command = sprintf(
                '/usr/bin/python3 %s %s %s',
                escapeshellarg($script),
                escapeshellarg((string)$formIdScrape),
                escapeshellarg((string)$hcNumberScrape)
            );
            $scrapingOutput = shell_exec($command);
        }

        $filtros = [
            'modo' => 'consolidado',
            'billing_id' => $_GET['billing_id'] ?? null,
            'mes' => $_GET['mes'] ?? '',
            'apellido' => $_GET['apellido'] ?? '',
        ];

        $mesSeleccionado = $filtros['mes'];
        $facturas = $this->billingController->obtenerFacturasDisponibles($mesSeleccionado ?: null);

        $cacheDerivaciones = [];
        $grupos = [];
        foreach ($facturas as $factura) {
            $formId = $factura['form_id'];
            if (!isset($cacheDerivaciones[$formId])) {
                $cacheDerivaciones[$formId] = $this->billingController->obtenerDerivacionPorFormId($formId);
            }
            $derivacion = $cacheDerivaciones[$formId];
            $codigo = $derivacion['codigo_derivacion'] ?? $derivacion['cod_derivacion'] ?? null;
            $keyAgrupacion = $codigo ?: 'SIN_CODIGO';

            $grupos[$keyAgrupacion][] = [
                'factura' => $factura,
                'codigo' => $codigo,
                'form_id' => $formId,
                'tiene_codigo' => !empty($codigo),
            ];
        }

        $cachePorMes = [];
        $pacientesCache = [];
        $datosCache = [];
        if (!empty($mesSeleccionado)) {
            foreach ($facturas as $factura) {
                $fechaOrdenada = $factura['fecha_ordenada'] ?? null;
                $mes = $fechaOrdenada ? date('Y-m', strtotime($fechaOrdenada)) : '';
                if ($mes !== $mesSeleccionado) {
                    continue;
                }

                $hc = $factura['hc_number'];
                $formId = $factura['form_id'];

                if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
                    $paciente = $this->pacienteService->getPatientDetails($hc);
                    $cachePorMes[$mes]['pacientes'][$hc] = $paciente;
                    $pacientesCache[$hc] = $paciente;
                }

                if (!isset($cachePorMes[$mes]['datos'][$formId])) {
                    $datos = $this->billingController->obtenerDatos($formId);
                    $cachePorMes[$mes]['datos'][$formId] = $datos;
                    $datosCache[$formId] = $datos;
                }
            }
        }

        $billingIds = isset($filtros['billing_id']) && $filtros['billing_id'] !== ''
            ? array_filter(array_map('trim', explode(',', $filtros['billing_id'])))
            : [];

        $formIds = [];
        $datosFacturas = [];
        if (!empty($billingIds)) {
            $placeholders = implode(',', array_fill(0, count($billingIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, form_id FROM billing_main WHERE id IN ($placeholders)");
            $stmt->execute($billingIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $formId = $row['form_id'];
                $formIds[] = $formId;
                $datos = $this->billingController->obtenerDatos($formId);
                if ($datos) {
                    $datosFacturas[] = $datos;
                    $datosCache[$formId] = $datos;
                }
            }
        }

        $this->render('modules/Billing/views/informe_iess.php', [
            'pageTitle' => $config['titulo'],
            'scrapingOutput' => $scrapingOutput,
            'filtros' => $filtros,
            'mesSeleccionado' => $mesSeleccionado,
            'facturas' => $facturas,
            'grupos' => $grupos,
            'cachePorMes' => $cachePorMes,
            'cacheDerivaciones' => $cacheDerivaciones,
            'billingIds' => $billingIds,
            'formIds' => $formIds,
            'datosFacturas' => $datosFacturas,
            'pacienteService' => $this->pacienteService,
            'billingController' => $this->billingController,
            'pacientesCache' => $pacientesCache,
            'datosCache' => $datosCache,
            'grupoConfig' => $config,
            'requestQuery' => $_GET,
        ]);
    }
}
