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
            ->leftJoin('crm_customers as c', 'c.id', '=', 'p.customer_id')
            ->where('p.id', $id)
            ->select([
                'p.*',
                'l.name as lead_name',
                'l.email as lead_email',
                'l.phone as lead_phone',
                'l.hc_number as lead_hc_number',
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
}
