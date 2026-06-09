import React, { useState, useEffect } from 'react';
import { AFILIACIONES, TEMPLATES, TABS, MOTIVOS_URGENTE } from './catalog';
import { fmtDate } from './helpers';

// ---- Modal shell ---------------------------------------------------
export function ModalShell({ size = 'md', icon, iconTone = 'primary', title, sub, onClose, children, footer }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const toneBg = {
    primary: { bg: 'var(--primary-fade)', c: 'var(--accent)' },
    danger:  { bg: '#fde2e7', c: 'var(--danger)' },
    success: { bg: '#e3f5ee', c: 'var(--success)' },
    warning: { bg: '#fff0d1', c: '#8a5d0a' },
  }[iconTone];

  return (
    <div className="imr-modal-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className={`imr-modal imr-modal-${size}`} role="dialog" aria-modal="true">
        <div className="imr-modal-head">
          {icon && <span className="imr-mh-ico" style={{ background: toneBg.bg, color: toneBg.c }}><i className={`mdi ${icon}`}></i></span>}
          <div>
            <h3>{title}</h3>
            {sub && <div className="imr-mh-sub">{sub}</div>}
          </div>
          <button className="imr-mh-close" onClick={onClose} aria-label="Cerrar"><i className="mdi mdi-close"></i></button>
        </div>
        <div className="imr-modal-body">{children}</div>
        {footer && <div className="imr-modal-foot">{footer}</div>}
      </div>
    </div>
  );
}

// ---- NAS file viewer -----------------------------------------------
function NasViewer({ row }) {
  const [files, setFiles] = useState(null); // null = loading, [] = empty
  const [idx, setIdx] = useState(0);
  const [error, setError] = useState(null);

  useEffect(() => {
    setFiles(null);
    setIdx(0);
    setError(null);
    if (!row?.form_id || !row?.hc_number) { setFiles([]); return; }
    const params = new URLSearchParams({ hc_number: row.hc_number, form_id: row.form_id });
    fetch(`/v2/imagenes/examenes-realizados/nas/list?${params}`, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => { setFiles(data.files || []); if (!data.success) setError(data.error || null); })
      .catch(() => { setFiles([]); setError('Error al cargar archivos del NAS.'); });
  }, [row?.form_id, row?.hc_number]);

  if (files === null) {
    return (
      <div className="imr-nas-stage">
        <div className="imr-nas-empty"><i className="mdi mdi-loading mdi-spin"></i> Cargando archivos…</div>
      </div>
    );
  }

  if (files.length === 0) {
    return (
      <div className="imr-nas-stage">
        <div className="imr-nas-empty">
          <i className="mdi mdi-folder-remove-outline"></i>
          {error || 'No se encontraron archivos asociados al examen en el NAS.'}
        </div>
      </div>
    );
  }

  const cur = files[idx];
  const isPdf = (cur.type || cur.name || '').toLowerCase().includes('pdf');
  const fileUrl = cur.url || '';

  return (
    <div className="imr-nas-panel">
      <div className="imr-nas-stage">
        <button className="imr-nas-nav imr-nas-prev" disabled={idx <= 0} onClick={() => setIdx((i) => Math.max(0, i - 1))} aria-label="Anterior">
          <i className="mdi mdi-chevron-left"></i>
        </button>
        {fileUrl && !isPdf ? (
          <img src={fileUrl} alt={cur.name} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain', borderRadius: 6 }} />
        ) : fileUrl && isPdf ? (
          <iframe src={fileUrl} title={cur.name} style={{ width: '100%', height: '100%', border: 'none', borderRadius: 6 }} />
        ) : (
          <div className="imr-nas-doc">
            <span className={`imr-nas-ftag ${isPdf ? 'pdf' : 'img'}`}>{isPdf ? 'PDF' : 'IMAGEN'}</span>
            <i className={`mdi ${isPdf ? 'mdi-file-pdf-box' : 'mdi-image-outline'}`}></i>
            <span className="imr-nas-fname">{cur.name}</span>
          </div>
        )}
        <button className="imr-nas-nav imr-nas-next" disabled={idx >= files.length - 1} onClick={() => setIdx((i) => Math.min(files.length - 1, i + 1))} aria-label="Siguiente">
          <i className="mdi mdi-chevron-right"></i>
        </button>
        <span className="imr-nas-counter">{idx + 1} / {files.length}</span>
      </div>
      <div style={{ display: 'flex', gap: 8, justifyContent: 'space-between', alignItems: 'center' }}>
        <span style={{ fontSize: 12, color: 'var(--fg-mute)' }}>{isPdf ? 'Documento PDF' : 'Imagen DICOM/JPG'}</span>
        {fileUrl && (
          <a className="imr-btn imr-btn-ghost imr-btn-sm" href={fileUrl} target="_blank" rel="noreferrer">
            <i className="mdi mdi-open-in-new"></i> Abrir archivo
          </a>
        )}
      </div>
      <div className="imr-nas-thumbs">
        {files.map((f, i) => {
          const fIsPdf = (f.type || f.name || '').toLowerCase().includes('pdf');
          return (
            <button key={i} className={`imr-nas-thumb ${i === idx ? 'active' : ''}`} onClick={() => setIdx(i)}>
              {f.url && !fIsPdf
                ? <img src={f.url} alt={f.name} style={{ width: '100%', height: 56, objectFit: 'cover', borderRadius: 4 }} />
                : <i className={`mdi ${fIsPdf ? 'mdi-file-pdf-box' : 'mdi-image-outline'} imr-th-ico`}></i>
              }
              <div className="imr-th-name">{f.name}</div>
            </button>
          );
        })}
      </div>
    </div>
  );
}

// ---- Patient data strip --------------------------------------------
function PatientStrip({ row }) {
  const afil = AFILIACIONES.find((a) => a.value === row.afiliacion);
  return (
    <div className="imr-pt-strip">
      <div className="imr-pt-item"><span className="imr-pt-k">Paciente</span><span className="imr-pt-v">{row.full_name}</span></div>
      <div className="imr-pt-item"><span className="imr-pt-k">Cédula / HC</span><span className="imr-pt-v" style={{ fontFamily: 'var(--font-mono)' }}>{row.cedula} · {row.hc_number}</span></div>
      <div className="imr-pt-item"><span className="imr-pt-k">Examen</span><span className="imr-pt-v">{row.tipo_label}</span></div>
      <div className="imr-pt-item"><span className="imr-pt-k">Ojo</span><span className="imr-pt-v">{row.ojo}</span></div>
      <div className="imr-pt-item"><span className="imr-pt-k">Fecha</span><span className="imr-pt-v">{fmtDate(row.fecha_examen)}</span></div>
      <div className="imr-pt-item"><span className="imr-pt-k">Afiliación</span><span className="imr-pt-v">{afil ? afil.label : row.afiliacion}</span></div>
      {row.equipo && <div className="imr-pt-item"><span className="imr-pt-k">Equipo</span><span className="imr-pt-v">{row.equipo}</span></div>}
    </div>
  );
}

// ---- WhatsApp notification block -----------------------------------
function NotifyBlock({ row, notify, setNotify }) {
  const labels = { enviado: 'Enviado', leido: 'Leído por el paciente', pendiente: 'Pendiente de envío', 'no-aplica': 'Sin enviar' };
  const status = row.informado ? (row.wpp_status || 'no-aplica') : 'no-aplica';
  return (
    <div className="imr-notify-box">
      <div className="imr-notify-head">
        <span className="imr-wa-ico"><i className="mdi mdi-whatsapp"></i></span>
        Aviso al paciente por WhatsApp
        {row.informado && status !== 'no-aplica' && (
          <span className={`imr-wa-status ${status}`} style={{ marginLeft: 'auto' }}>
            <i className={`mdi ${status === 'leido' ? 'mdi-check-all' : status === 'enviado' ? 'mdi-check' : 'mdi-clock-outline'}`}></i>
            {labels[status]}
          </span>
        )}
      </div>
      <p style={{ fontSize: 12.5, margin: 0, color: 'var(--fg-mute)' }}>
        Al guardar el informe se envía al paciente un mensaje avisando que su resultado está disponible.
      </p>
      <div className="imr-msg-preview">
        Hola {row.full_name.split(' ')[0]}, tu resultado de <b>{row.tipo_short}</b> ya está disponible.
        Puedes retirarlo en {row.sede} o solicitarlo por este medio. — MedForge · Consulmed
      </div>
      <div className="imr-toggle-row">
        <span>Enviar aviso al guardar</span>
        <label className="imr-switch">
          <input type="checkbox" checked={notify} onChange={(e) => setNotify(e.target.checked)} />
          <span className="imr-track"></span>
        </label>
      </div>
    </div>
  );
}

// ---- Findings checkbox grid ----------------------------------------
function ChecksGrid({ checks, vkey, vals, setVals, readOnly }) {
  const selected = vals[vkey] || [];
  const toggle = (item) => {
    if (readOnly) return;
    setVals((v) => {
      const arr = v[vkey] ? [...v[vkey]] : [];
      const idx = arr.indexOf(item);
      if (idx >= 0) arr.splice(idx, 1); else arr.push(item);
      return { ...v, [vkey]: arr };
    });
  };
  return (
    <div className="imr-checks-grid">
      {checks.map((item) => {
        const on = selected.includes(item);
        return (
          <button key={item} type="button"
            className={`imr-check-chip ${on ? 'on' : ''}`}
            onClick={() => toggle(item)}
            disabled={readOnly}>
            {on && <i className="mdi mdi-check" style={{ fontSize: 11, marginRight: 3 }}></i>}
            {item}
          </button>
        );
      })}
    </div>
  );
}

// ---- Campo renderer ------------------------------------------------
function CampoField({ c, prefix, vals, setVals, readOnly }) {
  const key = prefix ? `${prefix}_${c.k}` : c.k;
  return (
    <div className="imr-form-row" key={key}>
      <label>{c.label}</label>
      {c.type === 'select' ? (
        <select disabled={readOnly} value={vals[key] || ''} onChange={(e) => setVals((v) => ({ ...v, [key]: e.target.value }))}>
          <option value="">Seleccionar…</option>
          {c.opts.map((o) => <option key={o} value={o}>{o}</option>)}
        </select>
      ) : c.type === 'text' ? (
        <textarea disabled={readOnly} placeholder={c.ph} value={vals[key] || ''} onChange={(e) => setVals((v) => ({ ...v, [key]: e.target.value }))}></textarea>
      ) : (
        <input type="text" disabled={readOnly} placeholder={c.ph} value={vals[key] || ''} onChange={(e) => setVals((v) => ({ ...v, [key]: e.target.value }))} />
      )}
    </div>
  );
}

// ---- Modal: Informar examen ----------------------------------------
export function InformarModal({ row, readOnly, onClose, onSave, showToast, doctores }) {
  const tpl = TEMPLATES[row.tipo_key] || { titulo: row.tipo_label, campos: [], bilateral: false };
  const [vals, setVals] = useState({});
  const [notify, setNotify] = useState(true);
  const [auto, setAuto] = useState(false);
  const [saving, setSaving] = useState(false);

  const isAmbosOjos = /ambos/i.test(row.ojo || '');
  const showBilateral = tpl.bilateral && isAmbosOjos;
  const eyes = showBilateral ? ['od', 'oi'] : [null];
  const eyeLabels = { od: 'OD — Ojo Derecho', oi: 'OI — Ojo Izquierdo' };

  // Load existing informe on open
  useEffect(() => {
    if (!row.form_id) return;
    const params = new URLSearchParams({ form_id: row.form_id, tipo_examen: row.tipo_examen || row.tipo_label || '' });
    fetch(`/v2/imagenes/informes/datos?${params}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then((r) => r.json())
      .then((data) => { if (data.payload && typeof data.payload === 'object') setVals(data.payload); })
      .catch(() => {});
  }, [row.form_id]); // eslint-disable-line react-hooks/exhaustive-deps

  const normalFill = (prefix) => {
    setVals((v) => {
      const next = { ...v };
      tpl.campos.forEach((c) => {
        const key = prefix ? `${prefix}_${c.k}` : c.k;
        if (!next[key]) {
          if (c.type === 'num') next[key] = c.ph;
          else if (c.type === 'select') next[key] = c.opts[0];
          else next[key] = 'Dentro de parámetros normales.';
        }
      });
      return next;
    });
  };

  const autollenar = () => {
    const demo = {};
    eyes.forEach((prefix) => {
      tpl.campos.forEach((c) => {
        const key = prefix ? `${prefix}_${c.k}` : c.k;
        if (c.type === 'num') demo[key] = c.ph.replace(/[^\d.]/g, '') || '0';
        else if (c.type === 'select') demo[key] = c.opts[0];
        else demo[key] = 'Dentro de parámetros normales para la edad.';
      });
    });
    setVals(demo);
    showToast('Campos autollenados desde la imagen', 'mdi-auto-fix');
  };

  const handleSave = () => {
    setSaving(true);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('/v2/imagenes/informes/guardar', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({
        form_id: row.form_id,
        hc_number: row.hc_number,
        tipo_examen: row.tipo_examen || row.tipo_label || '',
        payload: vals,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success === false) throw new Error(data.error || 'Error al guardar');
        onSave(row, { notify, auto });
      })
      .catch((e) => { showToast(e.message || 'Error al guardar informe', 'mdi-alert', 'warn'); })
      .finally(() => setSaving(false));
  };

  const footer = (
    <>
      <span className="imr-foot-note">
        {readOnly
          ? `Informado por ${row.informado_por} · ${fmtDate(row.informado_fecha)}`
          : 'El informe quedará disponible para impresión y descarga.'}
      </span>
      <div className="imr-modal-spacer"></div>
      {!readOnly && (
        <>
          <button className={`imr-btn imr-btn-sm ${auto ? 'imr-btn-success' : 'imr-btn-ghost'}`} onClick={() => setAuto((a) => !a)} title="Avanzar automáticamente al siguiente examen al guardar">
            <i className="mdi mdi-fast-forward-outline"></i> Auto: {auto ? 'ON' : 'OFF'}
          </button>
          <button className="imr-btn imr-btn-outline-primary imr-btn-sm" onClick={autollenar}>
            <i className="mdi mdi-auto-fix"></i> Autollenar desde imagen
          </button>
        </>
      )}
      <button className="imr-btn imr-btn-ghost" onClick={onClose}>Cerrar</button>
      {!readOnly && (
        <button className="imr-btn imr-btn-primary" disabled={saving} onClick={handleSave}>
          <i className={`mdi ${saving ? 'mdi-loading mdi-spin' : 'mdi-content-save-outline'}`}></i> Guardar informe
        </button>
      )}
    </>
  );

  return (
    <ModalShell size="xl"
      icon={readOnly ? 'mdi-file-eye-outline' : 'mdi-file-document-edit-outline'}
      iconTone={readOnly ? 'success' : 'primary'}
      title={readOnly ? 'Informe del examen' : 'Informar examen'}
      sub={`${row.tipo_label} · ${row.ojo}`}
      onClose={onClose} footer={footer}>
      <PatientStrip row={row} />
      <div className="imr-informe-grid">
        <div>
          <h4 className="imr-section-title"><i className="mdi mdi-clipboard-text-outline"></i> Informe · {tpl.titulo} <span className="imr-section-ln"></span></h4>
          {tpl.campos.length === 0 && <p style={{ color: 'var(--fg-mute)' }}>Sin plantilla estructurada para este tipo. Usa el campo de conclusión.</p>}
          {showBilateral ? (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0 20px' }}>
              {eyes.map((prefix) => (
                <div key={prefix}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 10 }}>
                    <div className="imr-eye-section-label" style={{ margin: 0 }}>{eyeLabels[prefix]}</div>
                    {!readOnly && tpl.campos.length > 0 && (
                      <button type="button" className="imr-btn imr-btn-ghost imr-btn-sm" style={{ fontSize: 11 }} onClick={() => normalFill(prefix)}>
                        <i className="mdi mdi-check-all"></i> Normal
                      </button>
                    )}
                  </div>
                  {tpl.campos.map((c) => <CampoField key={c.k} c={c} prefix={prefix} vals={vals} setVals={setVals} readOnly={readOnly} />)}
                  {tpl.checks && <ChecksGrid checks={tpl.checks} vkey={`${prefix}_checks`} vals={vals} setVals={setVals} readOnly={readOnly} />}
                </div>
              ))}
            </div>
          ) : (
            <>
              {!readOnly && tpl.campos.length > 0 && (
                <div style={{ marginBottom: 10 }}>
                  <button type="button" className="imr-btn imr-btn-ghost imr-btn-sm" style={{ fontSize: 11 }} onClick={() => normalFill(null)}>
                    <i className="mdi mdi-check-all"></i> Normal
                  </button>
                </div>
              )}
              {tpl.campos.map((c) => <CampoField key={c.k} c={c} prefix={null} vals={vals} setVals={setVals} readOnly={readOnly} />)}
              {tpl.checks && <ChecksGrid checks={tpl.checks} vkey="checks" vals={vals} setVals={setVals} readOnly={readOnly} />}
            </>
          )}
          <div className="imr-form-row" style={{ marginTop: 8 }}>
            <label>Conclusión / impresión diagnóstica</label>
            <textarea disabled={readOnly} placeholder="Resumen e indicaciones para el médico tratante…" value={vals._concl || ''} onChange={(e) => setVals((v) => ({ ...v, _concl: e.target.value }))}></textarea>
          </div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div>
            <h4 className="imr-section-title"><i className="mdi mdi-folder-image"></i> Archivos del examen <span className="imr-section-ln"></span></h4>
            <NasViewer row={row} />
          </div>
          <NotifyBlock row={row} notify={notify} setNotify={setNotify} />
        </div>
      </div>
    </ModalShell>
  );
}

// ---- Modal: Ver imágenes -------------------------------------------
export function VerImagenesModal({ row, onClose }) {
  return (
    <ModalShell size="md" icon="mdi-folder-image" iconTone="primary"
      title="Imágenes del examen" sub={`${row.full_name} · ${row.tipo_short} · ${row.ojo}`}
      onClose={onClose}
      footer={
        <>
          <span className="imr-foot-note">{row.nas_files_count} archivo(s) en el NAS{row.equipo ? ` · ${row.equipo}` : ''}</span>
          <div className="imr-modal-spacer"></div>
          <button className="imr-btn imr-btn-ghost" onClick={onClose}>Cerrar</button>
        </>
      }>
      <NasViewer row={row} />
    </ModalShell>
  );
}

// ---- Modal: Marcar urgente / editar prioridad ----------------------
export function MarcarUrgenteModal({ rows, doctores, today, currentUser, onClose, onConfirm }) {
  const multi = rows.length > 1;
  const base = rows[0];
  const [prioridad, setPrioridad] = useState(base.prioridad || 'urgente');
  const [limite, setLimite] = useState(base.fecha_limite || today);
  const [resp, setResp] = useState(base.responsable || '');
  const [motivo, setMotivo] = useState(base.motivo || '');

  const footer = (
    <>
      <span className="imr-foot-note">Solicitado por {currentUser.name}</span>
      <div className="imr-modal-spacer"></div>
      <button className="imr-btn imr-btn-ghost" onClick={onClose}>Cancelar</button>
      <button className="imr-btn imr-btn-danger" disabled={!motivo.trim()} onClick={() => onConfirm(rows.map((r) => r.id), { prioridad, fecha_limite: limite, responsable: resp, motivo })}>
        <i className="mdi mdi-bell-plus-outline"></i> {base.prioridad ? 'Actualizar prioridad' : 'Enviar a bandeja'}
      </button>
    </>
  );

  return (
    <ModalShell size="sm" icon="mdi-bell-alert-outline" iconTone="danger"
      title={base.prioridad ? 'Editar prioridad' : 'Marcar para informe prioritario'}
      sub={multi ? `${rows.length} exámenes seleccionados` : `${base.full_name} · ${base.tipo_short}`}
      onClose={onClose} footer={footer}>
      <div className="imr-form-row">
        <label>Prioridad</label>
        <div className="imr-segmented">
          <button className={`imr-seg-opt ${prioridad === 'urgente' ? 'sel-urgente' : ''}`} onClick={() => setPrioridad('urgente')}>
            <i className="mdi mdi-fire"></i>
            <span><b>Urgente</b><small>Informar hoy mismo</small></span>
          </button>
          <button className={`imr-seg-opt ${prioridad === 'pronto' ? 'sel-pronto' : ''}`} onClick={() => setPrioridad('pronto')}>
            <i className="mdi mdi-clock-fast"></i>
            <span><b>Pronto</b><small>En los próximos días</small></span>
          </button>
        </div>
      </div>
      <div className="imr-form-2col">
        <div className="imr-form-row">
          <label>Informar antes de</label>
          <input type="date" value={limite} onChange={(e) => setLimite(e.target.value)} />
        </div>
        <div className="imr-form-row">
          <label>Médico responsable</label>
          <select value={resp} onChange={(e) => setResp(e.target.value)}>
            <option value="">Sin asignar</option>
            {(doctores || []).map((d) => <option key={d} value={d}>{d}</option>)}
          </select>
        </div>
      </div>
      <div className="imr-form-row">
        <label>Motivo / nota <span style={{ color: 'var(--danger)' }}>*</span></label>
        <textarea placeholder="¿Por qué requiere informe prioritario?" value={motivo} onChange={(e) => setMotivo(e.target.value)}></textarea>
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 4 }}>
          {MOTIVOS_URGENTE.slice(0, 3).map((m) => (
            <button key={m} className="imr-badge imr-badge-line" style={{ cursor: 'pointer' }} onClick={() => setMotivo(m)}>
              {m.length > 38 ? m.slice(0, 36) + '…' : m}
            </button>
          ))}
        </div>
      </div>
    </ModalShell>
  );
}

// ---- Modal: Help (cómo funciona) -----------------------------------
export function HelpModal({ onClose }) {
  const toneCls = { primary: 'imr-ft-primary', danger: 'imr-ft-danger', success: 'imr-ft-success', warning: 'imr-ft-warning' };
  return (
    <ModalShell size="md" icon="mdi-help-circle-outline" iconTone="primary"
      title="Cómo funciona Exámenes realizados"
      sub="El recorrido de una imagen, de la captura al informe"
      onClose={onClose}
      footer={<><div className="imr-modal-spacer"></div><button className="imr-btn imr-btn-primary" onClick={onClose}>Entendido</button></>}>
      <p style={{ marginTop: 0, color: 'var(--fg-mute)' }}>
        Cada examen de imagen recorre cuatro estados. Las pestañas son justamente esos estados —
        úsalas como bandejas de trabajo:
      </p>
      <div className="imr-flow-steps">
        {TABS.map((tb, i) => (
          <div className="imr-flow-card" key={tb.key}>
            <span className={`imr-ft-ico ${toneCls[tb.tone]}`}><i className={`mdi ${tb.icon}`}></i></span>
            <div>
              <h5 style={{ margin: '0 0 3px', fontSize: 14 }}>{i + 1}. {tb.label}</h5>
              <p style={{ margin: 0, fontSize: 12.5, color: 'var(--fg-3)', lineHeight: 1.5 }}>{tb.help}</p>
            </div>
          </div>
        ))}
      </div>
      <div className="imr-notify-box" style={{ marginTop: 8 }}>
        <div className="imr-notify-head">
          <span className="imr-wa-ico" style={{ background: 'var(--danger)' }}><i className="mdi mdi-bell-plus-outline"></i></span>
          Bandeja prioritaria
        </div>
        <p style={{ fontSize: 12.5, margin: 0, color: 'var(--fg-mute)' }}>
          Desde «Por informar» pulsa <b>Marcar urgente</b> en una fila, o selecciona varias y usa
          <b> Enviar a bandeja prioritaria</b>. Defines prioridad (Urgente/Pronto), fecha límite,
          médico responsable y motivo. Los casos con plazo vencido se resaltan en rojo.
        </p>
      </div>
    </ModalShell>
  );
}

// ---- Modal: Tab help -----------------------------------------------
export function TabHelpModal({ tabKey, onClose }) {
  const tb = TABS.find((t) => t.key === tabKey);
  if (!tb) return null;
  return (
    <ModalShell size="sm" icon={tb.icon} iconTone={tb.tone}
      title={`Pestaña «${tb.label}»`} sub={tb.desc}
      onClose={onClose}
      footer={<><div className="imr-modal-spacer"></div><button className="imr-btn imr-btn-primary" onClick={onClose}>Entendido</button></>}>
      <p style={{ marginTop: 0, lineHeight: 1.6 }}>{tb.help}</p>
    </ModalShell>
  );
}
