<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignDelivery;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CampaignService
{
    public function __construct(
        private readonly TemplateCatalogService $templateCatalogService = new TemplateCatalogService(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'campaigns' => $this->listCampaigns(),
            'templates' => $this->listTemplateOptions(),
            'audience_suggestions' => $this->audienceSuggestions(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCampaigns(int $limit = 20): array
    {
        if (!Schema::hasTable('whatsapp_campaigns')) {
            return [];
        }

        return WhatsappCampaign::query()
            ->latest('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(function (WhatsappCampaign $campaign): array {
                $deliveryStats = [];
                if (Schema::hasTable('whatsapp_campaign_deliveries')) {
                    $deliveryStats = WhatsappCampaignDelivery::query()
                        ->selectRaw('status, COUNT(*) AS total')
                        ->where('campaign_id', $campaign->id)
                        ->groupBy('status')
                        ->pluck('total', 'status')
                        ->all();
                }

                return [
                    'id' => (int) $campaign->id,
                    'name' => (string) $campaign->name,
                    'status' => (string) $campaign->status,
                    'template_id' => $campaign->template_id,
                    'template_name' => $campaign->template_name,
                    'audience_count' => (int) ($campaign->audience_count ?? 0),
                    'dry_run' => (bool) $campaign->dry_run,
                    'scheduled_at' => optional($campaign->scheduled_at)?->toISOString(),
                    'last_executed_at' => optional($campaign->last_executed_at)?->toISOString(),
                    'delivery_stats' => [
                        'dry_run_ready' => (int) ($deliveryStats['dry_run_ready'] ?? 0),
                        'sent' => (int) ($deliveryStats['sent'] ?? 0),
                        'error' => (int) ($deliveryStats['error'] ?? 0),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function createDraft(array $payload, ?int $actorUserId): array
    {
        $this->ensureTables();

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('El nombre de la campaña es obligatorio.');
        }

        $templateId = isset($payload['template_id']) && $payload['template_id'] !== '' ? (int) $payload['template_id'] : null;
        $template = $templateId !== null ? WhatsappMessageTemplate::query()->find($templateId) : null;
        if ($templateId !== null && $template === null) {
            throw new RuntimeException('La plantilla seleccionada no existe.');
        }

        $audience = $this->parseAudience((string) ($payload['audience_text'] ?? ''));
        if ($audience === []) {
            throw new RuntimeException('Debes definir al menos un destinatario válido.');
        }

        $campaign = WhatsappCampaign::query()->create([
            'name' => mb_substr($name, 0, 160),
            'status' => 'draft',
            'template_id' => $template?->id,
            'template_name' => $template?->display_name ?: $template?->template_code,
            'audience_payload' => $audience,
            'audience_count' => count($audience),
            'dry_run' => true,
            'created_by_user_id' => $actorUserId,
            'updated_by_user_id' => $actorUserId,
        ]);

        return $this->serializeCampaign($campaign);
    }

    /**
     * @return array<string, mixed>
     */
    public function executeDryRun(int $campaignId, ?int $actorUserId): array
    {
        $this->ensureTables();

        $campaign = WhatsappCampaign::query()->find($campaignId);
        if (!$campaign instanceof WhatsappCampaign) {
            throw new RuntimeException('Campaña no encontrada.');
        }

        $audience = is_array($campaign->audience_payload) ? $campaign->audience_payload : [];
        if ($audience === []) {
            throw new RuntimeException('La campaña no tiene audiencia cargada.');
        }

        DB::transaction(function () use ($campaign, $audience, $actorUserId): void {
            WhatsappCampaignDelivery::query()->where('campaign_id', $campaign->id)->delete();

            foreach ($audience as $target) {
                WhatsappCampaignDelivery::query()->create([
                    'campaign_id' => $campaign->id,
                    'wa_number' => (string) ($target['wa_number'] ?? ''),
                    'contact_name' => $target['contact_name'] ?? null,
                    'status' => 'dry_run_ready',
                    'template_name' => $campaign->template_name,
                    'payload' => [
                        'campaign_id' => $campaign->id,
                        'template_name' => $campaign->template_name,
                        'wa_number' => $target['wa_number'] ?? null,
                        'dry_run' => true,
                    ],
                        'executed_at' => now(),
                ]);
            }

            $campaign->forceFill([
                'status' => 'dry_run_ready',
                'last_executed_at' => now(),
                'updated_by_user_id' => $actorUserId,
            ])->save();
        });

        return [
            'campaign' => $this->serializeCampaign($campaign->fresh()),
            'deliveries' => WhatsappCampaignDelivery::query()
                ->where('campaign_id', $campaign->id)
                ->orderBy('id')
                ->get()
                ->map(fn (WhatsappCampaignDelivery $delivery): array => [
                    'id' => (int) $delivery->id,
                    'wa_number' => (string) $delivery->wa_number,
                    'contact_name' => $delivery->contact_name,
                    'status' => (string) $delivery->status,
                    'template_name' => $delivery->template_name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplateOptions(): array
    {
        if (!Schema::hasTable('whatsapp_message_templates')) {
            return [];
        }

        return WhatsappMessageTemplate::query()
            ->orderBy('display_name')
            ->limit(100)
            ->get()
            ->map(fn (WhatsappMessageTemplate $template): array => [
                'id' => (int) $template->id,
                'name' => (string) ($template->display_name ?: $template->template_code),
                'code' => (string) $template->template_code,
                'status' => (string) ($template->status ?? ''),
                'language' => (string) ($template->language ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function audienceSuggestions(string $segment = 'recent_open', int $limit = 25): array
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return [];
        }

        $query = WhatsappConversation::query();

        if ($segment === 'needs_human') {
            $query->where('needs_human', true);
        } elseif ($segment === 'resolved_recent') {
            $query->where('needs_human', false);
        }

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (WhatsappConversation $conversation): array => [
                'conversation_id' => (int) $conversation->id,
                'wa_number' => $this->normalizeSuggestedNumber((string) $conversation->wa_number),
                'display_name' => $conversation->display_name ?: $conversation->patient_full_name ?: ('Paciente #' . $conversation->id),
                'patient_full_name' => $conversation->patient_full_name,
                'needs_human' => (bool) $conversation->needs_human,
                'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
            ])
            ->filter(fn (array $row): bool => $row['wa_number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCampaign(WhatsappCampaign $campaign): array
    {
        return [
            'id' => (int) $campaign->id,
            'name' => (string) $campaign->name,
            'status' => (string) $campaign->status,
            'template_id' => $campaign->template_id,
            'template_name' => $campaign->template_name,
            'audience_count' => (int) ($campaign->audience_count ?? 0),
            'dry_run' => (bool) $campaign->dry_run,
            'last_executed_at' => optional($campaign->last_executed_at)?->toISOString(),
            'source' => 'laravel-v2',
        ];
    }

    /**
     * @return array<int, array{wa_number:string,contact_name:?string}>
     */
    private function parseAudience(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $audience = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $number = preg_replace('/\D+/', '', $parts[0] ?? '') ?: '';
            if ($number === '') {
                continue;
            }

            if (!str_starts_with($number, '593')) {
                $number = '593' . ltrim($number, '0');
            }

            $audience[] = [
                'wa_number' => '+' . $number,
                'contact_name' => ($parts[1] ?? '') !== '' ? $parts[1] : null,
            ];
        }

        return array_values(array_unique($audience, SORT_REGULAR));
    }

    private function normalizeSuggestedNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return '';
        }

        if (!str_starts_with($digits, '593')) {
            $digits = '593' . ltrim($digits, '0');
        }

        return '+' . $digits;
    }

    private function ensureTables(): void
    {
        if (!Schema::hasTable('whatsapp_campaigns') || !Schema::hasTable('whatsapp_campaign_deliveries')) {
            throw new RuntimeException('Las tablas de campañas aún no están disponibles.');
        }
    }
}
