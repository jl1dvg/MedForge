<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Services;

use App\Models\PharmacyInventory;
use App\Models\PharmacyPatient;
use App\Models\PharmacyPrescription;
use App\Models\PharmacyPrescriptionItem;
use Illuminate\Support\Facades\DB;

class PrescriptionService
{
    /**
     * Create or update patient, create prescription + items, run stock matching.
     *
     * @param array<string, mixed> $data
     */
    public function createFromApi(array $data): PharmacyPrescription
    {
        return DB::transaction(function () use ($data): PharmacyPrescription {
            $patientData = (array) ($data['patient'] ?? []);

            $patient = PharmacyPatient::updateOrCreate(
                ['identificacion' => (string) ($patientData['identificacion'] ?? '')],
                [
                    'nombres'          => (string) ($patientData['nombres'] ?? ''),
                    'apellidos'        => (string) ($patientData['apellidos'] ?? ''),
                    'telefono'         => (string) ($patientData['telefono'] ?? ''),
                    'whatsapp'         => (string) ($patientData['whatsapp'] ?? ''),
                    'email'            => (string) ($patientData['email'] ?? ''),
                    'clinica'          => (string) ($patientData['clinica'] ?? ''),
                    'medico_referidor' => (string) ($patientData['medico_referidor'] ?? ''),
                    'notas'            => (string) ($patientData['notas'] ?? ''),
                ]
            );

            $prescription = PharmacyPrescription::create([
                'pharmacy_patient_id' => $patient->id,
                'external_id'         => $data['external_id'] ?? null,
                'clinica'             => (string) ($data['clinica'] ?? ''),
                'medico'              => (string) ($data['medico'] ?? ''),
                'estado'              => 'pendiente',
                'notas'               => (string) ($data['notas'] ?? ''),
                'fecha_prescripcion'  => $data['fecha_prescripcion'] ?? now()->toDateString(),
            ]);

            foreach ((array) ($data['items'] ?? []) as $itemData) {
                PharmacyPrescriptionItem::create([
                    'pharmacy_prescription_id' => $prescription->id,
                    'nombre_medicamento'       => (string) ($itemData['nombre_medicamento'] ?? ''),
                    'principio_activo'         => $itemData['principio_activo'] ?? null,
                    'presentacion'             => (string) ($itemData['presentacion'] ?? ''),
                    'dosis'                    => (string) ($itemData['dosis'] ?? ''),
                    'frecuencia'               => (string) ($itemData['frecuencia'] ?? ''),
                    'duracion_dias'            => isset($itemData['duracion_dias']) ? (int) $itemData['duracion_dias'] : null,
                    'indicaciones'             => $itemData['indicaciones'] ?? null,
                    'disponibilidad'           => 'no_disponible',
                    'inventory_id'             => null,
                ]);
            }

            $this->matchStockForPrescription($prescription);

            return $prescription->fresh(['items', 'patient']) ?? $prescription;
        });
    }

    public function updateStatus(PharmacyPrescription $prescription, string $estado): void
    {
        $prescription->update(['estado' => $estado]);
    }

    /**
     * For each item, fuzzy-match by nombre_medicamento against pharmacy_inventory.
     */
    public function matchStockForPrescription(PharmacyPrescription $prescription): void
    {
        $items = $prescription->items()->get();

        foreach ($items as $item) {
            $nombre = trim((string) $item->nombre_medicamento);
            if ($nombre === '') {
                continue;
            }

            /** @var PharmacyInventory|null $match */
            $match = PharmacyInventory::where('estado', 'activo')
                ->where('nombre', 'LIKE', '%' . $nombre . '%')
                ->orderByDesc('stock')
                ->first();

            if ($match === null) {
                $item->update(['disponibilidad' => 'no_disponible', 'inventory_id' => null]);
                continue;
            }

            if ($match->stock <= 0) {
                $disponibilidad = 'no_disponible';
            } else {
                $disponibilidad = 'disponible';
            }

            $item->update([
                'disponibilidad' => $disponibilidad,
                'inventory_id'   => $match->id,
            ]);
        }
    }
}
