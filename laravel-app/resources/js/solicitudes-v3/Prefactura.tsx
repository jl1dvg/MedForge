// ============================================================
// MedForge · Solicitudes v3 — Modal Prefactura / Expediente
// 6 pestañas: Resumen · Caso · Cobertura · Cirugía · Agenda · Nota clínica
// ============================================================
import React, { useState, useEffect } from 'react';
import type { Solicitud } from './types';
import { fmtDate, fmtDateTime, fmtSla, SLA_META } from './components';

const PF_TABS = [
  { key: 'resumen',   label: 'Resumen',      icon: 'mdi-view-dashboard-outline'   },
  { key: 'caso',      label: 'Caso',          icon: 'mdi-clipboard-text-outline'   },
  { key: 'cobertura', label: 'Cobertura',     icon: 'mdi-shield-check-outline'     },
  { key: 'cirugia',   label: 'Cirugía',       icon: 'mdi-medical-bag'              },
  { key: 'agenda',    label: 'Agenda',        icon: 'mdi-calendar-clock-outline'   },
  { key: 'nota',      label: 'Nota clínica',  icon: 'mdi-stethoscope'              },
];

const COL_TONE: Record<string, string> = {
  'recibida': '#3d7ac7', 'llamado': '#3d7ac7',
  'revision-codigos': '#6f67d8', 'espera-documentos': '#6f67d8',
  'apto-oftalmologo': '#1f9d7a', 'apto-anestesia': '#1f9d7a',
  'listo-para-agenda': '#d59623', 'programada': '#d59623', 'completado': '#05825f',
};

const money = (n: number | null | undefined) =>
  '$' + Number(n || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ---- PfResumen -----------------------------------------------

function PfResumen({ sol, go }: { sol: Solicitud; go: (tab: string) => void }) {
  const det = sol.detalle;
  const preopDone = det.preop.filter((p) => p.done).length;
  const meta = SLA_META[sol.sla_status] ?? SLA_META.ok;
  const prop = det.propuestas[0];
  return (
    <>
      <div className="pf-kpis">
        <div className="pf-kpi">
          <div className={`pf-kpi-ic sla-${sol.sla_status}`}><i className={`mdi ${meta.icon}`}></i></div>
          <div><div className="pf-kpi-v">{fmtSla(sol)}</div><div className="pf-kpi-l">Estado SLA</div></div>
        </div>
        <div className="pf-kpi">
          <div className="pf-kpi-ic ok"><i className="mdi mdi-format-list-checks"></i></div>
          <div><div className="pf-kpi-v">{sol.checklist_progress.percent}%</div><div className="pf-kpi-l">Checklist operativo</div></div>
        </div>
        <div className="pf-kpi">
          <div className="pf-kpi-ic info"><i className="mdi mdi-medical-bag"></i></div>
          <div><div className="pf-kpi-v">{preopDone}/{det.preop.length}</div><div className="pf-kpi-l">Preoperatorio</div></div>
        </div>
        <div className="pf-kpi">
          <div className="pf-kpi-ic money"><i className="mdi mdi-cash-multiple"></i></div>
          <div><div className="pf-kpi-v">{prop ? money(prop.total) : '—'}</div><div className="pf-kpi-l">Propuesta</div></div>
        </div>
      </div>

      <div className="pf-section">
        <h4 className="pf-h">Próximo paso</h4>
        <div className="pf-next">
          <i className="mdi mdi-arrow-right-circle-outline"></i>
          <b>{sol.checklist_progress.next_label}</b>
          <span>· responsable {sol.crm.responsable}</span>
        </div>
      </div>

      {sol.alerts.length > 0 && (
        <div className="pf-section">
          <h4 className="pf-h">Alertas activas</h4>
          <div className="pf-alerts">
            {sol.alerts.map((a, i) => (
              <span key={i} className={`pf-alert tone-${a.tone}`}><i className={`mdi ${a.icon}`}></i>{a.label}</span>
            ))}
          </div>
        </div>
      )}

      <div className="pf-section">
        <h4 className="pf-h">Accesos rápidos</h4>
        <div className="pf-quick">
          <button onClick={() => go('caso')}><i className="mdi mdi-clipboard-text-outline"></i>Ver caso</button>
          <button onClick={() => go('cobertura')}><i className="mdi mdi-shield-check-outline"></i>Cobertura</button>
          <button onClick={() => go('agenda')}><i className="mdi mdi-calendar-clock-outline"></i>Ir a agenda</button>
          <button onClick={() => go('nota')}><i className="mdi mdi-stethoscope"></i>Nota clínica</button>
        </div>
      </div>
    </>
  );
}

// ---- PfCaso -------------------------------------------------

function PfCaso({ sol }: { sol: Solicitud }) {
  const det = sol.detalle;
  return (
    <>
      <div className="pf-section">
        <h4 className="pf-h">Procedimiento solicitado</h4>
        <div className="pf-proc"><span className="proto-eye">{sol.ojo}</span>{sol.procedimiento}</div>
      </div>
      <div className="pf-section">
        <h4 className="pf-h">Diagnósticos (CIE-10)</h4>
        <div className="pf-diags">
          {det.diagnosticos.length === 0 && <div style={{ color: 'var(--fg-mute)', fontSize: 12.5 }}>Sin diagnósticos registrados</div>}
          {det.diagnosticos.map((d, i) => (
            <div className="pf-diag" key={i}><span className="pf-cie">{d.cie}</span>{d.desc}</div>
          ))}
        </div>
      </div>
      <div className="pf-section">
        <h4 className="pf-h">Datos del paciente</h4>
        <div className="pf-grid">
          <div className="pf-f"><div className="k">Nombre</div><div className="v">{sol.full_name}</div></div>
          <div className="pf-f"><div className="k">Cédula</div><div className="v">{det.paciente.cedula}</div></div>
          <div className="pf-f"><div className="k">Edad / sexo</div><div className="v">{det.paciente.edad} años · {det.paciente.sexo}</div></div>
          <div className="pf-f"><div className="k">Teléfono</div><div className="v">{sol.crm.telefono}</div></div>
          <div className="pf-f"><div className="k">Dirección</div><div className="v">{det.paciente.direccion}</div></div>
          <div className="pf-f"><div className="k">Afiliación</div><div className="v">{sol.afiliacion_label}</div></div>
        </div>
      </div>
      {sol.observacion && (
        <div className="pf-section">
          <h4 className="pf-h">Observación</h4>
          <div className="pf-obs"><i className="mdi mdi-message-text-outline"></i>{sol.observacion}</div>
        </div>
      )}
    </>
  );
}

// ---- PfCobertura --------------------------------------------

function PfCobertura({ sol, showToast }: { sol: Solicitud; showToast: (msg: string, icon?: string) => void }) {
  const der = sol.detalle.derivacion;
  if (!der.tiene) {
    return (
      <div className="pf-section">
        <div className="mini-empty">Paciente particular — no requiere derivación ni autorización de seguro.</div>
        <div className="pf-quick" style={{ marginTop: 14 }}>
          <button onClick={() => showToast('Generando proforma particular', 'mdi-cash')}><i className="mdi mdi-cash"></i>Generar proforma particular</button>
        </div>
      </div>
    );
  }
  return (
    <>
      <div className="pf-section">
        <div className="pf-cover-banner">
          <div>
            <div className="pf-cover-title">{der.aseguradora}</div>
            <div className="pf-cover-sub">{der.plan} · Derivación #{der.cod}</div>
          </div>
          <span className={`conc-status ${der.vencida ? 'conc-warn' : 'conc-ok'}`}>
            <i className={`mdi ${der.vencida ? 'mdi-calendar-alert' : 'mdi-calendar-check'}`}></i>
            {der.vencida ? `Vencida hace ${Math.abs(der.dias_vigencia ?? 0)} días` : `Vigente · ${der.dias_vigencia} días`}
          </span>
        </div>
      </div>
      {der.autorizacion_pendiente && (
        <div className="pf-section">
          <div className="pf-auth-pending">
            <i className="mdi mdi-shield-clock-outline"></i>
            <div><b>Cobertura pendiente de autorización</b><span>Requiere autorización de la aseguradora antes de agendar.</span></div>
          </div>
        </div>
      )}
      <div className="pf-section">
        <h4 className="pf-h">Acciones de cobertura</h4>
        <div className="pf-quick">
          <button onClick={() => showToast('Descargando derivación (PDF)', 'mdi-file-download-outline')}><i className="mdi mdi-file-download-outline"></i>Descargar derivación</button>
          <button onClick={() => showToast('Re-scrapeando datos de cobertura…', 'mdi-sync')}><i className="mdi mdi-sync"></i>Re-scrapear</button>
          <button onClick={() => showToast('Correo de cobertura enviado', 'mdi-email-check-outline')}><i className="mdi mdi-email-fast-outline"></i>Correo de cobertura</button>
          {der.autorizacion_pendiente && (
            <button className="pf-q-primary" onClick={() => showToast('Solicitud de autorización enviada', 'mdi-shield-check')}>
              <i className="mdi mdi-shield-check"></i>Solicitar autorización
            </button>
          )}
        </div>
      </div>
    </>
  );
}

// ---- PfCirugia ----------------------------------------------

function PfCirugia({ sol, onTogglePreop }: { sol: Solicitud; onTogglePreop: (id: number, idx: number) => void }) {
  const preop = sol.detalle.preop;
  const done = preop.filter((p) => p.done).length;
  const pct = preop.length > 0 ? Math.round((done / preop.length) * 100) : 0;
  return (
    <div className="pf-section">
      <h4 className="pf-h">Checklist preoperatorio <span className="psec-meta">{done}/{preop.length} · {pct}%</span></h4>
      <div className="pf-progress"><div className="pf-progress-bar" style={{ width: pct + '%' }}></div></div>
      <div className="chk-list" style={{ marginTop: 14 }}>
        {preop.length === 0 && <div style={{ color: 'var(--fg-mute)', fontSize: 12.5, padding: 8 }}>Sin pasos preoperatorios registrados</div>}
        {preop.map((p, i) => (
          <div key={i} className={`chk-item ${p.done ? 'done' : ''}`} onClick={() => onTogglePreop(sol.id, i)}>
            <span className="chk-box">{p.done && <i className="mdi mdi-check"></i>}</span>
            <span className="chk-label">{p.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

// ---- PfAgenda -----------------------------------------------

function PfAgenda({ sol, showToast }: { sol: Solicitud; showToast: (msg: string, icon?: string) => void }) {
  const ag = sol.detalle.agenda;
  const programada = ['programada', 'completado'].includes(sol.estado);
  return (
    <>
      <div className="pf-section">
        <div className="pf-sigcenter-head">
          <span className="pf-sig-badge"><i className="mdi mdi-sync"></i>SIGCENTER</span>
          <span className={`conc-status ${programada ? 'conc-ok' : 'conc-none'}`}>{programada ? 'Programada' : 'Sin programar'}</span>
        </div>
      </div>
      <div className="pf-section">
        <h4 className="pf-h">Programación quirúrgica</h4>
        <div className="pf-grid">
          <div className="pf-f"><div className="k">Procedimiento</div><div className="v">{sol.procedimiento_short}</div></div>
          <div className="pf-f"><div className="k">Lateralidad</div><div className="v">{sol.ojo}</div></div>
          <div className="pf-f"><div className="k">Sala</div><div className="v">{ag.sala}</div></div>
          <div className="pf-f"><div className="k">Duración estimada</div><div className="v">{ag.duracion} min</div></div>
          <div className="pf-f"><div className="k">Anestesia</div><div className="v">{ag.anestesia}</div></div>
          <div className="pf-f"><div className="k">Cirujano</div><div className="v">{sol.doctor}</div></div>
          <div className="pf-f"><div className="k">Fecha programada</div><div className="v">{ag.fecha ? fmtDateTime(ag.fecha) : 'Por definir'}</div></div>
          <div className="pf-f"><div className="k">Sede</div><div className="v">{sol.sede}</div></div>
        </div>
      </div>
      <div className="pf-section">
        <div className="pf-quick">
          <button className="pf-q-primary" onClick={() => showToast(programada ? 'Reprogramación enviada a SIGCENTER' : 'Cirugía programada en SIGCENTER', 'mdi-calendar-check')}>
            <i className="mdi mdi-calendar-check"></i>{programada ? 'Reprogramar' : 'Programar cirugía'}
          </button>
          <button onClick={() => showToast('Sincronizando con SIGCENTER…', 'mdi-sync')}><i className="mdi mdi-sync"></i>Sincronizar</button>
        </div>
      </div>
    </>
  );
}

// ---- PfNota -------------------------------------------------

function PfNota({ sol, showToast }: { sol: Solicitud; showToast: (msg: string, icon?: string) => void }) {
  const ex = sol.detalle.examen;
  const [plan, setPlan] = useState(ex.plan);
  return (
    <>
      <div className="pf-section">
        <h4 className="pf-h">Examen físico</h4>
        <div className="pf-exam">
          <table className="pf-exam-table">
            <thead><tr><th></th><th>OD</th><th>OI</th></tr></thead>
            <tbody>
              <tr><td>Agudeza visual</td><td>{ex.av_od}</td><td>{ex.av_oi}</td></tr>
              <tr><td>PIO (mmHg)</td><td>{ex.pio_od}</td><td>{ex.pio_oi}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div className="pf-section">
        <h4 className="pf-h">Plan</h4>
        <textarea className="fld" rows={5} value={plan} onChange={(e) => setPlan(e.target.value)}></textarea>
        <button className="btn-add self-end" style={{ marginTop: 10 }} onClick={() => showToast('Nota clínica guardada', 'mdi-content-save-outline')}>
          <i className="mdi mdi-content-save-outline"></i>Guardar nota
        </button>
      </div>
    </>
  );
}

// ---- PrefacturaModal ----------------------------------------

export interface PrefacturaModalProps {
  sol: Solicitud | null;
  open: boolean;
  onClose: () => void;
  onTogglePreop: (id: number, idx: number) => void;
  showToast: (msg: string, icon?: string) => void;
}

export function PrefacturaModal({ sol, open, onClose, onTogglePreop, showToast }: PrefacturaModalProps) {
  const [tab, setTab] = useState('resumen');

  useEffect(() => { if (open) setTab('resumen'); }, [sol?.id, open]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    if (open) window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!sol) {
    return <div className={`pf-backdrop ${open ? 'open' : ''}`} onClick={onClose}></div>;
  }
  const tone = COL_TONE[sol.estado] ?? 'var(--accent)';

  return (
    <div className={`pf-backdrop ${open ? 'open' : ''}`} onClick={onClose}>
      <div className="pf-modal" onClick={(e) => e.stopPropagation()}>
        <header className="pf-head">
          <span className="pf-head-ic"><i className="mdi mdi-file-document-multiple-outline"></i></span>
          <div className="pf-head-info">
            <h2>Expediente del caso · Prefactura</h2>
            <div className="pf-head-meta">{sol.full_name} · HC {sol.hc_number} · {sol.form_id} · <span style={{ color: tone, fontWeight: 600 }}>{sol.estado_label}</span></div>
          </div>
          <button className="panel-close" onClick={onClose} aria-label="Cerrar"><i className="mdi mdi-close"></i></button>
        </header>
        <div className="pf-shell">
          <nav className="pf-rail">
            {PF_TABS.map((tb) => (
              <button key={tb.key} className={tab === tb.key ? 'is-active' : ''} onClick={() => setTab(tb.key)}>
                <i className={`mdi ${tb.icon}`}></i><span>{tb.label}</span>
              </button>
            ))}
          </nav>
          <div className="pf-content">
            {tab === 'resumen'   && <PfResumen sol={sol} go={setTab} />}
            {tab === 'caso'      && <PfCaso sol={sol} />}
            {tab === 'cobertura' && <PfCobertura sol={sol} showToast={showToast} />}
            {tab === 'cirugia'   && <PfCirugia sol={sol} onTogglePreop={onTogglePreop} />}
            {tab === 'agenda'    && <PfAgenda sol={sol} showToast={showToast} />}
            {tab === 'nota'      && <PfNota sol={sol} showToast={showToast} />}
          </div>
        </div>
      </div>
    </div>
  );
}
