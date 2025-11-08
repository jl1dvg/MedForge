<?php

namespace Modules\IdentityVerification\Services;

class ConsentDocumentService
{
    public function generate(array $certification, ?array $checkin, ?array $patientSummary): ?string
    {
        $directory = BASE_PATH . '/storage/patient_verification/consents';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        $patientName = $patientSummary['full_name'] ?? ($certification['patient_id'] ?? 'Paciente');
        $documentNumber = $certification['document_number'] ?? '';
        $documentType = $certification['document_type'] ?? 'cedula';
        $signaturePath = $certification['signature_path'] ?? null;
        $documentFront = $certification['document_front_path'] ?? null;
        $createdAt = $checkin['created_at'] ?? date('Y-m-d H:i:s');
        $result = $checkin['verification_result'] ?? $certification['status'] ?? 'pending';
        $faceScore = $checkin['verified_face_score'] ?? null;
        $signatureScore = $checkin['verified_signature_score'] ?? null;
        $userId = $checkin['created_by'] ?? null;

        $consentId = 'consent-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($certification['patient_id'] ?? '')) . '-' . date('YmdHis');
        $filename = $consentId . '.html';
        $path = $directory . '/' . $filename;

        $signatureImg = $signaturePath ? '<img src="/' . ltrim($signaturePath, '/') . '" alt="Firma del paciente" style="max-height:140px;">' : '<em>No disponible</em>';
        $documentImg = $documentFront ? '<img src="/' . ltrim($documentFront, '/') . '" alt="Documento del paciente" style="max-height:140px;">' : '<em>No adjunto</em>';

        $patientNameEsc = $this->escape($patientName);
        $createdAtEsc = $this->escape($createdAt);
        $resultEsc = $this->escape($result);
        $userIdEsc = $this->escape((string) $userId);
        $patientIdEsc = $this->escape((string) ($certification['patient_id'] ?? ''));
        $documentTypeEsc = $this->escape(strtoupper($documentType));
        $documentNumberEsc = $this->escape($documentNumber);
        $faceScoreLabel = $faceScore !== null
            ? $this->escape(number_format((float) $faceScore, 2))
            : $this->escape('N/A');
        $signatureScoreLabel = $signatureScore !== null
            ? $this->escape(number_format((float) $signatureScore, 2))
            : $this->escape('N/A');

        $content = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimiento de atención - {$patientNameEsc}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        h2 { font-size: 16px; margin-top: 24px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .section { margin-bottom: 24px; }
        .signature { margin-top: 16px; }
        .metadata { font-size: 12px; color: #4b5563; }
    </style>
</head>
<body>
    <h1>Consentimiento informado de atención</h1>
    <p class="metadata">Generado el {$createdAtEsc} · Resultado del check-in: {$resultEsc} · Usuario ID: {$userIdEsc}</p>

    <div class="section">
        <h2>Datos del paciente</h2>
        <table>
            <tr>
                <th>Nombre completo</th>
                <td>{$patientNameEsc}</td>
            </tr>
            <tr>
                <th>Historia clínica</th>
                <td>{$patientIdEsc}</td>
            </tr>
            <tr>
                <th>Documento</th>
                <td>{$documentTypeEsc} · {$documentNumberEsc}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Resultados de verificación biométrica</h2>
        <table>
            <tr>
                <th>Puntaje facial</th>
                <td>{$faceScoreLabel}</td>
            </tr>
            <tr>
                <th>Puntaje de firma</th>
                <td>{$signatureScoreLabel}</td>
            </tr>
        </table>
    </div>

    <div class="section signature">
        <h2>Firma registrada</h2>
        {$signatureImg}
    </div>

    <div class="section">
        <h2>Documento de identidad</h2>
        {$documentImg}
    </div>
</body>
</html>
HTML;

        if (file_put_contents($path, $content) === false) {
            return null;
        }

        return 'storage/patient_verification/consents/' . $filename;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
