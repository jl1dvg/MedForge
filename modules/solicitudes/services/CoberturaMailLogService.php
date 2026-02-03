<?php

namespace Modules\Solicitudes\Services;

use DateTimeImmutable;
use PDO;
use PDOStatement;

class CoberturaMailLogService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_mail_log
                (solicitud_id, form_id, hc_number, afiliacion, template_key, to_emails, cc_emails, subject, body_text, body_html,
                 attachment_path, attachment_name, attachment_size, sent_by_user_id, status, error_message, sent_at)
             VALUES
                (:solicitud_id, :form_id, :hc_number, :afiliacion, :template_key, :to_emails, :cc_emails, :subject, :body_text, :body_html,
                 :attachment_path, :attachment_name, :attachment_size, :sent_by_user_id, :status, :error_message, :sent_at)'
        );

        $this->bindNullableInt($stmt, ':solicitud_id', $payload['solicitud_id'] ?? null);
        $this->bindNullableStr($stmt, ':form_id', $payload['form_id'] ?? null);
        $this->bindNullableStr($stmt, ':hc_number', $payload['hc_number'] ?? null);
        $this->bindNullableStr($stmt, ':afiliacion', $payload['afiliacion'] ?? null);
        $this->bindNullableStr($stmt, ':template_key', $payload['template_key'] ?? null);
        $stmt->bindValue(':to_emails', $payload['to_emails'] ?? '', PDO::PARAM_STR);
        $this->bindNullableStr($stmt, ':cc_emails', $payload['cc_emails'] ?? null);
        $stmt->bindValue(':subject', $payload['subject'] ?? '', PDO::PARAM_STR);
        $this->bindNullableStr($stmt, ':body_text', $payload['body_text'] ?? null);
        $this->bindNullableStr($stmt, ':body_html', $payload['body_html'] ?? null);
        $this->bindNullableStr($stmt, ':attachment_path', $payload['attachment_path'] ?? null);
        $this->bindNullableStr($stmt, ':attachment_name', $payload['attachment_name'] ?? null);
        $this->bindNullableInt($stmt, ':attachment_size', $payload['attachment_size'] ?? null);
        $this->bindNullableInt($stmt, ':sent_by_user_id', $payload['sent_by_user_id'] ?? null);
        $stmt->bindValue(':status', $payload['status'] ?? 'sent', PDO::PARAM_STR);
        $this->bindNullableStr($stmt, ':error_message', $payload['error_message'] ?? null);
        $this->bindNullableStr($stmt, ':sent_at', $payload['sent_at'] ?? null);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchLatestBySolicitud(int $solicitudId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sml.id, sml.solicitud_id, sml.form_id, sml.hc_number, sml.afiliacion, sml.template_key,
                    sml.to_emails, sml.cc_emails, sml.subject, sml.body_text, sml.body_html, sml.attachment_path,
                    sml.attachment_name, sml.attachment_size, sml.sent_by_user_id, sml.status, sml.error_message,
                    sml.sent_at, sml.created_at, u.nombre AS sent_by_name
             FROM solicitud_mail_log sml
             LEFT JOIN users u ON u.id = sml.sent_by_user_id
             WHERE sml.solicitud_id = :solicitud_id AND sml.status = "sent"
             ORDER BY sml.sent_at DESC, sml.id DESC
             LIMIT 1'
        );
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchBySolicitud(int $solicitudId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sml.id, sml.solicitud_id, sml.form_id, sml.hc_number, sml.afiliacion, sml.template_key,
                    sml.to_emails, sml.cc_emails, sml.subject, sml.body_text, sml.body_html, sml.attachment_path,
                    sml.attachment_name, sml.attachment_size, sml.sent_by_user_id, sml.status, sml.error_message,
                    sml.sent_at, sml.created_at, u.nombre AS sent_by_name
             FROM solicitud_mail_log sml
             LEFT JOIN users u ON u.id = sml.sent_by_user_id
             WHERE sml.solicitud_id = :solicitud_id
             ORDER BY COALESCE(sml.sent_at, sml.created_at) DESC, sml.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sml.id, sml.solicitud_id, sml.form_id, sml.hc_number, sml.afiliacion, sml.template_key,
                    sml.to_emails, sml.cc_emails, sml.subject, sml.body_text, sml.body_html, sml.attachment_path,
                    sml.attachment_name, sml.attachment_size, sml.sent_by_user_id, sml.status, sml.error_message,
                    sml.sent_at, sml.created_at, u.nombre AS sent_by_name
             FROM solicitud_mail_log sml
             LEFT JOIN users u ON u.id = sml.sent_by_user_id
             WHERE sml.id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        if (!empty($row['sent_at'])) {
            try {
                $row['sent_at'] = (new DateTimeImmutable((string) $row['sent_at']))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $row['sent_at'] = $row['sent_at'];
            }
        }

        return $row;
    }

    private function bindNullableInt(PDOStatement $stmt, string $key, $value): void
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
            return;
        }
        $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
    }

    private function bindNullableStr(PDOStatement $stmt, string $key, $value): void
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
            return;
        }
        $stmt->bindValue($key, (string) $value, PDO::PARAM_STR);
    }
}
