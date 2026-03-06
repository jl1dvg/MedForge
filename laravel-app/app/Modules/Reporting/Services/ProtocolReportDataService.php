<?php

namespace App\Modules\Reporting\Services;

use DateTime;
use Illuminate\Support\Facades\DB;

class ProtocolReportDataService
{
    /**
     * @return array<string, mixed>
     */
    public function buildProtocolData(string $formId, string $hcNumber): array
    {
        $datos = $this->fetchProtocol($formId, $hcNumber);
        if ($datos === null) {
            return [];
        }

        $fechaInicio = isset($datos['fecha_inicio']) ? (string) $datos['fecha_inicio'] : null;
        [$fechaBase] = $this->splitDateTime($fechaInicio);
        [$anio, $mes, $dia] = $this->splitDate($fechaBase);

        $datos['anio'] = $anio;
        $datos['mes'] = $mes;
        $datos['dia'] = $dia;
        $datos['fechaAno'] = $anio;
        $datos['fechaMes'] = $mes;
        $datos['fechaDia'] = $dia;

        $datos['edad'] = $this->calculateAge(
            isset($datos['fecha_nacimiento']) ? (string) $datos['fecha_nacimiento'] : null,
            $fechaBase
        );
        $datos['edadPaciente'] = $datos['edad'];

        $datos['nombre_procedimiento_proyectado'] = $this->extractProjectedProcedureName(
            isset($datos['procedimiento_proyectado']) ? (string) $datos['procedimiento_proyectado'] : ''
        );
        $datos['codigos_concatenados'] = $this->extractProcedureCodes(
            isset($datos['procedimientos']) ? (string) $datos['procedimientos'] : ''
        );

        $datos['diagnosticos_previos'] = $this->formatDiagnosticosPrevios(
            $this->fetchDiagnosticosPreviosRaw($hcNumber, $formId)
        );

        $datos = array_merge($datos, $this->resolveUsers([
            'cirujano_data' => $datos['cirujano_1'] ?? null,
            'cirujano2_data' => $datos['cirujano_2'] ?? null,
            'ayudante_data' => $datos['primer_ayudante'] ?? null,
            'anestesiologo_data' => $datos['anestesiologo'] ?? null,
            'ayudante_anestesia_data' => $datos['ayudante_anestesia'] ?? null,
        ]));

        $procedureId = isset($datos['procedimiento_id']) ? trim((string) $datos['procedimiento_id']) : '';
        if ($procedureId === '') {
            $procedureId = $this->resolveProcedureIdFromRealizedProcedure(
                isset($datos['membrete']) ? (string) $datos['membrete'] : ''
            ) ?? '';
        }

        $datos['imagen_link'] = $procedureId !== ''
            ? $this->fetchProcedureImageLinkById($procedureId)
            : null;

        $diagnosticos = $this->decodeJsonArray($datos['diagnosticos'] ?? []);
        $datos['diagnostic1'] = isset($diagnosticos[0]['idDiagnostico']) ? (string) $diagnosticos[0]['idDiagnostico'] : '';
        $datos['diagnostic2'] = isset($diagnosticos[1]['idDiagnostico']) ? (string) $diagnosticos[1]['idDiagnostico'] : '';
        $datos['diagnostic3'] = isset($diagnosticos[2]['idDiagnostico']) ? (string) $diagnosticos[2]['idDiagnostico'] : '';

        $datos['realizedProcedure'] = isset($datos['membrete']) ? (string) $datos['membrete'] : '';
        $datos['codes_concatenados'] = isset($datos['codigos_concatenados']) ? (string) $datos['codigos_concatenados'] : '';
        $datos['mainSurgeon'] = isset($datos['cirujano_1']) ? (string) $datos['cirujano_1'] : '';
        $datos['assistantSurgeon1'] = isset($datos['cirujano_2']) ? (string) $datos['cirujano_2'] : '';
        $datos['ayudante'] = isset($datos['primer_ayudante']) ? (string) $datos['primer_ayudante'] : '';

        $evolucion = $this->fetchEvolucion005($procedureId);
        $signos = $this->buildSignosVitales(
            isset($datos['edadPaciente']) && is_numeric($datos['edadPaciente']) ? (int) $datos['edadPaciente'] : null,
            trim(implode(', ', is_array($datos['diagnosticos_previos'] ?? null) ? $datos['diagnosticos_previos'] : [])),
            $datos['realizedProcedure']
        );

        $datos['evolucion005'] = [
            'pre_evolucion' => !empty($evolucion['pre_evolucion'])
                ? $this->processEvolutionTextWithVariables((string) $evolucion['pre_evolucion'], 70, $signos)
                : [],
            'pre_indicacion' => !empty($evolucion['pre_indicacion'])
                ? $this->processEvolutionTextWithVariables((string) $evolucion['pre_indicacion'], 80, $signos)
                : [],
            'post_evolucion' => !empty($evolucion['post_evolucion'])
                ? $this->processEvolutionTextWithVariables((string) $evolucion['post_evolucion'], 70, $signos)
                : [],
            'post_indicacion' => !empty($evolucion['post_indicacion'])
                ? $this->processEvolutionTextWithVariables((string) $evolucion['post_indicacion'], 80, $signos)
                : [],
            'alta_evolucion' => !empty($evolucion['alta_evolucion'])
                ? $this->processEvolutionTextWithVariables((string) $evolucion['alta_evolucion'], 70, $signos)
                : [],
            'alta_indicacion' => !empty($evolucion['alta_indicacion'])
                ? $this->processEvolutionTextWithVariables((string) $evolucion['alta_indicacion'], 80, $signos)
                : [],
        ];

        $datos['preEvolucion'] = $datos['evolucion005']['pre_evolucion'];
        $datos['preIndicacion'] = $datos['evolucion005']['pre_indicacion'];
        $datos['postEvolucion'] = $datos['evolucion005']['post_evolucion'];
        $datos['postIndicacion'] = $datos['evolucion005']['post_indicacion'];
        $datos['altaEvolucion'] = $datos['evolucion005']['alta_evolucion'];
        $datos['altaIndicacion'] = $datos['evolucion005']['alta_indicacion'];

        [$horaInicioModificada, $horaFinModificada] = $this->adjustHours(
            isset($datos['hora_inicio']) ? (string) $datos['hora_inicio'] : null,
            isset($datos['hora_fin']) ? (string) $datos['hora_fin'] : null
        );
        $datos['horaInicioModificada'] = $horaInicioModificada;
        $datos['horaFinModificada'] = $horaFinModificada;

        $medicamentos = $this->fetchMedicamentos($procedureId, $formId, $hcNumber);
        $datos['medicamentos'] = $this->processMedicamentos(
            $medicamentos,
            (string) ($horaInicioModificada ?? ''),
            (string) ($datos['mainSurgeon'] ?? ''),
            (string) ($datos['anestesiologo'] ?? ''),
            (string) ($datos['ayudante_anestesia'] ?? '')
        );

        $rawInsumos = $datos['insumos'] ?? null;
        if (is_array($rawInsumos) && $rawInsumos !== []) {
            $datos['insumos'] = $rawInsumos;
        } elseif (is_string($rawInsumos)) {
            $trim = trim($rawInsumos);
            if ($trim !== '' && strtoupper($trim) !== 'NULL' && $trim !== '[]') {
                $datos['insumos'] = $this->processInsumos($rawInsumos);
            } else {
                $datos['insumos'] = [];
            }
        } else {
            $datos['insumos'] = [];
        }

        [$totalHoras, $totalHorasConDescuento] = $this->calculateDurations(
            isset($datos['hora_inicio']) ? (string) $datos['hora_inicio'] : null,
            isset($datos['hora_fin']) ? (string) $datos['hora_fin'] : null
        );
        $datos['totalHoras'] = $totalHoras;
        $datos['totalHorasConDescuento'] = $totalHorasConDescuento;

        return $datos;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProtocol(string $formId, string $hcNumber): ?array
    {
        $sql = "SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.afiliacion, p.sexo, p.ciudad,
                       pr.form_id, pr.fecha_inicio, pr.hora_inicio, pr.fecha_fin, pr.hora_fin, pr.cirujano_1, pr.instrumentista,
                       pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, pr.segundo_ayudante,
                       pr.ayudante_anestesia, pr.tercer_ayudante, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo,
                       pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos, pr.lateralidad,
                       pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado, pr.procedimiento_id, pr.insumos
                FROM patient_data p
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                WHERE pr.form_id = ? AND p.hc_number = ?
                LIMIT 1";

        $row = DB::selectOne($sql, [$formId, $hcNumber]);
        if (!is_object($row)) {
            return null;
        }

        return (array) $row;
    }

    private function extractProjectedProcedureName(?string $text): string
    {
        if (!$text) {
            return '';
        }

        $parts = explode(' - ', $text);

        return $parts[2] ?? '';
    }

    private function extractProcedureCodes(string $proceduresJson): string
    {
        $procedures = $this->decodeJsonArray($proceduresJson);
        $codes = [];

        foreach ($procedures as $procedure) {
            if (!is_array($procedure)) {
                continue;
            }

            $rawProcedure = isset($procedure['procInterno']) ? (string) $procedure['procInterno'] : '';
            if ($rawProcedure === '') {
                continue;
            }

            $parts = explode(' - ', $rawProcedure);
            if (isset($parts[1]) && trim($parts[1]) !== '') {
                $codes[] = trim($parts[1]);
            }
        }

        return implode('/', $codes);
    }

    /**
     * @return array<int, array{cie10: string, descripcion: string}>
     */
    private function fetchDiagnosticosPreviosRaw(string $hcNumber, string $formId): array
    {
        $row = DB::selectOne(
            'SELECT diagnosticos_previos FROM protocolo_data WHERE hc_number = ? AND form_id = ? LIMIT 1',
            [$hcNumber, $formId]
        );

        $diagnosticosPrevios = is_object($row) && isset($row->diagnosticos_previos)
            ? $this->decodeJsonArray((string) $row->diagnosticos_previos)
            : [];

        $result = [];
        for ($index = 0; $index < 3; $index++) {
            $item = $diagnosticosPrevios[$index] ?? [];
            if (!is_array($item)) {
                $item = [];
            }

            $result[] = [
                'cie10' => strtoupper(trim((string) ($item['cie10'] ?? ''))),
                'descripcion' => trim((string) ($item['descripcion'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array{cie10: string, descripcion: string}> $diagnosticos
     * @return array<int, string>
     */
    private function formatDiagnosticosPrevios(array $diagnosticos): array
    {
        $result = [];

        foreach ($diagnosticos as $diagnostico) {
            $cie = strtoupper(trim((string) ($diagnostico['cie10'] ?? '')));
            $descripcion = strtoupper(trim((string) ($diagnostico['descripcion'] ?? '')));
            $ciePad = str_pad($cie, 4, ' ', STR_PAD_RIGHT);
            $result[] = $ciePad . ' - ' . $descripcion;
        }

        while (count($result) < 3) {
            $result[] = '';
        }

        return array_slice($result, 0, 3);
    }

    /**
     * @param array<string, mixed> $users
     * @return array<string, mixed>
     */
    private function resolveUsers(array $users): array
    {
        $cache = [];
        $result = [];

        foreach ($users as $key => $name) {
            $cleanName = is_string($name) ? trim($name) : '';
            if ($cleanName === '') {
                $result[$key] = null;
                continue;
            }

            if (!array_key_exists($cleanName, $cache)) {
                $cache[$cleanName] = $this->findUserByName($cleanName);
            }

            $result[$key] = $cache[$cleanName];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserByName(string $fullName): ?array
    {
        $row = DB::selectOne(
            'SELECT * FROM users WHERE nombre COLLATE utf8mb4_unicode_ci LIKE ? LIMIT 1',
            ['%' . trim($fullName) . '%']
        );

        return is_object($row) ? (array) $row : null;
    }

    private function resolveProcedureIdFromRealizedProcedure(string $realizedProcedure): ?string
    {
        $normalized = trim($realizedProcedure);
        if ($normalized === '') {
            return null;
        }

        preg_match('/^(.*?)(\sen\sojo\s.*|\sao|\soi|\sod)?$/i', mb_strtolower($normalized), $matches);
        $name = trim((string) ($matches[1] ?? ''));
        if ($name === '') {
            return null;
        }

        $row = DB::selectOne(
            'SELECT id FROM procedimientos WHERE membrete COLLATE utf8mb4_unicode_ci LIKE ? LIMIT 1',
            ['%' . $name . '%']
        );

        if (!is_object($row) || !isset($row->id)) {
            return null;
        }

        return trim((string) $row->id) ?: null;
    }

    private function fetchProcedureImageLinkById(string $procedureId): ?string
    {
        $row = DB::selectOne(
            'SELECT imagen_link FROM procedimientos WHERE id COLLATE utf8mb4_unicode_ci LIKE ? LIMIT 1',
            ['%' . trim($procedureId) . '%']
        );

        if (!is_object($row)) {
            return null;
        }

        $imageLink = trim((string) ($row->imagen_link ?? ''));

        return $imageLink !== '' ? $imageLink : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchEvolucion005(string $procedureId): array
    {
        if (trim($procedureId) === '') {
            return [];
        }

        $row = DB::selectOne(
            'SELECT pre_evolucion, pre_indicacion, post_evolucion, post_indicacion, alta_evolucion, alta_indicacion FROM evolucion005 WHERE id = ? LIMIT 1',
            [$procedureId]
        );

        return is_object($row) ? (array) $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSignosVitales(?int $age, string $previousDiagnosis, string $projectedProcedure): array
    {
        return [
            'sistolica' => mt_rand(110, 130),
            'diastolica' => mt_rand(70, 83),
            'fc' => mt_rand(75, 100),
            'edadPaciente' => $age,
            'previousDiagnostic1' => $previousDiagnosis,
            'procedimientoProyectadoNow' => $projectedProcedure,
        ];
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<int, string>
     */
    private function processEvolutionTextWithVariables(string $text, int $width, array $variables): array
    {
        $withVariables = $this->replaceVariablesInText($text, $variables);
        $wrapped = wordwrap($withVariables, $width, "\n", true);

        return explode("\n", $wrapped);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function replaceVariablesInText(string $text, array $variables): string
    {
        $replacements = [
            '$sistolica' => (string) ($variables['sistolica'] ?? ''),
            '$diastolica' => (string) ($variables['diastolica'] ?? ''),
            '$fc' => (string) ($variables['fc'] ?? ''),
            '$edadPaciente' => (string) ($variables['edadPaciente'] ?? ''),
            '$previousDiagnostic1' => (string) ($variables['previousDiagnostic1'] ?? ''),
            '$procedimientoProyectadoNow' => (string) ($variables['procedimientoProyectadoNow'] ?? ''),
        ];

        return strtr($text, $replacements);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function adjustHours(?string $startHour, ?string $endHour): array
    {
        $startModified = null;
        $endModified = null;

        if (!empty($startHour)) {
            try {
                $start = new DateTime($startHour);
                $start->modify('-45 minutes');
                $startModified = $start->format('H:i');
            } catch (\Exception) {
                $startModified = null;
            }
        }

        if (!empty($endHour)) {
            try {
                $end = new DateTime($endHour);
                $end->modify('+30 minutes');
                $endModified = $end->format('H:i');
            } catch (\Exception) {
                $endModified = null;
            }
        }

        return [$startModified, $endModified];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMedicamentos(string $procedureId, string $formId, string $hcNumber): array
    {
        $protocolRow = DB::selectOne(
            'SELECT medicamentos FROM protocolo_data WHERE form_id = ? AND hc_number = ? LIMIT 1',
            [$formId, $hcNumber]
        );

        if (is_object($protocolRow)) {
            $protocolMeds = $this->decodeJsonArray($protocolRow->medicamentos ?? null);
            if ($protocolMeds !== []) {
                return $protocolMeds;
            }
        }

        if (trim($procedureId) === '') {
            return [];
        }

        $kardexRow = DB::selectOne(
            'SELECT medicamentos FROM kardex WHERE procedimiento_id = ? LIMIT 1',
            [$procedureId]
        );

        if (!is_object($kardexRow)) {
            return [];
        }

        return $this->decodeJsonArray($kardexRow->medicamentos ?? null);
    }

    /**
     * @param array<int, array<string, mixed>> $medications
     * @return array<int, array<string, mixed>>
     */
    private function processMedicamentos(
        array $medications,
        string $startModifiedHour,
        string $mainSurgeon,
        string $anesthesiologist,
        string $anesthesiaAssistant
    ): array {
        try {
            $currentHour = new DateTime(trim($startModifiedHour) !== '' ? $startModifiedHour : 'now');
        } catch (\Exception) {
            $currentHour = new DateTime();
        }

        $processed = [];
        foreach ($medications as $medication) {
            if (!is_array($medication)) {
                continue;
            }

            $responsible = '';
            switch ((string) ($medication['responsable'] ?? '')) {
                case 'Asistente':
                    $responsible = 'ENF. ' . $this->initialsName($anesthesiaAssistant);
                    break;
                case 'Anestesiólogo':
                    $responsible = 'ANEST. ' . $this->initialsName($anesthesiologist);
                    break;
                case 'Cirujano Principal':
                    $responsible = 'OFTAL. ' . $this->initialsName($mainSurgeon);
                    break;
            }

            $processed[] = [
                'medicamento' => isset($medication['medicamento']) ? (string) $medication['medicamento'] : 'N/A',
                'dosis' => isset($medication['dosis']) ? (string) $medication['dosis'] : 'N/A',
                'frecuencia' => isset($medication['frecuencia']) ? (string) $medication['frecuencia'] : 'N/A',
                'via' => isset($medication['via_administracion']) ? (string) $medication['via_administracion'] : 'N/A',
                'hora' => $currentHour->format('H:i'),
                'responsable' => $responsible,
            ];

            $currentHour->modify('+5 minutes');
        }

        return $processed;
    }

    private function initialsName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $initials = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials[] = strtoupper(substr($part, 0, 1)) . '.';
        }

        return implode(' ', $initials);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function processInsumos(string $insumosJson): array
    {
        $insumos = $this->decodeJsonArray($insumosJson);
        if ($insumos === []) {
            return [];
        }

        $result = [];
        foreach ($insumos as $category => $items) {
            if (!is_array($items)) {
                continue;
            }

            $categoryName = match ((string) $category) {
                'equipos' => 'EQUIPOS ESPECIALES',
                'anestesia' => 'INSUMOS Y MEDICAMENTOS DE ANESTESIA',
                'quirurgicos' => 'INSUMOS Y MEDICAMENTOS QUIRURGICOS',
                default => (string) $category,
            };

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $result[] = [
                    'categoria' => $categoryName,
                    'nombre' => isset($item['nombre']) ? (string) $item['nombre'] : '',
                    'cantidad' => isset($item['cantidad']) ? (string) $item['cantidad'] : '',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function calculateDurations(?string $startHour, ?string $endHour): array
    {
        if (empty($startHour) || empty($endHour)) {
            return ['No disponible', 'No disponible'];
        }

        try {
            $start = new DateTime($startHour);
            $end = new DateTime($endHour);

            $interval = $start->diff($end);
            $total = sprintf('%dh %dmin', $interval->h, $interval->i);

            $end->modify('-10 minutes');
            $discountedInterval = $start->diff($end);
            $discounted = sprintf('%dh %dmin', $discountedInterval->h, $discountedInterval->i);

            return [$total, $discounted];
        } catch (\Exception) {
            return ['No disponible', 'No disponible'];
        }
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitDateTime(?string $dateTime): array
    {
        if (empty($dateTime)) {
            return [null, null];
        }

        $parts = explode(' ', $dateTime, 2);

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function splitDate(?string $date): array
    {
        if (empty($date)) {
            return ['', '', ''];
        }

        $parts = explode('-', $date);

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
            $parts[2] ?? '',
        ];
    }

    private function calculateAge(?string $birthDate, ?string $referenceDate): ?int
    {
        if (empty($birthDate) || empty($referenceDate)) {
            return null;
        }

        try {
            $birth = new DateTime($birthDate);
            $reference = new DateTime($referenceDate);

            return $birth->diff($reference)->y;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || strtoupper($trimmed) === 'NULL') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
