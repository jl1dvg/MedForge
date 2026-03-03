<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmReadController
{
    public function leads(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $offset = max((int) $request->query('offset', 0), 0);
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $where = [];
        $bindings = [];

        if ($status !== '') {
            $where[] = 'l.status = ?';
            $bindings[] = $status;
        }

        if ($search !== '') {
            $where[] = '(l.hc_number LIKE ? OR l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)';
            $needle = '%' . $search . '%';
            array_push($bindings, $needle, $needle, $needle, $needle);
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $rows = DB::select(
                "SELECT l.id, l.hc_number, l.name, l.email, l.phone, l.status, l.source, l.assigned_to, l.created_at, l.updated_at
                 FROM crm_leads l
                 {$whereSql}
                 ORDER BY l.id DESC
                 LIMIT ? OFFSET ?",
                array_merge($bindings, [$limit, $offset])
            );

            $countRow = DB::selectOne(
                "SELECT COUNT(*) AS total FROM crm_leads l {$whereSql}",
                $bindings
            );

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'count' => count($rows),
                    'total' => (int) (($countRow->total ?? 0)),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'No se pudo cargar leads CRM',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function meta(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        try {
            $statuses = DB::select("SELECT DISTINCT status FROM crm_leads WHERE status IS NOT NULL AND status <> '' ORDER BY status");
            $sources = DB::select("SELECT DISTINCT source FROM crm_leads WHERE source IS NOT NULL AND source <> '' ORDER BY source");
            $owners = DB::select("SELECT id, username FROM users WHERE username IS NOT NULL ORDER BY username LIMIT 200");

            return response()->json([
                'data' => [
                    'statuses' => array_map(fn ($r) => (string) ($r->status ?? ''), $statuses),
                    'sources' => array_map(fn ($r) => (string) ($r->source ?? ''), $sources),
                    'owners' => $owners,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'No se pudo cargar meta CRM',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function metrics(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        try {
            $byStatusRows = DB::select('SELECT status, COUNT(*) total FROM crm_leads GROUP BY status');
            $openTasks = DB::selectOne("SELECT COUNT(*) total FROM crm_tasks WHERE status IS NULL OR status NOT IN ('done','completed','cerrada')");
            $openTickets = DB::selectOne("SELECT COUNT(*) total FROM crm_tickets WHERE status IS NULL OR status NOT IN ('closed','cerrado')");
            $activeProjects = DB::selectOne("SELECT COUNT(*) total FROM crm_projects WHERE status IS NULL OR status NOT IN ('completed','cancelled','completado','cancelado')");

            $byStatus = [];
            foreach ($byStatusRows as $row) {
                $key = (string) ($row->status ?? 'sin_estado');
                $byStatus[$key] = (int) ($row->total ?? 0);
            }

            return response()->json([
                'data' => [
                    'leads_by_status' => $byStatus,
                    'open_tasks' => (int) (($openTasks->total ?? 0)),
                    'open_tickets' => (int) (($openTickets->total ?? 0)),
                    'active_projects' => (int) (($activeProjects->total ?? 0)),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'No se pudo cargar métricas CRM',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
