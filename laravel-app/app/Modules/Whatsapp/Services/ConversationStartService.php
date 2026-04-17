<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\CrmLead;
use App\Models\PatientDatum;
use App\Models\WhatsappConversation;
use App\Models\WhatsappContactConsent;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ConversationStartService
{
    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly CloudApiTransportService $transport = new CloudApiTransportService(),
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchContacts(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $rows = [];

        $rows = array_merge($rows, $this->searchPatientData($query, $limit));
        $rows = array_merge($rows, $this->searchExistingConversations($query, $limit));
        $rows = array_merge($rows, $this->searchConsentContacts($query, $limit));
        $rows = array_merge($rows, $this->searchCrmLeads($query, $limit));

        $deduped = [];
        foreach ($rows as $row) {
            $number = trim((string) ($row['wa_number'] ?? ''));
            if ($number === '') {
                continue;
            }

            if (!isset($deduped[$number])) {
                $deduped[$number] = $row;
                continue;
            }

            $deduped[$number] = $this->mergeCandidateRows($deduped[$number], $row);
        }

        return collect(array_values($deduped))
            ->sortByDesc(fn (array $row) => (int) ($row['priority_score'] ?? 0))
            ->take($limit)
            ->values()
            ->map(function (array $row): array {
                unset($row['priority_score']);

                return $row;
            })
            ->all();
    }

    /**
     * @return array{conversation: array<string,mixed>, message: array<string,mixed>}
     */
    public function startConversationWithTemplate(
        string $waNumber,
        int $templateId,
        ?int $actorUserId,
        ?string $contactName = null,
        ?string $patientHcNumber = null,
        ?string $patientFullName = null,
    ): array {
        $config = $this->configService->get();
        if (!$config['enabled'] || $config['phone_number_id'] === '' || $config['access_token'] === '') {
            throw new RuntimeException('La integración de WhatsApp Cloud API no está lista en Laravel.');
        }

        $template = WhatsappMessageTemplate::query()->find($templateId);
        if (!$template instanceof WhatsappMessageTemplate) {
            throw new RuntimeException('La plantilla seleccionada no existe.');
        }

        $status = strtolower(trim((string) $template->status));
        if ($status !== '' && !in_array($status, ['approved', 'active'], true)) {
            throw new RuntimeException('Solo puedes iniciar conversación con plantillas aprobadas.');
        }

        $recipient = $this->normalizePhoneNumber($waNumber, $config['default_country_code']);
        if ($recipient === '') {
            throw new RuntimeException('El número de teléfono no es válido.');
        }

        $conversation = WhatsappConversation::query()->where('wa_number', $recipient)->first();
        if ($conversation instanceof WhatsappConversation) {
            $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
            if ($assignedUserId > 0 && $actorUserId !== null && $assignedUserId !== $actorUserId) {
                throw new RuntimeException('Esta conversación está asignada a otro agente.');
            }
        }

        $sentAt = now();
        $transportResult = $this->transport->sendTemplate(
            $config['phone_number_id'],
            $config['access_token'],
            $config['api_version'],
            $recipient,
            (string) $template->template_code,
            (string) $template->language
        );

        $conversation = DB::transaction(function () use (
            $conversation,
            $recipient,
            $contactName,
            $patientHcNumber,
            $patientFullName,
            $actorUserId,
            $sentAt,
            $template,
            $transportResult
        ): WhatsappConversation {
            $displayName = $this->truncate($contactName ?: $patientFullName ?: $recipient, 191);
            $patientFullName = $this->truncate($patientFullName ?: $contactName, 191);
            $patientHcNumber = $this->truncate($patientHcNumber, 64);

            if (!$conversation instanceof WhatsappConversation) {
                $conversation = WhatsappConversation::query()->create([
                    'wa_number' => $recipient,
                    'display_name' => $displayName,
                    'patient_hc_number' => $patientHcNumber,
                    'patient_full_name' => $patientFullName,
                    'needs_human' => true,
                    'assigned_user_id' => $actorUserId,
                    'assigned_at' => $actorUserId !== null ? $sentAt : null,
                    'last_message_at' => $sentAt,
                    'last_message_direction' => 'outbound',
                    'last_message_type' => 'template',
                    'last_message_preview' => 'Plantilla: ' . ($template->display_name ?: $template->template_code),
                    'unread_count' => 0,
                ]);
            } else {
                $conversation->fill([
                    'display_name' => $conversation->display_name ?: $displayName,
                    'patient_hc_number' => $conversation->patient_hc_number ?: $patientHcNumber,
                    'patient_full_name' => $conversation->patient_full_name ?: $patientFullName,
                    'needs_human' => true,
                    'assigned_user_id' => $conversation->assigned_user_id ?: $actorUserId,
                    'assigned_at' => $conversation->assigned_at ?: ($actorUserId !== null ? $sentAt : null),
                    'last_message_at' => $sentAt,
                    'last_message_direction' => 'outbound',
                    'last_message_type' => 'template',
                    'last_message_preview' => 'Plantilla: ' . ($template->display_name ?: $template->template_code),
                ]);
                $conversation->save();
            }

            WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $transportResult['wa_message_id'],
                'direction' => 'outbound',
                'message_type' => 'template',
                'body' => 'Plantilla: ' . ($template->display_name ?: $template->template_code),
                'raw_payload' => [
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->template_code,
                        'display_name' => $template->display_name,
                        'language' => $template->language,
                    ],
                    'transport' => $transportResult['raw'],
                ],
                'status' => $transportResult['status'],
                'message_timestamp' => $sentAt,
                'sent_at' => $sentAt,
            ]);

            return $conversation;
        });

        $conversation->refresh();
        $message = $conversation->whatsapp_messages()->latest('id')->first();

        return [
            'conversation' => [
                'id' => (int) $conversation->id,
                'wa_number' => (string) $conversation->wa_number,
                'display_name' => (string) ($conversation->display_name ?: $conversation->wa_number),
                'patient_hc_number' => $conversation->patient_hc_number,
                'patient_full_name' => $conversation->patient_full_name,
                'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
                'last_message_direction' => $conversation->last_message_direction,
                'last_message_type' => $conversation->last_message_type,
                'last_message_preview' => $conversation->last_message_preview,
            ],
            'message' => [
                'id' => (int) ($message?->id ?? 0),
                'wa_message_id' => (string) ($message?->wa_message_id ?? ''),
                'direction' => 'outbound',
                'message_type' => 'template',
                'body' => (string) ($message?->body ?? ''),
                'status' => (string) ($message?->status ?? 'accepted'),
                'sent_at' => optional($message?->sent_at)?->toISOString(),
                'source' => 'laravel-v2',
                'actor_user_id' => $actorUserId,
                'template_id' => (int) $template->id,
                'template_name' => (string) ($template->display_name ?: $template->template_code),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializePatient(PatientDatum $patient): array
    {
        $config = $this->configService->get();
        $fullName = trim(collect([
            $patient->fname,
            $patient->mname,
            $patient->lname,
            $patient->lname2,
        ])->filter()->implode(' '));

        return [
            'id' => (int) $patient->id,
            'source' => 'patient_data',
            'hc_number' => (string) ($patient->hc_number ?? ''),
            'display_name' => $fullName !== '' ? $fullName : trim((string) ($patient->celular ?? '')),
            'wa_number' => $this->normalizePhoneNumber((string) ($patient->celular ?? ''), (string) ($config['default_country_code'] ?? '')),
            'celular' => (string) ($patient->celular ?? ''),
            'email' => (string) ($patient->email ?? ''),
            'priority_score' => 40,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function searchPatientData(string $query, int $limit): array
    {
        if (!Schema::hasTable('patient_data')) {
            return [];
        }

        return PatientDatum::query()
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('celular', 'like', '%' . $query . '%')
                    ->orWhere('hc_number', 'like', '%' . $query . '%')
                    ->orWhere('fname', 'like', '%' . $query . '%')
                    ->orWhere('mname', 'like', '%' . $query . '%')
                    ->orWhere('lname', 'like', '%' . $query . '%')
                    ->orWhere('lname2', 'like', '%' . $query . '%');
            })
            ->whereNotNull('celular')
            ->whereRaw("TRIM(celular) <> ''")
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (PatientDatum $patient): array => $this->serializePatient($patient))
            ->filter(fn (array $row): bool => $row['wa_number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function searchExistingConversations(string $query, int $limit): array
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return [];
        }

        $config = $this->configService->get();

        return WhatsappConversation::query()
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('wa_number', 'like', '%' . $query . '%')
                    ->orWhere('display_name', 'like', '%' . $query . '%')
                    ->orWhere('patient_full_name', 'like', '%' . $query . '%')
                    ->orWhere('patient_hc_number', 'like', '%' . $query . '%');
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (WhatsappConversation $conversation) use ($config): array {
                return [
                    'id' => (int) $conversation->id,
                    'source' => 'conversation',
                    'hc_number' => (string) ($conversation->patient_hc_number ?? ''),
                    'display_name' => (string) ($conversation->display_name ?: $conversation->patient_full_name ?: $conversation->wa_number),
                    'wa_number' => $this->normalizePhoneNumber((string) $conversation->wa_number, (string) ($config['default_country_code'] ?? '')),
                    'celular' => (string) $conversation->wa_number,
                    'email' => '',
                    'priority_score' => 100,
                ];
            })
            ->filter(fn (array $row): bool => $row['wa_number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function searchConsentContacts(string $query, int $limit): array
    {
        if (!Schema::hasTable('whatsapp_contact_consent')) {
            return [];
        }

        $config = $this->configService->get();

        return WhatsappContactConsent::query()
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('wa_number', 'like', '%' . $query . '%')
                    ->orWhere('cedula', 'like', '%' . $query . '%')
                    ->orWhere('patient_hc_number', 'like', '%' . $query . '%')
                    ->orWhere('patient_full_name', 'like', '%' . $query . '%');
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (WhatsappContactConsent $contact) use ($config): array {
                return [
                    'id' => (int) $contact->id,
                    'source' => 'consent',
                    'hc_number' => (string) ($contact->patient_hc_number ?? ''),
                    'display_name' => (string) ($contact->patient_full_name ?: $contact->wa_number),
                    'wa_number' => $this->normalizePhoneNumber((string) $contact->wa_number, (string) ($config['default_country_code'] ?? '')),
                    'celular' => (string) $contact->wa_number,
                    'email' => '',
                    'priority_score' => 80,
                ];
            })
            ->filter(fn (array $row): bool => $row['wa_number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function searchCrmLeads(string $query, int $limit): array
    {
        if (!Schema::hasTable('crm_leads')) {
            return [];
        }

        $config = $this->configService->get();

        return CrmLead::query()
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('phone', 'like', '%' . $query . '%')
                    ->orWhere('hc_number', 'like', '%' . $query . '%')
                    ->orWhere('name', 'like', '%' . $query . '%')
                    ->orWhere('first_name', 'like', '%' . $query . '%')
                    ->orWhere('last_name', 'like', '%' . $query . '%')
                    ->orWhere('email', 'like', '%' . $query . '%');
            })
            ->whereNotNull('phone')
            ->whereRaw("TRIM(phone) <> ''")
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (CrmLead $lead) use ($config): array {
                $name = trim(collect([$lead->first_name, $lead->last_name])->filter()->implode(' '));
                $displayName = $name !== '' ? $name : trim((string) ($lead->name ?? ''));

                return [
                    'id' => (int) $lead->id,
                    'source' => 'crm_lead',
                    'hc_number' => (string) ($lead->hc_number ?? ''),
                    'display_name' => $displayName !== '' ? $displayName : (string) ($lead->phone ?? ''),
                    'wa_number' => $this->normalizePhoneNumber((string) ($lead->phone ?? ''), (string) ($config['default_country_code'] ?? '')),
                    'celular' => (string) ($lead->phone ?? ''),
                    'email' => (string) ($lead->email ?? ''),
                    'priority_score' => 60,
                ];
            })
            ->filter(fn (array $row): bool => $row['wa_number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function mergeCandidateRows(array $base, array $incoming): array
    {
        $baseScore = (int) ($base['priority_score'] ?? 0);
        $incomingScore = (int) ($incoming['priority_score'] ?? 0);

        $base['display_name'] = $base['display_name'] ?: ($incoming['display_name'] ?? '');
        $base['hc_number'] = $base['hc_number'] ?: ($incoming['hc_number'] ?? '');
        $base['email'] = $base['email'] ?: ($incoming['email'] ?? '');
        $base['celular'] = $base['celular'] ?: ($incoming['celular'] ?? '');
        $base['priority_score'] = max($baseScore, $incomingScore);
        if ($incomingScore > $baseScore) {
            $base['source'] = $incoming['source'] ?? ($base['source'] ?? '');
        } else {
            $base['source'] = $base['source'] ?: ($incoming['source'] ?? '');
        }

        return $base;
    }

    private function normalizePhoneNumber(?string $value, ?string $defaultCountryCode): string
    {
        $number = preg_replace('/\D+/', '', (string) $value);
        if ($number === '') {
            return '';
        }

        $defaultCountryCode = preg_replace('/\D+/', '', (string) $defaultCountryCode);

        if ($defaultCountryCode !== '' && !str_starts_with($number, $defaultCountryCode)) {
            if (str_starts_with($number, '0')) {
                $number = ltrim($number, '0');
            }

            if (!str_starts_with($number, $defaultCountryCode)) {
                $number = $defaultCountryCode . $number;
            }
        }

        return $number;
    }

    private function truncate(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
