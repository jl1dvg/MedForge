<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmContact;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CrmContactResolverService
{
    /**
     * Normaliza teléfonos ecuatorianos a formato 10 dígitos (0XXXXXXXXX).
     * +593981... y 593981... → 0981...
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === null || $digits === '') {
            return $phone;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '593')) {
            return '0' . substr($digits, 3);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '93')) {
            return '0' . substr($digits, 2);
        }
        return strlen($digits) >= 7 ? $digits : $phone;
    }

    /**
     * Encuentra o crea un contacto CRM resolviendo la identidad con la estrategia correcta.
     *
     * Prioridad: patient_id (más fuerte) > cédula > teléfono provisional (débil)
     */
    public function resolve(
        string $phone,
        string $name,
        ?string $cedula,
        string $source,
        ?int $patientId = null,
    ): CrmContact {
        $rawPhone = $phone;
        $phone = self::normalizePhone($phone);

        return DB::transaction(function () use ($rawPhone, $phone, $name, $cedula, $source, $patientId): CrmContact {
            // 0. Match más fuerte por patient_id (único por paciente clínico)
            if ($patientId !== null) {
                $existing = CrmContact::query()->where('patient_id', $patientId)->first();
                if ($existing instanceof CrmContact) {
                    $existing->fill(['name' => $name]);
                    if ($phone !== '' && $existing->phone === '') {
                        $existing->phone = $phone;
                    }
                    $existing->save();
                    return $existing;
                }
            }

            // 1. Match fuerte por cédula
            if ($cedula !== null && $cedula !== '') {
                $existing = CrmContact::query()->byCedula($cedula)->first();

                if ($existing instanceof CrmContact) {
                    $existing->fill(['name' => $name, 'phone' => $phone]);
                    if ($patientId !== null && $existing->patient_id === null) {
                        $existing->patient_id = $patientId;
                        $existing->resolution = CrmContact::RESOLUTION_LINKED;
                    }
                    $existing->save();
                    return $existing;
                }

                // ¿Existe contacto provisional con ese teléfono? Upgrade.
                $provisional = CrmContact::query()
                    ->whereIn('phone', array_values(array_unique([$phone, $rawPhone])))
                    ->provisional()
                    ->first();
                if ($provisional instanceof CrmContact) {
                    $provisional->fill([
                        'name'       => $name,
                        'cedula'     => $cedula,
                        'resolution' => $patientId !== null
                            ? CrmContact::RESOLUTION_LINKED
                            : CrmContact::RESOLUTION_IDENTIFIED,
                        'patient_id' => $patientId,
                    ]);
                    $provisional->save();
                    return $provisional;
                }

                // Crear nuevo contacto identificado (con protección ante race condition)
                try {
                    return CrmContact::query()->create([
                        'name'       => $name,
                        'phone'      => $phone,
                        'cedula'     => $cedula,
                        'resolution' => $patientId !== null
                            ? CrmContact::RESOLUTION_LINKED
                            : CrmContact::RESOLUTION_IDENTIFIED,
                        'source'     => $source,
                        'patient_id' => $patientId,
                    ]);
                } catch (QueryException $e) {
                    // Otro worker insertó el mismo cédula concurrentemente — devolver el existente
                    if ((int) $e->errorInfo[1] === 1062) {
                        return CrmContact::query()->byCedula($cedula)->firstOrFail();
                    }
                    throw $e;
                }
            }

            // 2. Match débil por teléfono (provisional)
            $byPhone = CrmContact::query()
                ->whereIn('phone', array_values(array_unique([$phone, $rawPhone])))
                ->first();
            if ($byPhone instanceof CrmContact) {
                return $byPhone;
            }

            // 3. Crear provisional
            return CrmContact::query()->create([
                'name'       => $name,
                'phone'      => $phone,
                'cedula'     => null,
                'resolution' => CrmContact::RESOLUTION_PROVISIONAL,
                'source'     => $source,
                'patient_id' => $patientId,
            ]);
        });
    }

    /**
     * Vincula un contacto provisional a un patient_id una vez identificado.
     */
    public function linkToPatient(CrmContact $contact, int $patientId): CrmContact
    {
        $contact->patient_id = $patientId;
        $contact->resolution = CrmContact::RESOLUTION_LINKED;
        $contact->save();
        return $contact;
    }
}
