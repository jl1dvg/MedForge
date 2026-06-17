<?php

namespace App\Modules\Pacientes\Services;

use App\Models\PatientDatum;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PacienteWriteService
{
    /**
     * @param array<string, mixed> $data
     */
    public function update(string $hcNumber, array $data, ?int $sessionUserId): PatientDatum
    {
        return DB::transaction(function () use ($hcNumber, $data, $sessionUserId): PatientDatum {
            $patient = PatientDatum::query()
                ->where('hc_number', $hcNumber)
                ->first();

            if (!$patient instanceof PatientDatum) {
                throw (new ModelNotFoundException())->setModel(PatientDatum::class, [$hcNumber]);
            }

            $attributes = $this->patientAttributes($data);
            $patient->fill($attributes);
            $patient->updated_by_type = $sessionUserId !== null ? 'user' : 'api';
            $patient->updated_by_identifier = $sessionUserId !== null
                ? 'user:' . (string) $sessionUserId
                : 'api:/v2/pacientes/editar';
            $patient->save();
            $this->persistNullableFields($patient, $attributes);

            return $patient;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array{hc_number:string,warnings:array<int,string>}
     */
    public function store(array $data, ?int $sessionUserId): array
    {
        return DB::transaction(function () use ($data, $sessionUserId): array {
            $hcNumber = $this->nextHcNumber();
            $auditType = $sessionUserId !== null ? 'user' : 'api';
            $auditIdentifier = $sessionUserId !== null
                ? 'user:' . (string) $sessionUserId
                : 'api:/v2/pacientes/crear';

            $attributes = $this->patientAttributes($data);
            $patient = PatientDatum::query()->create(array_merge(
                ['hc_number' => $hcNumber],
                $attributes,
                [
                    'created_by_type' => $auditType,
                    'created_by_identifier' => $auditIdentifier,
                    'updated_by_type' => $auditType,
                    'updated_by_identifier' => $auditIdentifier,
                ],
            ));
            $this->persistNullableFields($patient, $attributes);

            return [
                'hc_number' => $hcNumber,
                'warnings' => [],
            ];
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function patientAttributes(array $data): array
    {
        [$fname, $mname] = $this->splitNameFields($data, 'fname', 'mname', 'nombres');
        [$lname, $lname2] = $this->splitNameFields($data, 'lname', 'lname2', 'apellidos');

        return [
            'cedula' => $this->nullableString($data['cedula'] ?? null),
            'fname' => $fname,
            'mname' => $mname,
            'lname' => $lname,
            'lname2' => $lname2,
            'afiliacion' => $this->nullableString($data['afiliacion'] ?? null),
            'fecha_nacimiento' => $this->nullableString($data['fecha_nacimiento'] ?? $data['fecha_nac'] ?? null),
            'sexo' => $this->nullableString($data['sexo'] ?? null),
            'celular' => $this->nullableString($data['celular'] ?? $data['telefono'] ?? null),
            'telefono_alt' => $this->nullableString($data['telefono_alt'] ?? null),
            'ciudad' => $this->nullableString($data['ciudad'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'direccion' => $this->nullableString($data['direccion'] ?? null),
            'medico_tratante_id' => $this->nullableInt($data['medico_tratante_id'] ?? $data['medico'] ?? null),
            'sede_principal' => $this->nullableString($data['sede_principal'] ?? $data['sede'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0:string,1:?string}
     */
    private function splitNameFields(array $data, string $firstKey, string $secondKey, string $combinedKey): array
    {
        $first = trim((string) ($data[$firstKey] ?? ''));
        $second = $this->nullableString($data[$secondKey] ?? null);

        if ($first !== '') {
            return [$first, $second];
        }

        $parts = preg_split('/\s+/', trim((string) ($data[$combinedKey] ?? ''))) ?: [];

        return [
            trim((string) ($parts[0] ?? '')),
            $this->nullableString(implode(' ', array_slice($parts, 1))),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        $normalized = trim((string) ($value ?? ''));

        return ctype_digit($normalized) ? (int) $normalized : null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function persistNullableFields(PatientDatum $patient, array $attributes): void
    {
        DB::table('patient_data')
            ->where('id', $patient->id)
            ->update([
                'telefono_alt' => $attributes['telefono_alt'],
                'medico_tratante_id' => $attributes['medico_tratante_id'],
                'sede_principal' => $attributes['sede_principal'],
            ]);

        $patient->refresh();
    }

    private function nextHcNumber(): string
    {
        $max = PatientDatum::query()
            ->pluck('hc_number')
            ->map(static fn(mixed $hcNumber): int => ctype_digit((string) $hcNumber) ? (int) $hcNumber : 0)
            ->max() ?? 0;

        return str_pad((string) max($max + 1, 10001), 6, '0', STR_PAD_LEFT);
    }
}
