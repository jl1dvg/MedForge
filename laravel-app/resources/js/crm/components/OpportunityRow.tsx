import React from 'react';
import type { CrmOpportunity, Stage, Source, Phase } from '../types';

const STAGE_LABEL: Record<Stage, string> = {
  nuevo: 'Nuevo', contactado: 'Contactado', en_evaluacion: 'En evaluación',
  propuesta: 'Propuesta', comprometido: 'Comprometido', ganado: 'Ganado', perdido: 'Perdido',
};

const SOURCE_LABEL: Record<Source, string> = {
  whatsapp: 'WhatsApp', solicitud: 'Solicitud', examen: 'Examen', manual: 'Manual', legacy: 'Migrado',
};

const PHASE_LABEL: Record<Phase, string> = {
  operational: 'Operativo',
  commercial: 'Comercial',
};

const ACTION_LABEL: Partial<Record<Stage, string>> = {
  nuevo: 'Contactar', contactado: 'Avanzar', en_evaluacion: 'Avanzar', propuesta: 'Seguimiento',
};

const STALE_WARNING_DAYS = 3;  // orange warning after 3 days without activity
const STALE_DANGER_DAYS  = 7;  // red danger after 7 days without activity

function timeAgo(dateStr: string | null): { label: string; urgentDays: number } {
  if (!dateStr) return { label: 'Sin actividad', urgentDays: 999 };
  const diffH = (Date.now() - new Date(dateStr).getTime()) / 3_600_000;
  const days = Math.floor(diffH / 24);
  if (diffH < 1) return { label: 'hace < 1h', urgentDays: 0 };
  if (diffH < 24) return { label: `hace ${Math.floor(diffH)}h`, urgentDays: 0 };
  return { label: `hace ${days}d`, urgentDays: days };
}

function daysUntilEscalation(escalationAt: string | null): number | null {
  if (!escalationAt) return null;
  const diff = (new Date(escalationAt).getTime() - Date.now()) / 86_400_000;
  return diff > 0 ? Math.ceil(diff) : 0;
}

interface Props {
  opp: CrmOpportunity;
  onClick: (opp: CrmOpportunity) => void;
}

export function OpportunityRow({ opp, onClick }: Props) {
  const { label: timeLabel, urgentDays } = timeAgo(opp.last_activity_at);
  const daysLeft = daysUntilEscalation(opp.escalation_at);
  const isEscalating = daysLeft !== null && daysLeft <= 2 && opp.phase === 'operational';
  const displaySource = opp.effective_source ?? opp.source;
  const displaySources = opp.effective_sources?.length ? opp.effective_sources : [displaySource];

  return (
    <tr className={isEscalating ? 'escalating' : ''} onClick={() => onClick(opp)}>
      <td>
        <div style={{ fontWeight: 700, color: 'var(--fg-1)', fontSize: '.8125rem' }}>
          {opp.contact?.name ?? '—'}
        </div>
        <div style={{ fontSize: '.7rem', color: 'var(--primary)', marginTop: '.15rem', fontWeight: 600 }}>
          {opp.title.replace(/^(Examen:|Solicitud:|Lead WhatsApp:)\s*/i, '')}
        </div>
        <div style={{ fontSize: '.6875rem', color: 'var(--fg-mute)', marginTop: '.1rem' }}>
          {opp.contact?.cedula ?? opp.contact?.phone ?? '—'}
        </div>
      </td>
      <td>
        <span className={`crm-stage-badge ${opp.stage}`}>{STAGE_LABEL[opp.stage]}</span>
      </td>
      <td>
        <span className={`crm-phase-badge ${opp.phase}`}>{PHASE_LABEL[opp.phase]}</span>
        {isEscalating && (
          <div className="crm-escalation-warn" style={{ marginTop: '.2rem' }}>
            Escala en {daysLeft}d
          </div>
        )}
      </td>
      <td style={{ color: 'var(--fg-mute)', fontSize: '.75rem' }}>
        {displaySources.map(source => SOURCE_LABEL[source]).join(' / ')}
      </td>
      <td style={{
        fontSize: '.75rem',
        color: urgentDays >= STALE_DANGER_DAYS
          ? 'var(--danger)'
          : urgentDays >= STALE_WARNING_DAYS
          ? 'var(--warning)'
          : 'var(--fg-mute)',
      }}>
        {timeLabel}
      </td>
      <td>
        {ACTION_LABEL[opp.stage] && (
          <button
            className="btn btn-sm"
            style={{ background: 'var(--primary-fade)', color: 'var(--primary)', border: 'none' }}
            onClick={e => { e.stopPropagation(); onClick(opp); }}
          >
            {ACTION_LABEL[opp.stage]}
          </button>
        )}
      </td>
    </tr>
  );
}
