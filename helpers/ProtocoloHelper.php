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

    public static function obtenerDiagnosticosAnteriores(PDO $db, string $hc_number, string $form_id, ?string $idProcedimiento): array
    {
        // 1. Buscar diagnósticos anteriores en consulta_data
        $sql = "SELECT diagnosticos FROM consulta_data WHERE hc_number = ? AND form_id < ? ORDER BY form_id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hc_number, $form_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $diagnosticosArray = [];
        if (!empty($data['diagnosticos'])) {
            $diagnosticosArray = json_decode($data['diagnosticos'], true);
        }

        // 2. Si no se encontró nada, usar el respaldo desde procedimientos
        if (empty($diagnosticosArray) && !empty($idProcedimiento)) {
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
        }

        // 3. Retornar hasta 3 diagnósticos
        return [
            $diagnosticosArray[0]['idDiagnostico'] ?? '',
            $diagnosticosArray[1]['idDiagnostico'] ?? '',
            $diagnosticosArray[2]['idDiagnostico'] ?? '',
        ];
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

    public static function procesarTextoEvolucion(?string $texto, int $ancho = 70): array
    {
        if (!$texto) return [];
        $wrapped = wordwrap($texto, $ancho, "\n", true);
        return explode("\n", $wrapped);
    }

    private static ?array $signosVitales = null;

    public static function obtenerSignosVitalesYEdad($edad, $diagnosticoPrevio, $procedimientoProyectado): array
    {
        if (self::$signosVitales === null) {
            self::$signosVitales = [
                'sistolica' => rand(110, 130),
                'diastolica' => rand(70, 83),
                'fc' => rand(75, 100),
                'edadPaciente' => $edad,
                'previousDiagnostic1' => $diagnosticoPrevio,
                'procedimientoProyectadoNow' => $procedimientoProyectado,
            ];
        }
        return self::$signosVitales;
    }

    public static function reemplazarVariablesTexto(string $texto, array $variables): string
    {
        $reemplazos = [
            '$sistolica' => $variables['sistolica'] ?? '',
            '$diastolica' => $variables['diastolica'] ?? '',
            '$fc' => $variables['fc'] ?? '',
            '$edadPaciente' => $variables['edadPaciente'] ?? '',
            '$previousDiagnostic1' => $variables['previousDiagnostic1'] ?? '',
            '$procedimientoProyectadoNow' => $variables['procedimientoProyectadoNow'] ?? '',
        ];
        return strtr($texto, $reemplazos);
    }

    public static function procesarEvolucionConVariables(string $texto, int $ancho, array $variables): array
    {
        $textoConVariables = self::reemplazarVariablesTexto($texto, $variables);
        $wrapped = wordwrap($textoConVariables, $ancho, "\n", true);
        return explode("\n", $wrapped);
    }

    public static function procesarMedicamentos(array $medicamentosArray, string $horaInicioModificada, string $mainSurgeon, string $anestesiologo, string $ayudante_anestesia)
    {
        $horaActual = new \DateTime($horaInicioModificada);
        $datosMedicamentos = [];

        foreach ($medicamentosArray as $medicamento) {
            $dosis = $medicamento['dosis'] ?? 'N/A';
            $frecuencia = $medicamento['frecuencia'] ?? 'N/A';
            $nombre_medicamento = $medicamento['medicamento'] ?? 'N/A';
            $via_administracion = $medicamento['via_administracion'] ?? 'N/A';
            $responsableTexto = '';

            switch ($medicamento['responsable']) {
                case 'Asistente':
                    $responsableTexto = 'ENF. ' . self::inicialesNombre($ayudante_anestesia);
                    break;
                case 'Anestesiólogo':
                    $responsableTexto = 'ANEST. ' . self::inicialesNombre($anestesiologo);
                    break;
                case 'Cirujano Principal':
                    $responsableTexto = 'OFTAL. ' . self::inicialesNombre($mainSurgeon);
                    break;
            }

            $datosMedicamentos[] = [
                'medicamento' => $nombre_medicamento,
                'dosis' => $dosis,
                'frecuencia' => $frecuencia,
                'via' => $via_administracion,
                'hora' => $horaActual->format('H:i'),
                'responsable' => $responsableTexto,
            ];

            // Aumentar la hora para el siguiente medicamento
            $horaActual->modify('+5 minutes');
        }

        return $datosMedicamentos;
    }

// Función auxiliar para obtener iniciales
    private static function inicialesNombre($nombreCompleto)
    {
        $partes = explode(' ', $nombreCompleto);
        $iniciales = '';
        foreach ($partes as $parte) {
            if (!empty($parte)) {
                $iniciales .= strtoupper(substr($parte, 0, 1)) . '. ';
            }
        }
        return trim($iniciales);
    }

    public static function procesarInsumos(string $insumosJson): array
    {
        $insumosArray = json_decode($insumosJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($insumosArray)) {
            return [];
        }

        $resultado = [];

        foreach ($insumosArray as $categoria => $insumos) {
            $categoria_nombre = match ($categoria) {
                'equipos' => 'EQUIPOS ESPECIALES',
                'anestesia' => 'INSUMOS Y MEDICAMENTOS DE ANESTESIA',
                'quirurgicos' => 'INSUMOS Y MEDICAMENTOS QUIRURGICOS',
                default => $categoria
            };

            foreach ($insumos as $insumo) {
                $resultado[] = [
                    'categoria' => $categoria_nombre,
                    'nombre' => $insumo['nombre'] ?? '',
                    'cantidad' => $insumo['cantidad'] ?? '',
                ];
            }
        }

        return $resultado;
    }
}
