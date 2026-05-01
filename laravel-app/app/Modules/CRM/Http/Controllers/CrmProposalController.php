<?php

declare(strict_types=1);

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\CRM\Services\CrmProposalPdfService;
use App\Modules\CRM\Services\CrmProposalService;
use App\Modules\Solicitudes\Services\SolicitudesCommunicationService;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CrmProposalController
{
    public function __construct(
        private readonly CrmProposalService $proposals = new CrmProposalService(),
        private readonly CrmProposalPdfService $pdf = new CrmProposalPdfService(),
    ) {
    }

    public function pdf(Request $request, int $id): Response|JsonResponse
    {
        try {
            $result = $this->pdf->generate($id);
            $this->proposals->recordActivity($id, 'pdf_opened', $this->actorId());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->status($e));
        } catch (Throwable $e) {
            Log::error('crm.proposal.pdf.error', ['proposal_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo generar el PDF de la propuesta'], 500);
        }

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $result['filename'] . '"',
            'Content-Length' => (string) strlen($result['content']),
        ]);
    }

    public function publicView(Request $request, int $id, string $hash): Response|JsonResponse
    {
        try {
            $proposal = $this->proposals->findPublic($id, $hash);
            $this->proposals->recordActivity($id, 'public_viewed', null, ['ip' => $request->ip()]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->status($e));
        }

        return response()->view('crm.proposals.public', [
            'proposal' => $proposal,
            'items' => $proposal['items'] ?? [],
            'pdfUrl' => url('/proposal/' . $id . '/' . $hash . '/pdf'),
        ]);
    }

    public function publicPdf(Request $request, int $id, string $hash): Response|JsonResponse
    {
        try {
            $proposal = $this->proposals->findPublic($id, $hash);
            $result = $this->pdf->generate($id, $proposal);
            $this->proposals->recordActivity($id, 'public_pdf_opened', null, ['ip' => $request->ip()]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->status($e));
        } catch (Throwable $e) {
            Log::error('crm.proposal.public_pdf.error', ['proposal_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo generar el PDF de la propuesta'], 500);
        }

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $result['filename'] . '"',
            'Content-Length' => (string) strlen($result['content']),
        ]);
    }

    public function sendEmail(Request $request, int $id): JsonResponse
    {
        try {
            $proposal = $this->proposals->find($id);
            $to = trim((string) ($request->input('to') ?: ($proposal['lead_email'] ?? '')));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Indica un correo válido para enviar la propuesta');
            }

            $subject = trim((string) ($request->input('subject') ?: 'Propuesta ' . ($proposal['proposal_number'] ?? '#' . $id)));
            $body = trim((string) ($request->input('body') ?: $this->defaultMessage($proposal)));
            $attachPdf = filter_var($request->input('attach_pdf', true), FILTER_VALIDATE_BOOLEAN);

            $pdf = $attachPdf ? $this->pdf->generate($id, $proposal) : null;
            Mail::raw($body, static function ($message) use ($to, $subject, $pdf): void {
                $message->to($to)->subject($subject);
                if (is_array($pdf)) {
                    $message->attachData($pdf['content'], $pdf['filename'], ['mime' => 'application/pdf']);
                }
            });

            $this->proposals->markSent($id, 'email', $this->actorId());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->status($e));
        } catch (Throwable $e) {
            Log::error('crm.proposal.email.error', ['proposal_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo enviar la propuesta por correo'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Propuesta enviada por correo']);
    }

    public function sendWhatsapp(Request $request, int $id): JsonResponse
    {
        try {
            $proposal = $this->proposals->find($id);
            $solicitudId = (int) ($request->input('solicitud_id') ?: ($proposal['solicitud_id'] ?? 0));
            if ($solicitudId <= 0) {
                throw new RuntimeException('No se encontró la solicitud vinculada para enviar WhatsApp');
            }

            $message = trim((string) ($request->input('message') ?: $this->defaultMessage($proposal)));
            $communication = new SolicitudesCommunicationService(new SolicitudesReadParityService());
            $communication->sendWhatsapp($solicitudId, [
                'message' => $message,
                'phone' => $proposal['lead_phone'] ?? '',
            ], $this->actorId());

            $this->proposals->markSent($id, 'whatsapp', $this->actorId());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->status($e));
        } catch (Throwable $e) {
            Log::error('crm.proposal.whatsapp.error', ['proposal_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'No se pudo enviar la propuesta por WhatsApp'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Propuesta enviada por WhatsApp']);
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private function defaultMessage(array $proposal): string
    {
        return sprintf(
            "Hola %s,\n\nTe compartimos la propuesta %s por %s.\nPuedes revisarla aquí:\n%s",
            (string) ($proposal['lead_name'] ?? 'paciente'),
            (string) ($proposal['proposal_number'] ?? '#' . ($proposal['id'] ?? '')),
            $this->money((float) ($proposal['total'] ?? 0), (string) ($proposal['currency'] ?? 'USD')),
            (string) ($proposal['public_url'] ?? '')
        );
    }

    private function money(float $amount, string $currency): string
    {
        return ($currency ?: 'USD') . ' ' . number_format($amount, 2, '.', ',');
    }

    private function actorId(): ?int
    {
        $id = Auth::id();
        return $id ? (int) $id : null;
    }

    private function status(RuntimeException $e): int
    {
        $code = (int) $e->getCode();
        return $code >= 400 && $code < 600 ? $code : 422;
    }
}
