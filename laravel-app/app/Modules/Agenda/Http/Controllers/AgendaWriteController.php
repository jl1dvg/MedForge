<?php

namespace App\Modules\Agenda\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AgendaWriteController
{
    public function crearCita(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $validator = Validator::make($request->all(), [
            'hc_number' => ['required', 'string', 'max:64'],
            'paciente' => ['required', 'string', 'max:191'],
            'telefono' => ['nullable', 'string', 'max:64'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'tipo_atencion' => ['required', 'string', 'max:80'],
            'codigo_atencion' => ['nullable', 'string', 'max:80'],
            'detalle_atencion' => ['required', 'string', 'max:160'],
            'doctor' => ['nullable', 'string', 'max:191'],
            'sede' => ['nullable', 'string', 'max:191'],
            'id_sede' => ['nullable', 'integer'],
            'afiliacion' => ['nullable', 'string', 'max:64'],
            'estado_agenda' => ['nullable', 'string', Rule::in(['AGENDADO', 'CONFIRMADO', 'REAGENDADO'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Datos de cita inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $hcNumber = $this->normalizeText((string) $data['hc_number']);
        $patientName = $this->normalizeText((string) $data['paciente']);
        $procedure = $this->buildProcedureName(
            (string) $data['tipo_atencion'],
            (string) ($data['codigo_atencion'] ?? ''),
            (string) $data['detalle_atencion']
        );
        $now = now();

        try {
            $created = DB::transaction(function () use ($data, $hcNumber, $patientName, $procedure, $now): object {
                $this->upsertPatientData(
                    $hcNumber,
                    $patientName,
                    (string) ($data['telefono'] ?? ''),
                    (string) ($data['afiliacion'] ?? ''),
                    $now
                );

                $formId = $this->nextManualFormId();
                DB::table('procedimiento_proyectado')->insert(array_filter([
                    'form_id' => $formId,
                    'procedimiento_proyectado' => $procedure,
                    'doctor' => $this->nullableText((string) ($data['doctor'] ?? '')),
                    'hc_number' => $hcNumber,
                    'sede_departamento' => $this->nullableText((string) ($data['sede'] ?? '')),
                    'id_sede' => isset($data['id_sede']) ? (int) $data['id_sede'] : null,
                    'estado_agenda' => (string) ($data['estado_agenda'] ?? 'AGENDADO'),
                    'afiliacion' => $this->nullableText((string) ($data['afiliacion'] ?? '')),
                    'fecha' => (string) $data['fecha'],
                    'hora' => (string) $data['hora'],
                    'sigcenter_present' => true,
                    'sigcenter_last_seen_at' => $now,
                    'sigcenter_missing_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], static fn ($value): bool => $value !== null));

                $this->insertEstadoHistorial($formId, (string) ($data['estado_agenda'] ?? 'AGENDADO'));

                return DB::selectOne('SELECT * FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1', [$formId]);
            });

            return response()->json(['ok' => true, 'data' => $created], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo crear la cita', 'detail' => $e->getMessage()], 500);
        }
    }

    public function actualizarEstado(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $estado = trim((string) $request->input('estado_agenda', ''));

        if ($formId === '' || $estado === '') {
            return response()->json(['ok' => false, 'error' => 'form_id y estado_agenda son requeridos'], 422);
        }

        try {
            $current = DB::selectOne('SELECT form_id, estado_agenda FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1', [$formId]);
            if (!$current) {
                return response()->json(['ok' => false, 'error' => 'Procedimiento no encontrado'], 404);
            }

            DB::table('procedimiento_proyectado')
                ->where('form_id', $formId)
                ->update(['estado_agenda' => $estado]);

            $this->insertEstadoHistorial($formId, $estado);

            $updated = DB::selectOne('SELECT form_id, estado_agenda FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1', [$formId]);

            return response()->json([
                'ok' => true,
                'data' => [
                    'before' => $current,
                    'after' => $updated,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo actualizar estado agenda', 'detail' => $e->getMessage()], 500);
        }
    }

    private function upsertPatientData(string $hcNumber, string $patientName, string $telefono, string $afiliacion, mixed $now): void
    {
        if (!Schema::hasTable('patient_data')) {
            return;
        }

        $nameParts = $this->splitPatientName($patientName);
        $payload = [
            'fname' => $nameParts['fname'],
            'mname' => $nameParts['mname'],
            'lname' => $nameParts['lname'],
            'lname2' => $nameParts['lname2'],
            'celular' => $this->nullableText($telefono),
            'afiliacion' => $this->nullableText($afiliacion),
            'updated_at' => $now,
        ];

        $exists = DB::table('patient_data')->where('hc_number', $hcNumber)->exists();
        if ($exists) {
            DB::table('patient_data')->where('hc_number', $hcNumber)->update(array_filter(
                $payload,
                static fn ($value): bool => $value !== null
            ));
            return;
        }

        DB::table('patient_data')->insert(array_filter([
            'hc_number' => $hcNumber,
            ...$payload,
            'created_at' => $now,
        ], static fn ($value): bool => $value !== null));
    }

    /**
     * @return array{fname:string,mname:?string,lname:string,lname2:?string}
     */
    private function splitPatientName(string $patientName): array
    {
        $parts = preg_split('/\s+/u', $this->normalizeText($patientName)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return [
            'fname' => $parts[0] ?? $patientName,
            'mname' => count($parts) > 3 ? $parts[1] : null,
            'lname' => $parts[count($parts) > 1 ? count($parts) - 2 : 0] ?? $patientName,
            'lname2' => count($parts) > 2 ? $parts[count($parts) - 1] : null,
        ];
    }

    private function nextManualFormId(): int
    {
        $max = (int) DB::table('procedimiento_proyectado')->max('form_id');
        return max($max + 1, (int) now()->format('ymd000'));
    }

    private function buildProcedureName(string $tipoAtencion, string $codigoAtencion, string $detalleAtencion): string
    {
        $parts = array_filter([
            $this->normalizeText($tipoAtencion),
            $this->normalizeText($codigoAtencion),
            $this->normalizeText($detalleAtencion),
        ], static fn (string $value): bool => $value !== '');

        return mb_substr(implode(' - ', $parts), 0, 191, 'UTF-8');
    }

    private function insertEstadoHistorial(string|int $formId, string $estado): void
    {
        try {
            DB::table('procedimiento_proyectado_estado')->insert([
                'form_id' => $formId,
                'estado' => $estado,
                'fecha_hora_cambio' => now(),
            ]);
        } catch (\Throwable) {
            // Historial opcional según esquema legacy.
        }
    }

    private function nullableText(string $value): ?string
    {
        $normalized = $this->normalizeText($value);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeText(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        return $normalized !== null ? trim($normalized) : trim($value);
    }
}
