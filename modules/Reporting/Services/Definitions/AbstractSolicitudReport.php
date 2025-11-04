<?php

namespace Modules\Reporting\Services\Definitions;

use Controllers\SolicitudController;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

abstract class AbstractSolicitudReport implements ReportDefinitionInterface
{
    protected SolicitudController $solicitudController;

    public function __construct(protected PDO $pdo)
    {
        $this->solicitudController = new SolicitudController($pdo);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function buildSolicitudData(array $params): array
    {
        $formId = $this->extractParam($params, ['form_id', 'formId']);
        $hcNumber = $this->extractParam($params, ['hc_number', 'hcNumber']);

        if ($formId === null || $hcNumber === null) {
            throw new InvalidArgumentException('Se requieren los parámetros form_id y hc_number.');
        }

        $data = $this->solicitudController->obtenerDatosParaVista($hcNumber, $formId);

        if (empty($data) || empty($data['solicitud'])) {
            throw new RuntimeException('No se encontraron datos para la solicitud indicada.');
        }

        $paciente = $data['paciente'] ?? [];
        $solicitud = $data['solicitud'] ?? [];

        $data['paciente'] = is_array($paciente) ? $paciente : [];
        $data['solicitud'] = is_array($solicitud) ? $solicitud : [];
        $data['diagnostico'] = isset($data['diagnostico']) && is_array($data['diagnostico']) ? $data['diagnostico'] : [];
        $data['consulta'] = isset($data['consulta']) && is_array($data['consulta']) ? $data['consulta'] : [];
        $data['derivacion'] = isset($data['derivacion']) && is_array($data['derivacion']) ? $data['derivacion'] : [];

        $data['edadPaciente'] = $this->calculateAge(
            $data['paciente']['fecha_nacimiento'] ?? null,
            $data['solicitud']['created_at'] ?? null
        );

        $data['form_id'] = $formId;
        $data['hc_number'] = $hcNumber;

        return $data;
    }

    private function extractParam(array $params, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (!isset($params[$candidate])) {
                continue;
            }

            $value = trim((string) $params[$candidate]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function calculateAge(?string $birthDate, ?string $referenceDate): ?int
    {
        if (empty($birthDate)) {
            return null;
        }

        $birth = DateTimeImmutable::createFromFormat('Y-m-d', substr($birthDate, 0, 10))
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $birthDate)
            ?: DateTimeImmutable::createFromFormat('d-m-Y', $birthDate);

        if (!$birth) {
            return null;
        }

        $reference = null;
        if ($referenceDate) {
            $reference = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $referenceDate)
                ?: DateTimeImmutable::createFromFormat('Y-m-d', substr($referenceDate, 0, 10));
        }

        if (!$reference) {
            $reference = new DateTimeImmutable();
        }

        return $birth->diff($reference)->y;
    }
}
