<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class SolicitudesPrefacturaService
{
    private const COBERTURA_MAIL_TO = 'cespinoza@cive.ec';
    private const COBERTURA_MAIL_CC = ['oespinoza@cive.ec'];

    private const TEMPLATE_RULES = [
        [
            'key' => 'msp_informe',
            'priority' => 100,
            'exact' => ['msp', 'ministerio de salud', 'salud publica', 'red publica'],
            'contains' => ['msp', 'ministerio de salud', 'salud publica', 'red publica', 'red publica integral', 'red publica integral de salud'],
        ],
        [
            'key' => 'isspol_informe',
            'priority' => 90,
            'exact' => ['isspol'],
            'contains' => ['isspol', 'policia', 'policia nacional', 'seguro policial'],
        ],
        [
            'key' => 'issfa_informe',
            'priority' => 80,
            'exact' => ['issfa'],
            'contains' => ['issfa', 'ffaa', 'fuerzas armadas'],
        ],
        [
            'key' => 'iess_cive',
            'priority' => 10,
            'exact' => [
                'contribuyente voluntario',
                'conyuge',
                'conyuge pensionista',
                'seguro campesino',
                'seguro general por montepio',
                'seguro general tiempo parcial',
                'iess',
                'hijos dependientes',
                'seguro campesino jubilado',
                'seguro general',
                'seguro general jubilado',
            ],
            'contains' => [
                'contribuyente voluntario',
                'conyuge',
                'conyuge pensionista',
                'seguro campesino',
                'seguro general por montepio',
                'seguro general tiempo parcial',
                'iess',
                'hijos dependientes',
                'seguro campesino jubilado',
                'seguro general',
                'seguro general jubilado',
            ],
        ],
    ];

    private PDO $db;

    /**
     * @var array<string,array<int,string>>
     */
    private array $columnsCache = [];

    /**
     * @var array<string,bool>
     */
    private array $tableExistsCache = [];

    private ?int $companyIdCache = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DB::connection()->getPdo();
    }

    /**
     * @return array<string,mixed>
     */
    public function buildPrefacturaViewData(string $hcNumber, string $formId): array
    {
        $solicitudId = $this->obtenerSolicitudIdPorFormHc($formId, $hcNumber);
        if ($solicitudId !== null && $solicitudId > 0) {
            $this->ensureDerivacionPreseleccionAuto($hcNumber, $formId, $solicitudId);
        }

        $derivacion = $this->resolveDerivacion($formId, $hcNumber, $solicitudId);
        $solicitud = $this->obtenerDatosYCirujanoSolicitud($formId, $hcNumber);
        $paciente = $this->getPatientDetails($hcNumber);
        $diagnostico = $this->obtenerDxDeSolicitud($formId);
        $consulta = $this->obtenerConsultaDeSolicitud($formId);

        $afiliacion = trim((string) ($solicitud['afiliacion'] ?? ''));
        $templateKey = $this->resolveTemplateKey($afiliacion);

        return [
            'derivacion' => $derivacion ?? [],
            'solicitud' => $solicitud,
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
            'coberturaTemplateKey' => $templateKey,
            'coberturaTemplateAvailable' => $templateKey !== null ? $this->hasEnabledTemplate($templateKey) : false,
            'coberturaMailLog' => $solicitudId !== null ? $this->fetchLatestCoberturaMailLog($solicitudId) : null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function resolveDerivacion(string $formId, string $hcNumber, ?int $solicitudId = null): ?array
    {
        $seleccion = $this->obtenerDerivacionPreseleccion($solicitudId, $formId, $hcNumber);
        $lookupFormId = trim((string) ($seleccion['derivacion_pedido_id'] ?? $formId));
        $lookupCodigo = trim((string) ($seleccion['derivacion_codigo'] ?? ''));
        $hasSelection = $lookupFormId !== '' && !empty($seleccion['derivacion_pedido_id']);
        $fallback = $this->buildDerivacionFallbackFromSelection(
            $seleccion,
            $lookupFormId !== '' ? $lookupFormId : $formId,
            $hcNumber
        );

        $localOptions = $this->resolveDerivacionPreseleccionFromLocal($hcNumber, $formId);
        $selectedLocal = $this->findDerivacionOptionByFormId($localOptions, $formId) ?? ($localOptions[0] ?? null);
        if (is_array($selectedLocal)) {
            $localLookupFormId = trim((string) ($selectedLocal['pedido_id_mas_antiguo'] ?? ''));
            if ($localLookupFormId !== '') {
                $localDerivacion = $this->obtenerDerivacionPorFormId($localLookupFormId);
                if ($localDerivacion !== null) {
                    return $localDerivacion;
                }
                $localDerivacionLegacy = $this->obtenerDerivacionLegacyPorFormHc($localLookupFormId, $hcNumber);
                if ($localDerivacionLegacy !== null) {
                    return $localDerivacionLegacy;
                }

                if ($fallback === null) {
                    $fallback = $this->buildDerivacionFallbackFromSelection([
                        'derivacion_codigo' => $selectedLocal['codigo_derivacion'] ?? null,
                        'derivacion_pedido_id' => $selectedLocal['pedido_id_mas_antiguo'] ?? null,
                        'derivacion_lateralidad' => $selectedLocal['lateralidad'] ?? null,
                        'derivacion_fecha_vigencia_sel' => $selectedLocal['fecha_vigencia'] ?? null,
                        'derivacion_prefactura' => $selectedLocal['prefactura'] ?? null,
                    ], $localLookupFormId, $hcNumber);
                }
            }

            if (($solicitudId ?? 0) > 0) {
                $this->guardarDerivacionPreseleccion((int) $solicitudId, $selectedLocal);
            }
        }

        if ($hasSelection) {
            $derivacion = $this->obtenerDerivacionPorFormId($lookupFormId);
            if ($derivacion !== null) {
                return $derivacion;
            }
        } else {
            $derivacion = $this->obtenerDerivacionPorFormId($formId);
            if ($derivacion !== null) {
                return $derivacion;
            }
        }
        $lookupByForm = $hasSelection ? $lookupFormId : $formId;
        if ($lookupByForm !== '' && $hcNumber !== '') {
            $derivacionLegacyByForm = $this->obtenerDerivacionLegacyPorFormHc($lookupByForm, $hcNumber);
            if ($derivacionLegacyByForm !== null) {
                return $derivacionLegacyByForm;
            }
        }

        if ($lookupCodigo !== '') {
            $derivacionByCode = $this->obtenerDerivacionPorCodigoHc($lookupCodigo, $hcNumber);
            if ($derivacionByCode !== null) {
                return $derivacionByCode;
            }
        }

        if ($fallback === null) {
            try {
                $preselection = $this->resolveDerivacionPreseleccion($hcNumber, $formId, $solicitudId);
                $selected = is_array($preselection['selected'] ?? null)
                    ? $this->normalizeDerivacionOption($preselection['selected'])
                    : null;

                if ($selected !== null) {
                    $selectedLookupFormId = trim((string) ($selected['pedido_id_mas_antiguo'] ?? $formId));
                    $selectedCodigo = trim((string) ($selected['codigo_derivacion'] ?? ''));
                    if ($selectedLookupFormId !== '') {
                        $selectedDerivacion = $this->obtenerDerivacionPorFormId($selectedLookupFormId);
                        if ($selectedDerivacion !== null) {
                            return $selectedDerivacion;
                        }
                        $selectedLegacyDerivacion = $this->obtenerDerivacionLegacyPorFormHc($selectedLookupFormId, $hcNumber);
                        if ($selectedLegacyDerivacion !== null) {
                            return $selectedLegacyDerivacion;
                        }

                        $fallback = $this->buildDerivacionFallbackFromSelection([
                            'derivacion_codigo' => $selected['codigo_derivacion'] ?? null,
                            'derivacion_pedido_id' => $selected['pedido_id_mas_antiguo'] ?? null,
                            'derivacion_lateralidad' => $selected['lateralidad'] ?? null,
                            'derivacion_fecha_vigencia_sel' => $selected['fecha_vigencia'] ?? null,
                            'derivacion_prefactura' => $selected['prefactura'] ?? null,
                        ], $selectedLookupFormId, $hcNumber);
                    }

                    if ($selectedCodigo !== '') {
                        $selectedDerivacionByCode = $this->obtenerDerivacionPorCodigoHc($selectedCodigo, $hcNumber);
                        if ($selectedDerivacionByCode !== null) {
                            return $selectedDerivacionByCode;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('solicitudes.prefactura.derivacion.preselection_fallback_error', [
                    'hc_number' => $hcNumber,
                    'form_id' => $formId,
                    'solicitud_id' => $solicitudId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return $fallback;
        }

        $lookup = $lookupFormId !== '' ? $lookupFormId : $formId;
        [$scrapePayload, $scrapeRawOutput, $exitCode] = $this->runCommandWithJson(sprintf(
            'python3 %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($lookup),
            escapeshellarg($hcNumber)
        ));
        if (is_array($scrapePayload)) {
            $scrapeCode = trim((string) ($scrapePayload['codigo_derivacion'] ?? $scrapePayload['cod_derivacion'] ?? ''));
            $scrapeArchivo = trim((string) ($scrapePayload['archivo_path'] ?? $scrapePayload['archivo_derivacion_path'] ?? ''));
            if ($scrapeCode !== '' || $scrapeArchivo !== '') {
                $this->upsertLegacyDerivacion($lookup, $hcNumber, [
                    'cod_derivacion' => $scrapeCode !== '' ? $scrapeCode : ($lookupCodigo !== '' ? $lookupCodigo : null),
                    'fecha_registro' => $scrapePayload['fecha_registro'] ?? null,
                    'fecha_vigencia' => $scrapePayload['fecha_vigencia'] ?? null,
                    'referido' => $scrapePayload['referido'] ?? null,
                    'diagnostico' => $scrapePayload['diagnostico'] ?? null,
                    'sede' => $scrapePayload['sede'] ?? null,
                    'parentesco' => $scrapePayload['parentesco'] ?? null,
                    'archivo_derivacion_path' => $scrapeArchivo !== '' ? $scrapeArchivo : null,
                ]);
            }
        }
        if ($exitCode !== 0) {
            $rawLines = $scrapeRawOutput !== '' ? preg_split('/\R+/', $scrapeRawOutput) : [];
            if (!is_array($rawLines)) {
                $rawLines = [];
            }
            Log::warning('solicitudes.prefactura.derivacion.scrape_nonzero', [
                'lookup_form_id' => $lookup,
                'hc_number' => $hcNumber,
                'exit_code' => $exitCode,
                'tail' => implode("\n", array_slice($rawLines, -5)),
            ]);
        }

        $derivacion = $this->obtenerDerivacionPorFormId($lookup);
        if ($derivacion !== null) {
            return $derivacion;
        }
        $legacyDerivacion = $this->obtenerDerivacionLegacyPorFormHc($lookup, $hcNumber);
        if ($legacyDerivacion !== null) {
            return $legacyDerivacion;
        }
        if ($lookupCodigo !== '') {
            $legacyDerivacionByCode = $this->obtenerDerivacionPorCodigoHc($lookupCodigo, $hcNumber);
            if ($legacyDerivacionByCode !== null) {
                return $legacyDerivacionByCode;
            }
        }

        if ($fallback !== null) {
            Log::info('solicitudes.prefactura.derivacion.using_preselection_fallback', [
                'lookup_form_id' => $lookup,
                'hc_number' => $hcNumber,
                'lookup_codigo' => $lookupCodigo !== '' ? $lookupCodigo : null,
                'solicitud_id' => $solicitudId,
                'source' => $fallback['source'] ?? 'fallback',
            ]);
        }

        return $fallback;
    }

    /**
     * @return array<string,mixed>
     */
    public function resolveDerivacionPreseleccion(string $hcNumber, string $formId, ?int $solicitudId = null): array
    {
        $seleccion = $this->obtenerDerivacionPreseleccion($solicitudId, $formId, $hcNumber);
        $selectedFromSolicitud = $this->normalizeDerivacionOption([
            'codigo_derivacion' => $seleccion['derivacion_codigo'] ?? null,
            'pedido_id_mas_antiguo' => $seleccion['derivacion_pedido_id'] ?? null,
            'lateralidad' => $seleccion['derivacion_lateralidad'] ?? null,
            'fecha_vigencia' => $seleccion['derivacion_fecha_vigencia_sel'] ?? null,
            'prefactura' => $seleccion['derivacion_prefactura'] ?? null,
        ]);
        if ($selectedFromSolicitud !== null) {
            return [
                'success' => true,
                'selected' => $selectedFromSolicitud,
                'needs_selection' => false,
                'options' => [],
                'source' => 'solicitud_preseleccion',
            ];
        }

        $localOptions = $this->resolveDerivacionPreseleccionFromLocal($hcNumber, $formId);
        if ($localOptions !== []) {
            $selectedLocal = $this->findDerivacionOptionByFormId($localOptions, $formId);
            if ($selectedLocal !== null && ($solicitudId ?? 0) > 0) {
                $this->guardarDerivacionPreseleccion((int) $solicitudId, $selectedLocal);
            }

            return [
                'success' => true,
                'selected' => $selectedLocal,
                'needs_selection' => $selectedLocal === null && count($localOptions) > 0,
                'options' => $localOptions,
                'source' => 'local_cache',
            ];
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            throw new RuntimeException('No se encontró el script de admisiones.');
        }

        [$parsed, $rawOutput, $exitCode] = $this->runCommandWithJson(sprintf(
            'python3 %s %s --group --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        ));

        if (!is_array($parsed)) {
            throw new RuntimeException('No se pudo interpretar la respuesta del scraper de admisiones.');
        }

        $grouped = $parsed['grouped'] ?? [];
        $options = [];
        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }
            $data = is_array($item['data'] ?? null) ? $item['data'] : [];
            $option = $this->normalizeDerivacionOption([
                'codigo_derivacion' => $item['codigo_derivacion'] ?? null,
                'pedido_id_mas_antiguo' => $item['pedido_id_mas_antiguo'] ?? null,
                'lateralidad' => $item['lateralidad'] ?? null,
                'fecha_vigencia' => $data['fecha_grupo'] ?? null,
                'prefactura' => $data['prefactura'] ?? null,
            ]);
            if ($option !== null) {
                $options[] = $option;
            }
        }
        $options = $this->dedupeDerivacionOptions($options);
        $selectedFromScraper = $this->findDerivacionOptionByFormId($options, $formId);
        if ($selectedFromScraper !== null && ($solicitudId ?? 0) > 0) {
            $this->guardarDerivacionPreseleccion((int) $solicitudId, $selectedFromScraper);
        }

        return [
            'success' => true,
            'selected' => $selectedFromScraper,
            'needs_selection' => $selectedFromScraper === null && count($options) > 0,
            'options' => $options,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
            'source' => 'scraper',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rescrapeDerivacion(string $formId, string $hcNumber, ?int $solicitudId = null): array
    {
        $seleccion = $this->obtenerDerivacionPreseleccion($solicitudId, $formId, $hcNumber);
        $lookupFormId = trim((string) ($seleccion['derivacion_pedido_id'] ?? $formId));

        $script = $this->projectRootPath() . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            throw new RuntimeException('No se encontró el script de scraping.');
        }

        [$parsed, $rawOutput, $exitCode] = $this->runCommandWithJson(sprintf(
            'python3 %s %s %s --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($lookupFormId),
            escapeshellarg($hcNumber)
        ));

        if (!is_array($parsed)) {
            throw new RuntimeException('No se pudo interpretar la respuesta del scraper.');
        }

        $codDerivacion = trim((string) ($parsed['codigo_derivacion'] ?? ''));
        $archivoPath = trim((string) ($parsed['archivo_path'] ?? ''));
        $saved = false;
        $derivacionId = null;

        if ($codDerivacion !== '' && $archivoPath !== '') {
            $derivacionId = $this->upsertLegacyDerivacion($lookupFormId, $hcNumber, [
                'cod_derivacion' => $codDerivacion,
                'fecha_registro' => $parsed['fecha_registro'] ?? null,
                'fecha_vigencia' => $parsed['fecha_vigencia'] ?? null,
                'referido' => $parsed['referido'] ?? null,
                'diagnostico' => $parsed['diagnostico'] ?? null,
                'sede' => $parsed['sede'] ?? null,
                'parentesco' => $parsed['parentesco'] ?? null,
                'archivo_derivacion_path' => $archivoPath,
            ]);
            $saved = $derivacionId !== null;
        }

        return [
            'success' => true,
            'saved' => $saved,
            'derivacion_id' => $derivacionId,
            'lookup_form_id' => $lookupFormId,
            'payload' => $parsed,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * @param array<string,mixed>|null $seleccion
     * @return array<string,mixed>|null
     */
    private function buildDerivacionFallbackFromSelection(?array $seleccion, string $lookupFormId, string $hcNumber): ?array
    {
        $selected = $this->normalizeDerivacionOption([
            'codigo_derivacion' => $seleccion['derivacion_codigo'] ?? null,
            'pedido_id_mas_antiguo' => $seleccion['derivacion_pedido_id'] ?? null,
            'lateralidad' => $seleccion['derivacion_lateralidad'] ?? null,
            'fecha_vigencia' => $seleccion['derivacion_fecha_vigencia_sel'] ?? null,
            'prefactura' => $seleccion['derivacion_prefactura'] ?? null,
        ]);
        if ($selected === null) {
            return null;
        }

        return [
            'derivacion_id' => null,
            'id' => null,
            'cod_derivacion' => $selected['codigo_derivacion'],
            'codigo_derivacion' => $selected['codigo_derivacion'],
            'form_id' => $lookupFormId !== '' ? $lookupFormId : null,
            'hc_number' => $hcNumber !== '' ? $hcNumber : null,
            'fecha_creacion' => null,
            'fecha_registro' => null,
            'fecha_vigencia' => $selected['fecha_vigencia'] ?? null,
            'referido' => null,
            'diagnostico' => null,
            'sede' => null,
            'parentesco' => null,
            'archivo_derivacion_path' => null,
            'lateralidad' => $selected['lateralidad'] ?? null,
            'prefactura' => $selected['prefactura'] ?? null,
            'source' => 'preseleccion_fallback',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function resolveDerivacionPreseleccionFromLocal(string $hcNumber, string $formId): array
    {
        $options = [];

        if (
            $this->hasTable('solicitud_procedimiento')
            && $this->hasColumn('solicitud_procedimiento', 'hc_number')
            && $this->hasColumn('solicitud_procedimiento', 'derivacion_codigo')
            && $this->hasColumn('solicitud_procedimiento', 'derivacion_pedido_id')
        ) {
            $lateralidadExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_lateralidad')
                ? 'sp.derivacion_lateralidad'
                : 'NULL';
            $vigenciaExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_fecha_vigencia_sel')
                ? 'sp.derivacion_fecha_vigencia_sel'
                : 'NULL';
            $prefacturaExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_prefactura')
                ? 'sp.derivacion_prefactura'
                : 'NULL';

            $stmt = $this->db->prepare(
                sprintf(
                    'SELECT
                        sp.derivacion_codigo AS codigo_derivacion,
                        sp.derivacion_pedido_id AS pedido_id_mas_antiguo,
                        %s AS lateralidad,
                        %s AS fecha_vigencia,
                        %s AS prefactura
                     FROM solicitud_procedimiento sp
                     WHERE sp.hc_number = :hc_number
                       AND sp.derivacion_codigo IS NOT NULL
                       AND sp.derivacion_codigo <> \'\'
                       AND sp.derivacion_pedido_id IS NOT NULL
                       AND sp.derivacion_pedido_id <> \'\'
                     ORDER BY sp.id DESC
                     LIMIT 80',
                    $lateralidadExpr,
                    $vigenciaExpr,
                    $prefacturaExpr,
                )
            );
            $stmt->execute([':hc_number' => $hcNumber]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $option = $this->normalizeDerivacionOption($row);
                if ($option !== null) {
                    $options[] = $option;
                }
            }
        }

        if (
            $this->hasTable('derivaciones_form_id')
            && $this->hasColumn('derivaciones_form_id', 'hc_number')
            && $this->hasColumn('derivaciones_form_id', 'form_id')
            && $this->hasColumn('derivaciones_form_id', 'cod_derivacion')
        ) {
            $stmt = $this->db->prepare(
                'SELECT
                    d.cod_derivacion AS codigo_derivacion,
                    d.form_id AS pedido_id_mas_antiguo,
                    NULL AS lateralidad,
                    d.fecha_vigencia AS fecha_vigencia,
                    NULL AS prefactura
                 FROM derivaciones_form_id d
                 WHERE d.hc_number = :hc_number
                   AND d.cod_derivacion IS NOT NULL
                   AND d.cod_derivacion <> \'\'
                   AND d.form_id IS NOT NULL
                   AND d.form_id <> \'\'
                 ORDER BY d.id DESC
                 LIMIT 80'
            );
            $stmt->execute([':hc_number' => $hcNumber]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $option = $this->normalizeDerivacionOption($row);
                if ($option !== null) {
                    $options[] = $option;
                }
            }
        }

        if (
            $this->hasTable('derivaciones_forms')
            && $this->hasTable('derivaciones_referral_forms')
            && $this->hasTable('derivaciones_referrals')
            && $this->hasColumn('derivaciones_forms', 'hc_number')
            && $this->hasColumn('derivaciones_forms', 'iess_form_id')
            && $this->hasColumn('derivaciones_referral_forms', 'form_id')
            && $this->hasColumn('derivaciones_referral_forms', 'referral_id')
            && $this->hasColumn('derivaciones_referrals', 'referral_code')
        ) {
            $stmt = $this->db->prepare(
                'SELECT
                    r.referral_code AS codigo_derivacion,
                    f.iess_form_id AS pedido_id_mas_antiguo,
                    NULL AS lateralidad,
                    COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                    NULL AS prefactura
                 FROM derivaciones_forms f
                 INNER JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
                 INNER JOIN derivaciones_referrals r ON r.id = rf.referral_id
                 WHERE f.hc_number = :hc_number
                   AND f.iess_form_id IS NOT NULL
                   AND f.iess_form_id <> \'\'
                   AND r.referral_code IS NOT NULL
                   AND r.referral_code <> \'\'
                 ORDER BY f.id DESC
                 LIMIT 80'
            );
            $stmt->execute([':hc_number' => $hcNumber]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $option = $this->normalizeDerivacionOption($row);
                if ($option !== null) {
                    $options[] = $option;
                }
            }
        }

        $options = $this->dedupeDerivacionOptions($options);
        $matching = $this->findDerivacionOptionByFormId($options, $formId);
        if ($matching !== null) {
            usort($options, static function (array $a, array $b) use ($formId): int {
                $aMatch = trim((string) ($a['pedido_id_mas_antiguo'] ?? '')) === $formId ? 1 : 0;
                $bMatch = trim((string) ($b['pedido_id_mas_antiguo'] ?? '')) === $formId ? 1 : 0;
                return $bMatch <=> $aMatch;
            });
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $option
     * @return array<string,mixed>|null
     */
    private function normalizeDerivacionOption(array $option): ?array
    {
        $codigo = trim((string) ($option['codigo_derivacion'] ?? ''));
        $pedido = trim((string) ($option['pedido_id_mas_antiguo'] ?? ''));
        if ($codigo === '' || $pedido === '') {
            return null;
        }

        return [
            'codigo_derivacion' => $codigo,
            'pedido_id_mas_antiguo' => $pedido,
            'lateralidad' => $this->nullableString($option['lateralidad'] ?? null),
            'fecha_vigencia' => $this->nullableString($option['fecha_vigencia'] ?? null),
            'prefactura' => $this->nullableString($option['prefactura'] ?? null),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @return array<int,array<string,mixed>>
     */
    private function dedupeDerivacionOptions(array $options): array
    {
        $seen = [];
        $deduped = [];

        foreach ($options as $option) {
            $normalized = $this->normalizeDerivacionOption($option);
            if ($normalized === null) {
                continue;
            }

            $key = $normalized['codigo_derivacion'] . '|' . $normalized['pedido_id_mas_antiguo'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $normalized;
        }

        return $deduped;
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @return array<string,mixed>|null
     */
    private function findDerivacionOptionByFormId(array $options, string $formId): ?array
    {
        $needle = trim($formId);
        if ($needle === '') {
            return null;
        }

        foreach ($options as $option) {
            if (trim((string) ($option['pedido_id_mas_antiguo'] ?? '')) === $needle) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function sendCoberturaMail(array $payload, ?UploadedFile $attachment, ?int $currentUserId): array
    {
        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $toRaw = trim((string) ($payload['to'] ?? ''));
        $ccRaw = trim((string) ($payload['cc'] ?? ''));
        $isHtml = filter_var($payload['is_html'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $solicitudId = isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : null;
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $afiliacion = trim((string) ($payload['afiliacion'] ?? ''));
        $templateKey = trim((string) ($payload['template_key'] ?? ''));
        $derivacionPdf = trim((string) ($payload['derivacion_pdf'] ?? ''));

        $solicitudId = $this->resolveSolicitudIdForMail($solicitudId, $formId, $hcNumber);

        if ($subject === '' || $body === '') {
            throw new RuntimeException('Asunto y mensaje son obligatorios', 422);
        }

        $toList = $this->parseCoberturaEmails($toRaw);
        $ccList = $this->parseCoberturaEmails($ccRaw);
        if ($toList === []) {
            $toList = [self::COBERTURA_MAIL_TO];
        }
        $toList = array_values(array_unique($toList));
        $ccList = array_values(array_unique(array_merge($ccList, self::COBERTURA_MAIL_CC)));

        $mailConfig = $this->resolveMailConfigForContext('solicitudes');
        $sendResult = $this->dispatchCoberturaMail(
            $mailConfig,
            $toList,
            $ccList,
            $subject,
            $body,
            $isHtml,
            $attachment
        );

        $sentAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $mailLogPayload = [
            'solicitud_id' => $solicitudId ?: null,
            'form_id' => $formId !== '' ? $formId : null,
            'hc_number' => $hcNumber !== '' ? $hcNumber : null,
            'afiliacion' => $afiliacion !== '' ? $afiliacion : null,
            'template_key' => $templateKey !== '' ? $templateKey : null,
            'to_emails' => implode(', ', $toList),
            'cc_emails' => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject' => $subject,
            'body_text' => $this->formatCoberturaMailBodyText($body, $isHtml),
            'body_html' => $isHtml ? $body : null,
            'attachment_path' => null,
            'attachment_name' => $attachment?->isValid() ? ($attachment->getClientOriginalName() ?: null) : null,
            'attachment_size' => $attachment?->isValid() ? $attachment->getSize() : null,
            'sent_by_user_id' => $currentUserId,
            'sent_at' => $sentAt,
        ];

        if (($sendResult['success'] ?? false) !== true) {
            $mailLogPayload['status'] = 'failed';
            $mailLogPayload['error_message'] = $sendResult['error'] ?? 'No se pudo enviar el correo';
            $this->safeInsertCoberturaMailLog($mailLogPayload);
            throw new RuntimeException((string) ($sendResult['error'] ?? 'No se pudo enviar el correo de cobertura'), 500);
        }

        $mailLogPayload['status'] = 'sent';
        $mailLogPayload['error_message'] = null;
        $mailLogId = $this->safeInsertCoberturaMailLog($mailLogPayload);

        if (($solicitudId ?? 0) > 0) {
            $this->safeRegisterCoberturaCrmTrail(
                (int) $solicitudId,
                $toList,
                $ccList,
                $subject,
                $templateKey,
                $derivacionPdf,
                $mailLogId,
                $currentUserId
            );
        }

        $sentByName = null;
        if (($mailLogId ?? 0) > 0) {
            $mailLog = $this->fetchCoberturaMailLogById((int) $mailLogId);
            if (is_array($mailLog) && isset($mailLog['sent_by_name'])) {
                $sentByName = $this->nullableString($mailLog['sent_by_name']);
                $sentAt = $this->nullableString($mailLog['sent_at']) ?? $sentAt;
            }
        }

        return [
            'success' => true,
            'ok' => true,
            'mail_log_id' => $mailLogId,
            'sent_at' => $sentAt,
            'sent_by' => $currentUserId,
            'sent_by_name' => $sentByName,
            'template_key' => $templateKey !== '' ? $templateKey : null,
        ];
    }

    /**
     * @return string[]
     */
    private function parseCoberturaEmails(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $candidates = preg_split('/[;,]+/', $raw) ?: [];
        $emails = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = strtolower($candidate);
        }

        return array_values(array_unique($emails));
    }

    private function formatCoberturaMailBodyText(string $body, bool $isHtml): string
    {
        if (!$isHtml) {
            return $body;
        }

        $text = trim(strip_tags($body));
        if ($text === '') {
            return '';
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function resolveSolicitudIdForMail(?int $solicitudId, string $formId, string $hcNumber): ?int
    {
        if ($formId !== '' && $hcNumber !== '') {
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM solicitud_procedimiento
                 WHERE form_id = :form_id AND hc_number = :hc_number
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':form_id' => $formId,
                ':hc_number' => $hcNumber,
            ]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                return (int) $resolved;
            }
        }

        if (($solicitudId ?? 0) > 0) {
            return $solicitudId;
        }

        if ($formId !== '' && ctype_digit($formId)) {
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM solicitud_procedimiento
                 WHERE form_id = :form_id
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([':form_id' => $formId]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                return (int) $resolved;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $config
     * @param string[] $toList
     * @param string[] $ccList
     * @return array{success:bool,error?:string}
     */
    private function dispatchCoberturaMail(
        array $config,
        array $toList,
        array $ccList,
        string $subject,
        string $body,
        bool $isHtml,
        ?UploadedFile $attachment
    ): array {
        if (!$this->isMailConfigValid($config)) {
            return ['success' => false, 'error' => 'SMTP no configurado'];
        }

        try {
            $transport = Transport::fromDsn($this->buildSmtpDsn($config));
            $mailer = new Mailer($transport);

            $fromAddress = trim((string) ($config['from_address'] ?? ''));
            $fromName = trim((string) ($config['from_name'] ?? ''));
            $replyToAddress = trim((string) ($config['reply_to_address'] ?? ''));
            $replyToName = trim((string) ($config['reply_to_name'] ?? ''));

            $email = new Email();
            $email->from(new Address($fromAddress, $fromName !== '' ? $fromName : $fromAddress));
            foreach ($toList as $recipient) {
                $email->addTo(new Address($recipient));
            }
            foreach ($ccList as $cc) {
                $email->addCc(new Address($cc));
            }
            if ($replyToAddress !== '') {
                $email->replyTo(new Address($replyToAddress, $replyToName !== '' ? $replyToName : $fromName));
            }

            $email->subject($subject !== '' ? $subject : 'Actualización de su atención');
            if ($isHtml) {
                $email->html($this->buildMailBodyFromHtml($body, $config));
                $email->text(trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            } else {
                $email->html($this->buildMailBodyFromText($body, $config));
                $email->text(trim($body));
            }

            if ($attachment && $attachment->isValid()) {
                $realPath = $attachment->getRealPath();
                if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
                    $email->attachFromPath(
                        $realPath,
                        $attachment->getClientOriginalName() ?: null,
                        $attachment->getClientMimeType() ?: null
                    );
                }
            }

            $mailer->send($email);

            return ['success' => true];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    private function buildMailBodyFromText(string $content, array $config): string
    {
        $parts = [];

        $header = trim((string) ($config['header'] ?? ''));
        if ($header !== '') {
            $parts[] = $header;
        }

        $parts[] = '<p>' . nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';

        $signature = trim((string) ($config['signature'] ?? ''));
        if ($signature !== '') {
            $parts[] = '<p>' . $signature . '</p>';
        }

        $footer = trim((string) ($config['footer'] ?? ''));
        if ($footer !== '') {
            $parts[] = $footer;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function buildMailBodyFromHtml(string $html, array $config): string
    {
        $parts = [];

        $header = trim((string) ($config['header'] ?? ''));
        if ($header !== '') {
            $parts[] = $header;
        }

        $parts[] = $html;

        $signature = trim((string) ($config['signature'] ?? ''));
        if ($signature !== '') {
            $parts[] = '<p>' . $signature . '</p>';
        }

        $footer = trim((string) ($config['footer'] ?? ''));
        if ($footer !== '') {
            $parts[] = $footer;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function buildSmtpDsn(array $config): string
    {
        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 0);
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $encryption = trim((string) ($config['encryption'] ?? ''));

        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
        $auth = '';
        if ($username !== '' || $password !== '') {
            $auth = rawurlencode($username) . ':' . rawurlencode($password) . '@';
        }

        $query = [];
        if ($encryption === 'tls') {
            $query['encryption'] = 'tls';
        }

        $timeout = (int) ($config['timeout'] ?? 0);
        if ($timeout > 0) {
            $query['timeout'] = (string) $timeout;
        }

        if (($config['allow_self_signed'] ?? false) === true) {
            $query['verify_peer'] = '0';
            $query['verify_peer_name'] = '0';
        }

        $queryString = $query !== []
            ? ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986))
            : '';

        return sprintf('%s://%s%s:%d%s', $scheme, $auth, $host, $port, $queryString);
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveMailConfigForContext(string $context): array
    {
        $profileConfig = $this->loadMailProfileConfigForContext($context);
        if ($profileConfig !== null && $this->isMailConfigValid($profileConfig)) {
            return $profileConfig;
        }

        $settingsConfig = $this->loadMailConfigFromSettings();
        if ($this->isMailConfigValid($settingsConfig)) {
            return $settingsConfig;
        }

        return [
            'host' => trim((string) config('mail.host', '')),
            'port' => (int) config('mail.port', 0),
            'encryption' => $this->normalizeMailEncryption((string) config('mail.encryption', '')),
            'username' => trim((string) config('mail.username', '')),
            'password' => (string) config('mail.password', ''),
            'from_address' => trim((string) config('mail.from.address', '')),
            'from_name' => trim((string) config('mail.from.name', '')),
            'reply_to_address' => '',
            'reply_to_name' => '',
            'header' => '',
            'footer' => '',
            'signature' => '',
            'timeout' => 15,
            'allow_self_signed' => false,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadMailProfileConfigForContext(string $context): ?array
    {
        if (!$this->hasTable('mail_profile_assignments') || !$this->hasTable('mail_profiles')) {
            return null;
        }

        $assignmentStmt = $this->db->prepare(
            'SELECT profile_slug
             FROM mail_profile_assignments
             WHERE context = :context
             LIMIT 1'
        );
        $assignmentStmt->execute([':context' => strtolower(trim($context))]);
        $profileSlug = $assignmentStmt->fetchColumn();
        if (!is_string($profileSlug) || trim($profileSlug) === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM mail_profiles
             WHERE slug = :slug AND active = 1
             LIMIT 1'
        );
        $stmt->execute([':slug' => trim($profileSlug)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'host' => trim((string) ($row['smtp_host'] ?? '')),
            'port' => (int) ($row['smtp_port'] ?? 0),
            'encryption' => $this->normalizeMailEncryption((string) ($row['smtp_encryption'] ?? '')),
            'username' => trim((string) ($row['smtp_username'] ?? '')),
            'password' => (string) ($row['smtp_password'] ?? ''),
            'from_address' => trim((string) ($row['from_address'] ?? '')),
            'from_name' => trim((string) ($row['from_name'] ?? '')),
            'reply_to_address' => trim((string) ($row['reply_to_address'] ?? '')),
            'reply_to_name' => trim((string) ($row['reply_to_name'] ?? '')),
            'header' => trim((string) ($row['header'] ?? '')),
            'footer' => trim((string) ($row['footer'] ?? '')),
            'signature' => trim((string) ($row['signature'] ?? '')),
            'timeout' => max(1, (int) ($row['smtp_timeout_seconds'] ?? 15)),
            'allow_self_signed' => filter_var($row['smtp_allow_self_signed'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadMailConfigFromSettings(): array
    {
        $keys = [
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'smtp_email',
            'email_from_name',
            'email_from_address',
            'email_reply_to_address',
            'email_reply_to_name',
            'email_header',
            'email_footer',
            'email_signature',
            'smtp_timeout_seconds',
            'smtp_allow_self_signed',
        ];

        $options = $this->queryAppSettings($keys);

        $fromAddress = trim((string) ($options['email_from_address'] ?? ''));
        if ($fromAddress === '') {
            $fromAddress = trim((string) ($options['smtp_email'] ?? ''));
        }

        return [
            'host' => trim((string) ($options['smtp_host'] ?? '')),
            'port' => (int) ($options['smtp_port'] ?? 0),
            'encryption' => $this->normalizeMailEncryption((string) ($options['smtp_encryption'] ?? '')),
            'username' => trim((string) ($options['smtp_username'] ?? '')),
            'password' => (string) ($options['smtp_password'] ?? ''),
            'from_address' => $fromAddress,
            'from_name' => trim((string) ($options['email_from_name'] ?? '')),
            'reply_to_address' => trim((string) ($options['email_reply_to_address'] ?? '')),
            'reply_to_name' => trim((string) ($options['email_reply_to_name'] ?? '')),
            'header' => trim((string) ($options['email_header'] ?? '')),
            'footer' => trim((string) ($options['email_footer'] ?? '')),
            'signature' => trim((string) ($options['email_signature'] ?? '')),
            'timeout' => max(1, (int) ($options['smtp_timeout_seconds'] ?? 15)),
            'allow_self_signed' => filter_var($options['smtp_allow_self_signed'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param string[] $keys
     * @return array<string,string|null>
     */
    private function queryAppSettings(array $keys): array
    {
        if ($keys === [] || !$this->hasTable('app_settings')) {
            return [];
        }

        if (!$this->hasColumn('app_settings', 'name') || !$this->hasColumn('app_settings', 'value')) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($keys) as $index => $key) {
            $placeholder = ':k' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $key;
        }

        $stmt = $this->db->prepare(
            'SELECT name, value
             FROM app_settings
             WHERE name IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $options = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $options[$name] = isset($row['value']) ? (string) $row['value'] : null;
        }

        return $options;
    }

    private function normalizeMailEncryption(string $value): string
    {
        $normalized = strtolower(trim($value));
        return match ($normalized) {
            'ssl', 'smtps' => 'ssl',
            'tls', 'starttls' => 'tls',
            default => '',
        };
    }

    /**
     * @param array<string,mixed> $config
     */
    private function isMailConfigValid(array $config): bool
    {
        return trim((string) ($config['host'] ?? '')) !== ''
            && (int) ($config['port'] ?? 0) > 0
            && trim((string) ($config['from_address'] ?? '')) !== '';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function safeInsertCoberturaMailLog(array $payload): ?int
    {
        try {
            return $this->insertCoberturaMailLog($payload);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function insertCoberturaMailLog(array $payload): ?int
    {
        if (!$this->hasTable('solicitud_mail_log')) {
            return null;
        }

        $columns = $this->tableColumns('solicitud_mail_log');
        if ($columns === []) {
            return null;
        }

        $data = [];
        $now = date('Y-m-d H:i:s');
        $allowed = [
            'solicitud_id',
            'form_id',
            'hc_number',
            'afiliacion',
            'template_key',
            'to_emails',
            'cc_emails',
            'subject',
            'body_text',
            'body_html',
            'attachment_path',
            'attachment_name',
            'attachment_size',
            'sent_by_user_id',
            'status',
            'error_message',
            'sent_at',
        ];

        foreach ($allowed as $column) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $data[$column] = $payload[$column] ?? null;
        }

        if (in_array('created_at', $columns, true) && !array_key_exists('created_at', $data)) {
            $data['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true) && !array_key_exists('updated_at', $data)) {
            $data['updated_at'] = $now;
        }

        if ($data === []) {
            return null;
        }

        $columnSql = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', array_keys($data)));
        $placeholderSql = implode(', ', array_map(static fn(string $column): string => ':' . $column, array_keys($data)));
        $stmt = $this->db->prepare('INSERT INTO solicitud_mail_log (' . $columnSql . ') VALUES (' . $placeholderSql . ')');
        foreach ($data as $column => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $column, $value, PDO::PARAM_INT);
            } elseif ($value === null) {
                $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':' . $column, (string) $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        $insertId = (int) $this->db->lastInsertId();
        return $insertId > 0 ? $insertId : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCoberturaMailLogById(int $mailLogId): ?array
    {
        if ($mailLogId <= 0 || !$this->hasTable('solicitud_mail_log')) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT sml.id, sml.sent_at, u.nombre AS sent_by_name
             FROM solicitud_mail_log sml
             LEFT JOIN users u ON u.id = sml.sent_by_user_id
             WHERE sml.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $mailLogId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param string[] $toList
     * @param string[] $ccList
     */
    private function safeRegisterCoberturaCrmTrail(
        int $solicitudId,
        array $toList,
        array $ccList,
        string $subject,
        string $templateKey,
        string $derivacionPdf,
        ?int $mailLogId,
        ?int $currentUserId
    ): void {
        try {
            $this->registerCoberturaCrmTrail(
                $solicitudId,
                $toList,
                $ccList,
                $subject,
                $templateKey,
                $derivacionPdf,
                $mailLogId,
                $currentUserId
            );
        } catch (Throwable) {
            // no-op
        }
    }

    /**
     * @param string[] $toList
     * @param string[] $ccList
     */
    private function registerCoberturaCrmTrail(
        int $solicitudId,
        array $toList,
        array $ccList,
        string $subject,
        string $templateKey,
        string $derivacionPdf,
        ?int $mailLogId,
        ?int $currentUserId
    ): void {
        $notaLineas = [
            'Cobertura solicitada por correo',
            'Para: ' . implode(', ', $toList),
        ];
        if ($ccList !== []) {
            $notaLineas[] = 'CC: ' . implode(', ', $ccList);
        }
        $notaLineas[] = 'Asunto: ' . $subject;
        if ($templateKey !== '') {
            $notaLineas[] = 'Plantilla: ' . $templateKey;
        }
        if ($derivacionPdf !== '') {
            $notaLineas[] = 'PDF derivación: ' . $derivacionPdf;
        }

        $this->insertCoberturaCrmNota($solicitudId, implode("\n", $notaLineas), $currentUserId);
        $this->upsertCoberturaCrmTask($solicitudId, $currentUserId, $mailLogId, $templateKey);
    }

    private function insertCoberturaCrmNota(int $solicitudId, string $nota, ?int $autorId): void
    {
        if (!$this->hasTable('solicitud_crm_notas')) {
            return;
        }

        $columns = $this->tableColumns('solicitud_crm_notas');
        if ($columns === [] || !in_array('solicitud_id', $columns, true) || !in_array('nota', $columns, true)) {
            return;
        }

        $data = [
            'solicitud_id' => $solicitudId,
            'nota' => trim(strip_tags($nota)),
        ];
        if (in_array('autor_id', $columns, true)) {
            $data['autor_id'] = $autorId;
        }
        if (in_array('created_at', $columns, true)) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        $this->insertDynamicRow('solicitud_crm_notas', $data);
    }

    private function upsertCoberturaCrmTask(int $solicitudId, ?int $currentUserId, ?int $mailLogId, string $templateKey): void
    {
        if (!$this->hasTable('crm_tasks')) {
            return;
        }

        $columns = $this->tableColumns('crm_tasks');
        if ($columns === []) {
            return;
        }

        $taskKey = 'solicitud:' . $solicitudId . ':kanban:cobertura-mail';
        $now = date('Y-m-d H:i:s');
        $companyId = in_array('company_id', $columns, true) ? $this->resolveCompanyId() : null;
        $detail = $this->fetchCrmDetalleRow($solicitudId);

        $metadata = array_filter([
            'task_key' => $taskKey,
            'context' => 'cobertura_mail',
            'template_key' => $templateKey !== '' ? $templateKey : null,
            'mail_log_id' => $mailLogId,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
        $metadataJson = $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        $whereParts = [];
        $bindings = [];
        if (in_array('source_ref_id', $columns, true)) {
            $whereParts[] = 'source_ref_id = :source_ref_id';
            $bindings[':source_ref_id'] = (string) $solicitudId;
        }
        if (in_array('source_module', $columns, true)) {
            $whereParts[] = 'source_module = :source_module';
            $bindings[':source_module'] = 'solicitudes';
        }
        if (in_array('task_key', $columns, true)) {
            $whereParts[] = 'task_key = :task_key';
            $bindings[':task_key'] = $taskKey;
        }
        if (in_array('company_id', $columns, true) && $companyId !== null) {
            $whereParts[] = 'company_id = :company_id';
            $bindings[':company_id'] = $companyId;
        }

        $existingId = false;
        if ($whereParts !== []) {
            $findSql = 'SELECT id FROM crm_tasks WHERE ' . implode(' AND ', $whereParts) . ' ORDER BY id DESC LIMIT 1';
            $findStmt = $this->db->prepare($findSql);
            $findStmt->execute($bindings);
            $existingId = $findStmt->fetchColumn();
        }

        $payload = [];
        if (in_array('company_id', $columns, true) && $companyId !== null) {
            $payload['company_id'] = $companyId;
        }
        if (in_array('source_module', $columns, true)) {
            $payload['source_module'] = 'solicitudes';
        }
        if (in_array('source_ref_id', $columns, true)) {
            $payload['source_ref_id'] = (string) $solicitudId;
        }
        if (in_array('title', $columns, true)) {
            $payload['title'] = 'Solicitar cobertura';
        }
        if (in_array('description', $columns, true)) {
            $payload['description'] = 'Correo de cobertura enviado.';
        }
        if (in_array('status', $columns, true)) {
            $payload['status'] = 'completada';
        }
        if (in_array('task_key', $columns, true)) {
            $payload['task_key'] = $taskKey;
        }
        if (in_array('checklist_slug', $columns, true)) {
            $payload['checklist_slug'] = 'revision-codigos';
        }
        if (in_array('metadata', $columns, true)) {
            $payload['metadata'] = $metadataJson;
        }
        if (in_array('assigned_to', $columns, true)) {
            $payload['assigned_to'] = isset($detail['responsable_id']) ? (int) $detail['responsable_id'] : null;
        }
        if (in_array('lead_id', $columns, true)) {
            $payload['lead_id'] = isset($detail['crm_lead_id']) ? (int) $detail['crm_lead_id'] : null;
        }
        if (in_array('project_id', $columns, true)) {
            $payload['project_id'] = isset($detail['crm_project_id']) ? (int) $detail['crm_project_id'] : null;
        }
        if (in_array('created_by', $columns, true)) {
            $payload['created_by'] = $currentUserId;
        }
        if (in_array('completed_at', $columns, true)) {
            $payload['completed_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = $now;
        }

        if ($existingId !== false) {
            $this->updateDynamicRow('crm_tasks', $payload, 'id = :id', [':id' => (int) $existingId]);
            return;
        }

        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = $now;
        }
        $this->insertDynamicRow('crm_tasks', $payload);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCrmDetalleRow(int $solicitudId): ?array
    {
        if (!$this->hasTable('solicitud_crm_detalles')) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM solicitud_crm_detalles
             WHERE solicitud_id = :solicitud_id
             LIMIT 1'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function resolveCompanyId(): int
    {
        if ($this->companyIdCache !== null) {
            return $this->companyIdCache;
        }

        try {
            $stmt = $this->db->query('SELECT company_id FROM crm_tasks WHERE company_id IS NOT NULL LIMIT 1');
            $value = $stmt ? (int) $stmt->fetchColumn() : 0;
            if ($value > 0) {
                $this->companyIdCache = $value;
                return $value;
            }
        } catch (Throwable) {
            // ignore
        }

        $this->companyIdCache = 1;
        return 1;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function insertDynamicRow(string $table, array $data): void
    {
        if ($data === []) {
            return;
        }

        $columns = array_keys($data);
        $sql = 'INSERT INTO `' . $this->assertIdentifier($table) . '` ('
            . implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns))
            . ') VALUES ('
            . implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns))
            . ')';
        $stmt = $this->db->prepare($sql);
        foreach ($data as $column => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $column, $value, PDO::PARAM_INT);
            } elseif ($value === null) {
                $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':' . $column, (string) $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $bindings
     */
    private function updateDynamicRow(string $table, array $data, string $where, array $bindings): void
    {
        if ($data === []) {
            return;
        }

        $tableName = $this->assertIdentifier($table);
        $setSql = implode(', ', array_map(static fn(string $column): string => '`' . $column . '` = :' . $column, array_keys($data)));
        $stmt = $this->db->prepare('UPDATE `' . $tableName . '` SET ' . $setSql . ' WHERE ' . $where);
        foreach ($data as $column => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $column, $value, PDO::PARAM_INT);
            } elseif ($value === null) {
                $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':' . $column, (string) $value, PDO::PARAM_STR);
            }
        }
        foreach ($bindings as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } elseif ($value === null) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($key, (string) $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
    }

    private function ensureDerivacionPreseleccionAuto(string $hcNumber, string $formId, int $solicitudId): void
    {
        $seleccion = $this->obtenerDerivacionPreseleccion($solicitudId, $formId, $hcNumber);
        if (!empty($seleccion['derivacion_pedido_id'])) {
            return;
        }

        $fallback = $this->obtenerDerivacionPreseleccion(null, $formId, $hcNumber);
        if (!empty($fallback['derivacion_pedido_id'])) {
            $this->guardarDerivacionPreseleccion($solicitudId, [
                'codigo_derivacion' => $fallback['derivacion_codigo'] ?? null,
                'pedido_id_mas_antiguo' => $fallback['derivacion_pedido_id'] ?? null,
                'lateralidad' => $fallback['derivacion_lateralidad'] ?? null,
                'fecha_vigencia' => $fallback['derivacion_fecha_vigencia_sel'] ?? null,
                'prefactura' => $fallback['derivacion_prefactura'] ?? null,
            ]);
            return;
        }

        try {
            $result = $this->resolveDerivacionPreseleccion($hcNumber, $formId, $solicitudId);
        } catch (Throwable) {
            return;
        }

        $options = is_array($result['options'] ?? null) ? $result['options'] : [];
        if (count($options) !== 1) {
            return;
        }

        $option = $options[0];
        $pedidoId = trim((string) ($option['pedido_id_mas_antiguo'] ?? ''));
        $codigo = trim((string) ($option['codigo_derivacion'] ?? ''));
        if ($pedidoId === '' || $codigo === '') {
            return;
        }

        $this->guardarDerivacionPreseleccion($solicitudId, $option);
    }

    private function guardarDerivacionPreseleccion(int $solicitudId, array $payload): bool
    {
        $codigo = trim((string) ($payload['codigo_derivacion'] ?? ''));
        $pedidoId = trim((string) ($payload['pedido_id_mas_antiguo'] ?? ''));
        $lateralidad = trim((string) ($payload['lateralidad'] ?? ''));
        $vigencia = trim((string) ($payload['fecha_vigencia'] ?? ''));
        $prefactura = trim((string) ($payload['prefactura'] ?? ''));

        if ($solicitudId <= 0 || $codigo === '' || $pedidoId === '') {
            return false;
        }

        $set = [];
        $params = [':id' => $solicitudId];

        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_codigo')) {
            $set[] = 'derivacion_codigo = :codigo';
            $params[':codigo'] = $codigo;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_pedido_id')) {
            $set[] = 'derivacion_pedido_id = :pedido_id';
            $params[':pedido_id'] = $pedidoId;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_lateralidad')) {
            $set[] = 'derivacion_lateralidad = :lateralidad';
            $params[':lateralidad'] = $lateralidad !== '' ? $lateralidad : null;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_fecha_vigencia_sel')) {
            $set[] = 'derivacion_fecha_vigencia_sel = :vigencia';
            $params[':vigencia'] = $vigencia !== '' ? $vigencia : null;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_prefactura')) {
            $set[] = 'derivacion_prefactura = :prefactura';
            $params[':prefactura'] = $prefactura !== '' ? $prefactura : null;
        }

        if ($set === []) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE solicitud_procedimiento SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $existsStmt = $this->db->prepare('SELECT 1 FROM solicitud_procedimiento WHERE id = :id LIMIT 1');
        $existsStmt->execute([':id' => $solicitudId]);

        return $existsStmt->fetchColumn() !== false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerDerivacionPreseleccion(?int $solicitudId, string $formId, string $hcNumber): ?array
    {
        $codigoExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_codigo')
            ? 'sp.derivacion_codigo AS derivacion_codigo'
            : 'NULL AS derivacion_codigo';
        $pedidoExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_pedido_id')
            ? 'sp.derivacion_pedido_id AS derivacion_pedido_id'
            : 'NULL AS derivacion_pedido_id';
        $lateralidadExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_lateralidad')
            ? 'sp.derivacion_lateralidad AS derivacion_lateralidad'
            : 'NULL AS derivacion_lateralidad';
        $vigenciaExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_fecha_vigencia_sel')
            ? 'sp.derivacion_fecha_vigencia_sel AS derivacion_fecha_vigencia_sel'
            : 'NULL AS derivacion_fecha_vigencia_sel';
        $prefacturaExpr = $this->hasColumn('solicitud_procedimiento', 'derivacion_prefactura')
            ? 'sp.derivacion_prefactura AS derivacion_prefactura'
            : 'NULL AS derivacion_prefactura';

        if (($solicitudId ?? 0) > 0) {
            $stmt = $this->db->prepare(sprintf(
                'SELECT %s, %s, %s, %s, %s FROM solicitud_procedimiento sp WHERE sp.id = :id LIMIT 1',
                $codigoExpr,
                $pedidoExpr,
                $lateralidadExpr,
                $vigenciaExpr,
                $prefacturaExpr
            ));
            $stmt->execute([':id' => $solicitudId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        $stmt = $this->db->prepare(sprintf(
            'SELECT %s, %s, %s, %s, %s FROM solicitud_procedimiento sp WHERE sp.form_id = :form_id AND sp.hc_number = :hc_number ORDER BY sp.id DESC LIMIT 1',
            $codigoExpr,
            $pedidoExpr,
            $lateralidadExpr,
            $vigenciaExpr,
            $prefacturaExpr
        ));
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerDerivacionPorFormId(string $formId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT
                    rf.id AS derivacion_id,
                    r.referral_code AS cod_derivacion,
                    r.referral_code AS codigo_derivacion,
                    f.iess_form_id AS form_id,
                    f.hc_number,
                    f.fecha_creacion,
                    f.fecha_registro,
                    COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                    f.referido,
                    f.diagnostico,
                    f.sede,
                    f.parentesco,
                    f.archivo_derivacion_path
                 FROM derivaciones_forms f
                 LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
                 LEFT JOIN derivaciones_referrals r ON r.id = rf.referral_id
                 WHERE f.iess_form_id = :form_id
                 ORDER BY COALESCE(rf.linked_at, f.updated_at) DESC, f.id DESC
                 LIMIT 1'
            );
            $stmt->execute([':form_id' => $formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['id'] = $row['derivacion_id'] ?? null;
                return $row;
            }
        } catch (Throwable) {
            // continue with simpler/fallback lookups
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT
                    rf.id AS derivacion_id,
                    r.referral_code AS cod_derivacion,
                    r.referral_code AS codigo_derivacion,
                    f.iess_form_id AS form_id,
                    f.hc_number,
                    f.fecha_creacion,
                    f.fecha_registro,
                    COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                    f.referido,
                    f.diagnostico,
                    f.sede,
                    f.parentesco,
                    f.archivo_derivacion_path
                 FROM derivaciones_forms f
                 LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
                 LEFT JOIN derivaciones_referrals r ON r.id = rf.referral_id
                 WHERE f.iess_form_id = :form_id
                 ORDER BY f.id DESC
                 LIMIT 1'
            );
            $stmt->execute([':form_id' => $formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['id'] = $row['derivacion_id'] ?? null;
                return $row;
            }
        } catch (Throwable) {
            // continue
        }

        try {
            $stmt = $this->db->prepare('SELECT * FROM derivaciones_form_id WHERE form_id = :form_id LIMIT 1');
            $stmt->execute([':form_id' => $formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable) {
            // no-op
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerDerivacionLegacyPorFormHc(string $formId, string $hcNumber): ?array
    {
        if ($formId === '' || $hcNumber === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM derivaciones_form_id
                 WHERE form_id = :form_id
                   AND hc_number = :hc
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':form_id' => $formId,
                ':hc' => $hcNumber,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable) {
            // no-op
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerDerivacionPorCodigoHc(string $codigoDerivacion, string $hcNumber): ?array
    {
        if ($codigoDerivacion === '' || $hcNumber === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT
                    rf.id AS derivacion_id,
                    r.referral_code AS cod_derivacion,
                    r.referral_code AS codigo_derivacion,
                    f.iess_form_id AS form_id,
                    f.hc_number,
                    f.fecha_creacion,
                    f.fecha_registro,
                    COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                    f.referido,
                    f.diagnostico,
                    f.sede,
                    f.parentesco,
                    f.archivo_derivacion_path
                 FROM derivaciones_forms f
                 INNER JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
                 INNER JOIN derivaciones_referrals r ON r.id = rf.referral_id
                 WHERE r.referral_code = :codigo
                   AND f.hc_number = :hc
                 ORDER BY f.id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':codigo' => $codigoDerivacion,
                ':hc' => $hcNumber,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['id'] = $row['derivacion_id'] ?? null;
                return $row;
            }
        } catch (Throwable) {
            // continue
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM derivaciones_form_id
                 WHERE cod_derivacion = :codigo
                   AND hc_number = :hc
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':codigo' => $codigoDerivacion,
                ':hc' => $hcNumber,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable) {
            // no-op
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerDatosYCirujanoSolicitud(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                sp.*,
                sp.id AS solicitud_id,
                sp.id AS id,
                u.id AS user_id,
                u.nombre AS user_nombre,
                u.email AS user_email,
                u.first_name AS doctor_first_name,
                u.middle_name AS doctor_middle_name,
                u.last_name AS doctor_last_name,
                u.second_last_name AS doctor_second_last_name,
                u.cedula AS doctor_cedula,
                u.firma AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM solicitud_procedimiento sp
            LEFT JOIN users u
                ON LOWER(TRIM(sp.doctor)) LIKE CONCAT("%", LOWER(TRIM(u.nombre)), "%")
            WHERE sp.form_id = :form_id AND sp.hc_number = :hc_number
            ORDER BY sp.created_at DESC
            LIMIT 1'
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function getPatientDetails(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $hcNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function obtenerDxDeSolicitud(string $formId): array
    {
        if (!$this->hasTable('diagnosticos_asignados')) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT * FROM diagnosticos_asignados WHERE form_id = :form_id');
        $stmt->execute([':form_id' => $formId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>
     */
    private function obtenerConsultaDeSolicitud(string $formId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM consulta_data WHERE form_id = :form_id LIMIT 1');
        $stmt->execute([':form_id' => $formId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    private function obtenerSolicitudIdPorFormHc(string $formId, string $hcNumber): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM solicitud_procedimiento WHERE form_id = :form_id AND hc_number = :hc_number ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchLatestCoberturaMailLog(int $solicitudId): ?array
    {
        if (!$this->hasTable('solicitud_mail_log')) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT sml.sent_at, u.nombre AS sent_by_name
             FROM solicitud_mail_log sml
             LEFT JOIN users u ON u.id = sml.sent_by_user_id
             WHERE sml.solicitud_id = :solicitud_id AND sml.status = "sent"
             ORDER BY sml.sent_at DESC, sml.id DESC
             LIMIT 1'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function resolveTemplateKey(string $afiliacion): ?string
    {
        $normalized = $this->normalize($afiliacion);
        if ($normalized === '') {
            return null;
        }

        $rules = self::TEMPLATE_RULES;
        usort($rules, static fn(array $a, array $b): int => (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0));

        foreach ($rules as $rule) {
            foreach ($rule['exact'] as $matcher) {
                if ($normalized === $matcher) {
                    return (string) $rule['key'];
                }
            }
        }

        foreach ($rules as $rule) {
            foreach ($rule['contains'] as $matcher) {
                if ($matcher !== '' && str_contains($normalized, $matcher)) {
                    return (string) $rule['key'];
                }
            }
        }

        return null;
    }

    private function hasEnabledTemplate(string $templateKey): bool
    {
        if (!$this->hasTable('mail_templates')) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM mail_templates WHERE context = :context AND template_key = :template_key AND enabled = 1 LIMIT 1'
        );
        $stmt->execute([
            ':context' => 'cobertura',
            ':template_key' => $templateKey,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $value;
            }
        }

        $value = preg_replace('/[^a-z0-9\s\-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function upsertLegacyDerivacion(string $formId, string $hcNumber, array $payload): ?int
    {
        if (!$this->hasTable('derivaciones_form_id')) {
            return null;
        }

        $columns = $this->tableColumns('derivaciones_form_id');
        if (!in_array('form_id', $columns, true)) {
            return null;
        }

        $insert = [];
        if (in_array('cod_derivacion', $columns, true)) {
            $insert['cod_derivacion'] = $payload['cod_derivacion'] ?? null;
        }
        if (in_array('form_id', $columns, true)) {
            $insert['form_id'] = $formId;
        }
        if (in_array('hc_number', $columns, true)) {
            $insert['hc_number'] = $hcNumber;
        }
        if (in_array('fecha_registro', $columns, true)) {
            $insert['fecha_registro'] = $this->normalizeDate($payload['fecha_registro'] ?? null);
        }
        if (in_array('fecha_vigencia', $columns, true)) {
            $insert['fecha_vigencia'] = $this->normalizeDate($payload['fecha_vigencia'] ?? null);
        }
        if (in_array('referido', $columns, true)) {
            $insert['referido'] = $this->nullableString($payload['referido'] ?? null);
        }
        if (in_array('diagnostico', $columns, true)) {
            $insert['diagnostico'] = $this->nullableString($payload['diagnostico'] ?? null);
        }
        if (in_array('sede', $columns, true)) {
            $insert['sede'] = $this->nullableString($payload['sede'] ?? null);
        }
        if (in_array('parentesco', $columns, true)) {
            $insert['parentesco'] = $this->nullableString($payload['parentesco'] ?? null);
        }
        if (in_array('archivo_derivacion_path', $columns, true)) {
            $insert['archivo_derivacion_path'] = $this->nullableString($payload['archivo_derivacion_path'] ?? null);
        }

        $stmt = $this->db->prepare('SELECT id FROM derivaciones_form_id WHERE form_id = :form_id LIMIT 1');
        $stmt->execute([':form_id' => $formId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId === false) {
            if ($insert === []) {
                return null;
            }

            $columnsSql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", array_keys($insert)));
            $placeholders = implode(', ', array_map(static fn(string $column): string => ':' . $column, array_keys($insert)));
            $sql = 'INSERT INTO derivaciones_form_id (' . $columnsSql . ') VALUES (' . $placeholders . ')';
            $stmt = $this->db->prepare($sql);
            foreach ($insert as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->execute();

            return (int) $this->db->lastInsertId();
        }

        $update = $insert;
        unset($update['form_id']);

        if ($update === []) {
            return (int) $existingId;
        }

        $assignments = [];
        foreach (array_keys($update) as $column) {
            $assignments[] = "`{$column}` = :{$column}";
        }

        $sql = 'UPDATE derivaciones_form_id SET ' . implode(', ', $assignments) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        foreach ($update as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->bindValue(':id', (int) $existingId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $existingId;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * @return array{0:array<string,mixed>|null,1:string,2:int}
     */
    private function runCommandWithJson(string $command): array
    {
        [$outputLines, $exitCode] = $this->runCommand($command);
        $rawOutput = trim(implode("\n", $outputLines));
        $parsed = null;

        for ($i = count($outputLines) - 1; $i >= 0; $i--) {
            $line = trim((string) $outputLines[$i]);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
                break;
            }
        }

        if ($parsed === null && $rawOutput !== '') {
            $decoded = json_decode($rawOutput, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
            }
        }

        return [$parsed, $rawOutput, $exitCode];
    }

    /**
     * @return array{0:array<int,string>,1:int}
     */
    private function runCommand(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [$output, $exitCode];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }

        if (!$this->hasTable($table)) {
            $this->columnsCache[$table] = [];
            return [];
        }

        $safeTable = $this->assertIdentifier($table);

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM `' . $safeTable . '`');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            $rows = [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = isset($row['Field']) ? trim((string) $row['Field']) : '';
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        $this->columnsCache[$table] = $columns;

        return $columns;
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute([':table_name' => $table]);
            $exists = $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            $exists = false;
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function assertIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Identificador SQL inválido');
        }

        return $identifier;
    }

    private function projectRootPath(): string
    {
        return dirname(base_path());
    }
}
