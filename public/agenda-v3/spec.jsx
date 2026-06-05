/* MedForge Agendamiento — Especificación técnica (handoff a desarrollo) */

function SpecModule() {
  const tablas = [
    { n: "sedes", cols: [["id", "PK bigint", "pk"], ["nombre", "varchar"], ["abreviatura", "varchar"], ["hora_apertura", "time"], ["hora_cierre", "time"]] },
    { n: "areas_clinicas", cols: [["id", "PK"], ["nombre", "varchar"], ["color", "char(7)"], ["icono", "varchar"]] },
    { n: "medicos", cols: [["id", "PK", "pk"], ["nombre", "varchar"], ["especialidad", "varchar"], ["sede_id", "FK→sedes", "fk"]] },
    { n: "medico_area", cols: [["medico_id", "FK", "fk"], ["area_id", "FK", "fk"]] },
    { n: "salas", cols: [["id", "PK", "pk"], ["nombre", "varchar"], ["tipo", "enum"], ["area_id", "FK→areas", "fk"], ["sede_id", "FK→sedes", "fk"], ["capacidad", "int"], ["activa", "bool"]] },
    { n: "tipos_cita", cols: [["id", "PK", "pk"], ["nombre", "varchar"], ["area_id", "FK→areas", "fk"], ["duracion_min", "int"], ["color", "char(7)"], ["tipos_sala", "json"]] },
    { n: "horarios_medico", cols: [["id", "PK", "pk"], ["medico_id", "FK", "fk"], ["sede_id", "FK", "fk"], ["dia_semana", "tinyint"], ["hora_inicio", "time"], ["hora_fin", "time"]] },
    { n: "bloqueos", cols: [["id", "PK", "pk"], ["scope", "enum(medico,sala)"], ["ref_id", "bigint"], ["fecha", "date"], ["hora_inicio", "time"], ["hora_fin", "time"], ["motivo", "varchar"], ["tipo", "enum"]] },
    { n: "citas", cols: [["id", "PK", "pk"], ["paciente_id", "FK", "fk"], ["medico_id", "FK", "fk"], ["sala_id", "FK", "fk"], ["tipo_cita_id", "FK", "fk"], ["sede_id", "FK", "fk"], ["fecha", "date"], ["hora_inicio", "time"], ["hora_fin", "time"], ["estado", "enum"], ["es_sobreturno", "bool"], ["justif_sobreturno", "text"], ["wa_estado", "enum"], ["notas", "text"]] },
    { n: "cita_eventos", cols: [["id", "PK", "pk"], ["cita_id", "FK", "fk"], ["estado", "enum"], ["registrado_en", "datetime"], ["usuario_id", "FK", "fk"]] },
  ];

  const endpoints = [
    ["GET", "/api/v2/agenda", "Citas filtradas (fecha, sede, médico, área, estado)", "Cita[] paginado"],
    ["POST", "/api/v2/agenda/citas", "Crea cita; valida conflictos en el backend", "Cita | 422 conflictos[]"],
    ["PUT", "/api/v2/agenda/citas/{id}", "Edita / reagenda; revalida conflictos", "Cita | 422"],
    ["PATCH", "/api/v2/agenda/citas/{id}/estado", "Transición de estado del flujo", "Cita + cita_eventos"],
    ["DELETE", "/api/v2/agenda/citas/{id}", "Cancela cita (soft, estado=cancelado)", "204"],
    ["GET", "/api/v2/agenda/disponibilidad", "Slots y salas libres por médico/tipo/fecha", "{ slots[], salas[] }"],
    ["POST", "/api/v2/agenda/citas/{id}/recordatorio", "Dispara confirmación por WhatsApp", "{ wa_estado }"],
    ["GET", "/api/v2/agenda/flowboard", "Vista de recepción en tiempo real por sede", "{ columnas, alertas }"],
    ["GET", "/api/v2/config/horarios", "Turnos por médico", "Horario[]"],
    ["POST", "/api/v2/config/bloqueos", "Crea bloqueo manual (médico o sala)", "Bloqueo"],
    ["GET|POST|PUT", "/api/v2/config/{salas|tipos-cita|areas}", "CRUD de catálogos base", "Recurso[]"],
    ["POST", "/api/webhooks/whatsapp", "Entrante del bot: SÍ/NO → confirma o cancela", "200"],
  ];

  const reglas = [
    { i: "mdi-doctor", t: "Un médico, una cita a la vez", d: <span>Constraint de solapamiento: no se permiten dos <code>citas</code> activas del mismo <code>medico_id</code> con rangos <code>[hora_inicio, hora_fin)</code> que se intersecten. Único excepción: <code>es_sobreturno = true</code> con justificación.</span> },
    { i: "mdi-door", t: "Una sala, ocupación única por slot", d: <span>Misma regla de solapamiento sobre <code>sala_id</code>. Aplica también frente a <code>bloqueos</code> de scope <code>sala</code>. No admite excepción por sobreturno.</span> },
    { i: "mdi-tag-outline", t: "El tipo de cita define duración y sala", d: <span><code>hora_fin = hora_inicio + tipos_cita.duracion_min</code> (no editable manualmente). La sala debe pertenecer a <code>tipos_cita.tipos_sala</code> (p. ej. faco → quirófano).</span> },
    { i: "mdi-cancel", t: "Bloqueos manuales", d: <span>Vacaciones, reuniones o mantenimiento crean rangos no-agendables sobre un médico o una sala. La validación de creación los trata como ocupación.</span> },
    { i: "mdi-flash", t: "Sobreturno controlado", d: <span>Permite exceder la regla del médico SOLO con <code>justif_sobreturno</code> obligatoria y rol autorizado. Queda auditado en <code>cita_eventos</code>.</span> },
    { i: "mdi-whatsapp", t: "Confirmación por WhatsApp", d: <span>Al crear/confirmar se envía recordatorio. El webhook entrante mapea <code>SÍ→confirmado</code>, <code>NO→cancelado</code> y actualiza <code>wa_estado</code> sin intervención de recepción.</span> },
    { i: "mdi-account-clock-outline", t: "Flujo de estados unidireccional", d: <span><code>agendado → confirmado → en_sala → en_consulta → completado</code>. Estados terminales alternos: <code>ausente</code>, <code>cancelado</code>, <code>reagendado</code>. Cada transición escribe en <code>cita_eventos</code> (auditoría y cálculo de TAT).</span> },
    { i: "mdi-speedometer", t: "Rendimiento (~100 citas/día/sede)", d: <span>Índices compuestos <code>(sede_id, fecha, medico_id)</code> y <code>(sala_id, fecha)</code>. FlowBoard se refresca por polling de 30–60 s o WebSocket; la grilla virtualiza columnas por recurso.</span> },
  ];

  return (
    <div className="spec-wrap">
      <div className="spec-sec">
        <h2><span className="n">1</span>Modelo de datos (entidad–relación)</h2>
        <p className="lead">Esquema normalizado y extensible. <code>citas</code> es la tabla transaccional; los catálogos (<code>sedes, areas, medicos, salas, tipos_cita</code>) la alimentan, y <code>cita_eventos</code> registra cada transición de estado para auditoría y métricas (TAT, no-shows).</p>
        <div className="er">
          {tablas.map((t) => (
            <div key={t.n} className="er-tbl">
              <div className="h"><i className="mdi mdi-table"></i>{t.n}</div>
              <ul>
                {t.cols.map((c, i) => (
                  <li key={i} className={c[2] || ""}><span>{c[0]}</span><span className="ty">{c[1]}</span></li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <div className="er-rel" style={{ marginTop: 14 }}>
          <span><code>sedes</code> 1—N <code>medicos</code>, <code>salas</code>, <code>citas</code></span>
          <span><code>areas_clinicas</code> 1—N <code>tipos_cita</code>, <code>salas</code>; N—N <code>medicos</code> (vía <code>medico_area</code>)</span>
          <span><code>tipos_cita</code> 1—N <code>citas</code> (define duración + salas compatibles)</span>
          <span><code>citas</code> 1—N <code>cita_eventos</code> (historial de estados)</span>
        </div>
      </div>

      <div className="spec-sec">
        <h2><span className="n">2</span>Reglas de negocio clave</h2>
        <p className="lead">Validables en el backend (Laravel Form Requests + servicio de disponibilidad) y reflejadas en vivo en el formulario de cita.</p>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          {reglas.map((r, i) => (
            <div key={i} className="rule">
              <div className="ic"><i className={`mdi ${r.i}`}></i></div>
              <div><div className="tt">{r.t}</div><div className="ds">{r.d}</div></div>
            </div>
          ))}
        </div>
      </div>

      <div className="spec-sec">
        <h2><span className="n">3</span>Endpoints REST (Laravel API)</h2>
        <p className="lead">La validación de conflictos vive en el servidor (fuente de verdad); el frontend la anticipa para UX. <code>422</code> devuelve el arreglo de conflictos legible para mostrar en el formulario.</p>
        <div className="box" style={{ overflow: "hidden" }}>
          <table className="ep">
            <thead><tr><th>Método</th><th>URI</th><th>Descripción</th><th>Respuesta</th></tr></thead>
            <tbody>
              {endpoints.map((e, i) => {
                const m = e[0].split("|")[0].toLowerCase();
                const cls = m === "get" ? "get" : m === "post" ? "post" : m === "delete" ? "del" : "put";
                return (
                  <tr key={i}>
                    <td><span className={`m ${cls}`}>{e[0]}</span></td>
                    <td className="uri">{e[1]}</td>
                    <td className="ds">{e[2]}</td>
                    <td className="ds">{e[3]}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      <div className="spec-sec">
        <h2><span className="n">4</span>Estructura de componentes React</h2>
        <p className="lead">Componentes funcionales + hooks. React Query para estado de servidor (citas, disponibilidad), Zustand para estado local de UI (filtros, sede activa). Tailwind para estilos.</p>
        <div className="tree">
          <div><span className="c">&lt;AgendaApp&gt;</span> <span className="cm">// shell + sede activa (Zustand) + router de vistas</span></div>
          <div className="d">├─ <span className="c">&lt;CalendarioOperativo&gt;</span> <span className="cm">// día/semana · columnas por recurso</span></div>
          <div className="d">│  ├─ <span className="c">&lt;CalToolbar&gt;</span> <span className="cm">// vista, navegación de fecha, filtros</span></div>
          <div className="d">│  ├─ <span className="c">&lt;ColumnaRecurso&gt;</span> → <span className="c">&lt;ApptBlock&gt;</span> · <span className="c">&lt;SlotZone&gt;</span> · <span className="c">&lt;NowLine&gt;</span></div>
          <div className="d">│  └─ <span className="c">useDisponibilidad()</span> <span className="cm">// React Query</span></div>
          <div className="d">├─ <span className="c">&lt;CitaModal&gt;</span> <span className="cm">// crear/editar</span></div>
          <div className="d">│  ├─ <span className="c">useValidarCita()</span> <span className="cm">// conflictos en vivo</span></div>
          <div className="d">│  ├─ <span className="c">&lt;SugerenciaSala&gt;</span> · <span className="c">&lt;PanelWhatsApp&gt;</span></div>
          <div className="d">│  └─ <span className="c">&lt;BloqueSobreturno&gt;</span></div>
          <div className="d">├─ <span className="c">&lt;FlowBoard&gt;</span> <span className="cm">// recepción · polling 30s</span></div>
          <div className="d">│  ├─ <span className="c">&lt;AlertasBar&gt;</span> <span className="cm">// retrasos, sin confirmar</span></div>
          <div className="d">│  └─ <span className="c">&lt;ColumnaEstado&gt;</span> → <span className="c">&lt;FlowCard&gt;</span> <span className="cm">// avanzar estado</span></div>
          <div className="d">└─ <span className="c">&lt;Configuracion&gt;</span></div>
          <div className="d">{"   "}├─ <span className="c">&lt;Horarios&gt;</span> · <span className="c">&lt;Salas&gt;</span> · <span className="c">&lt;TiposCita&gt;</span></div>
          <div className="d">{"   "}└─ <span className="c">&lt;Areas&gt;</span> · <span className="c">&lt;Bloqueos&gt;</span></div>
        </div>
      </div>

      <div className="spec-sec">
        <h2><span className="n">5</span>Riesgos y supuestos</h2>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <div className="rule"><div className="ic" style={{ background: "#fde2e7", color: "var(--danger)" }}><i className="mdi mdi-alert-outline"></i></div><div><div className="tt">Concurrencia en recepción</div><div className="ds">Dos recepcionistas pueden agendar el mismo slot. Mitigación: validación atómica en backend + bloqueo optimista; el <code>422</code> reabre el formulario con el slot ya ocupado.</div></div></div>
          <div className="rule"><div className="ic" style={{ background: "#fde2e7", color: "var(--danger)" }}><i className="mdi mdi-alert-outline"></i></div><div><div className="tt">Sincronía del bot WhatsApp</div><div className="ds">El webhook puede llegar tarde. <code>wa_estado</code> es informativo; la confirmación nunca bloquea la atención presencial.</div></div></div>
          <div className="rule"><div className="ic" style={{ background: "#fff0d1", color: "#8a5d0a" }}><i className="mdi mdi-help-circle-outline"></i></div><div><div className="tt">Supuesto: 1 paciente por slot/sala</div><div className="ds"><code>capacidad</code> existe en el modelo para futuras salas grupales (charlas, comercial), hoy fijada en 1.</div></div></div>
          <div className="rule"><div className="ic" style={{ background: "#fff0d1", color: "#8a5d0a" }}><i className="mdi mdi-help-circle-outline"></i></div><div><div className="tt">Baja alfabetización digital</div><div className="ds">Botones siempre etiquetados con texto + ícono, errores en lenguaje llano, confirmaciones explícitas antes de cancelar.</div></div></div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { SpecModule });
