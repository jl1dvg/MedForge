import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Filters, KpiRow, SurgeryTable, Toast } from './components';
import { ProtocolModal, CertificadoPrompt } from './modals';

function useDatatable(endpoints, filters, page, pageSize, search, sortCol, sortDir) {
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [filtered, setFiltered] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const drawRef = useRef(0);

  const fetch_ = useCallback(() => {
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

    window
      .fetch(endpoints.datatable, {
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
      .catch((e) => {
        setError(e.message);
        setLoading(false);
      });
  }, [endpoints.datatable, filters, page, pageSize, search, sortCol, sortDir]);

  useEffect(() => { fetch_(); }, [fetch_]);

  return { rows, total, filtered, loading, error, reload: fetch_ };
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
  const [page, setPage] = useState(0);
  const [pageSize] = useState(25);
  const [sortCol, setSortCol] = useState(4);
  const [sortDir, setSortDir] = useState('desc');

  const [protocolRow, setProtocolRow] = useState(null);
  const [certRow, setCertRow] = useState(null);
  const [toast, setToast] = useState(null);

  const showToast = (msg, type = 'success') => setToast({ msg, type });

  const applyFilters = () => {
    setFilters(pendingFilters);
    setPage(0);
  };

  const clearFilters = () => {
    const def = {
      fecha_inicio: fechaInicioDefault,
      fecha_fin: fechaFinDefault,
      afiliacion: '',
      afiliacion_categoria: '',
      sede: '',
    };
    setPendingFilters(def);
    setFilters(def);
    setSearch('');
    setPage(0);
  };

  const { rows, total, filtered, loading, error, reload } = useDatatable(
    endpoints,
    filters,
    page,
    pageSize,
    search,
    sortCol,
    sortDir,
  );

  const handleSort = (col) => {
    if (sortCol === col) setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    else { setSortCol(col); setSortDir('asc'); }
    setPage(0);
  };

  const handlePrintToggle = (row, isActive) => {
    fetch(endpoints.printed, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': window.csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams({
        form_id: row.form_id,
        hc_number: row.hc_number,
        printed: isActive ? 1 : 0,
      }),
    })
      .then((r) => r.json())
      .then((d) => {
        if (!d.success) throw new Error('Error al actualizar');
        if (isActive && !row._wasPrinted) {
          window.open(
            `/v2/reports/protocolo/pdf?form_id=${encodeURIComponent(row.form_id)}&hc_number=${encodeURIComponent(row.hc_number)}`,
            '_blank',
          );
        }
        reload();
      })
      .catch(() => showToast('No se pudo actualizar el estado de impresión', 'error'));
  };

  const totalPages = Math.ceil(filtered / pageSize);

  return (
    <div className="cir-shell">
      <div className="cir-page">
        <div className="cir-page-head">
          <div>
            <h2>Reporte de Cirugías</h2>
            <div className="cir-page-sub">
              {filtered !== total
                ? `${filtered.toLocaleString()} de ${total.toLocaleString()} registros`
                : `${total.toLocaleString()} registros en total`}
            </div>
          </div>
          <div className="cir-head-actions">
            <a href="/v2/cirugias/dashboard" className="cir-btn cir-btn-ghost cir-btn-sm">
              <i className="mdi mdi-chart-line" /> Dashboard
            </a>
            <a href="/v2/cirugias/wizard" className="cir-btn cir-btn-primary cir-btn-sm">
              <i className="mdi mdi-plus" /> Nueva cirugía
            </a>
          </div>
        </div>

        <KpiRow rows={rows} total={total} />

        <div className="cir-card">
          <Filters
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
            rows={rows}
            loading={loading}
            error={error}
            sortCol={sortCol}
            sortDir={sortDir}
            onSort={handleSort}
            onViewProtocol={setProtocolRow}
            onCertificado={setCertRow}
            onPrintToggle={handlePrintToggle}
          />

          {!loading && totalPages > 1 && (
            <div className="cir-pagination">
              <button
                className="cir-btn cir-btn-ghost cir-btn-sm"
                disabled={page === 0}
                onClick={() => setPage((p) => p - 1)}
              >
                <i className="mdi mdi-chevron-left" /> Anterior
              </button>
              <span className="cir-page-info">
                Página {page + 1} de {totalPages}
              </span>
              <button
                className="cir-btn cir-btn-ghost cir-btn-sm"
                disabled={page >= totalPages - 1}
                onClick={() => setPage((p) => p + 1)}
              >
                Siguiente <i className="mdi mdi-chevron-right" />
              </button>
            </div>
          )}
        </div>
      </div>

      {protocolRow && (
        <ProtocolModal
          row={protocolRow}
          endpoints={endpoints}
          onClose={() => setProtocolRow(null)}
          onToast={showToast}
        />
      )}

      {certRow && (
        <CertificadoPrompt
          row={certRow}
          onClose={() => setCertRow(null)}
        />
      )}

      {toast && (
        <Toast
          message={toast.msg}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}
    </div>
  );
}
