import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { TABS, TIPOS } from './catalog';
import { inferTipoKey, inferOjo } from './helpers';
import { KpiRow, Tabs, TabDescription, DateRangeBanner, Filters, Toast } from './components';
import { BulkBar, ExamTable } from './table';
import { InformarModal, VerImagenesModal, ReclamoArchivosModal, MarcarUrgenteModal, HelpModal, TabHelpModal } from './modals';

// Filters that are applied server-side (reload page on change)
const SERVER_FILTER_KEYS = ['from', 'to', 'afiliacion', 'sede', 'tipo'];

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function parseJsonResponse(response) {
  const text = await response.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch (e) {
    throw new Error('El servidor devolvió una respuesta inválida al verificar el NAS.');
  }

  if (!response.ok || !data || data.success === false) {
    throw new Error(data?.error || 'No se pudo verificar el NAS.');
  }

  return data;
}

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
    file_claim_id: raw.file_claim_id || null,
    file_claim_status: raw.file_claim_status || null,
    file_claim_requested_at: raw.file_claim_requested_at || null,
    // priority fields – from server (imagenes_bandeja_prioridad join)
    prioridad: raw.bandeja_prioridad || null,
    fecha_limite: raw.bandeja_fecha_limite || null,
    responsable: raw.bandeja_responsable || null,
    motivo: raw.bandeja_motivo || null,
    wpp_status: raw.wpp_status || (raw.informado ? 'pendiente' : 'no-aplica'),
  };
}

export default function App({ config }) {
  const { today, currentUser, doctores, afiliacionesData = [], defaultResponsable = '', serverFilters = {}, baseUrl = '' } = config;

  const initialRows = useMemo(() => {
    return (config.rows || []).map((raw) => normalizeRow(raw));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const [rows, setRows] = useState(initialRows);
  const [activeTab, setActiveTab] = useState('no-informados');
  // Initialize client-side search from nothing; server filters already applied
  const [filters, setFilters] = useState({ search: '', ...serverFilters });
  const [pendingServerFilters, setPendingServerFilters] = useState({ ...serverFilters });
  const [kpiFilter, setKpiFilter] = useState('');
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [toast, setToast] = useState(null);
  const [recheckingIds, setRecheckingIds] = useState(() => new Set());
  const [claimRow, setClaimRow] = useState(null);
  const [claimLoading, setClaimLoading] = useState(false);

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
    // Optimistic update
    setRows((rs) => rs.map((r) => ids.includes(r.id) ? { ...r, ...data } : r));
    setUrgenteRows(null);
    setSelectedIds(new Set());
    showToast(ids.length > 1 ? `${ids.length} exámenes enviados a la bandeja prioritaria` : 'Examen en la bandeja prioritaria', 'mdi-bell-check');

    // Persist to DB
    const procedimientoIds = rows.filter((r) => ids.includes(r.id)).map((r) => r.id);
    const formIds = rows.filter((r) => ids.includes(r.id)).map((r) => r.form_id ? String(r.form_id) : null);
    fetch('/v2/imagenes/bandeja', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      body: JSON.stringify({
        procedimiento_ids: procedimientoIds,
        form_ids: formIds,
        prioridad: data.prioridad,
        fecha_limite: data.fecha_limite || null,
        responsable: data.responsable || null,
        motivo: data.motivo,
      }),
    }).catch(() => showToast('Error al guardar en la bandeja', 'mdi-alert', 'warn'));
  }, [showToast, rows]);

  const quitarBandeja = useCallback((row) => {
    // Optimistic update
    setRows((rs) => rs.map((r) => r.id === row.id ? { ...r, prioridad: null, fecha_limite: null, responsable: null, motivo: null } : r));
    showToast('Quitado de la bandeja prioritaria', 'mdi-bell-off', 'warn');

    // Remove from DB
    fetch(`/v2/imagenes/bandeja/${row.id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
    }).catch(() => showToast('Error al quitar de la bandeja', 'mdi-alert', 'warn'));
  }, [showToast]);

  const updateRowWithNasFiles = useCallback((row, files) => {
    const normalizedFiles = Array.isArray(files) ? files : [];
    const updatedRow = {
      ...row,
      nas_status: normalizedFiles.length > 0 ? 'con-archivos' : 'sin-archivos',
      nas_files_count: normalizedFiles.length,
      nas_files: normalizedFiles,
      file_claim_id: normalizedFiles.length > 0 ? null : row.file_claim_id,
      file_claim_status: normalizedFiles.length > 0 ? null : row.file_claim_status,
      file_claim_requested_at: normalizedFiles.length > 0 ? null : row.file_claim_requested_at,
    };

    setRows((rs) => rs.map((r) => r.id === row.id ? updatedRow : r));
    return updatedRow;
  }, []);

  const markRowClaimed = useCallback((row, claim) => {
    const updatedRow = {
      ...row,
      file_claim_id: claim?.id || row.file_claim_id || null,
      file_claim_status: claim?.status || row.file_claim_status || 'abierto',
      file_claim_requested_at: claim?.requested_at || row.file_claim_requested_at || null,
    };

    setRows((rs) => rs.map((r) => r.id === row.id ? updatedRow : r));
    return updatedRow;
  }, []);

  const postNasRecheck = useCallback((row, { createClaim = false, message = '' } = {}) => {
    return fetch('/v2/imagenes/examenes-realizados/nas/recheck', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({
        id: row.id,
        form_id: row.form_id,
        hc_number: row.hc_number,
        full_name: row.full_name,
        paciente: row.full_name,
        cedula: row.cedula,
        tipo_examen: row.tipo_label,
        tipo_label: row.tipo_label,
        ojo: row.ojo,
        afiliacion: row.afiliacion,
        sede: row.sede,
        fecha_examen: row.fecha_examen,
        create_claim: createClaim,
        message,
      }),
    }).then(parseJsonResponse);
  }, []);

  const revisarArchivos = useCallback((row) => {
    setRecheckingIds((prev) => new Set(prev).add(row.id));
    postNasRecheck(row)
      .then((data) => {
        if (data.found) {
          const updatedRow = updateRowWithNasFiles(row, data.files || []);
          setClaimRow(null);
          setActiveTab('no-informados');
          setVerImagenesRow(updatedRow);
          showToast(`${data.files_count || 0} archivo(s) encontrados. Ya puedes informar.`, 'mdi-folder-check-outline');
          return;
        }
        if (row.file_claim_id) {
          setClaimRow(null);
          showToast(`Sigue sin archivos. Reclamo #${row.file_claim_id} abierto.`, 'mdi-folder-alert-outline', 'warn');
          return;
        }
        setClaimRow(row);
        showToast('El NAS sigue sin archivos. Puedes crear el reclamo operativo.', 'mdi-folder-alert-outline', 'warn');
      })
      .catch((e) => {
        showToast(e.message || 'No se pudo verificar el NAS. No se creó reclamo.', 'mdi-alert-circle-outline', 'warn');
      })
      .finally(() => {
        setRecheckingIds((prev) => {
          const next = new Set(prev);
          next.delete(row.id);
          return next;
        });
      });
  }, [postNasRecheck, showToast, updateRowWithNasFiles]);

  const confirmarReclamoArchivos = useCallback((row, message) => {
    setClaimLoading(true);
    postNasRecheck(row, { createClaim: true, message })
      .then((data) => {
        if (data.found) {
          const updatedRow = updateRowWithNasFiles(row, data.files || []);
          setClaimRow(null);
          setActiveTab('no-informados');
          setVerImagenesRow(updatedRow);
          showToast(`${data.files_count || 0} archivo(s) encontrados. No se creó reclamo.`, 'mdi-folder-check-outline');
          return;
        }
        if (data.claim) {
          markRowClaimed(row, data.claim);
          setClaimRow(null);
          showToast(`Reclamo #${data.claim.id} creado para revisión de archivos.`, 'mdi-send-check-outline');
          return;
        }
        throw new Error('No se pudo crear el reclamo.');
      })
      .catch((e) => {
        showToast(e.message || 'No se pudo crear el reclamo.', 'mdi-alert-circle-outline', 'warn');
      })
      .finally(() => setClaimLoading(false));
  }, [markRowClaimed, postNasRecheck, showToast, updateRowWithNasFiles]);

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
            afiliaciones={afiliacionesData}
          />
          <BulkBar tab={activeTab} count={selectedIds.size}
            selectedRows={rows.filter((r) => selectedIds.has(r.id))}
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
            onRevisarArchivos={revisarArchivos}
            recheckingIds={recheckingIds}
          />
        </div>
      </div>

      {informarRow && (
        <InformarModal row={informarRow} readOnly={informarRow.informado}
          onClose={() => setInformarRow(null)} onSave={saveInforme}
          showToast={showToast} doctores={doctores} />
      )}
      {verImagenesRow && <VerImagenesModal row={verImagenesRow} onClose={() => setVerImagenesRow(null)} />}
      {claimRow && (
        <ReclamoArchivosModal
          row={claimRow}
          loading={claimLoading}
          onClose={() => setClaimRow(null)}
          onConfirm={confirmarReclamoArchivos}
        />
      )}
      {urgenteRows && (
        <MarcarUrgenteModal rows={urgenteRows} doctores={doctores} today={today}
          currentUser={currentUser} defaultResponsable={defaultResponsable} onClose={() => setUrgenteRows(null)} onConfirm={confirmUrgente} />
      )}
      {helpOpen && <HelpModal onClose={() => setHelpOpen(false)} />}
      {tabHelpKey && <TabHelpModal tabKey={tabHelpKey} onClose={() => setTabHelpKey(null)} />}

      <Toast toast={toast} />
    </div>
  );
}
