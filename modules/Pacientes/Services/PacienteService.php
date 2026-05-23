<?php
/**
 * Compatibility shim — Pacientes module migrated to Laravel in Onda 4.
 * Legacy modules (Billing, Consulta, Flujo) still reference this class.
 *
 * This shim is STANDALONE (no Laravel dependency) so it works under the
 * legacy autoloader which only loads /vendor/autoload.php (root), not
 * the laravel-app Composer autoloader.
 *
 * @deprecated Remove when Billing/Consulta/Flujo migrate to Laravel (Onda 5).
 */

namespace Modules\Pacientes\Services;

class PacienteService
{
    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Returns the full patient record for a given HC number.
     */
    public function getPatientDetails(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = ?');
        $stmt->execute([$hcNumber]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Calculates age in full years.
     * $fechaActual can be provided to calculate age relative to a past date.
     */
    public function calcularEdad(?string $fechaNacimiento, ?string $fechaActual = null): ?int
    {
        if (!$fechaNacimiento) {
            return null;
        }

        try {
            $fechaNacimientoDt = new \DateTime($fechaNacimiento);
            $fechaActualDt     = $fechaActual ? new \DateTime($fechaActual) : new \DateTime();

            return $fechaActualDt->diff($fechaNacimientoDt)->y;
        } catch (\Exception) {
            return null;
        }
    }
}
