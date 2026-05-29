import React, { useState } from 'react';
import type { CrmOpportunity, Stage } from '../types';
import { api } from '../api';
import { StageSelector } from './StageSelector';
import { ActivityTimeline } from './ActivityTimeline';
import { NoteForm } from './NoteForm';

interface Props {
  opportunity: CrmOpportunity;
  onClose: () => void;
  onUpdated: (opp: CrmOpportunity) => void;
}

const RESOLUTION_STYLE: Record<string, React.CSSProperties> = {
  provisional: { background: 'var(--warning-light)', color: 'var(--warning)' },
  identified:  { background: 'var(--info-light)',    color: 'var(--info)' },
  linked:      { background: 'var(--success-light)', color: 'var(--success)' },
};

const RESOLUTION_LABEL: Record<string, string> = {
  provisional: 'Provisional', identified: 'Identificado', linked: 'Vinculado',
};

const PHASE_STYLE: Record<string, React.CSSProperties> = {
  operational: { background: 'var(--info-light)', color: 'var(--info)' },
  commercial:  { background: 'var(--success-light)', color: 'var(--success)' },
};

export function DetailPanel({ opportunity: initial, onClose, onUpdated }: Props) {
  const [opp, setOpp] = useState(initial);
  const [stageLoading, setStageLoading] = useState(false);

  const handleStageChange = async (stage: Stage) => {
    setStageLoading(true);
    try {
      const updated = await api.opportunities.update(opp.id, { stage });
      setOpp(updated);
      onUpdated(updated);
    } finally {
      setStageLoading(false);
    }
  };

  const handleSaveNote = async (type: string, description: string) => {
    await api.opportunities.addActivity(opp.id, type, description);
    const refreshed = await api.opportunities.get(opp.id);
    setOpp(refreshed);
    onUpdated(refreshed);
  };

  const contact = opp.contact;
  const resolution = contact?.resolution ?? 'provisional';

  return (
    <div className="crm-detail-panel">
      <div className="crm-detail-header">
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
          <div>
            <h2 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: 'var(--fg-1)', fontFamily: 'var(--font-display)' }}>
              {contact?.name ?? '—'}
            </h2>
            <div style={{ display: 'flex', alignItems: 'center', gap: '.375rem', marginTop: '.375rem', flexWrap: 'wrap' }}>
              <span className="crm-phase-badge" style={PHASE_STYLE[opp.phase]}>
                {opp.phase === 'operational' ? 'Operativo' : 'Comercial'}
              </span>
              <span style={{
                fontSize: '.6875rem', fontWeight: 700, padding: '.15rem .45rem',
                borderRadius: 'var(--radius-pill)', ...RESOLUTION_STYLE[resolution],
              }}>
                {RESOLUTION_LABEL[resolution]}
              </span>
            </div>
          </div>
          <button
            onClick={onClose}
            style={{
              width: '2rem', height: '2rem', borderRadius: 'var(--radius-sm)',
              border: '1px solid var(--border)', background: 'transparent',
              cursor: 'pointer', color: 'var(--fg-mute)', fontSize: '1.25rem', lineHeight: 1,
            }}
          >
            ×
          </button>
        </div>
      </div>

      <div className="crm-detail-body">
        {/* Left: contact info + stage selector + actions */}
        <div className="crm-detail-left">
          <div>
            <p className="crm-section-label">Contacto</p>
            <div className="crm-contact-info">
              <p style={{ margin: '0 0 .25rem', fontSize: '.8125rem', color: 'var(--fg-2)' }}>{contact?.phone ?? '—'}</p>
              {contact?.cedula && <p style={{ margin: '0 0 .25rem', fontSize: '.8125rem', color: 'var(--fg-2)' }}>{contact.cedula}</p>}
              {contact?.email && <p style={{ margin: 0, fontSize: '.8125rem', color: 'var(--primary)' }}>{contact.email}</p>}
            </div>
          </div>

          <div>
            <p className="crm-section-label">Etapa actual</p>
            <StageSelector current={opp.stage} onChange={s => { void handleStageChange(s); }} loading={stageLoading} />
          </div>

          <div className="crm-action-grid">
            <button className="btn btn-sm" style={{ background: 'var(--warning-light)', color: 'var(--fg-1)', border: 'none' }}>
              📞 Llamar
            </button>
            <button className="btn btn-sm" style={{ background: 'var(--info-light)', color: 'var(--info)', border: 'none' }}>
              ✉ Email
            </button>
            <button className="btn btn-sm" style={{
              gridColumn: '1 / -1', background: 'var(--danger-light)',
              color: 'var(--danger)', border: '1px solid var(--danger-light)',
            }}>
              Marcar como perdido
            </button>
          </div>
        </div>

        {/* Right: note form + activity timeline */}
        <div className="crm-detail-right">
          <div>
            <p className="crm-section-label">Registrar actividad</p>
            <NoteForm onSave={handleSaveNote} />
          </div>
          <div>
            <p className="crm-section-label">Historial</p>
            <ActivityTimeline activities={opp.activities ?? []} />
          </div>
        </div>
      </div>
    </div>
  );
}
