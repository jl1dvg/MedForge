import React from 'react';
import type { OpportunityView } from '../types';
import { fmtMoney, relTime, nextActionState } from '../helpers';

const ACTION_ICON: Record<string, string> = {
  llamada: 'mdi-phone-outline',
  whatsapp: 'mdi-whatsapp',
  cotizar: 'mdi-file-document-outline',
  cobertura: 'mdi-shield-check-outline',
  agenda: 'mdi-calendar-clock-outline',
  recordatorio: 'mdi-bell-ring-outline',
};

interface DndHandlers {
  draggingId: number | null;
  dropTarget: string | null;
  onDragStart: (e: React.DragEvent, op: OpportunityView) => void;
  onDragEnd: () => void;
}

interface OpCardProps {
  op: OpportunityView;
  onOpen: (id: number) => void;
  onQuick: (op: OpportunityView, kind: string) => void;
  dnd: DndHandlers;
}

function ProbBar({ value }: { value: number }) {
  return <span className="pbar"><i style={{ width: `${value}%` }}></i></span>;
}

export function OpCard({ op, onOpen, onQuick, dnd }: OpCardProps) {
  const closed = op.stage === 'ganado' || op.stage === 'perdido';
  const naState = nextActionState(op);

  return (
    <article
      className={`op-card temp-${op.temperatura}${closed ? ' is-closed ' + (op.stage === 'ganado' ? 'is-won' : 'is-lost') : ''}${dnd.draggingId === op.id ? ' is-dragging' : ''}`}
      draggable
      onDragStart={e => dnd.onDragStart(e, op)}
      onDragEnd={dnd.onDragEnd}
      onClick={() => onOpen(op.id)}
    >
      <div className="op-top">
        <span className="op-av">{op.initials}</span>
        <div className="op-id">
          <h6 className="op-name">{op.full_name}</h6>
          <div className="op-sub">
            <span>{op.hc_number ? `HC ${op.hc_number}` : 'Sin HC'}</span>
            {op.edad !== '—' && <><span className="sep">·</span><span>{op.edad} años</span></>}
          </div>
        </div>
        {op.prioridad === 'urgente' && <span className="op-urgent" title="Urgente"></span>}
      </div>

      <div className={`op-proc tipo-${op.tipo}`}>
        <span className="pi"><i className={`mdi ${op.proc_icon}`}></i></span>
        <div>
          <div className="pt">
            {op.procedimiento_short}
            {op.ojo && <span className="eye">{op.ojo}</span>}
          </div>
          {op.diagnostico && <div className="dx">{op.diagnostico}</div>}
        </div>
      </div>

      {!closed && (
        <div className="op-meta">
          {op.valor > 0 && <div className="op-value">{fmtMoney(op.valor)}<span className="cur"> USD</span></div>}
          <div className="op-prob" title={`Probabilidad ${op.probabilidad}%`}>
            <ProbBar value={op.probabilidad} />
            {op.probabilidad}%
          </div>
        </div>
      )}

      <div className="op-chips">
        <span className={`chip chip-afil tone-${op.afiliacion_tone}`}>
          <i className="mdi mdi-shield-account-outline"></i>
          {op.afiliacion_label}
        </span>
        <span className="chip chip-fuente">
          <i className={`mdi ${op.fuente_icon}`}></i>
          {op.fuente_label}
        </span>
      </div>

      {closed ? (
        <div className={`op-result ${op.stage === 'ganado' ? 'won' : 'lost'}`}>
          <i className={`mdi ${op.stage === 'ganado' ? 'mdi-trophy-variant' : 'mdi-close-octagon'}`}></i>
          {op.stage === 'ganado'
            ? `Ganada · ${fmtMoney(op.cierre?.valor_final ?? op.valor)}`
            : `Perdida · ${op.cierre?.motivo_label || '—'}`}
        </div>
      ) : op.proxima_accion ? (
        <div className={`op-next estado-${naState}`}>
          <i className={`mdi ${ACTION_ICON[op.proxima_accion.tipo] || 'mdi-arrow-right'}`}></i>
          <span className="nx-txt">{op.proxima_accion.label}</span>
          <span className="nx-when">{relTime(op.proxima_accion.due_at)}</span>
        </div>
      ) : null}

      <div className="op-foot">
        <span className="op-owner">
          <span className="ow-av">{op.responsable_name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}</span>
          <span className="ow-name">{op.responsable_name.split(' ').slice(-1)[0]}</span>
        </span>
        {!closed && (
          <div className="op-quick" onClick={e => e.stopPropagation()}>
            <button className="qa-call" title="Llamar" onClick={() => onQuick(op, 'llamada')}>
              <i className="mdi mdi-phone-outline"></i>
            </button>
            <button className="qa-wa" title="WhatsApp" onClick={() => onQuick(op, 'whatsapp')}>
              <i className="mdi mdi-whatsapp"></i>
            </button>
            <button className="qa-open" title="Abrir" onClick={() => onOpen(op.id)}>
              <i className="mdi mdi-arrow-expand"></i>
            </button>
          </div>
        )}
      </div>
    </article>
  );
}
