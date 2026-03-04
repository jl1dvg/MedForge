<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmWriteController
{
    public function createLead(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $payload = $request->all();
        $name = trim((string) ($payload['name'] ?? ''));
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));

        if ($name === '' || $hcNumber === '') {
            return response()->json(['ok' => false, 'error' => 'Los campos name y hc_number son requeridos'], 422);
        }

        try {
            $exists = DB::selectOne('SELECT id FROM crm_leads WHERE hc_number = ? LIMIT 1', [$hcNumber]);
            if ($exists) {
                return response()->json(['ok' => false, 'error' => 'Ya existe un lead para esta HC'], 422);
            }

            $columns = $this->tableColumns('crm_leads');
            $data = [
                'hc_number' => $hcNumber,
                'name' => $name,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'status' => $payload['status'] ?? 'new',
                'source' => $payload['source'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'customer_id' => $payload['customer_id'] ?? null,
            ];

            if (in_array('created_by', $columns, true)) {
                $data['created_by'] = LegacySessionAuth::userId($request);
            }

            if (in_array('created_at', $columns, true)) {
                $data['created_at'] = now();
            }
            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = now();
            }

            $filtered = array_filter($data, static fn ($_, $k) => in_array($k, $columns, true), ARRAY_FILTER_USE_BOTH);

            DB::table('crm_leads')->insert($filtered);
            $id = (int) DB::getPdo()->lastInsertId();
            $lead = DB::selectOne('SELECT * FROM crm_leads WHERE id = ? LIMIT 1', [$id]);

            return response()->json(['ok' => true, 'data' => $lead], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo crear el lead', 'detail' => $e->getMessage()], 500);
        }
    }

    public function updateLead(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $payload = $request->all();
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        if ($hcNumber === '') {
            return response()->json(['ok' => false, 'error' => 'hc_number es requerido'], 422);
        }

        try {
            $existing = DB::selectOne('SELECT id FROM crm_leads WHERE hc_number = ? LIMIT 1', [$hcNumber]);
            if (!$existing) {
                return response()->json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
            }

            $columns = $this->tableColumns('crm_leads');
            $allowed = ['name', 'email', 'phone', 'status', 'source', 'notes', 'assigned_to', 'customer_id'];
            $changes = [];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $payload) && in_array($field, $columns, true)) {
                    $changes[$field] = $payload[$field];
                }
            }

            if (in_array('updated_at', $columns, true)) {
                $changes['updated_at'] = now();
            }

            if ($changes === []) {
                return response()->json(['ok' => false, 'error' => 'Sin cambios para aplicar'], 422);
            }

            DB::table('crm_leads')->where('id', (int) $existing->id)->update($changes);

            $lead = DB::selectOne('SELECT * FROM crm_leads WHERE id = ? LIMIT 1', [(int) $existing->id]);
            return response()->json(['ok' => true, 'data' => $lead]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo actualizar el lead', 'detail' => $e->getMessage()], 500);
        }
    }

    public function updateLeadStatus(Request $request, int $id): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status === '') {
            return response()->json(['ok' => false, 'error' => 'El estado es requerido'], 422);
        }

        try {
            $existing = DB::selectOne('SELECT id FROM crm_leads WHERE id = ? LIMIT 1', [$id]);
            if (!$existing) {
                return response()->json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
            }

            $changes = ['status' => $status];
            if (in_array('updated_at', $this->tableColumns('crm_leads'), true)) {
                $changes['updated_at'] = now();
            }

            DB::table('crm_leads')->where('id', $id)->update($changes);
            $lead = DB::selectOne('SELECT * FROM crm_leads WHERE id = ? LIMIT 1', [$id]);
            return response()->json(['ok' => true, 'data' => $lead]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo actualizar el estado', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        try {
            $rows = DB::select("SHOW COLUMNS FROM {$table}");
        } catch (\Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = (string) data_get((array) $row, 'Field', '');
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    }
}
