<?php

namespace Modules\Reporting\Services;

use Controllers\ExamenesController;
use Controllers\SolicitudController;
use Helpers\ProtocoloHelper;
use Models\ProtocoloModel;
use Modules\Reporting\Controllers\ReportController;
use Modules\Reporting\Services\Definitions\SolicitudTemplateDefinitionInterface;
use Modules\Reporting\Services\Definitions\SolicitudTemplateRegistry;
use Modules\Reporting\Support\SolicitudDataFormatter;
use PDO;
use RuntimeException;

class ProtocolReportService
{
    private const PROTOCOL_PAGES = [
        'protocolo',
        '005',
        'medicamentos',
        'signos_vitales',
        'insumos',
        'saveqx',
    ];

    private const PROTOCOL_LANDSCAPE_PAGE = 'transanestesico';

    private PDO $db;
    private ProtocoloModel $protocoloModel;
    private ExamenesController|SolicitudController $solicitudController;
    private ReportController $reportController;
    private SolicitudTemplateRegistry $solicitudTemplateRegistry;

    public function __construct(
        PDO                        $db,
        ReportController           $reportController,
        ?ProtocoloModel            $protocoloModel = null,
        ExamenesController|SolicitudController|null $solicitudController = null,
        ?SolicitudTemplateRegistry $solicitudTemplateRegistry = null
    )
    {
        $this->db = $db;
        $this->reportController = $reportController;
        $this->protocoloModel = $protocoloModel ?? new ProtocoloModel($db);
        $this->solicitudController = $solicitudController ?? new ExamenesController($db);
        $this->solicitudTemplateRegistry = $solicitudTemplateRegistry ?? SolicitudTemplateRegistry::fromConfig();
    }

    /**
     * Retorna los datos del protocolo listos para las plantillas.
     *
     * @return array<string, mixed>
     */
    public function buildProtocolData(string $formId, string $hcNumber): array
    {
        $datos = $this->protocoloModel->obtenerProtocolo($formId, $hcNumber);

        if (!$datos) {
            throw new RuntimeException('No se encontró el protocolo.');
        }

        $fechaInicio = $datos['fecha_inicio'] ?? null;
        [$fechaBase] = $this->separarFechaHora($fechaInicio);
        [$anio, $mes, $dia] = $this->descomponerFecha($fechaBase);

        $datos['anio'] = $anio;
        $datos['mes'] = $mes;
        $datos['dia'] = $dia;
        $datos['fechaAno'] = $anio;
        $datos['fechaMes'] = $mes;
        $datos['fechaDia'] = $dia;

        $datos['edad'] = $this->calcularEdad($datos['fecha_nacimiento'] ?? null, $fechaBase);
        $datos['edadPaciente'] = $datos['edad'];

        $datos['nombre_procedimiento_proyectado'] = $this->protocoloModel->obtenerNombreProcedimientoProyectado(
            (string)($datos['procedimiento_proyectado'] ?? '')
        );
        $datos['codigos_concatenados'] = $this->protocoloModel->obtenerCodigosProcedimientos($datos['procedimientos'] ?? []);

        $datos['diagnosticos_previos'] = $this->formatearDiagnosticosPrevios(
            ProtocoloHelper::obtenerDiagnosticosPrevios($this->db, $hcNumber, $formId)
        );

        $datos = array_merge($datos, $this->resolverUsuarios([
            'cirujano_data' => $datos['cirujano_1'] ?? null,
            'cirujano2_data' => $datos['cirujano_2'] ?? null,
            'ayudante_data' => $datos['primer_ayudante'] ?? null,
            'anestesiologo_data' => $datos['anestesiologo'] ?? null,
            'ayudante_anestesia_data' => $datos['ayudante_anestesia'] ?? null,
        ]));

        $procedureId = $datos['procedimiento_id'] ?? null;
        if (empty($procedureId)) {
            $procedureId = ProtocoloHelper::obtenerIdProcedimiento($this->db, (string)($datos['membrete'] ?? ''));
        }
        $procedureId = $procedureId !== null ? (string)$procedureId : '';

        $datos['imagen_link'] = $procedureId !== ''
            ? ProtocoloHelper::mostrarImagenProcedimiento($this->db, $procedureId)
            : null;

        $diagnosesArray = json_decode((string)($datos['diagnosticos'] ?? '[]'), true) ?? [];
        $datos['diagnostic1'] = $diagnosesArray[0]['idDiagnostico'] ?? '';
        $datos['diagnostic2'] = $diagnosesArray[1]['idDiagnostico'] ?? '';
        $datos['diagnostic3'] = $diagnosesArray[2]['idDiagnostico'] ?? '';

        $datos['realizedProcedure'] = (string)($datos['membrete'] ?? '');
        $datos['codes_concatenados'] = (string)($datos['codigos_concatenados'] ?? '');
        $datos['mainSurgeon'] = (string)($datos['cirujano_1'] ?? '');
        $datos['assistantSurgeon1'] = (string)($datos['cirujano_2'] ?? '');
        $datos['ayudante'] = (string)($datos['primer_ayudante'] ?? '');

        $evo = $this->protocoloModel->obtenerEvolucion005($procedureId);

        $signos = ProtocoloHelper::obtenerSignosVitalesYEdad(
            $datos['edadPaciente'],
            trim(implode(', ', $datos['diagnosticos_previos'] ?? [])),
            $datos['realizedProcedure']
        );

        $datos['evolucion005'] = [
            'pre_evolucion' => !empty($evo['pre_evolucion']) ? ProtocoloHelper::procesarEvolucionConVariables($evo['pre_evolucion'], 70, $signos) : [],
            'pre_indicacion' => !empty($evo['pre_indicacion']) ? ProtocoloHelper::procesarEvolucionConVariables($evo['pre_indicacion'], 80, $signos) : [],
            'post_evolucion' => !empty($evo['post_evolucion']) ? ProtocoloHelper::procesarEvolucionConVariables($evo['post_evolucion'], 70, $signos) : [],
            'post_indicacion' => !empty($evo['post_indicacion']) ? ProtocoloHelper::procesarEvolucionConVariables($evo['post_indicacion'], 80, $signos) : [],
            'alta_evolucion' => !empty($evo['alta_evolucion']) ? ProtocoloHelper::procesarEvolucionConVariables($evo['alta_evolucion'], 70, $signos) : [],
            'alta_indicacion' => !empty($evo['alta_indicacion']) ? ProtocoloHelper::procesarEvolucionConVariables($evo['alta_indicacion'], 80, $signos) : [],
        ];

        // Variables planas para la vista 005.php
        $datos['preEvolucion'] = $datos['evolucion005']['pre_evolucion'];
        $datos['preIndicacion'] = $datos['evolucion005']['pre_indicacion'];
        $datos['postEvolucion'] = $datos['evolucion005']['post_evolucion'];
        $datos['postIndicacion'] = $datos['evolucion005']['post_indicacion'];
        $datos['altaEvolucion'] = $datos['evolucion005']['alta_evolucion'];
        $datos['altaIndicacion'] = $datos['evolucion005']['alta_indicacion'];

        [$horaInicioModificada, $horaFinModificada] = $this->ajustarHoras(
            $datos['hora_inicio'] ?? null,
            $datos['hora_fin'] ?? null
        );
        $datos['horaInicioModificada'] = $horaInicioModificada;
        $datos['horaFinModificada'] = $horaFinModificada;

        $medicamentosArray = $this->protocoloModel->obtenerMedicamentos(
            $procedureId,
            $formId,
            $hcNumber
        );
        $datos['medicamentos'] = ProtocoloHelper::procesarMedicamentos(
            $medicamentosArray,
            $horaInicioModificada,
            $datos['mainSurgeon'],
            (string)($datos['anestesiologo'] ?? ''),
            (string)($datos['ayudante_anestesia'] ?? '')
        );

        // Insumos pueden venir como JSON (string) o como arreglo ya procesado.
        $rawInsumos = $datos['insumos'] ?? null;
        if (is_array($rawInsumos) && !empty($rawInsumos)) {
            // Ya vienen procesados
            $datos['insumos'] = $rawInsumos;
        } elseif (is_string($rawInsumos)) {
            $trim = trim($rawInsumos);
            if ($trim !== '' && strtoupper($trim) !== 'NULL' && $trim !== '[]') {
                $datos['insumos'] = ProtocoloHelper::procesarInsumos($rawInsumos);
            } else {
                $datos['insumos'] = [];
            }
        } else {
            $datos['insumos'] = [];
        }

        [$totalHoras, $totalHorasConDescuento] = $this->calcularDuraciones(
            $datos['hora_inicio'] ?? null,
            $datos['hora_fin'] ?? null
        );
        $datos['totalHoras'] = $totalHoras;
        $datos['totalHorasConDescuento'] = $totalHorasConDescuento;

        return $datos;
    }

    /**
     * @return array{html: string, filename: string, css: string}
     */
    public function generateProtocolDocument(string $formId, string $hcNumber): array
    {
        $datos = $this->buildProtocolData($formId, $hcNumber);

        $html = $this->renderSegments(
            array_merge(self::PROTOCOL_PAGES, [self::PROTOCOL_LANDSCAPE_PAGE]),
            $datos,
            [self::PROTOCOL_LANDSCAPE_PAGE => 'L']
        );

        return [
            'html' => $html,
            'filename' => sprintf('protocolo_%s_%s.pdf', $formId, $hcNumber),
            'css' => $this->getStylesheetPath(),
        ];
    }

    /**
     * @return array{html: string, filename: string, css: string, orientation: string}|null
     */
    public function renderProtocolPage(string $identifier, string $formId, string $hcNumber): ?array
    {
        $datos = $this->buildProtocolData($formId, $hcNumber);
        $slug = $this->normalizarIdentificador($identifier);
        $html = $this->renderSegment($slug, $datos);

        if ($html === null) {
            return null;
        }

        return [
            'html' => $html,
            'filename' => sprintf('%s_%s_%s.pdf', $slug, $formId, $hcNumber),
            'css' => $this->getStylesheetPath(),
            'orientation' => $this->resolverOrientacion($slug),
        ];
    }

    /**
     * @return array{html: string, filename: string, css: string, orientation: string, mpdf: array<string, mixed>}
     */
    public function generateCoberturaDocument(string $formId, string $hcNumber): array
    {
        $datos = $this->buildCoberturaData($formId, $hcNumber);
        $definition = $this->resolveSolicitudTemplate($datos);

        $reportSlug = $definition->getReportSlug();
        if ($reportSlug !== null) {
            $appendViews = $definition->getAppendViews();
            $appendix = null;

            if ($appendViews !== []) {
                $appendHtml = $this->renderSegments($appendViews, $datos, $definition->getOrientations());

                if ($appendHtml !== '') {
                    $appendix = [
                        'html' => $appendHtml,
                        'css' => $definition->getCss() ?? $this->getStylesheetPath(),
                        'orientation' => $definition->getDefaultOrientation(),
                        'mpdf' => $definition->getMpdfOptions(),
                    ];
                }
            }

            return [
                'mode' => 'report',
                'slug' => $reportSlug,
                'data' => $datos,
                'filename' => $definition->buildFilename($formId, $hcNumber),
                'options' => $definition->getReportOptions(),
                'append' => $appendix,
            ];
        }

        $pages = $definition->getPages();

        if ($pages === []) {
            throw new RuntimeException(sprintf('La plantilla "%s" no tiene páginas configuradas.', $definition->getIdentifier()));
        }

        $html = $this->renderSegments($pages, $datos, $definition->getOrientations());

        return [
            'mode' => 'html',
            'html' => $html,
            'filename' => $definition->buildFilename($formId, $hcNumber),
            'css' => $definition->getCss() ?? $this->getStylesheetPath(),
            'orientation' => $definition->getDefaultOrientation(),
            'mpdf' => $definition->getMpdfOptions(),
        ];
    }

    /**
     * @param array<string, mixed>|null $datos
     * @return array<string, mixed>|null
     */
    public function generateCoberturaAppendixDocument(string $formId, string $hcNumber, ?array $datos = null): ?array
    {
        $datos = $datos ?? $this->buildCoberturaData($formId, $hcNumber);
        $definition = $this->resolveSolicitudTemplate($datos);

        $segments = $definition->getAppendViews();
        if ($segments === []) {
            $segments = $definition->getPages();
        }

        if ($segments === []) {
            $fallback = $this->solicitudTemplateRegistry->get('cobertura');
            if ($fallback !== null) {
                $definition = $fallback;
                $segments = $definition->getPages();
            }
        }

        if ($segments === []) {
            return null;
        }

        $html = $this->renderSegments($segments, $datos, $definition->getOrientations());

        if ($html === '') {
            return null;
        }

        return [
            'mode' => 'html',
            'html' => $html,
            'filename' => $definition->buildFilename($formId, $hcNumber),
            'css' => $definition->getCss() ?? $this->getStylesheetPath(),
            'orientation' => $definition->getDefaultOrientation(),
            'mpdf' => $definition->getMpdfOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCoberturaData(string $formId, string $hcNumber): array
    {
        $datos = $this->solicitudController->obtenerDatosParaVista($hcNumber, $formId);

        return SolicitudDataFormatter::enrich($datos, $formId, $hcNumber);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function resolveSolicitudTemplate(array $datos): SolicitudTemplateDefinitionInterface
    {
        $definition = $this->solicitudTemplateRegistry->resolve($datos);

        if ($definition === null) {
            $definition = $this->solicitudTemplateRegistry->get('cobertura');
        }

        if ($definition === null) {
            throw new RuntimeException('No existe una plantilla configurada para la aseguradora seleccionada.');
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverUsuarios(array $usuarios): array
    {
        $cache = [];
        $resultado = [];

        foreach ($usuarios as $clave => $nombre) {
            $nombreLimpio = is_string($nombre) ? trim($nombre) : '';

            if ($nombreLimpio === '') {
                $resultado[$clave] = null;
                continue;
            }

            if (!array_key_exists($nombreLimpio, $cache)) {
                $cache[$nombreLimpio] = ProtocoloHelper::buscarUsuarioPorNombre($this->db, $nombreLimpio);
            }

            $resultado[$clave] = $cache[$nombreLimpio];
        }

        return $resultado;
    }

    private function separarFechaHora(?string $fechaHora): array
    {
        if (empty($fechaHora)) {
            return [null, null];
        }

        $partes = explode(' ', $fechaHora, 2);

        return [$partes[0] ?? null, $partes[1] ?? null];
    }

    private function descomponerFecha(?string $fecha): array
    {
        if (empty($fecha)) {
            return ['', '', ''];
        }

        $partes = explode('-', $fecha);

        return [
            $partes[0] ?? '',
            $partes[1] ?? '',
            $partes[2] ?? '',
        ];
    }

    private function calcularEdad(?string $fechaNacimiento, ?string $fechaReferencia): ?int
    {
        if (empty($fechaNacimiento) || empty($fechaReferencia)) {
            return null;
        }

        try {
            $nacimiento = new \DateTime($fechaNacimiento);
            $referencia = new \DateTime($fechaReferencia);

            return $nacimiento->diff($referencia)->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatearDiagnosticosPrevios($diagnosticos): array
    {
        $lista = is_array($diagnosticos) ? $diagnosticos : [];
        $resultado = [];

        foreach ($lista as $diagnostico) {
            $cie = strtoupper(trim((string)($diagnostico['cie10'] ?? '')));
            $descripcion = strtoupper(trim((string)($diagnostico['descripcion'] ?? '')));
            $ciePad = str_pad($cie, 4, ' ', STR_PAD_RIGHT);
            $resultado[] = $ciePad . ' - ' . $descripcion;
        }

        while (count($resultado) < 3) {
            $resultado[] = '';
        }

        return array_slice($resultado, 0, 3);
    }

    private function ajustarHoras(?string $horaInicio, ?string $horaFin): array
    {
        $inicioModificado = null;
        $finModificado = null;

        if (!empty($horaInicio)) {
            try {
                $inicio = new \DateTime($horaInicio);
                $inicio->modify('-45 minutes');
                $inicioModificado = $inicio->format('H:i');
            } catch (\Exception $e) {
                $inicioModificado = null;
            }
        }

        if (!empty($horaFin)) {
            try {
                $fin = new \DateTime($horaFin);
                $fin->modify('+30 minutes');
                $finModificado = $fin->format('H:i');
            } catch (\Exception $e) {
                $finModificado = null;
            }
        }

        return [$inicioModificado, $finModificado];
    }

    private function calcularDuraciones(?string $horaInicio, ?string $horaFin): array
    {
        if (empty($horaInicio) || empty($horaFin)) {
            return ['No disponible', 'No disponible'];
        }

        try {
            $inicio = new \DateTime($horaInicio);
            $fin = new \DateTime($horaFin);
            $intervalo = $inicio->diff($fin);
            $total = sprintf('%dh %dmin', $intervalo->h, $intervalo->i);

            $fin->modify('-10 minutes');
            $intervaloReducido = $inicio->diff($fin);
            $conDescuento = sprintf('%dh %dmin', $intervaloReducido->h, $intervaloReducido->i);

            return [$total, $conDescuento];
        } catch (\Exception $e) {
            return ['No disponible', 'No disponible'];
        }
    }

    private function renderSegments(array $identificadores, array $datos, array $orientaciones = []): string
    {
        $html = '';

        foreach ($identificadores as $slug) {
            $segmento = $this->renderSegment($slug, $datos);

            if ($segmento === null) {
                continue;
            }

            if ($html !== '') {
                $orientacion = $orientaciones[$slug] ?? null;
                $atributo = $orientacion ? sprintf(' orientation="%s"', $orientacion) : '';
                $html .= '<pagebreak' . $atributo . '>';
            }

            $html .= $segmento;
        }

        return $html;
    }

    private function renderSegment(string $identifier, array $datos): ?string
    {
        $slug = $this->normalizarIdentificador($identifier);

        return $this->reportController->renderIfExists($slug, $datos, true);
    }

    private function normalizarIdentificador(string $identifier): string
    {
        $limpio = trim($identifier);

        if ($limpio === '') {
            return $limpio;
        }

        $limpio = str_replace('\\', '/', $limpio);
        $limpio = basename($limpio, '.php');

        return $limpio;
    }

    private function resolverOrientacion(string $slug): string
    {
        return $slug === self::PROTOCOL_LANDSCAPE_PAGE ? 'L' : 'P';
    }

    private function getStylesheetPath(): string
    {
        return dirname(__DIR__) . '/Templates/assets/pdf.css';
    }
}
