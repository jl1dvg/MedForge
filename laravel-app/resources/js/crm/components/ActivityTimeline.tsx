import React from 'react';
import type { CrmActivity, ActivityType } from '../types';

const TYPE_LABEL: Record<ActivityType, string> = {
  nota: 'Nota', llamada: 'Llamada', cambio_etapa: 'Cambio de etapa',
  email: 'Email', examen: 'Examen', solicitud: 'Solicitud', whatsapp: 'WhatsApp',
};

const CLINICAL_TYPES = new Set<ActivityType>(['examen', 'solicitud', 'whatsapp']);

function formatDate(d: string): string {
  const diff = (Date.now() - new Date(d).getTime()) / 60_000;
  if (diff < 60) return `Hace ${Math.floor(diff)} min`;
  if (diff < 1440) return `Hace ${Math.floor(diff / 60)}h`;
  return `Hace ${Math.floor(diff / 1440)}d`;
}

interface Props { activities: CrmActivity[] }

export function ActivityTimeline({ activities }: Props) {
  if (activities.length === 0) {
    return (
      <p style={{ fontSize: '.8125rem', color: 'var(--fg-mute)', textAlign: 'center', padding: '1rem 0' }}>
        Sin actividades registradas
      </p>
    );
  }
  return (
    <div className="crm-timeline">
      <div style={{ display: 'flex', flexDirection: 'column', gap: '.625rem' }}>
        {activities.map(a => (
          <div key={a.id} className="crm-timeline-item">
            <div className={`crm-timeline-dot ${a.type}`} />
            <div className={`crm-timeline-card${CLINICAL_TYPES.has(a.type) ? ` ${a.type}` : ''}`}>
              <div className="crm-timeline-desc">{a.description}</div>
              <div className="crm-timeline-meta">
                {TYPE_LABEL[a.type]} · {formatDate(a.created_at)} · {a.user_id ? `Usuario #${a.user_id}` : 'Sistema'}
                {a.source_id != null && (
                  <span style={{ marginLeft: '.375rem', color: 'var(--primary)', fontWeight: 600 }}>
                    #{a.source_id}
                  </span>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
