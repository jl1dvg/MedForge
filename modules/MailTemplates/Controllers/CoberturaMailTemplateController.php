<?php

namespace Modules\MailTemplates\Controllers;

use Core\BaseController;
use Modules\MailTemplates\Services\CoberturaMailTemplateService;
use PDO;

class CoberturaMailTemplateController extends BaseController
{
    private CoberturaMailTemplateService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new CoberturaMailTemplateService($pdo);
    }

    public function index(?string $key = null): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $status = $_GET['status'] ?? null;
        $templates = $this->service->listTemplates();
        $selectedKey = $key ?? ($_GET['key'] ?? ($templates[0]['template_key'] ?? ''));

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
            $selected = $this->service->findTemplate($selectedKey);
        }

        $this->render(BASE_PATH . '/modules/MailTemplates/views/cobertura.php', [
            'pageTitle' => 'Plantillas de cobertura',
            'templates' => $templates,
            'selectedKey' => $selectedKey,
            'selectedTemplate' => $selected,
            'status' => $status,
        ]);
    }

    public function save(string $key): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $templateKey = trim((string)($_POST['template_key'] ?? $key));
        if ($key !== 'new') {
            $templateKey = $key;
        }

        if ($templateKey === '') {
            $this->json(['success' => false, 'error' => 'El key de plantilla es obligatorio.'], 422);
            return;
        }

        $data = [
            'name' => trim((string)($_POST['name'] ?? $templateKey)),
            'subject_template' => trim((string)($_POST['subject_template'] ?? '')),
            'body_template_html' => trim((string)($_POST['body_template_html'] ?? '')),
            'body_template_text' => trim((string)($_POST['body_template_text'] ?? '')),
            'recipients_to' => trim((string)($_POST['recipients_to'] ?? '')),
            'recipients_cc' => trim((string)($_POST['recipients_cc'] ?? '')),
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
        ];

        $this->service->saveTemplate($templateKey, $data, $this->currentUserId());

        header('Location: /mail-templates/cobertura/' . urlencode($templateKey) . '?status=updated');
        exit;
    }

    public function resolve(): void
    {
        $this->requireAuth();

        $payload = $_POST;
        if ($payload === []) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $afiliacion = trim((string)($payload['afiliacion'] ?? ''));
        $templateKey = trim((string)($payload['template_key'] ?? ''));
        $template = null;

        if ($templateKey !== '') {
            $candidate = $this->service->findTemplate($templateKey);
            if ($candidate && (int)($candidate['enabled'] ?? 0) === 1) {
                $template = $candidate;
            }
        }

        if (!$template) {
            $template = $this->service->getTemplateForAffiliation($afiliacion);
        }
        if (!$template) {
            $this->json(['success' => false, 'message' => 'No hay plantilla configurada para esta afiliación.'], 404);
            return;
        }

        $variables = $this->service->buildVariables([
            'PACIENTE' => trim((string)($payload['nombre'] ?? 'Paciente')),
            'HC' => trim((string)($payload['hc_number'] ?? '—')),
            'PROC' => trim((string)($payload['procedimiento'] ?? 'Procedimiento solicitado')),
            'PLAN' => trim((string)($payload['plan'] ?? 'Plan de consulta')),
            'EXAMENES_PENDIENTES' => trim((string)($payload['examenes_pendientes'] ?? '')),
            'EXAMENES_PENDIENTES_HTML' => trim((string)($payload['examenes_pendientes_html'] ?? '')),
            'FORM_ID' => trim((string)($payload['form_id'] ?? '—')),
            'PDF_URL' => trim((string)($payload['pdf_url'] ?? '')),
        ]);

        $resolved = $this->service->hydrateTemplate($template, $variables);

        $this->json([
            'success' => true,
            'template' => $resolved,
        ]);
    }
}
