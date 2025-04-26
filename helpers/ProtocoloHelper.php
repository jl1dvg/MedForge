<?php

namespace Helpers;

use PDO;

class ProtocoloHelper
{
    public static function buscarUsuarioPorNombre(PDO $db, string $nombreCompleto): ?array
    {
        $nombreCompletoNormalizado = strtolower(trim($nombreCompleto));
        $sql = "SELECT * FROM users WHERE LOWER(TRIM(nombre)) LIKE ?";
        $stmt = $db->prepare($sql);
        $param = "%" . $nombreCompletoNormalizado . "%";
        $stmt->execute([$param]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function obtenerIdProcedimiento(PDO $db, string $realizedProcedure): ?string
    {
        $normalized = strtolower(trim($realizedProcedure));
        preg_match('/^(.*?)(\sen\sojo\s.*|\sao|\soi|\sod)?$/i', $normalized, $matches);
        $nombre = $matches[1] ?? '';

        error_log('Nombre para buscar procedimiento: ' . $nombre);

        if (!empty($nombre)) {
            $sql = "SELECT id FROM procedimientos WHERE LOWER(TRIM(membrete)) LIKE ?";
            $stmt = $db->prepare($sql);
            $searchTerm = "%" . $nombre . "%";
            $stmt->execute([$searchTerm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                error_log('Resultado id_procedimiento: ' . $row['id']);
                return (string)$row['id'];
            } else {
                error_log('No se encontró procedimiento para: ' . $nombre);
            }
        }

        return null;
    }

public static function obtenerDiagnosticosAnteriores(PDO $db, string $hc_number, string $form_id, ?string $nombreProcedimiento): array    {
        $sql = "SELECT diagnosticos FROM consulta_data WHERE hc_number = ? AND form_id < ? ORDER BY form_id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hc_number, $form_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $diagnosticos = $data['diagnosticos'] ?? null;
        $diagnosticosArray = $diagnosticos ? json_decode($diagnosticos, true) : [];

        if (empty($diagnosticosArray) && $idProcedimiento) {
            $sql2 = "
                SELECT p.dx_pre, i.dx_code, i.long_desc
                FROM procedimientos p
                LEFT JOIN icd10_dx_order_code i ON p.dx_pre = i.dx_code
                WHERE p.id = ? LIMIT 1";
            $stmt2 = $db->prepare($sql2);
            $stmt2->execute([$idProcedimiento]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return ["{$row['dx_code']} - {$row['long_desc']}", '', ''];
            }
        } else {
            return [
                $diagnosticosArray[0]['idDiagnostico'] ?? '',
                $diagnosticosArray[1]['idDiagnostico'] ?? '',
                $diagnosticosArray[2]['idDiagnostico'] ?? '',
            ];
        }

        return ['', '', ''];
    }

    public static function mostrarImagenProcedimiento(PDO $db, string $nombreProcedimiento): ?string
    {
        $normalized = strtolower(trim($nombreProcedimiento));
        $sql = "SELECT imagen_link FROM procedimientos WHERE LOWER(TRIM(id)) LIKE ?";
        $stmt = $db->prepare($sql);
        $searchTerm = "%" . $normalized . "%";
        $stmt->execute([$searchTerm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['imagen_link'] ?? null;
    }
}