import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Modal, fmtDate, useCatMeta, useCatalogs } from './kit';

function CardMenu({ onEdit, onDup, onDelete, onClose }) {
  const ref = useRef(null);
  useEffect(() => {
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);
  return (
    <div className="pop" ref={ref} style={{ top: 44, right: 10 }} onClick={(e) => e.stopPropagation()}>
      <button onClick={onEdit}><i className="mdi mdi-pencil-outline"></i>Editar</button>
      <button onClick={onDup}><i className="mdi mdi-content-copy"></i>Duplicar</button>
      <div className="div"></div>
      <button className="danger" onClick={onDelete}><i className="mdi mdi-trash-can-outline"></i>Eliminar</button>
    </div>
  );
}

function TemplateCard({ p, onOpen, onDup, onDelete, canManage }) {
  const [menu, setMenu] = useState(false);
  const catMeta = useCatMeta();
  const cm = catMeta(p.categoria);
  return (
    <div className="tpl-card" onClick={() => onOpen(p.id)}>
      <div className="tpl-thumb">
        {p.imagen_link
          ? <img src={p.imagen_link} alt="" onError={(e) => { e.target.style.display = 'none'; }} />
          : <div className="ph" style={{ background: `linear-gradient(135deg, ${cm.color}, ${cm.color}bb)` }}><i className={`mdi ${cm.icon}`}></i></div>}
        <span className="cat-tag"><i className={`mdi ${cm.icon}`} style={{ fontSize: 12 }}></i>{p.categoria}</span>
      </div>
      {canManage && (
        <button className="card-menu-btn" onClick={(e) => { e.stopPropagation(); setMenu((m) => !m); }}>
          <i className="mdi mdi-dots-horizontal"></i>
        </button>
      )}
      {menu && <CardMenu onEdit={() => onOpen(p.id)} onDup={() => { setMenu(false); onDup(p); }} onDelete={() => { setMenu(false); onDelete(p); }} onClose={() => setMenu(false)} />}
      <div className="tpl-body">
        <div className="tpl-title">{p.membrete}</div>
        <div className="tpl-codes">
          {(p.codigos || []).slice(0, 2).map((c, i) => <span key={c.codigo || i} className="code-chip">{c.codigo || c.nombre}</span>)}
          {(p.codigos || []).length > 2 && <span className="code-chip">+{p.codigos.length - 2}</span>}
        </div>
        <div className="tpl-foot">
          <span className="tpl-meta"><i className="mdi mdi-clock-outline" style={{ fontSize: 13 }}></i>{p.horas} h · {fmtDate(p.actualizado)}</span>
          <span className="tpl-usos"><i className="mdi mdi-chart-line" style={{ fontSize: 12 }}></i>{p.usos}</span>
        </div>
      </div>
    </div>
  );
}

function TemplateRow({ p, onOpen, onDup, onDelete, canManage }) {
  const [menu, setMenu] = useState(false);
  const catMeta = useCatMeta();
  const cm = catMeta(p.categoria);
  return (
    <div className="tpl-row" onClick={() => onOpen(p.id)}>
      <div className="rthumb" style={{ background: p.imagen_link ? undefined : `linear-gradient(135deg, ${cm.color}, ${cm.color}bb)` }}>
        {p.imagen_link ? <img src={p.imagen_link} alt="" onError={(e) => { e.target.style.display = 'none'; }} /> : <i className={`mdi ${cm.icon}`}></i>}
      </div>
      <div>
        <div className="rtitle">{p.membrete}</div>
        <div className="rsub">{(p.codigos || []).map((c) => c.codigo || c.nombre).join(', ')}</div>
      </div>
      <div className="rmeta"><span className="badge badge--light">{p.categoria}</span></div>
      <div className="rmeta">{p.horas} h · {fmtDate(p.actualizado)}</div>
      <div className="rmeta"><span className="tpl-usos"><i className="mdi mdi-chart-line" style={{ fontSize: 12 }}></i>{p.usos}</span></div>
      {canManage ? (
        <button className="row-x" style={{ position: 'static' }} onClick={(e) => { e.stopPropagation(); setMenu((m) => !m); }}>
          <i className="mdi mdi-dots-horizontal"></i>
        </button>
      ) : <span></span>}
      {menu && <CardMenu onEdit={() => onOpen(p.id)} onDup={() => { setMenu(false); onDup(p); }} onDelete={() => { setMenu(false); onDelete(p); }} onClose={() => setMenu(false)} />}
    </div>
  );
}

export function ProtocolList({ protocolos, onOpen, onNew, onDuplicate, onDelete, canManage, toastNode, errorMessage }) {
  const { categorias } = useCatalogs();
  const [cat, setCat] = useState('all');
  const [q, setQ] = useState('');
  const [view, setView] = useState('grid');
  const [sort, setSort] = useState('recientes');
  const [confirmDel, setConfirmDel] = useState(null);

  const counts = useMemo(() => {
    const m = {};
    protocolos.forEach((p) => { m[p.categoria] = (m[p.categoria] || 0) + 1; });
    return m;
  }, [protocolos]);

  const filtered = useMemo(() => {
    let arr = protocolos.filter((p) => (cat === 'all' || p.categoria === cat));
    const s = q.trim().toLowerCase();
    if (s) arr = arr.filter((p) => (p.membrete + ' ' + p.cirugia + ' ' + (p.codigos || []).map((c) => (c.codigo || '') + (c.nombre || '')).join(' ')).toLowerCase().includes(s));
    arr = [...arr];
    if (sort === 'recientes') arr.sort((a, b) => (b.actualizado || '').localeCompare(a.actualizado || ''));
    else if (sort === 'usos') arr.sort((a, b) => (b.usos || 0) - (a.usos || 0));
    else if (sort === 'az') arr.sort((a, b) => a.membrete.localeCompare(b.membrete));
    return arr;
  }, [protocolos, cat, q, sort]);

  const doDup = (p) => onDuplicate(p);
  const askDel = (p) => setConfirmDel(p);

  return (
    <div className="mod-scroll">
      <div className="mod-head">
        <div>
          <div className="crumb"><span>Protocolos</span></div>
          <h1>Plantillas de protocolos quirúrgicos</h1>
          <p className="sub">Crea, edita y reutiliza protocolos por procedimiento. Se aplican automáticamente al documentar cada cirugía.</p>
        </div>
        {canManage && (
          <div className="actions">
            <button className="btn btn-primary" onClick={onNew}><i className="mdi mdi-plus-circle-outline"></i>Nuevo protocolo</button>
          </div>
        )}
      </div>

      {errorMessage && (
        <div className="page-body" style={{ paddingBottom: 0 }}>
          <div className="box" style={{ borderColor: 'var(--danger)', padding: '10px 16px', color: 'var(--danger)', fontSize: 13 }}>{errorMessage}</div>
        </div>
      )}

      <div className="list-layout">
        <aside className="cat-rail">
          <div className="rail-title">Categorías</div>
          <div className={`cat-item${cat === 'all' ? ' active' : ''}`} onClick={() => setCat('all')}>
            <span className="ci-ico" style={cat === 'all' ? { background: 'var(--primary)', color: '#fff' } : null}><i className="mdi mdi-view-grid-outline"></i></span>
            <span className="ci-label">Todas</span>
            <span className="ci-count" style={cat === 'all' ? { background: 'var(--primary)' } : null}>{protocolos.length}</span>
          </div>
          {(categorias || []).map((c) => {
            const active = cat === c.id;
            const n = counts[c.id] || 0;
            return (
              <div key={c.id} className={`cat-item${active ? ' active' : ''}`} onClick={() => setCat(c.id)}>
                <span className="ci-ico" style={active ? { background: c.color, color: '#fff' } : { color: c.color }}><i className={`mdi ${c.icon}`}></i></span>
                <span className="ci-label">{c.id}</span>
                <span className="ci-count" style={active ? { background: c.color } : null}>{n}</span>
              </div>
            );
          })}
        </aside>

        <div className="list-main">
          <div className="list-toolbar">
            <div className="search-box">
              <i className="mdi mdi-magnify"></i>
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nombre, procedimiento o código…" />
            </div>
            <select className="sort-select" value={sort} onChange={(e) => setSort(e.target.value)}>
              <option value="recientes">Más recientes</option>
              <option value="usos">Más utilizadas</option>
              <option value="az">A–Z</option>
            </select>
            <div className="seg">
              <button className={view === 'grid' ? 'on' : ''} onClick={() => setView('grid')}><i className="mdi mdi-view-grid-outline"></i></button>
              <button className={view === 'list' ? 'on' : ''} onClick={() => setView('list')}><i className="mdi mdi-format-list-bulleted"></i></button>
            </div>
          </div>

          <div className="spread">
            <span className="result-meta"><strong>{filtered.length}</strong> {filtered.length === 1 ? 'plantilla' : 'plantillas'}{cat !== 'all' ? ` en ${cat}` : ''}</span>
          </div>

          {filtered.length === 0 ? (
            <div className="empty">
              <i className="mdi mdi-file-search-outline"></i>
              <h4>No encontramos plantillas</h4>
              <p>Ajusta la búsqueda{canManage ? ' o crea un protocolo nuevo para esta categoría.' : '.'}</p>
              {canManage && <button className="btn btn-primary" onClick={onNew}><i className="mdi mdi-plus-circle-outline"></i>Nuevo protocolo</button>}
            </div>
          ) : view === 'grid' ? (
            <div className="tpl-grid">
              {filtered.map((p) => <TemplateCard key={p.id} p={p} onOpen={onOpen} onDup={doDup} onDelete={askDel} canManage={canManage} />)}
            </div>
          ) : (
            <div className="tpl-rows">
              {filtered.map((p) => <TemplateRow key={p.id} p={p} onOpen={onOpen} onDup={doDup} onDelete={askDel} canManage={canManage} />)}
            </div>
          )}
        </div>
      </div>

      {confirmDel && (
        <Modal icon="mdi-trash-can-outline" tone="danger" title="¿Eliminar este protocolo?"
               confirmLabel="Eliminar" confirmTone="danger"
               onConfirm={() => { onDelete(confirmDel); setConfirmDel(null); }}
               onClose={() => setConfirmDel(null)}>
          Se eliminará «{confirmDel.membrete}». Las cirugías ya documentadas con esta plantilla no se verán afectadas.
        </Modal>
      )}
      {toastNode}
    </div>
  );
}
