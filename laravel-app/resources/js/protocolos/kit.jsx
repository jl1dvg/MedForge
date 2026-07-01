import React, { createContext, useContext, useState, useEffect, useRef, useMemo, useCallback } from 'react';

/** Catálogos embebidos en data-config (categorías, staff, vías, insumos, etc.). */
export const CatalogContext = createContext({
  categorias: [], funcionesStaff: [], funcionEspecialidad: {}, vias: [], responsables: [],
  plantillasBase: [], sugerenciasStaff: {}, sugerenciasInsumos: {}, sugerenciasMedicamentos: {},
  operatorioSugerido: {}, insumosDisponibles: {}, opcionesMedicamentos: [],
});
export const useCatalogs = () => useContext(CatalogContext);

export function Field({ label, required, optional, help, children }) {
  return (
    <div className="field">
      {label && (
        <label>
          {label}
          {required && <span className="req">*</span>}
          {optional && <span className="opt">· opcional</span>}
        </label>
      )}
      {children}
      {help && <p className="help">{help}</p>}
    </div>
  );
}

export function TextInput(props) {
  const { affix, ...rest } = props;
  if (affix) {
    return (
      <div className="inp-affix">
        <input className="inp" {...rest} />
        <span className="affix">{affix}</span>
      </div>
    );
  }
  return <input className="inp" {...rest} />;
}
export function TextArea(props) { return <textarea className="ta" {...props} />; }
export function Select({ children, ...rest }) { return <select className="sel" {...rest}>{children}</select>; }

/** Botón "IA" con estado "pensando" — reglas deterministas por categoría, ejecutadas en cliente. */
export function AIButton({ label, onRun, icon = 'mdi-auto-fix', small }) {
  const [busy, setBusy] = useState(false);
  const run = () => {
    if (busy) return;
    setBusy(true);
    setTimeout(() => { setBusy(false); onRun && onRun(); }, 420 + Math.random() * 260);
  };
  return (
    <button type="button" className={`ai-btn${busy ? ' busy' : ''}`} onClick={run}
            style={small ? { padding: '6px 10px', fontSize: 11.5 } : null}>
      <i className={`mdi ${busy ? 'mdi-loading' : icon}`}></i>
      {busy ? 'Analizando…' : label}
    </button>
  );
}

/** Combobox: buscar en catálogo (local u onSearch remoto) y agregar. */
export function Combo({ options, onSearch, onPick, placeholder, getKey, getLabel, getBadge, allowCreate, onCreate }) {
  const [q, setQ] = useState('');
  const [open, setOpen] = useState(false);
  const [hl, setHl] = useState(0);
  const [remote, setRemote] = useState(null);
  const wrapRef = useRef(null);

  useEffect(() => {
    const h = (e) => { if (wrapRef.current && !wrapRef.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);

  useEffect(() => {
    if (!onSearch) return;
    const s = q.trim();
    if (s.length < 2) { setRemote(null); return; }
    let alive = true;
    const t = setTimeout(() => {
      onSearch(s).then((rows) => { if (alive) setRemote(rows); }).catch(() => { if (alive) setRemote([]); });
    }, 180);
    return () => { alive = false; clearTimeout(t); };
  }, [q, onSearch]);

  const filtered = useMemo(() => {
    if (onSearch) return remote === null ? (options || []).slice(0, 20) : remote;
    const s = q.trim().toLowerCase();
    if (!s) return options.slice(0, 40);
    return options.filter((o) => (getLabel(o) + ' ' + (getBadge ? getBadge(o) : '')).toLowerCase().includes(s)).slice(0, 40);
  }, [q, options, remote, onSearch]);

  const pick = (o) => { onPick(o); setQ(''); setOpen(false); setHl(0); setRemote(null); };

  return (
    <div className="combo" ref={wrapRef}>
      <div className="search-box">
        <i className="mdi mdi-magnify"></i>
        <input value={q} placeholder={placeholder}
               onChange={(e) => { setQ(e.target.value); setOpen(true); setHl(0); }}
               onFocus={() => setOpen(true)}
               onKeyDown={(e) => {
                 if (e.key === 'ArrowDown') { e.preventDefault(); setHl((h) => Math.min(h + 1, filtered.length - 1)); }
                 else if (e.key === 'ArrowUp') { e.preventDefault(); setHl((h) => Math.max(h - 1, 0)); }
                 else if (e.key === 'Enter') { e.preventDefault(); if (filtered[hl]) pick(filtered[hl]); else if (allowCreate && q.trim()) { onCreate(q.trim()); setQ(''); setOpen(false); } }
                 else if (e.key === 'Escape') setOpen(false);
               }} />
      </div>
      {open && (
        <div className="combo-menu">
          {filtered.map((o, i) => (
            <div key={getKey(o)} className={`combo-opt${i === hl ? ' hl' : ''}`}
                 onMouseEnter={() => setHl(i)} onMouseDown={(e) => { e.preventDefault(); pick(o); }}>
              {getBadge && <span className="co-code">{getBadge(o)}</span>}
              <span className="co-name">{getLabel(o)}</span>
              <i className="mdi mdi-plus-circle co-add"></i>
            </div>
          ))}
          {filtered.length === 0 && (
            <div className="combo-empty">
              No hay coincidencias{allowCreate && q.trim() ? <> · <a onMouseDown={(e) => { e.preventDefault(); onCreate(q.trim()); setQ(''); setOpen(false); }}>Usar «{q.trim()}» como texto libre</a></> : '.'}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/** Toast (sugerencia aplicada / guardado). */
export function useToast() {
  const [toast, setToast] = useState(null);
  const timer = useRef(null);
  const show = useCallback((msg, onUndo) => {
    setToast({ msg, onUndo });
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setToast(null), 4200);
  }, []);
  const node = toast ? (
    <div className="ai-toast show">
      <i className="mdi mdi-check-circle"></i>
      <span>{toast.msg}</span>
      {toast.onUndo && <span className="u" onClick={() => { toast.onUndo(); setToast(null); }}>Deshacer</span>}
    </div>
  ) : null;
  return [node, show];
}

export function Modal({ icon, tone = 'danger', title, children, confirmLabel, confirmTone = 'danger', onConfirm, onClose }) {
  const toneMap = {
    danger: { bg: '#fde2e7', fg: 'var(--danger)' },
    primary: { bg: 'var(--primary-fade)', fg: 'var(--primary)' },
    warning: { bg: '#fff0d1', fg: '#8a5d0a' },
  };
  const t = toneMap[tone] || toneMap.danger;
  return (
    <div className="ov" onMouseDown={onClose}>
      <div className="modal" onMouseDown={(e) => e.stopPropagation()}>
        <div className="modal-head">
          {icon && <div className="m-ico" style={{ background: t.bg, color: t.fg }}><i className={`mdi ${icon}`}></i></div>}
          <h3>{title}</h3>
          {children && <p>{children}</p>}
        </div>
        <div className="modal-foot">
          <button className="btn btn-ghost" onClick={onClose}>Cancelar</button>
          {confirmTone === 'danger'
            ? <button className="btn btn-outline-danger" onClick={onConfirm}>{confirmLabel}</button>
            : <button className="btn btn-primary" onClick={onConfirm}>{confirmLabel}</button>}
        </div>
      </div>
    </div>
  );
}

export const uid = () => 'p-' + Math.random().toString(36).slice(2, 8);
export const rid = () => Math.random().toString(36).slice(2, 9);

export const fmtDate = (iso) => {
  if (!iso) return '—';
  const d = new Date(iso + 'T00:00:00');
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' });
};

export function useCatMeta() {
  const { categorias } = useCatalogs();
  return useCallback((id) => (categorias || []).find((c) => c.id === id) || { id, icon: 'mdi-eye-outline', color: '#5156be' }, [categorias]);
}

/* ---------- Menciones de insumo en la descripción operatoria (@nombre → [[ID:n]]) ---------- */

export function flattenInsumos(insumosDisponibles) {
  return Object.values(insumosDisponibles || {}).flat();
}

export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** Convierte [[ID:n]] guardado en DB a <span class="tag" data-id="n">Nombre</span> para mostrar. */
export function renderOperatorioHtml(raw, listaInsumos) {
  return escapeHtml(raw)
    .replace(/\n/g, '<br>')
    .replace(/\[\[ID:(\d+)\]\]/g, (match, id) => {
      const insumo = (listaInsumos || []).find((i) => String(i.id) === String(id));
      const nombre = insumo ? escapeHtml(insumo.nombre) : `#${id}`;
      return `<span class="tag" data-id="${id}">${nombre}</span>&nbsp;`;
    });
}

/** Convierte el DOM del editor (texto + spans .tag) de vuelta a la cadena con [[ID:n]] para guardar. */
export function extractOperatorioValue(root) {
  let result = '';
  const walk = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      result += node.textContent;
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      if (node.classList && node.classList.contains('tag')) {
        result += `[[ID:${node.dataset.id}]]`;
        return;
      }
      if (node.tagName === 'BR') {
        result += '\n';
        return;
      }
      const isBlock = node.tagName === 'DIV' || node.tagName === 'P';
      if (isBlock && result && !result.endsWith('\n')) result += '\n';
      node.childNodes.forEach(walk);
    }
  };
  root.childNodes.forEach(walk);
  return result;
}

/**
 * Un <select> controlado nunca debe "tragarse" en silencio un valor guardado que ya no está
 * en el catálogo (dato legacy, catálogo editado después, etc.) — el navegador cae al primer
 * <option> sin avisar, y si el usuario guarda sin notar el cambio, se pierde el valor real.
 * Estas helpers agregan el valor actual como opción de respaldo cuando no calza con ninguna.
 */
export function ensureOption(list, value) {
  const arr = list || [];
  if (!value || arr.includes(value)) return arr;
  return [...arr, value];
}

export function ensureObjectOption(list, value, getValue) {
  const arr = list || [];
  if (!value || arr.some((o) => getValue(o) === value)) return arr;
  return [...arr, { __orphan: true, value }];
}

/** Colores fijos legacy para filas de kardex (por responsable). */
export const RESPONSABLE_ROW_COLOR = {
  'Anestesiólogo': '#f8d7da',
  'Cirujano Principal': '#cce5ff',
  'Asistente': '#d4edda',
};

const CATEGORIA_PALETTE = ['#d4edda', '#cce5ff', '#fff3cd', '#e9e7fb', '#fde2e7', '#d4f5ee', '#ffe4ad'];

/** Color estable por categoría real de insumo (no hay un set fijo de categorías como en legacy). */
export function colorForCategoria(categoria) {
  const s = String(categoria || '');
  let hash = 0;
  for (let i = 0; i < s.length; i++) hash = (hash * 31 + s.charCodeAt(i)) >>> 0;
  return CATEGORIA_PALETTE[hash % CATEGORIA_PALETTE.length];
}
