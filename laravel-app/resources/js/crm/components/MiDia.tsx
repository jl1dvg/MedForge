import React from 'react';
import type { OpportunityView } from '../types';
import { relTime, nextActionState } from '../helpers';

interface DayItemProps {
  op: OpportunityView;
  bucket: string;
  onOpen: (id: number) => void;
  onQuick: (op: OpportunityView, kind: string) => void;
  onAdvance: (id: number) => void;
}

function DayItem({ op, bucket, onOpen, onQuick, onAdvance }: DayItemProps) {
  const na = op.proxima_accion;
  const st = nextActionState(op);
  const taskText = bucket === 'cobertura'
    ? 'Dar seguimiento a la autorización'
    : bucket === 'agenda'
    ? 'Confirmar asistencia y recordar la cita'
    : (na?.label || 'Dar seguimiento');
  const whenIso = na?.due_at;

  return (
    <div className="day-item">
      <div className="day-item-top" onClick={() => onOpen(op.id)}>
        <span className="di-av">{op.initials}</span>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className="di-name">{op.full_name}</div>
          <div className="di-task">{taskText}</div>
          <div className="di-meta">
            <span>{op.procedimiento_short}</span>
            <span>·</span>
            <span>{op.afiliacion_label}</span>
          </div>
        </div>
        <span className={`di-when estado-${st}`}>{relTime(whenIso)}</span>
      </div>
      <div className="day-actions">
        <button className="qa-btn qa-wa" onClick={() => onQuick(op, 'whatsapp')}>
          <i className="mdi mdi-whatsapp"></i>Escribir
        </button>
        <button className="qa-btn" onClick={() => onQuick(op, 'llamada')}>
          <i className="mdi mdi-phone-outline"></i>Llamar
        </button>
        <button className="qa-btn qa-primary" onClick={() => onAdvance(op.id)}>
          <i className="mdi mdi-check"></i>Hecho
        </button>
      </div>
    </div>
  );
}

const DAY_BUCKETS = [
  { key: 'vencida', label: 'Atrasadas — atender ya', icon: 'mdi-alert-circle-outline', tone: 'vencida' },
  { key: 'hoy', label: 'Para hoy', icon: 'mdi-clock-outline', tone: 'hoy' },
  { key: 'cobertura', label: 'Esperando autorización', icon: 'mdi-shield-clock-outline', tone: 'cobertura' },
  { key: 'agenda', label: 'Citas por confirmar', icon: 'mdi-calendar-check-outline', tone: 'agenda' },
];

interface MiDiaProps {
  ops: OpportunityView[];
  onOpen: (id: number) => void;
  onQuick: (op: OpportunityView, kind: string) => void;
  onAdvance: (id: number) => void;
}

export function MiDia({ ops, onOpen, onQuick, onAdvance }: MiDiaProps) {
  const active = ops.filter(o => o.stage !== 'ganado' && o.stage !== 'perdido');

  const buckets: Record<string, OpportunityView[]> = { vencida: [], hoy: [], cobertura: [], agenda: [] };
  active.forEach(op => {
    if (op.stage === 'propuesta') { buckets.cobertura.push(op); return; }
    if (op.stage === 'comprometido') { buckets.agenda.push(op); return; }
    const st = nextActionState(op);
    if (st === 'vencida') buckets.vencida.push(op);
    else if (st === 'hoy') buckets.hoy.push(op);
  });

  Object.values(buckets).forEach(arr =>
    arr.sort((a, b) =>
      new Date(a.proxima_accion?.due_at || 0).getTime() -
      new Date(b.proxima_accion?.due_at || 0).getTime()
    )
  );

  const totalToday = buckets.vencida.length + buckets.hoy.length;

  return (
    <div className="miday">
      <div className="miday-hello">
        <h2>Mi día</h2>
        <p>
          Tienes <b style={{ color: 'var(--fg-1)' }}>{totalToday}</b> acciones para hoy
          · {buckets.cobertura.length} esperando seguro
          · {buckets.agenda.length} citas por confirmar
        </p>
      </div>
      <div className="miday-grid">
        {DAY_BUCKETS.map(b => {
          const list = buckets[b.key];
          return (
            <div className={`day-col tone-${b.tone}`} key={b.key}>
              <div className="day-col-head">
                <span className="dh-ic"><i className={`mdi ${b.icon}`}></i></span>
                <h3>{b.label}</h3>
                <span className="dh-n">{list.length}</span>
              </div>
              {list.length === 0 ? (
                <div className="day-empty">
                  <i className="mdi mdi-check-circle-outline"></i>
                  Nada pendiente aquí
                </div>
              ) : (
                <div className="day-list">
                  {list.map(op => (
                    <DayItem
                      key={op.id}
                      op={op}
                      bucket={b.key}
                      onOpen={onOpen}
                      onQuick={onQuick}
                      onAdvance={onAdvance}
                    />
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
