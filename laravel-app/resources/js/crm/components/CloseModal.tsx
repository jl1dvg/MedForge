import React, { useState, useEffect } from 'react';
import type { OpportunityView } from '../types';
import { fmtMoney } from '../helpers';

const LOSS_REASONS = [
  { id: 'precio', label: 'Precio', icon: 'mdi-cash-remove' },
  { id: 'no_contesta', label: 'No contesta', icon: 'mdi-phone-off' },
  { id: 'otra_clinica', label: 'Eligió otra clínica', icon: 'mdi-hospital-building' },
  { id: 'no_apto', label: 'No es candidato', icon: 'mdi-account-cancel-outline' },
  { id: 'sin_cobertura', label: 'Sin cobertura', icon: 'mdi-shield-off-outline' },
  { id: 'lo_pensara', label: 'Lo va a pensar', icon: 'mdi-thought-bubble-outline' },
];

interface CloseModalProps {
  op: OpportunityView | null;
  mode: 'win' | 'lose' | null;
  open: boolean;
  onClose: () => void;
  onConfirm: (id: number, mode: 'win' | 'lose', data: { reason: string; reason_label?: string; note: string }) => void;
}

export function CloseModal({ op, mode, open, onClose, onConfirm }: CloseModalProps) {
  const [reason, setReason] = useState('');
  const [note, setNote] = useState('');

  useEffect(() => {
    if (open) { setReason(''); setNote(''); }
  }, [open, op?.id]);

  if (!op) return <div className={`modal-backdrop${open ? ' open' : ''}`} onClick={onClose}></div>;

  const isWin = mode === 'win';

  return (
    <div className={`modal-backdrop${open ? ' open' : ''}`} onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className={`modal-head ${isWin ? 'win' : 'lose'}`}>
          <span className="mh-ic">
            <i className={`mdi ${isWin ? 'mdi-trophy-variant' : 'mdi-close-octagon'}`}></i>
          </span>
          <div>
            <h2>{isWin ? 'Marcar como ganada' : 'Marcar como perdida'}</h2>
            <p>{op.full_name} · {op.procedimiento_short}</p>
          </div>
          <button className="mh-close" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>

        <div className="modal-body">
          {isWin ? (
            <>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '14px 16px', background: '#dff5ee', borderRadius: 'var(--radius)' }}>
                <i className="mdi mdi-check-decagram" style={{ fontSize: 30, color: 'var(--success)' }}></i>
                <div>
                  <div style={{ fontWeight: 600, color: '#17654f' }}>¡Bien hecho!</div>
                  <div style={{ fontSize: 12.5, color: '#17654f' }}>Se registrará como procedimiento realizado.</div>
                </div>
              </div>
              <div>
                <label className="field-label">Nota de cierre (opcional)</label>
                <textarea className="fld" rows={2} placeholder="Ej. Cirugía realizada sin complicaciones" value={note} onChange={e => setNote(e.target.value)}></textarea>
              </div>
            </>
          ) : (
            <>
              <div>
                <label className="field-label">¿Por qué se perdió? <span style={{ color: 'var(--danger)' }}>*</span></label>
                <div className="reason-grid">
                  {LOSS_REASONS.map(r => (
                    <div className={`reason-opt${reason === r.id ? ' sel' : ''}`} key={r.id} onClick={() => setReason(r.id)}>
                      <i className={`mdi ${r.icon}`}></i>{r.label}
                    </div>
                  ))}
                </div>
              </div>
              <div>
                <label className="field-label">Detalle (opcional)</label>
                <textarea className="fld" rows={2} placeholder="Cuéntanos qué pasó para mejorar el seguimiento" value={note} onChange={e => setNote(e.target.value)}></textarea>
              </div>
            </>
          )}
        </div>

        <div className="modal-foot">
          <button className="btn" onClick={onClose}>Cancelar</button>
          <button
            className={`btn ${isWin ? 'btn-win' : 'btn-lose'}`}
            disabled={!isWin && !reason}
            style={!isWin && !reason ? { opacity: 0.5, cursor: 'not-allowed' } : {}}
            onClick={() => {
              if (isWin || reason) {
                onConfirm(op.id, mode!, {
                  reason,
                  reason_label: LOSS_REASONS.find(r => r.id === reason)?.label,
                  note,
                });
              }
            }}
          >
            <i className={`mdi ${isWin ? 'mdi-trophy-variant' : 'mdi-close-octagon'}`}></i>
            {isWin ? 'Confirmar ganada' : 'Confirmar perdida'}
          </button>
        </div>
      </div>
    </div>
  );
}
