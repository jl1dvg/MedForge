<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\WhatsApp\Services\KpiService;
use PDO;
use Throwable;

class KpiController extends BaseController
{
    private KpiService $kpis;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->kpis = new KpiService($pdo);
    }

    public function dashboard(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $this->render(BASE_PATH . '/modules/WhatsApp/views/dashboard.php', [
            'pageTitle' => 'Dashboard WhatsApp',
        ]);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $range = $this->resolveDateRange();
        if (!$range['ok']) {
            $this->json([
                'ok' => false,
                'error' => $range['error'] ?? 'Rango de fechas inválido.',
            ], 422);

            return;
        }

        $roleId = $this->normalizePositiveInt($this->getQueryInt('role_id'));
        $agentId = $this->normalizePositiveInt($this->getQueryInt('agent_id'));
        $slaTargetMinutes = $this->normalizePositiveInt($this->getQueryInt('sla_target_minutes'));

        try {
            $data = $this->kpis->buildDashboardKpis(
                $range['start'],
                $range['end'],
                $roleId,
                $agentId,
                $slaTargetMinutes
            );

            $this->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            $this->json([
                'ok' => false,
                'error' => 'No se pudieron calcular los KPIs de WhatsApp.',
                'detail' => $exception->getMessage(),
            ], 500);
        }
    }

    public function drilldown(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $metric = $this->getQuery('metric');
        if ($metric === null) {
            $this->json([
                'ok' => false,
                'error' => 'Debes indicar la métrica en ?metric=...',
            ], 422);

            return;
        }

        $range = $this->resolveDateRange();
        if (!$range['ok']) {
            $this->json([
                'ok' => false,
                'error' => $range['error'] ?? 'Rango de fechas inválido.',
            ], 422);

            return;
        }

        $roleId = $this->normalizePositiveInt($this->getQueryInt('role_id'));
        $agentId = $this->normalizePositiveInt($this->getQueryInt('agent_id'));
        $slaTargetMinutes = $this->normalizePositiveInt($this->getQueryInt('sla_target_minutes'));

        $page = $this->getQueryInt('page') ?? 1;
        if ($page <= 0) {
            $page = 1;
        }

        $limit = $this->getQueryInt('limit') ?? 50;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        try {
            $data = $this->kpis->buildDrilldown(
                $metric,
                $range['start'],
                $range['end'],
                $roleId,
                $agentId,
                $page,
                $limit,
                $slaTargetMinutes
            );

            $this->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            $this->json([
                'ok' => false,
                'error' => 'No se pudo generar el drill-down de KPI.',
                'detail' => $exception->getMessage(),
            ], 500);
        }
    }

    private function preventCaching(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function getQuery(string $key): ?string
    {
        if (!isset($_GET[$key])) {
            return null;
        }

        $value = trim((string) $_GET[$key]);

        return $value === '' ? null : $value;
    }

    private function getQueryInt(string $key): ?int
    {
        $value = $this->getQuery($key);

        return $value === null ? null : (int) $value;
    }

    private function normalizePositiveInt(?int $value): ?int
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return $value;
    }

    /**
     * @return array{ok:bool,start?:DateTimeImmutable,end?:DateTimeImmutable,error?:string}
     */
    private function resolveDateRange(): array
    {
        $today = new DateTimeImmutable('today');

        $fromRaw = $this->getQuery('date_from');
        $toRaw = $this->getQuery('date_to');

        $start = $fromRaw !== null ? $this->parseDate($fromRaw) : $today->modify('-29 days');
        $end = $toRaw !== null ? $this->parseDate($toRaw) : $today;

        if (!$start || !$end) {
            return [
                'ok' => false,
                'error' => 'Formato de fecha inválido. Usa YYYY-MM-DD.',
            ];
        }

        if ($start > $end) {
            return [
                'ok' => false,
                'error' => 'date_from no puede ser mayor que date_to.',
            ];
        }

        $days = (int) $start->diff($end)->days + 1;
        if ($days > 366) {
            return [
                'ok' => false,
                'error' => 'El rango máximo permitido es de 366 días.',
            ];
        }

        return [
            'ok' => true,
            'start' => $start,
            'end' => $end,
        ];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false) {
            return null;
        }

        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        return $date->setTime(0, 0, 0);
    }
}
