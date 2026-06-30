<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappOperationalDailyReportExportService;
use App\Modules\Whatsapp\Services\WhatsappOperationalDailyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Manual read-only export of the daily Alert Engine report.
 *
 * GET /v2/whatsapp/api/operational-alerts/daily-report/export
 *   ?date=YYYY-MM-DD  (default: today)
 *   &limit=500        (default: 500, max: 500)
 *   &format=csv|xlsx  (default: csv)
 *
 * read_only=true, db_writes=0. No messages sent. No scheduler. No jobs.
 */
class OperationalAlertDailyReportExportController
{
    private const ALLOWED_FORMATS = ['csv', 'xlsx'];

    public function __construct(
        private readonly WhatsappOperationalDailyReportService       $reportService
            = new WhatsappOperationalDailyReportService(),
        private readonly WhatsappOperationalDailyReportExportService $exportService
            = new WhatsappOperationalDailyReportExportService(),
    ) {
    }

    public function index(Request $request): SymfonyResponse
    {
        $format = strtolower(trim((string) $request->query('format', 'csv')));

        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            return new JsonResponse([
                'ok'      => false,
                'message' => 'Formato de exportación no permitido.',
                'errors'  => ['format' => ['El formato debe ser csv o xlsx.']],
            ], 422);
        }

        $date  = trim((string) $request->query('date', date('Y-m-d')));
        $limit = max(1, min(500, (int) $request->query('limit', 500)));

        $report = $this->reportService->report([
            'date'  => $date,
            'limit' => $limit,
        ]);

        $safeDate = preg_replace('/[^0-9\-]/', '', $date) ?: date('Y-m-d');
        $filename = "whatsapp-alert-engine-daily-report-{$safeDate}.{$format}";

        if ($format === 'xlsx') {
            $content = $this->exportService->toXlsx($report, $safeDate);
            return new Response($content, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'no-store, no-cache',
            ]);
        }

        // CSV — prepend UTF-8 BOM so Excel opens accents and ñ correctly
        $csv = "\xEF\xBB\xBF" . $this->exportService->toCsv($report);
        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }
}
