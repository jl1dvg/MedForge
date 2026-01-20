<?php

namespace Modules\MailTemplates\Models;

use PDO;

class MailTemplateModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllByContext(string $context): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mail_templates WHERE context = :context ORDER BY template_key'
        );
        $stmt->execute([':context' => $context]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $context, string $templateKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mail_templates WHERE context = :context AND template_key = :template_key LIMIT 1'
        );
        $stmt->execute([
            ':context' => $context,
            ':template_key' => $templateKey,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findEnabledByKey(string $context, string $templateKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mail_templates WHERE context = :context AND template_key = :template_key AND enabled = 1 LIMIT 1'
        );
        $stmt->execute([
            ':context' => $context,
            ':template_key' => $templateKey,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(string $context, string $templateKey, array $data, int $userId): void
    {
        $existing = $this->findByKey($context, $templateKey);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE mail_templates
                 SET name = :name,
                     subject_template = :subject_template,
                     body_template_html = :body_template_html,
                     body_template_text = :body_template_text,
                     recipients_to = :recipients_to,
                     recipients_cc = :recipients_cc,
                     enabled = :enabled,
                     updated_by = :updated_by
                 WHERE context = :context AND template_key = :template_key'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mail_templates
                    (context, template_key, name, subject_template, body_template_html, body_template_text,
                     recipients_to, recipients_cc, enabled, updated_by)
                 VALUES
                    (:context, :template_key, :name, :subject_template, :body_template_html, :body_template_text,
                     :recipients_to, :recipients_cc, :enabled, :updated_by)'
            );
        }

        $stmt->execute([
            ':context' => $context,
            ':template_key' => $templateKey,
            ':name' => $data['name'] ?? $templateKey,
            ':subject_template' => $data['subject_template'] ?? null,
            ':body_template_html' => $data['body_template_html'] ?? null,
            ':body_template_text' => $data['body_template_text'] ?? null,
            ':recipients_to' => $data['recipients_to'] ?? null,
            ':recipients_cc' => $data['recipients_cc'] ?? null,
            ':enabled' => (int)($data['enabled'] ?? 0),
            ':updated_by' => $userId > 0 ? $userId : null,
        ]);
    }
}
