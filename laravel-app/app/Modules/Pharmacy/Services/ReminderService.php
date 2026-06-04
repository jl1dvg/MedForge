<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Services;

use App\Models\PharmacyPrescription;
use App\Models\PharmacyReminder;
use Carbon\Carbon;

class ReminderService
{
    /**
     * For each item with duracion_dias, create a reminder (fecha_prescripcion + duracion_dias - 3 days).
     */
    public function createForPrescription(PharmacyPrescription $prescription): void
    {
        $prescription->load('items');
        $fechaBase = $prescription->fecha_prescripcion
            ? Carbon::instance($prescription->fecha_prescripcion)
            : Carbon::now();

        foreach ($prescription->items as $item) {
            if ($item->duracion_dias === null || $item->duracion_dias <= 0) {
                continue;
            }

            $fechaRecordatorio = $fechaBase->copy()->addDays(max(0, $item->duracion_dias - 3));

            PharmacyReminder::create([
                'pharmacy_prescription_id'      => $prescription->id,
                'pharmacy_patient_id'           => $prescription->pharmacy_patient_id,
                'pharmacy_prescription_item_id' => $item->id,
                'descripcion'                   => 'Renovación de ' . $item->nombre_medicamento,
                'fecha_recordatorio'            => $fechaRecordatorio->toDateString(),
                'estado'                        => 'pendiente',
                'notas'                         => null,
            ]);
        }
    }
}
