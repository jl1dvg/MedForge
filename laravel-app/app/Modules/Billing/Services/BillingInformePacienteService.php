<?php

namespace App\Modules\Billing\Services;

use DateTime;
use PDO;

class BillingInformePacienteService
{
    /** @var array<string, array<string, mixed>> */
    private array $patientCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $detalleCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPatientDetails(string $hcNumber): array
    {
        $hcNumber = trim($hcNumber);
        if ($hcNumber === '') {
            return [];
        }

        if (isset($this->patientCache[$hcNumber])) {
            return $this->patientCache[$hcNumber];
        }

        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = ? LIMIT 1');
        $stmt->execute([$hcNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->patientCache[$hcNumber] = $row;
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetalleSolicitud(string $hcNumber, string $formId): array
    {
        $hcNumber = trim($hcNumber);
        $formId = trim($formId);
        if ($hcNumber === '' || $formId === '') {
            return [];
        }

        $cacheKey = $hcNumber . '|' . $formId;
        if (isset($this->detalleCache[$cacheKey])) {
            return $this->detalleCache[$cacheKey];
        }

        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT sp.*, cd.*
            FROM solicitud_procedimiento sp
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            WHERE sp.hc_number = ? AND sp.form_id = ?
            LIMIT 1
            SQL
        );
        $stmt->execute([$hcNumber, $formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->detalleCache[$cacheKey] = $row;
        return $row;
    }

    public function calcularEdad(?string $fechaNacimiento, ?string $fechaActual = null): ?int
    {
        if (!$fechaNacimiento) {
            return null;
        }

        try {
            $fechaNacimientoDt = new DateTime($fechaNacimiento);
            $fechaActualDt = $fechaActual ? new DateTime($fechaActual) : new DateTime();
            return $fechaActualDt->diff($fechaNacimientoDt)->y;
        } catch (\Throwable) {
            return null;
        }
    }
}
