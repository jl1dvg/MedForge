<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Http\Controllers;

use App\Modules\Pharmacy\Services\PrescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyApiController
{
    private PrescriptionService $prescriptionService;

    public function __construct()
    {
        $this->prescriptionService = new PrescriptionService();
    }

    public function storePrescription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient'                  => 'required|array',
            'patient.nombres'          => 'required|string|max:255',
            'patient.apellidos'        => 'required|string|max:255',
            'patient.identificacion'   => 'required|string|max:50',
            'patient.telefono'         => 'nullable|string|max:20',
            'patient.whatsapp'         => 'nullable|string|max:20',
            'patient.email'            => 'nullable|email|max:255',
            'patient.clinica'          => 'nullable|string|max:255',
            'patient.medico_referidor' => 'nullable|string|max:255',
            'items'                    => 'required|array|min:1',
            'items.*.nombre_medicamento' => 'required|string|max:255',
            'items.*.presentacion'     => 'nullable|string|max:255',
            'items.*.dosis'            => 'nullable|string|max:255',
            'items.*.frecuencia'       => 'nullable|string|max:255',
            'items.*.duracion_dias'    => 'nullable|integer|min:1',
            'items.*.indicaciones'     => 'nullable|string',
            'external_id'              => 'nullable|string|max:255',
            'clinica'                  => 'nullable|string|max:255',
            'medico'                   => 'nullable|string|max:255',
            'notas'                    => 'nullable|string',
            'fecha_prescripcion'       => 'nullable|date',
        ]);

        try {
            $prescription = $this->prescriptionService->createFromApi($data);
            return response()->json([
                'success'         => true,
                'prescription_id' => $prescription->id,
                'estado'          => $prescription->estado,
                'patient_id'      => $prescription->pharmacy_patient_id,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear receta: ' . $e->getMessage(),
            ], 500);
        }
    }
}
