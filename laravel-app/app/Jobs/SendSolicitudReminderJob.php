<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Solicitudes\Services\SolicitudesCommunicationService;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía un recordatorio quirúrgico a paciente/coordinador.
 *
 * Tipos:
 *   preop_2d  — 2 días antes de la cirugía (preparación preoperatoria)
 *   preop_24h — 24 horas antes de la cirugía (recordatorio final)
 *   postop    — 1 día después de la cirugía (seguimiento postoperatorio)
 */
class SendSolicitudReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly int $solicitudId,
        private readonly string $tipo,   // preop_2d | preop_24h | postop
        private readonly string $fechaCirugia,
    ) {
    }

    public function handle(): void
    {
        $solicitud = DB::table('solicitud_procedimiento')
            ->where('id', $this->solicitudId)
            ->first();

        if ($solicitud === null) {
            Log::info('solicitudes.reminder.skipped', [
                'solicitud_id' => $this->solicitudId,
                'tipo' => $this->tipo,
                'reason' => 'solicitud_not_found',
            ]);
            return;
        }

        // Si la fecha de cirugía cambió, este job ya no aplica.
        $fechaActual = trim((string) ($solicitud->sigcenter_fecha_inicio ?: $solicitud->fecha ?? ''));
        if ($fechaActual !== '' && !str_starts_with($fechaActual, substr($this->fechaCirugia, 0, 10))) {
            Log::info('solicitudes.reminder.skipped', [
                'solicitud_id' => $this->solicitudId,
                'tipo' => $this->tipo,
                'reason' => 'fecha_changed',
                'original' => $this->fechaCirugia,
                'current' => $fechaActual,
            ]);
            return;
        }

        // Si ya fue completada o cancelada, no recordar.
        $estado = strtolower(trim((string) ($solicitud->estado ?? '')));
        if (in_array($estado, ['completado', 'cancelado', 'anulado'], true)) {
            Log::info('solicitudes.reminder.skipped', [
                'solicitud_id' => $this->solicitudId,
                'tipo' => $this->tipo,
                'reason' => 'estado_terminal',
                'estado' => $estado,
            ]);
            return;
        }

        $detalle = DB::table('solicitud_crm_detalles')
            ->where('solicitud_id', $this->solicitudId)
            ->first();

        $email   = trim((string) ($detalle?->contacto_email ?? ''));
        $telefono = trim((string) ($detalle?->contacto_telefono ?? ''));
        $hc      = trim((string) ($solicitud->hc_number ?? ''));

        [$asunto, $cuerpo] = $this->buildMensaje($solicitud);

        $sent = false;

        // Intentar WA primero si hay teléfono, luego email.
        if ($telefono !== '') {
            try {
                $readService   = new SolicitudesReadParityService();
                $comunicacion  = new SolicitudesCommunicationService($readService);
                $comunicacion->sendWhatsapp($this->solicitudId, [
                    'phone'   => $telefono,
                    'mensaje' => strip_tags($cuerpo),
                ], null);
                $sent = true;
            } catch (Throwable $e) {
                Log::warning('solicitudes.reminder.whatsapp_failed', [
                    'solicitud_id' => $this->solicitudId,
                    'tipo' => $this->tipo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$sent && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $readService   = new SolicitudesReadParityService();
                $comunicacion  = new SolicitudesCommunicationService($readService);
                $comunicacion->sendEmail($this->solicitudId, [
                    'to'      => $email,
                    'subject' => $asunto,
                    'body'    => $cuerpo,
                ], null);
                $sent = true;
            } catch (Throwable $e) {
                Log::warning('solicitudes.reminder.email_failed', [
                    'solicitud_id' => $this->solicitudId,
                    'tipo' => $this->tipo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('solicitudes.reminder.processed', [
            'solicitud_id' => $this->solicitudId,
            'hc'           => $hc,
            'tipo'         => $this->tipo,
            'sent'         => $sent,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function buildMensaje(object $solicitud): array
    {
        $procedimiento = trim((string) ($solicitud->procedimiento ?? 'procedimiento'));
        $fecha = substr($this->fechaCirugia, 0, 10);

        return match ($this->tipo) {
            'preop_2d' => [
                "Recordatorio preoperatorio — {$procedimiento}",
                "Estimado paciente, le recordamos que tiene programado su procedimiento *{$procedimiento}* para el {$fecha}.\n\n"
                . "Por favor asista a su valoración preoperatoria y siga las indicaciones del equipo médico.\n\n"
                . "Ante cualquier duda comuníquese con Coordinación Quirúrgica CIVE.",
            ],
            'preop_24h' => [
                "Mañana es su cirugía — {$procedimiento}",
                "Estimado paciente, le recordamos que *mañana {$fecha}* tiene programado su procedimiento *{$procedimiento}*.\n\n"
                . "Recuerde:\n• Ayuno de 8 horas antes del procedimiento\n• Traer documentos de autorización\n• Llegar 30 minutos antes de la hora programada\n\n"
                . "Coordinación Quirúrgica CIVE",
            ],
            'postop' => [
                "Seguimiento postoperatorio — {$procedimiento}",
                "Estimado paciente, esperamos que su procedimiento *{$procedimiento}* del {$fecha} haya transcurrido sin novedad.\n\n"
                . "Recuerde asistir a sus controles postoperatorios y seguir las indicaciones médicas.\n\n"
                . "Coordinación Quirúrgica CIVE",
            ],
            default => [
                "Recordatorio — {$procedimiento}",
                "Recordatorio sobre su procedimiento {$procedimiento} programado para {$fecha}.",
            ],
        };
    }
}
