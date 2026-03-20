<?php

namespace App\Modules\Examenes\Services;

use App\Models\ImagenSigcenterIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImagenesSigcenterIndexService
{
    private SigcenterImagenesService $sigcenterImagenesService;

    public function __construct(?SigcenterImagenesService $sigcenterImagenesService = null)
    {
        $this->sigcenterImagenesService = $sigcenterImagenesService ?? new SigcenterImagenesService();
    }

    /**
     * @param array{
     *   days?:int,
     *   from_date?:string|null,
     *   to_date?:string|null,
     *   limit?:int|null,
     *   stale_hours?:int,
     *   form_id?:string|null,
     *   force?:bool
     * } $options
     * @param callable(string,array<string,mixed>):void|null $progress
     * @return array<string,mixed>
     */
    public function scan(array $options = [], ?callable $progress = null): array
    {
        $rawLimit = $options['limit'] ?? null;
        $limit = null;
        if ($rawLimit !== null && $rawLimit !== '' && (int) $rawLimit > 0) {
            $limit = max(1, (int) $rawLimit);
        }

        $staleHours = max(1, (int) ($options['stale_hours'] ?? 6));
        $formId = trim((string) ($options['form_id'] ?? ''));
        $force = (bool) ($options['force'] ?? false);
        $dateWindow = $this->resolveDateWindow($options, $formId !== '');

        if ($dateWindow['error'] !== null) {
            return [
                'success' => false,
                'error' => $dateWindow['error'],
                'processed' => 0,
                'candidates' => 0,
            ];
        }

        if (!$this->sigcenterImagenesService->isAvailable()) {
            return [
                'success' => false,
                'error' => $this->sigcenterImagenesService->getLastError() ?? 'Sigcenter no disponible.',
                'processed' => 0,
                'candidates' => 0,
            ];
        }

        $lock = Cache::lock('imagenes-sigcenter-index-scan', 14400);
        if (!$lock->get()) {
            return [
                'success' => false,
                'error' => 'Ya existe un escaneo Sigcenter en ejecución.',
                'processed' => 0,
                'candidates' => 0,
            ];
        }

        try {
            $startedAt = microtime(true);
            $query = $this->candidateQuery(
                $dateWindow['from'],
                $dateWindow['to'],
                $staleHours,
                $formId,
                $force
            );

            if ($limit !== null) {
                $query->limit($limit);
            }

            $candidates = $query->get();

            $result = [
                'success' => true,
                'error' => null,
                'candidates' => $candidates->count(),
                'processed' => 0,
                'with_files' => 0,
                'with_db_rows' => 0,
                'no_mapping' => 0,
                'empty' => 0,
                'errors' => 0,
                'duration_ms' => 0,
                'avg_scan_ms' => 0,
            ];

            foreach ($candidates as $candidate) {
                $rowStartedAt = microtime(true);
                $form = trim((string) ($candidate->form_id ?? ''));
                $hc = trim((string) ($candidate->hc_number ?? ''));
                if ($form === '' || $hc === '') {
                    continue;
                }

                $docIds = $this->resolveDocSolicitudIds($form, $hc);
                $attachments = $this->sigcenterImagenesService->fetchAttachmentsByDocSolicitudIds($docIds);
                $summary = $this->summarizeAttachments($docIds, $attachments, $candidate);
                $summary['scan_duration_ms'] = (int) round((microtime(true) - $rowStartedAt) * 1000);

                ImagenSigcenterIndex::query()->updateOrCreate(
                    ['form_id' => $form],
                    [
                        'hc_number' => $hc,
                        'pedido_cirugia_id' => $summary['pedido_cirugia_id'],
                        'derivacion_pedido_id' => $summary['derivacion_pedido_id'],
                        'doc_solicitud_id' => $summary['doc_solicitud_id'],
                        'has_files' => $summary['has_files'],
                        'has_db_rows' => $summary['has_db_rows'],
                        'files_count' => $summary['files_count'],
                        'image_count' => $summary['image_count'],
                        'pdf_count' => $summary['pdf_count'],
                        'verified_files_count' => $summary['verified_files_count'],
                        'total_bytes' => $summary['total_bytes'],
                        'latest_file_mtime' => $summary['latest_file_mtime'],
                        'sample_file' => $summary['sample_file'],
                        'scan_status' => $summary['scan_status'],
                        'last_error' => $summary['last_error'],
                        'scan_duration_ms' => $summary['scan_duration_ms'],
                        'candidate_doc_ids' => $summary['candidate_doc_ids'],
                        'files_meta' => $summary['files_meta'],
                        'last_scanned_at' => now(),
                    ]
                );

                $result['processed']++;
                if ($summary['has_files']) {
                    $result['with_files']++;
                }
                if ($summary['has_db_rows']) {
                    $result['with_db_rows']++;
                }
                if ($summary['scan_status'] === 'no_mapping') {
                    $result['no_mapping']++;
                } elseif ($summary['scan_status'] === 'empty') {
                    $result['empty']++;
                } elseif ($summary['scan_status'] === 'error') {
                    $result['errors']++;
                }

                if ($progress !== null) {
                    $progress('row', [
                        'form_id' => $form,
                        'hc_number' => $hc,
                        'scan_status' => $summary['scan_status'],
                        'files_count' => $summary['files_count'],
                        'verified_files_count' => $summary['verified_files_count'],
                        'scan_duration_ms' => $summary['scan_duration_ms'],
                    ]);
                }
            }

            $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $result['avg_scan_ms'] = $result['processed'] > 0
                ? (int) round($result['duration_ms'] / $result['processed'])
                : 0;

            return $result;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return list<string>
     */
    private function resolveDocSolicitudIds(string $formId, string $hcNumber): array
    {
        $docIds = [];

        if (trim($formId) !== '') {
            $docIds[$formId] = $formId;
        }

        $solicitudRows = DB::table('solicitud_procedimiento')
            ->selectRaw('TRIM(COALESCE(pedido_cirugia_id, "")) as pedido_cirugia_id, TRIM(COALESCE(derivacion_pedido_id, "")) as derivacion_pedido_id')
            ->where('form_id', $formId)
            ->where('hc_number', $hcNumber)
            ->get();

        foreach ($solicitudRows as $row) {
            foreach (['pedido_cirugia_id', 'derivacion_pedido_id'] as $field) {
                $value = trim((string) ($row->{$field} ?? ''));
                if ($value !== '') {
                    $docIds[$value] = $value;
                }
            }
        }

        $consultaRows = DB::table('consulta_examenes')
            ->selectRaw('TRIM(COALESCE(derivacion_pedido_id, "")) as derivacion_pedido_id')
            ->where('form_id', $formId)
            ->where('hc_number', $hcNumber)
            ->get();

        foreach ($consultaRows as $row) {
            $value = trim((string) ($row->derivacion_pedido_id ?? ''));
            if ($value !== '') {
                $docIds[$value] = $value;
            }
        }

        return array_values($docIds);
    }

    /**
     * @param list<string> $docIds
     * @param array<int, array<string, mixed>> $attachments
     * @param object $candidate
     * @return array<string, mixed>
     */
    private function summarizeAttachments(array $docIds, array $attachments, object $candidate): array
    {
        $hasDbRows = $attachments !== [];
        $filesCount = count($attachments);
        $imageCount = 0;
        $pdfCount = 0;
        $verifiedFilesCount = 0;
        $totalBytes = 0;
        $latestFileMtime = null;
        $sampleFile = null;
        $lastError = $this->sigcenterImagenesService->getLastError();

        foreach ($attachments as $attachment) {
            $type = (string) ($attachment['tipo'] ?? '');
            if ($type === 'pdf') {
                $pdfCount++;
            } else {
                $imageCount++;
            }

            if (!empty($attachment['verified']) && !empty($attachment['exists'])) {
                $verifiedFilesCount++;
                $totalBytes += (int) ($attachment['size'] ?? 0);

                $mtime = $attachment['mtime'] ?? null;
                if (is_string($mtime) && $mtime !== '' && ($latestFileMtime === null || strcmp($mtime, $latestFileMtime) > 0)) {
                    $latestFileMtime = $mtime;
                }
            }

            if ($sampleFile === null) {
                $sampleFile = (string) ($attachment['relative_path'] ?? $attachment['foto'] ?? '');
            }
        }

        $hasFiles = $hasDbRows;
        $scanStatus = 'empty';

        if ($docIds === []) {
            $scanStatus = 'no_mapping';
        } elseif ($hasDbRows && $verifiedFilesCount > 0) {
            $scanStatus = 'verified';
        } elseif ($hasDbRows) {
            $scanStatus = 'metadata_only';
        }

        if ($lastError !== null && $scanStatus !== 'no_mapping') {
            $scanStatus = $hasDbRows ? $scanStatus : 'error';
        }

        return [
            'pedido_cirugia_id' => $this->readCandidateString($candidate, 'pedido_cirugia_id'),
            'derivacion_pedido_id' => $this->readCandidateString($candidate, 'derivacion_pedido_id'),
            'doc_solicitud_id' => $docIds[0] ?? null,
            'has_files' => $hasFiles,
            'has_db_rows' => $hasDbRows,
            'files_count' => $filesCount,
            'image_count' => $imageCount,
            'pdf_count' => $pdfCount,
            'verified_files_count' => $verifiedFilesCount,
            'total_bytes' => $totalBytes,
            'latest_file_mtime' => $latestFileMtime,
            'sample_file' => $sampleFile,
            'scan_status' => $scanStatus,
            'last_error' => $lastError,
            'candidate_doc_ids' => $docIds,
            'files_meta' => $attachments,
        ];
    }

    private function readCandidateString(object $candidate, string $field): ?string
    {
        $value = trim((string) ($candidate->{$field} ?? ''));
        return $value !== '' ? $value : null;
    }

    /**
     * @param array{
     *   days?:int,
     *   from_date?:string|null,
     *   to_date?:string|null
     * } $options
     * @return array{from:?string,to:?string,error:?string}
     */
    private function resolveDateWindow(array $options, bool $ignoreDates): array
    {
        if ($ignoreDates) {
            return ['from' => null, 'to' => null, 'error' => null];
        }

        $rawFrom = trim((string) ($options['from_date'] ?? ''));
        $rawTo = trim((string) ($options['to_date'] ?? ''));

        if ($rawFrom === '' && $rawTo === '') {
            $days = max(1, (int) ($options['days'] ?? 7));

            return [
                'from' => Carbon::today()->subDays($days)->toDateString(),
                'to' => null,
                'error' => null,
            ];
        }

        $fromDate = $this->normalizeDateOption($rawFrom);
        if ($fromDate === false) {
            return ['from' => null, 'to' => null, 'error' => 'La opción --from-date debe tener el formato YYYY-MM-DD.'];
        }

        $toDate = $this->normalizeDateOption($rawTo);
        if ($toDate === false) {
            return ['from' => null, 'to' => null, 'error' => 'La opción --to-date debe tener el formato YYYY-MM-DD.'];
        }

        if ($fromDate !== null && $toDate !== null && strcmp($fromDate, $toDate) > 0) {
            return ['from' => null, 'to' => null, 'error' => 'La opción --from-date no puede ser mayor que --to-date.'];
        }

        return ['from' => $fromDate, 'to' => $toDate, 'error' => null];
    }

    private function normalizeDateOption(string $value): string|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            return false;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function candidateQuery(?string $fromDate, ?string $toDate, int $staleHours, string $formId, bool $force)
    {
        $consulta = DB::table('consulta_examenes as ce')
            ->selectRaw('
                TRIM(ce.form_id) as form_id,
                TRIM(ce.hc_number) as hc_number,
                MAX(COALESCE(ce.consulta_fecha, ce.created_at)) as referencia_fecha
            ')
            ->whereNotNull('ce.form_id')
            ->whereRaw("TRIM(ce.form_id) <> ''")
            ->whereNotNull('ce.hc_number')
            ->whereRaw("TRIM(ce.hc_number) <> ''")
            ->whereNotNull('ce.examen_nombre')
            ->whereRaw("TRIM(ce.examen_nombre) <> ''")
            ->groupByRaw('TRIM(ce.form_id), TRIM(ce.hc_number)');

        $agenda = DB::table('procedimiento_proyectado as pp')
            ->selectRaw('
                TRIM(pp.form_id) as form_id,
                TRIM(pp.hc_number) as hc_number,
                MAX(pp.fecha) as referencia_fecha
            ')
            ->whereNotNull('pp.form_id')
            ->whereRaw("TRIM(pp.form_id) <> ''")
            ->whereNotNull('pp.hc_number')
            ->whereRaw("TRIM(pp.hc_number) <> ''")
            ->whereRaw("UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ''))) LIKE 'IMAGENES%'")
            ->groupByRaw('TRIM(pp.form_id), TRIM(pp.hc_number)');

        $union = $consulta->union($agenda);

        $base = DB::query()
            ->fromSub($union, 'src')
            ->selectRaw('
                src.form_id,
                src.hc_number,
                MAX(src.referencia_fecha) as referencia_fecha,
                MAX(NULLIF(TRIM(COALESCE(sp.pedido_cirugia_id, "")), "")) as pedido_cirugia_id,
                MAX(NULLIF(TRIM(COALESCE(ce.derivacion_pedido_id, "")), "")) as derivacion_pedido_id
            ')
            ->leftJoin('solicitud_procedimiento as sp', function ($join): void {
                $join->on('sp.form_id', '=', 'src.form_id')
                    ->on('sp.hc_number', '=', 'src.hc_number');
            })
            ->leftJoin('consulta_examenes as ce', function ($join): void {
                $join->on('ce.form_id', '=', 'src.form_id')
                    ->on('ce.hc_number', '=', 'src.hc_number');
            })
            ->groupBy('src.form_id', 'src.hc_number');

        if ($fromDate !== null) {
            $base->whereDate('src.referencia_fecha', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $base->whereDate('src.referencia_fecha', '<=', $toDate);
        }
        if ($formId !== '') {
            $base->where('src.form_id', $formId);
        }

        if (!$force) {
            $threshold = now()->subHours($staleHours);
            $base->leftJoin('imagenes_sigcenter_index as idx', 'idx.form_id', '=', 'src.form_id')
                ->where(function ($query) use ($threshold): void {
                    $query->whereNull('idx.last_scanned_at')
                        ->orWhere('idx.last_scanned_at', '<=', $threshold);
                });
        }

        return $base->orderByDesc('referencia_fecha');
    }
}
