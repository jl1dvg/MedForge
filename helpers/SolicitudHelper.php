<?php

namespace Helpers;

class SolicitudHelper
{
    public static function formatearParaFrontend(array $solicitudes): array
    {
        $formateadas = [];

        foreach ($solicitudes as $row) {
            $formateadas[] = [
                'id' => $row['id'] ?? null,
                'form_id' => $row['form_id'] ?? '',
                'hc_number' => $row['hc_number'] ?? '',
                'nombre' => isset($row['full_name']) ? ucwords(strtolower($row['full_name'])) : '',
                'procedimiento' => ucwords(strtolower(explode(' - ', $row['procedimiento'])[2] ?? $row['procedimiento'])),
                'afiliacion' => $row['afiliacion'] ?? '',
                'fecha' => isset($row['fecha']) ? date('d-m-Y', strtotime($row['fecha'])) : '',
                'estado' => $row['estado'] ?? 'Recibido',
                'secuencia' => $row['secuencia'] ?? '',
                'fecha_creacion' => $row['created_at'] ?? '',
                'doctor' => $row['doctor'] ?? '',
                'ojo' => match (strtoupper($row['ojo'] ?? '')) {
                    'D' => 'Derecho',
                    'I' => 'Izquierdo',
                    default => '—'
                },
            ];
        }

        return $formateadas;
    }
}

