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

        $this->render(BASE_PATH . '/modules/IdentityVerification/views/index.php', [
            'pageTitle' => 'Certificación biométrica de pacientes',
            'certifications' => $certifications,
            'status' => $status,
            'errors' => $errors,
            'old' => [],
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
        $errors = $this->validateCertificationInput($input);

        if (!empty($errors)) {
            $certifications = $this->verifications->getRecent(25);
            $this->render(BASE_PATH . '/modules/IdentityVerification/views/index.php', [
                'pageTitle' => 'Certificación biométrica de pacientes',
                'certifications' => $certifications,
                'status' => null,
                'errors' => $errors,
                'old' => $input,
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

        $signatureTemplate = null;
        if ($signaturePath !== null) {
            $signatureTemplate = $this->signatureAnalysis->createTemplateFromFile(BASE_PATH . '/' . $signaturePath);
        }

        $faceTemplate = null;
        if ($facePath !== null) {
            $faceTemplate = $this->faceRecognition->createTemplateFromFile(BASE_PATH . '/' . $facePath);
        }

        $existing = $this->verifications->findByPatient($input['patient_id']);

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
            'status' => 'verified',
            'updated_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($existing) {
            $this->verifications->update((int) $existing['id'], $payload);
            $certificationId = (int) $existing['id'];
        } else {
            $payload['created_by'] = $_SESSION['user_id'] ?? null;
            $certificationId = $this->verifications->create($payload);
        }

        $this->verifications->touchVerificationMetadata($certificationId, 'approved');

        header('Location: /pacientes/certificaciones?status=stored');
        exit;
    }

    public function show(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'pacientes.verification.view', 'pacientes.verification.manage']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $patientId = isset($_GET['patient_id']) ? trim((string) $_GET['patient_id']) : '';

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
        $patientId = isset($_POST['patient_id']) ? trim((string) $_POST['patient_id']) : '';

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

        $signatureScore = null;
        $faceScore = null;

        $metadata = [
            'patient_id' => $certification['patient_id'],
            'document_number' => $certification['document_number'],
        ];

        $signatureData = $_POST['signature_data'] ?? '';
        if ($signatureData !== '') {
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

        $faceData = $_POST['face_image'] ?? '';
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

        if ($signatureScore === null && $faceScore === null) {
            $this->json([
                'ok' => false,
                'message' => 'Debe adjuntar una firma o una captura facial para verificar.',
            ], 422);
            return;
        }

        $result = $this->determineResult($signatureScore, $faceScore);

        $this->verifications->logCheckin((int) $certification['id'], [
            'verified_signature_score' => $signatureScore,
            'verified_face_score' => $faceScore,
            'verification_result' => $result,
            'metadata' => $metadata,
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        $this->verifications->touchVerificationMetadata((int) $certification['id'], $result);

        $this->json([
            'ok' => true,
            'result' => $result,
            'signatureScore' => $signatureScore,
            'faceScore' => $faceScore,
        ]);
    }

    private function validateCertificationInput(array $input): array
    {
        $errors = [];

        $patientId = trim((string) ($input['patient_id'] ?? ''));
        $documentNumber = trim((string) ($input['document_number'] ?? ''));
        $signatureData = trim((string) ($input['signature_data'] ?? ''));
        $faceData = trim((string) ($input['face_image'] ?? ''));

        if ($patientId === '') {
            $errors['patient_id'] = 'Debe indicar el identificador interno del paciente.';
        }

        if ($documentNumber === '') {
            $errors['document_number'] = 'Debe indicar la cédula o documento de identidad.';
        }

        if ($signatureData === '') {
            $errors['signature_data'] = 'Es necesario capturar la firma manuscrita del paciente.';
        }

        if ($faceData === '') {
            $errors['face_image'] = 'Debe registrar una imagen facial del paciente.';
        }

        return $errors;
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
