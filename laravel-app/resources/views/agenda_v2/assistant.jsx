/* MedForge — Auxiliar virtual de enfermería (guía reactiva de la HC) */

function computeGuia(ctx, acts) {
  const { f, dx, recetas, examenes, solicitudes } = ctx;
  const has = (code) => dx.some((d) => (d.code || "").startsWith(code));
  const algunAV = f.avscOD || f.avscOS || f.avccOD || f.avccOS;
  const algunaPIO = f.pioOD || f.pioOS;

  // --- completitud (secciones clave) ---
  const checks = [
    { ok: !!f.motivo.trim(), txt: "Registrar el motivo de consulta" },
    { ok: !!algunAV, txt: "Tomar la agudeza visual" },
    { ok: dx.length > 0, txt: "Definir al menos un diagnóstico" },
    { ok: !!f.plan.trim(), txt: "Escribir el plan e indicaciones" },
  ];
  const hechos = checks.filter((c) => c.ok).length;
  const pendientes = checks.filter((c) => !c.ok);

  // --- tips contextuales por diagnóstico ---
  const tips = [];
  if (has("H40")) {
    tips.push({ icon: "mdi-eye-check-outline", txt: "En glaucoma conviene registrar la PIO en ambos ojos y evaluar el nervio óptico.",
      actions: [
        { label: "Pedir OCT de nervio", run: () => acts.addExamen("oct_nervio") },
        { label: "Pedir campo visual", run: () => acts.addExamen("campo") },
      ] });
    if (!algunaPIO) pendientes.push({ txt: "Falta la PIO (clave en glaucoma)", soft: true });
  }
  if (has("H25")) {
    tips.push({ icon: "mdi-calculator-variant-outline", txt: "Para operar la catarata necesitas la biometría (cálculo de LIO). ¿Genero la solicitud quirúrgica?",
      actions: [
        { label: "Pedir biometría", run: () => acts.addExamen("biometria") },
        { label: "Solicitud de faco", run: () => acts.addSolicitud("faco") },
      ] });
  }
  if (has("H35") || has("E11")) {
    tips.push({ icon: "mdi-grain", txt: "Patología retiniana/macular: documenta con OCT macular; valora angiografía si sospechas exudación.",
      actions: [
        { label: "Pedir OCT macular", run: () => acts.addExamen("oct_mac") },
        { label: "Pedir angiografía", run: () => acts.addExamen("angio") },
      ] });
  }
  if (has("H11")) {
    tips.push({ icon: "mdi-content-cut", txt: "Pterigión sintomático: puedes generar la solicitud de exéresis con injerto.",
      actions: [{ label: "Generar solicitud", run: () => acts.addSolicitud("pterigion") }] });
  }
  // nudges generales
  if (dx.length > 0 && examenes.length === 0 && tips.length === 0)
    tips.push({ icon: "mdi-clipboard-text-search-outline", txt: "¿Deseas apoyar el diagnóstico con algún examen complementario?", actions: [] });
  if (recetas.some((r) => !r.nm.trim()))
    pendientes.push({ txt: "Hay una línea de receta sin medicamento", soft: true });
  if (solicitudes.some((s) => !s.dx))
    pendientes.push({ txt: "Una solicitud no tiene diagnóstico asociado", soft: true });

  return { hechos, total: checks.length, pendientes, tips };
}

function AsistenteVirtual({ ctx, acts }) {
  const { hechos, total, pendientes, tips } = computeGuia(ctx, acts);
  const pct = Math.round((hechos / total) * 100);
  const listo = pendientes.length === 0 && hechos === total;

  return (
    <div className="aux">
      <div className="aux-head">
        <div className="aux-av"><i className="mdi mdi-account-heart"></i></div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className="aux-nm">Sofía · auxiliar virtual</div>
          <div className="aux-sb">{listo ? "Todo listo para finalizar" : "Te acompaño con la historia"}</div>
        </div>
        <div className={`aux-ring${listo ? " done" : ""}`} style={{ background: `conic-gradient(${listo ? "var(--success)" : "var(--primary)"} ${pct}%, var(--border) 0)` }}>
          <span>{hechos}/{total}</span>
        </div>
      </div>

      <div className="aux-body">
        {listo && (
          <div className="aux-msg ok"><i className="mdi mdi-check-circle"></i><div>¡Excelente! La historia está completa. Puedes finalizar la consulta cuando quieras.</div></div>
        )}

        {pendientes.length > 0 && (
          <div className="aux-block">
            <div className="aux-block-h"><i className="mdi mdi-progress-check"></i>Te falta por llenar</div>
            {pendientes.map((p, i) => (
              <div key={i} className={`aux-pend${p.soft ? " soft" : ""}`}><i className={`mdi ${p.soft ? "mdi-alert-outline" : "mdi-circle-medium"}`}></i>{p.txt}</div>
            ))}
          </div>
        )}

        {tips.map((t, i) => (
          <div key={i} className="aux-msg">
            <i className={`mdi ${t.icon}`}></i>
            <div style={{ flex: 1 }}>
              <div>{t.txt}</div>
              {t.actions && t.actions.length > 0 && (
                <div className="aux-acts">
                  {t.actions.map((a, j) => <button key={j} onClick={a.run}>{a.label}</button>)}
                </div>
              )}
            </div>
          </div>
        ))}

        <div className="aux-foot"><i className="mdi mdi-shield-check-outline"></i>Sugerencias de apoyo. No reemplazan el criterio clínico del profesional.</div>
      </div>
    </div>
  );
}

Object.assign(window, { computeGuia, AsistenteVirtual });
