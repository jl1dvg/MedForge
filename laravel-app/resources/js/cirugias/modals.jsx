import React, { useState, useEffect } from 'react';
import { TABS, AuditPanel, afilOf } from './components';

// ---- Shell genérico --------------------------------------------
export function ModalShell({ size = 'md', icon, iconTone = 'primary', title, sub, onClose, children, footer }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const toneBg = {
    primary: { bg: 'var(--primary-fade)', c: 'var(--accent)' },
    danger: { bg: '#fde2e7', c: 'var(--danger)' },
    success: { bg: '#e3f5ee', c: 'var(--success)' },
    warning: { bg: '#fff0d1', c: '#8a5d0a' },
    cir: { bg: 'var(--cir-bg)', c: 'var(--cir-fg)' },
  }[iconTone] || { bg: 'var(--primary-fade)', c: 'var(--accent)' };

  return (
    <div className="modal-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className={`modal modal-${size}`} role="dialog" aria-modal="true">
        <div className="modal-head">
          {icon && <span className="mh-ico" style={{ background: toneBg.bg, color: toneBg.c }}><i className={`mdi ${icon}`} /></span>}
          <div>
            <h3>{title}</h3>
            {sub && <div className="mh-sub">{sub}</div>}
          </div>
          <button className="mh-close" onClick={onClose} aria-label="Cerrar"><i className="mdi mdi-close" /></button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  );
}

// ---- Protocol Modal --------------------------------------------
function ProtoBlock({ icon, title, children, full }) {
  return (
    <div className={`proto-block ${full ? 'full' : ''}`}>
      <div className="pb-head"><i className={`mdi ${icon}`} />{title}</div>
      <div className="pb-body">{children}</div>
    </div>
  );
}

function TextOrEmpty({ value, placeholder }) {
  if (value && String(value).trim()) return <div className="proto-text">{value}</div>;
  return <div className="proto-empty">{placeholder || 'Sin registrar'}</div>;
}

export function ProtocolModal({ row, endpoints, onClose, onRevisar, onPrintToggle }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    const params = new URLSearchParams({ form_id: row.form_id, hc_number: row.hc_number });
    fetch(`${endpoints.protocolo}?${params}`)
      .then((r) => r.json())
      .then((d) => {
        if (d.error) throw new Error(d.error);
        setData(d);
        setLoading(false);
      })
      .catch((e) => { setError(e.message); setLoading(false); });
  }, [row.form_id, row.hc_number, endpoints.protocolo]);

  const afil = afilOf(row.afiliacion_label || row.afiliacion);
  const auditStatus = data?.auditoria?.status || 'warning';
  const iconTone = auditStatus === 'error' ? 'danger' : auditStatus === 'ok' ? 'success' : 'warning';

  const buildAudit = () => {
    if (!data?.auditoria) return null;
    const { status, checks = [], summary = {} } = data.auditoria;
    return { status, checks, summary };
  };

  const staffRoles = data ? [
    ['Cirujano principal', data.staff?.['Cirujano principal']],
    ['Cirujano 2', data.staff?.['Cirujano 2']],
    ['Primer ayudante', data.staff?.['Primer ayudante']],
    ['Segundo ayudante', data.staff?.['Segundo ayudante']],
    ['Tercer ayudante', data.staff?.['Tercer ayudante']],
    ['Anestesiólogo', data.staff?.['Anestesiólogo']],
    ['Instrumentista', data.staff?.['Instrumentista']],
    ['Circulante', data.staff?.['Circulante']],
    ['Ayudante anestesia', data.staff?.['Ayudante anestesia']],
  ].filter(([, v]) => v && String(v).trim()) : [];

  const footer = (
    <>
      <span className="foot-note">
        {row.status === 1
          ? <span><i className="mdi mdi-check-circle" style={{ color: 'var(--success)' }} /> Revisado</span>
          : `Protocolo ${row.form_id} · pendiente de revisión`}
      </span>
      <div className="spacer" />
      {onPrintToggle && (
        <button className="btn btn-ghost" onClick={() => { onPrintToggle(row); onClose(); }}>
          <i className={`mdi ${row.printed ? 'mdi-printer-check' : 'mdi-printer'}`} />
          {row.printed ? 'Impreso' : 'Imprimir'}
        </button>
      )}
      <button className="btn btn-ghost" onClick={onClose}>Cerrar</button>
      {onRevisar && (
        <button className="btn btn-primary" onClick={() => { onClose(); onRevisar(row); }}>
          <i className="mdi mdi-clipboard-edit-outline" /> Revisar en wizard
        </button>
      )}
    </>
  );

  return (
    <ModalShell size="lg" icon="mdi-shield-search" iconTone={iconTone}
      title="Revisión de protocolo quirúrgico"
      sub={`${row.full_name} · ${row.membrete || ''} · ${row.lateralidad || ''}`}
      onClose={onClose} footer={footer}>

      {loading && (
        <div className="tbl-loading">
          <div className="spin" />
          <div>Cargando protocolo…</div>
        </div>
      )}
      {error && <div className="tbl-error"><i className="mdi mdi-alert-circle-outline" /> {error}</div>}

      {!loading && !error && data && (
        <>
          {/* Auditoría */}
          {buildAudit() && <AuditPanel audit={buildAudit()} />}

          {/* Tira de paciente */}
          <div className="pt-strip">
            <div className="pt-item"><span className="k">Paciente</span><span className="v">{row.full_name}</span></div>
            <div className="pt-item"><span className="k">HC</span><span className="v mono">{row.hc_number}</span></div>
            {row.edad != null && <div className="pt-item"><span className="k">Edad</span><span className="v">{row.edad} años</span></div>}
            <div className="pt-item"><span className="k">Afiliación</span><span className="v">{afil ? afil.label : (row.afiliacion_label || row.afiliacion)}</span></div>
            {row.sede && <div className="pt-item"><span className="k">Sede</span><span className="v">{row.sede}</span></div>}
            <div className="pt-item"><span className="k">Protocolo</span><span className="v mono">{row.form_id}</span></div>
            {row.cirujano_display && <div className="pt-item"><span className="k">Cirujano</span><span className="v">{row.cirujano_display}</span></div>}
            {row.revisado_por && <div className="pt-item"><span className="k">Revisado por</span><span className="v">{row.revisado_por}{row.revisado_fecha ? <><br /><span style={{fontWeight:400,fontSize:11,color:'var(--fg-mute)'}}>{row.revisado_fecha}</span></> : ''}</span></div>}
          </div>

          <div className="proto-grid">
            {/* Diagnósticos */}
            <ProtoBlock icon="mdi-clipboard-pulse-outline" title="Diagnósticos">
              <table className="mini-table">
                <thead><tr><th>CIE-10</th><th>Detalle</th></tr></thead>
                <tbody>
                  {(!data.diagnosticos || data.diagnosticos.length === 0) && (
                    <tr><td colSpan={2} className="proto-empty">Sin diagnósticos</td></tr>
                  )}
                  {(data.diagnosticos || []).map((d, i) => (
                    <tr key={i}><td className="mono">{d.cie10}</td><td>{d.detalle}</td></tr>
                  ))}
                </tbody>
              </table>
            </ProtoBlock>

            {/* Procedimientos */}
            <ProtoBlock icon="mdi-medical-bag" title="Procedimientos">
              <table className="mini-table">
                <thead><tr><th>Código</th><th>Procedimiento</th></tr></thead>
                <tbody>
                  {(!data.procedimientos || data.procedimientos.length === 0) && (
                    <tr><td colSpan={2} className="proto-empty">Sin procedimientos</td></tr>
                  )}
                  {(data.procedimientos || []).map((p, i) => (
                    <tr key={i}><td className="mono">{p.codigo}</td><td>{p.nombre}</td></tr>
                  ))}
                </tbody>
              </table>
            </ProtoBlock>

            {/* Tiempos */}
            <ProtoBlock icon="mdi-clock-outline" title="Tiempos y anestesia">
              <dl className="kv-rows">
                <dt>Fecha</dt><dd>{data.fecha_inicio || '—'}</dd>
                <dt>Hora inicio</dt><dd>{data.hora_inicio || '—'}</dd>
                <dt>Hora fin</dt><dd>{data.hora_fin || '—'}</dd>
                <dt>Duración</dt><dd>{data.duracion || '—'}</dd>
                <dt>Anestesia</dt><dd>{data.tipo_anestesia || data.anestesia || '—'}</dd>
              </dl>
            </ProtoBlock>

            {/* Staff */}
            <ProtoBlock icon="mdi-account-group-outline" title="Equipo quirúrgico">
              <table className="mini-table">
                <tbody>
                  {staffRoles.length === 0 && <tr><td className="proto-empty">Sin equipo registrado</td></tr>}
                  {staffRoles.map(([rol, nom], i) => (
                    <tr key={i}>
                      <td style={{ color: 'var(--fg-mute)' }}>{rol}</td>
                      <td style={{ fontWeight: 600 }}>{nom}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </ProtoBlock>

            {/* Acto operatorio */}
            <ProtoBlock icon="mdi-scalpel" title="Procedimiento realizado" full>
              <dl className="kv-rows" style={{ marginBottom: 10 }}>
                <dt>Proyectado</dt><dd>{data.procedimiento_proyectado || '—'}</dd>
                <dt>Realizado</dt><dd>{data.membrete || '—'}</dd>
              </dl>
              <div style={{ marginBottom: 8 }}>
                <div className="section-title" style={{ margin: '0 0 5px' }}>Descripción operatoria <span className="ln" /></div>
                <TextOrEmpty value={data.operatorio} placeholder="Sin descripción operatoria" />
              </div>
              <dl className="kv-rows">
                <dt>Hallazgo</dt><dd style={{ fontWeight: 400 }}>{data.hallazgo || '—'}</dd>
                <dt>Complicaciones</dt><dd style={{ fontWeight: 400 }}>{data.complicaciones_operatorio || '—'}</dd>
              </dl>
            </ProtoBlock>

            {/* Insumos */}
            {(() => {
              const insumos = data.insumos
                ? [...(data.insumos.equipos || []), ...(data.insumos.quirurgicos || []), ...(data.insumos.anestesia || [])]
                : (Array.isArray(data.insumos_list) ? data.insumos_list : []);
              return (
                <ProtoBlock icon="mdi-package-variant-closed" title={`Insumos (${insumos.length})`}>
                  <table className="mini-table">
                    <tbody>
                      {insumos.length === 0 && <tr><td className="proto-empty">Sin insumos registrados</td></tr>}
                      {insumos.map((it, i) => (
                        <tr key={i}><td>{it.nombre || it.name}</td><td style={{ textAlign: 'right', color: 'var(--fg-mute)' }}>×{it.cantidad || it.qty || 1}</td></tr>
                      ))}
                    </tbody>
                  </table>
                </ProtoBlock>
              );
            })()}

            {/* Medicamentos */}
            <ProtoBlock icon="mdi-pill" title={`Medicamentos (${(data.medicamentos || []).length})`}>
              <table className="mini-table">
                <tbody>
                  {(!data.medicamentos || data.medicamentos.length === 0) && <tr><td className="proto-empty">Sin medicamentos</td></tr>}
                  {(data.medicamentos || []).map((m, i) => (
                    <tr key={i}><td>{m.nombre || m.name}</td><td style={{ textAlign: 'right', color: 'var(--fg-mute)' }}>{m.via}</td></tr>
                  ))}
                </tbody>
              </table>
            </ProtoBlock>
          </div>
        </>
      )}
    </ModalShell>
  );
}

// ---- Certificado de descanso -----------------------------------
export function CertificadoModal({ row, endpoints, onClose }) {
  const [dias, setDias] = useState('5');
  const valido = Number.isFinite(+dias) && +dias > 0;

  const hastaDate = (() => {
    const d = new Date();
    d.setDate(d.getDate() + (+dias || 0) - 1);
    return d.toISOString().slice(0, 10);
  })();

  const fmtDate = (iso) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}-${m}-${y}`;
  };

  const confirm = () => {
    if (!valido) return;
    const p = new URLSearchParams({ form_id: row.form_id, hc_number: row.hc_number, dias_descanso: String(+dias) });
    window.open(`/v2/reports/cirugias/descanso/pdf?${p}`, '_blank');
    onClose();
  };

  const today = new Date().toISOString().slice(0, 10);

  const footer = (
    <>
      <span className="foot-note">Reposo desde {fmtDate(today)} hasta {fmtDate(hastaDate)}</span>
      <div className="spacer" />
      <button className="btn btn-ghost" onClick={onClose}>Cancelar</button>
      <button className="btn btn-primary" disabled={!valido} onClick={confirm}>
        <i className="mdi mdi-file-certificate-outline" /> Emitir certificado
      </button>
    </>
  );

  return (
    <ModalShell size="sm" icon="mdi-file-certificate-outline" iconTone="cir"
      title="Certificado de descanso médico"
      sub={`${row.full_name} · ${row.membrete || row.form_id}`}
      onClose={onClose} footer={footer}>
      <div className="pt-strip" style={{ marginBottom: 14 }}>
        <div className="pt-item"><span className="k">Paciente</span><span className="v">{row.full_name}</span></div>
        <div className="pt-item"><span className="k">HC</span><span className="v mono">{row.hc_number}</span></div>
        <div className="pt-item"><span className="k">Cirugía</span><span className="v">{row.fecha_inicio || ''}</span></div>
      </div>
      <div className="form-row" style={{ maxWidth: 220 }}>
        <label>Días de reposo</label>
        <input type="number" min="1" max="90" value={dias}
          onChange={(e) => setDias(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter') confirm(); }}
          autoFocus />
        <span className="hint">Se emite un PDF con membrete de Consulmed.</span>
      </div>
      {!valido && <div className="proto-empty" style={{ color: 'var(--danger)' }}>Ingresa un número de días mayor a cero.</div>}
    </ModalShell>
  );
}

// ---- Cómo funciona ---------------------------------------------
export function HelpModal({ onClose }) {
  const toneCls = { primary: 'ft-primary', danger: 'ft-danger', success: 'ft-success', warning: 'ft-warning' };
  const footer = (
    <><div className="spacer" /><button className="btn btn-primary" onClick={onClose}>Entendido</button></>
  );
  return (
    <ModalShell size="md" icon="mdi-help-circle-outline" iconTone="primary"
      title="Cómo funciona Reporte de protocolos"
      sub="El recorrido de una cirugía, del acto quirúrgico al protocolo firmado"
      onClose={onClose} footer={footer}>
      <p className="txt-muted" style={{ marginTop: 0 }}>
        Cada cirugía realizada debe quedar documentada en un protocolo quirúrgico. Las pestañas son los
        estados de ese protocolo — úsalas como bandejas de trabajo:
      </p>
      <div className="flow-steps">
        {TABS.map((tb, i) => (
          <div className="flow-tab-card" key={tb.key}>
            <span className={`ft-ico ${toneCls[tb.tone]}`}><i className={`mdi ${tb.icon}`} /></span>
            <div>
              <h5>{i + 1}. {tb.label}</h5>
              <p>{tb.help}</p>
            </div>
          </div>
        ))}
      </div>
    </ModalShell>
  );
}

// ---- Ayuda de pestaña ------------------------------------------
export function TabHelpModal({ tabKey, onClose }) {
  const tb = TABS.find((t) => t.key === tabKey);
  if (!tb) return null;
  const footer = (
    <><div className="spacer" /><button className="btn btn-primary" onClick={onClose}>Entendido</button></>
  );
  return (
    <ModalShell size="sm" icon={tb.icon} iconTone={tb.tone}
      title={`Pestaña «${tb.label}»`} sub={tb.desc} onClose={onClose} footer={footer}>
      <p style={{ marginTop: 0, lineHeight: 1.6 }}>{tb.help}</p>
    </ModalShell>
  );
}
