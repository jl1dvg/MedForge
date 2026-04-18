<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\PatientDatum;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FlowAiAgentToolRegistryService
{
    public function __construct(
        private readonly CampaignService $campaignService = new CampaignService(),
        private readonly ConversationStartService $conversationStartService = new ConversationStartService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function run(array $input, array $context = [], array $tools = []): array
    {
        $tools = $tools !== [] ? array_values(array_filter($tools, 'is_string')) : [
            'conversation_state',
            'window_status',
            'suggest_template',
            'search_patient',
        ];

        $result = [];
        foreach ($tools as $tool) {
            $result[$tool] = match ($tool) {
                'conversation_state' => $this->conversationState((string) ($input['wa_number'] ?? '')),
                'window_status' => $this->windowStatus((string) ($input['wa_number'] ?? '')),
                'suggest_template' => $this->suggestTemplate((string) ($input['text'] ?? '')),
                'search_patient' => $this->searchPatient((string) ($input['text'] ?? ''), $context),
                default => ['ok' => false, 'error' => 'tool_not_supported'],
            };
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationState(string $waNumber): array
    {
        if ($waNumber === '' || !Schema::hasTable('whatsapp_conversations')) {
            return ['ok' => true, 'found' => false];
        }

        $conversation = WhatsappConversation::query()
            ->where('wa_number', $waNumber)
            ->first();

        if (!$conversation instanceof WhatsappConversation) {
            return ['ok' => true, 'found' => false];
        }

        return [
            'ok' => true,
            'found' => true,
            'conversation_id' => (int) $conversation->id,
            'needs_human' => (bool) $conversation->needs_human,
            'assigned_user_id' => $conversation->assigned_user_id,
            'ownership_state' => !(bool) $conversation->needs_human
                ? 'resolved'
                : ((int) ($conversation->assigned_user_id ?? 0) > 0 ? 'assigned' : 'queue'),
            'last_message_preview' => $conversation->last_message_preview,
            'patient_hc_number' => $conversation->patient_hc_number,
            'patient_full_name' => $conversation->patient_full_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function windowStatus(string $waNumber): array
    {
        if ($waNumber === '' || !Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_messages')) {
            return ['ok' => true, 'state' => 'unknown', 'can_send_freeform' => false];
        }

        $conversation = WhatsappConversation::query()->where('wa_number', $waNumber)->first();
        if (!$conversation instanceof WhatsappConversation) {
            return ['ok' => true, 'state' => 'needs_template', 'can_send_freeform' => false];
        }

        $latestInbound = WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->max('message_timestamp');

        if ($latestInbound === null) {
            return ['ok' => true, 'state' => 'needs_template', 'can_send_freeform' => false];
        }

        $windowOpen = now()->subHours(24)->lessThanOrEqualTo($latestInbound);

        return [
            'ok' => true,
            'state' => $windowOpen ? 'window_open' : 'needs_template',
            'label' => $windowOpen ? '24h abierta' : 'Requiere plantilla',
            'can_send_freeform' => $windowOpen,
            'latest_inbound_at' => Carbon::parse((string) $latestInbound)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function suggestTemplate(string $text): array
    {
        $templates = $this->campaignService->listTemplateOptions();
        if ($templates === []) {
            return ['ok' => true, 'suggested' => null];
        }

        $normalized = Str::lower($text);
        $needle = match (true) {
            str_contains($normalized, 'cita') || str_contains($normalized, 'agend') => 'cita',
            str_contains($normalized, 'consent') || str_contains($normalized, 'datos') => 'consent',
            str_contains($normalized, 'resultado') || str_contains($normalized, 'examen') => 'resultado',
            default => '',
        };

        $suggested = null;
        foreach ($templates as $template) {
            $haystack = Str::lower(implode(' ', array_filter([
                $template['name'] ?? '',
                $template['code'] ?? '',
            ])));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                $suggested = $template;
                break;
            }
            $suggested ??= $template;
        }

        return [
            'ok' => true,
            'suggested' => $suggested ? [
                'id' => $suggested['id'] ?? null,
                'name' => $suggested['name'] ?? null,
                'code' => $suggested['code'] ?? null,
                'language' => $suggested['language'] ?? null,
            ] : null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function searchPatient(string $text, array $context): array
    {
        $query = trim((string) ($context['hc'] ?? $context['patient_hc_number'] ?? $context['cedula'] ?? $context['cedula_input'] ?? $context['identifier'] ?? ''));
        if ($query === '') {
            $query = trim((string) preg_replace('/\s+/', ' ', $text));
        }

        if ($query === '' || !Schema::hasTable('patient_data')) {
            return ['ok' => true, 'matches' => []];
        }

        $matches = $this->conversationStartService->searchContacts($query, 3);
        if ($matches !== []) {
            return ['ok' => true, 'matches' => $matches];
        }

        $fallback = PatientDatum::query()
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('celular', 'like', '%' . $query . '%')
                    ->orWhere('hc_number', 'like', '%' . $query . '%')
                    ->orWhere('fname', 'like', '%' . $query . '%')
                    ->orWhere('lname', 'like', '%' . $query . '%');
            })
            ->limit(3)
            ->get()
            ->map(fn (PatientDatum $patient): array => [
                'hc_number' => $patient->hc_number,
                'display_name' => trim(collect([$patient->fname, $patient->lname])->filter()->implode(' ')),
                'celular' => $patient->celular,
                'source' => 'patient_data',
            ])
            ->all();

        return ['ok' => true, 'matches' => $fallback];
    }
}
