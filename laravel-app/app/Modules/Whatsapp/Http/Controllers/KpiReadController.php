<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Reporting\Services\PdfRenderer;
use App\Modules\Whatsapp\Services\KpiDashboardService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KpiReadController
{
    public function __construct(
        private readonly KpiDashboardService $service = new KpiDashboardService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            return response()->json([
                'ok' => true,
                'data' => $this->service->buildDashboard(
                    $start,
                    $end,
                    $this->nullableInt($request->query('role_id')),
                    $this->nullableInt($request->query('agent_id')),
                    $this->nullableInt($request->query('sla_target_minutes'))
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function drilldown(Request $request): JsonResponse
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);
            $metric = trim((string) $request->query('metric', ''));
            if ($metric === '') {
                throw new InvalidArgumentException('Debes indicar la métrica en ?metric=...');
            }

            return response()->json([
                'ok' => true,
                'data' => $this->service->buildDrilldown(
                    $metric,
                    $start,
                    $end,
                    $this->nullableInt($request->query('role_id')),
                    $this->nullableInt($request->query('agent_id')),
                    max(1, (int) $request->query('page', 1)),
                    max(1, min(200, (int) $request->query('limit', 50)))
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $roleId = $this->nullableInt($request->query('role_id'));
        $agentId = $this->nullableInt($request->query('agent_id'));
        $slaTargetMinutes = $this->nullableInt($request->query('sla_target_minutes'));
        $rows = $this->service->exportDashboardCsvRows($start, $end, $roleId, $agentId, $slaTargetMinutes);
        $filename = sprintf(
            'whatsapp-kpi-%s-a-%s.csv',
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        [$start, $end] = $this->resolveDateRange($request);
        $roleId = $this->nullableInt($request->query('role_id'));
        $agentId = $this->nullableInt($request->query('agent_id'));
        $slaTargetMinutes = $this->nullableInt($request->query('sla_target_minutes'));
        $dashboard = $this->service->buildDashboard($start, $end, $roleId, $agentId, $slaTargetMinutes);
        $filename = sprintf(
            'whatsapp-resumen-ejecutivo-%s-a-%s.pdf',
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        try {
            if (!class_exists(\Mpdf\Mpdf::class)) {
                throw new RuntimeException('La librería mPDF no está disponible en el entorno.');
            }

            $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
            $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
            $analyticsSummary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
            $lifecycle = array_slice(is_array($analytics['lifecycle'] ?? null) ? $analytics['lifecycle'] : [], 0, 4);
            $sources = array_slice(is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [], 0, 6);
            $funnel = is_array($analytics['funnel'] ?? null) ? $analytics['funnel'] : [];
            $frictions = array_slice(is_array($analytics['frictions'] ?? null) ? $analytics['frictions'] : [], 0, 6);
            $insights = is_array($analytics['insights'] ?? null) ? $analytics['insights'] : [];

            $recommendations = $this->buildExecutiveRecommendations($dashboard);

            $html = view('whatsapp.pdf.dashboard-executive', [
                'generatedAt' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                'period' => is_array($dashboard['period'] ?? null) ? $dashboard['period'] : [],
                'filters' => is_array($dashboard['filters'] ?? null) ? $dashboard['filters'] : [],
                'summary' => $summary,
                'analyticsSummary' => $analyticsSummary,
                'lifecycle' => $lifecycle,
                'sources' => $sources,
                'funnel' => $funnel,
                'frictions' => $frictions,
                'insights' => $insights,
                'recommendations' => $recommendations,
            ])->render();

            $tempDir = storage_path('app/mpdf');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }

            $renderer = new PdfRenderer();
            $pdf = $renderer->renderHtml($html, [
                'filename' => $filename,
                'destination' => 'S',
                'mpdf' => [
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 10,
                    'tempDir' => $tempDir,
                ],
            ]);

            if (!is_string($pdf) || strncmp($pdf, '%PDF-', 5) !== 0) {
                throw new RuntimeException('No se pudo generar el PDF del dashboard.');
            }

            return response($pdf, 200, [
                'Content-Length' => (string) strlen($pdf),
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function resolveDateRange(Request $request): array
    {
        $today = new DateTimeImmutable('today');
        $from = $this->parseDate(trim((string) $request->query('date_from', ''))) ?? $today->modify('-29 days');
        $to = $this->parseDate(trim((string) $request->query('date_to', ''))) ?? $today;

        if ($from > $to) {
            throw new InvalidArgumentException('date_from no puede ser mayor que date_to.');
        }

        $days = (int) $from->diff($to)->days + 1;
        if ($days > 366) {
            throw new InvalidArgumentException('El rango máximo permitido es de 366 días.');
        }

        return [$from, $to];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw new InvalidArgumentException('Formato de fecha inválido. Usa YYYY-MM-DD.');
        }

        return $date->setTime(0, 0, 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }

    /**
     * @param array<string,mixed> $dashboard
     * @return array<int, string>
     */
    private function buildExecutiveRecommendations(array $dashboard): array
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
        $analyticsSummary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
        $sources = is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [];
        $frictions = is_array($analytics['frictions'] ?? null) ? $analytics['frictions'] : [];

        $recommendations = [];
        $humanAppointments = (int) ($summary['human_attributed_appointments_strong'] ?? 0);
        $botAppointments = (int) ($summary['sigcenter_bookings_created'] ?? 0);
        $totalAppointments = $humanAppointments + $botAppointments;
        $peopleInbound = (int) ($summary['people_inbound'] ?? 0);
        $attributedBookingRate = $peopleInbound > 0 ? ($totalAppointments / $peopleInbound) * 100 : 0.0;

        if ($humanAppointments > 0) {
            $recommendations[] = sprintf(
                'Separar el análisis de cierre humano: hay %d citas Sigcenter atribuibles a conversaciones atendidas por agentes, frente a %d creadas por bot/integración.',
                $humanAppointments,
                $botAppointments
            );
        }

        if ($attributedBookingRate < 1.0) {
            $recommendations[] = 'Priorizar una revisión del flujo de captación: el volumen ya existe, pero la conversión atribuida total a cita sigue por debajo de 1%.';
        }

        if (((int) ($summary['conversations_abandoned_with_handoff'] ?? 0)) > 0) {
            $recommendations[] = 'Separar una cola operativa para handoffs vencidos: los casos con handoff sin respuesta después de 24 horas son la deuda humana más accionable.';
        }

        if (!empty($frictions) && ($frictions[0]['friction_state'] ?? null) === 'handoff_required') {
            $recommendations[] = 'Reducir dependencia de humano en FAQ y captación temprana para liberar capacidad del equipo y reservar handoff a cierres complejos.';
        }

        foreach ($sources as $source) {
            if (($source['source_category'] ?? '') === 'ad' && (int) ($source['bookings'] ?? 0) === 0 && (int) ($source['total'] ?? 0) > 50) {
                $recommendations[] = 'Auditar Ads por calidad, no por volumen: hoy generan muchas conversaciones, pero no están cerrando en cita dentro del periodo.';
                break;
            }
        }

        if (((float) ($summary['attention_rate'] ?? 0)) < 20.0) {
            $recommendations[] = 'Revisar cobertura humana y tiempos de respuesta: la capacidad actual del equipo no está absorbiendo la demanda que sí cae a circuito manual.';
        }

        return array_slice(array_values(array_unique($recommendations)), 0, 5);
    }
}
