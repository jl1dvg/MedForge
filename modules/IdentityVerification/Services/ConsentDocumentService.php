<?php

namespace Modules\IdentityVerification\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use Throwable;

class ConsentDocumentService
{
    public function __construct(private VerificationPolicyService $policy)
    {
    }

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
        $relativeHtmlPath = 'storage/patient_verification/consents/' . $filename;
        $path = BASE_PATH . '/' . $relativeHtmlPath;

        $signatureImg = $signaturePath ? '<img src="/' . ltrim($signaturePath, '/') . '" alt="Firma del paciente" style="max-height:140px;">' : '<em>No disponible</em>';
        $documentImg = $this->isEmbeddableImage($documentFront)
            ? '<img src="/' . ltrim((string) $documentFront, '/') . '" alt="Documento del paciente" style="max-height:140px;">'
            : '<em>No adjunto</em>';

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

        if ($this->policy->shouldGeneratePdf()) {
            $pdfPath = $this->generatePdfDocument(
                $consentId,
                [
                    'patient_name' => $patientName,
                    'patient_id' => (string) ($certification['patient_id'] ?? ''),
                    'document_number' => $documentNumber,
                    'document_type' => strtoupper($documentType),
                    'created_at' => $createdAt,
                    'result' => $result,
                    'user_id' => $userId,
                    'face_score' => $faceScore,
                    'signature_score' => $signatureScore,
                    'signature_path' => $signaturePath,
                    'document_front' => $documentFront,
                ]
            );

            if ($pdfPath !== null) {
                return $pdfPath;
            }
        }

        return $relativeHtmlPath;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function generatePdfDocument(string $consentId, array $data): ?string
    {
        try {
            $mpdf = new Mpdf([
                'tempDir' => $this->ensureTempDir(),
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_left' => 18,
                'margin_right' => 18,
            ]);
        } catch (Throwable) {
            return null;
        }

        $html = $this->buildPdfHtml($data);
        $mpdf->WriteHTML($html);

        $signatureConfig = $this->policy->getPdfSignatureConfig();
        $certificatePath = $this->resolveAbsolutePath($signatureConfig['certificate']);
        $keyPath = $this->resolveAbsolutePath($signatureConfig['key']);

        if ($certificatePath && $keyPath) {
            try {
                $mpdf->SetSignature(
                    'file://' . $certificatePath,
                    'file://' . $keyPath,
                    $signatureConfig['password'] ?? '',
                    '',
                    2,
                    [
                        'Name' => $signatureConfig['name'] ?: 'MedForge',
                        'Location' => $signatureConfig['location'] ?: '',
                        'Reason' => $signatureConfig['reason'] ?: 'Consentimiento de atención verificado',
                    ]
                );

                $appearance = $this->resolveSignatureAppearance($signatureConfig['image']);
                if ($appearance !== null) {
                    [$imgPath, $format] = $appearance;
                    $mpdf->Image($imgPath, 140, 250, 45, 18, $format);
                }
            } catch (Throwable) {
                // Continuar sin firma digital si hay inconvenientes
            }
        }

        $relativePdfPath = 'storage/patient_verification/consents/' . $consentId . '.pdf';

        try {
            $mpdf->Output(BASE_PATH . '/' . $relativePdfPath, Destination::FILE);
        } catch (Throwable) {
            return null;
        }

        return $relativePdfPath;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildPdfHtml(array $data): string
    {
        $patientName = $this->escape((string) ($data['patient_name'] ?? 'Paciente'));
        $patientId = $this->escape((string) ($data['patient_id'] ?? ''));
        $documentType = $this->escape((string) ($data['document_type'] ?? 'CEDULA'));
        $documentNumber = $this->escape((string) ($data['document_number'] ?? ''));
        $createdAt = $this->escape((string) ($data['created_at'] ?? date('Y-m-d H:i:s')));
        $result = $this->escape((string) ($data['result'] ?? 'pending'));
        $userId = $this->escape((string) ($data['user_id'] ?? ''));
        $faceScore = isset($data['face_score']) && $data['face_score'] !== null
            ? $this->escape(number_format((float) $data['face_score'], 2))
            : 'N/A';
        $signatureScore = isset($data['signature_score']) && $data['signature_score'] !== null
            ? $this->escape(number_format((float) $data['signature_score'], 2))
            : 'N/A';

        $signatureImg = $this->imageToDataUri($data['signature_path'] ?? null);
        $documentImg = $this->imageToDataUri($data['document_front'] ?? null);

        $signatureHtml = $signatureImg !== null
            ? '<img src="' . $signatureImg . '" style="max-height:120px;" alt="Firma del paciente">'
            : '<em>No disponible</em>';
        $documentHtml = $documentImg !== null
            ? '<img src="' . $documentImg . '" style="max-height:140px;" alt="Documento del paciente">'
            : '<em>No adjunto</em>';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; }
        h1 { font-size: 20px; margin-bottom: 6px; }
        h2 { font-size: 15px; margin-top: 18px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #d1d5db; font-size: 12px; }
        .metadata { font-size: 10px; color: #4b5563; }
        .section { margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>Consentimiento informado de atención</h1>
    <p class="metadata">Generado el {$createdAt} · Resultado del check-in: {$result} · Usuario ID: {$userId}</p>

    <div class="section">
        <h2>Datos del paciente</h2>
        <table>
            <tr>
                <th>Nombre completo</th>
                <td>{$patientName}</td>
            </tr>
            <tr>
                <th>Historia clínica</th>
                <td>{$patientId}</td>
            </tr>
            <tr>
                <th>Documento</th>
                <td>{$documentType} · {$documentNumber}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Resultados de verificación biométrica</h2>
        <table>
            <tr>
                <th>Puntaje facial</th>
                <td>{$faceScore}</td>
            </tr>
            <tr>
                <th>Puntaje de firma</th>
                <td>{$signatureScore}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Firma registrada</h2>
        {$signatureHtml}
    </div>

    <div class="section">
        <h2>Documento de identidad</h2>
        {$documentHtml}
    </div>
</body>
</html>
HTML;
    }

    private function imageToDataUri(?string $relativePath): ?string
    {
        $absolute = $this->resolveAbsolutePath($relativePath);
        if ($absolute === null || !is_file($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: '';
        if (strpos($mime, 'image/') !== 0) {
            return null;
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function ensureTempDir(): string
    {
        $directory = BASE_PATH . '/storage/cache/mpdf';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio temporal para PDF.');
        }

        return $directory;
    }

    private function resolveAbsolutePath(?string $relative): ?string
    {
        if (!is_string($relative) || $relative === '') {
            return null;
        }

        if (str_starts_with($relative, '/')) {
            $relative = ltrim($relative, '/');
        }

        $path = BASE_PATH . '/' . $relative;

        return is_file($path) ? $path : null;
    }

    private function resolveSignatureAppearance(?string $path): ?array
    {
        $absolute = $this->resolveAbsolutePath($path);
        if ($absolute === null) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: '';
        if (strpos($mime, 'image/') !== 0) {
            return null;
        }

        $format = strtoupper(substr($mime, strlen('image/')));

        return [$absolute, $format];
    }

    private function isEmbeddableImage(?string $relativePath): bool
    {
        $absolute = $this->resolveAbsolutePath($relativePath);
        if ($absolute === null) {
            return false;
        }

        $mime = mime_content_type($absolute) ?: '';

        return strpos($mime, 'image/') === 0;
    }
}
