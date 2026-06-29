import React, { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { TABS, KpiRow, Tabs, TabDescription, Filters, Toast, TweakPanel } from './components';
import { BulkBar, CirTable } from './table';
import { ProtocolModal, CertificadoModal, HelpModal, TabHelpModal } from './modals';
import { ProtocolWizard, normaliseWizardForm } from './wizard';

const TWEAK_DEFAULTS = { density: 'comodo', accent: '#5156be', afilColor: true, highlightAlerts: true };
const EMPTY_FILTERS = { search: '', from: '', to: '', afiliacion: '', sede: '' };

// Normalise a raw backend row into the shape the UI expects
function normaliseRow(r) {
  const auditStatus = r.audit_status || 'sin_protocolo';
  const alertasCount = r.alertas_count || 0;

  // status: 1 = revisado, 0 = not reviewed
  const status = auditStatus === 'conforme' ? 1 : 0;
  const protocolo_iniciado = auditStatus !== 'sin_protocolo';

  // Build a lightweight audit object for the row
  let audit = null;
  if (auditStatus === 'conforme') {
    audit = { status: 'ok', summary: { ok: 1, warning: 0, error: 0 }, checks: [] };
  } else if (auditStatus === 'alertas') {
    audit = { status: 'error', summary: { ok: 0, warning: 0, error: alertasCount }, checks: [] };
  } else if (auditStatus === 'por_revisar') {
    audit = { status: 'warning', summary: { ok: 0, warning: alertasCount, error: 0 }, checks: [] };
  }

  return {
    ...r,
    id: `${r.form_id}_${r.hc_number}`,
    status,
    protocolo_iniciado,
    audit,
    // fecha for sorting (backend returns dd/mm/yyyy, extract yyyy-mm-dd for compare)
    fecha_sort: r.fecha_inicio ? parseDateSort(r.fecha_inicio) : '',
  };
}

function parseDateSort(dateStr) {
  if (!dateStr) return '';
  // dd/mm/yyyy → yyyy-mm-dd
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
    const [d, m, y] = dateStr.split('/');
    return `${y}-${m}-${d}`;
  }
  return dateStr;
}

function useDatatable(endpoints, serverFilters) {
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const drawRef = useRef(0);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    drawRef.current += 1;
    const draw = drawRef.current;

    const body = new URLSearchParams({
      draw: String(draw),
      start: '0',
      length: '500',
      'search[value]': '',
      'order[0][column]': '4',
      'order[0][dir]': 'desc',
      fecha_inicio: serverFilters.from || '',
      fecha_fin: serverFilters.to || '',
      afiliacion: serverFilters.afiliacion || '',
      sede: serverFilters.sede || '',
    });

    window.fetch(endpoints.datatable, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': window.csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: body.toString(),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.draw !== draw) return;
        if (data.error) { setError(data.error); setLoading(false); return; }
        setRows((data.data || []).map(normaliseRow));
        setTotal(data.recordsTotal || 0);
        setLoading(false);
      })
      .catch((e) => { setError(e.message); setLoading(false); });
  }, [endpoints.datatable, serverFilters]);

  useEffect(() => { load(); }, [load]);

  return { rows, total, loading, error, reload: load };
}

export default function App({ config }) {
  const {
    afiliacionOptions = [],
    sedeOptions = [],
    fechaInicioDefault = '',
    fechaFinDefault = '',
    endpoints = {},
    currentUser = {},
  } = config;

  const [tweaks, setTweakState] = useState(TWEAK_DEFAULTS);
  const setTweak = (k, v) => setTweakState((prev) => ({ ...prev, [k]: v }));

  const [activeTab, setActiveTab] = useState('por-revisar');
  const [filters, setFilters] = useState({ ...EMPTY_FILTERS, from: fechaInicioDefault, to: fechaFinDefault });
  const [kpiFilter, setKpiFilter] = useState('');
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [toast, setToast] = useState(null);

  const [protocolRow, setProtocolRow] = useState(null);
  const [certRow, setCertRow] = useState(null);
  const [helpOpen, setHelpOpen] = useState(false);
  const [tabHelpKey, setTabHelpKey] = useState(null);
  const [tweakOpen, setTweakOpen] = useState(false);
  const [wizardRow, setWizardRow] = useState(null);
  const [wizardLoading, setWizardLoading] = useState(false);

  useEffect(() => {
    document.documentElement.style.setProperty('--accent', tweaks.accent || '#5156be');
  }, [tweaks.accent]);

  const serverFilters = useMemo(() => ({
    from: filters.from,
    to: filters.to,
    afiliacion: filters.afiliacion,
    sede: filters.sede,
  }), [filters.from, filters.to, filters.afiliacion, filters.sede]);

  const { rows, total, loading, error, reload } = useDatatable(endpoints, serverFilters);

  const showToast = useCallback((msg, icon = 'mdi-check-circle', tone = 'ok') => {
    setToast({ msg, icon, tone });
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => setToast(null), 2800);
  }, []);

  // Tab membership
  const inTab = useCallback((r, tab) => {
    if (tab === 'revisados') return r.status === 1;
    if (tab === 'sin-protocolo') return r.status === 0 && !r.protocolo_iniciado;
    if (tab === 'auditoria') return r.status === 0 && r.protocolo_iniciado && r.audit && r.audit.status === 'error';
    return r.status === 0 && r.protocolo_iniciado && (!r.audit || r.audit.status !== 'error');
  }, []);

  // Client-side search filter
  const matchesSearch = useCallback((r) => {
    const q = filters.search.trim().toLowerCase();
    if (!q) return true;
    return `${r.full_name} ${r.cedula || ''} ${r.hc_number} ${r.membrete || ''} ${r.afiliacion_label || ''}`.toLowerCase().includes(q);
  }, [filters.search]);

  const counts = useMemo(() => {
    const c = { 'por-revisar': 0, 'auditoria': 0, 'revisados': 0, 'sin-protocolo': 0 };
    rows.forEach((r) => {
      if (!matchesSearch(r)) return;
      TABS.forEach((tb) => { if (inTab(r, tb.key)) c[tb.key]++; });
    });
    return c;
  }, [rows, matchesSearch, inTab]);

  const metrics = useMemo(() => {
    let total2 = 0, porRevisar = 0, alertas = 0, revisados = 0, sinProtocolo = 0;
    rows.forEach((r) => {
      total2++;
      if (r.status === 1) revisados++;
      else if (!r.protocolo_iniciado) sinProtocolo++;
      else if (r.audit && r.audit.status === 'error') alertas++;
      else porRevisar++;
    });
    return { total: total2, porRevisar, alertas, revisados, sinProtocolo };
  }, [rows]);

  const visibleRows = useMemo(() => {
    let list = rows.filter((r) => matchesSearch(r) && inTab(r, activeTab));
    if (activeTab === 'auditoria') {
      list = list.slice().sort((a, b) => (b.audit?.summary?.error || 0) - (a.audit?.summary?.error || 0) || (b.fecha_sort || '').localeCompare(a.fecha_sort || ''));
    } else {
      list = list.slice().sort((a, b) => (b.fecha_sort || '').localeCompare(a.fecha_sort || ''));
    }
    return list;
  }, [rows, matchesSearch, inTab, activeTab]);

  useEffect(() => { setSelectedIds(new Set()); }, [activeTab]);

  const onKpi = useCallback((key) => {
    setKpiFilter((prev) => (prev === key ? '' : key));
    if (key === 'por-revisar') setActiveTab('por-revisar');
    else if (key === 'alertas') setActiveTab('auditoria');
    else if (key === 'revisados') setActiveTab('revisados');
    else if (key === 'sin-protocolo') setActiveTab('sin-protocolo');
    else setActiveTab('por-revisar');
  }, []);

  const toggle = useCallback((id) => {
    setSelectedIds((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
  }, []);
  const toggleAll = useCallback(() => {
    setSelectedIds((prev) => {
      const allIds = visibleRows.map((r) => r.id);
      const all = allIds.every((id) => prev.has(id));
      return all ? new Set() : new Set(allIds);
    });
  }, [visibleRows]);

  const openWizard = useCallback((row) => {
    setProtocolRow(null);
    setWizardLoading(true);
    const params = new URLSearchParams({ form_id: row.form_id, hc_number: row.hc_number });
    fetch(`${endpoints.protocolo}?${params}`)
      .then((r) => r.json())
      .then((data) => {
        if (data.error) throw new Error(data.error);
        setWizardRow(normaliseWizardForm(row, data));
      })
      .catch((e) => {
        showToast(e.message || 'No se pudo cargar el protocolo', 'mdi-alert-circle', 'warn');
      })
      .finally(() => setWizardLoading(false));
  }, [endpoints.protocolo, showToast]);

  const postJson = useCallback((url, payload) => {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': window.csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams(payload).toString(),
    }).then((r) => r.json());
  }, []);

  // MODELO DEFINITIVO (refactorización futura — NO implementar aún):
  // Los campos duplicados en el payload son TEMPORALES para mantener compatibilidad
  // con el backend actual mientras se normaliza el modelo de datos:
  //   - procedimientos → guardar solo el código y resolver nombre desde tarifario_2014
  //   - medicamentos   → guardar solo el ID del medicamento
  //   - insumos        → guardar solo el ID del insumo
  //   - staff          → guardar solo el ID del usuario (no el nombre como string)
  // Mientras tanto, se envían ambos formatos simultáneamente (ver comentarios B-1/B-4/B-5 abajo).
  const saveProtocol = useCallback((form, meta) => {
    const staff = form.staff || {};
    const payload = {
      form_id:                  form.form_id,
      hc_number:                form.hc_number,
      procedimiento_id:         form.proc_codigo || form.procedimiento_id || '',
      membrete:                 form.membrete || '',
      dieresis:                 form.dieresis || '',
      exposicion:               form.exposicion || '',
      hallazgo:                 form.hallazgo || '',
      operatorio:               form.operatorio || '',
      complicaciones_operatorio: form.complicaciones_operatorio || '',
      lateralidad:              form.lateralidad || '',
      tipo_anestesia:           form.tipo_anestesia || '',
      hora_inicio:              form.hora_inicio || '',
      hora_fin:                 form.hora_fin || '',
      fecha_inicio:             form.fecha_inicio || '',
      fecha_fin:                form.fecha_fin || '',
      cirujano_1:               staff.cirujano_1 || '',
      cirujano_2:               staff.cirujano_2 || '',
      primer_ayudante:          staff.primer_ayudante || '',
      segundo_ayudante:         staff.segundo_ayudante || '',
      tercer_ayudante:          staff.tercer_ayudante || '',
      anestesiologo:            staff.anestesiologo || '',
      ayudante_anestesia:       staff.ayudante_anestesia || '',
      instrumentista:           staff.instrumentista || '',
      circulante:               staff.circulante || '',
      // B-1 compat: PDF/sync expect {procInterno: "CODIGO - Nombre"}.
      // React works internally with {codigo, nombre}; both keys are sent for the transition.
      procedimientos: JSON.stringify(
        (form.procedimientos || []).map((p) => ({
          procInterno: p.codigo && p.nombre
            ? `${p.codigo} - ${p.nombre}`
            : (p.nombre || p.codigo || ''),
          codigo:  p.codigo || '',
          nombre:  p.nombre || '',
        }))
      ),
      // Diagnosticos: backend guardar() parses idDiagnostico = "CIE10 - detalle" for diagnosticos_asignados table
      diagnosticos: JSON.stringify(
        (form.diagnosticos || []).map((d) => ({
          ojo:           d.ojo || '',
          idDiagnostico: d.cie10 ? `${d.cie10}${d.detalle ? ` - ${d.detalle}` : ''}` : '',
          cie10:         d.cie10 || '',
          detalle:       d.detalle || '',
          evidencia:     d.evidencia || '',
          observaciones: d.observaciones || '',
        }))
      ),
      diagnosticos_previos: JSON.stringify(
        (form.diagnosticos_previos || []).map((p) => ({
          cie10:       p.cie10 || '',
          descripcion: p.descripcion || p.detalle || '',
        }))
      ),
      insumos:                  JSON.stringify(form.insumos || {}),
      // B-4/B-5 compat: PDF/legacy expect {medicamento, via_administracion}.
      // React works with {nombre, via}; both keys are sent for the transition.
      medicamentos: JSON.stringify(
        (form.medicamentos || []).map((m) => ({
          id:                 m.id || '',
          nombre:             m.nombre || m.medicamento || '',
          medicamento:        m.nombre || m.medicamento || '',
          dosis:              m.dosis || '',
          via:                m.via || m.via_administracion || '',
          via_administracion: m.via || m.via_administracion || '',
          responsable:        m.responsable || '',
          frecuencia:         m.frecuencia || '',
        }))
      ),
      status:                   String(meta.status ?? 0),
    };

    postJson(endpoints.guardar, payload)
      .then((res) => {
        if (!res.success) throw new Error(res.message || 'Error al guardar');
        setWizardRow(null);
        reload();
        showToast(
          meta.status === 1 ? 'Protocolo guardado y marcado como revisado' : 'Protocolo guardado',
          'mdi-check-circle',
        );
      })
      .catch((e) => {
        showToast(e.message || 'Error al guardar el protocolo', 'mdi-alert-circle', 'warn');
      });
  }, [endpoints.guardar, postJson, reload, showToast]);

  const printRow = useCallback((row) => {
    if (row.estado !== 'revisado' && row.status !== 1) {
      window.Swal?.fire({ icon: 'warning', title: 'Pendiente revisión', text: 'Debe revisar el protocolo antes de imprimir.' });
      return;
    }
    window.open(`/v2/reports/protocolo/pdf?form_id=${encodeURIComponent(row.form_id)}&hc_number=${encodeURIComponent(row.hc_number)}`, '_blank');
    if (endpoints.printed) {
      fetch(endpoints.printed, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': window.csrfToken || '', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ form_id: row.form_id, hc_number: row.hc_number, printed: 1 }),
      }).then(() => reload()).catch(() => {});
    }
    showToast(`Imprimiendo protocolo ${row.form_id}…`, 'mdi-printer');
  }, [endpoints.printed, reload, showToast]);

  const printSelected = useCallback(() => {
    const n = selectedIds.size || 1;
    showToast(`Preparando impresión de ${n} protocolo${n !== 1 ? 's' : ''}…`, 'mdi-printer');
  }, [selectedIds, showToast]);

  const activeTabObj = TABS.find((tb) => tb.key === activeTab);

  const shellClass = [
    'app-shell',
    tweaks.density === 'compacto' ? 'density-compact' : '',
    tweaks.afilColor ? '' : 'no-afil-color',
    tweaks.highlightAlerts ? '' : 'no-alert-highlight',
  ].filter(Boolean).join(' ');

  return (
    <div className={shellClass}>
      <div className="page">
        <div className="page-head">
          <div>
            <h2>Reporte de cirugías</h2>
            <div className="sub">Listado de cirugías realizadas · revisión y auditoría del protocolo quirúrgico</div>
          </div>
          <div className="head-actions">
            <button className="btn btn-ghost btn-sm" onClick={() => setHelpOpen(true)}>
              <i className="mdi mdi-help-circle-outline" /> Cómo funciona
            </button>
            <button className="btn btn-ghost btn-sm" onClick={printSelected}>
              <i className="mdi mdi-printer" /> Imprimir lista
            </button>
            <button className="btn btn-ghost btn-sm" onClick={() => setTweakOpen((v) => !v)}>
              <i className="mdi mdi-tune-variant" /> Vista
            </button>
          </div>
        </div>

        <KpiRow metrics={metrics} kpiFilter={kpiFilter} onKpi={onKpi} />

        <div className="card">
          <Tabs activeTab={activeTab} counts={counts}
            onChange={(k) => { setActiveTab(k); setKpiFilter(''); }}
            onTabHelp={(k) => setTabHelpKey(k)} />
          {activeTabObj && (
            <TabDescription tab={activeTabObj} onMore={(k) => setTabHelpKey(k)} />
          )}
          <Filters
            filters={filters}
            setFilters={setFilters}
            onClear={() => { setFilters({ ...EMPTY_FILTERS, from: fechaInicioDefault, to: fechaFinDefault }); setKpiFilter(''); }}
            afiliacionOptions={afiliacionOptions}
            sedeOptions={sedeOptions}
          />
          <BulkBar tab={activeTab} count={selectedIds.size} onPrint={printSelected} onClear={() => setSelectedIds(new Set())} />
          <CirTable
            rows={visibleRows}
            tab={activeTab}
            loading={loading}
            error={error}
            selectedIds={selectedIds}
            onToggle={toggle}
            onToggleAll={toggleAll}
            onVerProtocolo={(r) => setProtocolRow(r)}
            onRevisar={openWizard}
            onPrint={printRow}
            onCertificado={(r) => setCertRow(r)}
          />
        </div>
      </div>

      {protocolRow && (
        <ProtocolModal
          row={protocolRow}
          endpoints={endpoints}
          onClose={() => setProtocolRow(null)}
          onRevisar={openWizard}
          onPrintToggle={printRow}
        />
      )}
      {certRow && (
        <CertificadoModal row={certRow} endpoints={endpoints} onClose={() => setCertRow(null)} />
      )}
      {helpOpen && <HelpModal onClose={() => setHelpOpen(false)} />}
      {tabHelpKey && <TabHelpModal tabKey={tabHelpKey} onClose={() => setTabHelpKey(null)} />}

      <Toast toast={toast} />

      {tweakOpen && <TweakPanel tweaks={tweaks} setTweak={setTweak} />}

      {wizardLoading && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9998, background: 'rgba(0,0,0,0.35)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ background: '#fff', borderRadius: 12, padding: '28px 36px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14 }}>
            <div className="spin" style={{ width: 36, height: 36, borderWidth: 3 }} />
            <div style={{ fontSize: 14, color: 'var(--fg-2)' }}>Cargando protocolo…</div>
          </div>
        </div>
      )}
      {wizardRow && (
        <ProtocolWizard
          form={wizardRow}
          endpoints={endpoints}
          onClose={() => setWizardRow(null)}
          onSave={saveProtocol}
          showToast={showToast}
        />
      )}
    </div>
  );
}
