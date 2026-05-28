<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmContact;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CrmContactResolverService
{
    /**
     * Encuentra o crea un contacto CRM resolviendo la identidad con la estrategia correcta.
     *
     * Prioridad: cédula (fuerte) > teléfono provisional (débil)
     */
    public function resolve(
        string $phone,
        string $name,
        ?string $cedula,
        string $source,
        ?int $patientId = null,
    ): CrmContact {
        return DB::transaction(function () use ($phone, $name, $cedula, $source, $patientId): CrmContact {
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
                $provisional = CrmContact::query()->byPhone($phone)->provisional()->first();
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
            $byPhone = CrmContact::query()->byPhone($phone)->first();
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
