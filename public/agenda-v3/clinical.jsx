/* MedForge — bloques clínicos: antecedentes, receta, exámenes, solicitudes */

/* ---------- AddChips: input + lista de chips ---------- */
function ChipAdder({ items, onAdd, onRemove, placeholder, tone }) {
  const [v, setV] = React.useState("");
  const add = () => { const x = v.trim(); if (x) { onAdd(x); setV(""); } };
  return (
    <div>
      <div className="chip-row">
        {items.length === 0 && <span className="chip-empty">Sin registros</span>}
        {items.map((it, i) => (
          <span key={i} className={`antec-chip${tone ? " " + tone : ""}`}>{it}<button onClick={() => onRemove(i)}><i className="mdi mdi-close"></i></button></span>
        ))}
      </div>
      <div className="chip-add">
        <input value={v} onChange={(e) => setV(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") add(); }} placeholder={placeholder} />
        <button className="btn sm btn-ghost" onClick={add}><i className="mdi mdi-plus"></i></button>
      </div>
    </div>
  );
}

/* ---------- ANTECEDENTES (persistentes) ---------- */
function Antecedentes({ hc, antec, onChange }) {
  const upd = (key, next) => {
    const merged = { ...antec, [key]: next };
    onChange(merged);
    CLIN.saveAntec(hc, merged);
  };
  return (
    <div className="hc-sec">
      <div className="sh"><i className="mdi mdi-history"></i><span className="t">Antecedentes</span>
        <span className="badge badge--success" style={{ marginLeft: "auto" }}><i className="mdi mdi-content-save-check-outline"></i>Se guardan permanentemente</span>
      </div>
      <div className="sb antec-grid">
        {CLIN.ANTEC_CATS.map((cat) => (
          <div key={cat.key} className="antec-cat">
            <div className="ach"><i className={`mdi ${cat.icon}`}></i>{cat.label}</div>
            <ChipAdder items={antec[cat.key] || []} placeholder={`Añadir a ${cat.label.toLowerCase()}…`}
              tone={cat.key === "alergicos" ? "danger" : ""}
              onAdd={(x) => upd(cat.key, [...(antec[cat.key] || []), x])}
              onRemove={(i) => upd(cat.key, antec[cat.key].filter((_, j) => j !== i))} />
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------- RECETA ---------- */
function RecetaBuilder({ recetas, setRecetas, notify }) {
  const addMed = (medId) => {
    const m = CLIN.MEDS.find((x) => x.id === medId);
    setRecetas([...recetas, m
      ? { nm: m.nm, dosis: "1 gota", via: m.via, frec: m.frec, dur: m.dur, obs: "" }
      : { nm: "", dosis: "", via: CLIN.VIAS[0], frec: CLIN.FRECS[2], dur: CLIN.DURS[2], obs: "" }]);
  };
  const applyPlantilla = (pl) => {
    const filas = pl.items.map((it) => {
      const m = CLIN.MEDS.find((x) => x.id === it.med);
      return { nm: m.nm, dosis: "1 gota", via: m.via, frec: it.frec || m.frec, dur: it.dur || m.dur, obs: "" };
    });
    setRecetas([...recetas, ...filas]);
    if (notify) notify(`Plantilla «${pl.nm}» añadida a la receta`, "success");
  };
  const upd = (i, k, v) => setRecetas(recetas.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
  const del = (i) => setRecetas(recetas.filter((_, j) => j !== i));

  return (
    <div className="hc-sec">
      <div className="sh"><i className="mdi mdi-prescription"></i><span className="t">Receta</span>{recetas.length > 0 && <span className="badge badge--primary">{recetas.length}</span>}</div>
      <div className="sb">
        <div className="rx-tpl">
          <span className="rx-tpl-lbl"><i className="mdi mdi-file-document-multiple-outline"></i>Plantillas:</span>
          {CLIN.PLANTILLAS_RX.map((pl) => (
            <button key={pl.id} onClick={() => applyPlantilla(pl)}>{pl.nm}</button>
          ))}
        </div>

        {recetas.length > 0 && (
          <div className="rx-list">
            {recetas.map((r, i) => (
              <div key={i} className="rx-row">
                <div className="rx-grid">
                  <div className="field"><label>Medicamento</label><input value={r.nm} onChange={(e) => upd(i, "nm", e.target.value)} placeholder="Medicamento / gota" /></div>
                  <div className="field"><label>Dosis</label><input value={r.dosis} onChange={(e) => upd(i, "dosis", e.target.value)} placeholder="1 gota" /></div>
                  <div className="field"><label>Vía</label><select value={r.via} onChange={(e) => upd(i, "via", e.target.value)}>{CLIN.VIAS.map((v) => <option key={v}>{v}</option>)}</select></div>
                  <div className="field"><label>Frecuencia</label><select value={r.frec} onChange={(e) => upd(i, "frec", e.target.value)}>{CLIN.FRECS.map((v) => <option key={v}>{v}</option>)}</select></div>
                  <div className="field"><label>Duración</label><select value={r.dur} onChange={(e) => upd(i, "dur", e.target.value)}>{CLIN.DURS.map((v) => <option key={v}>{v}</option>)}</select></div>
                  <button className="rx-del" onClick={() => del(i)} title="Quitar"><i className="mdi mdi-trash-can-outline"></i></button>
                </div>
                <input className="rx-obs" value={r.obs} onChange={(e) => upd(i, "obs", e.target.value)} placeholder="Observación (opcional): aplicar en OD, suspender si irritación…" />
              </div>
            ))}
          </div>
        )}

        <div className="rx-add">
          <select value="" onChange={(e) => { if (e.target.value) addMed(e.target.value); e.target.value = ""; }}>
            <option value="">+ Añadir del vademécum…</option>
            {CLIN.MEDS.map((m) => <option key={m.id} value={m.id}>{m.nm} — {m.clase}</option>)}
          </select>
          <button className="btn sm btn-outline-primary" onClick={() => addMed(null)}><i className="mdi mdi-plus"></i>Línea manual</button>
        </div>
      </div>
    </div>
  );
}

/* ---------- EXÁMENES ---------- */
function ExamenesSelector({ examenes, setExamenes }) {
  const toggle = (ex) => {
    if (examenes.some((e) => e.id === ex.id)) setExamenes(examenes.filter((e) => e.id !== ex.id));
    else setExamenes([...examenes, { id: ex.id, nm: ex.nm, ojo: CLIN.OJOS[2], obs: "" }]);
  };
  const upd = (id, k, v) => setExamenes(examenes.map((e) => (e.id === id ? { ...e, [k]: v } : e)));
  return (
    <div className="hc-sec">
      <div className="sh"><i className="mdi mdi-clipboard-text-search-outline"></i><span className="t">Exámenes complementarios</span>{examenes.length > 0 && <span className="badge badge--primary">{examenes.length}</span>}</div>
      <div className="sb">
        <div className="ex-grid">
          {CLIN.EXAMENES.map((ex) => {
            const on = examenes.some((e) => e.id === ex.id);
            return (
              <button key={ex.id} className={`ex-tile${on ? " on" : ""}`} onClick={() => toggle(ex)}>
                <i className={`mdi ${on ? "mdi-check-circle" : ex.icon}`}></i>
                <span>{ex.nm}</span>
                <small>{ex.grupo}</small>
              </button>
            );
          })}
        </div>
        {examenes.length > 0 && (
          <div className="ex-sel">
            {examenes.map((e) => (
              <div key={e.id} className="ex-row">
                <span className="ex-nm"><i className="mdi mdi-arrow-right-thin"></i>{e.nm}</span>
                <select value={e.ojo} onChange={(ev) => upd(e.id, "ojo", ev.target.value)}>{CLIN.OJOS.map((o) => <option key={o}>{o}</option>)}</select>
                <input value={e.obs} onChange={(ev) => upd(e.id, "obs", ev.target.value)} placeholder="Indicación / observación" />
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

/* ---------- SOLICITUD procedimiento / quirúrgica ---------- */
function SolicitudBuilder({ solicitudes, setSolicitudes, dxList, notify }) {
  const [draft, setDraft] = React.useState(null);
  const open = (proc) => setDraft({
    proc: proc || "", ojo: CLIN.OJOS[0], prioridad: "Normal",
    dx: dxList[0] ? `${dxList[0].code} · ${dxList[0].nm}` : "", obs: "",
  });
  const add = () => {
    const p = CLIN.PROCEDIMIENTOS.find((x) => x.id === draft.proc);
    if (!p) { if (notify) notify("Selecciona el procedimiento", "danger"); return; }
    setSolicitudes([...solicitudes, { ...draft, nm: p.nm, clase: p.clase }]);
    setDraft(null);
    if (notify) notify(`Solicitud de ${p.clase.toLowerCase()} generada`, "success");
  };
  return (
    <div className="hc-sec">
      <div className="sh"><i className="mdi mdi-file-document-plus-outline"></i><span className="t">Solicitudes de procedimiento / cirugía</span>{solicitudes.length > 0 && <span className="badge badge--primary">{solicitudes.length}</span>}</div>
      <div className="sb">
        {solicitudes.map((s, i) => (
          <div key={i} className="sol-card">
            <div className="sol-top">
              <span className={`badge ${s.clase === "Cirugía" ? "badge--danger" : "badge--info"}`}><i className="mdi mdi-hospital-box-outline"></i>{s.clase}</span>
              <span className="sol-nm">{s.nm}</span>
              <button className="rx-del" onClick={() => setSolicitudes(solicitudes.filter((_, j) => j !== i))}><i className="mdi mdi-trash-can-outline"></i></button>
            </div>
            <div className="sol-meta">
              <span><b>Ojo:</b> {s.ojo}</span><span><b>Prioridad:</b> {s.prioridad}</span>
              {s.dx && <span><b>Dx:</b> {s.dx}</span>}
            </div>
            {s.obs && <div className="sol-obs">{s.obs}</div>}
            <div className="sol-foot"><i className="mdi mdi-arrow-right-circle-outline"></i>Se enviará al módulo de Solicitudes para agendamiento</div>
          </div>
        ))}

        {draft ? (
          <div className="sol-draft">
            <div className="form-grid">
              <div className="field full"><label>Procedimiento / cirugía</label>
                <select value={draft.proc} onChange={(e) => setDraft({ ...draft, proc: e.target.value })}>
                  <option value="">Seleccionar…</option>
                  {CLIN.PROCEDIMIENTOS.map((p) => <option key={p.id} value={p.id}>{p.nm} ({p.clase})</option>)}
                </select>
              </div>
              <div className="field"><label>Ojo</label><select value={draft.ojo} onChange={(e) => setDraft({ ...draft, ojo: e.target.value })}>{CLIN.OJOS.map((o) => <option key={o}>{o}</option>)}</select></div>
              <div className="field"><label>Prioridad</label><select value={draft.prioridad} onChange={(e) => setDraft({ ...draft, prioridad: e.target.value })}>{CLIN.PRIORIDADES.map((o) => <option key={o}>{o}</option>)}</select></div>
              <div className="field full"><label>Diagnóstico asociado</label>
                <select value={draft.dx} onChange={(e) => setDraft({ ...draft, dx: e.target.value })}>
                  <option value="">— Sin diagnóstico —</option>
                  {dxList.map((d, i) => <option key={i} value={`${d.code} · ${d.nm}`}>{d.code} · {d.nm}</option>)}
                </select>
              </div>
              <div className="field full"><label>Observaciones</label><input value={draft.obs} onChange={(e) => setDraft({ ...draft, obs: e.target.value })} placeholder="Técnica, lateralidad, insumos, notas para quirófano…" /></div>
            </div>
            <div style={{ display: "flex", gap: 8, marginTop: 10 }}>
              <button className="btn sm btn-light" onClick={() => setDraft(null)}>Cancelar</button>
              <button className="btn sm btn-primary" onClick={add}><i className="mdi mdi-check"></i>Generar solicitud</button>
            </div>
          </div>
        ) : (
          <button className="btn btn-outline-primary" onClick={() => open()}><i className="mdi mdi-plus"></i>Nueva solicitud</button>
        )}
      </div>
    </div>
  );
}

Object.assign(window, { ChipAdder, Antecedentes, RecetaBuilder, ExamenesSelector, SolicitudBuilder });
