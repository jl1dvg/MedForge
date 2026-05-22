<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AutomatedConversationDispatchService
{
    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly CloudApiTransportService $transport = new CloudApiTransportService(),
        private readonly ConversationStartService $conversationStartService = new ConversationStartService(),
    ) {
    }

    /**
     * @return array{conversation: array<string,mixed>, message: array<string,mixed>}
     */
    public function sendSystemText(WhatsappConversation $conversation, string $message, bool $previewUrl = false): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('El mensaje automatizado no puede estar vacío.');
        }

        $config = $this->transportConfig();
        $recipient = $this->normalizePhoneNumber((string) $conversation->wa_number, $config['default_country_code']);
        if ($recipient === '') {
            throw new RuntimeException('El número de la conversación no es válido.');
        }

        $sentAt = now();
        $transportResult = $this->transport->sendText(
            $config['phone_number_id'],
            $config['access_token'],
            $config['api_version'],
            $recipient,
            $message,
            $previewUrl
        );

        $createdMessage = DB::transaction(function () use ($conversation, $message, $transportResult, $sentAt): WhatsappMessage {
            $created = WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $transportResult['wa_message_id'],
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $message,
                'raw_payload' => $transportResult['raw'],
                'status' => $transportResult['status'],
                'message_timestamp' => $sentAt,
                'sent_at' => $sentAt,
            ]);

            $conversation->fill([
                'last_message_at' => $sentAt,
                'last_message_direction' => 'outbound',
                'last_message_type' => 'text',
                'last_message_preview' => mb_substr($message, 0, 512),
            ]);
            $conversation->save();

            return $created;
        });

        $conversation->refresh();

        return [
            'conversation' => [
                'id' => (int) $conversation->id,
                'wa_number' => (string) $conversation->wa_number,
                'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
                'last_message_direction' => $conversation->last_message_direction,
                'last_message_type' => $conversation->last_message_type,
                'last_message_preview' => $conversation->last_message_preview,
            ],
            'message' => [
                'id' => (int) $createdMessage->id,
                'wa_message_id' => (string) $createdMessage->wa_message_id,
                'direction' => (string) $createdMessage->direction,
                'message_type' => (string) $createdMessage->message_type,
                'body' => (string) ($createdMessage->body ?? ''),
                'status' => (string) ($createdMessage->status ?? ''),
                'sent_at' => optional($createdMessage->sent_at)?->toISOString(),
                'source' => 'laravel-v2-automation',
            ],
        ];
    }

    /**
     * @param array<int, string> $templateVariables
     * @return array{conversation: array<string,mixed>, message: array<string,mixed>}
     */
    public function sendTemplate(
        string $waNumber,
        int $templateId,
        ?string $contactName = null,
        ?string $patientHcNumber = null,
        ?string $patientFullName = null,
        array $templateVariables = [],
    ): array {
        $result = $this->conversationStartService->startConversationWithTemplate(
            $waNumber,
            $templateId,
            null,
            $contactName,
            $patientHcNumber,
            $patientFullName,
            $templateVariables
        );

        $conversationId = (int) data_get($result, 'conversation.id', 0);
        if ($conversationId > 0) {
            WhatsappConversation::query()
                ->whereKey($conversationId)
                ->update([
                    'needs_human' => false,
                    'assigned_user_id' => null,
                    'assigned_at' => null,
                    'handoff_role_id' => null,
                    'handoff_requested_at' => null,
                ]);
        }

        return $result;
    }

    /**
     * @return array{enabled:bool,phone_number_id:string,access_token:string,api_version:string,default_country_code:string}
     */
    private function transportConfig(): array
    {
        $config = $this->configService->get();
        $dryRun = (bool) config('whatsapp.migration.automation.dry_run', true);
        if ($dryRun) {
            config()->set('whatsapp.transport.dry_run', true);
            $config['enabled'] = true;
            $config['phone_number_id'] = $config['phone_number_id'] !== '' ? $config['phone_number_id'] : 'dry-run-phone';
            $config['access_token'] = $config['access_token'] !== '' ? $config['access_token'] : 'dry-run-token';
        }

        if (!$config['enabled'] || $config['phone_number_id'] === '' || $config['access_token'] === '') {
            throw new RuntimeException('La automatización de WhatsApp V2 no tiene Cloud API configurado.');
        }

        return [
            'enabled' => (bool) $config['enabled'],
            'phone_number_id' => (string) $config['phone_number_id'],
            'access_token' => (string) $config['access_token'],
            'api_version' => (string) $config['api_version'],
            'default_country_code' => (string) $config['default_country_code'],
        ];
    }

    private function normalizePhoneNumber(string $value, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if ($digits === '') {
            return '';
        }

        if ($defaultCountryCode !== '' && !str_starts_with($digits, $defaultCountryCode)) {
            if (str_starts_with($digits, '0')) {
                $digits = $defaultCountryCode . ltrim($digits, '0');
            }
        }

        return $digits;
    }
}
