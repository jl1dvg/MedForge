<?php

namespace Modules\Reporting\Services\Definitions;

use Controllers\ExamenesController;
use InvalidArgumentException;
use Modules\Reporting\Support\SolicitudDataFormatter;
use PDO;
use RuntimeException;

abstract class AbstractSolicitudReport implements ReportDefinitionInterface
{
    protected ExamenesController $solicitudController;

    public function __construct(protected PDO $pdo)
    {
        $this->solicitudController = new ExamenesController($pdo);
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

        return SolicitudDataFormatter::enrich($data, $formId, $hcNumber);
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
}
