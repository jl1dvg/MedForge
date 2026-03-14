<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use App\Modules\Reporting\Services\ReportPdfService;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Examenes\Models\ExamenModel;
use Modules\Examenes\Services\ExamenCrmService;
use Modules\Examenes\Services\ExamenEstadoService;
use Modules\Examenes\Services\ExamenMailLogService;
use Modules\Mail\Services\MailProfileService;
use Modules\Mail\Services\NotificationMailer;
use Modules\MailTemplates\Services\CoberturaMailTemplateService;
use Modules\Pacientes\Services\PacienteService;
use PDO;
use RuntimeException;
use Throwable;

class ExamenesPrefacturaService
{
    private const COBERTURA_MAIL_TO = 'cespinoza@cive.ec';
    private const COBERTURA_MAIL_CC = ['oespinoza@cive.ec'];

    private PDO $db;
    private ExamenModel $examenModel;
    private ExamenEstadoService $estadoService;
    private PacienteService $pacienteService;
    private ExamenCrmService $crmService;
    private ReportPdfService $reportPdfService;

    public function __construct(?PDO $pdo = null)
    {
        LegacyExamenesRuntime::boot();

        $this->db = $pdo ?? DB::connection()->getPdo();
        $this->examenModel = new ExamenModel($this->db);
        $this->estadoService = new ExamenEstadoService();
        $this->pacienteService = new PacienteService($this->db);
        $this->crmService = new ExamenCrmService($this->db);
        $this->reportPdfService = new ReportPdfService();
    }

    /**
     * @return array<string,mixed>
     */
    public function buildPrefacturaViewData(string $hcNumber, string $formId, ?int $examenId = null, ?int $currentUserId = null): array
    {
        $hcNumber = trim($hcNumber);
        $formId = trim($formId);
        if ($hcNumber === '' || $formId === '') {
            return ['examen' => null];
        }

        $examen = $this->examenModel->obtenerExamenPorFormHc($formId, $hcNumber, $examenId);
        if (!$examen) {
            $candidatos = $this->examenModel->obtenerExamenesPorFormId($formId);
            $primero = is_array($candidatos) && $candidatos !== [] ? $candidatos[0] : null;
            if (is_array($primero)) {
                $hcAlterno = trim((string) ($primero['hc_number'] ?? ''));
                $idAlterno = (int) ($primero['id'] ?? 0);
                if ($hcAlterno !== '') {
                    $examen = $this->examenModel->obtenerExamenPorFormHc(
                        $formId,
                        $hcAlterno,
                        $idAlterno > 0 ? $idAlterno : null
                    );
                    if ($examen) {
                        $hcNumber = $hcAlterno;
                    }
                }
            }
        }

        if (!$examen) {
            return ['examen' => null];
        }

        $examenIdValue = isset($examen['id']) ? (int) $examen['id'] : 0;
        if ($examenIdValue > 0) {
            $this->ensureDerivacionPreseleccionAuto($hcNumber, $formId, $examenIdValue);
        }

        $derivacion = null;
        try {
            $derivacion = $this->ensureDerivacion($formId, $hcNumber, $examenIdValue > 0 ? $examenIdValue : null);
        } catch (Throwable $e) {
            Log::warning('examenes.prefactura.derivacion.error', [
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'examen_id' => $examenIdValue > 0 ? $examenIdValue : null,
                'error' => $e->getMessage(),
            ]);
        }

        $fechaVigencia = $derivacion['fecha_vigencia'] ?? ($examen['derivacion_fecha_vigencia_sel'] ?? null);
        $vigenciaStatus = $this->resolveDerivacionVigenciaStatus(is_string($fechaVigencia) ? $fechaVigencia : null);
        $estadoSugerido = $this->resolveEstadoPorDerivacion($vigenciaStatus, (string) ($examen['estado'] ?? ''));
        if ($estadoSugerido !== null) {
            $this->actualizarEstadoPorFormHc(
                $formId,
                $hcNumber,
                $estadoSugerido,
                $currentUserId,
                'derivacion_vigencia',
                'Actualizado por vigencia de derivación'
            );
            $examen['estado'] = $estadoSugerido;
        }

        $consulta = $this->examenModel->obtenerConsultaPorFormHc($formId, $hcNumber) ?? [];
        if ($consulta === []) {
            $consulta = $this->examenModel->obtenerConsultaPorFormId($formId) ?? [];
        }

        $hcConsulta = trim((string) ($consulta['hc_number'] ?? ''));
        $paciente = $this->pacienteService->getPatientDetails($hcNumber);
        if ((!is_array($paciente) || $paciente === []) && $hcConsulta !== '' && $hcConsulta !== $hcNumber) {
            $paciente = $this->pacienteService->getPatientDetails($hcConsulta);
        }
        if (is_array($paciente) && trim((string) ($paciente['hc_number'] ?? '')) === '' && $hcConsulta !== '') {
            $paciente['hc_number'] = $hcConsulta;
        }

        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $doctorFromJoin = trim((string) ($consulta['doctor_nombre'] ?? ($consulta['procedimiento_doctor'] ?? '')));
            if ($doctorFromJoin !== '') {
                $consulta['doctor'] = $doctorFromJoin;
            }
        }

        $examenesRelacionados = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);
        if ($examenesRelacionados === []) {
            $examenesRelacionados = $this->examenModel->obtenerExamenesPorFormId($formId);
        }
        $examenesRelacionados = array_map(fn(array $row): array => $this->transformExamenRow($row), $examenesRelacionados);
        $examenesRelacionados = $this->estadoService->enrichExamenes($examenesRelacionados);
        foreach ($examenesRelacionados as &$rel) {
            if (empty($rel['derivacion_status']) && $vigenciaStatus !== null) {
                $rel['derivacion_status'] = $vigenciaStatus;
            }
        }
        unset($rel);

        $consultaSolicitante = trim((string) ($consulta['solicitante'] ?? ''));
        if ($consultaSolicitante === '') {
            foreach ($examenesRelacionados as $rel) {
                $candidate = trim((string) ($rel['solicitante'] ?? ''));
                if ($candidate !== '') {
                    $consultaSolicitante = $candidate;
                    break;
                }
            }
            if ($consultaSolicitante !== '') {
                $consulta['solicitante'] = $consultaSolicitante;
            }
        }

        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $doctor = '';
            foreach ($examenesRelacionados as $rel) {
                $candidate = trim((string) ($rel['doctor'] ?? ($rel['solicitante'] ?? '')));
                if ($candidate !== '') {
                    $doctor = $candidate;
                    break;
                }
            }
            if ($doctor === '') {
                $doctor = $this->examenModel->obtenerDoctorProcedimientoProyectado($formId, $hcNumber) ?? '';
            }
            if ($doctor !== '') {
                $consulta['doctor'] = $doctor;
            }
        }

        $consulta = $this->enriquecerDoctorConsulta012A($consulta);

        $crmResumen = [];
        try {
            $crmResumen = $this->crmService->obtenerResumen($examenIdValue);
        } catch (Throwable $e) {
            Log::warning('examenes.prefactura.crm.error', [
                'examen_id' => $examenIdValue,
                'error' => $e->getMessage(),
            ]);
        }

        $imagenesSolicitadas = $this->extraerImagenesSolicitadas(
            $consulta['examenes'] ?? null,
            $examenesRelacionados,
            is_array($crmResumen['adjuntos'] ?? null) ? $crmResumen['adjuntos'] : []
        );
        $diagnosticos = $this->extraerDiagnosticosDesdeConsulta($consulta);
        $trazabilidad = $this->construirTrazabilidad($examen, $crmResumen);

        $afiliacion = trim((string) (($paciente['afiliacion'] ?? null) ?: ($examen['afiliacion'] ?? '')));
        $templateService = new CoberturaMailTemplateService($this->db);
        $templateKey = $templateService->resolveImagenesTemplateKey($afiliacion);
        $templateAvailable = false;
        if ($templateKey && $templateService->hasEnabledTemplate($templateKey)) {
            $templateAvailable = true;
        } else {
            $baseTemplateKey = $templateService->resolveTemplateKey($afiliacion);
            $examenTemplateKey = $baseTemplateKey ? $baseTemplateKey . '_examenes' : null;
            if ($examenTemplateKey && $templateService->hasEnabledTemplate($examenTemplateKey)) {
                $templateKey = $examenTemplateKey;
                $templateAvailable = true;
            } elseif ($baseTemplateKey && $templateService->hasEnabledTemplate($baseTemplateKey)) {
                $templateKey = $baseTemplateKey;
                $templateAvailable = true;
            }
        }

        $mailLog = null;
        if ($examenIdValue > 0) {
            $mailLogService = new ExamenMailLogService($this->db);
            $mailLog = $mailLogService->fetchLatestByExamen($examenIdValue);
        }

        return [
            'examen' => $examen,
            'paciente' => is_array($paciente) ? $paciente : [],
            'consulta' => $consulta,
            'diagnostico' => $diagnosticos,
            'imagenes_solicitadas' => $imagenesSolicitadas,
            'examenes_relacionados' => $examenesRelacionados,
            'trazabilidad' => $trazabilidad,
            'crm' => $crmResumen,
            'derivacion' => $derivacion,
            'derivacion_vigencia' => $vigenciaStatus,
            'coberturaTemplateKey' => $templateKey,
            'coberturaTemplateAvailable' => $templateAvailable,
            'coberturaMailLog' => $mailLog,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function sendCoberturaMail(array $payload, ?UploadedFile $attachment, ?int $currentUserId): array
    {
        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $toRaw = trim((string) ($payload['to'] ?? ''));
        $ccRaw = trim((string) ($payload['cc'] ?? ''));
        $isHtml = filter_var($payload['is_html'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $examenId = isset($payload['examen_id']) ? (int) $payload['examen_id'] : null;
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $afiliacion = trim((string) ($payload['afiliacion'] ?? ''));
        $templateKey = trim((string) ($payload['template_key'] ?? ''));
        $derivacionPdf = trim((string) ($payload['derivacion_pdf'] ?? ''));

        if ($formId !== '' && $hcNumber !== '') {
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM consulta_examenes
                 WHERE form_id = :form_id AND hc_number = :hc_number
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':form_id' => $formId,
                ':hc_number' => $hcNumber,
            ]);
            $resolved = $stmt->fetchColumn();
            if ($resolved !== false) {
                $examenId = (int) $resolved;
            }
        }

        if ($subject === '' || $body === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Asunto y mensaje son obligatorios',
                ],
            ];
        }

        $toList = $this->parseCoberturaEmails($toRaw);
        $ccList = $this->parseCoberturaEmails($ccRaw);
        if ($toList === []) {
            $toList = [self::COBERTURA_MAIL_TO];
        }
        $toList = array_values(array_unique($toList));
        $ccList = array_values(array_unique(array_merge($ccList, self::COBERTURA_MAIL_CC)));

        $attachments = [];
        $generatedFiles = [];
        try {
            $autoAttachment = $this->buildCobertura012AAttachment($formId, $hcNumber, $examenId);
            if ($autoAttachment !== null) {
                $attachments[] = $autoAttachment;
                $generatedFiles[] = (string) $autoAttachment['path'];
            }

            $manualAttachment = $this->buildUploadedAttachment($attachment);
            if ($manualAttachment !== null) {
                $attachments[] = $manualAttachment;
            }

            $profileService = new MailProfileService($this->db);
            $profileSlug = $profileService->getProfileSlugForContext('examenes');
            $mailer = new NotificationMailer($this->db, $profileSlug);
            $result = $mailer->sendPatientUpdate($toList, $subject, $body, $ccList, $attachments, $isHtml, $profileSlug);
        } finally {
            foreach ($generatedFiles as $path) {
                if ($path !== '' && is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $sentAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $bodyText = $this->formatCoberturaMailBodyText($body, $isHtml);
        $mailLogService = new ExamenMailLogService($this->db);
        $mailLogPayload = [
            'examen_id' => $examenId ?: null,
            'form_id' => $formId !== '' ? $formId : null,
            'hc_number' => $hcNumber !== '' ? $hcNumber : null,
            'to_emails' => implode(', ', $toList),
            'cc_emails' => $ccList !== [] ? implode(', ', $ccList) : null,
            'subject' => $subject,
            'body_text' => $bodyText !== '' ? $bodyText : null,
            'body_html' => $isHtml ? $body : null,
            'channel' => 'email',
            'sent_by_user_id' => $currentUserId,
            'status' => 'sent',
            'error_message' => null,
            'sent_at' => $sentAt,
        ];

        if (!(($result['success'] ?? false) === true)) {
            $mailLogPayload['status'] = 'failed';
            $mailLogPayload['error_message'] = $result['error'] ?? 'No se pudo enviar el correo';
            try {
                $mailLogService->create($mailLogPayload);
            } catch (Throwable $e) {
                Log::warning('examenes.prefactura.mail_log_failed.error', [
                    'examen_id' => $examenId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => $result['error'] ?? 'No se pudo enviar el correo',
                ],
            ];
        }

        $mailLogId = null;
        try {
            $mailLogId = $mailLogService->create($mailLogPayload);
        } catch (Throwable $e) {
            Log::warning('examenes.prefactura.mail_log_sent.error', [
                'examen_id' => $examenId,
                'error' => $e->getMessage(),
            ]);
        }

        if (($examenId ?? 0) > 0) {
            $noteLines = [
                'Cobertura solicitada por correo',
                'Para: ' . implode(', ', $toList),
            ];
            if ($ccList !== []) {
                $noteLines[] = 'CC: ' . implode(', ', $ccList);
            }
            $noteLines[] = 'Asunto: ' . $subject;
            if ($templateKey !== '') {
                $noteLines[] = 'Plantilla: ' . $templateKey;
            }
            if ($derivacionPdf !== '') {
                $noteLines[] = 'PDF derivación: ' . $derivacionPdf;
            }

            try {
                $this->crmService->registrarNota((int) $examenId, implode("\n", $noteLines), $currentUserId);
            } catch (Throwable $e) {
                Log::warning('examenes.prefactura.crm_note.error', [
                    'examen_id' => $examenId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $sentByName = null;
        if (($mailLogId ?? 0) > 0) {
            $mailLog = $mailLogService->fetchById((int) $mailLogId);
            $sentByName = $mailLog['sent_by_name'] ?? null;
            $sentAt = $mailLog['sent_at'] ?? $sentAt;
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'ok' => true,
                'mail_log_id' => $mailLogId,
                'sent_at' => $sentAt,
                'sent_by' => $currentUserId,
                'sent_by_name' => $sentByName,
                'template_key' => $templateKey !== '' ? $templateKey : null,
            ],
        ];
    }

    private function buildCobertura012AAttachment(string $formId, string $hcNumber, ?int $examenId): ?array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        if ($formId === '' || $hcNumber === '') {
            return null;
        }

        $pdf = $this->reportPdfService->generateCobertura012APdf($formId, $hcNumber, $examenId);
        if (!is_array($pdf) || empty($pdf['content']) || empty($pdf['filename'])) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'cob012a_');
        if (!is_string($tempPath) || $tempPath === '') {
            return null;
        }

        if (file_put_contents($tempPath, (string) $pdf['content']) === false) {
            @unlink($tempPath);
            return null;
        }

        return [
            'path' => $tempPath,
            'name' => (string) $pdf['filename'],
            'type' => 'application/pdf',
        ];
    }

    private function buildUploadedAttachment(?UploadedFile $attachment): ?array
    {
        if (!$attachment || !$attachment->isValid()) {
            return null;
        }

        $realPath = $attachment->getRealPath();
        if (!is_string($realPath) || $realPath === '' || !is_file($realPath)) {
            return null;
        }

        return [
            'path' => $realPath,
            'name' => $attachment->getClientOriginalName() ?: null,
            'type' => $attachment->getClientMimeType() ?: null,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function parseCoberturaEmails(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $candidates = preg_split('/[;,]+/', $raw) ?: [];
        $emails = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = strtolower($candidate);
        }

        return array_values(array_unique($emails));
    }

    private function formatCoberturaMailBodyText(string $body, bool $isHtml): string
    {
        if (!$isHtml) {
            return $body;
        }

        $text = trim(strip_tags($body));
        if ($text === '') {
            return '';
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function ensureDerivacionPreseleccionAuto(string $hcNumber, string $formId, int $examenId): void
    {
        $selection = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        if (!empty($selection['derivacion_pedido_id'])) {
            return;
        }

        $selection = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        if (!empty($selection['derivacion_pedido_id'])) {
            $this->examenModel->guardarDerivacionPreseleccion($examenId, [
                'derivacion_codigo' => $selection['derivacion_codigo'] ?? null,
                'derivacion_pedido_id' => $selection['derivacion_pedido_id'] ?? null,
                'derivacion_lateralidad' => $selection['derivacion_lateralidad'] ?? null,
                'derivacion_fecha_vigencia_sel' => $selection['derivacion_fecha_vigencia_sel'] ?? null,
                'derivacion_prefactura' => $selection['derivacion_prefactura'] ?? null,
            ]);
            return;
        }

        $script = BASE_PATH . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            return;
        }

        $output = [];
        $exitCode = 0;
        exec(sprintf(
            'python3 %s %s --group --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        ), $output, $exitCode);

        $parsed = null;
        for ($index = count($output) - 1; $index >= 0; $index--) {
            $line = trim((string) ($output[$index] ?? ''));
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
                break;
            }
        }

        if (!is_array($parsed)) {
            return;
        }

        $grouped = is_array($parsed['grouped'] ?? null) ? $parsed['grouped'] : [];
        $options = [];
        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }
            $data = is_array($item['data'] ?? null) ? $item['data'] : [];
            $options[] = [
                'codigo_derivacion' => $item['codigo_derivacion'] ?? null,
                'pedido_id_mas_antiguo' => $item['pedido_id_mas_antiguo'] ?? null,
                'lateralidad' => $item['lateralidad'] ?? null,
                'fecha_vigencia' => $data['fecha_grupo'] ?? null,
                'prefactura' => $data['prefactura'] ?? null,
            ];
        }

        if (count($options) !== 1) {
            return;
        }

        $option = $options[0];
        $pedidoId = trim((string) ($option['pedido_id_mas_antiguo'] ?? ''));
        $codigo = trim((string) ($option['codigo_derivacion'] ?? ''));
        if ($pedidoId === '' || $codigo === '') {
            return;
        }

        $this->examenModel->guardarDerivacionPreseleccion($examenId, [
            'derivacion_codigo' => $codigo,
            'derivacion_pedido_id' => $pedidoId,
            'derivacion_lateralidad' => $option['lateralidad'] ?? null,
            'derivacion_fecha_vigencia_sel' => $option['fecha_vigencia'] ?? null,
            'derivacion_prefactura' => $option['prefactura'] ?? null,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function ensureDerivacion(string $formId, string $hcNumber, ?int $examenId = null): ?array
    {
        $selection = null;
        if ($examenId !== null && $examenId > 0) {
            $selection = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }
        if (!$selection) {
            $selection = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        $lookupFormId = (string) ($selection['derivacion_pedido_id'] ?? $formId);
        $hasSelection = trim((string) ($selection['derivacion_pedido_id'] ?? '')) !== '';

        if ($hasSelection) {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($lookupFormId);
            if ($derivacion) {
                return $derivacion;
            }
        } else {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($formId);
            if ($derivacion) {
                return $derivacion;
            }
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return null;
        }

        try {
            @exec(sprintf(
                'python3 %s %s %s',
                escapeshellarg($script),
                escapeshellarg($lookupFormId),
                escapeshellarg($hcNumber)
            ));
        } catch (Throwable) {
            return null;
        }

        return $this->examenModel->obtenerDerivacionPorFormId($lookupFormId) ?: null;
    }

    private function resolveDerivacionVigenciaStatus(?string $fechaVigencia): ?string
    {
        if ($fechaVigencia === null || trim($fechaVigencia) === '') {
            return null;
        }

        $dt = $this->parseFecha($fechaVigencia);
        if (!$dt instanceof DateTimeImmutable) {
            return null;
        }

        $today = new DateTimeImmutable('today');
        return $dt >= $today ? 'vigente' : 'vencida';
    }

    private function resolveEstadoPorDerivacion(?string $vigenciaStatus, string $estadoActual): ?string
    {
        if ($vigenciaStatus === null || $vigenciaStatus === '') {
            return null;
        }

        $slug = $this->estadoService->normalizeSlug($estadoActual);
        if ($slug === '') {
            $slug = 'recibido';
        }
        if ($slug === 'completado') {
            return null;
        }
        if ($vigenciaStatus === 'vencida') {
            return $slug !== 'revision-cobertura' ? 'Revisión de cobertura' : null;
        }
        if ($vigenciaStatus === 'vigente' && in_array($slug, ['recibido', 'llamado', 'revision-cobertura'], true)) {
            return 'Listo para agenda';
        }

        return null;
    }

    private function actualizarEstadoPorFormHc(
        string $formId,
        string $hcNumber,
        string $estado,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): void {
        if ($formId === '' || $hcNumber === '') {
            return;
        }

        $rows = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $this->examenModel->actualizarExamenParcial(
                $id,
                ['estado' => $estado],
                $changedBy,
                $origen,
                $observacion
            );
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function transformExamenRow(array $row): array
    {
        if (empty($row['fecha'] ?? null)) {
            $row['fecha'] = $row['consulta_fecha'] ?? ($row['created_at'] ?? null);
        }
        if (empty($row['procedimiento'] ?? null)) {
            $row['procedimiento'] = $row['examen_nombre'] ?? ($row['examen_codigo'] ?? null);
        }
        if (empty($row['tipo'] ?? null)) {
            $row['tipo'] = $row['examen_codigo'] ?? ($row['examen_nombre'] ?? null);
        }
        if (empty($row['observacion'] ?? null)) {
            $row['observacion'] = $row['observaciones'] ?? null;
        }
        if (empty($row['ojo'] ?? null)) {
            $row['ojo'] = $row['lateralidad'] ?? null;
        }
        if (!empty($row['derivacion_fecha_vigencia_sel']) && empty($row['derivacion_fecha_vigencia'])) {
            $row['derivacion_fecha_vigencia'] = $row['derivacion_fecha_vigencia_sel'];
        }
        $row['derivacion_status'] = $this->resolveDerivacionVigenciaStatus(
            isset($row['derivacion_fecha_vigencia']) ? (string) $row['derivacion_fecha_vigencia'] : null
        );

        return $row;
    }

    /**
     * @param mixed $rawExamenes
     * @param array<int,array<string,mixed>> $examenesRelacionados
     * @param array<int,array<string,mixed>> $adjuntosCrm
     * @return array<int,array<string,mixed>>
     */
    private function extraerImagenesSolicitadas(mixed $rawExamenes, array $examenesRelacionados, array $adjuntosCrm): array
    {
        $items = [];
        if (is_string($rawExamenes) && trim($rawExamenes) !== '') {
            $decoded = json_decode($rawExamenes, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        } elseif (is_array($rawExamenes)) {
            $items = $rawExamenes;
        }

        $normalizedAdjuntos = [];
        foreach ($adjuntosCrm as $adjunto) {
            if (!is_array($adjunto)) {
                continue;
            }
            $normalizedAdjuntos[] = [
                'raw' => $adjunto,
                'search' => $this->normalizarTexto(
                    (string) (($adjunto['descripcion'] ?? '') . ' ' . ($adjunto['nombre_original'] ?? ''))
                ),
            ];
        }

        $buildRecord = function (mixed $item, bool $allowNonImage) use ($examenesRelacionados, $normalizedAdjuntos): ?array {
            $nombre = null;
            $codigo = null;
            $fuente = 'Consulta';
            $fecha = null;

            if (is_array($item)) {
                $nombre = trim((string) ($item['nombre'] ?? ($item['examen'] ?? ($item['descripcion'] ?? ''))));
                $codigo = trim((string) ($item['codigo'] ?? ($item['id'] ?? ($item['code'] ?? ''))));
                $fuente = trim((string) ($item['fuente'] ?? ($item['origen'] ?? ''))) ?: 'Consulta';
                $fecha = $item['fecha'] ?? null;
            } elseif (is_string($item)) {
                $nombre = trim($item);
            }

            if ($nombre === null || $nombre === '') {
                return null;
            }
            if (!$allowNonImage && !$this->esEstudioImagen($nombre, $codigo)) {
                return null;
            }

            $nombreNorm = $this->normalizarTexto($nombre);
            $match = null;
            foreach ($examenesRelacionados as $rel) {
                $relNorm = $this->normalizarTexto((string) ($rel['examen_nombre'] ?? ''));
                if ($relNorm === '') {
                    continue;
                }
                if ($relNorm === $nombreNorm || str_contains($relNorm, $nombreNorm) || str_contains($nombreNorm, $relNorm)) {
                    $match = $rel;
                    break;
                }
            }

            $estado = $match['estado'] ?? 'Solicitado';
            $fuenteFinal = $fuente;
            if (($fuenteFinal === '' || $fuenteFinal === 'Consulta') && !empty($match['solicitante'])) {
                $fuenteFinal = (string) $match['solicitante'];
            }
            $fechaFinal = $match['consulta_fecha'] ?? ($fecha ?? ($match['created_at'] ?? null));

            $evidencias = [];
            foreach ($normalizedAdjuntos as $adjunto) {
                $search = $adjunto['search'] ?? '';
                if ($search === '' || !str_contains($search, $nombreNorm)) {
                    continue;
                }
                $raw = is_array($adjunto['raw'] ?? null) ? $adjunto['raw'] : [];
                $evidencias[] = [
                    'url' => $raw['url'] ?? null,
                    'descripcion' => $raw['descripcion'] ?? null,
                    'nombre' => $raw['nombre_original'] ?? null,
                ];
            }

            return [
                'nombre' => $nombre,
                'codigo' => $codigo !== '' ? $codigo : null,
                'estado' => $estado,
                'fuente' => $fuenteFinal !== '' ? $fuenteFinal : 'Consulta',
                'fecha' => $fechaFinal,
                'evidencias' => $evidencias,
                'evidencias_count' => count($evidencias),
            ];
        };

        $records = [];
        $seen = [];
        foreach ($items as $item) {
            $record = $buildRecord($item, false);
            if (!$record) {
                continue;
            }
            $key = $this->normalizarTexto((string) (($record['nombre'] ?? '') . '|' . ($record['codigo'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $records[] = $record;
        }

        if ($records === []) {
            foreach ($items as $item) {
                $record = $buildRecord($item, true);
                if (!$record) {
                    continue;
                }
                $key = $this->normalizarTexto((string) (($record['nombre'] ?? '') . '|' . ($record['codigo'] ?? '')));
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<int,array{dx_code:string,descripcion:string}>
     */
    private function extraerDiagnosticosDesdeConsulta(array $consulta): array
    {
        $raw = $consulta['diagnosticos'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizarDiagnosticos($decoded);
    }

    /**
     * @param array<int,mixed> $diagnosticos
     * @return array<int,array{dx_code:string,descripcion:string}>
     */
    private function normalizarDiagnosticos(array $diagnosticos): array
    {
        $result = [];
        $seen = [];

        foreach ($diagnosticos as $dx) {
            if (!is_array($dx)) {
                continue;
            }

            $code = trim((string) ($dx['dx_code'] ?? ($dx['codigo'] ?? '')));
            $desc = trim((string) ($dx['descripcion'] ?? ($dx['descripcion_dx'] ?? ($dx['nombre'] ?? ''))));

            if (($code === '' || $desc === '') && isset($dx['idDiagnostico'])) {
                [$parsedCode, $parsedDesc] = $this->parseDiagnosticoCie10((string) $dx['idDiagnostico']);
                if ($code === '') {
                    $code = $parsedCode;
                }
                if ($desc === '') {
                    $desc = $parsedDesc;
                }
            }

            if ($code === '' && $desc === '') {
                continue;
            }

            $key = strtoupper($code . '|' . $desc);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = [
                'dx_code' => $code,
                'descripcion' => $desc,
            ];
        }

        return $result;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseDiagnosticoCie10(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^\s*([A-Z][0-9A-Z\.]+)\s*[-–:]\s*(.+)\s*$/u', $value, $matches) === 1) {
            return [trim((string) ($matches[1] ?? '')), trim((string) ($matches[2] ?? ''))];
        }

        return ['', $value];
    }

    /**
     * @param array<string,mixed> $consulta
     * @return array<string,mixed>
     */
    private function enriquecerDoctorConsulta012A(array $consulta): array
    {
        $hasDoctorNames = trim((string) ($consulta['doctor_fname'] ?? '')) !== ''
            || trim((string) ($consulta['doctor_lname'] ?? '')) !== '';
        $hasFirma = trim((string) ($consulta['doctor_signature_path'] ?? '')) !== ''
            || trim((string) ($consulta['doctor_firma'] ?? '')) !== '';

        if ($hasDoctorNames && $hasFirma) {
            return $consulta;
        }

        $doctorNombreRef = trim((string) ($consulta['doctor'] ?? ''));
        if ($doctorNombreRef === '') {
            $doctorNombreRef = trim((string) ($consulta['doctor_nombre'] ?? ($consulta['procedimiento_doctor'] ?? '')));
        }
        if ($doctorNombreRef === '') {
            return $consulta;
        }

        $usuario = $this->obtenerUsuarioPorDoctorNombre($doctorNombreRef);
        if (!is_array($usuario) || $usuario === []) {
            return $consulta;
        }

        if (trim((string) ($consulta['doctor_fname'] ?? '')) === '') {
            $consulta['doctor_fname'] = (string) ($usuario['first_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_mname'] ?? '')) === '') {
            $consulta['doctor_mname'] = (string) ($usuario['middle_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_lname'] ?? '')) === '') {
            $consulta['doctor_lname'] = (string) ($usuario['last_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_lname2'] ?? '')) === '') {
            $consulta['doctor_lname2'] = (string) ($usuario['second_last_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_cedula'] ?? '')) === '') {
            $consulta['doctor_cedula'] = (string) ($usuario['cedula'] ?? '');
        }
        if (trim((string) ($consulta['doctor_signature_path'] ?? '')) === '') {
            $consulta['doctor_signature_path'] = (string) ($usuario['signature_path'] ?? '');
        }
        if (trim((string) ($consulta['doctor_firma'] ?? '')) === '') {
            $consulta['doctor_firma'] = (string) ($usuario['firma'] ?? '');
        }
        if ((int) ($consulta['doctor_user_id'] ?? 0) <= 0 && isset($usuario['id'])) {
            $consulta['doctor_user_id'] = (int) $usuario['id'];
        }

        return $consulta;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerUsuarioPorDoctorNombre(string $doctorNombre): ?array
    {
        $doctorNombre = trim($doctorNombre);
        if ($doctorNombre === '') {
            return null;
        }

        $variantes = $this->buildDoctorNombreVariantes($doctorNombre);
        if ($variantes === []) {
            return null;
        }

        $nombreNormPlaceholders = implode(', ', array_fill(0, count($variantes), '?'));
        $nombreRevPlaceholders = implode(', ', array_fill(0, count($variantes), '?'));
        $params = array_merge($variantes, $variantes);

        $sql = 'SELECT
                    u.id,
                    u.first_name,
                    u.middle_name,
                    u.last_name,
                    u.second_last_name,
                    u.cedula,
                    u.signature_path,
                    u.firma,
                    u.nombre
                FROM users u
                WHERE (
                    u.nombre_norm IN (' . $nombreNormPlaceholders . ')
                    OR u.nombre_norm_rev IN (' . $nombreRevPlaceholders . ')
                )
                  AND (
                    UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMÓLOGO"
                    OR UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMOLOGO"
                  )
                ORDER BY u.id ASC
                LIMIT 1';

        $row = DB::selectOne($sql, $params);
        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<int,string>
     */
    private function buildDoctorNombreVariantes(string $doctorNombre): array
    {
        $base = strtoupper(preg_replace('/\s+/', ' ', trim($doctorNombre)) ?? trim($doctorNombre));
        if ($base === '') {
            return [];
        }

        $variantes = [$base];
        $sinSns = preg_replace('/\bSNS\b/u', ' ', $base) ?? $base;
        $sinSns = trim(preg_replace('/\s+/', ' ', $sinSns) ?? $sinSns);
        if ($sinSns !== '' && $sinSns !== $base) {
            $variantes[] = $sinSns;
        }

        return array_values(array_unique($variantes));
    }

    /**
     * @param array<string,mixed> $examen
     * @param array<string,mixed> $crmResumen
     * @return array<int,array<string,mixed>>
     */
    private function construirTrazabilidad(array $examen, array $crmResumen): array
    {
        $events = [];

        if (!empty($examen['created_at'])) {
            $events[] = $this->crearEventoTrazabilidad(
                'estado',
                $examen['created_at'],
                'Examen registrado',
                'Estado inicial: ' . ((string) ($examen['estado'] ?? 'Pendiente')),
                null
            );
        }

        if (!empty($examen['updated_at']) && ($examen['updated_at'] ?? null) !== ($examen['created_at'] ?? null)) {
            $events[] = $this->crearEventoTrazabilidad(
                'estado',
                $examen['updated_at'],
                'Actualización operativa',
                'Último estado reportado: ' . ((string) ($examen['estado'] ?? 'Pendiente')),
                null
            );
        }

        foreach (($crmResumen['notas'] ?? []) as $nota) {
            if (!is_array($nota)) {
                continue;
            }
            $events[] = $this->crearEventoTrazabilidad(
                'nota',
                $nota['created_at'] ?? null,
                'Nota CRM',
                (string) ($nota['nota'] ?? ''),
                isset($nota['autor_nombre']) ? (string) $nota['autor_nombre'] : null
            );
        }

        foreach (($crmResumen['tareas'] ?? []) as $tarea) {
            if (!is_array($tarea)) {
                continue;
            }
            $title = trim((string) ($tarea['titulo'] ?? 'Tarea CRM'));
            $status = trim((string) ($tarea['estado'] ?? 'pendiente'));
            $description = $title . ' · Estado: ' . $status;
            if (!empty($tarea['due_date'])) {
                $description .= ' · Vence: ' . (string) $tarea['due_date'];
            }

            $events[] = $this->crearEventoTrazabilidad(
                'tarea',
                $tarea['updated_at'] ?? ($tarea['created_at'] ?? null),
                'Tarea CRM',
                $description,
                isset($tarea['assigned_name']) ? (string) $tarea['assigned_name'] : null
            );
        }

        foreach (($crmResumen['adjuntos'] ?? []) as $adjunto) {
            if (!is_array($adjunto)) {
                continue;
            }
            $description = trim((string) ($adjunto['descripcion'] ?? ''));
            $name = trim((string) ($adjunto['nombre_original'] ?? 'Documento'));
            $events[] = $this->crearEventoTrazabilidad(
                'adjunto',
                $adjunto['created_at'] ?? null,
                'Adjunto CRM',
                $description !== '' ? $description : $name,
                isset($adjunto['subido_por_nombre']) ? (string) $adjunto['subido_por_nombre'] : null
            );
        }

        foreach (($crmResumen['mail_events'] ?? []) as $mailEvent) {
            if (!is_array($mailEvent)) {
                continue;
            }
            $events[] = $this->crearEventoTrazabilidad(
                'correo',
                $mailEvent['created_at'] ?? null,
                'Correo saliente',
                (string) ($mailEvent['subject'] ?? 'Sin asunto'),
                isset($mailEvent['sent_by_name']) ? (string) $mailEvent['sent_by_name'] : null
            );
        }

        usort($events, static function (array $a, array $b): int {
            return strtotime((string) ($b['fecha'] ?? '')) <=> strtotime((string) ($a['fecha'] ?? ''));
        });

        return array_values(array_filter($events));
    }

    /**
     * @return array<string,mixed>
     */
    private function crearEventoTrazabilidad(string $tipo, mixed $fecha, string $titulo, string $detalle, ?string $autor): array
    {
        return [
            'tipo' => $tipo,
            'fecha' => $fecha,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'autor' => $autor,
        ];
    }

    private function esEstudioImagen(string $nombre, ?string $codigo = null): bool
    {
        $texto = $this->normalizarTexto($nombre . ' ' . ($codigo ?? ''));
        if ($texto === '') {
            return false;
        }

        foreach (['oct', 'tomografia', 'retinografia', 'angiografia', 'ecografia', 'ultrasonido', 'biometria', 'campimetria', 'paquimetria', 'resonancia', 'tac', 'rx', 'rayos x', 'fotografia', 'imagen'] as $keyword) {
            if (str_contains($texto, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $texto = preg_replace('/\p{Mn}/u', '', $normalized) ?? $texto;
            }
        }

        $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
        $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function parseFecha(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        $string = is_string($value) ? trim($value) : '';
        if ($string === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $string);
            if ($dt instanceof DateTimeImmutable) {
                return $format === 'Y-m-d' ? $dt->setTime(0, 0) : $dt;
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function projectRootPath(): string
    {
        return realpath(base_path('..')) ?: base_path('..');
    }
}
