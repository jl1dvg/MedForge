<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CrmProposalService
{
    /**
     * @return array<string,mixed>
     */
    public function find(int $id): array
    {
        $this->ensureSchema();

        $proposal = DB::table('crm_proposals as p')
            ->leftJoin('crm_leads as l', 'l.id', '=', 'p.lead_id')
            ->leftJoin('crm_opportunities as o', 'o.id', '=', 'p.crm_opportunity_id')
            ->leftJoin('crm_contacts as contact', 'contact.id', '=', 'o.contact_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'p.customer_id')
            ->where('p.id', $id)
            ->select([
                'p.*',
                DB::raw('COALESCE(l.name, contact.name) as lead_name'),
                DB::raw('COALESCE(l.email, contact.email) as lead_email'),
                DB::raw('COALESCE(l.phone, contact.phone) as lead_phone'),
                DB::raw('COALESCE(l.hc_number, contact.cedula) as lead_hc_number'),
                'o.title as opportunity_title',
                'o.stage as opportunity_stage',
                'o.source as opportunity_source',
                'o.source_id as opportunity_source_id',
                'o.source_type as opportunity_source_type',
                'c.name as customer_name',
            ])
            ->first();

        if (!$proposal) {
            throw new RuntimeException('Propuesta CRM no encontrada', 404);
        }

        $data = (array) $proposal;
        if (empty($data['public_hash'])) {
            $data['public_hash'] = $this->refreshPublicHash($id);
        }

        $items = DB::table('crm_proposal_items')
            ->where('proposal_id', $id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static fn(object $row): array => (array) $row)
            ->all();

        $data['items'] = $items;
        $data['public_url'] = $this->publicUrl($data);
        $data['pdf_url'] = '/v2/crm/proposals/' . $id . '/pdf';
        $data['solicitud_id'] = $this->resolveSolicitudId($data);
        $data['examen_id'] = $this->resolveExamenId($data);
        $data['clinical_context'] = $this->resolveClinicalContext($data);
        $data['responsible'] = $this->resolveResponsible($data, $data['clinical_context']);

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    public function findPublic(int $id, string $hash): array
    {
        $proposal = $this->find($id);
        if (!hash_equals((string) ($proposal['public_hash'] ?? ''), trim($hash))) {
            throw new RuntimeException('Propuesta no disponible', 404);
        }

        return $proposal;
    }

    /**
     * @param array<string,mixed> $proposal
     */
    public function publicUrl(array $proposal): string
    {
        return url('/proposal/' . (int) $proposal['id'] . '/' . (string) $proposal['public_hash']);
    }

    /**
     * @param array<string,mixed> $proposal
     */
    public function filename(array $proposal): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($proposal['proposal_number'] ?? $proposal['id'])) ?: 'propuesta';

        return 'propuesta_' . $number . '.pdf';
    }

    public function markSent(int $proposalId, string $channel, ?int $actorId): void
    {
        $this->ensureSchema();

        $updates = ['status' => 'sent'];
        if (Schema::hasColumn('crm_proposals', 'sent_at')) {
            $updates['sent_at'] = now()->toDateTimeString();
        }
        if (Schema::hasColumn('crm_proposals', 'updated_by')) {
            $updates['updated_by'] = $actorId;
        }
        if (Schema::hasColumn('crm_proposals', 'updated_at')) {
            $updates['updated_at'] = now()->toDateTimeString();
        }

        DB::table('crm_proposals')->where('id', $proposalId)->update($updates);
        $this->recordActivity($proposalId, 'sent_' . $channel, $actorId, ['channel' => $channel]);
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function recordActivity(int $proposalId, string $event, ?int $actorId = null, array $metadata = []): void
    {
        $this->ensureSchema();

        DB::table('crm_proposal_activity')->insert([
            'proposal_id' => $proposalId,
            'event' => $event,
            'actor_id' => $actorId,
            'metadata' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function ensureSchema(): void
    {
        if (!Schema::hasTable('crm_proposals')) {
            throw new RuntimeException('Tabla crm_proposals no disponible', 500);
        }

        if (!Schema::hasColumn('crm_proposals', 'public_hash')) {
            try {
                DB::statement('ALTER TABLE crm_proposals ADD COLUMN public_hash VARCHAR(64) NULL AFTER id');
                DB::statement('CREATE UNIQUE INDEX idx_crm_proposals_public_hash ON crm_proposals (public_hash)');
            } catch (Throwable) {
                if (!Schema::hasColumn('crm_proposals', 'public_hash')) {
                    throw new RuntimeException('No se pudo preparar el hash público de propuestas', 500);
                }
            }
        }

        if (!Schema::hasColumn('crm_proposals', 'crm_opportunity_id')) {
            try {
                Schema::table('crm_proposals', function ($table): void {
                    $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index()->after('lead_id');
                });
            } catch (Throwable) {
                if (!Schema::hasColumn('crm_proposals', 'crm_opportunity_id')) {
                    throw new RuntimeException('No se pudo preparar el vínculo de oportunidad en propuestas', 500);
                }
            }
        }

        if (!Schema::hasTable('crm_proposal_activity')) {
            try {
                DB::statement(
                    'CREATE TABLE crm_proposal_activity (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        proposal_id BIGINT UNSIGNED NOT NULL,
                        event VARCHAR(64) NOT NULL,
                        actor_id INT NULL,
                        metadata JSON NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_proposal_activity_proposal (proposal_id),
                        INDEX idx_proposal_activity_event (event)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            } catch (Throwable) {
                if (!Schema::hasTable('crm_proposal_activity')) {
                    throw new RuntimeException('No se pudo preparar el historial de propuestas', 500);
                }
            }
        }
    }

    private function refreshPublicHash(int $id): string
    {
        $hash = Str::random(40);
        DB::table('crm_proposals')->where('id', $id)->update(['public_hash' => $hash]);

        return $hash;
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private function resolveSolicitudId(array $proposal): ?int
    {
        $opportunityId = (int) ($proposal['crm_opportunity_id'] ?? 0);
        if ($opportunityId > 0) {
            $solicitudId = $this->resolveSolicitudIdByOpportunity($opportunityId);
            if ($solicitudId !== null) {
                return $solicitudId;
            }
        }

        $leadId = (int) ($proposal['lead_id'] ?? 0);
        if ($leadId <= 0 || !Schema::hasTable('solicitud_crm_detalles')) {
            return null;
        }

        $row = DB::table('solicitud_crm_detalles')
            ->where('crm_lead_id', $leadId)
            ->orderByDesc('solicitud_id')
            ->first(['solicitud_id']);

        return $row ? (int) $row->solicitud_id : null;
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private function resolveExamenId(array $proposal): ?int
    {
        $opportunityId = (int) ($proposal['crm_opportunity_id'] ?? 0);
        if ($opportunityId > 0) {
            $examenId = $this->resolveExamenIdByOpportunity($opportunityId);
            if ($examenId !== null) {
                return $examenId;
            }
        }

        $leadId = (int) ($proposal['lead_id'] ?? 0);
        if ($leadId <= 0 || !Schema::hasTable('examen_crm_detalles')) {
            return null;
        }

        $row = DB::table('examen_crm_detalles')
            ->where('crm_lead_id', $leadId)
            ->orderByDesc('examen_id')
            ->first(['examen_id']);

        return $row ? (int) $row->examen_id : null;
    }

    private function resolveSolicitudIdByOpportunity(int $opportunityId): ?int
    {
        if (Schema::hasTable('solicitud_procedimiento') && Schema::hasColumn('solicitud_procedimiento', 'crm_opportunity_id')) {
            $id = DB::table('solicitud_procedimiento')
                ->where('crm_opportunity_id', $opportunityId)
                ->orderByDesc('id')
                ->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        if (Schema::hasTable('solicitud_crm_detalles') && Schema::hasColumn('solicitud_crm_detalles', 'crm_opportunity_id')) {
            $id = DB::table('solicitud_crm_detalles')
                ->where('crm_opportunity_id', $opportunityId)
                ->orderByDesc('solicitud_id')
                ->value('solicitud_id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    private function resolveExamenIdByOpportunity(int $opportunityId): ?int
    {
        if (Schema::hasTable('consulta_examenes') && Schema::hasColumn('consulta_examenes', 'crm_opportunity_id')) {
            $id = DB::table('consulta_examenes')
                ->where('crm_opportunity_id', $opportunityId)
                ->orderByDesc('id')
                ->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        if (Schema::hasTable('examen_crm_detalles') && Schema::hasColumn('examen_crm_detalles', 'crm_opportunity_id')) {
            $id = DB::table('examen_crm_detalles')
                ->where('crm_opportunity_id', $opportunityId)
                ->orderByDesc('examen_id')
                ->value('examen_id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private function resolveClinicalContext(array $proposal): array
    {
        $solicitudId = (int) ($proposal['solicitud_id'] ?? 0);
        if ($solicitudId > 0) {
            $context = $this->resolveSolicitudClinicalContext($solicitudId, $proposal);
            if ($context !== []) {
                return $context;
            }
        }

        $examenId = (int) ($proposal['examen_id'] ?? 0);
        if ($examenId > 0) {
            $context = $this->resolveExamenClinicalContext($examenId, $proposal);
            if ($context !== []) {
                return $context;
            }
        }

        return [
            'type' => 'proposal',
            'type_label' => 'PROPUESTA',
            'fecha' => $proposal['created_at'] ?? null,
            'paciente' => $proposal['lead_name'] ?? $proposal['customer_name'] ?? 'Paciente',
            'seguro' => null,
            'medico' => null,
            'ojo' => null,
            'diagnosticos' => [],
            'procedimiento' => $proposal['title'] ?? 'Propuesta clínica',
            'responsable_id' => null,
        ];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private function resolveSolicitudClinicalContext(int $solicitudId, array $proposal): array
    {
        if (!Schema::hasTable('solicitud_procedimiento')) {
            return [];
        }

        $row = DB::table('solicitud_procedimiento as sp')
            ->leftJoin('patient_data as pd', 'sp.hc_number', '=', 'pd.hc_number')
            ->leftJoin('consulta_data as cd', function ($join): void {
                $join->on('sp.hc_number', '=', 'cd.hc_number')
                    ->on('sp.form_id', '=', 'cd.form_id');
            })
            ->leftJoin('solicitud_crm_detalles as detalles', 'detalles.solicitud_id', '=', 'sp.id')
            ->where('sp.id', $solicitudId)
            ->select([
                'sp.id',
                'sp.hc_number',
                'sp.form_id',
                'sp.doctor',
                'sp.procedimiento',
                'sp.ojo',
                'sp.created_at',
                'cd.fecha as fecha_consulta',
                'pd.afiliacion',
                'pd.fname',
                'pd.mname',
                'pd.lname',
                'pd.lname2',
                'detalles.responsable_id',
            ])
            ->first();

        if (!$row) {
            return [];
        }

        $data = (array) $row;
        $diagnosticos = $this->diagnosticosByFormId((string) ($data['form_id'] ?? ''));

        return [
            'type' => 'solicitud',
            'type_label' => 'CIRUGIA',
            'id' => (int) $data['id'],
            'hc_number' => $data['hc_number'] ?? null,
            'form_id' => $data['form_id'] ?? null,
            'fecha' => $data['fecha_consulta'] ?? $data['created_at'] ?? $proposal['created_at'] ?? null,
            'paciente' => $this->fullNameFromParts($data) ?: (string) ($proposal['lead_name'] ?? $proposal['customer_name'] ?? 'Paciente'),
            'seguro' => $data['afiliacion'] ?? null,
            'medico' => $data['doctor'] ?? null,
            'ojo' => $data['ojo'] ?? null,
            'diagnosticos' => $diagnosticos,
            'procedimiento' => $data['procedimiento'] ?? $proposal['title'] ?? null,
            'responsable_id' => isset($data['responsable_id']) ? (int) $data['responsable_id'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private function resolveExamenClinicalContext(int $examenId, array $proposal): array
    {
        if (!Schema::hasTable('consulta_examenes')) {
            return [];
        }

        $row = DB::table('consulta_examenes as ce')
            ->leftJoin('patient_data as pd', 'ce.hc_number', '=', 'pd.hc_number')
            ->leftJoin('examen_crm_detalles as detalles', 'detalles.examen_id', '=', 'ce.id')
            ->where('ce.id', $examenId)
            ->select([
                'ce.id',
                'ce.hc_number',
                'ce.form_id',
                'ce.consulta_fecha',
                'ce.created_at',
                'ce.doctor',
                'ce.solicitante',
                'ce.examen_codigo',
                'ce.examen_nombre',
                'ce.lateralidad',
                'pd.afiliacion',
                'pd.fname',
                'pd.mname',
                'pd.lname',
                'pd.lname2',
                'detalles.responsable_id',
            ])
            ->first();

        if (!$row) {
            return [];
        }

        $data = (array) $row;
        $examName = trim((string) (($data['examen_codigo'] ?? '') . ' - ' . ($data['examen_nombre'] ?? '')), " \t\n\r\0\x0B-");

        return [
            'type' => 'examen',
            'type_label' => 'EXAMEN',
            'id' => (int) $data['id'],
            'hc_number' => $data['hc_number'] ?? null,
            'form_id' => $data['form_id'] ?? null,
            'fecha' => $data['consulta_fecha'] ?? $data['created_at'] ?? $proposal['created_at'] ?? null,
            'paciente' => $this->fullNameFromParts($data) ?: (string) ($proposal['lead_name'] ?? $proposal['customer_name'] ?? 'Paciente'),
            'seguro' => $data['afiliacion'] ?? null,
            'medico' => $data['solicitante'] ?? $data['doctor'] ?? null,
            'ojo' => $data['lateralidad'] ?? null,
            'diagnosticos' => $this->diagnosticosByFormId((string) ($data['form_id'] ?? '')),
            'procedimiento' => $examName !== '' ? $examName : ($proposal['title'] ?? null),
            'responsable_id' => isset($data['responsable_id']) ? (int) $data['responsable_id'] : null,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function diagnosticosByFormId(string $formId): array
    {
        $formId = trim($formId);
        if ($formId === '' || !Schema::hasTable('diagnosticos_asignados')) {
            return [];
        }

        return DB::table('diagnosticos_asignados')
            ->where('form_id', $formId)
            ->orderBy('id')
            ->get()
            ->map(function (object $row): string {
                $data = (array) $row;
                $code = trim((string) ($data['dx_code'] ?? $data['codigo'] ?? $data['cie10'] ?? $data['code'] ?? ''));
                $description = trim((string) ($data['descripcion'] ?? $data['diagnostico'] ?? $data['nombre'] ?? $data['dx'] ?? ''));

                return trim($code . ($code !== '' && $description !== '' ? ' - ' : '') . $description);
            })
            ->filter(static fn(string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<string,mixed> $data
     */
    private function fullNameFromParts(array $data): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $data['fname'] ?? null,
            $data['mname'] ?? null,
            $data['lname'] ?? null,
            $data['lname2'] ?? null,
        ], static fn($value): bool => trim((string) $value) !== ''))) ?? '');
    }

    /**
     * @param array<string,mixed> $proposal
     * @param array<string,mixed> $clinicalContext
     * @return array<string,mixed>
     */
    private function resolveResponsible(array $proposal, array $clinicalContext): array
    {
        $candidateIds = [
            $proposal['created_by'] ?? null,
            $clinicalContext['responsable_id'] ?? null,
            $proposal['updated_by'] ?? null,
        ];

        foreach ($candidateIds as $candidateId) {
            $userId = (int) ($candidateId ?? 0);
            if ($userId <= 0 || !Schema::hasTable('users')) {
                continue;
            }

            $row = DB::table('users')->where('id', $userId)->first($this->userSelectColumns());
            if (!$row) {
                continue;
            }

            $data = (array) $row;
            $name = trim((string) ($data['full_name'] ?? $data['nombre'] ?? ''));
            if ($name === '') {
                $name = $this->fullNameFromParts($data);
            }

            return [
                'id' => $userId,
                'name' => $name !== '' ? $name : 'Equipo CIVE',
                'title' => trim((string) ($data['position'] ?? $data['cargo'] ?? '')) ?: 'COORDINACIÓN QUIRÚRGICA',
                'email' => $data['email'] ?? null,
                'signature_path' => $this->resolvePublicAssetPath((string) ($data['seal_signature_path'] ?? $data['signature_path'] ?? $data['firma'] ?? '')),
            ];
        }

        return [
            'id' => null,
            'name' => 'Equipo CIVE',
            'title' => 'COORDINACIÓN QUIRÚRGICA',
            'email' => null,
            'signature_path' => null,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function userSelectColumns(): array
    {
        $columns = ['id'];
        foreach (['nombre', 'full_name', 'fname', 'mname', 'lname', 'lname2', 'email', 'position', 'cargo', 'firma', 'signature_path', 'seal_signature_path'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function resolvePublicAssetPath(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1 || (str_starts_with($value, DIRECTORY_SEPARATOR) && is_file($value))) {
            return $value;
        }

        $relative = ltrim(parse_url($value, PHP_URL_PATH) ?: $value, '/');
        $candidates = array_values(array_unique([
            $relative,
            'uploads/users/' . basename($relative),
            'uploads/' . basename($relative),
        ]));

        $roots = [];
        if (function_exists('public_path')) {
            $roots[] = public_path();
        }
        if (function_exists('base_path')) {
            $roots[] = base_path('../public');
        }

        foreach ($candidates as $candidate) {
            foreach (array_unique($roots) as $root) {
                $absolute = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
                if (is_file($absolute)) {
                    return $absolute;
                }
            }
        }

        return null;
    }
}
