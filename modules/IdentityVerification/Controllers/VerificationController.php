<?php

namespace Modules\IdentityVerification\Controllers;

use Core\BaseController;
use Modules\IdentityVerification\Models\VerificationModel;
use Modules\IdentityVerification\Services\ConsentDocumentService;
use Modules\IdentityVerification\Services\FaceRecognitionService;
use Modules\IdentityVerification\Services\SignatureAnalysisService;
use PDO;

class VerificationController extends BaseController
{
    private VerificationModel $verifications;
    private FaceRecognitionService $faceRecognition;
    private SignatureAnalysisService $signatureAnalysis;
    private ConsentDocumentService $consentDocumentService;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->verifications = new VerificationModel($pdo);
        $this->faceRecognition = new FaceRecognitionService();
        $this->signatureAnalysis = new SignatureAnalysisService();
        $this->consentDocumentService = new ConsentDocumentService();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.view', 'pacientes.verification.manage']);

        $certifications = $this->verifications->getRecent(25);
        $status = $_GET['status'] ?? null;
        $errors = [];
        $selectedPatient = null;
        $old = [];

        $lookupPatientId = isset($_GET['patient_id']) ? $this->normalizePatientId((string) $_GET['patient_id']) : '';
        if ($lookupPatientId !== '') {
            $selectedPatient = $this->verifications->findPatientSummary($lookupPatientId);
            if ($selectedPatient) {
                $old['patient_id'] = $selectedPatient['hc_number'];
                if (!empty($selectedPatient['cedula'])) {
                    $old['document_number'] = $selectedPatient['cedula'];
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

        $existing = null;
        $patient = null;
        if ($input['patient_id'] !== '') {
            $patient = $this->verifications->findPatientSummary($input['patient_id']);
            if ($patient && empty($input['document_number'])) {
                $input['document_number'] = (string) ($patient['cedula'] ?? '');
            }
            $existing = $this->verifications->findByPatient($input['patient_id']);
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
                'scripts' => [
                    'js/modules/patient-verification.js',
                ],
            ]);
            return;
        }

        $signaturePath = $existing['signature_path'] ?? null;
        $signatureTemplate = $existing['signature_template'] ?? null;
        if (!empty($input['signature_data'])) {
            $newSignaturePath = $this->persistDataUri($input['signature_data'], 'signatures');
            if ($newSignaturePath !== null) {
                $signaturePath = $newSignaturePath;
                $signatureTemplate = $this->signatureAnalysis->createTemplateFromFile(BASE_PATH . '/' . $signaturePath);
            }
        }

        $facePath = $existing['face_image_path'] ?? null;
        $faceTemplate = $existing['face_template'] ?? null;
        if (!empty($input['face_image'])) {
            $newFacePath = $this->persistDataUri($input['face_image'], 'faces');
            if ($newFacePath !== null) {
                $facePath = $newFacePath;
                $faceTemplate = $this->faceRecognition->createTemplateFromFile(BASE_PATH . '/' . $facePath);
            }
        }

        $documentSignaturePath = $existing['document_signature_path'] ?? null;
        if (!empty($input['document_signature_data'])) {
            $newDocumentSignaturePath = $this->persistDataUri($input['document_signature_data'], 'document_signatures');
            if ($newDocumentSignaturePath !== null) {
                $documentSignaturePath = $newDocumentSignaturePath;
            }
        }

        $documentFrontPath = $this->persistUploadedFile('document_front', 'documents') ?? ($existing['document_front_path'] ?? null);
        $documentBackPath = $this->persistUploadedFile('document_back', 'documents') ?? ($existing['document_back_path'] ?? null);

        $payload = [
            'patient_id' => $input['patient_id'],
            'document_number' => $input['document_number'],
            'document_type' => $input['document_type'] ?? 'cedula',
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
                'face_image_path' => $facePath,
                'face_template' => $faceTemplate,
                'document_number' => $input['document_number'],
            ]),
            'updated_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($existing) {
            $this->verifications->update((int) $existing['id'], $payload);
        } else {
            $payload['created_by'] = $_SESSION['user_id'] ?? null;
            $this->verifications->create($payload);
        }

        header('Location: /pacientes/certificaciones?status=stored');
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

        $this->json([
            'ok' => true,
            'data' => $certification,
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

        if (empty($certification['face_template']) && empty($certification['signature_template'])) {
            $this->json([
                'ok' => false,
                'message' => 'La certificación no cuenta con datos biométricos suficientes. Complete el registro antes de continuar.',
            ], 409);
            return;
        }

        $requiresFace = !empty($certification['face_template']);
        $requiresSignature = !$requiresFace && !empty($certification['signature_template']);

        $signatureScore = null;
        $faceScore = null;

        $metadata = [
            'patient_id' => $certification['patient_id'],
            'document_number' => $certification['document_number'],
        ];

        $faceData = trim((string) ($_POST['face_image'] ?? ''));
        if ($faceData !== '') {
            $faceTempPath = $this->persistDataUri($faceData, 'verifications');
            if ($faceTempPath) {
                $template = $this->faceRecognition->createTemplateFromFile(BASE_PATH . '/' . $faceTempPath);
                $faceScore = $this->faceRecognition->compareTemplates(
                    $certification['face_template'] ?? null,
                    $template
                );
                $metadata['face_capture'] = $faceTempPath;
            }
        }

        $signatureData = trim((string) ($_POST['signature_data'] ?? ''));
        if ($signatureData !== '' && !empty($certification['signature_template'])) {
            $signatureTempPath = $this->persistDataUri($signatureData, 'verifications');
            if ($signatureTempPath) {
                $template = $this->signatureAnalysis->createTemplateFromFile(BASE_PATH . '/' . $signatureTempPath);
                $signatureScore = $this->signatureAnalysis->compareTemplates(
                    $certification['signature_template'] ?? null,
                    $template
                );
                $metadata['signature_capture'] = $signatureTempPath;
            }
        }

        if ($requiresFace && $faceScore === null) {
            $this->json([
                'ok' => false,
                'message' => 'Debe capturar el rostro del paciente para realizar el check-in.',
            ], 422);
            return;
        }

        if ($requiresSignature && $signatureScore === null) {
            $this->json([
                'ok' => false,
                'message' => 'Debe capturar la firma del paciente para realizar el check-in.',
            ], 422);
            return;
        }

        if ($signatureScore === null && $faceScore === null) {
            $this->json([
                'ok' => false,
                'message' => 'Debe adjuntar una captura válida para verificar.',
            ], 422);
            return;
        }

        $result = $this->determineResult($signatureScore, $faceScore);

        $checkin = $this->verifications->logCheckin((int) $certification['id'], [
            'verified_signature_score' => $signatureScore,
            'verified_face_score' => $faceScore,
            'verification_result' => $result,
            'metadata' => $metadata,
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        $this->verifications->touchVerificationMetadata((int) $certification['id'], $result);

        $consentPath = null;
        if (in_array($result, ['approved', 'manual_review'], true)) {
            $patientSummary = $this->verifications->findPatientSummary($certification['patient_id']);
            $consentPath = $this->consentDocumentService->generate($certification, $checkin, $patientSummary);
        }

        $this->json([
            'ok' => true,
            'result' => $result,
            'signatureScore' => $signatureScore,
            'faceScore' => $faceScore,
            'consentDocument' => $consentPath,
        ]);
    }

    private function validateCertificationInput(array $input, ?array $patient, ?array $existing): array
    {
        $errors = [];

        $patientId = trim((string) ($input['patient_id'] ?? ''));
        $documentNumber = trim((string) ($input['document_number'] ?? ''));

        if ($patientId === '') {
            $errors['patient_id'] = 'Debe indicar el identificador interno del paciente.';
        } elseif ($patient === null) {
            $errors['patient_id'] = 'El paciente indicado no existe en patient_data. Verifique la historia clínica ingresada.';
        }

        if ($documentNumber === '') {
            $errors['document_number'] = 'Debe indicar la cédula o documento de identidad.';
        }

        $hasSignature = !empty(trim((string) ($input['signature_data'] ?? ''))) || !empty($existing['signature_path'] ?? '');
        $hasFace = !empty(trim((string) ($input['face_image'] ?? ''))) || !empty($existing['face_image_path'] ?? '');

        if (!$hasSignature && !$hasFace) {
            $errors['biometrics'] = 'Debe capturar al menos la firma o el rostro para iniciar la certificación.';
        }

        return $errors;
    }

    private function determineCertificationStatus(array $data): string
    {
        $hasSignature = !empty($data['signature_path']) && !empty($data['signature_template']);
        $hasFace = !empty($data['face_image_path']) && !empty($data['face_template']);
        $hasDocument = !empty($data['document_number']);

        return ($hasSignature && $hasFace && $hasDocument) ? 'verified' : 'pending';
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
}
