<?php

namespace Modules\Reporting\Support;

use DateTimeImmutable;

/**
 * Normaliza y enriquece los datos provenientes de ExamenesController para que todas las
 * plantillas PDF dispongan del mismo contrato de información.
 */
class SolicitudDataFormatter
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function enrich(array $data, string $formId, string $hcNumber): array
    {
        $normalized = $data;

        $paciente = self::ensureArray($data['paciente'] ?? []);
        $solicitud = self::ensureArray($data['solicitud'] ?? []);
        $diagnostico = self::ensureList($data['diagnostico'] ?? []);
        $consulta = self::normalizeConsulta(self::ensureArray($data['consulta'] ?? []));
        $derivacion = self::ensureArray($data['derivacion'] ?? []);

        self::normalizeScalarField($paciente, 'afiliacion');
        self::normalizeScalarField($paciente, 'hc_number');
        self::normalizeScalarField($paciente, 'archive_number');
        self::normalizeScalarField($paciente, 'fname');
        self::normalizeScalarField($paciente, 'mname');
        self::normalizeScalarField($paciente, 'lname');
        self::normalizeScalarField($paciente, 'lname2');
        self::normalizeScalarField($paciente, 'sexo');
        self::normalizeScalarField($paciente, 'fecha_nacimiento');

        self::normalizeScalarField($solicitud, 'doctor');
        self::normalizeScalarField($solicitud, 'cedula');
        self::normalizeScalarField($solicitud, 'firma');
        self::normalizeScalarField($solicitud, 'procedimiento');
        self::normalizeScalarField($solicitud, 'ojo');

        $normalized['paciente'] = $paciente;
        $normalized['solicitud'] = $solicitud;
        $normalized['diagnostico'] = $diagnostico;
        $normalized['consulta'] = $consulta;
        $consultaExamenFisico = self::resolveExamenFisico($consulta);
        $normalized['consultaTexto'] = self::resolveConsultaTexto($consulta);
        $normalized['consultaExamenFisico'] = $consultaExamenFisico;
        $normalized['derivacion'] = $derivacion;

        $normalized['form_id'] = $formId;
        $normalized['hc_number'] = $hcNumber;
        $normalized['pacienteHistoriaClinica'] = $hcNumber;

        $normalized['edadPaciente'] = self::calculateAge(
            $paciente['fecha_nacimiento'] ?? null,
            $solicitud['created_at'] ?? null
        );

        $fullName = self::buildFullName($paciente);
        if ($fullName !== null) {
            $normalized['paciente']['full_name'] = $fullName;
            $normalized['pacienteNombreCompleto'] = $fullName;
        }

        $normalized['paciente']['edad_formateada'] = self::formatAge($normalized['edadPaciente']);
        $normalized['pacienteEdad'] = $normalized['edadPaciente'];
        $normalized['pacienteEdadTexto'] = $normalized['paciente']['edad_formateada'];
        $normalized['paciente']['sexo_normalizado'] = self::normalizeGender($paciente['sexo'] ?? null);
        $normalized['paciente']['documento'] = self::firstNonEmpty([
            $paciente['documento'] ?? null,
            $paciente['cedula'] ?? null,
            $paciente['ci'] ?? null,
            $paciente['identificacion'] ?? null,
        ]);

        $fechaNacimiento = $paciente['fecha_nacimiento'] ?? null;
        $normalized['paciente']['fecha_nacimiento_formateada'] = self::formatDate($fechaNacimiento);
        $normalized['pacienteFechaNacimiento'] = $fechaNacimiento;
        $normalized['pacienteFechaNacimientoFormateada'] = $normalized['paciente']['fecha_nacimiento_formateada'];

        $normalized['solicitud']['created_at_date'] = self::formatDate($solicitud['created_at'] ?? null);
        $normalized['solicitud']['created_at_time'] = self::formatTime($solicitud['created_at'] ?? null);
        $normalized['solicitud']['examenes_list'] = self::normalizeList($solicitud['examenes'] ?? null);
        $normalized['solicitud']['procedimientos_list'] = self::normalizeList($solicitud['procedimientos'] ?? null);
        $normalized['solicitud']['procedimiento_slug'] = self::slugify($solicitud['procedimiento'] ?? null);

        $normalized['diagnosticoLista'] = self::buildDiagnosticoList($diagnostico);
        $normalized['diagnosticoPrincipal'] = $normalized['diagnosticoLista'][0] ?? null;
        $normalized['diagnosticoListaTexto'] = $normalized['diagnosticoLista'] === []
            ? null
            : implode(PHP_EOL, $normalized['diagnosticoLista']);

        $insurerName = self::firstNonEmpty([
            $solicitud['aseguradora'] ?? null,
            $solicitud['aseguradora_nombre'] ?? null,
            $solicitud['aseguradoraName'] ?? null,
            $paciente['aseguradora'] ?? null,
            $paciente['afiliacion'] ?? null,
            $derivacion['aseguradora'] ?? null,
        ]);

        $insurerSlug = self::slugify($insurerName);
        $insurerCode = self::firstNonEmpty([
            $solicitud['codigo_aseguradora'] ?? null,
            $solicitud['numero_seguro'] ?? null,
            $solicitud['numero_afiliacion'] ?? null,
            $paciente['numero_seguro'] ?? null,
            $paciente['num_afiliacion'] ?? null,
        ]);

        $normalized['aseguradora'] = [
            'nombre' => $insurerName,
            'slug' => $insurerSlug,
            'codigo' => $insurerCode,
        ];
        $normalized['aseguradoraNombre'] = $insurerName;
        $normalized['aseguradoraSlug'] = $insurerSlug;
        $normalized['aseguradoraCodigo'] = $insurerCode;

        $normalized['timestamp_generado'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $consulta
     * @return array<string, mixed>
     */
    private static function normalizeConsulta(array $consulta): array
    {
        if ($consulta === []) {
            return [];
        }

        $formattedExamenFisicoNormalizado = self::formatExamenFisicoNormalizado(
            $consulta['examen_fisico_normalizado'] ?? null
        );

        if ($formattedExamenFisicoNormalizado !== null) {
            $consulta['examen_fisico_normalizado'] = $formattedExamenFisicoNormalizado;
        } else {
            unset($consulta['examen_fisico_normalizado']);
        }

        $scalarFields = [
            'motivo_consulta',
            'motivo',
            'enfermedad_actual',
            'examen_fisico',
            'plan',
            'diagnostico_plan',
            'estado_enfermedad',
            'signos_alarma',
            'recomen_no_farmaco',
            'antecedente_alergico',
            'vigencia_receta',
        ];

        foreach ($scalarFields as $field) {
            self::normalizeScalarField($consulta, $field);
        }

        $examenFisico = self::resolveExamenFisico($consulta);
        if ($examenFisico !== null) {
            $consulta['examen_fisico'] = $examenFisico;
        } else {
            unset($consulta['examen_fisico']);
        }

        if (array_key_exists('examen_fisico_normalizado', $consulta)) {
            self::normalizeScalarField($consulta, 'examen_fisico_normalizado');
        }

        if (($consulta['examen_fisico_normalizado'] ?? null) === null) {
            unset($consulta['examen_fisico_normalizado']);
        }

        if (array_key_exists('examenes', $consulta)) {
            $consulta['examenes_list'] = self::normalizeList($consulta['examenes']);
        }

        if (array_key_exists('diagnosticos', $consulta)) {
            $decoded = $consulta['diagnosticos'];
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded)) {
                $consulta['diagnosticos_list'] = array_values(array_filter(
                    array_map(static function ($item) {
                        if (!is_array($item)) {
                            return null;
                        }

                        $codigo = self::stringifyValue($item['dx_code'] ?? $item['idDiagnostico'] ?? null);
                        $descripcion = self::stringifyValue($item['descripcion'] ?? null);

                        if ($codigo !== null && $descripcion !== null) {
                            return sprintf('%s - %s', $codigo, $descripcion);
                        }

                        return $descripcion ?? $codigo;
                    },
                    $decoded
                ), static fn($value) => $value !== null));
            }
        }

        return $consulta;
    }

    private static function resolveConsultaTexto(array $consulta): ?string
    {
        $examenFisico = self::resolveExamenFisico($consulta);
        if ($examenFisico !== null) {
            return $examenFisico;
        }

        return self::buildConsultaTexto($consulta);
    }

    private static function formatExamenFisicoNormalizado(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return self::formatExamenFisicoNormalizado($decoded);
            }

            return $trimmed;
        }

        if (is_object($value)) {
            return self::formatExamenFisicoNormalizado((array) $value);
        }

        if (is_array($value)) {
            $sections = [];
            if (self::isList($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $label = self::stringifyValue($item['label'] ?? $item['titulo'] ?? $item['name'] ?? null);
                        $content = self::stringifyValue(
                            $item['value']
                                ?? $item['contenido']
                                ?? $item['texto']
                                ?? $item['descripcion']
                                ?? null
                        );

                        if ($content === null) {
                            continue;
                        }

                        $sections[] = self::formatExamenFisicoSection($label, $content);
                        continue;
                    }

                    $content = self::stringifyValue($item);
                    if ($content !== null) {
                        $sections[] = $content;
                    }
                }
            } else {
                foreach ($value as $label => $contentValue) {
                    $content = self::stringifyValue($contentValue);
                    if ($content === null) {
                        continue;
                    }

                    $labelString = is_string($label) ? trim($label) : null;
                    $sections[] = self::formatExamenFisicoSection(
                        $labelString !== '' ? $labelString : null,
                        $content
                    );
                }
            }

            $sections = array_values(array_filter($sections, static fn($item) => $item !== ''));

            if ($sections === []) {
                return null;
            }

            return implode(PHP_EOL . PHP_EOL, $sections);
        }

        return self::stringifyValue($value);
    }

    private static function formatExamenFisicoSection(?string $label, string $content): string
    {
        if ($label === null) {
            return $content;
        }

        $label = rtrim($label, ':');

        return sprintf('%s:%s%s', $label, PHP_EOL, $content);
    }

    private static function isList(array $value): bool
    {
        $expectedKey = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expectedKey) {
                return false;
            }

            ++$expectedKey;
        }

        return true;
    }

    private static function resolveExamenFisico(array $consulta): ?string
    {
        if (array_key_exists('examen_fisico_normalizado', $consulta)) {
            $formatted = self::formatExamenFisicoNormalizado($consulta['examen_fisico_normalizado']);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return self::stringifyValue($consulta['examen_fisico'] ?? null);
    }

    private static function buildConsultaTexto(array $consulta): ?string
    {
        if ($consulta === []) {
            return null;
        }

        $secciones = [
            'Motivo de consulta' => ['motivo_consulta', 'motivo'],
            'Enfermedad actual' => ['enfermedad_actual'],
            'Examen físico' => ['examen_fisico'],
            'Plan' => ['plan'],
            'Estado de la enfermedad' => ['estado_enfermedad'],
            'Signos de alarma' => ['signos_alarma'],
            'Recomendaciones' => ['recomen_no_farmaco'],
            'Antecedentes / alergias' => ['antecedente_alergico'],
            'Vigencia receta' => ['vigencia_receta'],
        ];

        $lineas = [];

        foreach ($secciones as $label => $keys) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $consulta)) {
                    continue;
                }

                $valor = self::stringifyValue($consulta[$key]);
                if ($valor === null) {
                    continue;
                }

                if ($key === 'vigencia_receta') {
                    $valor = self::formatDate($valor) ?? $valor;
                }

                $lineas[] = sprintf('%s: %s', $label, $valor);
                break;
            }
        }

        if ($lineas === []) {
            return null;
        }

        return implode(PHP_EOL, $lineas);
    }

    public static function slugify(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
        if ($transliterated === false) {
            $transliterated = $trimmed;
        }

        $lower = strtolower($transliterated);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : null;
    }

    /**
     * @return list<string>
     */
    public static function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $string = is_scalar($item) ? trim((string) $item) : null;
                if ($string !== null && $string !== '') {
                    $items[] = $string;
                }
            }

            return $items;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return self::normalizeList($decoded);
            }

            $parts = preg_split('/[,;|\n]+/', $trimmed) ?: [];

            return array_values(array_filter(array_map('trim', $parts), static fn ($item) => $item !== ''));
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $diagnostico
     * @return list<string>
     */
    private static function buildDiagnosticoList(array $diagnostico): array
    {
        $lista = [];

        foreach ($diagnostico as $item) {
            if (!is_array($item)) {
                continue;
            }

            $codigo = trim((string) ($item['dx_code'] ?? ''));
            $descripcion = trim((string) ($item['descripcion'] ?? ''));

            if ($codigo !== '' && $descripcion !== '') {
                $lista[] = sprintf('%s - %s', $codigo, $descripcion);
                continue;
            }

            if ($descripcion !== '') {
                $lista[] = $descripcion;
                continue;
            }

            if ($codigo !== '') {
                $lista[] = $codigo;
            }
        }

        return $lista;
    }

    private static function ensureArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    private static function ensureList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private static function calculateAge(?string $birthDate, ?string $referenceDate): ?int
    {
        $birth = self::createDate($birthDate);
        if ($birth === null) {
            return null;
        }

        $reference = self::createDate($referenceDate) ?? new DateTimeImmutable();

        return $birth->diff($reference)->y;
    }

    private static function formatAge(?int $age): ?string
    {
        if ($age === null) {
            return null;
        }

        return $age . ' años';
    }

    private static function buildFullName(array $paciente): ?string
    {
        $parts = array_filter([
            $paciente['fname'] ?? null,
            $paciente['mname'] ?? null,
            $paciente['lname'] ?? null,
            $paciente['lname2'] ?? null,
        ], static fn ($value) => is_string($value) && trim($value) !== '');

        if ($parts === []) {
            return null;
        }

        return implode(' ', array_map(static fn ($value) => trim((string) $value), $parts));
    }

    private static function normalizeGender(?string $gender): ?string
    {
        if ($gender === null) {
            return null;
        }

        $normalized = strtolower(trim($gender));

        return match ($normalized) {
            'm', 'masculino', 'hombre' => 'Masculino',
            'f', 'femenino', 'mujer' => 'Femenino',
            default => $normalized !== '' ? ucfirst($normalized) : null,
        };
    }

    private static function formatDate(?string $date): ?string
    {
        $dt = self::createDate($date);

        return $dt?->format('d/m/Y');
    }

    private static function formatTime(?string $date): ?string
    {
        $dt = self::createDate($date);

        return $dt?->format('H:i');
    }

    private static function createDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd-m-Y H:i:s',
            'd-m-Y',
            'd/m/Y H:i:s',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $trimmed);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }

    /**
     * @param array<int, ?string> $candidates
     */
    private static function firstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $value = self::stringifyValue($candidate);

            if ($value === null) {
                continue;
            }

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function normalizeScalarField(array &$values, string $key): void
    {
        if (!array_key_exists($key, $values)) {
            return;
        }

        $normalized = self::stringifyValue($values[$key]);

        if ($normalized === null) {
            unset($values[$key]);
            return;
        }

        $values[$key] = $normalized;
    }

    private static function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $item) {
                $string = self::stringifyValue($item);

                if ($string === null) {
                    continue;
                }

                $parts[] = $string;
            }

            if ($parts === []) {
                return null;
            }

            $parts = array_values(array_unique($parts));

            return implode(' ', $parts);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }
}
