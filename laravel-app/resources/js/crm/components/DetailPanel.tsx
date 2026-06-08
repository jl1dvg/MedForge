import React, { useState, useEffect } from 'react';
import type { OpportunityView, Tarea, TimelineItem, Comunicacion } from '../types';
import { STAGES, STAGE_MAP } from '../stages';
import { fmtMoney, fmtDate, fmtDateTime, relTime, hexToSoft } from '../helpers';

function StageStepper({ op }: { op: OpportunityView }) {
  const active = STAGES.filter(s => s.slug !== 'perdido');
  const curIdx = active.findIndex(s => s.slug === op.stage);
  const lost = op.stage === 'perdido';
  return (
    <div className="ph-stagebar">
      {active.map((s, i) => {
        const done = !lost && i < curIdx;
        const current = !lost && i === curIdx;
        return (
          <div className={`ph-step${done ? ' done' : ''}${current ? ' current' : ''}`} key={s.slug}>
            <span className="dot"><i className={`mdi ${done ? 'mdi-check' : s.icon}`}></i></span>
            <span className="lbl">{s.short}</span>
            {i < active.length - 1 && <span className="connector"></span>}
          </div>
        );
      })}
    </div>
  );
}

function StageBadge({ slug }: { slug: string }) {
  const s = STAGE_MAP[slug];
  if (!s) return null;
  const bg = hexToSoft(s.color);
  return (
    <span className="t-stage" style={{ background: bg, color: s.color, fontSize: 11, padding: '2px 9px' }}>
      <i className={`mdi ${s.icon}`}></i>{s.label}
    </span>
  );
}

function TabResumen({ op, onQuick }: { op: OpportunityView; onQuick: (op: OpportunityView, kind: string) => void }) {
  const cov = op.cobertura;
  return (
    <>
      <div className="val-banner">
        <div>
          <div className="vb-l">Valor de la oportunidad</div>
          <div className="vb-v">
            {fmtMoney(op.cierre?.valor_final ?? op.valor)}
            {' '}<span style={{ fontSize: 14, color: 'var(--fg-3)', fontWeight: 500 }}>USD</span>
          </div>
        </div>
        {op.stage !== 'perdido' && (
          <div className="vb-prob">
            <div className="pp">{op.probabilidad}%</div>
            <div className="pl">probabilidad</div>
          </div>
        )}
      </div>
      <div className="psec">
        <div className="psec-title"><i className="mdi mdi-lightning-bolt-outline"></i>Acciones rápidas</div>
        <div className="qa-bar">
          <button className="qa-act qa-wa" onClick={() => onQuick(op, 'whatsapp')}><i className="mdi mdi-whatsapp"></i><span>WhatsApp</span></button>
          <button className="qa-act qa-call" onClick={() => onQuick(op, 'llamada')}><i className="mdi mdi-phone-outline"></i><span>Llamar</span></button>
          <button className="qa-act qa-cal" onClick={() => onQuick(op, 'agendar')}><i className="mdi mdi-calendar-plus"></i><span>Agendar</span></button>
          <button className="qa-act qa-quote" onClick={() => onQuick(op, 'cotizar')}><i className="mdi mdi-file-document-outline"></i><span>Cotizar</span></button>
        </div>
      </div>
      <div className="psec">
        <div className="psec-title"><i className="mdi mdi-eye-outline"></i>Procedimiento</div>
        <div className={`procbar tipo-${op.tipo}`}>
          <span className="pp-ic"><i className={`mdi ${op.proc_icon}`}></i></span>
          <div>
            <div className="pp-name">{op.procedimiento_short}{op.ojo ? ` · ${op.ojo}` : ''}</div>
            {op.diagnostico && <div className="pp-sub">{op.diagnostico}</div>}
          </div>
        </div>
      </div>
      <div className="psec">
        <div className="psec-title"><i className="mdi mdi-account-outline"></i>Paciente y origen</div>
        <div className="info-grid">
          <div className="info-item"><div className="k">Teléfono</div><div className="v link">{op.telefono}</div></div>
          <div className="info-item"><div className="k">Edad</div><div className="v">{op.edad}</div></div>
          <div className="info-item"><div className="k">Afiliación</div><div className="v">{op.afiliacion_label}</div></div>
          <div className="info-item"><div className="k">Sede</div><div className="v">{op.sede}</div></div>
          <div className="info-item"><div className="k">Médico</div><div className="v">{op.doctor}</div></div>
          <div className="info-item"><div className="k">Responsable CRM</div><div className="v">{op.responsable_name}</div></div>
          <div className="info-item"><div className="k">Origen</div><div className="v">{op.fuente_label}</div></div>
          <div className="info-item"><div className="k">HC</div><div className="v">{op.hc_number || '—'}</div></div>
        </div>
      </div>
      {cov.estado !== 'no_aplica' && (
        <div className="psec">
          <div className="psec-title"><i className="mdi mdi-shield-check-outline"></i>Cobertura</div>
          <div className="cov-card">
            <span className={`cov-state s-${cov.estado}`}>
              <i className={`mdi ${cov.estado === 'aprobada' ? 'mdi-check-decagram' : 'mdi-progress-clock'}`}></i>
              {cov.estado === 'aprobada' ? 'Autorización aprobada' : 'Autorización pendiente'}
            </span>
            <div className="cov-row"><span>Aseguradora</span><b>{cov.aseguradora}</b></div>
            {cov.codigo && <div className="cov-row"><span>Código</span><b>{cov.codigo}</b></div>}
            <div className="cov-row"><span>Estado</span><b>{cov.label}</b></div>
          </div>
        </div>
      )}
    </>
  );
}

const TL_ICON: Record<string, string> = {
  stage: 'mdi-arrow-right', prop: 'mdi-file-document-outline', cobertura: 'mdi-shield-check',
  won: 'mdi-trophy-variant', lost: 'mdi-close', call: 'mdi-phone', note: 'mdi-note-text-outline',
};

function TabSeguimiento({ op }: { op: OpportunityView }) {
  if (!op.timeline.length) return <div className="mini-empty"><i className="mdi mdi-timeline-text-outline"></i>Sin actividad registrada todavía.</div>;
  return (
    <div className="timeline">
      {[...op.timeline].reverse().map((t: TimelineItem, i: number) => (
        <div className="tl-item" key={i}>
          <span className={`tl-dot ${t.tipo}`}><i className={`mdi ${TL_ICON[t.tipo] || 'mdi-circle-medium'}`}></i></span>
          <div className="tl-body">
            <div className="tl-act">{t.txt}</div>
            <div className="tl-time">{t.by} · {fmtDateTime(t.at)}</div>
          </div>
        </div>
      ))}
    </div>
  );
}

function TabTareas({ op, onToggleTask, onAddTask }: { op: OpportunityView; onToggleTask: (id: number, idx: number) => void; onAddTask: (id: number, txt: string) => void }) {
  const [val, setVal] = useState('');
  const pend = op.tareas.filter((t: Tarea) => !t.done).length;
  return (
    <>
      <div className="psec-title"><i className="mdi mdi-format-list-checks"></i>Tareas<span className="psec-meta">{pend} pendientes</span></div>
      {op.tareas.length === 0 ? (
        <div className="mini-empty"><i className="mdi mdi-clipboard-check-outline"></i>Sin tareas. Añade un recordatorio abajo.</div>
      ) : (
        <div className="task-list">
          {op.tareas.map((t: Tarea, i: number) => (
            <div className={`task-row${t.done ? ' done' : ''}`} key={i} onClick={() => onToggleTask(op.id, i)}>
              <span className="task-check">{t.done && <i className="mdi mdi-check"></i>}</span>
              <div className="task-body">
                <div className="task-title">{t.titulo}</div>
                <div className="task-meta">
                  <span><i className="mdi mdi-calendar-outline"></i> {relTime(t.due)}</span>
                  <span className={`prio-tag prio-${t.prioridad}`}>{t.prioridad === 'alta' ? 'Alta' : 'Normal'}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
      <form className="add-form" onSubmit={e => { e.preventDefault(); if (val.trim()) { onAddTask(op.id, val.trim()); setVal(''); } }}>
        <input className="fld" placeholder="Nueva tarea…" value={val} onChange={e => setVal(e.target.value)} />
        <button className="btn-add" type="submit"><i className="mdi mdi-plus"></i>Añadir</button>
      </form>
    </>
  );
}

const QUICK_REPLIES = ['Confirmar cita', 'Enviar cotización', 'Recordar requisitos', 'Indicaciones pre-cirugía', 'Ubicación de la clínica'];

function TabComunicacion({ op, onSendCom }: { op: OpportunityView; onSendCom: (id: number, txt: string) => void }) {
  const [msg, setMsg] = useState('');
  return (
    <>
      <div className="psec-title"><i className="mdi mdi-message-text-outline"></i>Conversación<span className="psec-meta">{op.comunicaciones.length} mensajes</span></div>
      {op.comunicaciones.length === 0 ? (
        <div className="mini-empty"><i className="mdi mdi-message-outline"></i>Aún no hay comunicaciones con el paciente.</div>
      ) : (
        <div className="coms-list">
          {op.comunicaciones.map((c: Comunicacion, i: number) => (
            <div className={`com-row canal-${c.canal} dir-${c.dir}`} key={i}>
              <span className="com-ic"><i className={`mdi ${c.canal === 'whatsapp' ? 'mdi-whatsapp' : c.canal === 'llamada' ? 'mdi-phone' : 'mdi-email-outline'}`}></i></span>
              <div className="com-body">
                <div className="com-txt">{c.txt}</div>
                <div className="com-meta">{c.dir === 'out' ? c.by : 'Paciente'} · {fmtDateTime(c.at)}</div>
              </div>
            </div>
          ))}
        </div>
      )}
      <div style={{ marginTop: 16 }}>
        <div className="quick-replies">
          {QUICK_REPLIES.map(q => <button className="qr" key={q} onClick={() => setMsg(q + ': ')}>{q}</button>)}
        </div>
        <form className="add-form" onSubmit={e => { e.preventDefault(); if (msg.trim()) { onSendCom(op.id, msg.trim()); setMsg(''); } }}>
          <input className="fld" placeholder="Escribe un mensaje de WhatsApp…" value={msg} onChange={e => setMsg(e.target.value)} />
          <button className="btn-add" type="submit" style={{ background: '#1f9d7a', borderColor: '#1f9d7a' }}><i className="mdi mdi-send"></i>Enviar</button>
        </form>
      </div>
    </>
  );
}

function TabPropuesta({ op }: { op: OpportunityView }) {
  if (!op.propuesta) return <div className="mini-empty"><i className="mdi mdi-file-document-plus-outline"></i>Esta oportunidad aún no tiene cotización.</div>;
  const p = op.propuesta;
  const stateLbl: Record<string, string> = { enviada: 'Enviada', aceptada: 'Aceptada', rechazada: 'Rechazada', borrador: 'Borrador' };
  return (
    <>
      <div className="psec-title"><i className="mdi mdi-file-document-outline"></i>Cotización</div>
      <div className="prop-card">
        <div className="prop-head">
          <span className="prop-title">Paquete — {op.procedimiento_short}</span>
          <span className={`prop-state s-${p.estado}`}>{stateLbl[p.estado]}</span>
        </div>
        <div className="prop-items">
          {p.items.map((it, i) => (
            <div className="prop-item" key={i}>
              <span className="pi-cod">{it.cod}</span>
              <span className="pi-desc">{it.desc}</span>
              <span className="pi-val">{fmtMoney(it.cant * it.valor)}</span>
            </div>
          ))}
        </div>
        <div className="prop-tot">
          <div className="tr"><span>Subtotal</span><span>{fmtMoney(p.subtotal)}</span></div>
          <div className="tr"><span>IVA 15%</span><span>{fmtMoney(p.iva)}</span></div>
          <div className="tr total"><span>Total</span><b>{fmtMoney(p.total)}</b></div>
        </div>
        <div className="prop-foot"><span className="prop-vig"><i className="mdi mdi-calendar-clock"></i>Vigente hasta {fmtDate(p.vigencia)}</span></div>
      </div>
    </>
  );
}

const TABS = [
  { key: 'resumen', label: 'Resumen', icon: 'mdi-card-account-details-outline' },
  { key: 'seguimiento', label: 'Seguimiento', icon: 'mdi-timeline-text-outline' },
  { key: 'tareas', label: 'Tareas', icon: 'mdi-format-list-checks' },
  { key: 'comunicacion', label: 'Mensajes', icon: 'mdi-message-text-outline' },
  { key: 'propuesta', label: 'Cotización', icon: 'mdi-file-document-outline' },
];

export interface DetailPanelProps {
  op: OpportunityView | null;
  open: boolean;
  onClose: () => void;
  onAdvance: (id: number) => void;
  onToggleTask: (id: number, idx: number) => void;
  onAddTask: (id: number, txt: string) => void;
  onSendCom: (id: number, txt: string) => void;
  onQuick: (op: OpportunityView, kind: string) => void;
  onWin: (id: number) => void;
  onLose: (id: number) => void;
}

export function DetailPanel({ op, open, onClose, onAdvance, onToggleTask, onAddTask, onSendCom, onQuick, onWin, onLose }: DetailPanelProps) {
  const [tab, setTab] = useState('resumen');
  useEffect(() => { if (open) setTab('resumen'); }, [op?.id, open]);
  if (!op) return <div className={`panel-backdrop${open ? ' open' : ''}`} onClick={onClose}></div>;

  const closed = op.stage === 'ganado' || op.stage === 'perdido';
  const stageIdx = STAGES.findIndex(s => s.slug === op.stage);
  const nextStage = STAGES[stageIdx + 1];
  const counts: Record<string, number> = {
    tareas: op.tareas.filter((t: Tarea) => !t.done).length,
    comunicacion: op.comunicaciones.length,
  };

  return (
    <>
      <div className={`panel-backdrop${open ? ' open' : ''}`} onClick={onClose}></div>
      <aside className={`panel${open ? ' open' : ''}`}>
        <div className="panel-head">
          <div className="panel-head-top">
            <span className="ph-av">{op.initials}</span>
            <div className="ph-info">
              <h2>{op.full_name}</h2>
              <div className="ph-meta">
                <span>{op.hc_number ? `HC ${op.hc_number}` : 'Sin HC'}</span>
                <span>·</span>
                <StageBadge slug={op.stage} />
              </div>
            </div>
            <button className="panel-close" onClick={onClose}><i className="mdi mdi-close"></i></button>
          </div>
          <StageStepper op={op} />
        </div>
        <div className="panel-tabs">
          {TABS.map(t => (
            <button key={t.key} className={tab === t.key ? 'is-active' : ''} onClick={() => setTab(t.key)}>
              <i className={`mdi ${t.icon}`}></i>{t.label}
              {counts[t.key] > 0 && <span className="tb-n">{counts[t.key]}</span>}
            </button>
          ))}
        </div>
        <div className="panel-body">
          {tab === 'resumen' && <TabResumen op={op} onQuick={onQuick} />}
          {tab === 'seguimiento' && <TabSeguimiento op={op} />}
          {tab === 'tareas' && <TabTareas op={op} onToggleTask={onToggleTask} onAddTask={onAddTask} />}
          {tab === 'comunicacion' && <TabComunicacion op={op} onSendCom={onSendCom} />}
          {tab === 'propuesta' && <TabPropuesta op={op} />}
        </div>
        {!closed ? (
          <div className="panel-foot">
            <button className="btn btn-lose" onClick={() => onLose(op.id)}><i className="mdi mdi-close-octagon-outline"></i>Perder</button>
            {nextStage && nextStage.slug !== 'perdido' && nextStage.slug !== 'ganado' ? (
              <button className="btn btn-advance" onClick={() => onAdvance(op.id)}><i className="mdi mdi-arrow-right-bold"></i>Avanzar a {nextStage.label}</button>
            ) : (
              <button className="btn btn-win" onClick={() => onWin(op.id)}><i className="mdi mdi-trophy-variant-outline"></i>Marcar como ganada</button>
            )}
          </div>
        ) : (
          <div className="panel-foot">
            <button className="btn" style={{ flex: 1 }} onClick={onClose}><i className="mdi mdi-check"></i>Cerrar</button>
          </div>
        )}
      </aside>
    </>
  );
}
