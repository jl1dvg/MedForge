<?php

declare(strict_types=1);

namespace Modules\KPI\Support;

use InvalidArgumentException;

final class KpiRegistry
{
    public const SOURCE_VERSION = '1.0.0';

    /**
     * @var array<string, array<string, mixed>>
     */
    private const DEFINITIONS = [
        'solicitudes.registradas' => [
            'label' => 'Solicitudes registradas',
            'description' => 'Solicitudes quirúrgicas ingresadas en el periodo.',
            'granularity' => ['daily'],
            'calculator' => 'solicitudes',
            'value_type' => 'count',
        ],
        'solicitudes.agendadas' => [
            'label' => 'Solicitudes con turno',
            'description' => 'Solicitudes quirúrgicas con turno asignado.',
            'granularity' => ['daily'],
            'calculator' => 'solicitudes',
            'value_type' => 'count',
        ],
        'solicitudes.urgentes_sin_turno' => [
            'label' => 'Urgentes sin turno',
            'description' => 'Solicitudes urgentes pendientes de agenda.',
            'granularity' => ['daily'],
            'calculator' => 'solicitudes',
            'value_type' => 'count',
        ],
        'solicitudes.con_cirugia' => [
            'label' => 'Solicitudes con cirugía',
            'description' => 'Solicitudes vinculadas a un protocolo registrado.',
            'granularity' => ['daily'],
            'calculator' => 'solicitudes',
            'value_type' => 'count',
        ],
        'solicitudes.conversion_agendada' => [
            'label' => 'Conversión de agenda',
            'description' => 'Porcentaje de solicitudes que lograron agenda.',
            'granularity' => ['daily'],
            'calculator' => 'solicitudes',
            'value_type' => 'percentage',
        ],
        'crm.tareas.vencidas' => [
            'label' => 'Tareas CRM vencidas',
            'description' => 'Tareas CRM activas con fecha de vencimiento pasada.',
            'granularity' => ['daily'],
            'calculator' => 'crm_tasks',
            'value_type' => 'count',
        ],
        'crm.tareas.avance' => [
            'label' => 'Avance de tareas CRM',
            'description' => 'Porcentaje de tareas CRM completadas.',
            'granularity' => ['daily'],
            'calculator' => 'crm_tasks',
            'value_type' => 'percentage',
        ],
        'protocolos.revision.revisados' => [
            'label' => 'Protocolos revisados',
            'description' => 'Protocolos marcados como revisados.',
            'granularity' => ['daily'],
            'calculator' => 'protocolos_revision',
            'value_type' => 'count',
        ],
        'protocolos.revision.no_revisados' => [
            'label' => 'Protocolos listos para auditoría',
            'description' => 'Protocolos completos que aún no se marcan como revisados.',
            'granularity' => ['daily'],
            'calculator' => 'protocolos_revision',
            'value_type' => 'count',
        ],
        'protocolos.revision.incompletos' => [
            'label' => 'Protocolos incompletos',
            'description' => 'Protocolos con información faltante o inválida.',
            'granularity' => ['daily'],
            'calculator' => 'protocolos_revision',
            'value_type' => 'count',
        ],
        'reingresos.mismo_diagnostico.total' => [
            'label' => 'Reingresos por diagnóstico',
            'description' => 'Pacientes que reingresan con el mismo diagnóstico en la ventana de seguimiento.',
            'granularity' => ['daily'],
            'calculator' => 'reingresos',
            'value_type' => 'count',
        ],
        'reingresos.mismo_diagnostico.tasa' => [
            'label' => 'Tasa de reingresos por diagnóstico',
            'description' => 'Porcentaje de episodios que corresponden a reingresos con el mismo diagnóstico.',
            'granularity' => ['daily'],
            'calculator' => 'reingresos',
            'value_type' => 'percentage',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $kpiKey): array
    {
        if (!isset(self::DEFINITIONS[$kpiKey])) {
            throw new InvalidArgumentException(sprintf('KPI "%s" no está registrado.', $kpiKey));
        }

        return self::DEFINITIONS[$kpiKey];
    }

    /**
     * @return array<string>
     */
    public static function calculators(): array
    {
        return array_values(array_unique(array_map(static function (array $definition): string {
            return $definition['calculator'];
        }, self::DEFINITIONS)));
    }
}
