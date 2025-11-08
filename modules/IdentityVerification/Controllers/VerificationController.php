<?php

namespace Modules\IdentityVerification\Controllers;

use Core\BaseController;
use Modules\IdentityVerification\Models\VerificationModel;
use Modules\IdentityVerification\Services\FaceRecognitionService;
use Modules\IdentityVerification\Services\SignatureAnalysisService;
use PDO;

class VerificationController extends BaseController
{
    private VerificationModel $verifications;
    private FaceRecognitionService $faceRecognition;
    private SignatureAnalysisService $signatureAnalysis;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->verifications = new VerificationModel($pdo);
        $this->faceRecognition = new FaceRecognitionService();
        $this->signatureAnalysis = new SignatureAnalysisService();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.view', 'pacientes.verification.manage']);

        $certifications = $this->verifications->getRecent(25);
        $status = $_GET['status'] ?? null;
        $errors = [];
        $selectedPatient = null;
        $selectedCertification = null;
        $old = [];

        $lookupPatientId = isset($_GET['patient_id']) ? $this->normalizePatientId((string) $_GET['patient_id']) : '';
        if ($lookupPatientId !== '') {
            $selectedPatient = $this->verifications->findPatientSummary($lookupPatientId);
            if ($selectedPatient) {
                $old['patient_id'] = $selectedPatient['hc_number'];
                if (!empty($selectedPatient['cedula'])) {
                    $old['document_number'] = $selectedPatient['cedula'];
                }
                $selectedCertification = $this->verifications->findByPatient($selectedPatient['hc_number']);
                if ($selectedCertification) {
                    $selectedCertification['completeness'] = $this->summarizeCertificationCompleteness($selectedCertification);
                }
            } else {
                $errors['patient_id'] = 'No se encontró un paciente con la historia clínica proporcionada.';
                $old['patient_id'] = $lookupPatientId;
            }
        }

        $this->render(BASE_PATH . '/modules/IdentityVerification/views/index.php', [
            'pageTitle' => 'Certificación biométrica de pacientes',
            'certifications' => $certifications,
            'status' => $status,
            'errors' => $errors,
            'old' => $old,
            'selectedPatient' => $selectedPatient,
            'selectedCertification' => $selectedCertification,
            'scripts' => [
                'js/modules/patient-verification.js',
            ],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.manage']);

        $input = $this->collectInput();
        $input['patient_id'] = $this->normalizePatientId($input['patient_id'] ?? '');

        $patient = null;
        $existing = null;
        if ($input['patient_id'] !== '') {
            $patient = $this->verifications->findPatientSummary($input['patient_id']);
            $existing = $this->verifications->findByPatient($input['patient_id']);

            if ($patient && empty($input['document_number'])) {
                $input['document_number'] = (string) ($patient['cedula'] ?? '');
            }

            if ($existing && empty($input['document_type'])) {
                $input['document_type'] = (string) ($existing['document_type'] ?? '');
            }

            if ($existing) {
                $existing['completeness'] = $this->summarizeCertificationCompleteness($existing);
            }
        }

        $errors = $this->validateCertificationInput($input, $patient, $existing);

        if (!empty($errors)) {
            $certifications = $this->verifications->getRecent(25);
            $this->render(BASE_PATH . '/modules/IdentityVerification/views/index.php', [
                'pageTitle' => 'Certificación biométrica de pacientes',
                'certifications' => $certifications,
                'status' => null,
                'errors' => $errors,
                'old' => $input,
                'selectedPatient' => $patient,
                'selectedCertification' => $existing,
                'scripts' => [
                    'js/modules/patient-verification.js',
                ],
            ]);
            return;
        }

        $signaturePath = $this->persistDataUri($input['signature_data'] ?? '', 'signatures');
        $facePath = $this->persistDataUri($input['face_image'] ?? '', 'faces');
        $documentSignaturePath = $this->persistDataUri($input['document_signature_data'] ?? '', 'document_signatures');

        $documentFrontPath = $this->persistUploadedFile('document_front', 'documents');
        $documentBackPath = $this->persistUploadedFile('document_back', 'documents');

        $signaturePath = $signaturePath ?? ($existing['signature_path'] ?? null);
        $facePath = $facePath ?? ($existing['face_image_path'] ?? null);
        $documentSignaturePath = $documentSignaturePath ?? ($existing['document_signature_path'] ?? null);
        $documentFrontPath = $documentFrontPath ?? ($existing['document_front_path'] ?? null);
        $documentBackPath = $documentBackPath ?? ($existing['document_back_path'] ?? null);

        $signatureTemplate = null;
        if (($input['signature_data'] ?? '') !== '' && $signaturePath !== null) {
            $signatureTemplate = $this->signatureAnalysis->createTemplateFromFile(BASE_PATH . '/' . $signaturePath);
        } elseif (!empty($existing['signature_template'])) {
            $signatureTemplate = $existing['signature_template'];
        } elseif ($signaturePath !== null) {
            $signatureTemplate = $this->signatureAnalysis->createTemplateFromFile(BASE_PATH . '/' . $signaturePath);
        }

        $faceTemplate = null;
        if (($input['face_image'] ?? '') !== '' && $facePath !== null) {
            $faceTemplate = $this->faceRecognition->createTemplateFromFile(BASE_PATH . '/' . $facePath);
        } elseif (!empty($existing['face_template'])) {
            $faceTemplate = $existing['face_template'];
        } elseif ($facePath !== null) {
            $faceTemplate = $this->faceRecognition->createTemplateFromFile(BASE_PATH . '/' . $facePath);
        }

        $documentNumber = $input['document_number'] !== ''
            ? $input['document_number']
            : (string) ($existing['document_number'] ?? '');

        $documentType = $input['document_type'] ?? ($existing['document_type'] ?? 'cedula');

        $payload = [
            'patient_id' => $input['patient_id'],
            'document_number' => $documentNumber,
            'document_type' => $documentType,
            'signature_path' => $signaturePath,
            'signature_template' => $signatureTemplate,
            'document_signature_path' => $documentSignaturePath,
            'document_front_path' => $documentFrontPath,
            'document_back_path' => $documentBackPath,
            'face_image_path' => $facePath,
            'face_template' => $faceTemplate,
            'status' => $this->determineCertificationStatus([
                'signature_path' => $signaturePath,
                'signature_template' => $signatureTemplate,
                'face_template' => $faceTemplate,
                'document_number' => $documentNumber,
                'document_front_path' => $documentFrontPath,
                'document_back_path' => $documentBackPath,
            ]),
            'updated_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($existing) {
            $this->verifications->update((int) $existing['id'], $payload);
        } else {
            $payload['created_by'] = $_SESSION['user_id'] ?? null;
            $this->verifications->create($payload);
        }

        $statusParam = $payload['status'] === 'verified' ? 'stored' : 'stored_pending';

        header('Location: /pacientes/certificaciones?status=' . $statusParam . '&patient_id=' . urlencode($input['patient_id']));
        exit;
    }

    public function show(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.view', 'pacientes.verification.manage']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $patientId = isset($_GET['patient_id']) ? $this->normalizePatientId((string) $_GET['patient_id']) : '';

        $certification = null;
        if ($id > 0) {
            $certification = $this->verifications->find($id);
        } elseif ($patientId !== '') {
            $certification = $this->verifications->findByPatient($patientId);
        }

        if (!$certification) {
            $this->json([
                'ok' => false,
                'message' => 'No se encontró una certificación para el paciente solicitado.',
            ], 404);
            return;
        }

        $certification['completeness'] = $this->summarizeCertificationCompleteness($certification);

        $this->json([
            'ok' => true,
            'data' => $certification,
        ]);
    }

    public function consentDocument(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.view', 'pacientes.verification.manage']);

        $checkinId = isset($_GET['checkin_id']) ? (int) $_GET['checkin_id'] : 0;
        if ($checkinId <= 0) {
            http_response_code(404);
            echo 'Registro de verificación no encontrado.';
            return;
        }

        $checkin = $this->verifications->findCheckinWithCertification($checkinId);
        if (!$checkin) {
            http_response_code(404);
            echo 'Registro de verificación no encontrado.';
            return;
        }

        $canRenderConsent = in_array($checkin['verification_result'], ['approved', 'manual_review'], true);

        $this->render(BASE_PATH . '/modules/IdentityVerification/views/consent_document.php', [
            'pageTitle' => 'Documento de atención del paciente',
            'checkin' => $checkin,
            'canRenderConsent' => $canRenderConsent,
        ]);
    }

    public function verify(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.view', 'pacientes.verification.manage']);

        $certificationId = isset($_POST['certification_id']) ? (int) $_POST['certification_id'] : 0;
        $patientId = isset($_POST['patient_id']) ? $this->normalizePatientId((string) $_POST['patient_id']) : '';

        $certification = null;
        if ($certificationId > 0) {
            $certification = $this->verifications->find($certificationId);
        }

        if (!$certification && $patientId !== '') {
            $certification = $this->verifications->findByPatient($patientId);
        }

        if (!$certification) {
            $this->json([
                'ok' => false,
                'message' => 'No existe una certificación registrada para el paciente proporcionado.',
            ], 404);
            return;
        }

        $completeness = $this->summarizeCertificationCompleteness($certification);
        if (!$completeness['is_complete']) {
            $this->json([
                'ok' => false,
                'message' => 'La certificación biométrica está incompleta. Capture los datos faltantes antes de verificar.',
                'missing' => $completeness['missing'],
            ], 409);
            return;
        }

        if (empty($certification['face_template'])) {
            $this->json([
                'ok' => false,
                'message' => 'No existe una plantilla facial registrada para este paciente. Complete la certificación inicial.',
            ], 409);
            return;
        }

        $faceData = trim((string) ($_POST['face_image'] ?? ''));
        if ($faceData === '') {
            $this->json([
                'ok' => false,
                'message' => 'Debe capturar el rostro del paciente para continuar con la verificación.',
            ], 422);
            return;
        }

        $faceTempPath = $this->persistDataUri($faceData, 'verifications');
        if ($faceTempPath === null) {
            $this->json([
                'ok' => false,
                'message' => 'No fue posible procesar la captura facial. Intente nuevamente.',
            ], 422);
            return;
        }

        $faceTemplate = $this->faceRecognition->createTemplateFromFile(BASE_PATH . '/' . $faceTempPath);
        $faceScore = $this->faceRecognition->compareTemplates(
            $certification['face_template'] ?? null,
            $faceTemplate
        );

        if ($faceScore === null) {
            $this->json([
                'ok' => false,
                'message' => 'No se pudo calcular la similitud facial con los datos registrados.',
            ], 422);
            return;
        }

        $result = $this->determineResult(null, $faceScore);

        $metadata = [
            'patient_id' => $certification['patient_id'],
            'document_number' => $certification['document_number'],
            'face_capture' => $faceTempPath,
        ];

        $checkinId = $this->verifications->logCheckin((int) $certification['id'], [
            'verified_signature_score' => null,
            'verified_face_score' => $faceScore,
            'verification_result' => $result,
            'metadata' => $metadata,
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        $statusUpdate = match ($result) {
            'approved' => 'verified',
            'rejected' => 'revoked',
            default => 'pending',
        };

        $this->verifications->touchVerificationMetadata((int) $certification['id'], $result, $statusUpdate);

        $consentUrl = null;
        if (in_array($result, ['approved', 'manual_review'], true)) {
            $consentUrl = '/pacientes/certificaciones/comprobante?checkin_id=' . $checkinId;
        }

        $this->json([
            'ok' => true,
            'result' => $result,
            'faceScore' => $faceScore,
            'checkinId' => $checkinId,
            'consentUrl' => $consentUrl,
        ]);
    }

    private function validateCertificationInput(array $input, ?array $patient, ?array $existing): array
    {
        $errors = [];

        $patientId = trim((string) ($input['patient_id'] ?? ''));
        $documentNumber = trim((string) ($input['document_number'] ?? ''));
        $signatureData = trim((string) ($input['signature_data'] ?? ''));
        $faceData = trim((string) ($input['face_image'] ?? ''));

        if ($patientId === '') {
            $errors['patient_id'] = 'Debe indicar el identificador interno del paciente.';
        } elseif ($patient === null) {
            $errors['patient_id'] = 'El paciente indicado no existe en patient_data. Verifique la historia clínica ingresada.';
        }

        $hasExistingDocument = !empty($existing['document_number'] ?? '');
        if ($documentNumber === '' && !$hasExistingDocument) {
            $errors['document_number'] = 'Debe indicar la cédula o documento de identidad.';
        }

        $hasExistingSignature = !empty($existing['signature_path'] ?? '');
        if ($signatureData === '' && !$hasExistingSignature) {
            $errors['signature_data'] = 'Es necesario capturar la firma manuscrita del paciente.';
        }

        $hasExistingFace = !empty($existing['face_template'] ?? null) || !empty($existing['face_image_path'] ?? '');
        if ($faceData === '' && !$hasExistingFace) {
            $errors['face_image'] = 'Debe registrar una imagen facial del paciente.';
        }

        return $errors;
    }

    private function normalizePatientId(?string $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private function persistDataUri(string $dataUri, string $folder): ?string
    {
        $dataUri = trim($dataUri);
        if ($dataUri === '') {
            return null;
        }

        if (!preg_match('#^data:(.*?);base64,(.*)$#', $dataUri, $matches)) {
            return null;
        }

        $mime = strtolower(trim($matches[1]));
        $data = base64_decode(str_replace(' ', '+', $matches[2]), true);
        if ($data === false) {
            return null;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => null,
        };

        if ($extension === null) {
            return null;
        }

        $directory = BASE_PATH . '/storage/patient_verification/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No fue posible crear el directorio de almacenamiento: ' . $directory);
        }

        $filename = $folder . '-' . uniqid('', true) . '.' . $extension;
        $path = $directory . '/' . $filename;
        if (file_put_contents($path, $data) === false) {
            return null;
        }

        return 'storage/patient_verification/' . $folder . '/' . $filename;
    }

    private function persistUploadedFile(string $field, string $folder): ?string
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = $file['tmp_name'] ?? null;
        if (!$tmpName || !is_uploaded_file($tmpName)) {
            return null;
        }

        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime === false || !isset($allowed[$mime])) {
            return null;
        }

        $directory = BASE_PATH . '/storage/patient_verification/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No fue posible crear el directorio de almacenamiento: ' . $directory);
        }

        $filename = $folder . '-' . uniqid('', true) . '.' . $allowed[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            return null;
        }

        return 'storage/patient_verification/' . $folder . '/' . $filename;
    }

    private function collectInput(): array
    {
        $data = [];
        foreach ($_POST as $key => $value) {
            $data[$key] = is_string($value) ? trim($value) : $value;
        }
        return $data;
    }

    private function determineResult(?float $signatureScore, ?float $faceScore): string
    {
        $hasSignature = $signatureScore !== null;
        $hasFace = $faceScore !== null;

        if ($hasSignature && $hasFace) {
            if ($signatureScore >= 80 && $faceScore >= 80) {
                return 'approved';
            }

            if ($signatureScore < 40 || $faceScore < 40) {
                return 'rejected';
            }

            return 'manual_review';
        }

        $score = $hasSignature ? $signatureScore : $faceScore;
        if ($score === null) {
            return 'manual_review';
        }

        if ($score >= 85) {
            return 'approved';
        }

        if ($score < 40) {
            return 'rejected';
        }

        return 'manual_review';
    }

    private function determineCertificationStatus(array $data): string
    {
        $hasSignature = !empty($data['signature_path']) && !empty($data['signature_template']);
        $hasFace = !empty($data['face_template']);
        $hasDocumentNumber = !empty($data['document_number']);
        $hasDocumentImages = !empty($data['document_front_path']) && !empty($data['document_back_path']);

        if ($hasSignature && $hasFace && $hasDocumentNumber && $hasDocumentImages) {
            return 'verified';
        }

        return 'pending';
    }

    private function summarizeCertificationCompleteness(array $certification): array
    {
        $missing = [];

        if (empty($certification['signature_path'])) {
            $missing[] = 'firma manuscrita';
        }

        if (empty($certification['face_template']) && empty($certification['face_image_path'])) {
            $missing[] = 'captura facial';
        }

        if (empty($certification['document_number'])) {
            $missing[] = 'número de documento';
        }

        if (empty($certification['document_front_path'])) {
            $missing[] = 'anverso del documento';
        }

        if (empty($certification['document_back_path'])) {
            $missing[] = 'reverso del documento';
        }

        return [
            'is_complete' => empty($missing),
            'missing' => $missing,
        ];
    }
}
