<?php

namespace App\Modules\Examenes\Services;

use App\Models\ImagenNasIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImagenesNasIndexService
{
    private NasImagenesService $nasImagenesService;

    public function __construct(?NasImagenesService $nasImagenesService = null)
    {
        $this->nasImagenesService = $nasImagenesService ?? new NasImagenesService();
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

        if (!$this->nasImagenesService->isAvailable()) {
            return [
                'success' => false,
                'error' => $this->nasImagenesService->getLastError() ?? 'NAS no disponible.',
                'processed' => 0,
                'candidates' => 0,
            ];
        }

        $lockKey = 'imagenes-nas-index-scan';
        $lock = Cache::lock($lockKey, 14400);
        if (!$lock->get()) {
            return [
                'success' => false,
                'error' => 'Ya existe un escaneo de NAS en ejecución.',
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
                'empty' => 0,
                'missing_dir' => 0,
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

                $files = $this->nasImagenesService->listFiles($hc, $form);
                $error = $this->nasImagenesService->getLastError();
                $summary = $this->summarizeFiles($files, $error);
                $summary['scan_duration_ms'] = (int) round((microtime(true) - $rowStartedAt) * 1000);

                ImagenNasIndex::query()->updateOrCreate(
                    ['form_id' => $form],
                    [
                        'hc_number' => $hc,
                        'has_files' => $summary['has_files'],
                        'files_count' => $summary['files_count'],
                        'image_count' => $summary['image_count'],
                        'pdf_count' => $summary['pdf_count'],
                        'total_bytes' => $summary['total_bytes'],
                        'latest_file_mtime' => $summary['latest_file_mtime'],
                        'sample_file' => $summary['sample_file'],
                        'scan_status' => $summary['scan_status'],
                        'last_error' => $summary['last_error'],
                        'scan_duration_ms' => $summary['scan_duration_ms'],
                        'last_scanned_at' => now(),
                    ]
                );

                $result['processed']++;
                if ($summary['has_files']) {
                    $result['with_files']++;
                }
                if ($summary['scan_status'] === 'empty') {
                    $result['empty']++;
                } elseif ($summary['scan_status'] === 'missing_dir') {
                    $result['missing_dir']++;
                } elseif ($summary['scan_status'] === 'error') {
                    $result['errors']++;
                }

                if ($progress !== null) {
                    $progress('row', [
                        'form_id' => $form,
                        'hc_number' => $hc,
                        'scan_status' => $summary['scan_status'],
                        'files_count' => $summary['files_count'],
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
            return [
                'from' => null,
                'to' => null,
                'error' => null,
            ];
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
            return [
                'from' => null,
                'to' => null,
                'error' => 'La opción --from-date debe tener el formato YYYY-MM-DD.',
            ];
        }

        $toDate = $this->normalizeDateOption($rawTo);
        if ($toDate === false) {
            return [
                'from' => null,
                'to' => null,
                'error' => 'La opción --to-date debe tener el formato YYYY-MM-DD.',
            ];
        }

        if ($fromDate !== null && $toDate !== null && strcmp($fromDate, $toDate) > 0) {
            return [
                'from' => null,
                'to' => null,
                'error' => 'La opción --from-date no puede ser mayor que --to-date.',
            ];
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'error' => null,
        ];
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
        $base = DB::table('procedimiento_proyectado as pp')
            ->selectRaw('TRIM(pp.form_id) as form_id, TRIM(pp.hc_number) as hc_number, MAX(pp.fecha) as fecha_programada')
            ->whereNotNull('pp.form_id')
            ->whereRaw("TRIM(pp.form_id) <> ''")
            ->whereNotNull('pp.hc_number')
            ->whereRaw("TRIM(pp.hc_number) <> ''")
            ->whereRaw("UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ''))) LIKE 'IMAGENES%'")
            ->groupByRaw('TRIM(pp.form_id), TRIM(pp.hc_number)');

        if ($formId !== '') {
            $base->where('pp.form_id', $formId);
        } else {
            if ($fromDate !== null) {
                $base->where('pp.fecha', '>=', $fromDate);
            }
            if ($toDate !== null) {
                $base->where('pp.fecha', '<', Carbon::parse($toDate)->addDay()->toDateString());
            }
        }

        $query = DB::query()
            ->fromSub($base, 'cand')
            ->leftJoin('imagenes_nas_index as idx', 'idx.form_id', '=', 'cand.form_id')
            ->select([
                'cand.form_id',
                'cand.hc_number',
                'cand.fecha_programada',
                'idx.last_scanned_at',
                'idx.scan_status',
            ])
            ->orderByDesc('cand.fecha_programada')
            ->orderBy('cand.form_id');

        if (!$force && $formId === '') {
            $staleBefore = now()->subHours($staleHours);
            $query->where(function ($where) use ($staleBefore): void {
                $where->whereNull('idx.last_scanned_at')
                    ->orWhere('idx.last_scanned_at', '<', $staleBefore);
            });
        }

        return $query;
    }

    /**
     * @param array<int,array{name:string,size:int,mtime:int,ext:string,type:string}> $files
     * @return array<string,mixed>
     */
    private function summarizeFiles(array $files, ?string $error): array
    {
        $filesCount = count($files);
        $imageCount = 0;
        $pdfCount = 0;
        $totalBytes = 0;
        $latestMtime = null;
        $sampleFile = null;

        foreach ($files as $index => $file) {
            $ext = strtolower(trim((string) ($file['ext'] ?? '')));
            $size = max(0, (int) ($file['size'] ?? 0));
            $mtime = max(0, (int) ($file['mtime'] ?? 0));
            $name = trim((string) ($file['name'] ?? ''));

            $totalBytes += $size;
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'tif', 'tiff', 'webp'], true)) {
                $imageCount++;
            }
            if ($ext === 'pdf') {
                $pdfCount++;
            }
            if ($latestMtime === null || $mtime > $latestMtime) {
                $latestMtime = $mtime;
                $sampleFile = $name !== '' ? $name : $sampleFile;
            }
            if ($sampleFile === null && $index === 0 && $name !== '') {
                $sampleFile = $name;
            }
        }

        $scanStatus = 'ok';
        $lastError = null;
        if ($filesCount === 0) {
            $normalizedError = strtolower(trim((string) $error));
            if ($normalizedError !== '') {
                if (str_contains($normalizedError, 'carpeta no encontrada')) {
                    $scanStatus = 'missing_dir';
                } else {
                    $scanStatus = 'error';
                }
                $lastError = $error;
            } else {
                $scanStatus = 'empty';
            }
        }

        return [
            'has_files' => $filesCount > 0,
            'files_count' => $filesCount,
            'image_count' => $imageCount,
            'pdf_count' => $pdfCount,
            'total_bytes' => $totalBytes,
            'latest_file_mtime' => $latestMtime !== null ? Carbon::createFromTimestamp($latestMtime) : null,
            'sample_file' => $sampleFile,
            'scan_status' => $scanStatus,
            'last_error' => $lastError,
        ];
    }
}
