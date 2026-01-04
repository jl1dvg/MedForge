<?php

namespace Modules\Billing\Controllers;

use Controllers\BillingController as LegacyBillingController;
use Core\BaseController;
use Models\SettingsModel;
use Modules\Pacientes\Services\PacienteService;
use PDO;
use RuntimeException;
use Throwable;

class InformesController extends BaseController
{
    /** @var LegacyBillingController */
    private $billingController;

    /** @var PacienteService */
    private $pacienteService;

    /** @var array<string, array> */
    private $grupoConfigs = [];

    private ?SettingsModel $settingsModel = null;

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
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
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
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
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
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
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
            'msp' => [
                'slug' => 'msp',
                'titulo' => 'Informe MSP',
                'basePath' => '/informes/msp',
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
                'afiliaciones' => ['msp'],
                'excelButtons' => [
                    [
                        'grupo' => 'MSP',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Obtener código de derivación',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes MSP',
                'enableApellidoFilter' => true,
            ],
        ];

        $this->hydrateConfigsFromSettings();
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

    public function informeMsp(): void
    {
        $this->renderInformeGrupo('msp');
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
        $scrapingOutput = null;
        $scrapingLimitMessage = '';

        $formIdScrapeRaw = $_POST['form_id_scrape'] ?? $_GET['form_id'] ?? null;
        $formIdsScrape = array_values(array_filter(array_map(
            'trim',
            is_array($formIdScrapeRaw)
                ? $formIdScrapeRaw
                : preg_split('/\s*,\s*/', (string) $formIdScrapeRaw)
        )));
        $maxScrapeBatch = 20;
        if (count($formIdsScrape) > $maxScrapeBatch) {
            $scrapingLimitMessage = "⚠️ Se limitaron las selecciones a los primeros {$maxScrapeBatch} registros para evitar saturar el servidor.";
            $formIdsScrape = array_slice($formIdsScrape, 0, $maxScrapeBatch);
        }
        $hcNumberScrapeRaw = $_POST['hc_number_scrape'] ?? $_GET['hc_number'] ?? null;
        $hcNumbersScrape = [];
        if (is_array($hcNumberScrapeRaw)) {
            $hcNumbersScrape = array_values(array_filter(array_map('trim', $hcNumberScrapeRaw)));
        } elseif (!empty($hcNumberScrapeRaw)) {
            $hcNumbersScrape = array_fill(0, max(count($formIdsScrape), 1), (string) $hcNumberScrapeRaw);
        }

        if (isset($_POST['scrape_derivacion']) && $formIdsScrape && $hcNumbersScrape) {
            $script = BASE_PATH . '/scrapping/scrape_log_admision.py';
            $outputs = [];

            // Alinear hc_numbers con form_ids en caso de envío por lotes
            if (count($hcNumbersScrape) === 1 && count($formIdsScrape) > 1) {
                $hcNumbersScrape = array_fill(0, count($formIdsScrape), $hcNumbersScrape[0]);
            }

            foreach ($formIdsScrape as $index => $formIdScrape) {
                $hcNumberScrape = $hcNumbersScrape[$index] ?? $hcNumbersScrape[0] ?? null;
                if (!$hcNumberScrape) {
                    continue;
                }

                $command = sprintf(
                    '/usr/bin/python3 %s %s %s',
                    escapeshellarg($script),
                    escapeshellarg((string) $formIdScrape),
                    escapeshellarg((string) $hcNumberScrape)
                );
                $outputs[] = shell_exec($command);
            }

            $outputs = array_filter($outputs, static fn($output) => $output !== null && $output !== '');
            if (count($outputs) === 1) {
                $scrapingOutput = reset($outputs);
            } elseif (!empty($outputs)) {
                $procedimientos = [];
                foreach ($outputs as $output) {
                    $partes = explode("📋 Procedimientos proyectados:", (string) $output);
                    if (isset($partes[1])) {
                        $procedimientos[] = trim($partes[1]);
                    }
                }

                $scrapingOutput = !empty($procedimientos)
                    ? "📋 Procedimientos proyectados:\n" . implode("\n", $procedimientos)
                    : implode("\n\n", $outputs);
            }

            if ($scrapingLimitMessage !== '') {
                $scrapingOutput = ($scrapingOutput !== null && $scrapingOutput !== '')
                    ? $scrapingLimitMessage . "\n" . $scrapingOutput
                    : $scrapingLimitMessage;
            }
        } elseif ($scrapingLimitMessage !== '') {
            $scrapingOutput = $scrapingLimitMessage;
        }

        $filtros = [
            'modo' => 'consolidado',
            'billing_id' => $_GET['billing_id'] ?? null,
            'mes' => $_GET['mes'] ?? '',
            'apellido' => $_GET['apellido'] ?? '',
            'hc_number' => $_GET['hc_number'] ?? '',
            'derivacion' => $_GET['derivacion'] ?? '',
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

    private function hydrateConfigsFromSettings(): void
    {
        $settings = $this->resolveSettingsModel();
        if (!($settings instanceof SettingsModel)) {
            return;
        }

        $keys = ['billing_informes_custom_groups'];
        foreach (array_keys($this->grupoConfigs) as $slug) {
            $keys = array_merge($keys, $this->buildOptionKeysForSlug($slug));
        }

        try {
            $options = $settings->getOptions($keys);
        } catch (Throwable $exception) {
            error_log('No fue posible cargar los ajustes de informes: ' . $exception->getMessage());
            return;
        }

        foreach ($this->grupoConfigs as $slug => &$config) {
            $this->applyOverridesToConfig($config, $options, $slug);
        }
        unset($config);

        $customGroups = $options['billing_informes_custom_groups'] ?? '';
        if (empty($customGroups)) {
            return;
        }

        $decoded = json_decode($customGroups, true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $customConfig) {
            if (!is_array($customConfig)) {
                continue;
            }
            $slug = $customConfig['slug'] ?? null;
            if (!$slug) {
                continue;
            }
            $slug = $this->sanitizeSlug($slug);
            if ($slug === '') {
                continue;
            }
            $this->grupoConfigs[$slug] = array_merge(
                [
                    'slug' => $slug,
                    'titulo' => 'Informe ' . strtoupper($slug),
                    'basePath' => '/informes/' . $slug,
                    'afiliaciones' => [],
                    'excelButtons' => [],
                    'scrapeButtonLabel' => '📋 Ver atenciones pendientes',
                    'consolidadoTitulo' => 'Consolidado mensual de pacientes',
                    'tableOptions' => [
                        'pageLength' => 25,
                        'defaultOrder' => 'fecha_ingreso_desc',
                    ],
                ],
                $customConfig
            );
        }
    }

    private function buildOptionKeysForSlug(string $slug): array
    {
        $prefix = 'billing_informes_' . $slug . '_';

        return [
            $prefix . 'title',
            $prefix . 'base_path',
            $prefix . 'scrape_label',
            $prefix . 'consolidado_title',
            $prefix . 'apellido_filter',
            $prefix . 'afiliaciones',
            $prefix . 'excel_buttons',
            $prefix . 'table_page_length',
            $prefix . 'table_order',
        ];
    }

    private function applyOverridesToConfig(array &$config, array $options, string $slug): void
    {
        $prefix = 'billing_informes_' . $slug . '_';

        if (!empty($options[$prefix . 'title'])) {
            $config['titulo'] = $options[$prefix . 'title'];
        }
        if (!empty($options[$prefix . 'base_path'])) {
            $config['basePath'] = '/' . ltrim($options[$prefix . 'base_path'], '/');
        }
        if (!empty($options[$prefix . 'scrape_label'])) {
            $config['scrapeButtonLabel'] = $options[$prefix . 'scrape_label'];
        }
        if (!empty($options[$prefix . 'consolidado_title'])) {
            $config['consolidadoTitulo'] = $options[$prefix . 'consolidado_title'];
        }
        if (isset($options[$prefix . 'apellido_filter'])) {
            $config['enableApellidoFilter'] = $this->castBooleanOption($options[$prefix . 'apellido_filter']);
        }
        if (!empty($options[$prefix . 'afiliaciones'])) {
            $config['afiliaciones'] = $this->parseLineSeparatedList($options[$prefix . 'afiliaciones']);
        }
        if (!empty($options[$prefix . 'excel_buttons'])) {
            $config['excelButtons'] = $this->parseExcelButtons($options[$prefix . 'excel_buttons'], $config['excelButtons']);
        }
        if (!empty($options[$prefix . 'table_page_length'])) {
            $pageLength = max(5, (int) $options[$prefix . 'table_page_length']);
            $config['tableOptions']['pageLength'] = $pageLength;
        }
        if (!empty($options[$prefix . 'table_order'])) {
            $config['tableOptions']['defaultOrder'] = $this->sanitizeOrder($options[$prefix . 'table_order']);
        }
    }

    private function sanitizeOrder(string $order): string
    {
        $allowed = [
            'fecha_ingreso_desc',
            'fecha_ingreso_asc',
            'nombre_asc',
            'nombre_desc',
            'monto_desc',
            'monto_asc',
        ];

        return in_array($order, $allowed, true) ? $order : 'fecha_ingreso_desc';
    }

    private function castBooleanOption($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $truthy = ['1', 1, 'true', 'on', 'yes'];

        return in_array($value, $truthy, true);
    }

    private function parseLineSeparatedList(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines, static fn($line) => $line !== ''));
    }

    private function parseExcelButtons(string $rawValue, array $fallback): array
    {
        $lines = $this->parseLineSeparatedList($rawValue);
        $buttons = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            $parts = array_pad($parts, 4, '');
            [$grupo, $label, $class, $icon] = $parts;
            if ($grupo === '') {
                continue;
            }
            $buttons[] = [
                'grupo' => $grupo,
                'label' => $label !== '' ? $label : 'Descargar Excel',
                'class' => $class !== '' ? $class : 'btn btn-success btn-lg me-2',
                'icon' => $icon,
            ];
        }

        return $buttons ?: $fallback;
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9_-]/', '-', $slug ?? '') ?? '';

        return trim($slug, '-');
    }

    private function resolveSettingsModel(): ?SettingsModel
    {
        if ($this->settingsModel instanceof SettingsModel) {
            return $this->settingsModel;
        }

        try {
            $this->settingsModel = new SettingsModel($this->pdo);
        } catch (RuntimeException $exception) {
            error_log('No se pudo inicializar SettingsModel: ' . $exception->getMessage());
            return null;
        }

        return $this->settingsModel;
    }
}
