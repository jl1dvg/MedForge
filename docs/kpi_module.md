# Módulo de KPIs

El módulo `KPI` centraliza el cálculo, almacenamiento y exposición de indicadores clave de la plataforma.

## Contrato de datos

Las mediciones se almacenan en `kpi_snapshots` con la siguiente convención:

| Campo | Descripción |
| --- | --- |
| `kpi_key` | Identificador único del indicador. Ej: `solicitudes.registradas`. |
| `period_start` / `period_end` | Rango temporal del snapshot (inclusive). |
| `period_granularity` | Granularidad declarada (`daily`, `weekly`, `monthly`). |
| `dimension_hash` | `sha256` de las dimensiones normalizadas. Permite idempotencia en `UPSERT`. |
| `dimensions_json` | Dimensiones opcionales codificadas como JSON. |
| `value` | Valor principal del KPI (conteo, monto, porcentaje, etc.). |
| `numerator` / `denominator` | Valores auxiliares para tasas. |
| `extra_json` | Datos derivados adicionales (por ejemplo, desglose por etapa). |
| `computed_at` | Fecha/hora de cálculo. |
| `source_version` | Versión de la lógica que generó el snapshot. |

### Definiciones actuales

```json
{
  "solicitudes.registradas": {
    "label": "Solicitudes registradas",
    "description": "Solicitudes quirúrgicas ingresadas en el periodo",
    "granularity": ["daily"],
    "dimensions": []
  },
  "solicitudes.agendadas": {
    "label": "Solicitudes con turno",
    "description": "Solicitudes que cuentan con un turno asignado",
    "granularity": ["daily"],
    "dimensions": []
  },
  "solicitudes.urgentes_sin_turno": {
    "label": "Urgentes sin turno",
    "description": "Solicitudes urgentes pendientes de agenda",
    "granularity": ["daily"],
    "dimensions": []
  },
  "solicitudes.con_cirugia": {
    "label": "Solicitudes con cirugía registrada",
    "description": "Solicitudes vinculadas a un protocolo en el periodo",
    "granularity": ["daily"],
    "dimensions": []
  },
  "solicitudes.conversion_agendada": {
    "label": "Conversión de agenda",
    "description": "Porcentaje de solicitudes que lograron agenda",
    "granularity": ["daily"],
    "dimensions": []
  },
  "crm.tareas.vencidas": {
    "label": "Tareas CRM vencidas",
    "description": "Tareas activas con fecha de vencimiento pasada",
    "granularity": ["daily"],
    "dimensions": []
  },
  "crm.tareas.avance": {
    "label": "Avance de tareas CRM",
    "description": "Porcentaje de tareas completadas sobre el total",
    "granularity": ["daily"],
    "dimensions": []
  },
  "protocolos.revision.revisados": {
    "label": "Protocolos revisados",
    "description": "Protocolos marcados como revisados",
    "granularity": ["daily"],
    "dimensions": []
  },
  "protocolos.revision.no_revisados": {
    "label": "Protocolos listos para auditoría",
    "description": "Protocolos completos que aún no se marcan como revisados",
    "granularity": ["daily"],
    "dimensions": []
  },
  "protocolos.revision.incompletos": {
    "label": "Protocolos incompletos",
    "description": "Protocolos con información faltante o inválida",
    "granularity": ["daily"],
    "dimensions": []
  },
  "reingresos.mismo_diagnostico.total": {
    "label": "Reingresos por diagnóstico",
    "description": "Pacientes que reingresan con el mismo diagnóstico en la ventana considerada",
    "granularity": ["daily"],
    "dimensions": []
  },
  "reingresos.mismo_diagnostico.tasa": {
    "label": "Tasa de reingresos por diagnóstico",
    "description": "Porcentaje de episodios que corresponden a reingresos con el mismo diagnóstico",
    "granularity": ["daily"],
    "dimensions": []
  }
}
```

## Servicios

- `Modules\KPI\Services\KpiCalculationService` recalcula snapshots a partir de las fuentes existentes (solicitudes, CRM y protocolos).
- `Modules\KPI\Services\KpiQueryService` expone utilidades de lectura para dashboards, reportes y APIs.

### Ventanas de cálculo especiales

- Los indicadores `reingresos.mismo_diagnostico.*` consideran como reingreso a todo paciente que vuelve a registrar un protocolo con el mismo diagnóstico definitivo dentro de los últimos 30 días.

## Automatización

- Cron `kpi-refresh` ejecuta el recálculo diario del rango `[hoy-1, hoy]`.
- El script `tools/kpi_recalculate.php` permite reprocesar rangos manualmente.

## Endpoints

- `GET /kpis` lista los KPI disponibles.
- `GET /kpis/{kpi}` devuelve snapshots filtrables por `start`, `end` y dimensiones.

