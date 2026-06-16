import React, { useState, useMemo, useEffect } from 'react';
import type { PacientesCatalogos, Patient } from '../types';
import { fmtDateShort, relDays, isFuture, isToday } from '../utils';
import { Avatar, SedeBadge, AfilChip, PatientBadges, MedicoCell, ProxCita, Kpi, RowActions } from '../components';

interface Props {
  patients: Patient[];
  loading: boolean;
  search: string;
  setSearch: (v: string) => void;
  catalogos: PacientesCatalogos | null;
  onOpen: (id: number) => void;
  onAgendar: (p: Patient) => void;
  onWhats: (p: Patient) => void;
}

export default function ListView({ patients, loading, search, setSearch, catalogos, onOpen, onAgendar, onWhats }: Props) {
  const [view, setView] = useState<'tabla' | 'tarjetas'>('tabla');
  const [filters, setFilters] = useState({ sede: '', medico: '', afiliacion: '', registro: '' });
  const [flags, setFlags] = useState({ citas: false, solicitudes: false, hoy: false });
  const [sort, setSort] = useState({ key: 'ultima_visita', dir: 'desc' });
  const [page, setPage] = useState(1);
  const [perPage] = useState(50);

  const kpis = useMemo(() => {
    const hoy = new Date();
    const total = patients.length;
    const nuevos = patients.filter(p => {
      const d = new Date(p.created_at);
      return d.getFullYear() === hoy.getFullYear() && d.getMonth() === hoy.getMonth();
    }).length;
    const conHoy = patients.filter(p => p.proxima_cita && isToday(p.proxima_cita.fecha)).length;
    const conSol = patients.filter(p => p.sol_activa > 0).length;
    return { total, nuevos, conHoy, conSol };
  }, [patients]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return patients.filter(p => {
      if (q) {
        const hay = `${p.full_name} ${p.display_name} ${p.hc_number} ${p.cedula} ${p.telefono} ${p.telefono_alt || ''} ${p.email || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      if (filters.sede && p.sede !== filters.sede) return false;
      if (filters.medico && p.medico !== filters.medico) return false;
      if (filters.afiliacion && p.tipo_afiliacion !== filters.afiliacion) return false;
      if (filters.registro) {
        const days = (Date.now() - new Date(p.ultima_visita).getTime()) / 86400000;
        if (filters.registro === '7' && days > 7) return false;
        if (filters.registro === '30' && days > 30) return false;
        if (filters.registro === '90' && days > 90) return false;
        if (filters.registro === 'old' && days <= 90) return false;
      }
      if (flags.citas && !(p.proxima_cita && isFuture(p.proxima_cita.fecha))) return false;
      if (flags.hoy && !(p.proxima_cita && isToday(p.proxima_cita.fecha))) return false;
      if (flags.solicitudes && p.sol_activa === 0) return false;
      return true;
    });
  }, [patients, search, filters, flags]);

  const sedeOptions = useMemo(() => {
    if (catalogos?.sedes?.length) {
      return catalogos.sedes.map(s => ({ id: s.id, label: s.label || s.nombre || s.id }));
    }

    return Array.from(new Map(
      patients
        .filter(p => p.sede)
        .map(p => [p.sede, { id: p.sede, label: p.sede_info?.nombre || p.sede }])
    ).values());
  }, [catalogos, patients]);

  const medicoOptions = useMemo(() => {
    if (catalogos?.medicos?.length) {
      return catalogos.medicos.map(m => ({ id: m.nombre || m.full || m.id, label: m.full || m.nombre || m.id }));
    }

    return Array.from(new Set(patients.map(p => p.medico).filter(Boolean)))
      .map(nombre => ({ id: nombre, label: nombre }));
  }, [catalogos, patients]);

  const tipoAfiliacionOptions = useMemo(() => {
    if (catalogos?.tipos_afiliacion?.length) {
      return catalogos.tipos_afiliacion.map(t => ({ id: t.id, label: t.label }));
    }

    return Array.from(new Set(patients.map(p => p.tipo_afiliacion).filter(Boolean)))
      .map(tipo => ({ id: tipo, label: tipo }));
  }, [catalogos, patients]);

  const sorted = useMemo(() => {
    const arr = filtered.slice();
    arr.sort((a, b) => {
      let av: any, bv: any;
      if (sort.key === 'full_name') { av = a.full_name; bv = b.full_name; }
      else if (sort.key === 'edad') { av = a.edad; bv = b.edad; }
      else if (sort.key === 'ultima_visita') { av = new Date(a.ultima_visita).getTime(); bv = new Date(b.ultima_visita).getTime(); }
      else if (sort.key === 'proxima') { av = a.proxima_cita ? new Date(a.proxima_cita.fecha).getTime() : 9e15; bv = b.proxima_cita ? new Date(b.proxima_cita.fecha).getTime() : 9e15; }
      else { av = (a as any)[sort.key]; bv = (b as any)[sort.key]; }
      if (typeof av === 'string') return sort.dir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
      return sort.dir === 'asc' ? av - bv : bv - av;
    });
    return arr;
  }, [filtered, sort]);

  useEffect(() => setPage(1), [filtered]);

  const pageCount = Math.ceil(sorted.length / perPage);
  const paged = sorted.slice((page - 1) * perPage, page * perPage);

  const hasFilters = filters.sede || filters.medico || filters.afiliacion || filters.registro || flags.citas || flags.solicitudes || flags.hoy || search;
  const clearAll = () => { setFilters({ sede: '', medico: '', afiliacion: '', registro: '' }); setFlags({ citas: false, solicitudes: false, hoy: false }); setSearch(''); };

  const Th = ({ k, children, style }: { k?: string; children: React.ReactNode; style?: React.CSSProperties }) => (
    <th
      style={style}
      className={k ? 'sortable' : ''}
      onClick={k ? () => setSort(s => ({ key: k, dir: s.key === k && s.dir === 'asc' ? 'desc' : 'asc' })) : undefined}
    >
      {children}
      {sort.key === k && <i className={`mdi mdi-menu-${sort.dir === 'asc' ? 'up' : 'down'} th-sort-ic`} />}
    </th>
  );

  return (
    <div className="page">
      <div className="page-inner">
        {/* KPIs */}
        <div className="pkpi-row">
          <Kpi tone="total" icon="mdi-account-multiple-outline" value={kpis.total} label="Pacientes en el sistema" onClick={null} />
          <Kpi tone="nuevos" icon="mdi-account-plus-outline" value={kpis.nuevos} label="Nuevos este mes" onClick={null} />
          <Kpi tone="hoy" icon="mdi-calendar-today" value={kpis.conHoy} label="Con cita hoy" active={flags.hoy} onClick={() => setFlags(f => ({ ...f, hoy: !f.hoy }))} />
          <Kpi tone="sol" icon="mdi-clipboard-text-clock-outline" value={kpis.conSol} label="Con solicitud activa" active={flags.solicitudes} onClick={() => setFlags(f => ({ ...f, solicitudes: !f.solicitudes }))} />
        </div>

        {/* Toolbar */}
        <div className="toolbar">
          <div className="seg">
            <button className={view === 'tabla' ? 'is-active' : ''} onClick={() => setView('tabla')}><i className="mdi mdi-table" />Tabla</button>
            <button className={view === 'tarjetas' ? 'is-active' : ''} onClick={() => setView('tarjetas')}><i className="mdi mdi-view-grid-outline" />Tarjetas</button>
          </div>

          <select className={`filter-select ${filters.sede ? 'is-set' : ''}`} value={filters.sede} onChange={e => setFilters(f => ({ ...f, sede: e.target.value }))}>
            <option value="">Todas las sedes</option>
            {sedeOptions.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}
          </select>
          <select className={`filter-select ${filters.medico ? 'is-set' : ''}`} value={filters.medico} onChange={e => setFilters(f => ({ ...f, medico: e.target.value }))}>
            <option value="">Todos los médicos</option>
            {medicoOptions.map(m => <option key={m.id} value={m.id}>{m.label}</option>)}
          </select>
          <select className={`filter-select ${filters.afiliacion ? 'is-set' : ''}`} value={filters.afiliacion} onChange={e => setFilters(f => ({ ...f, afiliacion: e.target.value }))}>
            <option value="">Todo tipo afiliación</option>
            {tipoAfiliacionOptions.map(a => <option key={a.id} value={a.id}>{a.label}</option>)}
          </select>
          <select className={`filter-select ${filters.registro ? 'is-set' : ''}`} value={filters.registro} onChange={e => setFilters(f => ({ ...f, registro: e.target.value }))}>
            <option value="">Último registro</option>
            <option value="7">Últimos 7 días</option>
            <option value="30">Últimos 30 días</option>
            <option value="90">Últimos 3 meses</option>
            <option value="old">Hace más de 3 meses</option>
          </select>

          <button className={`chip-toggle t-cita ${flags.citas ? 'is-active' : ''}`} onClick={() => setFlags(f => ({ ...f, citas: !f.citas }))}>
            <i className="mdi mdi-calendar-clock" />Con citas pendientes
          </button>
          <button className={`chip-toggle t-sol ${flags.solicitudes ? 'is-active' : ''}`} onClick={() => setFlags(f => ({ ...f, solicitudes: !f.solicitudes }))}>
            <i className="mdi mdi-clipboard-text-clock-outline" />Con solicitudes
          </button>

          <div className="toolbar-spacer" />
          <span className="result-count"><b>{filtered.length}</b> de {patients.length}</span>
          {hasFilters && <button className="tip-clear" onClick={clearAll}><i className="mdi mdi-close-circle-outline" />Limpiar</button>}
        </div>

        {/* Loading skeleton */}
        {loading && (
          <div className="table-wrap">
            <table className="pac-table">
              <tbody>
                {[1, 2, 3, 4, 5].map(i => (
                  <tr key={i} className="skel-row">
                    <td><span className="skel" style={{ width: 200 }} /></td>
                    <td><span className="skel" style={{ width: 130 }} /></td>
                    <td><span className="skel" style={{ width: 150 }} /></td>
                    <td><span className="skel" style={{ width: 80 }} /></td>
                    <td><span className="skel" style={{ width: 70 }} /></td>
                    <td><span className="skel" style={{ width: 80 }} /></td>
                    <td><span className="skel" style={{ width: 90 }} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {!loading && filtered.length === 0 && (
          <div className="list-empty">
            <i className="mdi mdi-account-search-outline" />
            <h3>Sin coincidencias</h3>
            <p>No se encontraron pacientes con los filtros actuales.{' '}
              <a href="#" onClick={e => { e.preventDefault(); clearAll(); }}>Limpiar los filtros</a>.
            </p>
          </div>
        )}

        {!loading && filtered.length > 0 && view === 'tabla' && (
          <div className="table-wrap">
            <table className="pac-table">
              <thead>
                <tr>
                  <Th k="full_name">Paciente</Th>
                  <th>Contacto</th>
                  <th>Médico tratante</th>
                  <th>Sede</th>
                  <th>Afiliación</th>
                  <Th k="ultima_visita">Última visita</Th>
                  <Th k="proxima">Próxima cita</Th>
                  <th>Estado</th>
                  <th style={{ textAlign: 'right' }}>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {paged.map(p => (
                  <tr key={p.id} className={p.alerta ? 'has-alert' : ''} onClick={() => onOpen(p.id)}>
                    <td>
                      <div className="tc-pac">
                        <Avatar initials={p.initials} sede={p.sede} size={38} />
                        <div>
                          <div className="pf-name">{p.display_name}</div>
                          <div className="hc">HC {p.hc_number}<span className="agedot">·</span>{p.edad > 0 ? `${p.edad} años` : ''}</div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div className="tc-contact">
                        <div className="ct-tel"><i className="mdi mdi-phone-outline" />{p.telefono || '—'}</div>
                        <div className="ct-mail">{p.email || <span className="tc-muted">Sin correo</span>}</div>
                      </div>
                    </td>
                    <td><MedicoCell id={p.medico} medico={p.medico_tratante} /></td>
                    <td><SedeBadge id={p.sede} label={p.sede_info?.nombre} /></td>
                    <td><AfilChip id={p.afiliacion} tipo={p.tipo_afiliacion} /></td>
                    <td>
                      <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--fg-1)' }}>{fmtDateShort(p.ultima_visita)}</div>
                      <div style={{ fontSize: 11, color: 'var(--fg-mute)' }}>{relDays(p.ultima_visita)}</div>
                    </td>
                    <td><ProxCita cita={p.proxima_cita} /></td>
                    <td><PatientBadges p={p} compact /></td>
                    <td style={{ textAlign: 'right' }}><RowActions p={p} onOpen={onOpen} onAgendar={onAgendar} onWhats={onWhats} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
            {pageCount > 1 && (
              <div className="pac-pagination">
                <span className="pg-info">
                  Mostrando {(page-1)*perPage + 1}–{Math.min(page*perPage, sorted.length)} de {sorted.length}
                </span>
                <div className="pg-controls">
                  <button disabled={page === 1} onClick={() => setPage(1)} className="pg-btn"><i className="mdi mdi-page-first" /></button>
                  <button disabled={page === 1} onClick={() => setPage(p => p-1)} className="pg-btn"><i className="mdi mdi-chevron-left" /></button>
                  {Array.from({length: pageCount}, (_, i) => i+1)
                    .filter(n => n === 1 || n === pageCount || Math.abs(n - page) <= 2)
                    .map((n, idx, arr) => (
                      <React.Fragment key={n}>
                        {idx > 0 && arr[idx-1] !== n-1 && <span className="pg-ellipsis">…</span>}
                        <button className={`pg-btn ${n === page ? 'is-active' : ''}`} onClick={() => setPage(n)}>{n}</button>
                      </React.Fragment>
                    ))
                  }
                  <button disabled={page === pageCount} onClick={() => setPage(p => p+1)} className="pg-btn"><i className="mdi mdi-chevron-right" /></button>
                  <button disabled={page === pageCount} onClick={() => setPage(pageCount)} className="pg-btn"><i className="mdi mdi-page-last" /></button>
                </div>
              </div>
            )}
          </div>
        )}

        {!loading && filtered.length > 0 && view === 'tarjetas' && (
          <div className="pac-cards">
            {paged.map(p => (
              <article key={p.id} className={`pcard ${p.alerta ? 'has-alert' : ''}`} onClick={() => onOpen(p.id)}>
                <div className="pcard-top">
                  <Avatar initials={p.initials} sede={p.sede} size={48} />
                  <div className="pcard-id">
                    <div className="pf-name">{p.display_name}</div>
                    <div className="sub">
                      <span className="hc">HC {p.hc_number}</span>
                      <span>·</span>
                      {p.edad > 0 && <span>{p.edad} años</span>}
                      <SedeBadge id={p.sede} label={p.sede_info?.nombre} />
                    </div>
                  </div>
                  <PatientBadges p={p} compact />
                </div>
                <div className="pcard-meta">
                  <div className="mi"><div className="k">Médico</div><div className="v">{p.medico || '—'}</div></div>
                  <div className="mi"><div className="k">Afiliación</div><div className="v"><AfilChip id={p.afiliacion} tipo={p.tipo_afiliacion} /></div></div>
                  <div className="mi"><div className="k">Teléfono</div><div className="v">{p.telefono || '—'}</div></div>
                  <div className="mi"><div className="k">Próxima cita</div><div className="v"><ProxCita cita={p.proxima_cita} /></div></div>
                </div>
                <div className="pcard-foot">
                  <span style={{ fontSize: 11.5, color: 'var(--fg-mute)' }}>Última visita {relDays(p.ultima_visita)}</span>
                  <RowActions p={p} onOpen={onOpen} onAgendar={onAgendar} onWhats={onWhats} />
                </div>
              </article>
            ))}
            {pageCount > 1 && (
              <div className="pac-pagination" style={{ width: '100%' }}>
                <span className="pg-info">
                  Mostrando {(page-1)*perPage + 1}–{Math.min(page*perPage, sorted.length)} de {sorted.length}
                </span>
                <div className="pg-controls">
                  <button disabled={page === 1} onClick={() => setPage(1)} className="pg-btn"><i className="mdi mdi-page-first" /></button>
                  <button disabled={page === 1} onClick={() => setPage(p => p-1)} className="pg-btn"><i className="mdi mdi-chevron-left" /></button>
                  {Array.from({length: pageCount}, (_, i) => i+1)
                    .filter(n => n === 1 || n === pageCount || Math.abs(n - page) <= 2)
                    .map((n, idx, arr) => (
                      <React.Fragment key={n}>
                        {idx > 0 && arr[idx-1] !== n-1 && <span className="pg-ellipsis">…</span>}
                        <button className={`pg-btn ${n === page ? 'is-active' : ''}`} onClick={() => setPage(n)}>{n}</button>
                      </React.Fragment>
                    ))
                  }
                  <button disabled={page === pageCount} onClick={() => setPage(p => p+1)} className="pg-btn"><i className="mdi mdi-chevron-right" /></button>
                  <button disabled={page === pageCount} onClick={() => setPage(pageCount)} className="pg-btn"><i className="mdi mdi-page-last" /></button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
