<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappOperationalDailyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only daily report of Alert Engine activity.
 *
 * No DB writes. No messages sent. read_only=true, db_writes=0.
 */
class OperationalAlertDailyReportController
{
    public function __construct(
        private readonly WhatsappOperationalDailyReportService $reportService
            = new WhatsappOperationalDailyReportService()
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $date  = trim((string) $request->query('date', date('Y-m-d')));
        $limit = max(1, min(500, (int) $request->query('limit', 500)));

        $result = $this->reportService->report([
            'date'  => $date,
            'limit' => $limit,
        ]);

        return response()->json($result);
    }
}
