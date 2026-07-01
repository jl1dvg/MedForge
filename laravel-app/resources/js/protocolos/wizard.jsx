import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  Field, TextInput, TextArea, AIButton, Combo, Modal, useToast, useCatalogs, useCatMeta, rid,
  ensureOption, RESPONSABLE_ROW_COLOR, colorForCategoria, flattenInsumos, renderOperatorioHtml, extractOperatorioValue,
} from './kit';
import { ProtocolDoc } from './preview';

const emptyData = () => ({
  id: '', cirugia: '', membrete: '', categoria: '', horas: '',
  dieresis: '', exposicion: '', hallazgo: '', operatorio: '',
  imagen_link: '',
  codigos: [],
  staff: [],
  pre_evolucion: '', pre_indicacion: '', post_evolucion: '', post_indicacion: '', alta_evolucion: '', alta_indicacion: '',
  medicamentos: [],
  insumos: [],
});

/* ============================ PASOS ============================ */

function StepInicio({ onTemplate, onBlank, loadingId }) {
  const { plantillasBase } = useCatalogs();
  const catMeta = useCatMeta();
  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="eyebrow"><i className="mdi mdi-rocket-launch-outline"></i>Punto de partida</div>
        <h2>¿Desde dónde quieres empezar?</h2>
        <p>Arranca desde una plantilla base con técnica, kardex e insumos ya sugeridos, o parte de cero. Todo es editable después.</p>
      </div>
      <div className="ai-banner">
        <div className="ai-ico"><i className="mdi mdi-auto-fix"></i></div>
        <div className="ai-tx">
          <h5>Plantillas asistidas por MedForge IA</h5>
          <p>Elige un procedimiento frecuente y precargamos los pasos operatorios, medicación e insumos típicos. Ajusta lo que necesites.</p>
        </div>
      </div>
      <div className="start-grid">
        {(plantillasBase || []).map((t) => {
          const cm = catMeta(t.categoria);
          const busy = loadingId === t.id;
          return (
            <button key={t.id} className="start-card" disabled={!!loadingId} onClick={() => onTemplate(t)} style={loadingId && !busy ? { opacity: .5 } : null}>
              <div className="start-thumb" style={{ background: `linear-gradient(135deg, ${cm.color}, ${cm.color}bb)`, display: 'grid', placeItems: 'center' }}>
                <i className={`mdi ${busy ? 'mdi-loading' : t.icon}`} style={{ fontSize: 30, color: '#fff', animation: busy ? 'spin 1s linear infinite' : 'none' }}></i>
                <span className="sc-cat">{t.categoria}</span>
              </div>
              <div className="sc-body">
                <div className="sc-name">{t.nombre}</div>
                <div className="sc-desc">{t.descripcion}</div>
                <div className="sc-ai"><i className="mdi mdi-auto-fix"></i>{busy ? 'Precargando…' : 'Precargar con IA'}</div>
              </div>
            </button>
          );
        })}
        <button className="start-card blank" disabled={!!loadingId} onClick={onBlank}>
          <i className="mdi mdi-file-plus-outline"></i>
          <div style={{ fontWeight: 600, fontSize: 14, color: 'inherit' }}>Empezar en blanco</div>
          <div className="sc-desc" style={{ textAlign: 'center' }}>Construye el protocolo paso a paso</div>
        </button>
      </div>
    </div>
  );
}

function StepDatos({ data, patch }) {
  const { categorias } = useCatalogs();
  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="eyebrow"><i className="mdi mdi-card-text-outline"></i>Paso 1 · Datos generales</div>
        <h2>Identifica el protocolo</h2>
        <p>Nombre, categoría y duración estimada. Estos datos aparecen en la lista y encabezan el documento.</p>
      </div>
      <Field label="Título del protocolo" required help="Nombre completo que verá el equipo. Ej: «Facoemulsificación de catarata + LIO».">
        <TextInput value={data.membrete} onChange={(e) => patch({ membrete: e.target.value })} placeholder="Facoemulsificación de catarata + LIO" />
      </Field>
      <div className="field-row">
        <Field label="Nombre corto" required help="Palabra clave para búsquedas rápidas.">
          <TextInput value={data.cirugia} onChange={(e) => patch({ cirugia: e.target.value })} placeholder="faco, pterigión, avastin…" />
        </Field>
        <Field label="Duración estimada" required>
          <TextInput type="number" step="0.25" min="0" affix="horas" value={data.horas} onChange={(e) => patch({ horas: e.target.value })} placeholder="0.75" />
        </Field>
      </div>
      <Field label="Categoría" required help="Agrupa el protocolo en el catálogo por especialidad.">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px,1fr))', gap: 8 }}>
          {(categorias || []).map((c) => {
            const on = data.categoria === c.id;
            return (
              <button key={c.id} type="button" onClick={() => patch({ categoria: c.id })}
                      style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '9px 11px', borderRadius: 9, cursor: 'pointer',
                               border: `1px solid ${on ? c.color : 'var(--border)'}`, background: on ? c.color + '14' : '#fff',
                               font: '600 12.5px var(--font-body)', color: on ? c.color : 'var(--fg-2)', transition: 'all .12s' }}>
                <i className={`mdi ${c.icon}`} style={{ fontSize: 17, color: c.color }}></i>
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.id}</span>
                {on && <i className="mdi mdi-check-circle" style={{ marginLeft: 'auto', fontSize: 15, color: c.color }}></i>}
              </button>
            );
          })}
        </div>
      </Field>
      <Field label="Imagen de portada" optional help="URL de una imagen para identificar el protocolo en el catálogo. Si la dejas vacía, se muestra un ícono de la categoría.">
        <TextInput value={data.imagen_link} onChange={(e) => patch({ imagen_link: e.target.value })} placeholder="https://ejemplo.com/imagen.jpg" />
        {data.imagen_link && (
          <div style={{ marginTop: 10, width: 120, height: 78, borderRadius: 9, overflow: 'hidden', border: '1px solid var(--border)', background: 'var(--bg-softer)' }}>
            <img src={data.imagen_link} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }}
                 onError={(e) => { e.target.style.display = 'none'; }} />
          </div>
        )}
      </Field>
    </div>
  );
}

function StepCodigos({ data, patch, api }) {
  const search = useCallback((q) => api.searchCodigos(q).then((rows) => rows.map((r) => ({ codigo: r.codigo, nombre: r.descripcion }))), [api]);
  const add = (o) => { if (!data.codigos.some((c) => c.codigo && c.codigo === o.codigo)) patch({ codigos: [...data.codigos, o] }); };
  const create = (name) => patch({ codigos: [...data.codigos, { codigo: null, nombre: name }] });
  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="eyebrow"><i className="mdi mdi-barcode"></i>Paso 2 · Códigos quirúrgicos</div>
        <h2>Agrega los procedimientos</h2>
        <p>Busca por nombre y añádelo. La lateralidad y el mapeo técnico se generan automáticamente — tú solo eliges el procedimiento.</p>
      </div>
      <Field label="Buscar procedimiento" help="Escribe el nombre del procedimiento; nosotros vinculamos el código.">
        <Combo onSearch={search} onPick={add} allowCreate onCreate={create}
               placeholder="Facoemulsificación, vitrectomía, pterigión…"
               getKey={(o) => o.codigo} getLabel={(o) => o.nombre} getBadge={(o) => o.codigo} />
      </Field>
      {data.codigos.length > 0 ? (
        <div className="chips">
          {data.codigos.map((c, i) => (
            <div className="sel-chip" key={i}>
              <span className="sc-badge">{c.codigo || '—'}</span>
              <span className="sc-name">{c.nombre}</span>
              <span className="sc-tag"><i className="mdi mdi-check-decagram" style={{ fontSize: 13, color: 'var(--success)' }}></i> lateralidad auto</span>
              <button className="x" onClick={() => patch({ codigos: data.codigos.filter((_, j) => j !== i) })}><i className="mdi mdi-close"></i></button>
            </div>
          ))}
        </div>
      ) : (
        <div className="muted-note" style={{ padding: '16px 0' }}>Aún no agregas códigos. Empieza escribiendo arriba.</div>
      )}
    </div>
  );
}

function StepEquipo({ data, patch, onAI, staffOptions }) {
  const { funcionesStaff, funcionEspecialidad } = useCatalogs();
  const setRow = (i, patchRow) => { const s = [...data.staff]; s[i] = { ...s[i], ...patchRow }; patch({ staff: s }); };
  const addRow = () => patch({ staff: [...data.staff, { _id: rid(), funcion: (funcionesStaff || [])[0] || '', nombre: '', trabajador_id: null }] });
  const del = (i) => patch({ staff: data.staff.filter((_, j) => j !== i) });

  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="spread">
          <div>
            <div className="eyebrow"><i className="mdi mdi-account-group-outline"></i>Paso 3 · Equipo quirúrgico</div>
            <h2>Define el equipo típico</h2>
          </div>
          <AIButton label="Autocompletar equipo" onRun={onAI} icon="mdi-account-multiple-plus-outline" />
        </div>
        <p>Asigna funciones y personal habitual. Podrás cambiarlo al documentar cada cirugía.</p>
      </div>
      {data.staff.length > 0 ? (
        <div className="staff-list">
          {data.staff.map((s, i) => {
            const grupo = funcionEspecialidad[s.funcion];
            const opciones = grupo ? (staffOptions[grupo] || []) : null;
            return (
              <div className="staff-card" key={s._id || i}>
                <select className="sel" style={{ padding: '8px 30px 8px 11px', fontSize: 12.5 }} value={s.funcion}
                        onChange={(e) => setRow(i, { funcion: e.target.value, nombre: '', trabajador_id: null })}>
                  {(funcionesStaff || []).map((f) => <option key={f} value={f}>{f}</option>)}
                </select>
                {opciones ? (
                  <select className="sel" style={{ padding: '8px 30px 8px 11px', fontSize: 13 }} value={s.trabajador_id || ''}
                          onChange={(e) => {
                            const id = e.target.value ? Number(e.target.value) : null;
                            const found = opciones.find((o) => o.id === id);
                            setRow(i, { trabajador_id: id, nombre: found ? found.nombre : '' });
                          }}>
                    <option value="">Seleccionar personal…</option>
                    {opciones.map((o) => <option key={o.id} value={o.id}>{o.nombre}</option>)}
                  </select>
                ) : (
                  <input className="inp" style={{ fontSize: 13 }} value={s.nombre} placeholder="Nombre del personal…"
                         onChange={(e) => setRow(i, { nombre: e.target.value })} />
                )}
                <button className="row-x" style={{ position: 'static' }} onClick={() => del(i)}><i className="mdi mdi-close"></i></button>
              </div>
            );
          })}
        </div>
      ) : (
        <div className="muted-note" style={{ padding: '12px 0' }}>Sin miembros aún. Agrega manualmente o deja que la IA proponga el equipo típico.</div>
      )}
      <button className="btn-add-row" onClick={addRow}><i className="mdi mdi-plus"></i>Agregar miembro</button>
    </div>
  );
}

const MENTION_RE = /@([a-zA-Z0-9À-ÿ ]*)$/;

/**
 * Editor de descripción operatoria con menciones "@insumo" → tag en negrita/color.
 * Se guarda como [[ID:n]] en data.operatorio (mismo formato que el editor legacy),
 * y la vista previa (preview.jsx) resuelve el mismo placeholder para mostrar el tag.
 * `resetToken` fuerza un remount (re-sincroniza el HTML) cuando el texto cambia desde
 * fuera del editor (plantilla base / botón IA), ya que el div es no-controlado mientras se escribe.
 */
function OperatorioEditor({ value, onChange, listaInsumos, resetToken }) {
  const ref = useRef(null);
  const [ac, setAc] = useState(null);

  useEffect(() => {
    if (ref.current) ref.current.innerHTML = renderOperatorioHtml(value, listaInsumos);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resetToken]);

  const emit = () => { if (ref.current) onChange(extractOperatorioValue(ref.current)); };

  const updateAutocomplete = () => {
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount || !ref.current) { setAc(null); return; }
    const range = sel.getRangeAt(0);
    if (!ref.current.contains(range.startContainer)) { setAc(null); return; }
    const preRange = range.cloneRange();
    preRange.selectNodeContents(ref.current);
    preRange.setEnd(range.endContainer, range.endOffset);
    const match = preRange.toString().match(MENTION_RE);
    if (!match) { setAc(null); return; }
    const term = match[1].toLowerCase();
    const items = (listaInsumos || []).filter((i) => i.nombre.toLowerCase().includes(term)).slice(0, 30);
    const rect = range.getBoundingClientRect();
    setAc({ items, top: rect.bottom, left: rect.left, width: ref.current.offsetWidth });
  };

  const pick = (item) => {
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount || !ref.current) return;
    const range = sel.getRangeAt(0);
    const preRange = range.cloneRange();
    preRange.selectNodeContents(ref.current);
    preRange.setEnd(range.startContainer, range.startOffset);
    const match = preRange.toString().match(MENTION_RE);
    if (!match) return;
    range.setStart(range.startContainer, range.startOffset - match[0].length);
    range.deleteContents();
    const span = document.createElement('span');
    span.className = 'tag';
    span.setAttribute('data-id', item.id);
    span.textContent = item.nombre.replace(/\s+/g, ' ').trim();
    range.insertNode(span);
    const space = document.createTextNode(' ');
    span.after(space);
    range.setStartAfter(space);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
    setAc(null);
    emit();
  };

  return (
    <div style={{ position: 'relative' }}>
      <div ref={ref} className="ta operatorio-editor" contentEditable suppressContentEditableWarning
           style={{ minHeight: 180 }}
           onInput={() => { emit(); updateAutocomplete(); }}
           onKeyUp={updateAutocomplete}
           onBlur={() => setTimeout(() => setAc(null), 150)} />
      {ac && ac.items.length > 0 && (
        <div className="autocomplete-box" style={{ position: 'fixed', top: ac.top, left: ac.left, width: ac.width }}>
          {ac.items.map((it) => (
            <div key={it.id} className="suggestion" onMouseDown={(e) => { e.preventDefault(); pick(it); }}>{it.nombre}</div>
          ))}
        </div>
      )}
    </div>
  );
}

function StepTecnica({ data, patch, onAI, operatorioVersion }) {
  const { insumosDisponibles } = useCatalogs();
  const listaInsumos = useMemo(() => flattenInsumos(insumosDisponibles), [insumosDisponibles]);
  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="eyebrow"><i className="mdi mdi-scalpel"></i>Paso 4 · Técnica operatoria</div>
        <h2>Describe el procedimiento</h2>
        <p>Estos textos se insertan automáticamente en el parte operatorio de cada cirugía.</p>
      </div>
      <div className="field-row-3">
        <Field label="Diéresis" optional><TextArea value={data.dieresis} onChange={(e) => patch({ dieresis: e.target.value })} placeholder="Tipo de incisión…" style={{ minHeight: 70 }} /></Field>
        <Field label="Exposición" optional><TextArea value={data.exposicion} onChange={(e) => patch({ exposicion: e.target.value })} placeholder="Blefarostato…" style={{ minHeight: 70 }} /></Field>
        <Field label="Hallazgo" optional><TextArea value={data.hallazgo} onChange={(e) => patch({ hallazgo: e.target.value })} placeholder="Hallazgos…" style={{ minHeight: 70 }} /></Field>
      </div>
      <Field label="Descripción operatoria" required
             help="Redacción que aparecerá en el parte quirúrgico. Escribe @ para mencionar un insumo.">
        <div className="spread" style={{ marginBottom: 8 }}>
          <span className="muted-note">Escribe @ para insertar un insumo, o pide un borrador.</span>
          <AIButton label="Ayúdame a redactar" onRun={onAI} icon="mdi-text-box-edit-outline" small />
        </div>
        <OperatorioEditor value={data.operatorio} onChange={(v) => patch({ operatorio: v })} listaInsumos={listaInsumos} resetToken={operatorioVersion} />
      </Field>
    </div>
  );
}

function StepEvolucion({ data, patch }) {
  const block = (titulo, ek, ik, ph1, ph2) => (
    <div style={{ marginBottom: 18 }}>
      <div className="divider-lbl">{titulo}</div>
      <div className="field-row">
        <Field label="Evolución"><TextArea value={data[ek]} onChange={(e) => patch({ [ek]: e.target.value })} placeholder={ph1} style={{ minHeight: 76 }} /></Field>
        <Field label="Indicación"><TextArea value={data[ik]} onChange={(e) => patch({ [ik]: e.target.value })} placeholder={ph2} style={{ minHeight: 76 }} /></Field>
      </div>
    </div>
  );
  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="eyebrow"><i className="mdi mdi-clipboard-pulse-outline"></i>Paso 5 · Evolución e indicaciones</div>
        <h2>Notas del curso clínico</h2>
        <p>Plantillas de evolución e indicaciones para los tres momentos. Opcional pero recomendado.</p>
      </div>
      {block('Pre-quirúrgica', 'pre_evolucion', 'pre_indicacion', 'Estado previo a cirugía…', 'Indicaciones antes de cirugía…')}
      {block('Post-quirúrgica', 'post_evolucion', 'post_indicacion', 'Evolución posterior…', 'Indicaciones post-operatorias…')}
      {block('Alta', 'alta_evolucion', 'alta_indicacion', 'Condición al alta…', 'Indicaciones al alta…')}
    </div>
  );
}

function StepKardex({ data, patch, onAI }) {
  const { opcionesMedicamentos, vias, responsables } = useCatalogs();
  const set = (i, k, v) => { const m = [...data.medicamentos]; m[i] = { ...m[i], [k]: v }; patch({ medicamentos: m }); };
  const add = () => patch({ medicamentos: [...data.medicamentos, { _id: rid(), medicamento: (opcionesMedicamentos || [])[0]?.medicamento || '', dosis: '', frecuencia: '', via: (vias || [])[0] || '', responsable: (responsables || [])[0] || '' }] });
  const del = (i) => patch({ medicamentos: data.medicamentos.filter((_, j) => j !== i) });
  return (
    <div className="wiz-panel" style={{ maxWidth: 760 }}>
      <div className="wiz-panel-head">
        <div className="spread">
          <div>
            <div className="eyebrow"><i className="mdi mdi-pill"></i>Paso 6 · Kardex de medicación</div>
            <h2>Medicación protocolizada</h2>
          </div>
          <AIButton label="Sugerir medicación" onRun={onAI} icon="mdi-flask-outline" />
        </div>
        <p>Fármacos que se administran de rutina en este procedimiento.</p>
      </div>
      <table className="etable">
        <thead><tr><th style={{ width: '30%' }}>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Vía</th><th>Responsable</th><th style={{ width: 40 }}></th></tr></thead>
        <tbody>
          {data.medicamentos.length === 0 && <tr className="etable-empty"><td colSpan={6}>Sin medicación. Agrega una fila o usa la sugerencia IA.</td></tr>}
          {data.medicamentos.map((m, i) => {
            const bg = RESPONSABLE_ROW_COLOR[m.responsable];
            const medOptions = ensureOption((opcionesMedicamentos || []).map((x) => x.medicamento), m.medicamento);
            const viaOptions = ensureOption(vias, m.via);
            const respOptions = ensureOption(responsables, m.responsable);
            return (
              <tr key={m._id || i} style={bg ? { background: bg } : null}>
                <td><select className="cell-inp" value={m.medicamento} onChange={(e) => set(i, 'medicamento', e.target.value)}>{medOptions.map((x) => <option key={x} value={x}>{x}</option>)}</select></td>
                <td><input className="cell-inp" value={m.dosis} onChange={(e) => set(i, 'dosis', e.target.value)} placeholder="1 gota" /></td>
                <td><input className="cell-inp" value={m.frecuencia} onChange={(e) => set(i, 'frecuencia', e.target.value)} placeholder="c/8 h" /></td>
                <td><select className="cell-inp" value={m.via} onChange={(e) => set(i, 'via', e.target.value)}>{viaOptions.map((x) => <option key={x}>{x}</option>)}</select></td>
                <td><select className="cell-inp" value={m.responsable} onChange={(e) => set(i, 'responsable', e.target.value)}>{respOptions.map((x) => <option key={x}>{x}</option>)}</select></td>
                <td><button className="row-x" onClick={() => del(i)}><i className="mdi mdi-close"></i></button></td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <button className="btn-add-row" onClick={add}><i className="mdi mdi-plus"></i>Agregar medicamento</button>
    </div>
  );
}

function StepInsumos({ data, patch, onAI }) {
  const { insumosDisponibles } = useCatalogs();
  const cats = Object.keys(insumosDisponibles || {});
  const set = (i, k, v) => {
    const arr = [...data.insumos];
    arr[i] = { ...arr[i], [k]: v };
    if (k === 'categoria') arr[i].nombre = '';
    patch({ insumos: arr });
  };
  const add = () => patch({ insumos: [...data.insumos, { _id: rid(), categoria: cats[0] || '', nombre: '', cantidad: 1 }] });
  const del = (i) => patch({ insumos: data.insumos.filter((_, j) => j !== i) });
  return (
    <div className="wiz-panel" style={{ maxWidth: 720 }}>
      <div className="wiz-panel-head">
        <div className="spread">
          <div>
            <div className="eyebrow"><i className="mdi mdi-package-variant-closed"></i>Paso 7 · Lista de insumos</div>
            <h2>Insumos y materiales</h2>
          </div>
          <AIButton label="Sugerir insumos" onRun={onAI} icon="mdi-cube-scan" />
        </div>
        <p>Materiales que el equipo prepara para este procedimiento. Alimenta el pedido a bodega.</p>
      </div>
      <table className="etable">
        <thead><tr><th style={{ width: '34%' }}>Categoría</th><th>Insumo</th><th style={{ width: 90 }}>Cantidad</th><th style={{ width: 40 }}></th></tr></thead>
        <tbody>
          {data.insumos.length === 0 && <tr className="etable-empty"><td colSpan={4}>Sin insumos. Agrega una fila o usa la sugerencia IA.</td></tr>}
          {data.insumos.map((it, i) => {
            const bg = it.categoria ? colorForCategoria(it.categoria) : null;
            const catOptions = ensureOption(cats, it.categoria);
            const nombreOptions = ensureOption((insumosDisponibles[it.categoria] || []).map((n) => n.nombre), it.nombre);
            return (
              <tr key={it._id || i} style={bg ? { background: bg } : null}>
                <td><select className="cell-inp" value={it.categoria} onChange={(e) => set(i, 'categoria', e.target.value)}>{catOptions.map((c) => <option key={c}>{c}</option>)}</select></td>
                <td><select className="cell-inp" value={it.nombre} onChange={(e) => set(i, 'nombre', e.target.value)}><option value="">Seleccionar…</option>{nombreOptions.map((n) => <option key={n}>{n}</option>)}</select></td>
                <td><input className="cell-inp" type="number" min="1" value={it.cantidad} onChange={(e) => set(i, 'cantidad', e.target.value)} /></td>
                <td><button className="row-x" onClick={() => del(i)}><i className="mdi mdi-close"></i></button></td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <button className="btn-add-row" onClick={add}><i className="mdi mdi-plus"></i>Agregar insumo</button>
    </div>
  );
}

function StepRevision({ data, goTo, steps }) {
  const rows = [
    ['Datos generales', data.membrete && data.categoria && data.horas, `${data.categoria || '—'} · ${data.horas || '—'} h`],
    ['Códigos', data.codigos.length > 0, `${data.codigos.length} procedimiento(s)`],
    ['Equipo', data.staff.filter((s) => s.nombre).length > 0, `${data.staff.filter((s) => s.nombre).length} miembro(s)`],
    ['Técnica operatoria', !!data.operatorio, data.operatorio ? 'Descripción lista' : 'Pendiente'],
    ['Evolución', data.pre_evolucion || data.post_evolucion || data.alta_evolucion, 'Notas clínicas'],
    ['Kardex', data.medicamentos.filter((m) => m.medicamento).length > 0, `${data.medicamentos.length} medicamento(s)`],
    ['Insumos', data.insumos.filter((i) => i.nombre).length > 0, `${data.insumos.filter((i) => i.nombre).length} insumo(s)`],
  ];
  return (
    <div className="wiz-panel">
      <div className="wiz-panel-head">
        <div className="eyebrow"><i className="mdi mdi-check-decagram-outline"></i>Paso 8 · Revisión final</div>
        <h2>Revisa antes de guardar</h2>
        <p>Confirma que cada sección esté completa. Puedes volver a cualquier paso para ajustar.</p>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {rows.map((r, i) => (
          <div key={i} className="sel-chip" style={{ cursor: 'pointer' }} onClick={() => goTo(steps.findIndex((s) => s.title === r[0]) >= 0 ? steps.findIndex((s) => s.title === r[0]) : 1)}>
            <i className={`mdi ${r[1] ? 'mdi-check-circle' : 'mdi-circle-outline'}`} style={{ fontSize: 20, color: r[1] ? 'var(--success)' : 'var(--fg-fade)' }}></i>
            <div style={{ flex: 1 }}>
              <div style={{ font: '600 13.5px var(--font-body)', color: 'var(--fg-1)' }}>{r[0]}</div>
              <div className="muted-note">{r[2]}</div>
            </div>
            <i className="mdi mdi-pencil-outline" style={{ color: 'var(--fg-mute)', fontSize: 16 }}></i>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ============================ WIZARD ============================ */
export function ProtocolWizard({ initial, mode, duplicandoDe, onExit, onSave, api }) {
  const { sugerenciasStaff, sugerenciasInsumos, sugerenciasMedicamentos, operatorioSugerido, insumosDisponibles, opcionesMedicamentos } = useCatalogs();
  const [data, setData] = useState(() => initial ? { ...emptyData(), ...initial } : emptyData());
  const [step, setStep] = useState(mode === 'edit' ? 1 : 0);
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState(null);
  const [confirmExit, setConfirmExit] = useState(false);
  const [pvOpen, setPvOpen] = useState(false);
  const [loadingTemplateId, setLoadingTemplateId] = useState(null);
  const [staffOptions, setStaffOptions] = useState({});
  const [operatorioVersion, setOperatorioVersion] = useState(0);
  const [toastNode, showToast] = useToast();
  const patch = useCallback((p) => setData((d) => ({ ...d, ...p })), []);

  useEffect(() => {
    let alive = true;
    api.staffOptions().then((opts) => { if (alive) setStaffOptions(opts || {}); });
    return () => { alive = false; };
  }, [api]);

  const steps = useMemo(() => ([
    { key: 'inicio',    title: 'Inicio',              hint: 'Plantilla base',       hideInEdit: true, optional: true },
    { key: 'datos',     title: 'Datos generales',     hint: 'Nombre y categoría',   done: (d) => d.membrete && d.cirugia && d.categoria && d.horas },
    { key: 'codigos',   title: 'Códigos',             hint: 'Procedimientos',       done: (d) => d.codigos.length > 0 },
    { key: 'equipo',    title: 'Equipo',              hint: 'Staff quirúrgico',     optional: true, done: (d) => d.staff.filter((s) => s.nombre).length > 0 },
    { key: 'tecnica',   title: 'Técnica operatoria',  hint: 'Descripción',          done: (d) => !!d.operatorio },
    { key: 'evolucion', title: 'Evolución',           hint: 'Notas clínicas',       optional: true, done: (d) => !!(d.pre_evolucion || d.post_evolucion || d.alta_evolucion) },
    { key: 'kardex',    title: 'Kardex',              hint: 'Medicación',           optional: true, done: (d) => d.medicamentos.filter((m) => m.medicamento).length > 0 },
    { key: 'insumos',   title: 'Insumos',             hint: 'Materiales',           optional: true, done: (d) => d.insumos.filter((i) => i.nombre).length > 0 },
    { key: 'revision',  title: 'Revisión',            hint: 'Guardar',              optional: true },
  ]), []);

  const visibleSteps = mode === 'edit' ? steps.filter((s) => !s.hideInEdit) : steps;
  const requiredSteps = steps.filter((s) => !s.optional);
  const completed = requiredSteps.filter((s) => s.done && s.done(data)).length;
  const progress = Math.round((completed / requiredSteps.length) * 100);
  const cur = steps[step];

  const resolveStaffSuggestion = useCallback((categoria) => {
    const rules = sugerenciasStaff[categoria] || sugerenciasStaff.default || [];
    return rules.map((r) => {
      const opciones = r.grupo ? (staffOptions[r.grupo] || []) : [];
      const first = opciones[0];
      return { _id: rid(), funcion: r.funcion, nombre: first ? first.nombre : '', trabajador_id: first ? first.id : null };
    });
  }, [sugerenciasStaff, staffOptions]);

  const resolveInsumosSuggestion = useCallback((categoria) => {
    const rules = sugerenciasInsumos[categoria] || sugerenciasInsumos.default || [];
    const out = [];
    rules.forEach((r) => {
      for (const cat of Object.keys(insumosDisponibles || {})) {
        const found = (insumosDisponibles[cat] || []).find((it) => it.nombre.toLowerCase().includes(r.match.toLowerCase()));
        if (found) { out.push({ _id: rid(), categoria: cat, nombre: found.nombre, cantidad: r.cantidad }); break; }
      }
    });
    return out;
  }, [sugerenciasInsumos, insumosDisponibles]);

  const resolveMedsSuggestion = useCallback((categoria) => {
    const rules = sugerenciasMedicamentos[categoria] || sugerenciasMedicamentos.default || [];
    const out = [];
    rules.forEach((r) => {
      const found = (opcionesMedicamentos || []).find((m) => m.medicamento.toLowerCase().includes(r.match.toLowerCase()));
      if (found) out.push({ _id: rid(), medicamento: found.medicamento, dosis: r.dosis, frecuencia: r.frecuencia, via: r.via, responsable: r.responsable });
    });
    return out;
  }, [sugerenciasMedicamentos, opcionesMedicamentos]);

  const loadTemplate = async (t) => {
    setLoadingTemplateId(t.id);
    let codigoNombre = t.nombre;
    try {
      const rows = await api.searchCodigos(t.codigo);
      const match = rows.find((r) => r.codigo === t.codigo);
      if (match) codigoNombre = match.descripcion;
    } catch { /* deja el nombre de la plantilla como respaldo */ }

    setData({
      ...emptyData(),
      ...t.data,
      codigos: [{ codigo: t.codigo, nombre: codigoNombre }],
      staff: resolveStaffSuggestion(t.data.categoria),
      operatorio: operatorioSugerido[t.data.categoria] || operatorioSugerido.default,
      medicamentos: resolveMedsSuggestion(t.data.categoria),
      insumos: resolveInsumosSuggestion(t.data.categoria),
    });
    setOperatorioVersion((v) => v + 1);
    setLoadingTemplateId(null);
    setStep(1);
    showToast(`Plantilla «${t.nombre}» precargada con IA. Revisa y ajusta.`);
  };

  const aiStaff = () => { patch({ staff: resolveStaffSuggestion(data.categoria) }); showToast('Equipo típico autocompletado.'); };
  const aiOperatorio = () => {
    patch({ operatorio: operatorioSugerido[data.categoria] || operatorioSugerido.default });
    setOperatorioVersion((v) => v + 1);
    showToast('Borrador de técnica generado. Revísalo y edítalo.');
  };
  const aiMeds = () => {
    const sug = resolveMedsSuggestion(data.categoria);
    patch({ medicamentos: [...data.medicamentos, ...sug] });
    showToast(`${sug.length} medicamento(s) sugeridos agregados.`);
  };
  const aiInsumos = () => {
    const sug = resolveInsumosSuggestion(data.categoria);
    patch({ insumos: [...data.insumos, ...sug] });
    showToast(`${sug.length} insumo(s) sugeridos agregados.`);
  };

  const canSave = data.membrete && data.cirugia && data.categoria && data.horas && data.codigos.length > 0 && data.operatorio;

  const doSave = async () => {
    if (!canSave || saving) return;
    setSaving(true);
    setSaveError(null);
    const payload = {
      ...data,
      staff: data.staff.filter((s) => s.nombre),
      medicamentos: data.medicamentos.filter((m) => m.medicamento),
      insumos: data.insumos.filter((i) => i.nombre),
    };
    const res = await api.guardar(payload);
    setSaving(false);
    if (res.ok && res.success) {
      onSave({ ...data, id: res.generated_id || data.id });
      setSaved(true);
    } else {
      setSaveError(res.message || 'No se pudo guardar el protocolo.');
    }
  };

  const idxInVisible = visibleSteps.findIndex((s) => s.key === cur.key);
  const goPrev = () => { const i = idxInVisible - 1; if (i >= 0) setStep(steps.findIndex((s) => s.key === visibleSteps[i].key)); };
  const goNext = () => { const i = idxInVisible + 1; if (i < visibleSteps.length) setStep(steps.findIndex((s) => s.key === visibleSteps[i].key)); };

  if (saved) {
    return (
      <div className="mod-scroll">
        <div className="saved">
          <div className="chk"><i className="mdi mdi-check-bold"></i></div>
          <h2>Protocolo guardado</h2>
          <p>«{data.membrete}» está disponible en el catálogo y listo para aplicarse en cirugías.</p>
          <div className="row">
            <button className="btn btn-ghost" onClick={() => setSaved(false)}>Seguir editando</button>
            <button className="btn btn-primary" onClick={onExit}><i className="mdi mdi-format-list-bulleted"></i>Ir al catálogo</button>
          </div>
        </div>
        {toastNode}
      </div>
    );
  }

  return (
    <div className={`wiz${pvOpen ? ' pv-open' : ''}`}>
      <aside className="wiz-steps">
        <div className="wiz-back" onClick={() => (mode === 'edit' || data.membrete || step > 0) ? setConfirmExit(true) : onExit()}>
          <i className="mdi mdi-arrow-left"></i>Volver al catálogo
        </div>
        <div className="wiz-progress-wrap">
          <div className="wiz-progress-label"><span>{mode === 'edit' ? (duplicandoDe ? 'Duplicando' : 'Editando') : 'Nuevo protocolo'}</span><span>{progress}%</span></div>
          <div className="wiz-progress"><span style={{ width: progress + '%' }}></span></div>
        </div>
        {visibleSteps.map((s, i) => {
          const realIdx = steps.findIndex((x) => x.key === s.key);
          const isDone = s.done && s.done(data);
          const active = cur.key === s.key;
          return (
            <div key={s.key} className={`step-item${active ? ' active' : ''}${isDone && !active ? ' done' : ''}`} onClick={() => setStep(realIdx)}>
              <span className="step-dot">{isDone && !active ? <i className="mdi mdi-check" style={{ fontSize: 15 }}></i> : (s.key === 'inicio' ? <i className="mdi mdi-rocket-launch-outline" style={{ fontSize: 14 }}></i> : (s.key === 'revision' ? <i className="mdi mdi-flag-checkered" style={{ fontSize: 14 }}></i> : i))}</span>
              <div className="step-tx">
                <div className="step-name">{s.title}{s.optional && s.key !== 'inicio' && s.key !== 'revision' && <span style={{ fontWeight: 400, color: 'var(--fg-fade)', fontSize: 10 }}> · opcional</span>}</div>
                <div className="step-hint">{s.hint}</div>
              </div>
            </div>
          );
        })}
        <div className="steps-foot">
          <div className="wiz-mini-tip">
            <i className="mdi mdi-lightbulb-on-outline"></i>
            <span>Los pasos <strong>opcionales</strong> mejoran la documentación pero no bloquean el guardado. Solo Datos, Códigos y Técnica son obligatorios.</span>
          </div>
        </div>
      </aside>

      <main className="wiz-form">
        {duplicandoDe && cur.key !== 'inicio' && (
          <div className="muted-note" style={{ maxWidth: 640, margin: '0 auto 14px' }}>
            <i className="mdi mdi-content-copy"></i> Duplicando de «{duplicandoDe}» — ajusta el nombre corto antes de guardar.
          </div>
        )}
        {cur.key === 'inicio' && <StepInicio onTemplate={loadTemplate} onBlank={() => setStep(1)} loadingId={loadingTemplateId} />}
        {cur.key === 'datos' && <StepDatos data={data} patch={patch} />}
        {cur.key === 'codigos' && <StepCodigos data={data} patch={patch} api={api} />}
        {cur.key === 'equipo' && <StepEquipo data={data} patch={patch} onAI={aiStaff} staffOptions={staffOptions} />}
        {cur.key === 'tecnica' && <StepTecnica data={data} patch={patch} onAI={aiOperatorio} operatorioVersion={operatorioVersion} />}
        {cur.key === 'evolucion' && <StepEvolucion data={data} patch={patch} />}
        {cur.key === 'kardex' && <StepKardex data={data} patch={patch} onAI={aiMeds} />}
        {cur.key === 'insumos' && <StepInsumos data={data} patch={patch} onAI={aiInsumos} />}
        {cur.key === 'revision' && <StepRevision data={data} goTo={setStep} steps={steps} />}

        {saveError && cur.key !== 'inicio' && (
          <div className="muted-note" style={{ maxWidth: 640, margin: '14px auto 0', color: 'var(--danger)' }}>{saveError}</div>
        )}

        {cur.key !== 'inicio' && (
          <div className="wiz-footer">
            <button className="btn btn-ghost" onClick={goPrev} disabled={idxInVisible <= 0} style={idxInVisible <= 0 ? { opacity: .4, pointerEvents: 'none' } : null}>
              <i className="mdi mdi-arrow-left"></i>Anterior
            </button>
            <div className="spacer"></div>
            {cur.key === 'revision' ? (
              <button className="btn btn-primary btn-lg" onClick={doSave} disabled={!canSave || saving} style={(!canSave || saving) ? { opacity: .5, pointerEvents: 'none' } : null}>
                <i className={`mdi ${saving ? 'mdi-loading' : 'mdi-content-save-check-outline'}`}></i>{saving ? 'Guardando…' : 'Guardar protocolo'}
              </button>
            ) : (
              <>
                <button className="btn btn-ghost" onClick={doSave} disabled={!canSave || saving} style={(!canSave || saving) ? { opacity: .45, pointerEvents: 'none' } : null} title={canSave ? '' : 'Completa datos, códigos y técnica'}>
                  <i className="mdi mdi-content-save-outline"></i>Guardar
                </button>
                <button className="btn btn-primary" onClick={goNext}>Continuar<i className="mdi mdi-arrow-right"></i></button>
              </>
            )}
          </div>
        )}
      </main>

      <aside className="wiz-preview">
        <div className="wiz-preview-head">
          <span className="lbl"><span className="live-dot"></span>Vista previa en vivo</span>
          <button className="pv-close" onClick={() => setPvOpen(false)}><i className="mdi mdi-close"></i></button>
        </div>
        <ProtocolDoc data={data} />
      </aside>

      <button className="pv-toggle" onClick={() => setPvOpen((o) => !o)}>
        <i className={`mdi ${pvOpen ? 'mdi-close' : 'mdi-file-document-outline'}`}></i>{pvOpen ? 'Cerrar' : 'Vista previa'}
      </button>

      {confirmExit && (
        <Modal icon="mdi-exit-run" tone="warning" title="¿Salir sin guardar?"
               confirmLabel="Salir" confirmTone="warning"
               onConfirm={onExit} onClose={() => setConfirmExit(false)}>
          Los cambios que no hayas guardado se perderán.
        </Modal>
      )}
      {toastNode}
    </div>
  );
}
