<?php

namespace Modules\CRM\Services;

use DateTimeImmutable;
use Modules\CRM\Models\TaskModel;
use PDO;

class TaskService
{
    private TaskModel $tasks;

    public function __construct(PDO $pdo)
    {
        $this->tasks = new TaskModel($pdo);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function createEpisodeDefaults(array $context, int $userId): array
    {
        $episodeType = strtolower(trim((string) ($context['episode_type'] ?? '')));
        if ($episodeType === '') {
            return [];
        }

        $baseAt = $context['episode_at'] ?? $context['scheduled_at'] ?? $context['due_at'] ?? null;
        if (!$baseAt) {
            return [];
        }

        $companyId = (int) ($context['company_id'] ?? 0);
        if ($companyId <= 0) {
            return [];
        }

        $templates = $this->resolveTemplates($episodeType);
        if (!$templates) {
            return [];
        }

        $created = [];
        foreach ($templates as $template) {
            $dueAt = $this->shiftDate((string) $baseAt, $template['offset']);
            if ($dueAt === null) {
                continue;
            }

            $payload = array_merge($context, [
                'company_id' => $companyId,
                'title' => $template['title'],
                'description' => $template['description'] ?? null,
                'priority' => $template['priority'] ?? null,
                'category' => $template['category'] ?? null,
                'due_at' => $dueAt,
                'remind_at' => $template['remind'] ? $dueAt : null,
                'remind_channel' => $template['remind_channel'] ?? null,
            ]);

            $created[] = $this->tasks->create($payload, $userId);
        }

        return $created;
    }

    /**
     * @return array<int, array{title: string, offset: string, description?: string, priority?: string, category?: string, remind?: bool, remind_channel?: string}>
     */
    private function resolveTemplates(string $episodeType): array
    {
        if ($episodeType !== 'cirugia') {
            return [];
        }

        return [
            [
                'title' => 'Confirmar ayuno y acompañante',
                'description' => 'Validar ayuno e informar acompañante requerido.',
                'offset' => '-1 day',
                'priority' => 'alta',
                'category' => 'preop',
                'remind' => true,
                'remind_channel' => 'whatsapp',
            ],
            [
                'title' => 'Verificar lente/IOL y poder',
                'description' => 'Confirmar disponibilidad y poder del lente programado.',
                'offset' => '-2 day',
                'priority' => 'alta',
                'category' => 'preop',
                'remind' => false,
            ],
            [
                'title' => 'Autorización IESS/ISSFA',
                'description' => 'Gestionar autorizaciones administrativas del caso.',
                'offset' => '-3 day',
                'priority' => 'media',
                'category' => 'admin',
                'remind' => false,
            ],
            [
                'title' => 'Enviar instrucciones preop WhatsApp',
                'description' => 'Compartir instrucciones preoperatorias al paciente.',
                'offset' => '-1 day',
                'priority' => 'alta',
                'category' => 'preop',
                'remind' => true,
                'remind_channel' => 'whatsapp',
            ],
            [
                'title' => 'Control 24h postoperatorio',
                'description' => 'Agendar control 24h postoperatorio.',
                'offset' => '+1 day',
                'priority' => 'alta',
                'category' => 'postop',
                'remind' => true,
                'remind_channel' => 'whatsapp',
            ],
        ];
    }

    private function shiftDate(string $baseAt, string $modifier): ?string
    {
        $timestamp = strtotime($baseAt);
        if ($timestamp === false) {
            return null;
        }

        $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC'));
        $shifted = $date->modify($modifier);
        if ($shifted === false) {
            return null;
        }

        return $shifted->format('Y-m-d H:i:s');
    }
}
