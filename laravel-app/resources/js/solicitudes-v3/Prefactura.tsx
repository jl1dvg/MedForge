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

const APTITUD_ORDER = [
  'recibida',
  'llamado',
  'revision-codigos',
  'espera-documentos',
  'apto-oftalmologo',
  'apto-anestesia',
  'listo-para-agenda',
  'programada',
  'completado',
];

function isAtOrAfter(estado: string, target: string): boolean {
  const currentIdx = APTITUD_ORDER.indexOf(estado);
  const targetIdx = APTITUD_ORDER.indexOf(target);
  return currentIdx >= 0 && targetIdx >= 0 && currentIdx >= targetIdx;
}

function approvalStatus(sol: Solicitud, slug: 'apto-oftalmologo' | 'apto-anestesia') {
  const realStep = sol.detalle.preop.find((step) => step.slug === slug);
  const done = realStep?.done === true
    || (slug === 'apto-oftalmologo' && isAtOrAfter(sol.estado, 'apto-anestesia'))
    || (slug === 'apto-anestesia' && isAtOrAfter(sol.estado, 'listo-para-agenda'));
  const isCurrentStation = sol.estado === slug && !done;

  return {
    done,
    isCurrentStation,
    badge: done ? 'Apto' : 'Pendiente',
    tone: done ? 'ok' : isCurrentStation ? 'warn' : 'pending',
    current: realStep?.label ?? (slug === 'apto-anestesia' ? 'Preanestesia' : 'Evaluación oftalmológica'),
  };
}

// ---- PfResumen -----------------------------------------------

function PfResumen({ sol, go }: { sol: Solicitud; go: (tab: string) => void }) {
  const det = sol.detalle;
  const aptoOftalmo = approvalStatus(sol, 'apto-oftalmologo');
  const aptoAnestesia = approvalStatus(sol, 'apto-anestesia');
  const aptitudDone = [aptoOftalmo, aptoAnestesia].filter((item) => item.done).length;
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
          <div><div className="pf-kpi-v">{aptitudDone}/2</div><div className="pf-kpi-l">Aptitud clínica</div></div>
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
  const planAfiliacion = sol.plan_seguro !== '—' ? sol.plan_seguro : sol.afiliacion_label;
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
          <div className="pf-f"><div className="k">HC / Cédula</div><div className="v">{det.paciente.cedula}</div></div>
          <div className="pf-f"><div className="k">Edad / sexo</div><div className="v">{det.paciente.edad > 0 ? `${det.paciente.edad} años` : '—'} · {det.paciente.sexo}</div></div>
          <div className="pf-f"><div className="k">Teléfono</div><div className="v">{det.paciente.telefono !== '—' ? det.paciente.telefono : (sol.crm.telefono !== '—' ? sol.crm.telefono : '—')}</div></div>
          <div className="pf-f"><div className="k">Dirección</div><div className="v">{det.paciente.direccion}</div></div>
          <div className="pf-f"><div className="k">Plan afiliación</div><div className="v">{planAfiliacion}</div></div>
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

function PfCobertura({
  sol,
  onRescrapeDerivacion,
}: {
  sol: Solicitud;
  onRescrapeDerivacion: (id: number) => Promise<void>;
}) {
  const der = sol.detalle.derivacion;
  const [scraping, setScraping] = useState(false);
  const [scrapeError, setScrapeError] = useState<string | null>(null);
  const runScrape = async () => {
    if (scraping) return;
    setScraping(true);
    setScrapeError(null);
    try {
      await onRescrapeDerivacion(sol.id);
    } catch (err) {
      setScrapeError(err instanceof Error ? err.message : 'No se pudo re-scrapear la derivación.');
    } finally {
      setScraping(false);
    }
  };
  if (!der.tiene) {
    return (
      <div className="pf-section">
        <div className="mini-empty">Sin derivación registrada para esta solicitud.</div>
        <button className="btn-add full pf-rescrape-btn" type="button" disabled={scraping} onClick={() => void runScrape()}>
          <i className={`mdi ${scraping ? 'mdi-loading mdi-spin' : 'mdi-refresh'}`}></i>Re-scrapear derivación
        </button>
        {scrapeError && <div className="form-error" role="alert">{scrapeError}</div>}
      </div>
    );
  }
  const vigenciaStatus = der.vigencia_label || (der.vencida ? 'Vencida' : 'Vigente');
  return (
    <>
      <div className="pf-section">
        <div className="pf-cover-banner">
          <div>
            <div className="pf-cover-title">{der.aseguradora}</div>
            <div className="pf-cover-sub">{der.plan} · Derivación #{der.cod ?? '—'}</div>
          </div>
          <span className={`conc-status ${der.vencida ? 'conc-warn' : 'conc-ok'}`}>
            <i className={`mdi ${der.vencida ? 'mdi-calendar-alert' : 'mdi-calendar-check'}`}></i>
            {vigenciaStatus}
          </span>
        </div>
      </div>
      <div className="pf-section">
        <h4 className="pf-h">Datos de derivación</h4>
        <div className="pf-grid">
          <div className="pf-f"><div className="k">Código</div><div className="v">{der.cod ?? '—'}</div></div>
          <div className="pf-f"><div className="k">Fecha registro</div><div className="v">{der.fecha_registro ? fmtDate(der.fecha_registro) : '—'}</div></div>
          <div className="pf-f"><div className="k">Fecha vigencia</div><div className="v">{der.fecha_vigencia ? fmtDate(der.fecha_vigencia) : '—'}</div></div>
          <div className="pf-f"><div className="k">Archivo</div><div className="v">{der.archivo_href ? <a href={der.archivo_href} target="_blank" rel="noreferrer">Ver derivación</a> : 'Sin archivo'}</div></div>
          <div className="pf-f" style={{ gridColumn: '1 / -1' }}><div className="k">Estado de vigencia</div><div className="v">{der.vigencia_text}</div></div>
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
    </>
  );
}

// ---- PfCirugia ----------------------------------------------

function PfCirugia({ sol }: { sol: Solicitud }) {
  const aptoOftalmo = approvalStatus(sol, 'apto-oftalmologo');
  const aptoAnestesia = approvalStatus(sol, 'apto-anestesia');
  const approvals = [
    {
      title: 'Oftalmólogo',
      icon: 'mdi-eye-check-outline',
      status: aptoOftalmo,
      detail: aptoOftalmo.done
        ? 'Plan quirúrgico validado para continuar flujo.'
        : 'Marcar “Apto oftalmólogo” antes de enviar a anestesia.',
    },
    {
      title: 'Anestesia',
      icon: 'mdi-clipboard-pulse-outline',
      status: aptoAnestesia,
      detail: aptoAnestesia.done
        ? 'Paciente habilitado por anestesia para agenda.'
        : 'Confirmar preanestesia antes de liberar agenda quirúrgica.',
    },
  ];
  const done = approvals.filter((item) => item.status.done).length;
  const pct = Math.round((done / approvals.length) * 100);

  return (
    <>
      <div className="pf-section">
        <h4 className="pf-h">Aptitud clínica <span className="psec-meta">{done}/2 · {pct}%</span></h4>
        <div className="pf-progress"><div className="pf-progress-bar" style={{ width: pct + '%' }}></div></div>
        <div className="pf-approval-grid">
          {approvals.map((item) => (
            <div key={item.title} className={`pf-approval tone-${item.status.tone}`}>
              <div className="pf-approval-head">
                <span className="pf-approval-ic"><i className={`mdi ${item.icon}`}></i></span>
                <div>
                  <div className="pf-approval-title">{item.title}</div>
                  <div className="pf-approval-sub">{item.status.current}</div>
                </div>
                <span className="pf-approval-badge">{item.status.badge}</span>
              </div>
              <div className="pf-approval-body">{item.detail}</div>
              {item.status.isCurrentStation && (
                <div className="pf-approval-callout">
                  <i className="mdi mdi-timer-sand"></i>
                  Estación actual pendiente de aprobación.
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {sol.detalle.preop.length === 0 && (
        <div className="pf-section">
          <div className="mini-empty">Sin checklist preoperatorio registrado</div>
        </div>
      )}

      <div className="pf-section">
        <h4 className="pf-h">Datos quirúrgicos operativos</h4>
        <div className="pf-grid">
          <div className="pf-f"><div className="k">Procedimiento</div><div className="v">{sol.procedimiento_short}</div></div>
          <div className="pf-f"><div className="k">Ojo</div><div className="v">{sol.ojo}</div></div>
          <div className="pf-f"><div className="k">Anestesia prevista</div><div className="v">{sol.detalle.agenda.anestesia}</div></div>
          <div className="pf-f"><div className="k">Sede</div><div className="v">{sol.sede}</div></div>
          <div className="pf-f"><div className="k">Estado actual</div><div className="v">{sol.estado_label}</div></div>
          <div className="pf-f"><div className="k">Siguiente paso</div><div className="v">{sol.checklist_progress.next_label}</div></div>
        </div>
      </div>

      <div className="pf-section">
        <h4 className="pf-h">Requisitos para agenda</h4>
        <div className="pf-readiness">
          <div className={aptoOftalmo.done ? 'is-done' : ''}><i className="mdi mdi-check-circle-outline"></i>Apto oftalmólogo</div>
          <div className={aptoAnestesia.done ? 'is-done' : ''}><i className="mdi mdi-check-circle-outline"></i>Apto anestesia</div>
          <div className={sol.detalle.agenda.fecha ? 'is-done' : ''}><i className="mdi mdi-calendar-check-outline"></i>Agenda quirúrgica</div>
        </div>
      </div>
    </>
  );
}

// ---- PfAgenda -----------------------------------------------

function PfAgenda({ sol }: { sol: Solicitud }) {
  const ag = sol.detalle.agenda;
  const programada = !!ag.fecha || ['programada', 'completado'].includes(sol.estado);
  return (
    <>
      <div className="pf-section">
        <div className="pf-sigcenter-head">
          <span className="pf-sig-badge"><i className="mdi mdi-sync"></i>SIGCENTER</span>
          <span className={`conc-status ${programada ? 'conc-ok' : 'conc-none'}`}>{ag.origen}</span>
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
          <div className="pf-f"><div className="k">Cirujano</div><div className="v">{ag.doctor !== '—' ? ag.doctor : sol.doctor}</div></div>
          <div className="pf-f"><div className="k">Fecha programada</div><div className="v">{ag.fecha ? fmtDateTime(ag.fecha) : 'Por definir'}</div></div>
          <div className="pf-f"><div className="k">Fin bloqueo</div><div className="v">{ag.fecha_fin ? fmtDateTime(ag.fecha_fin) : '—'}</div></div>
          <div className="pf-f"><div className="k">Sede</div><div className="v">{sol.sede}</div></div>
          <div className="pf-f"><div className="k">Agenda SIGCENTER</div><div className="v">{ag.sigcenter_agenda_id ?? '—'}</div></div>
        </div>
      </div>
    </>
  );
}

// ---- PfNota -------------------------------------------------

function PfNota({ sol }: { sol: Solicitud; showToast: (msg: string, icon?: string) => void }) {
  const ex = sol.detalle.examen;
  const hasExamValues = ex.av_od !== '—' || ex.av_oi !== '—' || ex.pio_od !== 0 || ex.pio_oi !== 0;
  return (
    <>
      <div className="pf-section">
        <h4 className="pf-h">Examen físico</h4>
        <div className="pf-exam">
          <table className="pf-exam-table">
            <thead><tr><th></th><th>OD</th><th>OI</th></tr></thead>
            <tbody>
              <tr><td>Agudeza visual</td><td>{ex.av_od}</td><td>{ex.av_oi}</td></tr>
              <tr><td>PIO (mmHg)</td><td>{hasExamValues ? ex.pio_od : '—'}</td><td>{hasExamValues ? ex.pio_oi : '—'}</td></tr>
            </tbody>
          </table>
        </div>
        {ex.examen_fisico && (
          <div style={{ marginTop: 12 }}>
            <div className="pf-h" style={{ marginBottom: 6, fontSize: '0.72rem' }}>NOTA DEL EXAMEN</div>
            <textarea className="fld" rows={6} readOnly value={ex.examen_fisico}
              style={{ resize: 'vertical', cursor: 'default', background: 'var(--bg-2, #f8f9fb)' }} />
          </div>
        )}
      </div>
      {ex.plan && ex.plan !== '—' && (
        <div className="pf-section">
          <h4 className="pf-h">Plan</h4>
          <textarea className="fld" rows={6} readOnly value={ex.plan}
            style={{ resize: 'vertical', cursor: 'default', background: 'var(--bg-2, #f8f9fb)' }} />
        </div>
      )}
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
  onRescrapeDerivacion: (id: number) => Promise<void>;
}

export function PrefacturaModal({ sol, open, onClose, showToast, onRescrapeDerivacion }: PrefacturaModalProps) {
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
      <div className="pf-modal" onClick={(e: React.MouseEvent) => e.stopPropagation()}>
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
            {tab === 'cobertura' && <PfCobertura sol={sol} onRescrapeDerivacion={onRescrapeDerivacion} />}
            {tab === 'cirugia'   && <PfCirugia sol={sol} />}
            {tab === 'agenda'    && <PfAgenda sol={sol} />}
            {tab === 'nota'      && <PfNota sol={sol} showToast={showToast} />}
          </div>
        </div>
      </div>
    </div>
  );
}
