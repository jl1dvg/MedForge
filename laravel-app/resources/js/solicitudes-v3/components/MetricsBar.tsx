import React from 'react';
import type { KanbanData, KanbanSlug, Solicitud } from '../types';

interface Props {
  data: Record<KanbanSlug, Solicitud[]> | null;
}

export function MetricsBar({ data }: Props) {
  if (!data) return null;

  const all = Object.values(data).flat();
  const total = all.length;
  const criticos = all.filter((s) => s.sla_status === 'critico' || s.sla_status === 'vencido').length;
  const sinProgramar = all.filter((s) => s.sla_status === 'sin_fecha').length;
  const completados = (data['completado'] ?? []).length;
  const conAlerta = all.filter((s) =>
    s.alert_reprogramacion ||
    s.alert_documentos_faltantes ||
    s.alert_derivacion_vencida ||
    s.alert_tarea_vencida
  ).length;

  const metrics = [
    { label: 'Total',         value: total,       cls: '' },
    { label: 'Críticos/SLA',  value: criticos,    cls: criticos  > 0 ? 'v3-metric--danger' : '' },
    { label: 'Sin programar', value: sinProgramar, cls: sinProgramar > 0 ? 'v3-metric--warn' : '' },
    { label: 'Con alertas',   value: conAlerta,   cls: conAlerta > 0 ? 'v3-metric--warn' : '' },
    { label: 'Completados',   value: completados, cls: 'v3-metric--ok' },
  ];

  return (
    <div className="v3-metrics-bar" role="region" aria-label="Métricas resumen">
      {metrics.map((m) => (
        <div key={m.label} className={`v3-metric ${m.cls}`}>
          <span className="v3-metric-value">{m.value}</span>
          <span className="v3-metric-label">{m.label}</span>
        </div>
      ))}
    </div>
  );
}
