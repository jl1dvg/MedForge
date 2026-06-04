<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CrmCaseService
{
    public function __construct(
        private readonly CrmCaseActivityService $activityService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(string $sourceType, int $sourceId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        if (!Schema::hasTable('solicitud_procedimiento')) {
            throw new RuntimeException('Caso no encontrado');
        }

        $case = DB::table('solicitud_procedimiento')
            ->where('id', $sourceId)
            ->first();

        if ($case === null) {
            throw new RuntimeException('Caso no encontrado');
        }

        $caseRow = (array) $case;
        $detailRow = $this->solicitudDetail($sourceId);
        $contacts = $this->contacts($caseRow, $detailRow);
        $notes = $this->activityService->notesForCase($normalizedSourceType, $sourceId);
        $tasks = $this->activityService->tasksForCase($normalizedSourceType, $sourceId);

        return [
            'case' => [
                'case_id' => $normalizedSourceType . ':' . $sourceId,
                'source_type' => $normalizedSourceType,
                'source_id' => $sourceId,
                'solicitud_id' => $sourceId,
                'paciente_id' => $this->pacienteId($caseRow),
                'form_id' => $this->nullableInt($caseRow['form_id'] ?? null),
                'hc_number' => $caseRow['hc_number'] ?? null,
                'patient_name' => $this->patientName($caseRow),
                'stage' => $detailRow['pipeline_stage'] ?? $caseRow['estado'] ?? null,
                'site' => $this->firstFilled($caseRow, ['sede', 'sede_departamento', 'id_sede']),
            ],
            'crm' => [
                'responsible_id' => $this->nullableInt($detailRow['responsable_id'] ?? null),
                'responsible_name' => $this->responsibleName($detailRow),
                'source' => $this->valueOrNull($detailRow['fuente'] ?? null),
                'pipeline_stage' => $this->valueOrNull($detailRow['pipeline_stage'] ?? null),
                'insurance_company' => $this->firstFilled($detailRow, [
                    'insurance_company',
                    'empresa_seguro',
                    'aseguradora',
                    'seguro_empresa',
                ]) ?? '',
                'insurance_plan' => $this->firstFilled($detailRow, [
                    'insurance_plan',
                    'plan_seguro',
                    'seguro_plan',
                ]) ?? '',
                'insurance_code' => $this->firstFilled($detailRow, [
                    'insurance_code',
                    'codigo_seguro',
                    'insurance_codigo',
                    'seguro_codigo',
                ]) ?? '',
            ],
            'contacts' => $contacts,
            'notes' => $notes,
            'tasks' => $tasks,
            'activity' => $this->activityService->forCase($normalizedSourceType, $sourceId),
            'proposals' => $this->proposals($sourceId, $caseRow, $detailRow),
            'documents' => $this->documents($sourceId),
        ];
    }

    private function normalizeSourceType(string $sourceType): string
    {
        return match (strtolower(trim($sourceType))) {
            'solicitud', 'solicitud_procedimiento', 'solicitudes' => 'solicitud',
            default => strtolower(trim($sourceType)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitudDetail(int $sourceId): array
    {
        if (!Schema::hasTable('solicitud_crm_detalles') || !Schema::hasColumn('solicitud_crm_detalles', 'solicitud_id')) {
            return [];
        }

        $row = DB::table('solicitud_crm_detalles')
            ->where('solicitud_id', $sourceId)
            ->orderBy(Schema::hasColumn('solicitud_crm_detalles', 'updated_at') ? 'updated_at' : 'solicitud_id', 'desc')
            ->first();

        return $row === null ? [] : (array) $row;
    }

    /**
     * @param array<string, mixed> $caseRow
     */
    private function pacienteId(array $caseRow): ?int
    {
        foreach (['paciente_id', 'patient_id'] as $column) {
            if (Schema::hasColumn('solicitud_procedimiento', $column) && array_key_exists($column, $caseRow)) {
                return $this->nullableInt($caseRow[$column]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $caseRow
     */
    private function patientName(array $caseRow): ?string
    {
        $direct = $this->firstFilled($caseRow, ['patient_name', 'full_name', 'paciente_nombre']);
        if ($direct !== null) {
            return $direct;
        }

        $hcNumber = trim((string) ($caseRow['hc_number'] ?? ''));
        if ($hcNumber === '' || !Schema::hasTable('patient_data') || !Schema::hasColumn('patient_data', 'hc_number')) {
            return null;
        }

        $patient = DB::table('patient_data')->where('hc_number', $hcNumber)->first();
        if ($patient === null) {
            return null;
        }

        $patientRow = (array) $patient;
        $fullName = $this->firstFilled($patientRow, ['full_name', 'patient_full_name']);
        if ($fullName !== null) {
            return $fullName;
        }

        $parts = [];
        foreach (['fname', 'mname', 'lname', 'lname2'] as $column) {
            $value = trim((string) ($patientRow[$column] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $caseRow
     * @param array<string, mixed> $detailRow
     * @return array<string, mixed>
     */
    private function contacts(array $caseRow, array $detailRow): array
    {
        $primaryPhone = $this->firstFilled($detailRow, ['contacto_telefono', 'telefono', 'primary_phone']);
        $primaryEmail = $this->firstFilled($detailRow, ['contacto_email', 'email', 'primary_email']);

        return [
            'primary_phone' => $primaryPhone ?? $this->firstFilled($caseRow, ['telefono', 'paciente_telefono']),
            'alternate_phones' => [],
            'primary_email' => $primaryEmail ?? $this->firstFilled($caseRow, ['email', 'paciente_email']),
            'alternate_emails' => [],
        ];
    }

    /**
     * @param array<string, mixed> $detailRow
     */
    private function responsibleName(array $detailRow): ?string
    {
        $name = $this->firstFilled($detailRow, ['responsable_nombre', 'responsible_name']);
        if ($name !== null) {
            return $name;
        }

        $responsibleId = $this->nullableInt($detailRow['responsable_id'] ?? null);
        if ($responsibleId === null || !Schema::hasTable('users')) {
            return null;
        }

        $select = ['id'];
        foreach (['name', 'username', 'nombre'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $select[] = $column;
            }
        }

        $user = DB::table('users')->select($select)->where('id', $responsibleId)->first();
        if ($user === null) {
            return null;
        }

        return $this->firstFilled((array) $user, ['name', 'username', 'nombre']);
    }

    /**
     * @param array<string, mixed> $caseRow
     * @param array<string, mixed> $detailRow
     * @return array<int, array<string, mixed>>
     */
    private function proposals(int $sourceId, array $caseRow, array $detailRow): array
    {
        if (!Schema::hasTable('crm_proposals')) {
            return [];
        }

        $query = DB::table('crm_proposals');
        $hasCondition = false;

        $query->where(function ($where) use ($sourceId, $caseRow, $detailRow, &$hasCondition): void {
            if ($this->hasColumns('crm_proposals', ['source_type', 'source_id'])) {
                $hasCondition = true;
                $where->orWhere(function ($source) use ($sourceId): void {
                    $source->whereIn('source_type', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('source_id', $sourceId);
                });
            }

            if (Schema::hasColumn('crm_proposals', 'form_id') && isset($caseRow['form_id'])) {
                $hasCondition = true;
                $where->orWhere('form_id', $caseRow['form_id']);
            }

            if (Schema::hasColumn('crm_proposals', 'crm_opportunity_id') && isset($detailRow['crm_opportunity_id'])) {
                $hasCondition = true;
                $where->orWhere('crm_opportunity_id', $detailRow['crm_opportunity_id']);
            }
        });

        if (!$hasCondition) {
            return [];
        }

        return $query->orderBy(Schema::hasColumn('crm_proposals', 'created_at') ? 'created_at' : 'id', 'desc')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(int $sourceId): array
    {
        if (!Schema::hasTable('solicitud_crm_adjuntos') || !Schema::hasColumn('solicitud_crm_adjuntos', 'solicitud_id')) {
            return [];
        }

        $query = DB::table('solicitud_crm_adjuntos')->where('solicitud_id', $sourceId);
        if (Schema::hasColumn('solicitud_crm_adjuntos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy(Schema::hasColumn('solicitud_crm_adjuntos', 'created_at') ? 'created_at' : 'id', 'desc')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     */
    private function firstFilled(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function valueOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<int, string> $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
