<?php

declare(strict_types=1);

namespace Models;

use PDO;
use Throwable;

class BillingSriDocumentModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findLatestByBillingId(int $billingId): ?array
    {
        try {
            $sql = 'SELECT * FROM billing_sri_documents WHERE billing_id = :billing_id ORDER BY id DESC LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':billing_id' => $billingId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM billing_sri_documents WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $data */
    public function create(int $billingId, array $data = []): int
    {
        $payload = [
            'billing_id' => $billingId,
            'estado' => (string)($data['estado'] ?? 'pendiente'),
            'clave_acceso' => $data['clave_acceso'] ?? null,
            'numero_autorizacion' => $data['numero_autorizacion'] ?? null,
            'xml_enviado' => $data['xml_enviado'] ?? null,
            'respuesta' => $data['respuesta'] ?? null,
            'errores' => $data['errores'] ?? null,
            'intentos' => (int)($data['intentos'] ?? 0),
            'last_sent_at' => $data['last_sent_at'] ?? null,
        ];

        $sql = 'INSERT INTO billing_sri_documents
                (billing_id, estado, clave_acceso, numero_autorizacion, xml_enviado, respuesta, errores, intentos, last_sent_at, created_at, updated_at)
                VALUES
                (:billing_id, :estado, :clave_acceso, :numero_autorizacion, :xml_enviado, :respuesta, :errores, :intentos, :last_sent_at, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':billing_id' => $payload['billing_id'],
            ':estado' => $payload['estado'],
            ':clave_acceso' => $payload['clave_acceso'],
            ':numero_autorizacion' => $payload['numero_autorizacion'],
            ':xml_enviado' => $payload['xml_enviado'],
            ':respuesta' => $payload['respuesta'],
            ':errores' => $payload['errores'],
            ':intentos' => $payload['intentos'],
            ':last_sent_at' => $payload['last_sent_at'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return true;
        }

        $allowed = [
            'estado',
            'clave_acceso',
            'numero_autorizacion',
            'xml_enviado',
            'respuesta',
            'errores',
            'intentos',
            'last_sent_at',
        ];

        $sets = [];
        $params = [':id' => $id];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $sets[] = "$key = :$key";
            $params[":$key"] = $data[$key];
        }

        if ($sets === []) {
            return true;
        }

        $sql = 'UPDATE billing_sri_documents SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function incrementarIntentos(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE billing_sri_documents SET intentos = COALESCE(intentos,0) + 1, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
