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
     *   limit?:int,
     *   stale_hours?:int,
     *   form_id?:string|null,
     *   force?:bool
     * } $options
     * @param callable(string,array<string,mixed>):void|null $progress
     * @return array<string,mixed>
     */
    public function scan(array $options = [], ?callable $progress = null): array
    {
        $days = max(1, (int) ($options['days'] ?? 7));
        $limit = max(1, min(5000, (int) ($options['limit'] ?? 200)));
        $staleHours = max(1, (int) ($options['stale_hours'] ?? 6));
        $formId = trim((string) ($options['form_id'] ?? ''));
        $force = (bool) ($options['force'] ?? false);

        if (!$this->nasImagenesService->isAvailable()) {
            return [
                'success' => false,
                'error' => $this->nasImagenesService->getLastError() ?? 'NAS no disponible.',
                'processed' => 0,
                'candidates' => 0,
            ];
        }

        $lockKey = 'imagenes-nas-index-scan';
        $lock = Cache::lock($lockKey, 1800);
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
            $candidates = $this->candidateQuery($days, $staleHours, $formId, $force)
                ->limit($limit)
                ->get();

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

    private function candidateQuery(int $days, int $staleHours, string $formId, bool $force)
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
            $base->where('pp.fecha', '>=', Carbon::today()->subDays($days)->toDateString());
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
