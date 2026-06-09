import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Header, KpiCards, Tabs, FiltersBar, SurgeryTable, Pagination, TweaksPanel, Toast } from './components';
import { ProtocolModal, CertificadoModal } from './modals';

const TABS = [
  { key: 'all',          label: 'Todos' },
  { key: 'por_revisar',  label: 'Por revisar' },
  { key: 'alertas',      label: 'Con alertas' },
  { key: 'conforme',     label: 'Revisados' },
  { key: 'sin_protocolo', label: 'Sin protocolo' },
];

function useDatatable(endpoints, filters, page, pageSize, search, sortCol, sortDir) {
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [filtered, setFiltered] = useState(0);
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
      start: String(page * pageSize),
      length: String(pageSize),
      'search[value]': search,
      'order[0][column]': String(sortCol),
      'order[0][dir]': sortDir,
      fecha_inicio: filters.fecha_inicio || '',
      fecha_fin: filters.fecha_fin || '',
      afiliacion: filters.afiliacion || '',
      afiliacion_categoria: filters.afiliacion_categoria || '',
      sede: filters.sede || '',
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
        setRows(data.data || []);
        setTotal(data.recordsTotal || 0);
        setFiltered(data.recordsFiltered || 0);
        setLoading(false);
      })
      .catch((e) => { setError(e.message); setLoading(false); });
  }, [endpoints.datatable, filters, page, pageSize, search, sortCol, sortDir]);

  useEffect(() => { load(); }, [load]);

  return { rows, total, filtered, loading, error, reload: load };
}

export default function App({ config }) {
  const {
    afiliacionOptions = [],
    afiliacionCategoriaOptions = [],
    sedeOptions = [],
    fechaInicioDefault = '',
    fechaFinDefault = '',
    endpoints = {},
  } = config;

  const [filters, setFilters] = useState({
    fecha_inicio: fechaInicioDefault,
    fecha_fin: fechaFinDefault,
    afiliacion: '',
    afiliacion_categoria: '',
    sede: '',
  });
  const [pendingFilters, setPendingFilters] = useState(filters);
  const [search, setSearch] = useState('');
  const [activeTab, setActiveTab] = useState('all');
  const [page, setPage] = useState(0);
  const [pageSize] = useState(25);
  const [sortCol, setSortCol] = useState(4);
  const [sortDir, setSortDir] = useState('desc');

  // Tweaks state
  const [tweaksOpen, setTweaksOpen] = useState(false);
  const [density, setDensity] = useState('comodo'); // 'comodo' | 'compacto'
  const [colorByAfil, setColorByAfil] = useState(true);
  const [highlightAlerts, setHighlightAlerts] = useState(true);
  const [accentColor, setAccentColor] = useState('#4361ee');

  const [protocolRow, setProtocolRow] = useState(null);
  const [certRow, setCertRow] = useState(null);
  const [toast, setToast] = useState(null);

  const showToast = (msg, type = 'success') => setToast({ msg, type });

  const applyFilters = () => { setFilters(pendingFilters); setPage(0); };
  const clearFilters = () => {
    const def = { fecha_inicio: fechaInicioDefault, fecha_fin: fechaFinDefault, afiliacion: '', afiliacion_categoria: '', sede: '' };
    setPendingFilters(def);
    setFilters(def);
    setSearch('');
    setPage(0);
  };

  const { rows, total, filtered, loading, error, reload } = useDatatable(
    endpoints, filters, page, pageSize, search, sortCol, sortDir,
  );

  // Tab counts from loaded rows
  const counts = rows.reduce((acc, r) => {
    acc[r.audit_status] = (acc[r.audit_status] || 0) + 1;
    return acc;
  }, {});

  const visibleRows = activeTab === 'all' ? rows : rows.filter((r) => r.audit_status === activeTab);

  const handleSort = (col) => {
    if (sortCol === col) setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    else { setSortCol(col); setSortDir('asc'); }
    setPage(0);
  };

  const handlePrintToggle = (row) => {
    const willBePrinted = !row.printed;
    if (willBePrinted && row.estado !== 'revisado') {
      window.Swal?.fire({ icon: 'warning', title: 'Pendiente revisión', text: 'Debe revisar el protocolo antes de imprimir.' });
      return;
    }
    if (willBePrinted) {
      window.open(`/v2/reports/protocolo/pdf?form_id=${encodeURIComponent(row.form_id)}&hc_number=${encodeURIComponent(row.hc_number)}`, '_blank');
    }
    fetch(endpoints.printed, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': window.csrfToken || '', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({ form_id: row.form_id, hc_number: row.hc_number, printed: willBePrinted ? 1 : 0 }),
    }).then((r) => r.json()).then((d) => { if (!d.success) throw new Error(); reload(); })
      .catch(() => showToast('No se pudo actualizar el estado de impresión', 'error'));
  };

  const totalPages = Math.ceil(filtered / pageSize);

  return (
    <div className="cir-shell" style={{ '--cir-accent': accentColor }}>
      <div className="cir-page">
        <Header onTweaks={() => setTweaksOpen((v) => !v)} />

        <KpiCards rows={rows} total={total} activeTab={activeTab} onTabChange={(t) => { setActiveTab(t); setPage(0); }} />

        <div className="cir-card">
          <Tabs tabs={TABS} counts={counts} active={activeTab} onChange={(t) => { setActiveTab(t); setPage(0); }} totalFiltered={filtered} />

          <FiltersBar
            pending={pendingFilters}
            onChange={setPendingFilters}
            onApply={applyFilters}
            onClear={clearFilters}
            search={search}
            onSearch={(v) => { setSearch(v); setPage(0); }}
            afiliacionOptions={afiliacionOptions}
            afiliacionCategoriaOptions={afiliacionCategoriaOptions}
            sedeOptions={sedeOptions}
          />

          <SurgeryTable
            rows={visibleRows}
            loading={loading}
            error={error}
            sortCol={sortCol}
            sortDir={sortDir}
            onSort={handleSort}
            onViewProtocol={setProtocolRow}
            onCertificado={setCertRow}
            onPrintToggle={handlePrintToggle}
            onEdit={(row) => { window.location.href = `${endpoints.wizard}?form_id=${encodeURIComponent(row.form_id)}&hc_number=${encodeURIComponent(row.hc_number)}`; }}
            density={density}
            colorByAfil={colorByAfil}
            highlightAlerts={highlightAlerts}
          />

          {!loading && totalPages > 1 && (
            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
          )}
        </div>
      </div>

      {tweaksOpen && (
        <TweaksPanel
          density={density} onDensity={setDensity}
          colorByAfil={colorByAfil} onColorByAfil={setColorByAfil}
          highlightAlerts={highlightAlerts} onHighlightAlerts={setHighlightAlerts}
          accentColor={accentColor} onAccentColor={setAccentColor}
          onClose={() => setTweaksOpen(false)}
        />
      )}

      {protocolRow && (
        <ProtocolModal
          row={protocolRow}
          endpoints={endpoints}
          onClose={() => setProtocolRow(null)}
          onToast={showToast}
          onPrintToggle={handlePrintToggle}
        />
      )}

      {certRow && (
        <CertificadoModal row={certRow} onClose={() => setCertRow(null)} />
      )}

      {toast && (
        <Toast message={toast.msg} type={toast.type} onClose={() => setToast(null)} />
      )}
    </div>
  );
}
