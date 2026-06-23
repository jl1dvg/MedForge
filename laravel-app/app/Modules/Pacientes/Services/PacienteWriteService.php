<?php

namespace App\Modules\Pacientes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class PacienteWriteService
{
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function crearPaciente(array $input, ?int $sessionUserId): array
    {
        $names = $this->normalizeNames($input);
        if ($names['fname'] === '' || $names['lname'] === '') {
            throw new InvalidArgumentException('Primer nombre y primer apellido son requeridos.');
        }

        $hcNumber = $this->normalizeHcNumber((string) ($input['hc_number'] ?? $input['cedula'] ?? ''));
        if ($hcNumber === '') {
            $hcNumber = $this->nextHcNumber();
        }

        if ($this->patientExists($hcNumber)) {
            throw new InvalidArgumentException('Ya existe un paciente con ese HC.');
        }

        $audit = $this->auditPayload($sessionUserId, 'api:/v2/pacientes/crear', 'created');
        $payload = array_merge([
            'hc_number' => $hcNumber,
            'fname' => $names['fname'],
            'mname' => $names['mname'],
            'lname' => $names['lname'],
            'lname2' => $names['lname2'],
            'afiliacion' => $this->cleanString($input['afiliacion'] ?? ''),
            'fecha_nacimiento' => $this->cleanDate($input['fecha_nac'] ?? $input['fecha_nacimiento'] ?? null),
            'sexo' => $this->cleanString($input['sexo'] ?? ''),
            'celular' => $this->cleanString($input['telefono'] ?? $input['celular'] ?? ''),
            'ciudad' => $this->cleanString($input['ciudad'] ?? ''),
            'email' => $this->cleanString($input['email'] ?? ''),
            'direccion' => $this->cleanString($input['direccion'] ?? ''),
            'created_at' => now(),
            'updated_at' => now(),
        ], $audit);

        $payload = $this->withOptionalColumns($payload, [
            'telefono_alt' => $this->cleanString($input['telefono_alt'] ?? ''),
            'medico_tratante_id' => $this->cleanNullableInt($input['medico'] ?? $input['medico_tratante_id'] ?? null),
            'sede_principal' => $this->normalizeSede($input['sede'] ?? $input['sede_principal'] ?? ''),
        ]);

        DB::table('patient_data')->insert($payload);

        return [
            'hc_number' => $hcNumber,
            'warnings' => [],
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    public function actualizarPaciente(string $hcNumber, array $input, ?int $sessionUserId): void
    {
        $hcNumber = $this->normalizeHcNumber($hcNumber);
        if ($hcNumber === '') {
            throw new InvalidArgumentException('hc_number es requerido.');
        }

        $patient = DB::table('patient_data')->where('hc_number', $hcNumber)->first();
        if (!$patient) {
            throw new InvalidArgumentException('Paciente no encontrado.');
        }

        $names = $this->normalizeNames($input);
        if ($names['fname'] === '' || $names['lname'] === '') {
            throw new InvalidArgumentException('Primer nombre y primer apellido son requeridos.');
        }

        $payload = array_merge([
            'fname' => $names['fname'],
            'mname' => $names['mname'],
            'lname' => $names['lname'],
            'lname2' => $names['lname2'],
            'afiliacion' => $this->cleanString($input['afiliacion'] ?? ''),
            'fecha_nacimiento' => $this->cleanDate($input['fecha_nacimiento'] ?? $input['fecha_nac'] ?? null),
            'sexo' => $this->cleanString($input['sexo'] ?? ''),
            'celular' => $this->cleanString($input['celular'] ?? $input['telefono'] ?? ''),
            'ciudad' => $this->cleanString($input['ciudad'] ?? ''),
            'email' => $this->cleanString($input['email'] ?? ''),
            'direccion' => $this->cleanString($input['direccion'] ?? ''),
            'updated_at' => now(),
        ], $this->auditPayload($sessionUserId, 'api:/v2/pacientes/editar', 'updated'));

        $payload = $this->withOptionalColumns($payload, [
            'telefono_alt' => $this->cleanString($input['telefono_alt'] ?? ''),
            'medico_tratante_id' => $this->cleanNullableInt($input['medico'] ?? $input['medico_tratante_id'] ?? null),
            'sede_principal' => $this->normalizeSede($input['sede'] ?? $input['sede_principal'] ?? ''),
        ]);

        $updated = DB::table('patient_data')->where('hc_number', $hcNumber)->update($payload);
        if ($updated < 0) {
            throw new RuntimeException('No se pudo actualizar el paciente.');
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{fname:string,mname:string,lname:string,lname2:string}
     */
    private function normalizeNames(array $input): array
    {
        $fname = $this->cleanString($input['fname'] ?? '');
        $mname = $this->cleanString($input['mname'] ?? '');
        $lname = $this->cleanString($input['lname'] ?? '');
        $lname2 = $this->cleanString($input['lname2'] ?? '');

        if ($fname === '' && isset($input['nombres'])) {
            $parts = $this->splitWords((string) $input['nombres']);
            $fname = $parts[0] ?? '';
            $mname = trim(implode(' ', array_slice($parts, 1)));
        }

        if ($lname === '' && isset($input['apellidos'])) {
            $parts = $this->splitWords((string) $input['apellidos']);
            $lname = $parts[0] ?? '';
            $lname2 = trim(implode(' ', array_slice($parts, 1)));
        }

        return compact('fname', 'mname', 'lname', 'lname2');
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $optional
     * @return array<string,mixed>
     */
    private function withOptionalColumns(array $payload, array $optional): array
    {
        foreach ($optional as $column => $value) {
            if (Schema::hasColumn('patient_data', $column)) {
                $payload[$column] = $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<string,string>
     */
    private function auditPayload(?int $sessionUserId, string $fallbackIdentifier, string $prefix): array
    {
        $typeColumn = "{$prefix}_by_type";
        $identifierColumn = "{$prefix}_by_identifier";
        $payload = [];

        if (Schema::hasColumn('patient_data', $typeColumn)) {
            $payload[$typeColumn] = $sessionUserId !== null ? 'user' : 'api';
        }

        if (Schema::hasColumn('patient_data', $identifierColumn)) {
            $payload[$identifierColumn] = $sessionUserId !== null ? ('user:' . $sessionUserId) : $fallbackIdentifier;
        }

        return $payload;
    }

    private function normalizeHcNumber(string $value): string
    {
        return trim($value);
    }

    private function cleanString(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function cleanDate(mixed $value): ?string
    {
        $value = $this->cleanString($value);
        return $value !== '' ? $value : null;
    }

    private function cleanNullableInt(mixed $value): ?int
    {
        $value = $this->cleanString($value);
        return ctype_digit($value) ? (int) $value : null;
    }

    private function normalizeSede(mixed $value): string
    {
        $value = strtolower($this->cleanString($value));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, 'ceibos') || $value === '16') {
            return 'CEIBOS';
        }

        if (str_contains($value, 'matriz') || $value === '1') {
            return 'MATRIZ';
        }

        return strtoupper($value);
    }

    /**
     * @return array<int,string>
     */
    private function splitWords(string $value): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($value)) ?: []));
    }

    private function patientExists(string $hcNumber): bool
    {
        return DB::table('patient_data')->where('hc_number', $hcNumber)->exists();
    }

    private function nextHcNumber(): string
    {
        $max = DB::table('patient_data')
            ->whereNotNull('hc_number')
            ->whereRaw("TRIM(hc_number) <> ''")
            ->where('hc_number', 'not like', '%-%')
            ->where('hc_number', 'not like', '% %')
            ->selectRaw('MAX(CAST(hc_number AS UNSIGNED)) AS max_hc')
            ->value('max_hc');

        return (string) (((int) $max) + 1);
    }
}
