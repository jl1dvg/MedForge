import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { TABS, TIPOS } from './catalog';
import { inferTipoKey, inferOjo, getBandejaStore, setBandejaStore } from './helpers';
import { KpiRow, Tabs, TabDescription, DateRangeBanner, Filters, Toast } from './components';
import { BulkBar, ExamTable } from './table';
import { InformarModal, VerImagenesModal, MarcarUrgenteModal, HelpModal, TabHelpModal } from './modals';

// Filters that are applied server-side (reload page on change)
const SERVER_FILTER_KEYS = ['from', 'to', 'afiliacion', 'sede', 'tipo'];

function buildUrl(baseUrl, serverFilters) {
  const params = new URLSearchParams();
  if (serverFilters.from)       params.set('fecha_inicio', serverFilters.from);
  if (serverFilters.to)         params.set('fecha_fin',    serverFilters.to);
  if (serverFilters.afiliacion) params.set('afiliacion',   serverFilters.afiliacion);
  if (serverFilters.sede)       params.set('sede',         serverFilters.sede);
  if (serverFilters.tipo)       params.set('tipo_examen',  serverFilters.tipo);
  const qs = params.toString();
  return qs ? `${baseUrl}?${qs}` : baseUrl;
}

// Transform backend row to the shape the UI expects
function normalizeRow(raw) {
  const procedureText = raw.tipo_examen || raw.procedimiento || '';
  const tipoKey = inferTipoKey(procedureText);
  const tipoInfo = TIPOS.find((t) => t.key === tipoKey);
  const nasHasFiles = Boolean(raw.nas_has_files || raw.nas_files_count > 0);

  // Build synthetic file list from count (real file info comes from NAS API if needed)
  const nasFiles = [];
  const count = parseInt(raw.nas_files_count, 10) || 0;
  for (let i = 0; i < count; i++) {
    nasFiles.push({
      name: `${(tipoInfo?.short || 'examen').replace(/\s+/g, '_')}_${i + 1}.jpg`,
      type: 'image',
      size: '—',
    });
  }

  return {
    id: raw.id ?? raw.form_id,
    form_id: raw.form_id,
    hc_number: raw.hc_number,
    full_name: raw.paciente || raw.full_name || '—',
    cedula: raw.cedula || '—',
    fecha_examen: raw.fecha_cita || raw.fecha_examen || '',
    estado_agenda: raw.estado_agenda || '',
    afiliacion: raw.afiliacion || raw.afiliacion_empresa || '',
    afiliacion_cat: raw.afiliacion_categoria || raw.afiliacion_cat || 'otros',
    sede: raw.sede || '',
    tipo_key: tipoKey,
    tipo_label: tipoInfo?.label || raw.tipo_examen || raw.procedimiento || '—',
    tipo_short: tipoInfo?.short || raw.tipo_examen || '—',
    equipo: tipoInfo?.equipo || '',
    ojo: raw.ojo || inferOjo(procedureText),
    informado: Boolean(raw.informado),
    informe_id: raw.informe_id || null,
    informado_por: raw.informado_por || raw.informe_firmado_por || null,
    informado_fecha: raw.informado_fecha || raw.informe_actualizado || null,
    nas_status: nasHasFiles ? 'con-archivos' : 'sin-archivos',
    nas_files_count: count,
    nas_files: nasFiles,
    // priority fields – merged from localStorage bandeja store
    prioridad: null,
    fecha_limite: null,
    responsable: null,
    motivo: null,
    wpp_status: raw.wpp_status || (raw.informado ? 'pendiente' : 'no-aplica'),
  };
}

export default function App({ config }) {
  const { today, currentUser, doctores, serverFilters = {}, baseUrl = '' } = config;

  // Merge bandeja (client-side priority overrides) on first load
  const initialRows = useMemo(() => {
    const store = getBandejaStore();
    return (config.rows || []).map((raw) => {
      const r = normalizeRow(raw);
      const saved = store[r.id];
      return saved ? { ...r, ...saved } : r;
    });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const [rows, setRows] = useState(initialRows);
  const [activeTab, setActiveTab] = useState('no-informados');
  // Initialize client-side search from nothing; server filters already applied
  const [filters, setFilters] = useState({ search: '', ...serverFilters });
  const [pendingServerFilters, setPendingServerFilters] = useState({ ...serverFilters });
  const [kpiFilter, setKpiFilter] = useState('');
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [toast, setToast] = useState(null);

  // modals
  const [informarRow, setInformarRow] = useState(null);
  const [verImagenesRow, setVerImagenesRow] = useState(null);
  const [urgenteRows, setUrgenteRows] = useState(null);
  const [helpOpen, setHelpOpen] = useState(false);
  const [tabHelpKey, setTabHelpKey] = useState(null);

  const showToast = useCallback((msg, icon = 'mdi-check-circle', tone = 'ok') => {
    setToast({ msg, icon, tone });
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => setToast(null), 2800);
  }, []);

  // ---- Tab membership ----
  const inTab = useCallback((r, tab) => {
    if (tab === 'informados') return r.informado;
    if (tab === 'sin-nas') return !r.informado && r.nas_status === 'sin-archivos';
    if (tab === 'bandeja') return !r.informado && !!r.prioridad;
    return !r.informado && r.nas_status === 'con-archivos';
  }, []);

  // ---- Filters ----
  // Server already filtered by from/to/afiliacion/sede/tipo — only apply search client-side
  const matchesFilters = useCallback((r) => {
    const q = filters.search.trim().toLowerCase();
    if (q && !(`${r.full_name} ${r.cedula} ${r.hc_number} ${r.tipo_label}`.toLowerCase().includes(q))) return false;
    return true;
  }, [filters.search]);

  // Reload page with new server-side filters
  const applyServerFilters = useCallback(() => {
    window.location.href = buildUrl(baseUrl, pendingServerFilters);
  }, [baseUrl, pendingServerFilters]);

  const clearAllFilters = useCallback(() => {
    window.location.href = baseUrl;
  }, [baseUrl]);

  // ---- Tab counts ----
  const counts = useMemo(() => {
    const c = { 'no-informados': 0, 'bandeja': 0, 'informados': 0, 'sin-nas': 0 };
    rows.forEach((r) => {
      if (!matchesFilters(r)) return;
      TABS.forEach((tb) => { if (inTab(r, tb.key)) c[tb.key]++; });
    });
    return c;
  }, [rows, matchesFilters, inTab]);

  // ---- KPI metrics ----
  const metrics = useMemo(() => {
    let porInformar = 0, bandeja = 0, vencidos = 0, sinNas = 0, informados = 0;
    rows.forEach((r) => {
      if (r.informado) { informados++; return; }
      if (r.nas_status === 'sin-archivos') { sinNas++; return; }
      porInformar++;
      if (r.prioridad) bandeja++;
      if (r.fecha_limite && r.fecha_limite < today) vencidos++;
    });
    return { porInformar, bandeja, vencidos, sinNas, informados };
  }, [rows, today]);

  // ---- Visible rows ----
  const visibleRows = useMemo(() => {
    let list = rows.filter((r) => matchesFilters(r) && inTab(r, activeTab));
    if (kpiFilter === 'vencidos') list = list.filter((r) => r.fecha_limite && r.fecha_limite < today);
    if (activeTab === 'bandeja') {
      list = list.slice().sort((a, b) => {
        const pa = a.prioridad === 'urgente' ? 0 : 1, pb = b.prioridad === 'urgente' ? 0 : 1;
        if (pa !== pb) return pa - pb;
        return (a.fecha_limite || '9999').localeCompare(b.fecha_limite || '9999');
      });
    } else {
      list = list.slice().sort((a, b) => b.fecha_examen.localeCompare(a.fecha_examen));
    }
    return list;
  }, [rows, matchesFilters, inTab, activeTab, kpiFilter, today]);

  useEffect(() => { setSelectedIds(new Set()); }, [activeTab]);

  // ---- KPI click ----
  const onKpi = useCallback((key) => {
    setKpiFilter((prev) => (prev === key ? '' : key));
    if (key === 'por-informar') setActiveTab('no-informados');
    else if (key === 'bandeja') setActiveTab('bandeja');
    else if (key === 'vencidos') setActiveTab('bandeja');
    else if (key === 'sin-nas') setActiveTab('sin-nas');
    else if (key === 'informados') setActiveTab('informados');
  }, []);

  // ---- Selection ----
  const toggle = useCallback((id) => {
    setSelectedIds((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
  }, []);
  const toggleAll = useCallback(() => {
    setSelectedIds((prev) => {
      const allIds = visibleRows.map((r) => r.id);
      return allIds.every((id) => prev.has(id)) ? new Set() : new Set(allIds);
    });
  }, [visibleRows]);

  // ---- Actions ----
  const saveInforme = useCallback((row, { notify, auto }) => {
    setRows((rs) => rs.map((r) => r.id === row.id ? {
      ...r, informado: true, informe_id: `INF-${50000 + Number(r.id)}`,
      informado_por: currentUser.name, informado_fecha: today,
      prioridad: null, fecha_limite: null,
      wpp_status: notify ? 'enviado' : 'no-aplica',
    } : r));
    showToast(notify ? 'Informe guardado · aviso enviado al paciente' : 'Informe guardado', 'mdi-file-check');
    if (auto) {
      const next = visibleRows.find((r) => r.id !== row.id && !r.informado);
      setInformarRow(next || null);
    } else {
      setInformarRow(null);
    }
  }, [showToast, visibleRows, currentUser, today]);

  const confirmUrgente = useCallback((ids, data) => {
    setRows((rs) => rs.map((r) => {
      if (!ids.includes(r.id)) return r;
      const updated = { ...r, ...data };
      // Persist in localStorage for cross-reload survival
      const store = getBandejaStore();
      store[r.id] = { prioridad: data.prioridad, fecha_limite: data.fecha_limite, responsable: data.responsable, motivo: data.motivo };
      setBandejaStore(store);
      return updated;
    }));
    setUrgenteRows(null);
    setSelectedIds(new Set());
    showToast(ids.length > 1 ? `${ids.length} exámenes enviados a la bandeja prioritaria` : 'Examen en la bandeja prioritaria', 'mdi-bell-check');
  }, [showToast]);

  const quitarBandeja = useCallback((row) => {
    setRows((rs) => rs.map((r) => {
      if (r.id !== row.id) return r;
      const store = getBandejaStore();
      delete store[r.id];
      setBandejaStore(store);
      return { ...r, prioridad: null, fecha_limite: null, responsable: null, motivo: null };
    }));
    showToast('Quitado de la bandeja prioritaria', 'mdi-bell-off', 'warn');
  }, [showToast]);

  const sendSelectedToBandeja = useCallback(() => {
    const sel = rows.filter((r) => selectedIds.has(r.id) && !r.informado);
    if (sel.length) setUrgenteRows(sel);
  }, [rows, selectedIds]);

  const printRows = useCallback(() => {
    const n = selectedIds.size || 1;
    showToast(`Preparando impresión de ${n} informe${n !== 1 ? 's' : ''}…`, 'mdi-printer');
  }, [selectedIds, showToast]);

  const activeTabObj = TABS.find((tb) => tb.key === activeTab);

  return (
    <div className="imr-shell">
      <div className="imr-page">
        <div className="imr-page-head">
          <div>
            <h2>Procedimientos de imágenes</h2>
            <div className="imr-page-sub">Listado por fecha, afiliación y paciente · informe, priorización y aviso al paciente</div>
          </div>
          <div className="imr-head-actions">
            <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={() => setHelpOpen(true)}>
              <i className="mdi mdi-help-circle-outline"></i> Cómo funciona
            </button>
            <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={printRows}>
              <i className="mdi mdi-printer"></i> Imprimir lista
            </button>
          </div>
        </div>

        <KpiRow metrics={metrics} kpiFilter={kpiFilter} onKpi={onKpi} />

        <DateRangeBanner serverFilters={serverFilters} />

        <div className="imr-card">
          <Tabs activeTab={activeTab} counts={counts}
            onChange={(k) => { setActiveTab(k); setKpiFilter(''); }}
            onTabHelp={(k) => setTabHelpKey(k)} />
          <TabDescription tab={activeTabObj} onMore={(k) => setTabHelpKey(k)} />
          <Filters
            search={filters.search}
            onSearchChange={(v) => setFilters((f) => ({ ...f, search: v }))}
            serverFilters={pendingServerFilters}
            setServerFilters={setPendingServerFilters}
            onApply={applyServerFilters}
            onClear={clearAllFilters}
          />
          <BulkBar tab={activeTab} count={selectedIds.size}
            onSendBandeja={sendSelectedToBandeja} onPrint={printRows}
            onClear={() => setSelectedIds(new Set())} />
          <ExamTable
            rows={visibleRows} tab={activeTab} today={today}
            selectedIds={selectedIds} onToggle={toggle} onToggleAll={toggleAll}
            onInformar={(r) => setInformarRow(r)}
            onVerImagenes={(r) => setVerImagenesRow(r)}
            onMarcarUrgente={(r) => setUrgenteRows([r])}
            onQuitarBandeja={quitarBandeja}
            onPrint={printRows}
          />
        </div>
      </div>

      {informarRow && (
        <InformarModal row={informarRow} readOnly={informarRow.informado}
          onClose={() => setInformarRow(null)} onSave={saveInforme}
          showToast={showToast} doctores={doctores} />
      )}
      {verImagenesRow && <VerImagenesModal row={verImagenesRow} onClose={() => setVerImagenesRow(null)} />}
      {urgenteRows && (
        <MarcarUrgenteModal rows={urgenteRows} doctores={doctores} today={today}
          currentUser={currentUser} onClose={() => setUrgenteRows(null)} onConfirm={confirmUrgente} />
      )}
      {helpOpen && <HelpModal onClose={() => setHelpOpen(false)} />}
      {tabHelpKey && <TabHelpModal tabKey={tabHelpKey} onClose={() => setTabHelpKey(null)} />}

      <Toast toast={toast} />
    </div>
  );
}
