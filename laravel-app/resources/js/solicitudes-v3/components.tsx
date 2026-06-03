// ============================================================
// MedForge · Solicitudes v3 — Shared components
// ============================================================
import React from 'react';
import type { Solicitud, ChecklistProgress, Alert } from './types';

const MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

export function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return `${String(d.getDate()).padStart(2, '0')} ${MESES[d.getMonth()]}`;
}

export function fmtDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return `${String(d.getDate()).padStart(2, '0')} ${MESES[d.getMonth()]} · ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

export function fmtSla(sol: Pick<Solicitud, 'sla_status' | 'sla_hours_remaining' | 'sla_label'>): string {
  if (sol.sla_status === 'ok' && sol.sla_hours_remaining == null) return sol.sla_label ?? '—';
  const h = sol.sla_hours_remaining;
  if (h == null) return sol.sla_label ?? '—';
  if (h < 0) return `${Math.abs(h)}h vencido`;
  if (h < 24) return `${h}h restantes`;
  return `${Math.round(h / 24)}d restantes`;
}

export const SLA_META = {
  vencido: { icon: 'mdi-alert-octagon',    label: 'Vencido'  },
  critico: { icon: 'mdi-clock-alert-outline', label: 'Crítico' },
  ok:      { icon: 'mdi-clock-check-outline', label: 'En tiempo' },
};

export const money = (n: number | null | undefined): string =>
  '$' + Number(n || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ---- SlaBadge -----------------------------------------------

interface SlaBadgeProps { sol: Pick<Solicitud, 'sla_status' | 'sla_hours_remaining' | 'sla_label'>; }

export function SlaBadge({ sol }: SlaBadgeProps) {
  const meta = SLA_META[sol.sla_status] ?? SLA_META.ok;
  return (
    <span className={`card-sla sla-${sol.sla_status}`} title={sol.sla_label}>
      <i className={`mdi ${meta.icon}`}></i>{fmtSla(sol)}
    </span>
  );
}

// ---- DocAvatar ----------------------------------------------

interface DocAvatarProps { name: string; cls?: string; }

export function DocAvatar({ name, cls = 'doc-av' }: DocAvatarProps) {
  const ini = (name || '').split(/\s+/).filter((w) => /[A-Za-zÁÉÍÓÚÑ]/.test(w)).slice(-2).map((w) => w[0]).join('').toUpperCase();
  return <span className={cls}>{ini || '—'}</span>;
}

// ---- AfilChip -----------------------------------------------

interface AfilChipProps { sol: Pick<Solicitud, 'afiliacion_tone' | 'empresa_seguro'>; }

export function AfilChip({ sol }: AfilChipProps) {
  return (
    <span className={`chip chip-afil tone-${sol.afiliacion_tone}`}>
      <i className="mdi mdi-shield-account-outline"></i>{sol.empresa_seguro}
    </span>
  );
}

// ---- ChecklistBar -------------------------------------------

interface ChecklistBarProps { progress: ChecklistProgress; compact?: boolean; }

export function ChecklistBar({ progress }: ChecklistBarProps) {
  const total = progress.total || 1;
  const done = progress.completed || 0;
  return (
    <div className="card-progress">
      <div className="cp-head">
        <span className="cp-steps">{done}/{total} pasos · {progress.percent}%</span>
        <span className="cp-next" title={progress.next_label}>
          <i className="mdi mdi-arrow-right-thin"></i>
          <span className="nx">{progress.next_label}</span>
        </span>
      </div>
      <div className="cp-bar">
        {Array.from({ length: total }).map((_, i) => (
          <span key={i} className={`cp-seg ${i < done ? 'done' : ''}`}></span>
        ))}
      </div>
    </div>
  );
}

// ---- AlertIcons --------------------------------------------

interface AlertIconsProps { alerts: Alert[]; }

export function AlertIcons({ alerts }: AlertIconsProps) {
  if (!alerts || !alerts.length) return <span></span>;
  return (
    <div className="card-alerts">
      {alerts.slice(0, 4).map((a, i) => (
        <i key={i} className={`mdi ${a.icon} alert-ic tone-${a.tone}`} title={a.label}></i>
      ))}
    </div>
  );
}

// ---- DnD context type ---------------------------------------

export interface DndContext {
  draggingId: number | null;
  dropTarget: string | null;
  onDragStart: (e: React.DragEvent, sol: Solicitud) => void;
  onDragEnd: () => void;
  onDragOver: (e: React.DragEvent, slug: string) => void;
  onDragLeave: () => void;
  onDrop: (e: React.DragEvent, slug: string) => void;
}

// ---- Card ---------------------------------------------------

interface CardProps {
  sol: Solicitud;
  onOpen: (id: number) => void;
  onAdvance: (id: number) => void;
  isLast: boolean;
  dnd: DndContext;
}

export function Card({ sol, onOpen, onAdvance, isLast, dnd }: CardProps) {
  return (
    <article
      className={`card sla-${sol.sla_status} ${dnd.draggingId === sol.id ? 'is-dragging' : ''}`}
      draggable
      onDragStart={(e: React.DragEvent<HTMLElement>) => dnd.onDragStart(e, sol)}
      onDragEnd={dnd.onDragEnd}
      onClick={() => onOpen(sol.id)}
    >
      <div className="card-top">
        {sol.prioridad === 'urgente' && <span className="urgent-flag" title="Prioridad urgente"></span>}
        <div className="ident">
          <h6 className="card-name"><span className="name-txt">{sol.full_name}</span></h6>
          <div className="card-sub">
            <span>HC {sol.hc_number}</span>
            <span className="sep">·</span>
            <span>{sol.form_id}</span>
            <span className="sep">·</span>
            <span>{fmtDate(sol.fecha)}</span>
          </div>
        </div>
        <SlaBadge sol={sol} />
      </div>

      <div className="card-proc">
        <i className="mdi mdi-eye-check-outline pi"></i>
        <div className="pt">{sol.procedimiento_short}<span className="eye">{sol.ojo}</span></div>
      </div>

      <div className="card-chips">
        <AfilChip sol={sol} />
        <span className="chip chip-sede"><i className="mdi mdi-map-marker-outline"></i>{sol.sede}</span>
        {sol.turno && <span className="chip chip-turno"><i className="mdi mdi-ticket-confirmation-outline"></i>{sol.turno}</span>}
      </div>

      <div className="card-doc">
        <DocAvatar name={sol.doctor} />
        <span className="doc-name">{sol.doctor}</span>
      </div>

      <ChecklistBar progress={sol.checklist_progress} />

      <div className="card-foot">
        <AlertIcons alerts={sol.alerts} />
        <div className="crm-mini density-hide">
          <span title="Notas"><i className="mdi mdi-note-text-outline"></i>{sol.crm.notas}</span>
          <span title="Adjuntos"><i className="mdi mdi-paperclip"></i>{sol.crm.adjuntos}</span>
          <span title="Tareas"><i className="mdi mdi-format-list-checks"></i>{sol.crm.tareas_pendientes}/{sol.crm.tareas_total}</span>
        </div>
      </div>

      {!isLast && (
        <div className="card-cta" onClick={(e: React.MouseEvent) => e.stopPropagation()}>
          <button className="cta-advance" onClick={() => onAdvance(sol.id)} title="Avanzar a la siguiente etapa">
            <i className="mdi mdi-check-circle-outline"></i>{sol.checklist_progress.next_label}
          </button>
          <button title="Abrir detalle" onClick={() => onOpen(sol.id)}>
            <i className="mdi mdi-arrow-expand"></i>
          </button>
        </div>
      )}
    </article>
  );
}

// ---- Kpi ----------------------------------------------------

interface KpiProps {
  tone: string;
  icon: string;
  value: number;
  label: string;
  active: boolean;
  onClick: () => void;
}

export function Kpi({ tone, icon, value, label, active, onClick }: KpiProps) {
  return (
    <button className={`kpi tone-${tone} ${active ? 'is-active' : ''}`} onClick={onClick}>
      <span className="kpi-ic"><i className={`mdi ${icon}`}></i></span>
      <span className="kpi-body">
        <span className="kpi-value">{value}</span>
        <span className="kpi-label">{label}</span>
      </span>
    </button>
  );
}
