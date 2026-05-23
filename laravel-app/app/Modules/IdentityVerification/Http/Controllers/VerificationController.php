<?php

declare(strict_types=1);

namespace App\Modules\IdentityVerification\Http\Controllers;

use App\Modules\IdentityVerification\Models\VerificationModel;
use App\Modules\IdentityVerification\Services\ConsentDocumentService;
use App\Modules\IdentityVerification\Services\FaceRecognitionService;
use App\Modules\IdentityVerification\Services\MissingEvidenceEscalationService;
use App\Modules\IdentityVerification\Services\PythonBiometricClient;
use App\Modules\IdentityVerification\Services\SignatureAnalysisService;
use App\Modules\IdentityVerification\Services\VerificationPolicyService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\SettingsOptionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerificationController
{
    private const STORAGE_PREFIX = 'storage/patient_verification/';

    private VerificationModel $verifications;
    private FaceRecognitionService $faceRecognition;
    private SignatureAnalysisService $signatureAnalysis;
    private PythonBiometricClient $pythonBiometricClient;
    private ConsentDocumentService $consentDocumentService;
    private VerificationPolicyService $policy;
    private MissingEvidenceEscalationService $escalationService;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $settingsResolver = new SettingsOptionResolver();

        $this->verifications = new VerificationModel($pdo);
        $this->pythonBiometricClient = new PythonBiometricClient();
        $this->faceRecognition = new FaceRecognitionService($this->pythonBiometricClient);
        $this->signatureAnalysis = new SignatureAnalysisService($this->pythonBiometricClient);
        $this->policy = new VerificationPolicyService($settingsResolver);
        $this->consentDocumentService = new ConsentDocumentService($this->policy);
        $this->escalationService = new MissingEvidenceEscalationService($this->policy);
    }

    public function index(Request $request): View
    {
        $certifications = $this->verifications->getRecent(25);
        $status = $request->query('status');
        $errors = [];
        $selectedPatient = null;
        $old = [];

        $lookupPatientId = $request->has('patient_id')
            ? $this->normalizePatientId((string) $request->query('patient_id'))
            : '';

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

        return view('identity_verification.index', [
            'pageTitle' => 'Certificación biométrica de pacientes',
            'currentUser' => LegacyCurrentUser::resolve($request),
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

    public function store(Request $request): View|RedirectResponse
    {
        $input = $this->collectInput($request);
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
            return view('identity_verification.index', [
                'pageTitle' => 'Certificación biométrica de pacientes',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'certifications' => $certifications,
                'status' => null,
                'errors' => $errors,
                'old' => $input,
                'selectedPatient' => $patient,
                'scripts' => [
                    'js/modules/patient-verification.js',
                ],
            ]);
        }

        $signaturePath = $existing['signature_path'] ?? null;
        $signatureTemplate = $existing['signature_template'] ?? null;
        if (!empty($input['signature_data'])) {
            $newSignaturePath = $this->persistDataUri((string) $input['signature_data'], 'signatures');
            if ($newSignaturePath !== null) {
                $signaturePath = $newSignaturePath;
                $signatureTemplate = $this->signatureAnalysis->createTemplateFromFile(base_path($signaturePath));
            }
        }

        $facePath = $existing['face_image_path'] ?? null;
        $faceTemplate = $existing['face_template'] ?? null;
        if (!empty($input['face_image'])) {
            $newFacePath = $this->persistDataUri((string) $input['face_image'], 'faces');
            if ($newFacePath !== null) {
                $facePath = $newFacePath;
                $faceTemplate = $this->faceRecognition->createTemplateFromFile(base_path($facePath));
            }
        }

        $documentSignaturePath = $existing['document_signature_path'] ?? null;
        if (!empty($input['document_signature_data'])) {
            $newDocumentSignaturePath = $this->persistDataUri((string) $input['document_signature_data'], 'document_signatures');
            if ($newDocumentSignaturePath !== null) {
                $documentSignaturePath = $newDocumentSignaturePath;
            }
        }

        $documentFrontPath = $this->persistUploadedFile($request, 'document_front', 'documents') ?? ($existing['document_front_path'] ?? null);
        $documentBackPath = $this->persistUploadedFile($request, 'document_back', 'documents') ?? ($existing['document_back_path'] ?? null);

        $userId = Auth::id();

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
                'existing_status' => $existing['status'] ?? null,
            ]),
            'updated_by' => $userId,
            'expired_at' => null,
        ];

        if ($existing) {
            if (($payload['status'] ?? null) === 'expired') {
                $payload['expired_at'] = $existing['expired_at'] ?? null;
            }
            $this->verifications->update((int) $existing['id'], $payload);
        } else {
            $payload['created_by'] = $userId;
            $this->verifications->create($payload);
        }

        return redirect('/pacientes/certificaciones?status=stored');
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->has('id') ? (int) $request->query('id') : 0;
        $patientId = $request->has('patient_id')
            ? $this->normalizePatientId((string) $request->query('patient_id'))
            : '';

        $certification = null;
        if ($id > 0) {
            $certification = $this->verifications->find($id);
        } elseif ($patientId !== '') {
            $certification = $this->verifications->findByPatient($patientId);
        }

        if (!$certification) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró una certificación para el paciente solicitado.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $certification,
        ]);
    }

    public function consentDocument(Request $request): View|JsonResponse
    {
        $id = $request->has('id') ? (int) $request->query('id') : 0;
        $patientId = $request->has('patient_id')
            ? $this->normalizePatientId((string) $request->query('patient_id'))
            : '';

        $certification = null;
        if ($id > 0) {
            $certification = $this->verifications->find($id);
        } elseif ($patientId !== '') {
            $certification = $this->verifications->findByPatient($patientId);
        }

        if (!$certification) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la certificación solicitada.',
            ], 404);
        }

        $checkinId = $request->has('checkin_id') ? (int) $request->query('checkin_id') : 0;
        $checkin = $checkinId > 0 ? $this->verifications->findCheckin($checkinId) : null;

        return view('identity_verification.consent_document', [
            'pageTitle' => 'Documento de consentimiento / atención',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'certification' => $certification,
            'checkin' => $checkin,
            'canRenderConsent' => $checkin !== null,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $certificationId = $request->has('certification_id') ? (int) $request->input('certification_id') : 0;
        $patientId = $request->has('patient_id')
            ? $this->normalizePatientId((string) $request->input('patient_id'))
            : '';

        $certification = null;
        if ($certificationId > 0) {
            $certification = $this->verifications->find($certificationId);
        }

        if (!$certification && $patientId !== '') {
            $certification = $this->verifications->findByPatient($patientId);
        }

        if (!$certification) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe una certificación registrada para el paciente proporcionado.',
            ], 404);
        }

        $certification = $this->refreshBiometricTemplates($certification);

        $metadata = [
            'patient_id' => $certification['patient_id'],
            'document_number' => $certification['document_number'],
        ];

        if (empty($certification['face_template']) && empty($certification['signature_template'])) {
            $this->escalateMissingEvidence($certification, 'missing_biometrics', [
                'metadata' => $metadata,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'La certificación no cuenta con datos biométricos suficientes. Complete el registro antes de continuar.',
            ], 409);
        }

        $requiresFace = !empty($certification['face_template']);
        $requiresSignature = !$requiresFace && !empty($certification['signature_template']);

        $signatureScore = null;
        $faceScore = null;
        $hasSignatureCapture = false;
        $hasFaceCapture = false;

        $faceData = trim((string) ($request->input('face_image', '')));
        if ($faceData !== '') {
            $faceTempPath = $this->persistDataUri($faceData, 'verifications');
            if ($faceTempPath) {
                $hasFaceCapture = true;
                $metadata['face_capture'] = $faceTempPath;
                $template = $this->faceRecognition->createTemplateFromFile(base_path($faceTempPath));
                if ($template) {
                    $faceScore = $this->faceRecognition->compareTemplates(
                        $certification['face_template'] ?? null,
                        $template
                    );
                    if ($faceScore === null) {
                        $metadata['face_capture_error'] = 'comparison_failed';
                    }
                } else {
                    $metadata['face_capture_error'] = 'template_generation_failed';
                }
            }
        }

        $signatureData = trim((string) ($request->input('signature_data', '')));
        if ($signatureData !== '' && !empty($certification['signature_template'])) {
            $signatureTempPath = $this->persistDataUri($signatureData, 'verifications');
            if ($signatureTempPath) {
                $hasSignatureCapture = true;
                $metadata['signature_capture'] = $signatureTempPath;
                $template = $this->signatureAnalysis->createTemplateFromFile(base_path($signatureTempPath));
                if ($template) {
                    $signatureScore = $this->signatureAnalysis->compareTemplates(
                        $certification['signature_template'] ?? null,
                        $template
                    );
                    if ($signatureScore === null) {
                        $metadata['signature_capture_error'] = 'comparison_failed';
                    }
                } else {
                    $metadata['signature_capture_error'] = 'template_generation_failed';
                }
            }
        }

        $userId = Auth::id();

        if ($requiresFace && !$hasFaceCapture) {
            $this->escalateMissingEvidence($certification, 'missing_face_capture', [
                'metadata' => $metadata,
                'user_id' => $userId,
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'Debe capturar el rostro del paciente para realizar el check-in.',
            ], 422);
        }

        if ($requiresSignature && !$hasSignatureCapture) {
            $this->escalateMissingEvidence($certification, 'missing_signature_capture', [
                'metadata' => $metadata,
                'user_id' => $userId,
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'Debe capturar la firma del paciente para realizar el check-in.',
            ], 422);
        }

        if (!$hasSignatureCapture && !$hasFaceCapture) {
            $this->escalateMissingEvidence($certification, 'missing_biometrics', [
                'metadata' => $metadata,
                'user_id' => $userId,
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'Debe adjuntar una captura válida para verificar.',
            ], 422);
        }

        $result = $this->determineResult($signatureScore, $faceScore);

        $checkin = $this->verifications->logCheckin((int) $certification['id'], [
            'verified_signature_score' => $signatureScore,
            'verified_face_score' => $faceScore,
            'verification_result' => $result,
            'metadata' => $metadata,
            'created_by' => $userId,
        ]);

        $this->verifications->touchVerificationMetadata((int) $certification['id'], $result);

        $consentPath = null;
        if (in_array($result, ['approved', 'manual_review'], true)) {
            $patientSummary = $this->verifications->findPatientSummary($certification['patient_id']);
            $consentPath = $this->consentDocumentService->generate($certification, $checkin, $patientSummary);
        }

        return response()->json([
            'ok' => true,
            'result' => $result,
            'signatureScore' => $signatureScore,
            'faceScore' => $faceScore,
            'consentDocument' => $consentPath,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $certificationId = $request->has('certification_id') ? (int) $request->input('certification_id') : 0;
        $patientId = $request->has('patient_id')
            ? $this->normalizePatientId((string) $request->input('patient_id'))
            : '';

        $certification = null;
        if ($certificationId > 0) {
            $certification = $this->verifications->find($certificationId);
        }

        if (!$certification && $patientId !== '') {
            $certification = $this->verifications->findByPatient($patientId);
        }

        if (!$certification) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la certificación solicitada.',
            ], 404);
        }

        $paths = [];
        foreach ([
            $certification['signature_path'] ?? null,
            $certification['document_signature_path'] ?? null,
            $certification['document_front_path'] ?? null,
            $certification['document_back_path'] ?? null,
            $certification['face_image_path'] ?? null,
        ] as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        $capturePaths = $this->verifications->getCheckinCapturePaths((int) $certification['id']);
        if (!empty($capturePaths)) {
            $paths = array_merge($paths, $capturePaths);
        }

        $paths = array_values(array_unique($paths));

        if (!$this->verifications->delete((int) $certification['id'])) {
            return response()->json([
                'ok' => false,
                'message' => 'No fue posible eliminar la certificación. Intente nuevamente.',
            ], 500);
        }

        foreach ($paths as $relativePath) {
            $this->deleteStoragePath($relativePath);
        }

        return response()->json([
            'ok' => true,
            'message' => 'La certificación biométrica se eliminó correctamente.',
            'patient_id' => $certification['patient_id'],
            'document_number' => $certification['document_number'],
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

        if ($hasSignature && $hasFace && $hasDocument) {
            return 'verified';
        }

        $existing = $data['existing_status'] ?? null;
        if ($existing === 'revoked') {
            return 'revoked';
        }

        if ($existing === 'expired') {
            return 'expired';
        }

        return 'pending';
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

        $directory = base_path('storage/patient_verification/' . $folder);
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

    private function persistUploadedFile(Request $request, string $field, string $folder): ?string
    {
        if (!$request->hasFile($field)) {
            return null;
        }

        /** @var UploadedFile $file */
        $file = $request->file($field);

        if (!$file->isValid()) {
            return null;
        }

        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        $mime = $file->getMimeType() ?? '';

        if (!isset($allowed[$mime])) {
            return null;
        }

        $directory = base_path('storage/patient_verification/' . $folder);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No fue posible crear el directorio de almacenamiento: ' . $directory);
        }

        $filename = $folder . '-' . uniqid('', true) . '.' . $allowed[$mime];
        $destination = $directory . '/' . $filename;

        if (!$file->move($directory, $filename)) {
            return null;
        }

        if (!is_file($destination)) {
            return null;
        }

        return 'storage/patient_verification/' . $folder . '/' . $filename;
    }

    private function collectInput(Request $request): array
    {
        $data = [];
        foreach ($request->post() as $key => $value) {
            $data[$key] = is_string($value) ? trim($value) : $value;
        }
        return $data;
    }

    private function refreshBiometricTemplates(array $certification): array
    {
        $updated = false;

        if ($this->isHashOnlyTemplate($certification['face_template'] ?? null) && !empty($certification['face_image_path'])) {
            $path = $this->absoluteStoragePath($certification['face_image_path']);
            if ($path && is_file($path)) {
                $template = $this->faceRecognition->createTemplateFromFile($path);
                if (is_array($template) && !$this->isHashOnlyTemplate($template)) {
                    $certification['face_template'] = $template;
                    $updated = true;
                }
            }
        }

        if ($this->isHashOnlyTemplate($certification['signature_template'] ?? null) && !empty($certification['signature_path'])) {
            $path = $this->absoluteStoragePath($certification['signature_path']);
            if ($path && is_file($path)) {
                $template = $this->signatureAnalysis->createTemplateFromFile($path);
                if (is_array($template) && !$this->isHashOnlyTemplate($template)) {
                    $certification['signature_template'] = $template;
                    $updated = true;
                }
            }
        }

        if ($updated) {
            $payload = [
                'document_number' => $certification['document_number'],
                'document_type' => $certification['document_type'] ?? 'cedula',
                'signature_path' => $certification['signature_path'] ?? null,
                'signature_template' => $certification['signature_template'] ?? null,
                'document_signature_path' => $certification['document_signature_path'] ?? null,
                'document_front_path' => $certification['document_front_path'] ?? null,
                'document_back_path' => $certification['document_back_path'] ?? null,
                'face_image_path' => $certification['face_image_path'] ?? null,
                'face_template' => $certification['face_template'] ?? null,
                'status' => $certification['status'] ?? 'pending',
                'updated_by' => Auth::id(),
            ];

            $this->verifications->update((int) $certification['id'], $payload);
        }

        return $certification;
    }

    private function isHashOnlyTemplate(mixed $template): bool
    {
        if (!is_array($template)) {
            return false;
        }

        if (($template['algorithm'] ?? null) === 'hash-only') {
            return true;
        }

        $vector = $template['vector'] ?? null;
        if (!is_array($vector)) {
            return empty($vector);
        }

        foreach ($vector as $value) {
            if (abs((float) $value) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    private function absoluteStoragePath(?string $relativePath): ?string
    {
        if (!is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $cleanPath = ltrim($relativePath, '/');

        return base_path($cleanPath);
    }

    private function deleteStoragePath(?string $relativePath): void
    {
        if (!is_string($relativePath) || $relativePath === '') {
            return;
        }

        $cleanPath = ltrim($relativePath, '/');
        if (!str_starts_with($cleanPath, self::STORAGE_PREFIX)) {
            return;
        }

        $fullPath = base_path($cleanPath);
        if (!file_exists($fullPath)) {
            return;
        }

        $baseDirectory = realpath(base_path(self::STORAGE_PREFIX));
        $realPath = realpath($fullPath);
        if ($realPath === false || $baseDirectory === false) {
            @unlink($fullPath);
            return;
        }

        if (str_starts_with($realPath, $baseDirectory)) {
            @unlink($realPath);
        }
    }

    private function determineResult(?float $signatureScore, ?float $faceScore): string
    {
        $hasSignature = $signatureScore !== null;
        $hasFace = $faceScore !== null;

        if ($hasSignature && $hasFace) {
            $signatureApprove = $this->policy->getSignatureApproveThreshold();
            $faceApprove = $this->policy->getFaceApproveThreshold();
            $signatureReject = $this->policy->getSignatureRejectThreshold();
            $faceReject = $this->policy->getFaceRejectThreshold();

            if ($signatureScore >= $signatureApprove && $faceScore >= $faceApprove) {
                return 'approved';
            }

            if ($signatureScore < $signatureReject || $faceScore < $faceReject) {
                return 'rejected';
            }

            return 'manual_review';
        }

        $score = $hasSignature ? $signatureScore : $faceScore;
        if ($score === null) {
            return 'manual_review';
        }

        if ($score >= $this->policy->getSingleApproveThreshold()) {
            return 'approved';
        }

        if ($score < $this->policy->getSingleRejectThreshold()) {
            return 'rejected';
        }

        return 'manual_review';
    }

    private function escalateMissingEvidence(array $certification, string $reason, array $context = []): void
    {
        try {
            $this->escalationService->escalate($certification, $reason, $context);
        } catch (\Throwable) {
            // Evitar fallos en el flujo principal si la escalación falla
        }
    }
}
