<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\BillingAnestesium;
use App\Models\BillingDerecho;
use App\Models\BillingInsumo;
use App\Models\BillingMain;
use App\Models\BillingOxigeno;
use App\Models\BillingProcedimiento;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillingWriteService
{
    public function __construct(private readonly BillingPreviewService $previewService)
    {
    }

    /**
     * @return array{billing_id:int, created:bool, repaired:bool}
     */
    public function crearDesdeNoFacturado(string $formId, string $hcNumber, ?int $userId): array
    {
        $existing = BillingMain::where('form_id', $formId)->first();

        if ($existing !== null) {
            $billingId = (int) $existing->id;
            $repaired = false;

            if ($billingId > 0 && !$this->hasAnyBillingDetail($billingId)) {
                DB::transaction(function () use ($billingId, $formId, $hcNumber): void {
                    $this->seedBillingDetailsFromPreview($billingId, $formId, $hcNumber);
                });
                $repaired = true;
            }

            return ['billing_id' => $billingId, 'created' => false, 'repaired' => $repaired];
        }

        $billingId = DB::transaction(function () use ($formId, $hcNumber, $userId): int {
            $billingId = $this->insertBillingMain($formId, $hcNumber, $userId);
            $this->syncBillingCreatedAt($billingId, $formId);
            $this->seedBillingDetailsFromPreview($billingId, $formId, $hcNumber);
            return $billingId;
        });

        return ['billing_id' => $billingId, 'created' => true, 'repaired' => false];
    }

    public function eliminarFactura(string $formId): bool
    {
        return BillingMain::where('form_id', $formId)->delete() > 0;
    }

    /**
     * @param array<int, string|int> $formIds
     * @return array{success:bool, existentes:array<int,string>, nuevos:array<int,string>, message?:string}
     */
    public function verificarFormIds(array $formIds): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn($id): string => trim((string) $id), $formIds),
            static fn($id): bool => $id !== ''
        ));

        if ($normalized === []) {
            return ['success' => false, 'message' => 'No se enviaron form_ids.', 'existentes' => [], 'nuevos' => []];
        }

        $existentes = DB::table('procedimiento_proyectado')
            ->whereIn('form_id', $normalized)
            ->pluck('form_id')
            ->map(static fn($v): string => (string) $v)
            ->values()
            ->all();

        $nuevos = array_values(array_diff($normalized, $existentes));

        return [
            'success' => true,
            'existentes' => array_values(array_unique($existentes)),
            'nuevos' => $nuevos,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     * @return array{
     *   procedimiento_proyectado:array{creados:array<int,string>,ya_existian:array<int,string>},
     *   billing:array{nuevos:array<int,string>,existentes:array<int,string>,procedimientos_insertados:array<int,string>},
     *   errores:array<int,string>
     * }
     */
    public function registrarProcedimientoCompleto(array $procedimientos, ?int $userId): array
    {
        $resultadoProyectado = $this->crearFormIdsFaltantes($procedimientos);
        $resultadoBilling = $this->insertarBillingMainSiNoExiste($procedimientos, $userId);

        return [
            'procedimiento_proyectado' => [
                'creados' => $resultadoProyectado['creados'],
                'ya_existian' => $resultadoProyectado['ya_existian'],
            ],
            'billing' => [
                'nuevos' => $resultadoBilling['nuevos'],
                'existentes' => $resultadoBilling['existentes'],
                'procedimientos_insertados' => $resultadoBilling['procedimientos_insertados'],
            ],
            'errores' => array_merge($resultadoProyectado['errores'], $resultadoBilling['errores']),
        ];
    }

    // --- Métodos privados ---

    private function insertBillingMain(string $formId, string $hcNumber, ?int $userId): int
    {
        $data = ['hc_number' => $hcNumber, 'form_id' => $formId];
        if ($userId !== null && $userId > 0) {
            $data['facturado_por'] = $userId;
        }

        $billing = BillingMain::create($data);
        return (int) $billing->id;
    }

    private function syncBillingCreatedAt(int $billingId, string $formId): void
    {
        $fechaInicio = DB::table('protocolo_data')
            ->where('form_id', $formId)
            ->value('fecha_inicio');

        if (!is_string($fechaInicio) || trim($fechaInicio) === '') {
            return;
        }

        BillingMain::where('id', $billingId)->update(['created_at' => $fechaInicio]);
    }

    private function hasAnyBillingDetail(int $billingId): bool
    {
        $billing = BillingMain::find($billingId);
        if ($billing === null) {
            return false;
        }

        return $billing->billing_procedimientos()->exists()
            || $billing->billing_insumos()->exists()
            || $billing->billing_derechos()->exists()
            || $billing->billing_oxigenos()->exists()
            || $billing->billing_anestesia()->exists();
    }

    private function seedBillingDetailsFromPreview(int $billingId, string $formId, string $hcNumber): void
    {
        $preview = $this->buildPreviewPayload($formId, $hcNumber);

        foreach (($preview['procedimientos'] ?? []) as $proc) {
            $codigo = trim((string) ($proc['procCodigo'] ?? ''));
            $detalle = trim((string) ($proc['procDetalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }
            $this->insertBillingProcedimiento($billingId, $codigo, $detalle, (float) ($proc['procPrecio'] ?? 0));
        }

        foreach (($preview['insumos'] ?? []) as $insumo) {
            $codigo = trim((string) ($insumo['codigo'] ?? ''));
            $nombre = trim((string) ($insumo['nombre'] ?? ''));
            if ($codigo === '' || $nombre === '') {
                continue;
            }
            BillingInsumo::create([
                'billing_id' => $billingId,
                'insumo_id'  => is_numeric($insumo['id'] ?? null) ? (int) $insumo['id'] : null,
                'codigo'     => $codigo,
                'nombre'     => $nombre,
                'cantidad'   => (float) ($insumo['cantidad'] ?? 0),
                'precio'     => (float) ($insumo['precio'] ?? 0),
                'iva'        => (int) ($insumo['iva'] ?? 1),
            ]);
        }

        foreach (($preview['derechos'] ?? []) as $derecho) {
            $codigo = trim((string) ($derecho['codigo'] ?? ''));
            $detalle = trim((string) ($derecho['detalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }
            BillingDerecho::create([
                'billing_id'        => $billingId,
                'derecho_id'        => is_numeric($derecho['id'] ?? null) ? (int) $derecho['id'] : null,
                'codigo'            => $codigo,
                'detalle'           => $detalle,
                'cantidad'          => (float) ($derecho['cantidad'] ?? 0),
                'iva'               => (int) ($derecho['iva'] ?? 0),
                'precio_afiliacion' => (float) ($derecho['precioAfiliacion'] ?? 0),
            ]);
        }

        foreach (($preview['oxigeno'] ?? []) as $oxigeno) {
            $codigo = trim((string) ($oxigeno['codigo'] ?? ''));
            $nombre = trim((string) ($oxigeno['nombre'] ?? ''));
            if ($codigo === '' || $nombre === '') {
                continue;
            }
            BillingOxigeno::create([
                'billing_id' => $billingId,
                'codigo'     => $codigo,
                'nombre'     => $nombre,
                'tiempo'     => (float) ($oxigeno['tiempo'] ?? 0),
                'litros'     => (float) ($oxigeno['litros'] ?? 0),
                'valor1'     => (float) ($oxigeno['valor1'] ?? 0),
                'valor2'     => (float) ($oxigeno['valor2'] ?? 0),
                'precio'     => (float) ($oxigeno['precio'] ?? 0),
            ]);
        }

        foreach (($preview['anestesia'] ?? []) as $anestesia) {
            $codigo = trim((string) ($anestesia['codigo'] ?? ''));
            $nombre = trim((string) ($anestesia['nombre'] ?? ''));
            if ($codigo === '' || $nombre === '') {
                continue;
            }
            BillingAnestesium::create([
                'billing_id' => $billingId,
                'codigo'     => $codigo,
                'nombre'     => $nombre,
                'tiempo'     => (float) ($anestesia['tiempo'] ?? 0),
                'valor2'     => (float) ($anestesia['valor2'] ?? 0),
                'precio'     => (float) ($anestesia['precio'] ?? 0),
            ]);
        }
    }

    private function insertBillingProcedimiento(int $billingId, string $codigo, string $detalle, float $precio): void
    {
        if (!BillingProcedimiento::where('billing_id', $billingId)
            ->where('proc_codigo', $codigo)
            ->where('proc_detalle', $detalle)
            ->exists()
        ) {
            BillingProcedimiento::create([
                'billing_id'      => $billingId,
                'procedimiento_id' => null,
                'proc_codigo'     => $codigo,
                'proc_detalle'    => $detalle,
                'proc_precio'     => $precio,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     * @return array{creados:array<int,string>, ya_existian:array<int,string>, errores:array<int,string>}
     */
    private function crearFormIdsFaltantes(array $procedimientos): array
    {
        $formIds = array_values(array_filter(
            array_map(static fn($item): string => trim((string) ($item['form_id'] ?? '')), $procedimientos),
            static fn($id): bool => $id !== ''
        ));

        if ($formIds === []) {
            return ['creados' => [], 'ya_existian' => [], 'errores' => []];
        }

        $existentes = DB::table('procedimiento_proyectado')
            ->whereIn('form_id', $formIds)
            ->pluck('form_id')
            ->map(static fn($v): string => (string) $v)
            ->all();

        $creados = [];
        $errores = [];
        $now = now();

        foreach ($procedimientos as $item) {
            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));

            if ($formId === '' || $hcNumber === '') {
                continue;
            }
            if (in_array($formId, $existentes, true)) {
                continue;
            }
            if (!is_numeric($formId)) {
                $errores[] = "form_id inválido: {$formId}";
                continue;
            }

            try {
                DB::table('procedimiento_proyectado')->insert(array_filter([
                    'form_id'                 => (int) $formId,
                    'hc_number'               => $hcNumber,
                    'procedimiento_proyectado' => (string) ($item['procedimiento_proyectado'] ?? $item['detalle'] ?? ''),
                    'doctor'                  => $this->nullableString($item['doctor'] ?? null),
                    'fecha'                   => $this->normalizeDate($item['fecha'] ?? null),
                    'hora'                    => $this->normalizeTime($item['hora'] ?? null),
                    'sede_departamento'       => $this->nullableString($item['sede_departamento'] ?? null),
                    'id_sede'                 => is_numeric($item['id_sede'] ?? null) ? (int) $item['id_sede'] : null,
                    'afiliacion'              => $this->nullableString($item['afiliacion'] ?? null),
                    'estado_agenda'           => $this->nullableString($item['estado_agenda'] ?? null),
                    'visita_id'               => is_numeric($item['visita_id'] ?? null) ? (int) $item['visita_id'] : null,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ], static fn($v) => $v !== null));

                $existentes[] = $formId;
                $creados[] = $formId;
            } catch (Throwable $e) {
                $errores[] = "Error insertando procedimiento_proyectado {$formId}: {$e->getMessage()}";
            }
        }

        return ['creados' => $creados, 'ya_existian' => array_values(array_unique($existentes)), 'errores' => $errores];
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     * @return array{nuevos:array<int,string>, existentes:array<int,string>, procedimientos_insertados:array<int,string>, errores:array<int,string>}
     */
    private function insertarBillingMainSiNoExiste(array $procedimientos, ?int $userId): array
    {
        $formIds = array_values(array_filter(
            array_map(static fn($item): string => trim((string) ($item['form_id'] ?? '')), $procedimientos),
            static fn($id): bool => $id !== ''
        ));

        if ($formIds === []) {
            return ['nuevos' => [], 'existentes' => [], 'procedimientos_insertados' => [], 'errores' => []];
        }

        $existingByFormId = BillingMain::whereIn('form_id', $formIds)
            ->pluck('id', 'form_id')
            ->map(static fn($id): int => (int) $id)
            ->all();

        $nuevos = [];
        $procedimientosInsertados = [];
        $errores = [];

        foreach ($procedimientos as $item) {
            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $billingId = $existingByFormId[$formId] ?? null;
            if ($billingId === null) {
                try {
                    $billingId = $this->insertBillingMain($formId, $hcNumber, $userId);
                    $existingByFormId[$formId] = $billingId;
                    $nuevos[] = $formId;
                    $this->syncBillingCreatedAt($billingId, $formId);
                } catch (Throwable $e) {
                    $errores[] = "Error insertando billing_main {$formId}: {$e->getMessage()}";
                    continue;
                }
            }

            $codigoDerivacion = trim((string) ($item['codigo_derivacion'] ?? ''));
            if ($codigoDerivacion !== '') {
                try {
                    $this->upsertLegacyDerivacion($item);
                } catch (Throwable $e) {
                    $errores[] = "No se pudo registrar derivación {$formId}: {$e->getMessage()}";
                }
            }

            [$codigo, $detalle] = $this->extractCodigoDetalle($item);
            if ($billingId > 0 && $codigo !== '' && $detalle !== '') {
                try {
                    $this->insertBillingProcedimiento($billingId, $codigo, $detalle, $this->lookupTarifa($codigo));
                    $procedimientosInsertados[] = $formId;
                } catch (Throwable $e) {
                    $errores[] = "Error insertando procedimiento billing {$formId}: {$e->getMessage()}";
                }
            }
        }

        return [
            'nuevos'                   => $nuevos,
            'existentes'               => array_values(array_unique(array_keys($existingByFormId))),
            'procedimientos_insertados' => array_values(array_unique($procedimientosInsertados)),
            'errores'                  => $errores,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function upsertLegacyDerivacion(array $item): void
    {
        $formId = trim((string) ($item['form_id'] ?? ''));
        if ($formId === '') {
            return;
        }

        if (!DB::getSchemaBuilder()->hasTable('derivaciones_form_id')) {
            return;
        }

        DB::table('derivaciones_form_id')->updateOrInsert(
            ['form_id' => $formId],
            array_filter([
                'cod_derivacion'          => trim((string) ($item['codigo_derivacion'] ?? '')),
                'hc_number'               => $this->nullableString($item['hc_number'] ?? null),
                'fecha_registro'          => $this->normalizeDate($item['fecha_registro'] ?? null),
                'fecha_vigencia'          => $this->normalizeDate($item['fecha_vigencia'] ?? null),
                'referido'                => $this->nullableString($item['referido'] ?? null),
                'diagnostico'             => $this->nullableString($item['diagnostico'] ?? null),
                'sede'                    => $this->nullableString($item['sede'] ?? null),
                'parentesco'              => $this->nullableString($item['parentesco'] ?? null),
                'archivo_derivacion_path' => $this->nullableString($item['archivo_derivacion_path'] ?? null),
            ], static fn($v) => $v !== null)
        );
    }

    private function lookupTarifa(string $codigo): float
    {
        $value = DB::table('tarifario_2014')
            ->where(static function ($q) use ($codigo): void {
                $q->where('codigo', $codigo)->orWhere('codigo', ltrim($codigo, '0'));
            })
            ->value('valor_facturar_nivel3');

        return $value !== null ? (float) $value : 0.0;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPreviewPayload(string $formId, string $hcNumber): array
    {
        $preview = ['procedimientos' => [], 'insumos' => [], 'derechos' => [], 'oxigeno' => [], 'anestesia' => []];

        try {
            $previewData = $this->previewService->prepararPreviewFacturacion($formId, $hcNumber);
            if (is_array($previewData)) {
                foreach (array_keys($preview) as $key) {
                    if (isset($previewData[$key]) && is_array($previewData[$key])) {
                        $preview[$key] = $previewData[$key];
                    }
                }
            }
        } catch (Throwable) {
            // Mantener el flujo de facturación aunque el preview enriquecido falle.
        }

        if (($preview['procedimientos'] ?? []) === []) {
            $preview['procedimientos'] = $this->buildProcedimientosPreview($formId);
        }

        return $preview;
    }

    /**
     * @return array<int, array{procCodigo:string, procDetalle:string, procPrecio:float}>
     */
    private function buildProcedimientosPreview(string $formId): array
    {
        $result = [];
        $seen = [];

        $json = DB::table('protocolo_data')->where('form_id', $formId)->value('procedimientos');
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $proc) {
                    if (!is_array($proc)) {
                        continue;
                    }
                    [$codigo, $detalle] = $this->parseCodigoDetalle((string) ($proc['procInterno'] ?? ''));
                    if ($codigo === '' || $detalle === '') {
                        continue;
                    }
                    $key = $codigo . '|' . $detalle;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $result[] = ['procCodigo' => $codigo, 'procDetalle' => $detalle, 'procPrecio' => $this->lookupTarifa($codigo)];
                }
            }
        }

        if ($result !== []) {
            return $result;
        }

        $raw = (string) (DB::table('procedimiento_proyectado')->where('form_id', $formId)->value('procedimiento_proyectado') ?? '');
        [$codigo, $detalle] = $this->parseCodigoDetalle($raw);
        if ($codigo !== '' && $detalle !== '') {
            $result[] = ['procCodigo' => $codigo, 'procDetalle' => $detalle, 'procPrecio' => $this->lookupTarifa($codigo)];
        }

        return $result;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function parseCodigoDetalle(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            return ['', ''];
        }

        if (preg_match('/-\s*(\d{5,6})\s*-\s*(.+)$/', $text, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        if (preg_match('/\b(\d{5,6})\b/', $text, $matches) === 1) {
            $codigo = trim($matches[1]);
            $detalle = trim(str_replace($codigo, '', $text));
            $detalle = trim(preg_replace('/\s+/', ' ', $detalle) ?? $detalle);
            return [$codigo, $detalle !== '' ? $detalle : $text];
        }

        return ['', ''];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{0:string, 1:string}
     */
    private function extractCodigoDetalle(array $item): array
    {
        $codigo = trim((string) ($item['codigo'] ?? ''));
        $detalle = trim((string) ($item['detalle'] ?? ''));
        if ($codigo !== '' && $detalle !== '') {
            return [$codigo, $detalle];
        }

        return $this->parseCodigoDetalle((string) ($item['procedimiento_proyectado'] ?? ''));
    }

    private function nullableString(mixed $value): ?string
    {
        $clean = trim((string) $value);
        return $clean !== '' ? $clean : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }
        $timestamp = strtotime($clean);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }
        $timestamp = strtotime($clean);
        return $timestamp !== false ? date('H:i:s', $timestamp) : null;
    }
}
