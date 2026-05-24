<?php

namespace Modules\Examenes\Services;

use DateTimeImmutable;
use PDO;
use PDOStatement;
use Throwable;

class ExamenMailLogService
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
            'INSERT INTO examen_mail_log
                (examen_id, form_id, hc_number, to_emails, cc_emails, subject, body_text, body_html, channel, sent_by_user_id, status, error_message, sent_at)
             VALUES
                (:examen_id, :form_id, :hc_number, :to_emails, :cc_emails, :subject, :body_text, :body_html, :channel, :sent_by_user_id, :status, :error_message, :sent_at)'
        );

        $this->bindNullableInt($stmt, ':examen_id', $payload['examen_id'] ?? null);
        $this->bindNullableStr($stmt, ':form_id', $payload['form_id'] ?? null);
        $this->bindNullableStr($stmt, ':hc_number', $payload['hc_number'] ?? null);
        $stmt->bindValue(':to_emails', $payload['to_emails'] ?? '', PDO::PARAM_STR);
        $this->bindNullableStr($stmt, ':cc_emails', $payload['cc_emails'] ?? null);
        $stmt->bindValue(':subject', $payload['subject'] ?? '', PDO::PARAM_STR);
        $this->bindNullableStr($stmt, ':body_text', $payload['body_text'] ?? null);
        $this->bindNullableStr($stmt, ':body_html', $payload['body_html'] ?? null);
        $stmt->bindValue(':channel', $payload['channel'] ?? 'email', PDO::PARAM_STR);
        $this->bindNullableInt($stmt, ':sent_by_user_id', $payload['sent_by_user_id'] ?? null);
        $stmt->bindValue(':status', $payload['status'] ?? 'sent', PDO::PARAM_STR);
        $this->bindNullableStr($stmt, ':error_message', $payload['error_message'] ?? null);
        $this->bindNullableStr($stmt, ':sent_at', $payload['sent_at'] ?? null);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchByExamen(int $examenId, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT eml.id, eml.examen_id, eml.form_id, eml.hc_number, eml.to_emails, eml.cc_emails, eml.subject,
                        eml.body_text, eml.body_html, eml.channel, eml.sent_by_user_id, eml.status, eml.error_message,
                        eml.sent_at, eml.created_at, u.nombre AS sent_by_name
                 FROM examen_mail_log eml
                 LEFT JOIN users u ON u.id = eml.sent_by_user_id
                 WHERE eml.examen_id = :examen_id
                 ORDER BY COALESCE(eml.sent_at, eml.created_at) DESC, eml.id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchLatestByExamen(int $examenId): ?array
    {
        $rows = $this->fetchByExamen($examenId, 1);
        return $rows[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT eml.id, eml.examen_id, eml.form_id, eml.hc_number, eml.to_emails, eml.cc_emails, eml.subject,
                        eml.body_text, eml.body_html, eml.channel, eml.sent_by_user_id, eml.status, eml.error_message,
                        eml.sent_at, eml.created_at, u.nombre AS sent_by_name
                 FROM examen_mail_log eml
                 LEFT JOIN users u ON u.id = eml.sent_by_user_id
                 WHERE eml.id = :id
                 LIMIT 1'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $exception) {
            return null;
        }

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
        foreach (['sent_at', 'created_at'] as $key) {
            if (empty($row[$key])) {
                continue;
            }

            try {
                $row[$key] = (new DateTimeImmutable((string) $row[$key]))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                // Keep original value if parsing fails.
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
