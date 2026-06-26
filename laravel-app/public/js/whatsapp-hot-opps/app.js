"use strict";
const { useState, useEffect, useRef, useCallback } = React;
const CFG = window.HOT_OPPS_CONFIG || {
  apiUrl: "/v2/whatsapp/api/operational-queues",
  chatUrl: "/v2/whatsapp/chat",
  pollIntervalMs: 0
};
function Icon({ n, cls = "" }) {
  return React.createElement("span", { className: `mdi mdi-${n} ${cls}`.trim() });
}
function fmt(v, fallback = "\u2014") {
  return v == null ? fallback : v;
}
function fmtBool(v) {
  return v ? "S\xED" : "No";
}
const PRIORITY_CLS = { high: "pri-high", medium: "pri-med", normal: "pri-normal", low: "pri-low" };
const RISK_CLS = { high: "risk-high", medium: "risk-med", low: "risk-low", closed: "risk-closed" };
const ACTION_LABEL = {
  assign_now: "Asignar ahora",
  supervisor_review: "Supervisar",
  rescue_followup: "Rescatar",
  send_template_or_review: "Template / revisar",
  hold_backlog: "Backlog",
  no_action_lost: "Perdida",
  no_action_converted: "Convertida",
  no_action_already_handled: "Atendida"
};
const QUEUE_TABS = [
  { key: "all", label: "Todas", icon: "view-grid-outline" },
  { key: "assignment", label: "Asignar ahora", icon: "account-plus-outline" },
  { key: "supervisor", label: "Supervisar", icon: "shield-account-outline" },
  { key: "rescue", label: "Rescatar", icon: "lifebuoy" }
];
const BUCKET_CLS = {
  hot_open: "bk-hot",
  hot_needs_template: "bk-hot",
  rescue: "bk-rescue",
  backlog: "bk-backlog",
  lost: "bk-lost"
};
function ToastContainer({ toasts }) {
  return React.createElement(
    "div",
    { style: { position: "fixed", bottom: 24, right: 24, zIndex: 9999, display: "flex", flexDirection: "column", gap: 8 } },
    toasts.map(
      (t) => React.createElement("div", { key: t.id, style: {
        background: t.type === "error" ? "#fee2e2" : "#f0fdf4",
        border: `1px solid ${t.type === "error" ? "#fca5a5" : "#86efac"}`,
        color: t.type === "error" ? "#991b1b" : "#166534",
        padding: "10px 16px",
        borderRadius: 8,
        fontSize: 13,
        maxWidth: 360,
        boxShadow: "0 4px 12px rgba(0,0,0,.1)"
      } }, t.msg)
    )
  );
}
function KpiCard({ icon, label, value, sub, color = "primary", urgent = false }) {
  const colors = {
    primary: { bg: "rgba(99,102,241,.1)", fg: "#6366f1", border: "rgba(99,102,241,.2)" },
    amber: { bg: "rgba(245,158,11,.1)", fg: "#d97706", border: "rgba(245,158,11,.2)" },
    red: { bg: "rgba(239,68,68,.1)", fg: "#ef4444", border: "rgba(239,68,68,.2)" },
    green: { bg: "rgba(34,197,94,.1)", fg: "#16a34a", border: "rgba(34,197,94,.2)" },
    gray: { bg: "rgba(107,114,128,.1)", fg: "#6b7280", border: "rgba(107,114,128,.2)" }
  };
  const c = colors[color] || colors.primary;
  return React.createElement(
    "div",
    {
      style: {
        background: "#fff",
        border: `1px solid ${urgent ? c.border : "var(--border)"}`,
        borderRadius: 12,
        padding: "16px 20px",
        display: "flex",
        flexDirection: "column",
        gap: 4,
        boxShadow: urgent ? `0 0 0 2px ${c.border}` : "var(--shadow-xs)"
      }
    },
    React.createElement(
      "div",
      { style: { display: "flex", alignItems: "center", gap: 8, marginBottom: 4 } },
      React.createElement(
        "span",
        { style: { fontSize: 20, color: c.fg } },
        React.createElement(Icon, { n: icon })
      ),
      React.createElement("span", { style: { fontSize: 12, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: ".5px" } }, label)
    ),
    React.createElement("div", { style: { fontSize: 32, fontWeight: 800, color: urgent ? c.fg : "var(--fg-1)", lineHeight: 1 } }, value),
    sub && React.createElement("div", { style: { fontSize: 12, color: "var(--fg-3)", marginTop: 2 } }, sub)
  );
}
function SummaryStrip({ summary, loading }) {
  if (!summary) return null;
  const aq = summary.assignment_queue || {};
  const sq = summary.supervisor_queue || {};
  const rq = summary.rescue_queue || {};
  const na = summary.no_action || {};
  const noActionTotal = (na.converted || 0) + (na.already_handled || 0) + (na.backlog || 0) + (na.lost || 0);
  return React.createElement(
    "div",
    {
      style: {
        display: "grid",
        gridTemplateColumns: "repeat(auto-fit,minmax(170px,1fr))",
        gap: 12,
        padding: "16px 20px",
        background: "var(--bg-soft)",
        borderBottom: "1px solid var(--border)"
      }
    },
    React.createElement(KpiCard, {
      icon: "view-dashboard-outline",
      label: "Decisiones",
      value: loading ? "\u2026" : summary.total_decisions ?? "\u2014",
      color: "gray"
    }),
    React.createElement(KpiCard, {
      icon: "account-plus-outline",
      label: "Asignar ahora",
      value: loading ? "\u2026" : aq.total ?? 0,
      sub: aq.eligible_for_autoassign != null ? `${aq.eligible_for_autoassign} elegibles autoassign` : null,
      color: "primary",
      urgent: (aq.total ?? 0) > 0
    }),
    React.createElement(KpiCard, {
      icon: "shield-account-outline",
      label: "Supervisar",
      value: loading ? "\u2026" : sq.total ?? 0,
      sub: sq.over_sla != null ? `${sq.over_sla} sobre SLA` : null,
      color: "red",
      urgent: (sq.total ?? 0) > 0
    }),
    React.createElement(KpiCard, {
      icon: "lifebuoy",
      label: "Rescatar",
      value: loading ? "\u2026" : rq.total ?? 0,
      sub: rq.total ? `${rq.rescue_followup ?? 0} followup \xB7 ${rq.send_template_or_review ?? 0} template` : null,
      color: "amber",
      urgent: (rq.total ?? 0) > 0
    }),
    React.createElement(KpiCard, {
      icon: "check-circle-outline",
      label: "Sin acci\xF3n",
      value: loading ? "\u2026" : noActionTotal,
      sub: noActionTotal ? `${na.converted ?? 0} conv \xB7 ${na.already_handled ?? 0} atend \xB7 ${na.backlog ?? 0} backlog` : null,
      color: "green"
    })
  );
}
function PriorityBadge({ v }) {
  const labels = { high: "Alta", medium: "Media", normal: "Normal", low: "Baja" };
  return React.createElement("span", { className: `ho-bucket ${PRIORITY_CLS[v] || ""}` }, labels[v] || v || "\u2014");
}
function RiskBadge({ v }) {
  const labels = { high: "Alto", medium: "Medio", low: "Bajo", closed: "Cerrado" };
  return React.createElement("span", { className: `ho-bucket ${RISK_CLS[v] || ""}` }, labels[v] || v || "\u2014");
}
function BucketBadge({ v }) {
  const labels = { hot_open: "HOT", hot_needs_template: "HOT\xB7Template", rescue: "Rescue", backlog: "Backlog", lost: "Perdida" };
  return React.createElement("span", { className: `ho-bucket ${BUCKET_CLS[v] || "bk-backlog"}` }, labels[v] || v || "\u2014");
}
function ActionBadge({ v }) {
  return React.createElement("span", { style: { fontSize: 12, color: "var(--fg-2)" } }, ACTION_LABEL[v] || v || "\u2014");
}
function ItemsTable({ items, loading, emptyMsg }) {
  if (loading) return React.createElement(
    "div",
    { className: "ho-empty" },
    React.createElement(Icon, { n: "loading", cls: "mdi-spin" }),
    " Cargando\u2026"
  );
  if (!items || items.length === 0) return React.createElement(
    "div",
    { className: "ho-empty" },
    React.createElement(Icon, { n: "inbox-outline" }),
    " ",
    emptyMsg || "Sin conversaciones."
  );
  return React.createElement(
    "div",
    { style: { overflowX: "auto" } },
    React.createElement(
      "table",
      { className: "ho-table" },
      React.createElement(
        "thead",
        null,
        React.createElement(
          "tr",
          null,
          ["Conv ID", "Bucket", "Acci\xF3n recomendada", "Prioridad", "Riesgo", "Oportunidad", "Autoassign", "Rescate", "Supervisor", "Booking", "Motivo"].map(
            (h) => React.createElement("th", { key: h }, h)
          )
        )
      ),
      React.createElement(
        "tbody",
        null,
        items.map(
          (item) => React.createElement(
            "tr",
            { key: item.conversation_id },
            React.createElement(
              "td",
              null,
              React.createElement("a", {
                href: `${CFG.chatUrl}/${item.conversation_id}`,
                target: "_blank",
                rel: "noopener noreferrer",
                style: { fontWeight: 700, color: "var(--primary)" }
              }, `#${item.conversation_id}`)
            ),
            React.createElement("td", null, React.createElement(BucketBadge, { v: item.bucket })),
            React.createElement("td", null, React.createElement(ActionBadge, { v: item.recommended_action })),
            React.createElement("td", null, React.createElement(PriorityBadge, { v: item.priority })),
            React.createElement("td", null, React.createElement(RiskBadge, { v: item.risk_level })),
            React.createElement("td", null, item.opportunity_level || "\u2014"),
            React.createElement("td", { style: { textAlign: "center" } }, item.eligible_for_autoassign ? React.createElement(Icon, { n: "check-circle", cls: "text-green" }) : React.createElement(Icon, { n: "minus-circle-outline", cls: "text-muted" })),
            React.createElement("td", { style: { textAlign: "center" } }, item.eligible_for_rescue ? React.createElement(Icon, { n: "check-circle", cls: "text-green" }) : React.createElement(Icon, { n: "minus-circle-outline", cls: "text-muted" })),
            React.createElement("td", { style: { textAlign: "center" } }, item.eligible_for_supervisor_alert ? React.createElement(Icon, { n: "check-circle", cls: "text-green" }) : React.createElement(Icon, { n: "minus-circle-outline", cls: "text-muted" })),
            React.createElement("td", { style: { textAlign: "center" } }, item.has_attributed_booking ? React.createElement(Icon, { n: "calendar-check", cls: "text-green" }) : React.createElement(Icon, { n: "minus-circle-outline", cls: "text-muted" })),
            React.createElement("td", { title: item.reason, style: { maxWidth: 300, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" } }, item.reason || "\u2014")
          )
        )
      )
    )
  );
}
function NoActionPanel({ summary }) {
  if (!summary) return null;
  const na = summary.no_action || {};
  const rows = [
    { label: "Convertidas", icon: "calendar-check", val: na.converted ?? 0, desc: "Paciente ya tiene cita atribuida." },
    { label: "Ya atendidas", icon: "check-decagram", val: na.already_handled ?? 0, desc: "Agente respondi\xF3 dentro del SLA." },
    { label: "Backlog", icon: "archive-clock", val: na.backlog ?? 0, desc: "Sin actividad reciente \u2014 deuda hist\xF3rica." },
    { label: "Perdidas", icon: "account-off-outline", val: na.lost ?? 0, desc: "Sin actividad en m\xE1s de 30 d\xEDas." }
  ];
  return React.createElement(
    "div",
    { style: { padding: "20px", display: "flex", flexDirection: "column", gap: 8 } },
    React.createElement(
      "p",
      { style: { fontSize: 13, color: "var(--fg-3)", marginBottom: 8 } },
      "Estas conversaciones no requieren acci\xF3n inmediata."
    ),
    rows.map(
      (r) => React.createElement(
        "div",
        {
          key: r.label,
          style: {
            display: "flex",
            alignItems: "center",
            gap: 12,
            padding: "12px 16px",
            background: "#fff",
            border: "1px solid var(--border)",
            borderRadius: 8
          }
        },
        React.createElement("span", { style: { fontSize: 22, color: "var(--fg-3)" } }, React.createElement(Icon, { n: r.icon })),
        React.createElement(
          "div",
          { style: { flex: 1 } },
          React.createElement("div", { style: { fontWeight: 600, fontSize: 14 } }, r.label),
          React.createElement("div", { style: { fontSize: 12, color: "var(--fg-3)" } }, r.desc)
        ),
        React.createElement("div", { style: { fontSize: 24, fontWeight: 800, color: "var(--fg-2)" } }, r.val)
      )
    )
  );
}
function App() {
  const today = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const [date, setDate] = useState(today);
  const [tab, setTab] = useState("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [summary, setSummary] = useState(null);
  const [items, setItems] = useState([]);
  const [toasts, setToasts] = useState([]);
  function addToast(msg, type = "error") {
    const id = Date.now();
    setToasts((t) => [...t, { id, msg, type }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 5e3);
  }
  const fetchData = useCallback(async (queue, dateVal) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({ date: dateVal, queue });
      const resp = await fetch(`${CFG.apiUrl}?${params}`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
      });
      if (resp.redirected || resp.status === 302 || resp.headers.get("content-type")?.includes("text/html")) {
        setError("Sesi\xF3n expirada. Por favor recarga la p\xE1gina e inicia sesi\xF3n.");
        setLoading(false);
        return;
      }
      const json = await resp.json();
      if (!resp.ok || !json.ok) {
        setError(json.message || `Error ${resp.status}`);
        setLoading(false);
        return;
      }
      const data = json.data || {};
      setSummary(data.summary || null);
      if (queue === "all") {
        const queues = data.queues || {};
        setItems([
          ...queues.assignment || [],
          ...queues.supervisor || [],
          ...queues.rescue || []
        ]);
      } else {
        setItems(data.items || []);
      }
    } catch (e) {
      setError("Error de red: " + e.message);
      addToast("Error cargando datos: " + e.message);
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    fetchData(tab, date);
  }, [tab, date, fetchData]);
  const displayItems = tab === "all" ? items : items.filter((i) => {
    if (tab === "assignment") return i.recommended_action === "assign_now";
    if (tab === "supervisor") return i.recommended_action === "supervisor_review";
    if (tab === "rescue") return ["rescue_followup", "send_template_or_review"].includes(i.recommended_action);
    return true;
  });
  return React.createElement(
    React.Fragment,
    null,
    React.createElement(ToastContainer, { toasts }),
    /* ── Header ── */
    React.createElement(
      "div",
      { className: "ho-hd" },
      React.createElement(
        "div",
        { style: { display: "flex", alignItems: "center", gap: 12, flex: 1 } },
        React.createElement(
          "span",
          { style: { fontSize: 20, color: "var(--primary)" } },
          React.createElement(Icon, { n: "view-dashboard-variant-outline" })
        ),
        React.createElement("span", { className: "ho-hd-title" }, "Colas operacionales"),
        summary && React.createElement(
          "span",
          { className: "ho-hd-pill" },
          `${summary.total_decisions ?? "\u2014"} decisiones`
        )
      ),
      /* Date picker */
      React.createElement(
        "div",
        { style: { display: "flex", alignItems: "center", gap: 8 } },
        React.createElement(Icon, { n: "calendar-outline", cls: "" }),
        React.createElement("input", {
          type: "date",
          value: date,
          onChange: (e) => setDate(e.target.value),
          style: {
            border: "1px solid var(--border)",
            borderRadius: 6,
            padding: "5px 10px",
            fontSize: 13,
            color: "var(--fg-1)",
            background: "#fff"
          }
        }),
        React.createElement("button", {
          className: "ho-btn",
          onClick: () => fetchData(tab, date),
          disabled: loading
        }, loading ? React.createElement(Icon, { n: "loading", cls: "mdi-spin" }) : React.createElement(Icon, { n: "refresh" }))
      )
    ),
    /* ── KPI Strip ── */
    React.createElement(SummaryStrip, { summary, loading }),
    /* ── Tabs ── */
    React.createElement(
      "div",
      { className: "ho-tabs" },
      QUEUE_TABS.map(
        (t) => React.createElement(
          "button",
          {
            key: t.key,
            className: `ho-tab ${tab === t.key ? "active" : ""}`,
            onClick: () => setTab(t.key)
          },
          React.createElement(Icon, { n: t.icon }),
          " ",
          t.label,
          tab === t.key && !loading && React.createElement("span", {
            style: {
              marginLeft: 6,
              background: "var(--primary)",
              color: "#fff",
              borderRadius: 10,
              fontSize: 11,
              padding: "1px 6px",
              fontWeight: 700
            }
          }, displayItems.length)
        )
      )
    ),
    /* ── Content ── */
    React.createElement(
      "div",
      { style: { flex: 1, overflowY: "auto", padding: "0 0 24px" } },
      error ? React.createElement(
        "div",
        { style: { margin: "24px", padding: "16px", background: "#fee2e2", border: "1px solid #fca5a5", borderRadius: 8, color: "#991b1b", fontSize: 13 } },
        React.createElement(Icon, { n: "alert-circle-outline" }),
        " ",
        error
      ) : tab === "all" && !loading ? React.createElement(
        React.Fragment,
        null,
        React.createElement("div", { style: { padding: "20px 20px 4px", fontSize: 12, fontWeight: 700, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: ".5px" } }, "Sin acci\xF3n inmediata"),
        React.createElement(NoActionPanel, { summary }),
        React.createElement("div", { style: { padding: "20px 20px 4px", fontSize: 12, fontWeight: 700, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: ".5px" } }, "Conversaciones activas en cola"),
        React.createElement(ItemsTable, { items: displayItems, loading, emptyMsg: "No hay conversaciones activas en cola." })
      ) : React.createElement(
        React.Fragment,
        null,
        tab === "supervisor" && (summary?.supervisor_queue?.total ?? 0) === 0 && !loading ? React.createElement(
          "div",
          { className: "ho-empty" },
          React.createElement(Icon, { n: "shield-check-outline" }),
          " Sin conversaciones en supervisi\xF3n \u2014 bien."
        ) : null,
        React.createElement(ItemsTable, {
          items: displayItems,
          loading,
          emptyMsg: `No hay conversaciones en cola "${QUEUE_TABS.find((t2) => t2.key === tab)?.label || tab}".`
        })
      )
    )
  );
}
ReactDOM.createRoot(document.getElementById("root")).render(React.createElement(App));
