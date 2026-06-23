<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Modules\Solicitudes\Services\Traits\SolicitudesDbHelperTrait;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;

/**
 * Handles pure CRM data writes for solicitudes:
 * details, notes, tasks, proposals, attachments, and calendar blocks.
 * Extracted from SolicitudesWriteParityService.
 */
class SolicitudesCrmWriteService
{
    use SolicitudesDbHelperTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly SolicitudesReadParityService $readService,
    ) {
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /** @return array<string, mixed> */
    public function crmGuardarDetalles(int $solicitudId, array $payload, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $now              = date('Y-m-d H:i:s');
        $followers        = $this->normalizeFollowers($payload['seguidores'] ?? []);
        $existing         = $this->fetchCrmDetalleRow($solicitudId);

        // solicitud_crm_detalles: solicitud_id, crm_lead_id, crm_project_id, responsable_id,
        // contacto_email, contacto_telefono, fuente, pipeline_stage, followers, created_at, updated_at
        $data = [
            'crm_lead_id'       => $this->nullableInt($payload['crm_lead_id'] ?? null),
            'crm_project_id'    => $existing['crm_project_id'] ?? null,
            'responsable_id'    => $this->nullableInt($payload['responsable_id'] ?? null),
            'pipeline_stage'    => $this->nullableString($payload['pipeline_stage'] ?? null),
            'fuente'            => $this->nullableString($payload['fuente'] ?? null),
            'contacto_email'    => $this->nullableString($payload['contacto_email'] ?? null),
            'contacto_telefono' => $this->nullableString($payload['contacto_telefono'] ?? null),
            'followers'         => $followers !== [] ? json_encode($followers, JSON_UNESCAPED_UNICODE) : null,
            'updated_at'        => $now,
        ];
        if (Schema::hasColumn('solicitud_crm_detalles', 'crm_opportunity_id')) {
            $data['crm_opportunity_id'] = $this->nullableInt($payload['crm_opportunity_id'] ?? ($existing['crm_opportunity_id'] ?? null));
        }

        if ($existing === null) {
            DB::table('solicitud_crm_detalles')->insert(array_merge(
                ['solicitud_id' => $solicitudId, 'created_at' => $now],
                $data
            ));
        } else {
            DB::table('solicitud_crm_detalles')
                ->where('solicitud_id', $solicitudId)
                ->update($data);
        }

        if (isset($payload['custom_fields']) && is_array($payload['custom_fields'])) {
            $this->guardarCrmMeta($solicitudId, $payload['custom_fields']);
        }

        return $this->readService->crmResumen($solicitudId);
    }

    /** @return array<string, mixed> */
    public function crmRegistrarBloqueo(int $solicitudId, array $payload, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $base = $this->fetchSolicitudBloqueoBase($solicitudId);
        if ($base === null) {
            throw new RuntimeException('No se encontró la solicitud para bloquear agenda');
        }

        $inicio = $this->parseFlexibleDateTime($payload['fecha_inicio'] ?? ($base['fecha_programada'] ?? null));
        if (!$inicio instanceof DateTimeImmutable) {
            throw new RuntimeException('La fecha/hora de inicio es obligatoria');
        }

        $fin = $this->parseFlexibleDateTime($payload['fecha_fin'] ?? null);
        if (!$fin instanceof DateTimeImmutable) {
            $duracionMinutos = max(15, (int) ($payload['duracion_minutos'] ?? 60));
            $fin = $inicio->add(new DateInterval(sprintf('PT%dM', $duracionMinutos)));
        }

        if ($fin <= $inicio) {
            throw new RuntimeException('La hora de fin debe ser posterior al inicio');
        }

        $doctor = $this->nullableString($payload['doctor'] ?? ($base['doctor'] ?? null));
        $sala   = $this->nullableString($payload['sala'] ?? ($payload['quirofano'] ?? ($base['sala'] ?? null)));
        $motivo = $this->nullableString($payload['motivo'] ?? null);

        // crm_calendar_blocks: id, solicitud_id, doctor, sala, fecha_inicio, fecha_fin, motivo, created_by, created_at
        $bloqueoId = DB::table('crm_calendar_blocks')->insertGetId([
            'solicitud_id' => $solicitudId,
            'doctor'       => $doctor,
            'sala'         => $sala,
            'fecha_inicio' => $inicio->format('Y-m-d H:i:s'),
            'fecha_fin'    => $fin->format('Y-m-d H:i:s'),
            'motivo'       => $motivo,
            'created_by'   => $userId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $resumen = $this->readService->crmResumen($solicitudId);
        $resumen['ultimo_bloqueo'] = [
            'id'           => $bloqueoId > 0 ? $bloqueoId : null,
            'solicitud_id' => $solicitudId,
            'doctor'       => $doctor,
            'sala'         => $sala,
            'fecha_inicio' => $inicio->format(DateTimeImmutable::ATOM),
            'fecha_fin'    => $fin->format(DateTimeImmutable::ATOM),
            'motivo'       => $motivo,
            'created_by'   => $userId,
        ];

        return $resumen;
    }

    /** @return array<string, mixed> */
    public function crmSubirAdjunto(
        int $solicitudId,
        string $nombreOriginal,
        string $rutaRelativa,
        ?string $mimeType,
        ?int $tamanoBytes,
        ?int $usuarioId,
        ?string $descripcion = null,
    ): array {
        $this->assertSolicitudExists($solicitudId);

        // solicitud_crm_adjuntos: id, solicitud_id, nombre_original, ruta_relativa,
        // mime_type, tamano_bytes, descripcion, subido_por, created_at (sin updated_at)
        DB::table('solicitud_crm_adjuntos')->insert([
            'solicitud_id'    => $solicitudId,
            'nombre_original' => $nombreOriginal,
            'ruta_relativa'   => $rutaRelativa,
            'mime_type'       => $mimeType,
            'tamano_bytes'    => $tamanoBytes,
            'descripcion'     => $this->nullableString($descripcion),
            'subido_por'      => $usuarioId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $this->readService->crmResumen($solicitudId);
    }

    /** @return array<string, mixed> */
    public function crmAgregarNota(int $solicitudId, string $nota, ?int $autorId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $nota = trim(strip_tags($nota));
        if ($nota === '') {
            throw new RuntimeException('La nota no puede estar vacía');
        }

        // solicitud_crm_notas confirmed columns: id, solicitud_id, autor_id, nota, created_at
        DB::table('solicitud_crm_notas')->insert([
            'solicitud_id' => $solicitudId,
            'nota'         => $nota,
            'autor_id'     => $autorId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->readService->crmResumen($solicitudId);
    }

    /** @return array<string, mixed> */
    public function crmGuardarTarea(int $solicitudId, array $payload, ?int $autorId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $title  = trim((string) ($payload['titulo'] ?? $payload['title'] ?? ''));
        if ($title === '') {
            $title = 'Tarea solicitud #' . $solicitudId;
        }

        $status = strtolower(trim((string) ($payload['estado'] ?? $payload['status'] ?? 'pendiente')));
        if (!in_array($status, ['pendiente', 'en_progreso', 'en_proceso', 'completada', 'cancelada'], true)) {
            $status = 'pendiente';
        }

        $now = date('Y-m-d H:i:s');

        // crm_tasks confirmed columns — checklist_slug / task_key do NOT exist as columns;
        // they are stored exclusively inside the metadata JSON field.
        $checklistSlug = $this->nullableString($payload['checklist_slug'] ?? $payload['etapa_slug'] ?? null);
        $taskKey       = $this->nullableString($payload['task_key'] ?? null);
        $metadata      = [];
        if ($checklistSlug !== null) {
            $metadata['checklist_slug']  = $checklistSlug;
            $metadata['checklist_label'] = $title;
        }
        if ($taskKey !== null) {
            $metadata['task_key'] = $taskKey;
        }

        DB::table('crm_tasks')->insert([
            'company_id'    => $this->resolveCompanyId(),
            'source_module' => 'solicitudes',
            'source_ref_id' => (string) $solicitudId,
            'title'         => $title,
            'description'   => $this->nullableString($payload['descripcion'] ?? $payload['description'] ?? null),
            'status'        => $status,
            'assigned_to'   => $this->nullableInt($payload['assigned_to'] ?? $payload['asignado_a'] ?? null),
            'created_by'    => $autorId,
            'due_date'      => $this->normalizeDate($payload['due_date'] ?? $payload['fecha_vencimiento'] ?? null),
            'due_at'        => $this->normalizeDateTime($payload['due_at'] ?? $payload['fecha_hora_vencimiento'] ?? null),
            'remind_at'     => $this->normalizeDateTime($payload['remind_at'] ?? null),
            'priority'      => $this->normalizeTaskPriority($payload['priority'] ?? $payload['prioridad'] ?? null),
            'completed_at'  => $status === 'completada' ? $now : null,
            'metadata'      => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $this->readService->crmResumen($solicitudId);
    }

    /** @return array<string, mixed> */
    public function crmCrearPropuesta(int $solicitudId, array $payload, ?int $autorId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $detalle = $this->fetchCrmDetalleRow($solicitudId);
        $opportunityId = $this->nullableInt($payload['crm_opportunity_id'] ?? null)
            ?? $this->nullableInt($detalle['crm_opportunity_id'] ?? null)
            ?? $this->resolveSolicitudOpportunityId($solicitudId);

        $leadId = $this->nullableInt($payload['lead_id'] ?? null);
        if ($leadId === null) {
            $leadId  = $this->nullableInt($detalle['crm_lead_id'] ?? null);
        }
        if ($leadId === null && $opportunityId === null) {
            $leadId = $this->autoCrearLeadParaSolicitud($solicitudId, $autorId);
        }

        $lead = $leadId !== null ? $this->fetchCrmLead($leadId) : null;
        if ($leadId !== null && $lead === null) {
            throw new RuntimeException('El lead CRM vinculado no existe');
        }

        $title = $this->nullableString($payload['title'] ?? null);
        if ($title === null) {
            throw new RuntimeException('La propuesta necesita un título');
        }

        $items = $this->normalizeProposalItems($payload['items'] ?? []);
        if ($items === []) {
            throw new RuntimeException('La propuesta debe incluir al menos un ítem');
        }

        $taxRate  = max(0.0, min(100.0, (float) ($payload['tax_rate'] ?? 0)));
        $totals   = $this->calculateProposalTotals($items, $taxRate);
        $number   = $this->nextProposalNumber();
        $now      = date('Y-m-d H:i:s');
        $currency = strtoupper(substr(trim((string) ($payload['currency'] ?? 'USD')), 0, 3)) ?: 'USD';

        $proposalId = DB::transaction(function () use ($leadId, $opportunityId, $lead, $title, $number, $taxRate, $totals, $currency, $items, $autorId, $now, $payload): int {
            $proposalPayload = [
                'proposal_number'   => $number['number'],
                'proposal_year'     => $number['year'],
                'sequence'          => $number['sequence'],
                'lead_id'           => $leadId,
                'customer_id'       => $this->nullableInt($payload['customer_id'] ?? ($lead['customer_id'] ?? null)),
                'title'             => $title,
                'status'            => 'draft',
                'currency'          => $currency,
                'subtotal'          => $totals['subtotal'],
                'discount_total'    => $totals['discount'],
                'tax_rate'          => $taxRate,
                'tax_total'         => $totals['tax'],
                'total'             => $totals['total'],
                'valid_until'       => $this->normalizeDate($payload['valid_until'] ?? null),
                'notes'             => $this->nullableString($payload['notes'] ?? null),
                'terms'             => $this->nullableString($payload['terms'] ?? null),
                'packages_snapshot' => null,
                'created_by'        => $autorId,
                'updated_by'        => $autorId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
            if (Schema::hasColumn('crm_proposals', 'crm_opportunity_id')) {
                $proposalPayload['crm_opportunity_id'] = $opportunityId;
            }

            $id = DB::table('crm_proposals')->insertGetId($proposalPayload);

            if ($id <= 0) {
                throw new RuntimeException('No se pudo crear la propuesta CRM');
            }

            $itemRows = [];
            foreach ($items as $index => $item) {
                $itemRows[] = [
                    'proposal_id'      => $id,
                    'code_id'          => $item['code_id'],
                    'package_id'       => $item['package_id'],
                    'description'      => $item['description'],
                    'quantity'         => $item['quantity'],
                    'unit_price'       => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'],
                    'sort_order'       => $index,
                    'metadata'         => null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
            DB::table('crm_proposal_items')->insert($itemRows);

            return $id;
        });

        $summary = $this->readService->crmResumen($solicitudId);
        $summary['ultima_propuesta'] = [
            'id'              => $proposalId,
            'proposal_number' => $number['number'],
            'lead_id'         => $leadId,
            'crm_opportunity_id' => $opportunityId,
            'total'           => $totals['total'],
            'currency'        => $currency,
            'pdf_url'         => '/v2/crm/proposals/' . $proposalId . '/pdf',
            'url'             => '/crm?proposal=' . $proposalId,
        ];

        return $summary;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /** @return array<int, int> */
    private function normalizeFollowers(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $customFields */
    private function guardarCrmMeta(int $solicitudId, array $customFields): void
    {
        // solicitud_crm_meta confirmed columns: id, solicitud_id, meta_key, meta_value, meta_type, created_at, updated_at
        $now = date('Y-m-d H:i:s');

        foreach ($customFields as $key => $value) {
            $metaKey = trim((string) $key);
            if ($metaKey === '') {
                continue;
            }

            $metaValue = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $existingId = DB::table('solicitud_crm_meta')
                ->where('solicitud_id', $solicitudId)
                ->where('meta_key', $metaKey)
                ->value('id');

            if ($existingId !== null) {
                DB::table('solicitud_crm_meta')
                    ->where('id', $existingId)
                    ->update([
                        'meta_value' => $metaValue,
                        'meta_type'  => 'string',
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('solicitud_crm_meta')->insert([
                'solicitud_id' => $solicitudId,
                'meta_key'     => $metaKey,
                'meta_value'   => $metaValue,
                'meta_type'    => 'string',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function autoCrearLeadParaSolicitud(int $solicitudId, ?int $autorId): int
    {
        $sol = $this->fetchSolicitudById($solicitudId);
        $now = date('Y-m-d H:i:s');

        $hcNumber = $this->nullableString($sol['hc_number'] ?? null);

        $nombre = 'Solicitud #' . $solicitudId;
        if ($hcNumber !== null) {
            $pd = DB::table('patient_data')->where('hc_number', $hcNumber)->first();
            if ($pd !== null) {
                $nombre = trim(implode(' ', array_filter([
                    trim((string) ($pd->fname ?? '')),
                    trim((string) ($pd->mname ?? '')),
                    trim((string) ($pd->lname ?? '')),
                    trim((string) ($pd->lname2 ?? '')),
                ]))) ?: $nombre;
            }
        }

        $leadId = (int) DB::table('crm_leads')->insertGetId([
            'name'       => $nombre ?: ('Solicitud #' . $solicitudId),
            'hc_number'  => $hcNumber,
            'status'     => 'activo',
            'source'     => 'solicitud',
            'created_by' => $autorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Vincular el lead recién creado a la solicitud en solicitud_crm_detalles
        $detalleExistente = $this->fetchCrmDetalleRow($solicitudId);
        if ($detalleExistente === null) {
            DB::table('solicitud_crm_detalles')->insert([
                'solicitud_id' => $solicitudId,
                'crm_lead_id'  => $leadId,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        } else {
            DB::table('solicitud_crm_detalles')
                ->where('solicitud_id', $solicitudId)
                ->update(['crm_lead_id' => $leadId, 'updated_at' => $now]);
        }

        return $leadId;
    }

    private function fetchCrmLead(int $leadId): ?array
    {
        if ($leadId <= 0) {
            return null;
        }

        $row = DB::table('crm_leads')->where('id', $leadId)->first();

        return $row !== null ? (array) $row : null;
    }

    private function resolveSolicitudOpportunityId(int $solicitudId): ?int
    {
        if (!Schema::hasColumn('solicitud_procedimiento', 'crm_opportunity_id')) {
            return null;
        }

        $value = DB::table('solicitud_procedimiento')
            ->where('id', $solicitudId)
            ->value('crm_opportunity_id');

        return $this->nullableInt($value);
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProposalItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $clean[] = [
                'description'      => $description,
                'quantity'         => max(0.01, (float) ($item['quantity'] ?? 1)),
                'unit_price'       => max(0.0, (float) ($item['unit_price'] ?? 0)),
                'discount_percent' => max(0.0, min(100.0, (float) ($item['discount_percent'] ?? 0))),
                'code_id'          => $this->nullableInt($item['code_id'] ?? null),
                'package_id'       => $this->nullableInt($item['package_id'] ?? null),
            ];
        }

        return $clean;
    }

    /** @return array{subtotal: float, discount: float, tax: float, total: float} */
    private function calculateProposalTotals(array $items, float $taxRate): array
    {
        $subtotal      = 0.0;
        $discountTotal = 0.0;

        foreach ($items as $item) {
            $lineSubtotal  = (float) $item['quantity'] * (float) $item['unit_price'];
            $lineDiscount  = $lineSubtotal * ((float) $item['discount_percent'] / 100);
            $subtotal      += $lineSubtotal;
            $discountTotal += $lineDiscount;
        }

        $taxable = max(0.0, $subtotal - $discountTotal);
        $tax     = $taxable * ($taxRate / 100);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discountTotal, 2),
            'tax'      => round($tax, 2),
            'total'    => round($taxable + $tax, 2),
        ];
    }

    /** @return array{number: string, sequence: int, year: int} */
    private function nextProposalNumber(): array
    {
        $year     = (int) date('Y');
        $sequence = (int) DB::table('crm_proposals')->where('proposal_year', $year)->max('sequence') + 1;

        return [
            'number'   => sprintf('PROP-%d-%04d', $year, $sequence),
            'sequence' => $sequence,
            'year'     => $year,
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetchSolicitudBloqueoBase(int $solicitudId): ?array
    {
        // consulta_data confirmed columns: hc_number, form_id, fecha — quirofano does NOT exist.
        // Always LEFT JOIN consulta_data; always COALESCE fecha; always NULL AS sala.
        $row = DB::selectOne(
            'SELECT sp.id, sp.doctor,
                    COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                    NULL AS sala
             FROM solicitud_procedimiento sp
             LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
             WHERE sp.id = ?
             LIMIT 1',
            [$solicitudId]
        );

        return $row !== null ? (array) $row : null;
    }
}
