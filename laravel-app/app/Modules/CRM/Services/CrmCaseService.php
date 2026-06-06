<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use App\Modules\Codes\Services\CodesCatalogService;
use App\Modules\Codes\Services\CodesPackageService;
use App\Modules\Solicitudes\Services\SolicitudesCommunicationService;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use App\Modules\Solicitudes\Services\SolicitudesWriteParityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CrmCaseService
{
    public function __construct(
        private readonly CrmCaseActivityService $activityService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(string $sourceType, int $sourceId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        if (!Schema::hasTable('solicitud_procedimiento')) {
            throw new RuntimeException('Caso no encontrado');
        }

        $case = DB::table('solicitud_procedimiento')
            ->where('id', $sourceId)
            ->first();

        if ($case === null) {
            throw new RuntimeException('Caso no encontrado');
        }

        $caseRow = (array) $case;
        $detailRow = $this->solicitudDetail($sourceId);
        $contacts = $this->contacts($sourceId, $caseRow, $detailRow);
        $notes = $this->notes($normalizedSourceType, $sourceId);
        $tasks = $this->tasks($normalizedSourceType, $sourceId);

        return [
            'case' => [
                'case_id' => $normalizedSourceType . ':' . $sourceId,
                'source_type' => $normalizedSourceType,
                'source_id' => $sourceId,
                'solicitud_id' => $sourceId,
                'paciente_id' => $this->pacienteId($caseRow),
                'form_id' => $this->nullableInt($caseRow['form_id'] ?? null),
                'hc_number' => $caseRow['hc_number'] ?? null,
                'patient_name' => $this->patientName($caseRow),
                'stage' => $detailRow['pipeline_stage'] ?? $caseRow['estado'] ?? null,
                'site' => $this->firstFilled($caseRow, ['sede', 'sede_departamento', 'id_sede']),
            ],
            'crm' => [
                'responsible_id' => $this->nullableInt($detailRow['responsable_id'] ?? null),
                'responsible_name' => $this->responsibleName($detailRow),
                'source' => $this->valueOrNull($detailRow['fuente'] ?? null),
                'pipeline_stage' => $this->valueOrNull($detailRow['pipeline_stage'] ?? null),
                'insurance_company' => $this->firstFilled($detailRow, [
                    'insurance_company',
                    'empresa_seguro',
                    'aseguradora',
                    'seguro_empresa',
                ]) ?? '',
                'insurance_plan' => $this->firstFilled($detailRow, [
                    'insurance_plan',
                    'plan_seguro',
                    'seguro_plan',
                ]) ?? '',
                'insurance_code' => $this->firstFilled($detailRow, [
                    'insurance_code',
                    'codigo_seguro',
                    'insurance_codigo',
                    'seguro_codigo',
                ]) ?? '',
            ],
            'contacts' => $contacts,
            'notes' => $notes,
            'tasks' => $tasks,
            'activity' => $this->activityService->forCase($normalizedSourceType, $sourceId),
            'proposals' => $this->proposals($sourceId, $caseRow, $detailRow),
            'documents' => $this->documents($sourceId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function storeNote(string $sourceType, int $sourceId, string $body, ?int $userId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        $this->assertSolicitudCaseExists($sourceId);

        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException('La nota es obligatoria');
        }

        if (!Schema::hasTable('solicitud_crm_notas') || !Schema::hasColumn('solicitud_crm_notas', 'solicitud_id')) {
            throw new RuntimeException('Notas CRM no disponibles');
        }

        $payload = ['solicitud_id' => $sourceId];
        if (Schema::hasColumn('solicitud_crm_notas', 'nota')) {
            $payload['nota'] = $body;
        } elseif (Schema::hasColumn('solicitud_crm_notas', 'body')) {
            $payload['body'] = $body;
        } else {
            throw new RuntimeException('Notas CRM no disponibles');
        }

        if ($userId !== null) {
            if (Schema::hasColumn('solicitud_crm_notas', 'user_id')) {
                $payload['user_id'] = $userId;
            }
            if (Schema::hasColumn('solicitud_crm_notas', 'autor_id')) {
                $payload['autor_id'] = $userId;
            }
        }

        $now = now();
        if (Schema::hasColumn('solicitud_crm_notas', 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::hasColumn('solicitud_crm_notas', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        DB::table('solicitud_crm_notas')->insert($payload);

        return $this->show($normalizedSourceType, $sourceId);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteNote(string $sourceType, int $sourceId, int $noteId, ?int $userId, bool $isAdmin): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        if (!Schema::hasTable('solicitud_crm_notas') || !Schema::hasColumn('solicitud_crm_notas', 'solicitud_id')) {
            throw new RuntimeException('Notas CRM no disponibles');
        }

        $query = DB::table('solicitud_crm_notas')->where('id', $noteId)->where('solicitud_id', $sourceId);
        if (Schema::hasColumn('solicitud_crm_notas', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $note = $query->first();
        if ($note === null) {
            throw new RuntimeException('Nota no encontrada');
        }

        $noteRow = (array) $note;
        $ownerId = null;
        foreach (['user_id', 'autor_id'] as $ownerColumn) {
            if (Schema::hasColumn('solicitud_crm_notas', $ownerColumn) && isset($noteRow[$ownerColumn])) {
                $ownerId = (int) $noteRow[$ownerColumn];
                break;
            }
        }

        if (!$isAdmin && ($ownerId === null || $ownerId !== $userId)) {
            throw new RuntimeException('No autorizado para eliminar la nota');
        }

        $deleteQuery = DB::table('solicitud_crm_notas')->where('id', $noteId)->where('solicitud_id', $sourceId);
        if (Schema::hasColumn('solicitud_crm_notas', 'deleted_at')) {
            $payload = ['deleted_at' => now()];
            if (Schema::hasColumn('solicitud_crm_notas', 'updated_at')) {
                $payload['updated_at'] = now();
            }
            $deleteQuery->update($payload);
        } else {
            $deleteQuery->delete();
        }

        return $this->show($normalizedSourceType, $sourceId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogCodes(string $query, string $affiliation, int $limit): array
    {
        if ($query === '') {
            return [];
        }

        return (new CodesCatalogService())->quickSearch($query, $limit, $affiliation);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogPackages(string $query, string $affiliation, int $limit): array
    {
        if ($query === '') {
            return [];
        }

        $packages = new CodesPackageService(DB::connection()->getPdo());

        return $packages->list([
            'active' => 1,
            'search' => $query,
            'afiliacion' => $affiliation,
            'limit' => $limit,
            'offset' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function storeTask(string $sourceType, int $sourceId, array $payload, ?int $userId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        $this->assertSolicitudCaseExists($sourceId);

        if (!Schema::hasTable('crm_tasks')) {
            throw new RuntimeException('Tareas CRM no disponibles');
        }

        $title = trim((string) ($payload['title'] ?? $payload['titulo'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('El titulo de la tarea es obligatorio');
        }

        $insert = [];
        $this->putIfColumn($insert, 'crm_tasks', 'source_type', $normalizedSourceType);
        $this->putIfColumn($insert, 'crm_tasks', 'source_id', $sourceId);
        $this->putIfColumn($insert, 'crm_tasks', 'entity_type', $normalizedSourceType);
        $this->putIfColumn($insert, 'crm_tasks', 'entity_id', (string) $sourceId);
        $this->putIfColumn($insert, 'crm_tasks', 'form_id', $sourceId);
        $this->putIfColumn($insert, 'crm_tasks', 'source_module', $normalizedSourceType);
        $this->putIfColumn($insert, 'crm_tasks', 'source_ref_id', (string) $sourceId);
        $this->putFirstColumn($insert, 'crm_tasks', ['title', 'titulo'], $title);
        $this->putIfColumn($insert, 'crm_tasks', 'priority', $payload['priority'] ?? 'normal');
        $this->putIfColumn($insert, 'crm_tasks', 'status', $payload['status'] ?? 'pending');
        $this->putIfColumn($insert, 'crm_tasks', 'assigned_to', $payload['assigned_to'] ?? null);
        $this->putIfColumn($insert, 'crm_tasks', 'due_at', $payload['due_at'] ?? null);
        $this->putIfColumn($insert, 'crm_tasks', 'due_date', $payload['due_at'] ?? $payload['due_date'] ?? null);

        if ($userId !== null) {
            $this->putIfColumn($insert, 'crm_tasks', 'created_by', $userId);
            $this->putIfColumn($insert, 'crm_tasks', 'user_id', $userId);
        }

        $now = now();
        $this->putIfColumn($insert, 'crm_tasks', 'created_at', $now);
        $this->putIfColumn($insert, 'crm_tasks', 'updated_at', $now);

        DB::table('crm_tasks')->insert($insert);

        return $this->show($normalizedSourceType, $sourceId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateTask(string $sourceType, int $sourceId, int $taskId, array $payload): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        if (!Schema::hasTable('crm_tasks')) {
            throw new RuntimeException('Tareas CRM no disponibles');
        }

        $query = DB::table('crm_tasks')->where('id', $taskId);
        $this->scopeTaskToCaseForWrite($query, $normalizedSourceType, $sourceId);

        if (!$query->exists()) {
            throw new RuntimeException('Tarea no encontrada');
        }

        $update = [];
        foreach (['title', 'titulo', 'priority', 'status', 'assigned_to', 'due_at'] as $column) {
            if (array_key_exists($column, $payload) && Schema::hasColumn('crm_tasks', $column)) {
                $update[$column] = $payload[$column];
            }
        }
        if (array_key_exists('due_at', $payload) && Schema::hasColumn('crm_tasks', 'due_date')) {
            $update['due_date'] = $payload['due_at'];
        }
        if (array_key_exists('due_date', $payload) && Schema::hasColumn('crm_tasks', 'due_date')) {
            $update['due_date'] = $payload['due_date'];
        }
        if (Schema::hasColumn('crm_tasks', 'updated_at')) {
            $update['updated_at'] = now();
        }

        if ($update !== []) {
            $updateQuery = DB::table('crm_tasks')->where('id', $taskId);
            $this->scopeTaskToCaseForWrite($updateQuery, $normalizedSourceType, $sourceId);
            $updateQuery->update($update);
        }

        return $this->show($normalizedSourceType, $sourceId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendWhatsapp(string $sourceType, int $sourceId, array $payload, ?int $actorUserId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        $recipients = $this->cleanStringList($payload['recipients'] ?? null);
        $message = trim((string) ($payload['message'] ?? ''));
        if ($recipients === [] || $message === '') {
            throw new RuntimeException('Indica destinatarios y mensaje de WhatsApp');
        }

        $this->assertSolicitudCaseExists($sourceId);
        $service = $this->solicitudesCommunicationService();

        foreach ($recipients as $phone) {
            $service->sendWhatsapp($sourceId, [
                'phone' => $phone,
                'message' => $message,
            ], $actorUserId);
        }

        return $this->show($normalizedSourceType, $sourceId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendEmail(string $sourceType, int $sourceId, array $payload, ?int $actorUserId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        $to = $this->cleanStringList($payload['to'] ?? null);
        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        if ($to === [] || $subject === '' || $body === '') {
            throw new RuntimeException('Indica destinatarios, asunto y cuerpo del correo');
        }

        $this->assertSolicitudCaseExists($sourceId);
        $service = $this->solicitudesCommunicationService();

        foreach ($to as $email) {
            $service->sendEmail($sourceId, [
                'to' => $email,
                'subject' => $subject,
                'body' => $body,
            ], $actorUserId);
        }

        return $this->show($normalizedSourceType, $sourceId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function storeProposal(string $sourceType, int $sourceId, array $payload, ?int $actorUserId): array
    {
        $normalizedSourceType = $this->normalizeSourceType($sourceType);
        if ($normalizedSourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso CRM no soportado');
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('La propuesta debe incluir items');
        }

        $legacyItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('La propuesta debe incluir items');
            }

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                throw new RuntimeException('Cada item de la propuesta debe incluir una descripción');
            }

            $catalogType = strtolower(trim((string) ($item['catalog_type'] ?? '')));
            $catalogId = $item['catalog_id'] ?? null;
            $legacyItem = $item;
            $legacyItem['description'] = $description;
            $legacyItem['quantity'] = $item['quantity'] ?? 1;

            if ($catalogType === '' || $catalogId === null || $catalogId === '') {
                $legacyItem['catalog_type'] = 'manual';
                $legacyItem['catalog_id'] = null;
                $legacyItem['code_id'] = null;
                $legacyItem['package_id'] = null;
            } elseif ($catalogType === 'code' || $catalogType === 'codigo') {
                $legacyItem['catalog_type'] = 'code';
                $legacyItem['catalog_id'] = (int) $catalogId;
                $legacyItem['code_id'] = (int) $catalogId;
                $legacyItem['package_id'] = null;
            } elseif ($catalogType === 'package' || $catalogType === 'paquete') {
                $legacyItem['catalog_type'] = 'package';
                $legacyItem['catalog_id'] = (int) $catalogId;
                $legacyItem['package_id'] = (int) $catalogId;
                $legacyItem['code_id'] = null;
            } else {
                throw new RuntimeException('Tipo de item de catalogo no soportado');
            }

            $legacyItems[] = $legacyItem;
        }

        $this->assertSolicitudCaseExists($sourceId);
        $formId = $this->nullableInt(DB::table('solicitud_procedimiento')->where('id', $sourceId)->value('form_id'));
        $sendBy = $this->normalizeProposalSendBy($payload['send_by'] ?? null);
        $detailRow = $this->solicitudDetail($sourceId);
        $sendRecipient = $this->proposalSendRecipient($sendBy, $payload, $detailRow);

        $legacyPayload = $payload;
        $legacyPayload['items'] = $legacyItems;

        $result = $this->solicitudesWriteService()->crmCrearPropuesta($sourceId, $legacyPayload, $actorUserId);
        $this->linkCreatedProposalToCase($result, $normalizedSourceType, $sourceId, $formId);
        if ($sendBy !== 'none') {
            $this->sendCreatedProposal($sourceId, $result, $sendBy, $sendRecipient, $actorUserId);
        }

        return $this->show($normalizedSourceType, $sourceId);
    }

    private function normalizeSourceType(string $sourceType): string
    {
        return match (strtolower(trim($sourceType))) {
            'solicitud', 'solicitud_procedimiento', 'solicitudes' => 'solicitud',
            default => strtolower(trim($sourceType)),
        };
    }

    private function assertSolicitudCaseExists(int $sourceId): void
    {
        if (!Schema::hasTable('solicitud_procedimiento')) {
            throw new RuntimeException('Caso no encontrado');
        }

        if (!DB::table('solicitud_procedimiento')->where('id', $sourceId)->exists()) {
            throw new RuntimeException('Caso no encontrado');
        }
    }

    private function solicitudesReadService(): SolicitudesReadParityService
    {
        return new SolicitudesReadParityService();
    }

    private function solicitudesCommunicationService(): SolicitudesCommunicationService
    {
        return new SolicitudesCommunicationService($this->solicitudesReadService());
    }

    private function solicitudesWriteService(): SolicitudesWriteParityService
    {
        return new SolicitudesWriteParityService(DB::connection()->getPdo(), $this->solicitudesReadService());
    }

    /**
     * @return array<int, string>
     */
    private function cleanStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $values = [];
        foreach ($raw as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values($values);
    }

    private function normalizeProposalSendBy(mixed $sendBy): string
    {
        $sendBy = strtolower(trim((string) ($sendBy ?? '')));
        if ($sendBy === '' || $sendBy === 'none') {
            return 'none';
        }

        if ($sendBy === 'email' || $sendBy === 'whatsapp') {
            return $sendBy;
        }

        throw new RuntimeException('Canal de envio de propuesta no soportado');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $detailRow
     */
    private function proposalSendRecipient(string $sendBy, array $payload, array $detailRow): ?string
    {
        if ($sendBy === 'none') {
            return null;
        }

        if ($sendBy === 'email') {
            $email = trim((string) ($payload['email_to'] ?? $detailRow['contacto_email'] ?? $detailRow['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Indica un correo de destino válido para enviar la propuesta');
            }

            return $email;
        }

        $phone = trim((string) ($payload['phone'] ?? $detailRow['contacto_telefono'] ?? $detailRow['telefono'] ?? ''));
        if ($phone === '') {
            throw new RuntimeException('Indica un teléfono para enviar la propuesta por WhatsApp');
        }

        return $phone;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function sendCreatedProposal(int $sourceId, array $result, string $sendBy, ?string $recipient, ?int $actorUserId): void
    {
        $ultima = $result['ultima_propuesta'] ?? [];
        if (!is_array($ultima)) {
            $ultima = [];
        }

        $proposalId = (int) ($ultima['id'] ?? 0);
        if ($proposalId <= 0) {
            throw new RuntimeException('No se pudo identificar la propuesta creada para enviarla');
        }

        $proposalNumber = trim((string) ($ultima['proposal_number'] ?? ''));
        if ($proposalNumber === '') {
            $proposalNumber = (string) $proposalId;
        }

        $proposalService = new CrmProposalService();
        $proposal = $proposalService->find($proposalId);
        $publicUrl = $proposal['public_url'] ?? ('/v2/crm/proposals/' . $proposalId);
        $communicationService = $this->solicitudesCommunicationService();

        if ($sendBy === 'email') {
            $communicationService->sendEmail($sourceId, [
                'to' => $recipient,
                'subject' => 'Propuesta ' . $proposalNumber,
                'body' => 'Le enviamos la propuesta ' . $proposalNumber . ".\n\nPuede revisarla en línea: " . $publicUrl,
            ], $actorUserId);
        } else {
            $communicationService->sendWhatsapp($sourceId, [
                'phone' => $recipient,
                'mensaje' => 'Propuesta ' . $proposalNumber . ': ' . $publicUrl,
            ], $actorUserId);
        }

        $proposalService->markSent($proposalId, $sendBy, $actorUserId);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function linkCreatedProposalToCase(array $result, string $sourceType, int $sourceId, ?int $formId): void
    {
        $ultima = $result['ultima_propuesta'] ?? [];
        if (!is_array($ultima) || !Schema::hasTable('crm_proposals')) {
            return;
        }

        $proposalId = $this->nullableInt($ultima['id'] ?? null);
        if ($proposalId === null || $proposalId <= 0) {
            return;
        }

        $updates = [];
        $this->putIfColumn($updates, 'crm_proposals', 'source_type', $sourceType);
        $this->putIfColumn($updates, 'crm_proposals', 'source_id', $sourceId);
        if ($formId !== null) {
            $this->putIfColumn($updates, 'crm_proposals', 'form_id', $formId);
        }
        $this->putIfColumn($updates, 'crm_proposals', 'updated_at', now()->toDateTimeString());

        if ($updates !== []) {
            DB::table('crm_proposals')->where('id', $proposalId)->update($updates);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notes(string $sourceType, int $sourceId): array
    {
        if ($sourceType !== 'solicitud' || !$this->hasColumns('solicitud_crm_notas', ['solicitud_id'])) {
            return [];
        }

        $query = DB::table('solicitud_crm_notas')->where('solicitud_id', $sourceId);
        if (Schema::hasColumn('solicitud_crm_notas', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy($this->orderColumn('solicitud_crm_notas'), 'desc')
            ->get()
            ->map(function (object $row) use ($sourceId): array {
                $item = (array) $row;
                $authorId = $this->nullableInt($item['user_id'] ?? $item['autor_id'] ?? null);

                return [
                    'id' => $this->nullableInt($item['id'] ?? null),
                    'source_type' => 'solicitud',
                    'source_id' => $sourceId,
                    'body' => $item['nota'] ?? $item['body'] ?? null,
                    'author_id' => $authorId,
                    'author_name' => $this->userName($authorId),
                    'created_at' => $item['created_at'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tasks(string $sourceType, int $sourceId): array
    {
        if ($sourceType !== 'solicitud' || !Schema::hasTable('crm_tasks')) {
            return [];
        }

        $query = DB::table('crm_tasks');
        $this->scopeTaskToCaseForRead($query, $sourceId);

        return $query->orderBy($this->orderColumn('crm_tasks'), 'desc')
            ->get()
            ->map(function (object $row): array {
                $item = (array) $row;
                $assignedTo = $this->nullableInt($item['assigned_to'] ?? null);
                $createdBy = $this->nullableInt($item['created_by'] ?? $item['user_id'] ?? null);

                return [
                    'id' => $this->nullableInt($item['id'] ?? null),
                    'title' => $item['title'] ?? $item['titulo'] ?? null,
                    'description' => $item['description'] ?? $item['descripcion'] ?? null,
                    'status' => $item['status'] ?? null,
                    'priority' => $item['priority'] ?? null,
                    'assigned_to' => $assignedTo,
                    'assigned_name' => $this->userName($assignedTo),
                    'created_by' => $createdBy,
                    'created_by_name' => $this->userName($createdBy),
                    'due_at' => $item['due_at'] ?? $item['due_date'] ?? null,
                    'completed_at' => $item['completed_at'] ?? null,
                    'created_at' => $item['created_at'] ?? null,
                    'updated_at' => $item['updated_at'] ?? null,
                ];
            })
            ->all();
    }

    private function scopeTaskToCaseForRead(mixed $query, int $sourceId): void
    {
        $hasCondition = false;

        $query->where(function ($linked) use ($sourceId, &$hasCondition): void {
            if ($this->hasColumns('crm_tasks', ['source_type', 'source_id'])) {
                $hasCondition = true;
                $linked->orWhere(function ($source) use ($sourceId): void {
                    $source->whereIn('source_type', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('source_id', $sourceId);
                });
            }

            if ($this->hasColumns('crm_tasks', ['source_module', 'source_ref_id'])) {
                $hasCondition = true;
                $linked->orWhere(function ($source) use ($sourceId): void {
                    $source->whereIn('source_module', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('source_ref_id', (string) $sourceId);
                });
            }

            if ($this->hasColumns('crm_tasks', ['entity_type', 'entity_id'])) {
                $hasCondition = true;
                $linked->orWhere(function ($entity) use ($sourceId): void {
                    $entity->whereIn('entity_type', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('entity_id', (string) $sourceId);
                });
            }

            if (Schema::hasColumn('crm_tasks', 'form_id')) {
                $hasCondition = true;
                $linked->orWhere('form_id', $sourceId);
            }
        });

        if (!$hasCondition) {
            $query->whereRaw('1 = 0');
        }
    }

    private function scopeTaskToCaseForWrite(mixed $query, string $sourceType, int $sourceId): void
    {
        if ($this->hasColumns('crm_tasks', ['source_type', 'source_id'])) {
            $query->where('source_type', $sourceType)->where('source_id', $sourceId);

            return;
        }

        if ($this->hasColumns('crm_tasks', ['source_module', 'source_ref_id'])) {
            $query->where('source_module', $sourceType)->where('source_ref_id', (string) $sourceId);

            return;
        }

        if (Schema::hasColumn('crm_tasks', 'solicitud_id')) {
            $query->where('solicitud_id', $sourceId);

            return;
        }

        if (Schema::hasColumn('crm_tasks', 'form_id')) {
            $query->where('form_id', $sourceId);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitudDetail(int $sourceId): array
    {
        if (!Schema::hasTable('solicitud_crm_detalles') || !Schema::hasColumn('solicitud_crm_detalles', 'solicitud_id')) {
            return [];
        }

        $row = DB::table('solicitud_crm_detalles')
            ->where('solicitud_id', $sourceId)
            ->orderBy(Schema::hasColumn('solicitud_crm_detalles', 'updated_at') ? 'updated_at' : 'solicitud_id', 'desc')
            ->first();

        return $row === null ? [] : (array) $row;
    }

    /**
     * @param array<string, mixed> $caseRow
     */
    private function pacienteId(array $caseRow): ?int
    {
        foreach (['paciente_id', 'patient_id'] as $column) {
            if (Schema::hasColumn('solicitud_procedimiento', $column) && array_key_exists($column, $caseRow)) {
                return $this->nullableInt($caseRow[$column]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $caseRow
     */
    private function patientName(array $caseRow): ?string
    {
        $direct = $this->firstFilled($caseRow, ['patient_name', 'full_name', 'paciente_nombre']);
        if ($direct !== null) {
            return $direct;
        }

        $hcNumber = trim((string) ($caseRow['hc_number'] ?? ''));
        if ($hcNumber === '' || !Schema::hasTable('patient_data') || !Schema::hasColumn('patient_data', 'hc_number')) {
            return null;
        }

        $patient = DB::table('patient_data')->where('hc_number', $hcNumber)->first();
        if ($patient === null) {
            return null;
        }

        $patientRow = (array) $patient;
        $fullName = $this->firstFilled($patientRow, ['full_name', 'patient_full_name']);
        if ($fullName !== null) {
            return $fullName;
        }

        $parts = [];
        foreach (['fname', 'mname', 'lname', 'lname2'] as $column) {
            $value = trim((string) ($patientRow[$column] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $caseRow
     * @param array<string, mixed> $detailRow
     * @return array<string, mixed>
     */
    private function contacts(int $sourceId, array $caseRow, array $detailRow): array
    {
        $primaryPhone = $this->firstFilled($detailRow, ['contacto_telefono', 'telefono', 'primary_phone']);
        $primaryEmail = $this->firstFilled($detailRow, ['contacto_email', 'email', 'primary_email']);

        return [
            'primary_phone' => $primaryPhone ?? $this->firstFilled($caseRow, ['telefono', 'paciente_telefono']),
            'alternate_phones' => [],
            'primary_email' => $primaryEmail ?? $this->firstFilled($caseRow, ['email', 'paciente_email']),
            'alternate_emails' => [],
            'whatsapp_context' => $this->whatsappContext($sourceId, $caseRow, $detailRow),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsappContext(int $sourceId, array $caseRow, array $detailRow): array
    {
        try {
            $summary = $this->solicitudesReadService()->crmResumen($sourceId);
            $context = $summary['whatsapp_context'] ?? [];
            if (!is_array($context)) {
                return $this->directWhatsappContext($caseRow, $detailRow);
            }

            foreach (['conversation_url', 'search_url'] as $key) {
                if (isset($context[$key]) && is_string($context[$key])) {
                    $context[$key] = str_replace('/v2/whatsapp/chat', '/v3/whatsapp/chat', $context[$key]);
                }
            }

            return ($context['matched'] ?? false) === true ? $context : $this->directWhatsappContext($caseRow, $detailRow);
        } catch (\Throwable) {
            return $this->directWhatsappContext($caseRow, $detailRow);
        }
    }

    /**
     * @param array<string, mixed> $caseRow
     * @param array<string, mixed> $detailRow
     * @return array<string, mixed>
     */
    private function directWhatsappContext(array $caseRow, array $detailRow): array
    {
        $phone = $this->firstFilled($detailRow, ['contacto_telefono', 'telefono', 'primary_phone'])
            ?? $this->firstFilled($caseRow, ['telefono', 'paciente_telefono'])
            ?? '';
        $hcNumber = trim((string) ($caseRow['hc_number'] ?? ''));
        $normalized = $this->normalizeWhatsappNumber($phone);
        $search = $normalized !== '' ? $normalized : preg_replace('/\D+/', '', $phone);
        $searchUrl = $search !== '' ? '/v3/whatsapp/chat?search=' . urlencode($search) : null;
        $context = [
            'available' => Schema::hasTable('whatsapp_conversations'),
            'matched' => false,
            'search' => $search !== '' ? $search : null,
            'search_url' => $searchUrl,
            'conversation_id' => null,
            'conversation_url' => null,
            'wa_number' => null,
            'display_name' => null,
            'last_message_at' => null,
            'unread_count' => 0,
        ];

        if (!Schema::hasTable('whatsapp_conversations')) {
            return $context;
        }

        $query = DB::table('whatsapp_conversations');
        if (Schema::hasColumn('whatsapp_conversations', 'patient_hc_number') && $hcNumber !== '') {
            $query->where('patient_hc_number', $hcNumber);
        } elseif (Schema::hasColumn('whatsapp_conversations', 'wa_number') && $normalized !== '') {
            $query->where(function ($where) use ($normalized): void {
                $where->where('wa_number', $normalized)
                    ->orWhere('wa_number', '+' . $normalized);
            });
        } else {
            return $context;
        }

        $row = $query
            ->orderByDesc(Schema::hasColumn('whatsapp_conversations', 'last_message_at') ? 'last_message_at' : 'id')
            ->first();
        if ($row === null) {
            return $context;
        }

        $item = (array) $row;
        $conversationId = $this->nullableInt($item['id'] ?? null);
        if ($conversationId === null) {
            return $context;
        }

        return [
            'available' => true,
            'matched' => true,
            'search' => $context['search'],
            'search_url' => $searchUrl,
            'conversation_id' => $conversationId,
            'conversation_url' => '/v3/whatsapp/chat?conversation=' . $conversationId,
            'wa_number' => $this->valueOrNull($item['wa_number'] ?? null),
            'display_name' => $this->firstFilled($item, ['display_name', 'patient_full_name']),
            'last_message_at' => $item['last_message_at'] ?? null,
            'unread_count' => (int) ($item['unread_count'] ?? 0),
        ];
    }

    private function normalizeWhatsappNumber(string $value): string
    {
        $number = preg_replace('/\D+/', '', $value) ?? '';
        if ($number === '') {
            return '';
        }

        if (str_starts_with($number, '0')) {
            return '593' . substr($number, 1);
        }

        return $number;
    }

    /**
     * @param array<string, mixed> $detailRow
     */
    private function responsibleName(array $detailRow): ?string
    {
        $name = $this->firstFilled($detailRow, ['responsable_nombre', 'responsible_name']);
        if ($name !== null) {
            return $name;
        }

        $responsibleId = $this->nullableInt($detailRow['responsable_id'] ?? null);
        if ($responsibleId === null || !Schema::hasTable('users')) {
            return null;
        }

        $select = ['id'];
        foreach (['name', 'username', 'nombre'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $select[] = $column;
            }
        }

        $user = DB::table('users')->select($select)->where('id', $responsibleId)->first();
        if ($user === null) {
            return null;
        }

        return $this->firstFilled((array) $user, ['name', 'username', 'nombre']);
    }

    /**
     * @param array<string, mixed> $caseRow
     * @param array<string, mixed> $detailRow
     * @return array<int, array<string, mixed>>
     */
    private function proposals(int $sourceId, array $caseRow, array $detailRow): array
    {
        if (!Schema::hasTable('crm_proposals')) {
            return [];
        }

        $query = DB::table('crm_proposals');
        $hasCondition = false;

        $query->where(function ($where) use ($sourceId, $caseRow, $detailRow, &$hasCondition): void {
            if ($this->hasColumns('crm_proposals', ['source_type', 'source_id'])) {
                $hasCondition = true;
                $where->orWhere(function ($source) use ($sourceId): void {
                    $source->whereIn('source_type', ['solicitud', 'solicitud_procedimiento', 'solicitudes'])
                        ->where('source_id', $sourceId);
                });
            }

            if (Schema::hasColumn('crm_proposals', 'form_id') && isset($caseRow['form_id'])) {
                $hasCondition = true;
                $where->orWhere('form_id', $caseRow['form_id']);
            }

            if (Schema::hasColumn('crm_proposals', 'crm_opportunity_id') && isset($detailRow['crm_opportunity_id'])) {
                $hasCondition = true;
                $where->orWhere('crm_opportunity_id', $detailRow['crm_opportunity_id']);
            }
        });

        if (!$hasCondition) {
            return [];
        }

        $proposalService = new CrmProposalService();

        return $query->orderBy(Schema::hasColumn('crm_proposals', 'created_at') ? 'created_at' : 'id', 'desc')
            ->get()
            ->map(function (object $row) use ($proposalService): array {
                $proposal = (array) $row;
                $proposalId = (int) ($proposal['id'] ?? 0);
                if ($proposalId <= 0) {
                    return $proposal;
                }

                try {
                    return $proposalService->find($proposalId);
                } catch (RuntimeException) {
                    return $proposal;
                }
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(int $sourceId): array
    {
        if (!Schema::hasTable('solicitud_crm_adjuntos') || !Schema::hasColumn('solicitud_crm_adjuntos', 'solicitud_id')) {
            return [];
        }

        $query = DB::table('solicitud_crm_adjuntos')->where('solicitud_id', $sourceId);
        if (Schema::hasColumn('solicitud_crm_adjuntos', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy(Schema::hasColumn('solicitud_crm_adjuntos', 'created_at') ? 'created_at' : 'id', 'desc')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     */
    private function firstFilled(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function valueOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function userName(?int $userId): string
    {
        if ($userId === null || $userId <= 0 || !Schema::hasTable('users')) {
            return 'Sistema';
        }

        $select = ['id'];
        foreach (['name', 'username', 'nombre'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $select[] = $column;
            }
        }

        $user = DB::table('users')->select($select)->where('id', $userId)->first();
        if ($user === null) {
            return 'Usuario';
        }

        return $this->firstFilled((array) $user, ['name', 'username', 'nombre']) ?? 'Usuario';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function putIfColumn(array &$payload, string $table, string $column, mixed $value): void
    {
        if (Schema::hasColumn($table, $column)) {
            $payload[$column] = $value;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $columns
     */
    private function putFirstColumn(array &$payload, string $table, array $columns, mixed $value): void
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $payload[$column] = $value;

                return;
            }
        }

        throw new RuntimeException('Tareas CRM no disponibles');
    }

    /**
     * @param array<int, string> $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function orderColumn(string $table): string
    {
        foreach (['updated_at', 'created_at', 'id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return 'rowid';
    }
}
