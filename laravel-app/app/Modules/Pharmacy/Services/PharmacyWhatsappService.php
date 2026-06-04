<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Services;

use App\Models\PharmacyPatient;
use App\Models\PharmacyPrescription;
use App\Models\PharmacyWhatsappLog;

class PharmacyWhatsappService
{
    public function send(
        PharmacyPatient $patient,
        PharmacyPrescription $prescription,
        string $tipo
    ): PharmacyWhatsappLog {
        // WhatsApp integration is always disabled for now (simulado)
        $mensaje = $this->buildMessage($tipo, $patient, $prescription);
        $numero  = $patient->whatsapp ?: $patient->telefono ?: '';

        return PharmacyWhatsappLog::create([
            'pharmacy_patient_id'      => $patient->id,
            'pharmacy_prescription_id' => $prescription->id,
            'tipo'                     => $tipo,
            'mensaje'                  => $mensaje,
            'numero_destino'           => $numero,
            'estado'                   => 'simulado',
            'metadata'                 => null,
        ]);
    }

    public function buildMessage(
        string $tipo,
        PharmacyPatient $patient,
        PharmacyPrescription $prescription
    ): string {
        $nombre = trim($patient->nombres . ' ' . $patient->apellidos);
        $fecha  = $prescription->fecha_prescripcion
            ? $prescription->fecha_prescripcion->format('d/m/Y')
            : '';

        return match ($tipo) {
            'receta_recibida' =>
                "Hola {$nombre}, hemos recibido su receta del {$fecha}. Pronto le informaremos sobre la disponibilidad de sus medicamentos.",
            'lista_para_entrega' =>
                "Hola {$nombre}, su pedido de medicamentos del {$fecha} está listo para entrega. Le contactaremos para coordinar.",
            'recordatorio_recompra' =>
                "Hola {$nombre}, es momento de renovar sus medicamentos de la receta del {$fecha}. Contáctenos para coordinar.",
            'entrega_en_camino' =>
                "Hola {$nombre}, su pedido de medicamentos del {$fecha} está en camino. Pronto llegará a su dirección.",
            default =>
                "Hola {$nombre}, tenemos un mensaje importante sobre su receta del {$fecha}. Contáctenos para más información.",
        };
    }
}
