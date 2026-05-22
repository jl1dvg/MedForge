<?php

namespace App\Modules\MailTemplates\Http\Controllers;

use App\Modules\MailTemplates\Services\CoberturaMailTemplateService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CoberturaMailTemplateController
{
    public function __construct(
        private readonly CoberturaMailTemplateService $service,
    ) {}

    public function index(Request $request, ?string $key = null): View
    {
        $status = $request->query('status');
        $templates = $this->service->listTemplates();
        $selectedKey = $key ?? ($request->query('key') ?? ($templates[0]['template_key'] ?? ''));

        $selected = null;
        if ($selectedKey === 'new') {
            $selected = [
                'template_key' => '',
                'name' => '',
                'subject_template' => '',
                'body_template_html' => '',
                'body_template_text' => '',
                'recipients_to' => '',
                'recipients_cc' => '',
                'enabled' => 1,
            ];
        } elseif ($selectedKey !== '') {
            $selected = $this->service->findTemplate((string) $selectedKey);
        }

        return view('mail_templates.cobertura', [
            'pageTitle' => 'Plantillas de cobertura',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'templates' => $templates,
            'selectedKey' => $selectedKey,
            'selectedTemplate' => $selected,
            'status' => $status,
        ]);
    }

    public function save(Request $request, string $key): RedirectResponse
    {
        $templateKey = trim((string) $request->input('template_key', $key));
        if ($key !== 'new') {
            $templateKey = $key;
        }

        if ($templateKey === '') {
            return redirect('/mail-templates/cobertura')->withErrors(['error' => 'El key de plantilla es obligatorio.']);
        }

        $data = [
            'name' => trim((string) $request->input('name', $templateKey)),
            'subject_template' => trim((string) $request->input('subject_template', '')),
            'body_template_html' => trim((string) $request->input('body_template_html', '')),
            'body_template_text' => trim((string) $request->input('body_template_text', '')),
            'recipients_to' => trim((string) $request->input('recipients_to', '')),
            'recipients_cc' => trim((string) $request->input('recipients_cc', '')),
            'enabled' => $request->has('enabled') ? 1 : 0,
        ];

        $userId = $this->currentUserId();
        $this->service->saveTemplate($templateKey, $data, $userId);

        return redirect('/mail-templates/cobertura/' . urlencode($templateKey) . '?status=updated');
    }

    public function resolve(Request $request): JsonResponse
    {
        $payload = $request->isJson()
            ? (array) $request->json()->all()
            : $request->all();

        $afiliacion = trim((string) ($payload['afiliacion'] ?? ''));
        $templateKey = trim((string) ($payload['template_key'] ?? ''));
        $template = null;

        if ($templateKey !== '') {
            $candidate = $this->service->findTemplate($templateKey);
            if ($candidate && (int) ($candidate['enabled'] ?? 0) === 1) {
                $template = $candidate;
            }
        }

        if (!$template) {
            $template = $this->service->getTemplateForAffiliation($afiliacion);
        }

        if (!$template) {
            return response()->json(['success' => false, 'message' => 'No hay plantilla configurada para esta afiliación.'], 404);
        }

        $variables = $this->service->buildVariables([
            'PACIENTE' => trim((string) ($payload['nombre'] ?? 'Paciente')),
            'HC' => trim((string) ($payload['hc_number'] ?? '—')),
            'PROC' => trim((string) ($payload['procedimiento'] ?? 'Procedimiento solicitado')),
            'PLAN' => trim((string) ($payload['plan'] ?? 'Plan de consulta')),
            'EXAMENES_PENDIENTES' => trim((string) ($payload['examenes_pendientes'] ?? '')),
            'EXAMENES_PENDIENTES_HTML' => trim((string) ($payload['examenes_pendientes_html'] ?? '')),
            'FORM_ID' => trim((string) ($payload['form_id'] ?? '—')),
            'PDF_URL' => trim((string) ($payload['pdf_url'] ?? '')),
        ]);

        $resolved = $this->service->hydrateTemplate($template, $variables);

        return response()->json([
            'success' => true,
            'template' => $resolved,
        ]);
    }

    private function currentUserId(): int
    {
        $authId = Auth::id();
        if (is_numeric($authId)) {
            return (int) $authId;
        }

        $sessionId = session('user_id');

        return is_numeric($sessionId) ? (int) $sessionId : 0;
    }
}
