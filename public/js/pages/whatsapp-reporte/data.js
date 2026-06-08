/* ============================================================================
   MedForge · Reporte ejecutivo de WhatsApp — capa de datos (mock)
   ----------------------------------------------------------------------------
   Reproduce el contrato de datos que el controlador dashboard() de Laravel
   entrega al reporte ejecutivo (summary, analytics.sources/intents/frictions,
   lifecycle, funnel, breakdowns.agent_live_status / handoffs_by_role,
   insights, recommendations) — ver pdf/dashboard-executive.blade.php.

   buildReport(periodKey, sedeId) devuelve TODO el reporte agregado para el
   período y la sede seleccionados. Los filtros cambian de verdad los números
   (volumen, ratios, narrativa) para que el reporte cuente una historia
   distinta según el corte. Determinista: estable entre recargas.

   KPIs marcados con  nuevo:true  no existen aún en BD — se diseñan como si
   tuvieran datos y se renderizan con placeholder "Datos en implementación".
   ========================================================================== */
window.WAR = (function () {
  "use strict";

  const SLA_TARGET = 15;                      // minutos
  const TODAY = new Date(2026, 5, 8);         // 08 jun 2026 (mes 0-indexado)

  const SEDES = [
    { id: "ceibos",     label: "Ceibos",     zone: "Av. del Bombero · Guayaquil", base: 41 },
    { id: "villa_club", label: "Villa Club", zone: "Vía a Daule · Guayaquil",     base: 29 },
  ];

  /* ---- PRNG determinista ---- */
  function mulberry32(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  function seed(str) { let h = 1779033703 ^ str.length; for (let i = 0; i < str.length; i++) { h = Math.imul(h ^ str.charCodeAt(i), 3432918353); h = h << 13 | h >>> 19; } return h >>> 0; }
  function rng(key) { return mulberry32(seed(key)); }
  /* valor estable en [min,max] a partir de una clave */
  function pick(key, min, max) { return min + rng(key)() * (max - min); }
  const round = Math.round;
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

  /* ---- fechas ---- */
  const DAY = 86400000;
  const iso = (d) => d.toISOString().slice(0, 10);
  const fmtShort = (d) => d.toLocaleDateString("es-EC", { day: "2-digit", month: "short" });
  const fmtLong  = (d) => d.toLocaleDateString("es-EC", { day: "2-digit", month: "long", year: "numeric" });

  const PERIODS = {
    hoy: { key: "hoy", label: "Hoy",            days: 1,  granularity: "hour" },
    "7d": { key: "7d", label: "Últimos 7 días", days: 7,  granularity: "day" },
    "30d":{ key: "30d",label: "Últimos 30 días",days: 30, granularity: "day" },
    "90d":{ key: "90d",label: "Últimos 90 días",days: 90, granularity: "week" },
  };

  /* volumen de conversaciones nuevas de un día concreto en una sede */
  function dayVolume(date, sedeBase, sedeId) {
    const r = rng("vol|" + iso(date) + "|" + sedeId);
    const dow = date.getDay();                       // 0 dom … 6 sáb
    const weekend = dow === 0 ? 0.32 : dow === 6 ? 0.6 : 1;
    // leve tendencia ascendente hacia el presente
    const ageDays = Math.round((TODAY - date) / DAY);
    const trend = 1 + (90 - clamp(ageDays, 0, 90)) / 90 * 0.22;
    const noise = 0.8 + r() * 0.42;
    return Math.max(2, round(sedeBase * weekend * trend * noise));
  }

  function windowDays(periodKey) {
    const p = PERIODS[periodKey] || PERIODS["30d"];
    const out = [];
    for (let i = p.days - 1; i >= 0; i--) out.push(new Date(TODAY.getTime() - i * DAY));
    return out;
  }

  function sedeList(sedeId) { return sedeId === "todas" ? SEDES : SEDES.filter((s) => s.id === sedeId); }

  /* ---- perfiles de calidad por (período · sede) — varían la "historia" ---- */
  function quality(periodKey, sedeId) {
    const k = "q|" + periodKey + "|" + sedeId;
    // Ceibos rinde algo mejor que Villa Club; el filtro mueve los ratios.
    const sedeLift = sedeId === "villa_club" ? -6 : sedeId === "ceibos" ? 4 : 0;
    return {
      attentionRate:  round(clamp(pick(k + "att", 80, 91) + sedeLift, 62, 96)),
      medianResp:     round(clamp(pick(k + "med", 7, 17) - sedeLift * 0.4, 4, 28)),
      p75Resp:        round(clamp(pick(k + "p75", 18, 34) - sedeLift * 0.5, 12, 48)),
      slaRate:        round(clamp(pick(k + "sla", 68, 86) + sedeLift, 48, 95)),
      bookingRate:    round(clamp(pick(k + "bk", 17, 25) + sedeLift * 0.5, 9, 34) * 10) / 10,
      containment:    round(clamp(pick(k + "cont", 58, 69) + sedeLift * 0.3, 40, 78) * 10) / 10,
      identification: round(clamp(pick(k + "idn", 63, 77) + sedeLift * 0.4, 45, 88) * 10) / 10,
      csat:           round(clamp(pick(k + "csat", 86, 94) + sedeLift * 0.3, 70, 99) * 10) / 10, // [NUEVO]
      reactivation:   round(clamp(pick(k + "react", 6, 13) + sedeLift * 0.2, 2, 22) * 10) / 10,  // [NUEVO]
    };
  }

  /* distribuye un total en partes según pesos (con ruido determinista) */
  function distribute(total, items, keyBase) {
    const weighted = items.map((it, i) => ({ it, w: it.w * (0.85 + rng(keyBase + i)() * 0.3) }));
    const sumW = weighted.reduce((a, x) => a + x.w, 0);
    let acc = 0;
    const out = weighted.map((x, i) => {
      const v = i === weighted.length - 1 ? total - acc : round(total * x.w / sumW);
      acc += v;
      return { ...x.it, total: Math.max(0, v) };
    });
    const grand = out.reduce((a, x) => a + x.total, 0) || 1;
    out.forEach((x) => { x.share = round(x.total / grand * 1000) / 10; });
    return out.sort((a, b) => b.total - a.total);
  }

  /* ---- catálogos base ---- */
  const SRC = [
    { id: "meta",    label: "Meta Ads",          icon: "mdi-bullseye-arrow",          w: 34, conv: 1.18, ident: 0.62 },
    { id: "organico",label: "Orgánico (directo)",icon: "mdi-whatsapp",                w: 26, conv: 0.92, ident: 0.78 },
    { id: "web",     label: "Formulario web",    icon: "mdi-web",                     w: 16, conv: 1.05, ident: 0.71 },
    { id: "deriv",   label: "Derivación interna",icon: "mdi-account-arrow-right-outline", w: 14, conv: 1.34, ident: 0.86 },
    { id: "google",  label: "Google Ads",        icon: "mdi-google",                  w: 10, conv: 1.0,  ident: 0.58 },
  ];
  const INTENT = [
    { label: "Agendar valoración / cirugía", w: 38 },
    { label: "Información de precios",        w: 24 },
    { label: "Reagendar o cancelar",         w: 16 },
    { label: "Resultados y seguimiento",     w: 13 },
    { label: "Otros / no clasificado",       w: 9 },
  ];
  const LIFECYCLE = [
    { label: "Captación (pacientes nuevos)", w: 41, conv: 1.0,  ident: 0.55 },
    { label: "Operación (citas vigentes)",   w: 27, conv: 1.22, ident: 0.92 },
    { label: "Seguimiento clínico",          w: 18, conv: 1.34, ident: 0.95 },
    { label: "Reactivación",                 w: 8,  conv: 1.12, ident: 0.74 },
    { label: "Información general",           w: 6,  conv: 0.4,  ident: 0.4 },
  ];
  const FRICTION = [
    { label: "No entendió la intención",     w: 30 },
    { label: "Pidió hablar con un humano",   w: 26 },
    { label: "Datos incompletos del paciente",w: 18 },
    { label: "Mensaje fuera de horario",     w: 15 },
    { label: "Repetición del menú principal",w: 11 },
  ];
  const TEAMS = [
    { name: "Admisión",     w: 38 },
    { name: "Oftalmología", w: 31 },
    { name: "Caja",         w: 18 },
    { name: "Soporte",      w: 13 },
  ];
  const AGENTS = [
    { name: "Lcda. Paola Cordero",   role: "Admisión",     sede: "ceibos",     w: 1.0 },
    { name: "Dra. Carolina Rivera",  role: "Oftalmología", sede: "ceibos",     w: 0.78 },
    { name: "Lic. Juan Mejía",       role: "Caja",         sede: "villa_club", w: 0.7 },
    { name: "Lcda. Andrea Salinas",  role: "Admisión",     sede: "villa_club", w: 0.64 },
    { name: "Lic. Felipe Vargas",    role: "Soporte",      sede: "ceibos",     w: 0.52 },
  ];

  /* ---- agregación principal ---- */
  function aggregateVolume(periodKey, sedeId) {
    const days = windowDays(periodKey);
    const sedes = sedeList(sedeId);
    let conv = 0;
    const perSede = {};
    sedes.forEach((s) => (perSede[s.id] = 0));
    const daily = days.map((d) => {
      let dayTotal = 0;
      sedes.forEach((s) => { const v = dayVolume(d, s.base, s.id); dayTotal += v; perSede[s.id] += v; });
      conv += dayTotal;
      return { date: d, total: dayTotal };
    });
    return { conv, daily, perSede, days };
  }

  function buildTrend(periodKey, sedeId, vol, q) {
    const g = (PERIODS[periodKey] || PERIODS["30d"]).granularity;
    const bRate = q.bookingRate / 100;
    const attRate = q.attentionRate / 100;
    const botRate = q.containment / 100;
    const mkPoint = (label, total) => ({
      label,
      conversaciones: total,
      atendidas: round(total * attRate),
      bot: round(total * botRate * (1 - 0.15)),
      citas: round(total * bRate),
    });
    if (g === "hour") {
      // distribuir el día en horas de clínica 07–20
      const total = vol.conv;
      const shape = [1, 3, 6, 9, 11, 12, 10, 7, 9, 11, 10, 7, 4, 2]; // 07..20
      const sumS = shape.reduce((a, b) => a + b, 0);
      return shape.map((w, i) => mkPoint(String(7 + i).padStart(2, "0") + "h", round(total * w / sumS)));
    }
    if (g === "week") {
      const out = [];
      for (let i = 0; i < vol.daily.length; i += 7) {
        const chunk = vol.daily.slice(i, i + 7);
        const total = chunk.reduce((a, x) => a + x.total, 0);
        out.push(mkPoint("Sem " + (out.length + 1), total));
      }
      return out;
    }
    return vol.daily.map((d) => mkPoint(fmtShort(d.date), d.total));
  }

  function report(periodKey, sedeId) {
    const P = PERIODS[periodKey] || PERIODS["30d"];
    const vol = aggregateVolume(periodKey, sedeId);
    const q = quality(periodKey, sedeId);
    const prevVol = (function () {
      // ventana anterior equivalente
      const days = windowDays(periodKey).length;
      const sedes = sedeList(sedeId);
      let conv = 0;
      for (let i = days * 2 - 1; i >= days; i--) {
        const d = new Date(TODAY.getTime() - i * DAY);
        sedes.forEach((s) => (conv += dayVolume(d, s.base, s.id)));
      }
      return conv;
    })();
    const prevQ = quality("prev-" + periodKey, sedeId);

    const conv = vol.conv;
    const people = round(conv * 0.87);
    const messagesIn = round(conv * pick("min|" + periodKey + sedeId, 3.8, 4.8));
    const messagesOut = round(conv * pick("mout|" + periodKey + sedeId, 4.6, 5.8));
    const attended = round(conv * q.attentionRate / 100);
    const lost = round(conv * (1 - q.attentionRate / 100) * 0.55);
    const resolvedBot = round(conv * q.containment / 100);
    const resolved = round(attended * pick("res|" + periodKey + sedeId, 0.62, 0.78));
    const bookings = round(people * q.bookingRate / 100);
    const bookingFail = round(bookings * pick("bf|" + periodKey + sedeId, 0.06, 0.13));
    const handoffs = round(conv * (1 - q.containment / 100));
    const handoffRate = round((1 - q.containment / 100) * 1000) / 10;

    /* deltas vs período anterior */
    const pct = (cur, prev) => (prev > 0 ? round((cur - prev) / prev * 1000) / 10 : 0);
    const prevBookings = round(prevVol * 0.87 * prevQ.bookingRate / 100);
    const deltas = {
      conversations: pct(conv, prevVol),
      people:        pct(people, round(prevVol * 0.87)),
      attentionRate: round((q.attentionRate - prevQ.attentionRate) * 10) / 10,
      medianResp:    round((q.medianResp - prevQ.medianResp) * 10) / 10,
      bookings:      pct(bookings, prevBookings),
      bookingRate:   round((q.bookingRate - prevQ.bookingRate) * 10) / 10,
      containment:   round((q.containment - prevQ.containment) * 10) / 10,
    };

    /* breakdowns escalados al volumen */
    const sources = distribute(conv, SRC, "src|" + periodKey + sedeId).map((s) => ({
      id: s.id, label: s.label, icon: s.icon, total: s.total, share: s.share,
      identified: round(s.total * s.ident),
      bookings: round(s.total * (q.bookingRate / 100) * s.conv),
      bookingRate: round((q.bookingRate * s.conv) * 10) / 10,
      handoffs: round(s.total * (1 - q.containment / 100)),
      cpl: round(pick("cpl|" + s.id + periodKey, 1.4, 6.8) * 100) / 100,    // [NUEVO] costo por lead
    }));
    const intents = distribute(conv, INTENT, "int|" + periodKey + sedeId);
    const lifecycle = distribute(conv, LIFECYCLE, "lif|" + periodKey + sedeId).map((l) => ({
      label: l.label, total: l.total, share: l.share,
      identified: round(l.total * l.ident),
      bookings: round(l.total * (q.bookingRate / 100) * l.conv),
      bookingRate: round((q.bookingRate * l.conv) * 10) / 10,
      handoffs: round(l.total * (1 - q.containment / 100)),
    }));
    const frictions = distribute(handoffs, FRICTION, "fr|" + periodKey + sedeId);

    /* embudo de servicio */
    const funnelRaw = [
      { label: "Escribieron", value: people },
      { label: "Atendidas",   value: attended },
      { label: "Resueltas",   value: resolved },
      { label: "Agendadas",   value: bookings },
    ];
    const funnel = funnelRaw.map((f, i) => ({
      label: f.label, value: f.value,
      rateFromStart: round(f.value / funnelRaw[0].value * 1000) / 10,
      rateToNext: i < funnelRaw.length - 1 ? round(funnelRaw[i + 1].value / f.value * 1000) / 10 : null,
    }));

    /* distribución de tiempos de 1ª respuesta (histograma) */
    const respBuckets = ["<5", "5–15", "15–30", "30–60", "60+"];
    const respShape = q.medianResp <= 10 ? [34, 38, 16, 8, 4] : q.medianResp <= 16 ? [22, 36, 24, 12, 6] : [14, 30, 28, 18, 10];
    const responseDist = respBuckets.map((b, i) => ({ bucket: b + " min", count: round(attended * respShape[i] / 100), pctInSla: i <= 1 }));

    /* por sede (comparativo) */
    const bySede = SEDES.filter((s) => sedeId === "todas" || s.id === sedeId).map((s) => {
      const sq = quality(periodKey, s.id);
      const sc = vol.perSede[s.id] || 0;
      return {
        id: s.id, label: s.label, zone: s.zone,
        conversations: sc, share: conv > 0 ? round(sc / conv * 1000) / 10 : 0,
        attentionRate: sq.attentionRate, bookingRate: sq.bookingRate,
        bookings: round(sc * 0.87 * sq.bookingRate / 100), medianResp: sq.medianResp,
      };
    });

    /* agentes */
    const agents = AGENTS.filter((a) => sedeId === "todas" || a.sede === sedeId).map((a) => {
      const r = rng("ag|" + a.name + periodKey + sedeId);
      const at = round(attended * a.w / AGENTS.reduce((s, x) => s + x.w, 0) * (sedeId === "todas" ? 1 : 1.7));
      return {
        name: a.name, role: a.role, sede: a.sede,
        attended: at, resolved: round(at * (0.6 + r() * 0.25)),
        avgRespMin: round(q.medianResp * (0.7 + r() * 0.8) * 10) / 10,
        convRate: round((q.bookingRate * (0.75 + r() * 0.6)) * 10) / 10,
      };
    }).sort((a, b) => b.attended - a.attended);

    const teams = distribute(handoffs, TEAMS, "tm|" + periodKey + sedeId).map((t) => {
      const r = rng("tmd|" + t.name + periodKey + sedeId);
      const resolvedT = round(t.total * (0.5 + r() * 0.3));
      const queuedT = round(t.total * (0.08 + r() * 0.12));
      return { name: t.name, total: t.total, queued: queuedT, assigned: Math.max(0, t.total - resolvedT - queuedT), resolved: resolvedT };
    });

    /* mapa de calor por hora × día (demanda) */
    const dows = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
    const hours = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18];
    const heat = dows.map((d, di) => ({
      day: d,
      cells: hours.map((h) => {
        const r = rng("heat|" + d + h + periodKey + sedeId)();
        const peak = (h >= 9 && h <= 12) || (h >= 15 && h <= 17) ? 1.6 : 1;
        const weekend = di === 5 ? 0.5 : 1;
        return { hour: h, value: round(r * 40 * peak * weekend) };
      }),
    }));
    const heatMax = Math.max(...heat.flatMap((row) => row.cells.map((c) => c.value)), 1);

    /* narrativa: insights + recomendaciones generados según los números */
    const insights = buildInsights({ q, deltas, sources, lifecycle, funnel, bookings, conv, attended, lost, periodKey, sedeId });
    const recommendations = buildRecommendations({ q, deltas, sources, frictions, lost });

    return {
      period: { ...P, from: iso(vol.days[0]), to: iso(vol.days[vol.days.length - 1]),
                fromLabel: fmtLong(vol.days[0]), toLabel: fmtLong(vol.days[vol.days.length - 1]) },
      sede: sedeId === "todas" ? { id: "todas", label: "Todas las sedes" } : SEDES.find((s) => s.id === sedeId),
      generatedAt: TODAY.toLocaleDateString("es-EC", { day: "2-digit", month: "long", year: "numeric" }) + " · 09:14",
      slaTarget: SLA_TARGET,
      summary: {
        conversationsNew: conv, peopleInbound: people, messagesIn, messagesOut, messagesTotal: messagesIn + messagesOut,
        attentionRate: q.attentionRate, attendedHuman: attended, lostNeedsHuman: lost, resolvedBot,
        resolved, medianFirstResp: q.medianResp, p75FirstResp: q.p75Resp, slaRate: q.slaRate,
        bookings, bookingPatients: round(bookings * 0.93), bookingFailures: bookingFail, bookingRate: q.bookingRate,
        handoffs, handoffRate, identificationRate: q.identification, containmentRate: q.containment,
        csat: q.csat, reactivationRate: q.reactivation,
        deltas,
      },
      trend: buildTrend(periodKey, sedeId, vol, q),
      bySede, sources, intents, lifecycle, funnel, responseDist, frictions, agents, teams,
      heat, heatMax, hours, insights, recommendations,
    };
  }

  function buildInsights(ctx) {
    const { q, deltas, sources, lifecycle, funnel, bookings, periodKey } = ctx;
    const top = sources[0], topLc = lifecycle[0];
    const bestConvLc = [...lifecycle].sort((a, b) => b.bookingRate - a.bookingRate)[0];
    const out = [];
    out.push({
      tone: deltas.conversations >= 0 ? "success" : "warning",
      title: "Demanda del canal",
      body: `El canal recibió ${ctx.conv.toLocaleString("es-EC")} conversaciones nuevas, ${deltas.conversations >= 0 ? "un alza" : "una baja"} de ${Math.abs(deltas.conversations)}% frente al período anterior. ${top.label} concentra el ${top.share}% del origen.`,
    });
    out.push({
      tone: q.attentionRate >= 85 ? "success" : q.attentionRate >= 75 ? "warning" : "danger",
      title: "Cobertura humana",
      body: `Se atendió al ${q.attentionRate}% de las conversaciones que escalaron a una persona, con una mediana de ${q.medianResp} min a la primera respuesta. ${ctx.lost} pacientes pidieron ayuda y no recibieron respuesta oportuna.`,
    });
    out.push({
      tone: q.bookingRate >= 20 ? "success" : "warning",
      title: "Conversión a cita",
      body: `${bookings.toLocaleString("es-EC")} citas se agendaron desde WhatsApp (${q.bookingRate}% de quienes escribieron). El segmento que mejor convierte es "${bestConvLc.label}" con ${bestConvLc.bookingRate}%, muy por encima de la captación fría.`,
    });
    out.push({
      tone: q.containment >= 62 ? "success" : "warning",
      title: "Automatización",
      body: `El bot contuvo el ${q.containment}% de las conversaciones sin intervención humana (${deltas.containment >= 0 ? "+" : ""}${deltas.containment} pts vs. período anterior). El resto requirió handoff a un agente.`,
    });
    return out;
  }

  function buildRecommendations(ctx) {
    const { q, deltas, sources, frictions, lost } = ctx;
    const topFr = frictions[0];
    const recs = [];
    if (lost > 0) recs.push(`Cerrar la brecha de ${lost} conversaciones perdidas: reforzar el turno de mayor demanda (09–12 h) en Admisión.`);
    if (q.bookingRate < 22) recs.push(`Elevar la conversión a cita priorizando los segmentos de seguimiento y reactivación, que convierten 1.3× mejor que la captación nueva.`);
    if (topFr) recs.push(`Reducir la fricción "${topFr.label.toLowerCase()}" (${topFr.share}% de los handoffs) afinando el flujo del bot en Flowmaker.`);
    if (deltas.medianResp > 0) recs.push(`La mediana de 1ª respuesta subió ${deltas.medianResp} min: revisar la asignación automática fuera de horario pico.`);
    recs.push(`Activar medición de costo por conversación e ingreso atribuido para cerrar el caso de ROI del canal (ver KPIs en implementación).`);
    return recs.slice(0, 5);
  }

  return { PERIODS, SEDES, SLA_TARGET, report, buildReport: report };
})();
