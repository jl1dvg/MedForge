import React, { useEffect, useRef } from 'react';
import type { KanbanSlug, Solicitud } from '../types';
import { SlaChip } from './SlaChip';
import { AlertBadges } from './AlertBadges';
import { updateEstado } from '../api';

const ESTADOS: Array<{ slug: KanbanSlug; label: string }> = [
  { slug: 'recibida',            label: 'Recibida' },
  { slug: 'llamado',             label: 'Llamado' },
  { slug: 'revision-codigos',    label: 'Revisión códigos' },
  { slug: 'espera-documentos',   label: 'Documentación' },
  { slug: 'apto-oftalmologo',    label: 'Apto oftalmólogo' },
  { slug: 'apto-anestesia',      label: 'Apto anestesia' },
  { slug: 'listo-para-agenda',   label: 'Listo para agenda' },
  { slug: 'programada',          label: 'Programada' },
  { slug: 'completado',          label: 'Completado' },
];

function Field({ label, value }: { label: string; value?: string | number }) {
  if (!value) return null;
  return (
    <div className="v3-modal-field">
      <span className="v3-modal-field-label">{label}</span>
      <span className="v3-modal-field-value">{value}</span>
    </div>
  );
}

interface Props {
  sol: Solicitud | null;
  actualizarEstadoEndpoint: string;
  onClose: () => void;
  onEstadoUpdated: (id: number, nuevoEstado: KanbanSlug) => void;
}

export function SolicitudModal({ sol, actualizarEstadoEndpoint, onClose, onEstadoUpdated }: Props) {
  const dialogRef = useRef<HTMLDivElement>(null);
  const [saving, setSaving] = React.useState(false);
  const [saveError, setSaveError] = React.useState<string | null>(null);

  useEffect(() => {
    if (!sol) return;
    const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handleKey);
    dialogRef.current?.focus();
    return () => document.removeEventListener('keydown', handleKey);
  }, [sol, onClose]);

  if (!sol) return null;

  async function handleEstadoChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const nuevoEstado = e.target.value as KanbanSlug;
    setSaving(true);
    setSaveError(null);
    try {
      await updateEstado(actualizarEstadoEndpoint, sol!.id, nuevoEstado);
      onEstadoUpdated(sol!.id, nuevoEstado);
    } catch {
      setSaveError('No se pudo actualizar el estado. Intente de nuevo.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div
      className="v3-modal-backdrop"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-label={`Solicitud de ${sol.paciente}`}
    >
      <div
        className="v3-modal"
        ref={dialogRef}
        tabIndex={-1}
      >
        {/* Header */}
        <div className="v3-modal-header">
          <div>
            <h2 className="v3-modal-title">{sol.paciente}</h2>
            <p className="v3-modal-subtitle">HC: {sol.hc_number} · {sol.procedimiento || sol.tipo}</p>
          </div>
          <button className="v3-modal-close" onClick={onClose} aria-label="Cerrar">&times;</button>
        </div>

        {/* SLA + Alerts */}
        <div className="v3-modal-sla-row">
          <SlaChip status={sol.sla_status} />
          {sol.prioridad && <span className="v3-prioridad-label">Prioridad: <strong>{sol.prioridad}</strong></span>}
        </div>
        <AlertBadges sol={sol} />

        {/* Body */}
        <div className="v3-modal-body">
          <section className="v3-modal-section">
            <h3 className="v3-modal-section-title">Datos clínicos</h3>
            <div className="v3-modal-grid">
              <Field label="Doctor" value={sol.doctor} />
              <Field label="Procedimiento" value={sol.procedimiento} />
              <Field label="Tipo" value={sol.tipo} />
              <Field label="Ojo" value={sol.ojo} />
              <Field label="Afiliación" value={sol.afiliacion} />
              <Field label="Sede" value={sol.sede} />
              <Field label="Fecha programada" value={sol.fecha_programada} />
            </div>
          </section>

          <section className="v3-modal-section">
            <h3 className="v3-modal-section-title">CRM</h3>
            <div className="v3-modal-stats-row">
              <div className="v3-modal-stat">
                <span className="v3-modal-stat-value">{sol.crm_total_notas ?? 0}</span>
                <span className="v3-modal-stat-label">Notas</span>
              </div>
              <div className="v3-modal-stat">
                <span className="v3-modal-stat-value">{sol.crm_total_adjuntos ?? 0}</span>
                <span className="v3-modal-stat-label">Adjuntos</span>
              </div>
              <div className="v3-modal-stat">
                <span className="v3-modal-stat-value">{sol.crm_tareas_pendientes ?? 0}/{sol.crm_tareas_total ?? 0}</span>
                <span className="v3-modal-stat-label">Tareas abiertas</span>
              </div>
            </div>
          </section>

          <section className="v3-modal-section">
            <h3 className="v3-modal-section-title">Cambiar estado</h3>
            <div className="v3-modal-estado-row">
              <select
                className="v3-select v3-select-lg"
                defaultValue={sol.estado}
                onChange={handleEstadoChange}
                disabled={saving}
                aria-label="Estado de la solicitud"
              >
                {ESTADOS.map((e) => (
                  <option key={e.slug} value={e.slug}>{e.label}</option>
                ))}
              </select>
              {saving && <span className="v3-saving-indicator">Guardando…</span>}
            </div>
            {saveError && <p className="v3-error-msg">{saveError}</p>}
          </section>
        </div>

        {/* Footer: link to full detail */}
        <div className="v3-modal-footer">
          <a
            href={`/v2/solicitudes/${sol.hc_number}`}
            className="v3-btn v3-btn-primary"
            target="_blank"
            rel="noopener noreferrer"
          >
            Ver detalle completo →
          </a>
          <button className="v3-btn v3-btn-ghost" onClick={onClose} type="button">Cerrar</button>
        </div>
      </div>
    </div>
  );
}
