import React, { useState, useMemo, useCallback } from 'react';
import { AuditPanel } from './components';

// ---- Catalogs (mirrors data.js for backend-driven wizard) -------
const CIRUJANOS = {
  'Cirujano Oftalmólogo': [],
  'Anestesiólogo': [],
  'Asistente': [],
};
const TIPO_ANESTESIA = ['GENERAL', 'LOCAL', 'REGIONAL', 'SEDACIÓN', 'TÓPICA', 'PERIBULBAR'];
const LATERALIDAD = [
  { value: 'OD', label: 'OD · Ojo derecho' },
  { value: 'OI', label: 'OI · Ojo izquierdo' },
  { value: 'AO', label: 'AO · Ambos ojos' },
];
const INSUMOS_DISPONIBLES = {
  equipos: ['Equipo de facoemulsificación', 'Microscopio quirúrgico', 'Vitrectomo', 'Láser YAG', 'Criosonda', 'Lente de contacto de Abraham', 'Trépano de Hessburg-Barron', 'Gancho de músculo'],
  quirurgicos: ['Lente intraocular (LIO) plegable', 'Viscoelástico (hialuronato)', 'Set de facoemulsificación', 'Cánula de irrigación-aspiración', 'Cuchillete 2.75 mm', 'Sutura nylon 10-0', 'Sutura vicryl 8-0', 'Sutura vicryl 6-0', 'Injerto de membrana amniótica', 'Pegamento de fibrina', 'Gas SF6 / C3F8', 'Aceite de silicón', 'Mitomicina C', 'Botón corneal donante', 'Set de inyección intravítrea', 'Set de estrabismo', 'Blefarostato estéril'],
  anestesia: ['Lidocaína 2%', 'Bupivacaína 0.75%', 'Set de anestesia peribulbar', 'Midazolam', 'Propofol'],
};
const CAT_INSUMO_LABEL = { equipos: 'Equipos', quirurgicos: 'Quirúrgicos', anestesia: 'Anestesia' };
const MEDICAMENTOS = ['Cefazolina', 'Moxifloxacino intracameral', 'Dexametasona', 'Ketorolaco', 'Atropina', 'Acetazolamida', 'Bevacizumab (Avastin)', 'Ranibizumab', 'Triamcinolona', 'Tropicamida', 'Apraclonidina'];
const VIAS = ['INTRAVENOSA', 'INFILTRATIVA', 'SUBCONJUNTIVAL', 'TÓPICA', 'INTRAVÍTREA', 'INTRACAMERAL'];
const RESPONSABLES_MED = ['Cirujano Principal', 'Anestesiólogo', 'Asistente', 'Instrumentista'];

// ---- Utilities --------------------------------------------------
function insumoCat(it) {
  // Prefer explicit category fields from the backend
  const cat = it.categoria || it.cat || it.category || '';
  if (cat === 'equipos' || cat === 'quirurgicos' || cat === 'anestesia') return cat;
  // Fall back to catalogue lookup by name
  const nombre = it.nombre || it.name || '';
  for (const c of Object.keys(INSUMOS_DISPONIBLES)) {
    if (INSUMOS_DISPONIBLES[c].includes(nombre)) return c;
  }
  return 'quirurgicos';
}

// Resolve display name for an insumo item (handles both 'nombre' and 'name' keys)
function insumoNombre(it) {
  return it.nombre || it.name || '';
}

// Resolve display name for a medicamento item (handles 'medicamento' and 'nombre')
function medNombre(m) {
  return m.nombre || m.medicamento || m.name || '';
}

function flatInsumos(ins) {
  if (!ins) return [];
  if (Array.isArray(ins)) {
    // Format 2: plain array — normalise on the fly
    return ins.map((it) => ({
      id: it.id ?? it.nombre ?? it.name ?? '',
      nombre: insumoNombre(it),
      cantidad: it.cantidad ?? it.quantity ?? 1,
      cat: insumoCat(it),
    }));
  }
  // Format 1: grouped object { equipos, quirurgicos, anestesia }
  return [
    ...(ins.equipos || []).map((it) => ({ ...it, nombre: insumoNombre(it), cat: 'equipos' })),
    ...(ins.quirurgicos || []).map((it) => ({ ...it, nombre: insumoNombre(it), cat: 'quirurgicos' })),
    ...(ins.anestesia || []).map((it) => ({ ...it, nombre: insumoNombre(it), cat: 'anestesia' })),
  ];
}

function groupInsumos(items) {
  const g = { equipos: [], quirurgicos: [], anestesia: [] };
  items.forEach((it) => { (g[it.cat] || g.quirurgicos).push(it); });
  return g;
}

// Normalise a raw insumos payload (any format) → grouped object
function normaliseInsumos(raw) {
  if (!raw) return { equipos: [], quirurgicos: [], anestesia: [] };
  if (Array.isArray(raw)) return groupInsumos(flatInsumos(raw));
  if (typeof raw === 'object') {
    // Already grouped — normalise names and add cat marker
    const fix = (arr, cat) => (arr || []).map((it) => ({
      id: it.id ?? insumoNombre(it),
      nombre: insumoNombre(it),
      cantidad: it.cantidad ?? it.quantity ?? 1,
      cat,
    }));
    return {
      equipos: fix(raw.equipos, 'equipos'),
      quirurgicos: fix(raw.quirurgicos, 'quirurgicos'),
      anestesia: fix(raw.anestesia, 'anestesia'),
    };
  }
  return { equipos: [], quirurgicos: [], anestesia: [] };
}

// Normalise a raw medicamentos array → standard shape
function normaliseMedicamentos(raw) {
  if (!Array.isArray(raw)) return [];
  return raw.map((m) => ({
    id: m.id ?? medNombre(m),
    nombre: medNombre(m),            // canonical display name
    dosis: m.dosis || m.dose || '',
    via: m.via || m.vía || m.route || '',
    responsable: m.responsable || m.responsible || '',
    frecuencia: m.frecuencia || m.frequency || 'Dosis única',
  }));
}

function durMin(hi, hf) {
  if (!hi || !hf) return 0;
  const [a, b] = hi.split(':').map(Number), [c, d] = hf.split(':').map(Number);
  return (c * 60 + d) - (a * 60 + b);
}

function nombreCompleto(f) {
  return [f.fname, f.mname, f.lname, f.lname2].filter(Boolean).join(' ') || f.full_name || '';
}

function runAudit(r) {
  const checks = [];
  const add = (status, title, message, details) => checks.push({ status, title, message, details: details || {} });

  if (!String(r.membrete || '').trim()) {
    add('error', 'Procedimiento realizado', 'No se registró la cirugía realizada (membrete).', { proyectado: r.procedimiento_proyectado });
  } else {
    add('ok', 'Procedimiento realizado', 'Cirugía realizada registrada y concordante con lo proyectado.', { proyectado: r.procedimiento_proyectado, registrado: r.membrete });
  }

  if (!String(r.operatorio || '').trim()) {
    add('error', 'Descripción operatoria', 'Falta la descripción operatoria del protocolo.');
  } else if (String(r.operatorio || '').trim().length < 60) {
    add('warning', 'Descripción operatoria', 'La descripción operatoria es muy breve; revisa que esté completa.');
  } else {
    add('ok', 'Descripción operatoria', 'Descripción operatoria presente.');
  }

  const previos = (r.diagnosticos_previos || []).map((d) => d.cie10);
  const registrados = (r.diagnosticos || []).map((d) => d.cie10);
  const faltanDx = previos.filter((c) => !registrados.includes(c));
  if (registrados.length === 0) {
    add('error', 'Diagnósticos', 'No hay diagnósticos registrados en el protocolo.', { faltantes: previos });
  } else if (faltanDx.length) {
    add('warning', 'Diagnósticos', 'Hay diagnósticos de la derivación que no constan en el protocolo.', { proyectado: previos.join(', '), registrado: registrados.join(', '), faltantes: faltanDx });
  } else {
    add('ok', 'Diagnósticos', 'Diagnósticos registrados concuerdan con la derivación.', { registrado: registrados.join(', ') });
  }

  const usados = flatInsumos(r.insumos).map((x) => x.nombre);
  const faltanIns = (r.insumos_esperados || []).filter((n) => !usados.includes(n));
  if (faltanIns.length >= 2) {
    add('error', 'Insumos', 'Faltan insumos esperados por la plantilla quirúrgica.', { esperado: (r.insumos_esperados || []).length, registrado: usados.length, faltantes: faltanIns });
  } else if (faltanIns.length === 1) {
    add('warning', 'Insumos', 'Falta 1 insumo respecto a la plantilla quirúrgica.', { esperado: (r.insumos_esperados || []).length, registrado: usados.length, faltantes: faltanIns });
  } else {
    add('ok', 'Insumos', 'Insumos registrados concuerdan con la plantilla quirúrgica.', { esperado: (r.insumos_esperados || []).length, registrado: usados.length });
  }

  const st = r.staff || {};
  const faltanStaff = [];
  if (!st.cirujano_1) faltanStaff.push('Cirujano principal');
  if (!st.anestesiologo) faltanStaff.push('Anestesiólogo');
  if (!st.instrumentista) faltanStaff.push('Instrumentista');
  if (!st.circulante) faltanStaff.push('Circulante');
  if (faltanStaff.length >= 2) add('error', 'Equipo quirúrgico', 'Faltan integrantes obligatorios del equipo.', { faltantes: faltanStaff });
  else if (faltanStaff.length === 1) add('warning', 'Equipo quirúrgico', 'Falta registrar un integrante del equipo.', { faltantes: faltanStaff });
  else add('ok', 'Equipo quirúrgico', 'Equipo quirúrgico completo.');

  if (!r.hora_inicio || !r.hora_fin) {
    add('error', 'Tiempos quirúrgicos', 'No se registraron hora de inicio y/o fin.');
  } else {
    const dur = durMin(r.hora_inicio, r.hora_fin);
    if (dur <= 0) add('warning', 'Tiempos quirúrgicos', 'La hora de fin no es posterior a la de inicio.');
    else add('ok', 'Tiempos quirúrgicos', `Duración registrada: ${dur} min.`, { registrado: dur });
  }

  if (!r.tipo_anestesia) add('warning', 'Anestesia', 'No se registró el tipo de anestesia.');
  else add('ok', 'Anestesia', `Anestesia ${String(r.tipo_anestesia).toLowerCase()} registrada.`);

  const summary = { ok: 0, warning: 0, error: 0 };
  checks.forEach((c) => { summary[c.status]++; });
  const status = summary.error > 0 ? 'error' : summary.warning > 0 ? 'warning' : 'ok';
  return { status, summary, checks };
}

// ---- normaliseWizardForm: backend → wizard form shape -----------
export function normaliseWizardForm(row, data) {
  // Debug: compare raw backend response vs what we normalise
  console.log('[wizard] raw /protocolo response', {
    insumos: data.insumos,
    medicamentos: data.medicamentos,
    procedimientos: data.procedimientos,
    diagnosticos: data.diagnosticos,
    diagnosticos_previos: data.diagnosticos_previos,
    operatorio: data.operatorio ? data.operatorio.slice(0, 80) + '…' : null,
  });

  const backendStaff = data.staff || {};
  const staff = {
    cirujano_1: backendStaff['Cirujano principal'] || backendStaff['cirujano_1'] || '',
    cirujano_2: backendStaff['Cirujano 2'] || backendStaff['cirujano_2'] || '',
    primer_ayudante: backendStaff['Primer ayudante'] || backendStaff['primer_ayudante'] || '',
    segundo_ayudante: backendStaff['Segundo ayudante'] || backendStaff['segundo_ayudante'] || '',
    tercer_ayudante: backendStaff['Tercer ayudante'] || backendStaff['tercer_ayudante'] || '',
    anestesiologo: backendStaff['Anestesiólogo'] || backendStaff['anestesiologo'] || '',
    ayudante_anestesia: backendStaff['Ayudante anestesia'] || backendStaff['ayudante_anestesia'] || '',
    instrumentista: backendStaff['Instrumentista'] || backendStaff['instrumentista'] || '',
    circulante: backendStaff['Circulante'] || backendStaff['circulante'] || '',
  };

  const insumos = normaliseInsumos(data.insumos);

  // Ensure procedimientos always has at least one editable row
  const procedimientosRaw = data.procedimientos || [];
  const procedimientos = Array.isArray(procedimientosRaw) && procedimientosRaw.length > 0
    ? procedimientosRaw
    : [{ codigo: data.procedimiento_id || '', nombre: data.membrete || row.membrete || '' }];

  const result = {
    // identity (from row)
    id: row.id,
    form_id: row.form_id,
    hc_number: row.hc_number,
    cedula: row.cedula || data.hc_number || '',
    full_name: row.full_name || '',
    fname: data.fname || '',
    mname: data.mname || '',
    lname: data.lname || '',
    lname2: data.lname2 || '',
    fecha_nacimiento: data.fecha_nacimiento || '',
    edad: row.edad ?? '',
    afiliacion: row.afiliacion_label || row.afiliacion || '',
    sede: row.sede || '',
    quirofano: row.quirofano || '',
    // procedure
    proc_codigo: data.procedimiento_id || '',
    procedimiento_id: data.procedimiento_id || '',
    proc_nombre: data.membrete || row.membrete || '',
    proc_short: row.proc_short || '',
    lateralidad: data.lateralidad || row.lateralidad || '',
    procedimiento_proyectado: data.procedimiento_proyectado || '',
    procedimientos,
    diagnosticos: Array.isArray(data.diagnosticos) ? data.diagnosticos : [],
    diagnosticos_previos: Array.isArray(data.diagnosticos_previos) ? data.diagnosticos_previos : [],
    // staff (normalised)
    staff,
    // tiempos
    fecha_inicio: data.fecha_inicio || '',
    hora_inicio: data.hora_inicio || '',
    fecha_fin: data.fecha_fin || '',
    hora_fin: data.hora_fin || '',
    duracion_proy: row.duracion_proy || 0,
    tipo_anestesia: data.tipo_anestesia || '',
    // operatorio
    membrete: data.membrete || '',
    dieresis: data.dieresis || '',
    exposicion: data.exposicion || '',
    hallazgo: data.hallazgo || '',
    operatorio: data.operatorio || '',
    complicaciones_operatorio: data.complicaciones_operatorio || '',
    // insumos / meds
    insumos,
    insumos_esperados: data.insumos_esperados || [],
    medicamentos: normaliseMedicamentos(data.medicamentos),
    medicamentos_esperados: data.medicamentos_esperados || [],
    // meta
    status: row.status,
    autosave_ts: null,
  };

  console.log('[wizard] normalised form', {
    insumos: result.insumos,
    insumos_flat: flatInsumos(result.insumos),
    medicamentos: result.medicamentos,
  });

  return result;
}

// ---- Wizard steps config ----------------------------------------
const WSTEPS = [
  { key: 'paciente', label: 'Paciente', sub: 'Datos del paciente', icon: 'mdi-account-outline' },
  { key: 'procedimiento', label: 'Procedimientos', sub: 'Diagnósticos y lateralidad', icon: 'mdi-medical-bag' },
  { key: 'staff', label: 'Equipo quirúrgico', sub: 'Staff completo', icon: 'mdi-account-group-outline' },
  { key: 'tiempos', label: 'Tiempos y anestesia', sub: 'Fechas, horas, anestesia', icon: 'mdi-clock-outline' },
  { key: 'operatorio', label: 'Operatorio', sub: 'Descripción quirúrgica', icon: 'mdi-scalpel' },
  { key: 'insumos', label: 'Insumos', sub: 'Plantilla quirúrgica', icon: 'mdi-package-variant-closed' },
  { key: 'medicamentos', label: 'Medicamentos', sub: 'Fármacos usados', icon: 'mdi-pill' },
  { key: 'resumen', label: 'Resumen', sub: 'Auditoría y firma', icon: 'mdi-clipboard-check-outline' },
];
const CHECK_STEP = {
  'Procedimiento realizado': 'operatorio', 'Descripción operatoria': 'operatorio',
  'Diagnósticos': 'procedimiento', 'Insumos': 'insumos', 'Equipo quirúrgico': 'staff',
  'Tiempos quirúrgicos': 'tiempos', 'Anestesia': 'tiempos',
};

function deepCopy(r) { return JSON.parse(JSON.stringify(r)); }

// ---- AuditPill (topbar indicator) --------------------------------
function AuditPill({ audit }) {
  const cfg = {
    ok: { cls: 'audit-ok', icon: 'mdi-check-circle', label: 'Conforme' },
    warning: { cls: 'audit-warning', icon: 'mdi-alert', label: `${audit.summary.warning} alerta(s)` },
    error: { cls: 'audit-error', icon: 'mdi-alert-circle', label: `${audit.summary.error} error(es)` },
  }[audit.status];
  return (
    <span className={`audit-pill ${cfg.cls}`}>
      <i className={`mdi ${cfg.icon}`} /> {cfg.label}
    </span>
  );
}

// ---- Main wizard component --------------------------------------
export function ProtocolWizard({ form: initialForm, endpoints = {}, onClose, onSave, showToast }) {
  const [form, setForm] = useState(() => deepCopy(initialForm));
  const [step, setStep] = useState(0);
  const [marcarRevisado, setMarcarRevisado] = useState(initialForm.status === 1);
  const [scraped, setScraped] = useState((initialForm.diagnosticos_previos || []).length > 0);
  const [scraping, setScraping] = useState(false);

  const audit = useMemo(() => runAudit(form), [form]);
  const stepStatus = useMemo(() => {
    const m = {};
    audit.checks.forEach((c) => {
      const s = CHECK_STEP[c.title]; if (!s) return;
      const rank = { ok: 0, warning: 1, error: 2 };
      if (!m[s] || rank[c.status] > rank[m[s]]) m[s] = c.status;
    });
    return m;
  }, [audit]);

  const set = useCallback((k, v) => setForm((f) => ({ ...f, [k]: v })), []);
  const setStaff = useCallback((k, v) => setForm((f) => ({ ...f, staff: { ...f.staff, [k]: v } })), []);

  const go = (i) => setStep(Math.max(0, Math.min(WSTEPS.length - 1, i)));
  const last = step === WSTEPS.length - 1;

  const finish = () => onSave(form, { status: marcarRevisado ? 1 : 0 });

  const cur = WSTEPS[step].key;
  const ctx = { form, set, setStaff, setForm, showToast, scraped, setScraped, scraping, setScraping, audit, marcarRevisado, setMarcarRevisado, endpoints, goStep: (k) => go(WSTEPS.findIndex((s) => s.key === k)) };

  return (
    <div className="wiz-overlay">
      <div className="wiz-topbar">
        <button className="wt-back" onClick={onClose}><i className="mdi mdi-arrow-left" /> Volver al listado</button>
        <div className="wt-title">
          <b>{form.full_name}</b>
          <span>{form.form_id} · {form.proc_short || form.proc_nombre} · {form.lateralidad}</span>
        </div>
        <div className="wt-spacer" />
        <AuditPill audit={audit} />
        <span className="wiz-autosave"><i className="mdi mdi-cloud-check-outline" /> Autoguardado {form.autosave_ts || 'ahora'}</span>
      </div>

      <div className="wiz-main">
        <aside className="wiz-rail">
          <div className="wiz-progress">
            <div className="bar"><i style={{ width: `${Math.round(((step + 1) / WSTEPS.length) * 100)}%` }} /></div>
            <div className="lbl">Paso {step + 1} de {WSTEPS.length}</div>
          </div>
          <div className="wiz-steps">
            {WSTEPS.map((s, i) => {
              const st = stepStatus[s.key];
              return (
                <button key={s.key} className={`wiz-step ${i === step ? 'active' : ''} ${i < step ? 'done' : ''}`} onClick={() => go(i)}>
                  <span className="ws-num">{i < step ? <i className="mdi mdi-check" /> : i + 1}</span>
                  <span className="ws-meta"><b>{s.label}</b><span>{s.sub}</span></span>
                  {st === 'error' && <i className="mdi mdi-alert-circle ws-flag err" />}
                  {st === 'warning' && <i className="mdi mdi-alert ws-flag warn" />}
                </button>
              );
            })}
          </div>
        </aside>

        <div className="wiz-content" style={{ position: 'relative' }}>
          {cur === 'paciente' && <StepPaciente {...ctx} />}
          {cur === 'procedimiento' && <StepProcedimiento {...ctx} />}
          {cur === 'staff' && <StepStaff {...ctx} />}
          {cur === 'tiempos' && <StepTiempos {...ctx} />}
          {cur === 'operatorio' && <StepOperatorio {...ctx} />}
          {cur === 'insumos' && <StepInsumos {...ctx} />}
          {cur === 'medicamentos' && <StepMedicamentos {...ctx} />}
          {cur === 'resumen' && <StepResumen {...ctx} />}

          <div className="wiz-foot">
            <button className="btn btn-ghost" onClick={onClose}>Cancelar</button>
            <button className="btn btn-ghost" disabled={step === 0} onClick={() => go(step - 1)}>
              <i className="mdi mdi-chevron-left" /> Anterior
            </button>
            <div className="spacer" />
            <span className="step-of">{WSTEPS[step].label}</span>
            {!last ? (
              <button className="btn btn-primary" onClick={() => go(step + 1)}>Siguiente <i className="mdi mdi-chevron-right" /></button>
            ) : (
              <button className={`btn ${marcarRevisado ? 'btn-success' : 'btn-primary'}`} onClick={finish}>
                <i className={`mdi ${marcarRevisado ? 'mdi-clipboard-check-outline' : 'mdi-content-save-outline'}`} />
                {marcarRevisado ? 'Guardar y marcar revisado' : 'Guardar protocolo'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ---- Step 1: Paciente -------------------------------------------
function StepPaciente({ form, set }) {
  return (
    <div className="wiz-stepframe">
      <h3>Datos del paciente</h3>
      <div className="step-sub">Identidad del paciente intervenido. La afiliación proviene de la admisión y no se edita aquí.</div>
      <div className="form-grid-2">
        <div className="form-row"><label>Primer nombre</label><input value={form.fname} onChange={(e) => set('fname', e.target.value)} /></div>
        <div className="form-row"><label>Segundo nombre</label><input value={form.mname} onChange={(e) => set('mname', e.target.value)} /></div>
        <div className="form-row"><label>Primer apellido</label><input value={form.lname} onChange={(e) => set('lname', e.target.value)} /></div>
        <div className="form-row"><label>Segundo apellido</label><input value={form.lname2} onChange={(e) => set('lname2', e.target.value)} /></div>
        <div className="form-row"><label>Fecha de nacimiento</label><input type="date" value={form.fecha_nacimiento} onChange={(e) => set('fecha_nacimiento', e.target.value)} /></div>
        <div className="form-row"><label>Afiliación</label><input value={form.afiliacion} readOnly /></div>
        <div className="form-row"><label>Cédula</label><input value={form.cedula} readOnly /></div>
        <div className="form-row"><label>Historia clínica</label><input value={form.hc_number} readOnly /></div>
      </div>
    </div>
  );
}

// ---- Step 2: Procedimientos & Diagnósticos ----------------------
function StepProcedimiento({ form, set, setForm, showToast, scraped, setScraped, scraping, setScraping, endpoints }) {
  const updArr = (key, i, field, val) => setForm((f) => { const a = f[key].slice(); a[i] = { ...a[i], [field]: val }; return { ...f, [key]: a }; });
  const addRow = (key, blank) => setForm((f) => ({ ...f, [key]: [...f[key], blank] }));
  const rmRow = (key, i) => setForm((f) => ({ ...f, [key]: f[key].filter((_, k) => k !== i) }));

  const doScrape = () => {
    if (!endpoints.scrapeDerivacion) return;
    setScraping(true);
    fetch(endpoints.scrapeDerivacion, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': window.csrfToken || '', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({ form_id: form.form_id, hc_number: form.hc_number }).toString(),
    })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success) throw new Error(res.message || 'El scraper no devolvió datos');
        const previos = res.data?.diagnosticos_previos || [];
        setForm((f) => ({ ...f, diagnosticos_previos: previos }));
        setScraped(true);
        showToast(
          previos.length > 0
            ? `${previos.length} diagnóstico(s) extraídos del Log de Admisión`
            : 'Sin diagnósticos en la derivación',
          'mdi-cloud-download-outline',
        );
      })
      .catch((e) => showToast(e.message || 'Error al extraer diagnósticos', 'mdi-alert-circle', 'warn'))
      .finally(() => setScraping(false));
  };

  const importarPrevio = (p) => {
    setForm((f) => {
      if (f.diagnosticos.some((d) => d.cie10 === p.cie10)) return f;
      return { ...f, diagnosticos: [...f.diagnosticos, { ojo: f.lateralidad, evidencia: 'Derivación', cie10: p.cie10, detalle: p.descripcion || '', observaciones: '' }] };
    });
    showToast(`Diagnóstico ${p.cie10} importado al protocolo`, 'mdi-plus-circle-outline');
  };

  return (
    <div className="wiz-stepframe">
      <h3>Procedimientos, diagnósticos y lateralidad</h3>
      <div className="step-sub">Define qué se operó y por qué. La auditoría compara estos diagnósticos con los de la derivación.</div>

      <div className="fieldset">
        <legend>Procedimientos realizados</legend>
        {(form.procedimientos || []).map((p, i) => (
          <div className="rep-row proc" key={i}>
            <input value={p.codigo} placeholder="Código" onChange={(e) => updArr('procedimientos', i, 'codigo', e.target.value)} />
            <input value={p.nombre} placeholder="Nombre del procedimiento" onChange={(e) => updArr('procedimientos', i, 'nombre', e.target.value)} />
            <button className="icon-mini" title="Quitar" onClick={() => rmRow('procedimientos', i)} disabled={(form.procedimientos || []).length === 1}><i className="mdi mdi-minus" /></button>
          </div>
        ))}
        <button className="add-line" onClick={() => addRow('procedimientos', { codigo: '', nombre: '' })}><i className="mdi mdi-plus-circle-outline" /> Agregar procedimiento</button>
      </div>

      <div className="form-row" style={{ maxWidth: 280 }}>
        <label>Lateralidad</label>
        <select value={form.lateralidad} onChange={(e) => set('lateralidad', e.target.value)}>
          {LATERALIDAD.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
        </select>
      </div>

      <div className="scrape-block">
        <div className="sb-head"><i className="mdi mdi-radar" /> Diagnósticos de la derivación</div>
        <div className="hint" style={{ marginBottom: 8 }}>Extrae los diagnósticos del Log de Admisión (CIVE) para contrastarlos con lo registrado.</div>
        <button className="btn btn-ghost btn-sm" onClick={doScrape} disabled={scraping}>
          {scraping
            ? <><i className="mdi mdi-loading mdi-spin" /> Extrayendo…</>
            : <><i className="mdi mdi-cloud-search-outline" /> Extraer desde Log de Admisión</>}
        </button>
        {scraped && (form.diagnosticos_previos || []).length > 0 && (
          <div className="diag-prev">
            {form.diagnosticos_previos.map((p, i) => {
              const yaEsta = (form.diagnosticos || []).some((d) => d.cie10 === p.cie10);
              return (
                <div className="diag-prev-row" key={i}>
                  <span className="cie">{p.cie10}</span>
                  <span className="desc">{p.descripcion}</span>
                  {yaEsta
                    ? <span className="badge badge-success"><i className="mdi mdi-check" /> En protocolo</span>
                    : <button className="btn btn-outline-primary btn-sm" onClick={() => importarPrevio(p)}><i className="mdi mdi-plus" /> Importar</button>}
                </div>
              );
            })}
          </div>
        )}
      </div>

      <div className="fieldset">
        <legend>Diagnósticos del protocolo (CIE-10)</legend>
        {(form.diagnosticos || []).length === 0 && <div className="proto-empty" style={{ marginBottom: 10 }}>Sin diagnósticos. Importa desde la derivación o agrégalos manualmente.</div>}
        {(form.diagnosticos || []).map((d, i) => (
          <div className="rep-row dx" key={i}>
            <input value={d.ojo} placeholder="Ojo" onChange={(e) => updArr('diagnosticos', i, 'ojo', e.target.value)} />
            <input value={d.detalle} placeholder="Detalle del diagnóstico" onChange={(e) => updArr('diagnosticos', i, 'detalle', e.target.value)} />
            <input value={d.cie10} placeholder="CIE-10" onChange={(e) => updArr('diagnosticos', i, 'cie10', e.target.value)} />
            <button className="icon-mini" title="Quitar" onClick={() => rmRow('diagnosticos', i)}><i className="mdi mdi-minus" /></button>
          </div>
        ))}
        <button className="add-line" onClick={() => addRow('diagnosticos', { ojo: form.lateralidad, evidencia: '', cie10: '', detalle: '', observaciones: '' })}><i className="mdi mdi-plus-circle-outline" /> Agregar diagnóstico</button>
      </div>
    </div>
  );
}

// ---- Step 3: Staff ----------------------------------------------
function StepStaff({ form, setStaff }) {
  const Sel = ({ label, k, req }) => (
    <div className="form-row">
      <label>{label} {req && <span style={{ color: 'var(--danger)' }}>*</span>}</label>
      <input value={form.staff[k] || ''} onChange={(e) => setStaff(k, e.target.value)} placeholder="Nombre del profesional" />
    </div>
  );
  return (
    <div className="wiz-stepframe">
      <h3>Equipo quirúrgico</h3>
      <div className="step-sub">Registra a los integrantes del acto quirúrgico. Cirujano principal, anestesiólogo, instrumentista y circulante son obligatorios para la auditoría.</div>
      <div className="form-grid-2">
        <Sel label="Cirujano principal" k="cirujano_1" req />
        <Sel label="Cirujano asistente" k="cirujano_2" />
        <Sel label="Primer ayudante" k="primer_ayudante" />
        <Sel label="Segundo ayudante" k="segundo_ayudante" />
        <Sel label="Tercer ayudante" k="tercer_ayudante" />
        <Sel label="Anestesiólogo" k="anestesiologo" req />
        <Sel label="Ayudante de anestesia" k="ayudante_anestesia" />
        <Sel label="Instrumentista" k="instrumentista" req />
        <Sel label="Enfermera circulante" k="circulante" req />
      </div>
    </div>
  );
}

// ---- Step 4: Tiempos & Anestesia --------------------------------
function StepTiempos({ form, set }) {
  const dur = durMin(form.hora_inicio, form.hora_fin);
  return (
    <div className="wiz-stepframe">
      <h3>Tiempos quirúrgicos y anestesia</h3>
      <div className="step-sub">Horario real del procedimiento y tipo de anestesia administrada.</div>
      <div className="form-grid-2">
        <div className="form-row"><label>Fecha de inicio</label><input type="date" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} /></div>
        <div className="form-row"><label>Hora de inicio</label><input type="time" value={form.hora_inicio} onChange={(e) => set('hora_inicio', e.target.value)} /></div>
        <div className="form-row"><label>Fecha de fin</label><input type="date" value={form.fecha_fin} onChange={(e) => set('fecha_fin', e.target.value)} /></div>
        <div className="form-row"><label>Hora de fin</label><input type="time" value={form.hora_fin} onChange={(e) => set('hora_fin', e.target.value)} /></div>
      </div>
      <div className="form-grid-2">
        <div className="form-row">
          <label>Tipo de anestesia</label>
          <select value={form.tipo_anestesia} onChange={(e) => set('tipo_anestesia', e.target.value)}>
            <option value="">— Seleccionar —</option>
            {TIPO_ANESTESIA.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
        </div>
        <div className="form-row">
          <label>Duración registrada</label>
          <input readOnly value={dur > 0 ? `${dur} min (proyectado ${form.duracion_proy} min)` : '—'} />
          <span className="hint">Se calcula automáticamente desde las horas de inicio y fin.</span>
        </div>
      </div>
    </div>
  );
}

// ---- Step 5: Operatorio -----------------------------------------
function StepOperatorio({ form, set, showToast }) {
  const autocompletar = () => {
    showToast('Para autocompletar, revisa la plantilla del procedimiento', 'mdi-auto-fix');
  };
  return (
    <div className="wiz-stepframe">
      <h3>Descripción del acto operatorio</h3>
      <div className="step-sub">Documenta la cirugía realizada. La auditoría verifica concordancia con lo proyectado.</div>

      <div className="ai-row">
        <i className="mdi mdi-auto-fix" />
        <div className="ai-txt"><b>Asistente IA.</b> Genera un borrador de la descripción operatoria a partir de la plantilla del procedimiento proyectado. Revisa y ajusta antes de firmar.</div>
        <button className="btn btn-outline-primary btn-sm" onClick={autocompletar}><i className="mdi mdi-auto-fix" /> Autocompletar</button>
      </div>

      <div className="form-row"><label>Procedimiento proyectado</label>
        <textarea readOnly rows={2} value={form.procedimiento_proyectado} /></div>
      <div className="form-row"><label>Procedimiento realizado (membrete)</label>
        <textarea rows={2} value={form.membrete} onChange={(e) => set('membrete', e.target.value)} placeholder="Cirugía efectivamente realizada…" /></div>
      <div className="form-grid-2">
        <div className="form-row"><label>Diéresis</label><textarea rows={2} value={form.dieresis} onChange={(e) => set('dieresis', e.target.value)} /></div>
        <div className="form-row"><label>Exposición</label><textarea rows={2} value={form.exposicion} onChange={(e) => set('exposicion', e.target.value)} /></div>
      </div>
      <div className="form-row"><label>Hallazgo</label><textarea rows={2} value={form.hallazgo} onChange={(e) => set('hallazgo', e.target.value)} /></div>
      <div className="form-row"><label>Descripción operatoria</label>
        <textarea rows={6} value={form.operatorio} onChange={(e) => set('operatorio', e.target.value)} placeholder="Pasos del acto quirúrgico…" /></div>
      <div className="form-row"><label>Complicaciones operatorias</label>
        <textarea rows={2} value={form.complicaciones_operatorio} onChange={(e) => set('complicaciones_operatorio', e.target.value)} placeholder="Sin complicaciones / detalle…" /></div>
    </div>
  );
}

// ---- Step 6: Insumos --------------------------------------------
function StepInsumos({ form, setForm, showToast }) {
  const cats = Object.keys(INSUMOS_DISPONIBLES);
  const all = flatInsumos(form.insumos);
  const faltantes = (form.insumos_esperados || []).filter((n) => !all.map((x) => x.nombre).includes(n));

  const rebuild = (items) => setForm((f) => ({ ...f, insumos: groupInsumos(items) }));

  const updItem = (idx, field, val) => {
    const items = all.slice();
    if (field === 'cat') {
      // Only change category — preserve the existing name
      items[idx] = { ...items[idx], cat: val };
    } else if (field === 'nombre') {
      // Update name; re-derive category from catalogue, keep id stable if it was the old name
      const newCat = insumoCat({ nombre: val, cat: items[idx].cat });
      items[idx] = { ...items[idx], nombre: val, id: items[idx].id === items[idx].nombre ? val : items[idx].id, cat: newCat };
    } else {
      items[idx] = { ...items[idx], [field]: val };
    }
    rebuild(items);
  };

  const rm = (idx) => rebuild(all.filter((_, i) => i !== idx));
  const add = () => rebuild([...all, { id: '', nombre: '', cantidad: 1, cat: 'quirurgicos' }]);
  const addFaltantes = () => {
    const existing = new Set(all.map((x) => x.nombre));
    const nuevos = faltantes
      .filter((n) => !existing.has(n))
      .map((n) => ({ id: n, nombre: n, cantidad: 1, cat: insumoCat({ nombre: n }) }));
    rebuild([...all, ...nuevos]);
    showToast(`${nuevos.length} insumo(s) de la plantilla agregados`, 'mdi-package-variant-closed-plus');
  };

  return (
    <div className="wiz-stepframe">
      <h3>Insumos utilizados</h3>
      <div className="step-sub">Registra los insumos del acto quirúrgico. La auditoría los contrasta con la plantilla del procedimiento.</div>

      {faltantes.length > 0 && (
        <div className="faltan-banner">
          <i className="mdi mdi-clipboard-alert-outline" />
          <div>
            <b>Faltan {faltantes.length} insumo(s) esperado(s)</b> según la plantilla.
            <div className="chips">{faltantes.map((n) => <span className="c" key={n}>{n}</span>)}</div>
          </div>
          <button className="btn btn-ghost btn-sm addall" onClick={addFaltantes}><i className="mdi mdi-plus" /> Agregar todos</button>
        </div>
      )}

      <div className="etable-wrap">
        <table className="etable">
          <thead><tr><th style={{ width: 130 }}>Categoría</th><th>Insumo</th><th className="col-qty">Cant.</th><th className="col-act" /></tr></thead>
          <tbody>
            {all.length === 0 && <tr><td colSpan={4} style={{ padding: 16, color: 'var(--fg-mute)', fontStyle: 'italic' }}>Sin insumos registrados.</td></tr>}
            {all.map((it, i) => {
              const listId = `insumos-cat-${it.cat}-${i}`;
              return (
                <tr key={i}>
                  <td>
                    <select value={it.cat} onChange={(e) => updItem(i, 'cat', e.target.value)}>
                      {cats.map((c) => <option key={c} value={c}>{CAT_INSUMO_LABEL[c]}</option>)}
                    </select>
                  </td>
                  <td>
                    {/* Free-text input with datalist suggestions — never silently replaces the stored name */}
                    <input
                      list={listId}
                      value={it.nombre}
                      onChange={(e) => updItem(i, 'nombre', e.target.value)}
                      placeholder="Nombre del insumo"
                      style={{ width: '100%' }}
                    />
                    <datalist id={listId}>
                      {(INSUMOS_DISPONIBLES[it.cat] || []).map((n) => <option key={n} value={n} />)}
                    </datalist>
                  </td>
                  <td><input type="number" min="1" value={it.cantidad ?? 1} onChange={(e) => updItem(i, 'cantidad', e.target.value)} /></td>
                  <td className="col-act"><button className="icon-mini" onClick={() => rm(i)} title="Quitar"><i className="mdi mdi-minus" /></button></td>
                </tr>
              );
            })}
          </tbody>
        </table>
        <div className="etable-foot">
          <button className="add-line" onClick={add}><i className="mdi mdi-plus-circle-outline" /> Agregar insumo</button>
          <div style={{ flex: 1 }} />
          <span className="txt-muted" style={{ fontSize: 12 }}>{all.length} insumo(s) · plantilla espera {(form.insumos_esperados || []).length}</span>
        </div>
      </div>
    </div>
  );
}

// ---- Step 7: Medicamentos ---------------------------------------
function StepMedicamentos({ form, setForm, showToast }) {
  const meds = form.medicamentos || [];
  const setMeds = (m) => setForm((f) => ({ ...f, medicamentos: m }));
  const upd = (i, field, val) => {
    const m = meds.slice();
    m[i] = { ...m[i], [field]: val };
    // keep id in sync with nombre if it was previously the same value
    if (field === 'nombre' && m[i].id === meds[i].nombre) m[i].id = val;
    setMeds(m);
  };
  const rm = (i) => setMeds(meds.filter((_, k) => k !== i));
  const add = () => setMeds([...meds, { id: '', nombre: '', dosis: '', frecuencia: 'Dosis única', via: '', responsable: '' }]);
  const faltantes = (form.medicamentos_esperados || []).filter((n) => !meds.map((x) => x.nombre).includes(n));
  const addFaltantes = () => {
    const existing = new Set(meds.map((x) => x.nombre));
    const nuevos = faltantes
      .filter((n) => !existing.has(n))
      .map((n) => ({ id: n, nombre: n, dosis: '', frecuencia: 'Dosis única', via: '', responsable: '' }));
    setMeds([...meds, ...nuevos]);
    showToast(`${nuevos.length} medicamento(s) de la plantilla agregados`, 'mdi-pill');
  };

  return (
    <div className="wiz-stepframe">
      <h3>Medicamentos administrados</h3>
      <div className="step-sub">Fármacos utilizados durante el procedimiento, con vía y responsable de administración.</div>

      {faltantes.length > 0 && (
        <div className="faltan-banner">
          <i className="mdi mdi-pill" />
          <div>
            <b>La plantilla sugiere {faltantes.length} medicamento(s)</b> no registrado(s).
            <div className="chips">{faltantes.map((n) => <span className="c" key={n}>{n}</span>)}</div>
          </div>
          <button className="btn btn-ghost btn-sm addall" onClick={addFaltantes}><i className="mdi mdi-plus" /> Agregar todos</button>
        </div>
      )}

      <div className="etable-wrap">
        <table className="etable">
          <thead><tr><th>Medicamento</th><th style={{ width: 100 }}>Dosis</th><th style={{ width: 130 }}>Vía</th><th style={{ width: 150 }}>Responsable</th><th className="col-act" /></tr></thead>
          <tbody>
            {meds.length === 0 && <tr><td colSpan={5} style={{ padding: 16, color: 'var(--fg-mute)', fontStyle: 'italic' }}>Sin medicamentos registrados.</td></tr>}
            {meds.map((m, i) => (
              <tr key={i}>
                <td>
                  {/* Free-text + datalist — never silently replaces real values from backend */}
                  <input list="med-nombres" value={m.nombre} placeholder="Medicamento"
                    onChange={(e) => upd(i, 'nombre', e.target.value)} style={{ width: '100%' }} />
                </td>
                <td><input value={m.dosis || ''} placeholder="—" onChange={(e) => upd(i, 'dosis', e.target.value)} /></td>
                <td>
                  <input list="med-vias" value={m.via || ''} placeholder="Vía"
                    onChange={(e) => upd(i, 'via', e.target.value)} style={{ width: '100%' }} />
                </td>
                <td>
                  <input list="med-resp" value={m.responsable || ''} placeholder="Responsable"
                    onChange={(e) => upd(i, 'responsable', e.target.value)} style={{ width: '100%' }} />
                </td>
                <td className="col-act"><button className="icon-mini" onClick={() => rm(i)} title="Quitar"><i className="mdi mdi-minus" /></button></td>
              </tr>
            ))}
          </tbody>
        </table>
        {/* Shared datalists for suggestions */}
        <datalist id="med-nombres">{MEDICAMENTOS.map((n) => <option key={n} value={n} />)}</datalist>
        <datalist id="med-vias">{VIAS.map((v) => <option key={v} value={v} />)}</datalist>
        <datalist id="med-resp">{RESPONSABLES_MED.map((r) => <option key={r} value={r} />)}</datalist>
        <div className="etable-foot">
          <button className="add-line" onClick={add}><i className="mdi mdi-plus-circle-outline" /> Agregar medicamento</button>
        </div>
      </div>
    </div>
  );
}

// ---- Step 8: Resumen --------------------------------------------
function StepResumen({ form, audit, marcarRevisado, setMarcarRevisado }) {
  const dur = durMin(form.hora_inicio, form.hora_fin);
  const dxReg = (form.diagnosticos || []).map((d) => d.cie10);
  const dxPrev = (form.diagnosticos_previos || []).map((d) => d.cie10);
  const procNombres = (form.procedimientos || []).map((p) => p.nombre).filter(Boolean);

  return (
    <div className="wiz-stepframe">
      <h3>Resumen y auditoría final</h3>
      <div className="step-sub">Revisa el protocolo completo y la auditoría automática antes de firmar.</div>

      <AuditPanel audit={audit} />
      {audit.status === 'error' && (
        <div className="faltan-banner" style={{ background: '#fdecef', borderColor: '#f4c4cd', color: 'var(--cir-fg)' }}>
          <i className="mdi mdi-alert-circle-outline" style={{ color: 'var(--danger)' }} />
          <div><b>Hay {audit.summary.error} alerta(s) sin resolver.</b> Puedes firmar de todos modos, pero se recomienda corregirlas.</div>
        </div>
      )}

      <div className="resume-card">
        <div className="rc-head"><h4>Protocolo {form.form_id}</h4><span className="badge badge-line">Edad: {form.edad} años</span></div>
        <div className="rc-body">
          <div className="resume-cols">
            <dl className="kv-rows">
              <dt>Paciente</dt><dd>{nombreCompleto(form)}</dd>
              <dt>Afiliación</dt><dd>{form.afiliacion}</dd>
              <dt>Lateralidad</dt><dd>{form.lateralidad}</dd>
              <dt>Sede</dt><dd>{form.sede} · {form.quirofano}</dd>
            </dl>
            <dl className="kv-rows">
              <dt>Cirujano</dt><dd>{form.staff.cirujano_1 || '—'}</dd>
              <dt>Anestesiólogo</dt><dd>{form.staff.anestesiologo || '—'}</dd>
              <dt>Anestesia</dt><dd>{form.tipo_anestesia || '—'}</dd>
              <dt>Duración</dt><dd>{dur > 0 ? `${dur} min` : '—'}</dd>
            </dl>
          </div>
          <hr style={{ border: 0, borderTop: '1px solid var(--border)', margin: '14px 0' }} />
          <div style={{ marginBottom: 6, fontWeight: 600, fontSize: 13 }}>Procedimientos</div>
          <div className="chip-group" style={{ marginBottom: 14 }}>
            {procNombres.length ? procNombres.map((p, i) => <span className="chip chip-cir" key={i}>{p}</span>) : <span className="proto-empty">—</span>}
          </div>
          <div className="resume-cols">
            <div>
              <div style={{ marginBottom: 6, fontWeight: 600, fontSize: 13 }}>Diagnósticos del protocolo</div>
              <div className="chip-group">{dxReg.length ? dxReg.map((d, i) => <span className="chip" key={i}>{d}</span>) : <span className="proto-empty">—</span>}</div>
            </div>
            <div>
              <div style={{ marginBottom: 6, fontWeight: 600, fontSize: 13 }}>De la derivación</div>
              <div className="chip-group">{dxPrev.length ? dxPrev.map((d, i) => <span className="chip chip-primary" key={i}>{d}</span>) : <span className="proto-empty">—</span>}</div>
            </div>
          </div>
        </div>
      </div>

      <div className={`confirm-box ${marcarRevisado ? 'on' : ''}`}>
        <i className={`mdi ${marcarRevisado ? 'mdi-clipboard-check' : 'mdi-clipboard-text-outline'}`} style={{ fontSize: 26, color: marcarRevisado ? 'var(--success)' : 'var(--fg-3)' }} />
        <div className="cb-txt">
          <b>Marcar protocolo como revisado</b>
          <p>Al firmarlo, el protocolo pasa a «Revisados» y queda disponible para impresión y certificados.</p>
        </div>
        <label className="switch">
          <input type="checkbox" checked={marcarRevisado} onChange={(e) => setMarcarRevisado(e.target.checked)} />
          <span className="track" />
        </label>
      </div>
    </div>
  );
}
