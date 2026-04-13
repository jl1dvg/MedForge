<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappMessageTemplate;
use App\Models\WhatsappTemplateRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TemplateCatalogService
{
    private const GRAPH_BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     templates: array<int, array<string, mixed>>,
     *     available_categories: array<int, array{value:string,label:string}>,
     *     available_languages: array<int, array{code:string,name:string}>,
     *     integration: array<string, mixed>,
     *     source: string
     * }
     */
    public function getTemplateCatalog(array $filters = []): array
    {
        $integration = $this->getIntegrationState();
        $templates = $this->loadLocalTemplates($filters);
        $source = 'local-cache';

        if ($templates === [] && !$this->hasLocalTemplateCache() && $integration['ready']) {
            $templates = $this->fetchRemoteTemplates($filters);
            $source = 'meta-live';
        }

        return [
            'templates' => $templates,
            'available_categories' => $this->availableCategories(),
            'available_languages' => $this->availableLanguages(),
            'integration' => $integration,
            'source' => $source,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{synced:int, templates:array<int, array<string,mixed>>}
     */
    public function syncTemplates(array $filters = []): array
    {
        $integration = $this->getIntegrationState();
        if (!$integration['ready']) {
            throw new RuntimeException('La integración con Meta no está lista para sincronizar plantillas.');
        }

        if (!$this->hasTemplateTables()) {
            throw new RuntimeException('Las tablas locales de plantillas no están disponibles en Laravel.');
        }

        $templates = $this->fetchRemoteTemplates($filters);
        foreach ($templates as $template) {
            $this->persistRemoteTemplate($template);
        }

        return [
            'synced' => count($templates),
            'templates' => $templates,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveDraft(array $payload, ?int $templateId = null, ?int $userId = null): array
    {
        if (!$this->hasTemplateTables()) {
            throw new RuntimeException('Las tablas locales de plantillas no están disponibles en Laravel.');
        }

        $sanitized = $this->sanitizeDraftPayload($payload);

        /** @var array<string, mixed> $template */
        $template = DB::transaction(function () use ($sanitized, $templateId, $userId): array {
            $record = $templateId !== null
                ? WhatsappMessageTemplate::query()->findOrFail($templateId)
                : new WhatsappMessageTemplate();

            $isNew = !$record->exists;
            if ($isNew) {
                $record->template_code = $sanitized['name'];
                $record->created_by = $userId;
            } elseif (!$this->isEditableLocalDraft($record)) {
                throw new RuntimeException('Las plantillas sincronizadas desde Meta no se editan en sitio. Clónalas a un borrador local.');
            } elseif ($record->template_code !== $sanitized['name']) {
                throw new RuntimeException('No se permite cambiar el código base de una plantilla existente.');
            }

            $record->display_name = $sanitized['display_name'];
            $record->language = $sanitized['language'];
            $record->category = $sanitized['category'];
            $record->status = 'DRAFT';
            $record->wa_business_account = (string) $this->configService->get()['business_account_id'];
            $record->description = $this->normalizeTemplateDescription($sanitized['body_text']);
            $record->updated_by = $userId;
            $record->save();

            $nextVersion = (int) WhatsappTemplateRevision::query()
                ->where('template_id', $record->id)
                ->max('version') + 1;

            $revision = WhatsappTemplateRevision::query()->create([
                'template_id' => $record->id,
                'version' => max(1, $nextVersion),
                'status' => 'draft',
                'header_type' => $sanitized['header_type'],
                'header_text' => $sanitized['header_text'],
                'body_text' => $sanitized['body_text'],
                'footer_text' => $sanitized['footer_text'],
                'buttons' => $sanitized['buttons'],
                'variables' => $sanitized['variables'],
                'quality_rating' => 'unknown',
                'rejection_reason' => null,
                'submitted_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'created_by' => $userId,
            ]);

            $record->forceFill(['current_revision_id' => $revision->id])->save();
            $record->load('whatsapp_template_revision');

            return $this->serializeLocalTemplate($record);
        });

        return $template;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function cloneTemplate(array $payload, ?int $userId = null): array
    {
        if (!$this->hasTemplateTables()) {
            throw new RuntimeException('Las tablas locales de plantillas no están disponibles en Laravel.');
        }

        $template = $payload['template'] ?? null;
        if (!is_array($template)) {
            throw new RuntimeException('Falta la plantilla base para clonar.');
        }

        $baseName = trim((string) ($template['name'] ?? ''));
        if ($baseName === '') {
            throw new RuntimeException('La plantilla base no tiene un nombre válido para clonar.');
        }

        $components = $template['editable_components'] ?? $template['components'] ?? [];
        if (!is_array($components) || $components === []) {
            $preview = $template['preview'] ?? [];
            if (!is_array($preview) || trim((string) ($preview['body_text'] ?? '')) === '') {
                throw new RuntimeException('La plantilla base no tiene componentes reutilizables para clonar.');
            }

            $components = $this->buildComponentsFromPreview($preview);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = $this->nextCloneCode($baseName . '_draft');
        }

        return $this->saveDraft([
            'name' => $name,
            'display_name' => trim((string) ($template['display_name'] ?? $baseName)) . ' (Borrador)',
            'language' => (string) ($template['language'] ?? ''),
            'category' => (string) ($template['category'] ?? ''),
            'components' => $components,
        ], null, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function publishDraft(int $templateId, ?int $userId = null): array
    {
        $integration = $this->getIntegrationState();
        if (!$integration['ready']) {
            throw new RuntimeException('La integración con Meta no está lista para publicar.');
        }

        if (!$this->hasTemplateTables()) {
            throw new RuntimeException('Las tablas locales de plantillas no están disponibles en Laravel.');
        }

        /** @var WhatsappMessageTemplate|null $template */
        $template = WhatsappMessageTemplate::query()
            ->with(['whatsapp_template_revision', 'whatsapp_template_revisions' => fn ($query) => $query->orderByDesc('version')])
            ->find($templateId);

        if ($template === null || $template->whatsapp_template_revision === null) {
            throw new RuntimeException('No se encontró la plantilla local a publicar.');
        }

        $config = $this->configService->get();
        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', self::GRAPH_BASE_URL), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $endpoint = sprintf(
            '%s/%s/%s/message_templates',
            $baseUrl,
            trim($config['api_version'], '/'),
            rawurlencode($config['business_account_id'])
        );

        $revision = $template->whatsapp_template_revision;
        $components = $this->buildComponentsFromRevision($revision);

        $response = Http::timeout($timeout)
            ->withToken($config['access_token'])
            ->acceptJson()
            ->post($endpoint, [
                'name' => $template->template_code,
                'language' => $template->language,
                'category' => $template->category,
                'components' => $components,
            ]);

        $payload = $response->json();
        if (!$response->successful()) {
            throw new RuntimeException('Meta respondió con error al publicar la plantilla: ' . $response->status());
        }

        $template->forceFill([
            'status' => 'PENDING',
            'approval_requested_at' => now(),
            'updated_by' => $userId,
        ])->save();

        $revision->forceFill([
            'status' => 'pending',
            'submitted_at' => now(),
        ])->save();

        return [
            'template' => $this->serializeLocalTemplate($template->fresh([
                'whatsapp_template_revision',
                'whatsapp_template_revisions' => fn ($query) => $query->orderByDesc('version'),
            ])),
            'meta_response' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getIntegrationState(): array
    {
        $config = $this->configService->get();
        $errors = [];

        if (!$config['enabled']) {
            $errors[] = 'WhatsApp Cloud API no está habilitado.';
        }

        if (trim($config['business_account_id']) === '') {
            $errors[] = 'Falta el Business Account ID.';
        }

        if (trim($config['access_token']) === '') {
            $errors[] = 'Falta el access token.';
        }

        return [
            'ready' => $errors === [],
            'errors' => $errors,
            'brand' => $config['brand'],
            'business_account_id' => $config['business_account_id'],
            'has_local_tables' => $this->hasTemplateTables(),
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function availableCategories(): array
    {
        return [
            ['value' => 'AUTHENTICATION', 'label' => 'Autenticación'],
            ['value' => 'UTILITY', 'label' => 'Utilidad'],
            ['value' => 'MARKETING', 'label' => 'Marketing'],
        ];
    }

    /**
     * @return array<int, array{code:string,name:string}>
     */
    public function availableLanguages(): array
    {
        $configured = trim((string) $this->configService->get()['template_languages']);
        if ($configured !== '') {
            $lines = preg_split('/[\r\n,]+/', $configured) ?: [];
            $entries = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $parts = array_map('trim', explode('|', $line, 2));
                $entries[] = [
                    'code' => $parts[0],
                    'name' => $parts[1] ?? strtoupper(str_replace('_', ' ', $parts[0])),
                ];
            }

            if ($entries !== []) {
                return collect($entries)->sortBy('name')->values()->all();
            }
        }

        return [
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'es_EC', 'name' => 'Español (Ecuador)'],
            ['code' => 'es_MX', 'name' => 'Español (México)'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'en_US', 'name' => 'English (US)'],
            ['code' => 'pt_BR', 'name' => 'Português (Brasil)'],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function loadLocalTemplates(array $filters): array
    {
        if (!$this->hasTemplateTables()) {
            return [];
        }

        $query = WhatsappMessageTemplate::query()
            ->with([
                'whatsapp_template_revision',
                'whatsapp_template_revisions' => fn ($builder) => $builder->orderByDesc('version'),
            ])
            ->orderByDesc('updated_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('template_code', 'like', '%' . $search . '%')
                    ->orWhere('display_name', 'like', '%' . $search . '%')
                    ->orWhere('language', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        foreach (['status', 'category', 'language'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $query->where($key, $value);
            }
        }

        $limit = max(1, min(200, (int) ($filters['limit'] ?? 100)));

        return $query->limit($limit)->get()->map(
            fn (WhatsappMessageTemplate $template): array => $this->serializeLocalTemplate($template)
        )->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function fetchRemoteTemplates(array $filters): array
    {
        $config = $this->configService->get();
        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', self::GRAPH_BASE_URL), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 100)));
        $endpoint = sprintf(
            '%s/%s/%s/message_templates',
            $baseUrl,
            trim($config['api_version'], '/'),
            rawurlencode($config['business_account_id'])
        );

        $response = Http::timeout($timeout)
            ->withToken($config['access_token'])
            ->acceptJson()
            ->get($endpoint, [
                'limit' => $limit,
                'fields' => 'id,name,category,language,status,quality_score,components,last_updated_time,rejected_reason',
            ]);

        $payload = $response->json();
        if (!$response->successful()) {
            throw new RuntimeException('Meta respondió con error al listar plantillas: ' . $response->status());
        }

        $templates = [];
        foreach (($payload['data'] ?? []) as $template) {
            if (!is_array($template)) {
                continue;
            }

            $templates[] = $this->serializeRemoteTemplate($template);
        }

        return $this->applyFilters($templates, $filters);
    }

    /**
     * @param array<string, mixed> $template
     */
    private function persistRemoteTemplate(array $template): void
    {
        DB::transaction(function () use ($template): void {
            /** @var WhatsappMessageTemplate $record */
            $record = WhatsappMessageTemplate::query()->updateOrCreate(
                ['template_code' => (string) $template['name']],
                [
                    'display_name' => (string) ($template['display_name'] ?? $template['name']),
                    'language' => (string) ($template['language'] ?? ''),
                    'category' => (string) ($template['category'] ?? ''),
                    'status' => (string) ($template['status'] ?? 'UNKNOWN'),
                    'wa_business_account' => (string) $this->configService->get()['business_account_id'],
                    'description' => $this->normalizeTemplateDescription((string) ($template['preview']['body_text'] ?? '')),
                    'approval_requested_at' => null,
                    'approved_at' => ($template['status'] ?? null) === 'APPROVED' ? now() : null,
                    'rejected_at' => ($template['status'] ?? null) === 'REJECTED' ? now() : null,
                    'updated_by' => null,
                ]
            );

            $nextVersion = (int) WhatsappTemplateRevision::query()
                ->where('template_id', $record->id)
                ->max('version') + 1;

            $revision = WhatsappTemplateRevision::query()->create([
                'template_id' => $record->id,
                'version' => max(1, $nextVersion),
                'status' => strtolower((string) ($template['status'] ?? 'draft')),
                'header_type' => (string) ($template['preview']['header_type'] ?? 'none'),
                'header_text' => $template['preview']['header_text'] ?? null,
                'body_text' => (string) ($template['preview']['body_text'] ?? ''),
                'footer_text' => $template['preview']['footer_text'] ?? null,
                'buttons' => $template['preview']['buttons'] ?? [],
                'variables' => $template['preview']['variables'] ?? [],
                'quality_rating' => (string) ($template['quality_score'] ?? 'unknown'),
                'rejection_reason' => $template['rejected_reason'] ?? null,
                'submitted_at' => null,
                'approved_at' => ($template['status'] ?? null) === 'APPROVED' ? now() : null,
                'rejected_at' => ($template['status'] ?? null) === 'REJECTED' ? now() : null,
                'created_by' => null,
            ]);

            $record->forceFill(['current_revision_id' => $revision->id])->save();
        });
    }

    private function hasTemplateTables(): bool
    {
        return Schema::hasTable('whatsapp_message_templates')
            && Schema::hasTable('whatsapp_template_revisions');
    }

    private function hasLocalTemplateCache(): bool
    {
        if (!$this->hasTemplateTables()) {
            return false;
        }

        return WhatsappMessageTemplate::query()->exists();
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    private function serializeRemoteTemplate(array $template): array
    {
        $preview = $this->buildPreviewFromComponents($template['components'] ?? []);

        return [
            'id' => (string) ($template['id'] ?? ''),
            'name' => (string) ($template['name'] ?? ''),
            'display_name' => (string) ($template['name'] ?? ''),
            'category' => (string) ($template['category'] ?? ''),
            'language' => (string) ($template['language'] ?? ''),
            'status' => (string) ($template['status'] ?? ''),
            'quality_score' => is_array($template['quality_score'] ?? null)
                ? (string) (($template['quality_score']['score'] ?? 'unknown'))
                : (string) ($template['quality_score'] ?? 'unknown'),
            'rejected_reason' => $template['rejected_reason'] ?? null,
            'last_updated_time' => $template['last_updated_time'] ?? null,
            'components' => is_array($template['components'] ?? null) ? $template['components'] : [],
            'preview' => $preview,
            'source' => 'meta',
            'editorial_state' => 'remote',
            'editorial_label' => 'Remota aprobada',
            'is_editable' => false,
            'can_clone' => true,
            'can_publish' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLocalTemplate(WhatsappMessageTemplate $template): array
    {
        $revision = $template->whatsapp_template_revision;
        $buttons = $revision?->buttons;
        if (!is_array($buttons)) {
            $buttons = [];
        }

        $variables = $revision?->variables;
        if (!is_array($variables)) {
            $variables = [];
        }

        $editorialState = $this->resolveEditorialState($template, $revision);
        $revisions = $template->relationLoaded('whatsapp_template_revisions')
            ? $template->whatsapp_template_revisions
            : collect();

        return [
            'id' => (string) $template->id,
            'name' => (string) $template->template_code,
            'display_name' => (string) ($template->display_name ?: $template->template_code),
            'category' => (string) $template->category,
            'language' => (string) $template->language,
            'status' => (string) $template->status,
            'quality_score' => (string) ($revision?->quality_rating ?? 'unknown'),
            'rejected_reason' => $revision?->rejection_reason,
            'last_updated_time' => optional($template->updated_at)?->toAtomString(),
            'components' => [],
            'editable_components' => $revision !== null ? $this->buildComponentsFromRevision($revision) : [],
            'preview' => [
                'header_type' => (string) ($revision?->header_type ?? 'none'),
                'header_text' => $revision?->header_text,
                'body_text' => (string) ($revision?->body_text ?? ''),
                'footer_text' => $revision?->footer_text,
                'buttons' => $buttons,
                'variables' => $variables,
            ],
            'source' => 'local',
            'editorial_state' => $editorialState,
            'editorial_label' => $this->editorialLabel($editorialState),
            'is_editable' => $this->isEditableLocalDraft($template),
            'can_clone' => in_array($editorialState, ['synced_meta', 'published_local'], true),
            'can_publish' => in_array($editorialState, ['draft', 'published_local'], true),
            'current_revision_version' => (int) ($revision?->version ?? 0),
            'revision_history' => $revisions->map(function (WhatsappTemplateRevision $item): array {
                return [
                    'id' => (int) $item->id,
                    'version' => (int) $item->version,
                    'status' => (string) $item->status,
                    'header_type' => (string) $item->header_type,
                    'quality_rating' => (string) $item->quality_rating,
                    'submitted_at' => optional($item->submitted_at)?->toAtomString(),
                    'approved_at' => optional($item->approved_at)?->toAtomString(),
                    'rejected_at' => optional($item->rejected_at)?->toAtomString(),
                    'created_at' => optional($item->created_at)?->toAtomString(),
                    'body_excerpt' => $this->normalizeTemplateDescription((string) $item->body_text),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizeDraftPayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || !preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException('El nombre de la plantilla es obligatorio y debe usar solo minúsculas, números y guiones bajos.');
        }

        $language = trim((string) ($payload['language'] ?? ''));
        if ($language === '') {
            throw new RuntimeException('El idioma es obligatorio.');
        }

        $category = strtoupper(trim((string) ($payload['category'] ?? '')));
        if (!in_array($category, array_column($this->availableCategories(), 'value'), true)) {
            throw new RuntimeException('La categoría enviada no es válida.');
        }

        $components = $payload['components'] ?? [];
        if (!is_array($components)) {
            throw new RuntimeException('Los componentes deben enviarse como arreglo.');
        }

        $preview = $this->buildPreviewFromComponents($components);
        if (trim((string) $preview['body_text']) === '') {
            throw new RuntimeException('El cuerpo del mensaje es obligatorio.');
        }

        return [
            'name' => $name,
            'display_name' => trim((string) ($payload['display_name'] ?? $name)) ?: $name,
            'language' => $language,
            'category' => $category,
            'header_type' => strtolower((string) ($preview['header_type'] ?? 'none')),
            'header_text' => $preview['header_text'],
            'body_text' => (string) $preview['body_text'],
            'footer_text' => $preview['footer_text'],
            'buttons' => is_array($preview['buttons']) ? $preview['buttons'] : [],
            'variables' => is_array($preview['variables']) ? $preview['variables'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<int, array<string, mixed>>
     */
    private function buildComponentsFromPreview(array $preview): array
    {
        $components = [];
        $headerType = strtolower((string) ($preview['header_type'] ?? 'none'));
        $headerText = trim((string) ($preview['header_text'] ?? ''));
        $bodyText = (string) ($preview['body_text'] ?? '');
        $footerText = trim((string) ($preview['footer_text'] ?? ''));
        $buttons = $preview['buttons'] ?? [];

        if ($headerType !== 'none' && $headerText !== '') {
            if ($headerType === 'text') {
                $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $headerText];
            } else {
                $components[] = ['type' => 'HEADER', 'format' => strtoupper($headerType), 'example' => $headerText];
            }
        }

        if (trim($bodyText) !== '') {
            $components[] = ['type' => 'BODY', 'text' => $bodyText];
        }

        if ($footerText !== '') {
            $components[] = ['type' => 'FOOTER', 'text' => $footerText];
        }

        if (is_array($buttons) && $buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildComponentsFromRevision(WhatsappTemplateRevision $revision): array
    {
        $components = [];

        if ($revision->header_type !== 'none' && $revision->header_text !== null && $revision->header_text !== '') {
            $header = [
                'type' => 'HEADER',
                'format' => strtoupper($revision->header_type === 'text' ? 'TEXT' : $revision->header_type),
            ];

            if ($revision->header_type === 'text') {
                $header['text'] = $revision->header_text;
            } else {
                $header['example'] = $revision->header_text;
            }

            $components[] = $header;
        }

        $components[] = [
            'type' => 'BODY',
            'text' => $revision->body_text,
        ];

        if ($revision->footer_text !== null && $revision->footer_text !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $revision->footer_text,
            ];
        }

        $buttons = $revision->buttons;
        if (is_array($buttons) && $buttons !== []) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        return $components;
    }

    private function isEditableLocalDraft(WhatsappMessageTemplate $template): bool
    {
        return $template->created_by !== null || strtoupper((string) $template->status) === 'DRAFT';
    }

    private function resolveEditorialState(WhatsappMessageTemplate $template, ?WhatsappTemplateRevision $revision): string
    {
        $status = strtoupper((string) $template->status);
        $revisionStatus = strtolower((string) ($revision?->status ?? ''));

        if ($this->isEditableLocalDraft($template)) {
            return $status === 'PENDING' || $revisionStatus === 'pending'
                ? 'published_local'
                : 'draft';
        }

        return 'synced_meta';
    }

    private function editorialLabel(string $editorialState): string
    {
        return match ($editorialState) {
            'draft' => 'Borrador local',
            'published_local' => 'Borrador publicado',
            'synced_meta' => 'Sincronizada desde Meta',
            'remote' => 'Remota aprobada',
            default => 'Plantilla',
        };
    }

    private function nextCloneCode(string $baseCode): string
    {
        $candidate = $baseCode;
        $suffix = 2;

        while (WhatsappMessageTemplate::query()->where('template_code', $candidate)->exists()) {
            $candidate = sprintf('%s_%d', $baseCode, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param mixed $components
     * @return array<string, mixed>
     */
    private function buildPreviewFromComponents(mixed $components): array
    {
        $preview = [
            'header_type' => 'none',
            'header_text' => null,
            'body_text' => '',
            'footer_text' => null,
            'buttons' => [],
            'variables' => [],
        ];

        if (!is_array($components)) {
            return $preview;
        }

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));
            if ($type === 'HEADER') {
                $format = strtolower((string) ($component['format'] ?? 'text'));
                if (!in_array($format, ['text', 'image', 'video', 'document'], true)) {
                    $format = 'text';
                }
                $preview['header_type'] = $format;
                $preview['header_text'] = $component['text']
                    ?? $component['example']
                    ?? ($format !== 'text' ? strtoupper($format) : null);
                continue;
            }

            if ($type === 'BODY') {
                $bodyText = (string) ($component['text'] ?? '');
                $preview['body_text'] = $bodyText;
                $preview['variables'] = $this->extractVariables($bodyText);
                continue;
            }

            if ($type === 'FOOTER') {
                $preview['footer_text'] = $component['text'] ?? null;
                continue;
            }

            if ($type === 'BUTTONS') {
                $preview['buttons'] = collect($component['buttons'] ?? [])
                    ->filter(fn ($button): bool => is_array($button))
                    ->map(fn (array $button): array => [
                        'type' => (string) ($button['type'] ?? ''),
                        'text' => (string) ($button['text'] ?? ''),
                    ])
                    ->values()
                    ->all();
            }
        }

        return $preview;
    }

    /**
     * @return array<int, string>
     */
    private function extractVariables(string $text): array
    {
        preg_match_all('/\{\{\d+\}\}/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function normalizeTemplateDescription(string $text): string
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, 250);
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $templates, array $filters): array
    {
        $collection = collect($templates);

        foreach (['status', 'category', 'language'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $collection = $collection->filter(
                fn (array $template): bool => strtoupper((string) ($template[$key] ?? '')) === strtoupper($value)
            );
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $collection = $collection->filter(function (array $template) use ($needle): bool {
                foreach (['name', 'display_name', 'category', 'language', 'status'] as $field) {
                    if (str_contains(mb_strtolower((string) ($template[$field] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $collection->values()->all();
    }
}
