<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Models\WhatsappConversation;
use App\Modules\Whatsapp\Services\ConversationWriteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SolicitudesCommunicationService
{
    public function __construct(
        private readonly SolicitudesReadParityService $readService,
        private readonly ConversationWriteService $whatsapp = new ConversationWriteService(),
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function sendWhatsapp(int $solicitudId, array $payload, ?int $actorUserId): array
    {
        $message = trim((string) ($payload['message'] ?? $payload['mensaje'] ?? ''));
        if ($message === '') {
            throw new RuntimeException('Escribe un mensaje de WhatsApp antes de enviar.');
        }

        $detail = $this->detail($solicitudId);
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $phone = trim((string) ($payload['phone'] ?? $payload['telefono'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($detail['crm_contacto_telefono'] ?? $detail['paciente_celular'] ?? ''));
        }

        $conversation = $conversationId > 0
            ? WhatsappConversation::query()->find($conversationId)
            : $this->findConversationByPhone($phone);

        if (!$conversation instanceof WhatsappConversation) {
            throw new RuntimeException('No hay conversación WhatsApp vinculada. Abre el chat V2 o inicia con una plantilla aprobada.');
        }

        if ((int) ($conversation->assigned_user_id ?? 0) <= 0 && $actorUserId !== null) {
            $conversation->assigned_user_id = $actorUserId;
            $conversation->assigned_at = now();
            $conversation->needs_human = true;
            $conversation->save();
        }

        $result = $this->whatsapp->sendTextToConversation((int) $conversation->id, $message, false, $actorUserId);
        $this->addNote(
            $solicitudId,
            sprintf(
                "WhatsApp enviado a +%s:\n%s",
                ltrim((string) $conversation->wa_number, '+'),
                $message
            ),
            $actorUserId
        );

        return [
            'success' => true,
            'message' => 'Mensaje WhatsApp enviado.',
            'whatsapp' => $result,
            'data' => $this->readService->crmResumen($solicitudId),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function sendEmail(int $solicitudId, array $payload, ?int $actorUserId): array
    {
        $detail = $this->detail($solicitudId);
        $to = trim((string) ($payload['to'] ?? $payload['email'] ?? $detail['crm_contacto_email'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? $payload['asunto'] ?? ''));
        $body = trim((string) ($payload['body'] ?? $payload['mensaje'] ?? ''));

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Indica un correo de destino válido.');
        }
        if ($subject === '') {
            throw new RuntimeException('Indica un asunto para el correo.');
        }
        if ($body === '') {
            throw new RuntimeException('Escribe el cuerpo del correo antes de enviar.');
        }

        Mail::raw($body, static function ($message) use ($to, $subject): void {
            $message->to($to)->subject($subject);
        });

        $this->logMail($solicitudId, $detail, $to, $subject, $body, $actorUserId);
        $this->addNote(
            $solicitudId,
            sprintf(
                "Correo enviado a %s\nAsunto: %s\n\n%s",
                $to,
                $subject,
                $body
            ),
            $actorUserId
        );

        return [
            'success' => true,
            'message' => 'Correo enviado.',
            'data' => $this->readService->crmResumen($solicitudId),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function detail(int $solicitudId): array
    {
        $data = $this->readService->crmResumen($solicitudId);
        $detail = $data['detalle'] ?? null;
        if (!is_array($detail)) {
            throw new RuntimeException('Solicitud no encontrada.');
        }

        return $detail;
    }

    private function findConversationByPhone(string $phone): ?WhatsappConversation
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return null;
        }

        return WhatsappConversation::query()->where('wa_number', $normalized)->first();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '593' . substr($digits, 1);
        }
        if (!str_starts_with($digits, '593') && strlen($digits) === 9) {
            $digits = '593' . $digits;
        }

        return $digits;
    }

    private function addNote(int $solicitudId, string $note, ?int $actorUserId): void
    {
        if (!Schema::hasTable('solicitud_crm_notas')) {
            return;
        }

        $payload = [
            'solicitud_id' => $solicitudId,
            'nota' => $note,
        ];
        if (Schema::hasColumn('solicitud_crm_notas', 'autor_id')) {
            $payload['autor_id'] = $actorUserId;
        }
        if (Schema::hasColumn('solicitud_crm_notas', 'created_at')) {
            $payload['created_at'] = now()->toDateTimeString();
        }

        DB::table('solicitud_crm_notas')->insert($payload);
    }

    /**
     * @param array<string,mixed> $detail
     */
    private function logMail(int $solicitudId, array $detail, string $to, string $subject, string $body, ?int $actorUserId): void
    {
        if (!Schema::hasTable('solicitud_mail_log')) {
            return;
        }

        $payload = [
            'solicitud_id' => $solicitudId,
            'form_id' => $detail['form_id'] ?? null,
            'hc_number' => $detail['hc_number'] ?? null,
            'afiliacion' => $detail['afiliacion'] ?? null,
            'template_key' => 'crm_manual_email',
            'to_emails' => $to,
            'subject' => $subject,
            'body_text' => $body,
            'sent_by_user_id' => $actorUserId,
            'status' => 'sent',
            'sent_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        $columns = Schema::getColumnListing('solicitud_mail_log');
        $payload = array_intersect_key($payload, array_flip($columns));
        if ($payload !== []) {
            DB::table('solicitud_mail_log')->insert($payload);
        }
    }
}
