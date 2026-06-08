/* ============================================================================
   MedForge · Reporte ejecutivo WhatsApp — gráficas (recharts)
   Sin ResponsiveContainer: medimos el ancho del contenedor de forma síncrona
   (ref + getBoundingClientRect en useLayoutEffect) y pasamos width/height
   numéricos explícitos. Así el chart monta en el primer paint sin depender
   de que dispare el ResizeObserver del ResponsiveContainer.
   ========================================================================== */
const {
  ComposedChart, AreaChart, Area, LineChart, Line,
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
} = Recharts;

const AXIS = { stroke: "#e4e6ef", tick: { fill: "#7e8299", fontSize: 11, fontFamily: '"IBM Plex Sans", sans-serif' } };

/* contenedor que mide su ancho de forma robusta y renderiza children(width) */
function Measured({ height, className = "rep-chart", children }) {
  const ref = React.useRef(null);
  const [w, setW] = React.useState(0);
  React.useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const measure = () => {
      const cw = Math.round(el.clientWidth || el.getBoundingClientRect().width || 0);
      if (cw > 0) setW((prev) => (Math.abs(prev - cw) > 1 ? cw : prev));
    };
    measure();                                   // medición síncrona en el primer layout
    const raf = requestAnimationFrame(measure);  // reintento tras el primer paint
    let ro;
    if (typeof ResizeObserver !== "undefined") { ro = new ResizeObserver(measure); ro.observe(el); }
    window.addEventListener("resize", measure);
    return () => { cancelAnimationFrame(raf); if (ro) ro.disconnect(); window.removeEventListener("resize", measure); };
  }, []);
  return <div className={className} ref={ref} style={{ height, width: "100%" }}>{w > 0 ? children(w) : null}</div>;
}

function RepTooltip({ active, payload, label, unit = "", title }) {
  if (!active || !payload || !payload.length) return null;
  return (
    <div style={{ background: "#fff", border: "1px solid #e4e6ef", borderRadius: 8, boxShadow: "0 4px 12px rgba(16,24,40,.12)", padding: "9px 12px", font: '12px "IBM Plex Sans", sans-serif' }}>
      <div style={{ fontWeight: 700, color: "#172b4c", marginBottom: 5 }}>{title || label}</div>
      {payload.map((p, i) => (
        <div key={i} style={{ display: "flex", alignItems: "center", gap: 7, color: "#3f4254", marginTop: 2 }}>
          <span style={{ width: 9, height: 9, borderRadius: 2, background: p.color || p.fill, display: "inline-block" }}></span>
          <span style={{ flex: 1 }}>{p.name}</span>
          <strong style={{ color: "#172b4c", fontVariantNumeric: "tabular-nums" }}>{p.value.toLocaleString("es-EC")}{unit}</strong>
        </div>
      ))}
    </div>
  );
}

/* Tendencia de demanda — área (conversaciones) + línea (citas) */
function TrendChart({ data, height = 268 }) {
  return (
    <Measured height={height}>
      {(w) => (
        <ComposedChart width={w} height={height} data={data} margin={{ top: 10, right: 6, left: -14, bottom: 0 }}>
          <defs>
            <linearGradient id="gradConv" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#5156be" stopOpacity={0.26} />
              <stop offset="100%" stopColor="#5156be" stopOpacity={0.02} />
            </linearGradient>
            <linearGradient id="gradAtt" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#3596f7" stopOpacity={0.2} />
              <stop offset="100%" stopColor="#3596f7" stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey="label" axisLine={AXIS} tickLine={false} tick={AXIS.tick} interval="preserveStartEnd" minTickGap={18} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS.tick} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} />
          <Area type="monotone" dataKey="conversaciones" name="Conversaciones" stroke="#5156be" strokeWidth={2.4} fill="url(#gradConv)" isAnimationActive={false} />
          <Area type="monotone" dataKey="atendidas" name="Atendidas" stroke="#3596f7" strokeWidth={1.6} fill="url(#gradAtt)" isAnimationActive={false} />
          <Line type="monotone" dataKey="citas" name="Citas agendadas" stroke="#05825f" strokeWidth={2.4} dot={false} isAnimationActive={false} />
        </ComposedChart>
      )}
    </Measured>
  );
}

/* Donut de origen de demanda (con total al centro) */
function DonutChart({ data, nameKey = "label", valueKey = "total", height = 230, unit = "", innerR = "62%", colors = PALETTE }) {
  const total = data.reduce((a, d) => a + d[valueKey], 0);
  return (
    <Measured height={height}>
      {(w) => (
        <React.Fragment>
          <PieChart width={w} height={height}>
            <Pie data={data} dataKey={valueKey} nameKey={nameKey} cx="50%" cy="50%" innerRadius={innerR} outerRadius="92%" paddingAngle={2} stroke="#fff" strokeWidth={2} isAnimationActive={false}>
              {data.map((d, i) => <Cell key={i} fill={colors[i % colors.length]} />)}
            </Pie>
            <Tooltip content={<RepTooltip unit={unit} />} />
          </PieChart>
          <div style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", pointerEvents: "none" }}>
            <div style={{ textAlign: "center" }}>
              <div style={{ font: '600 27px/1 "Rubik", sans-serif', color: "#172b4c", fontVariantNumeric: "tabular-nums" }}>{total.toLocaleString("es-EC")}</div>
              <div style={{ font: '600 10px "IBM Plex Sans", sans-serif', textTransform: "uppercase", letterSpacing: ".06em", color: "#7e8299", marginTop: 3 }}>total</div>
            </div>
          </div>
        </React.Fragment>
      )}
    </Measured>
  );
}

/* Histograma de tiempos de respuesta — sombrea las dentro de SLA */
function HistogramChart({ data, height = 210 }) {
  return (
    <Measured height={height}>
      {(w) => (
        <BarChart width={w} height={height} data={data} margin={{ top: 8, right: 6, left: -16, bottom: 0 }}>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey="bucket" axisLine={AXIS} tickLine={false} tick={AXIS.tick} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS.tick} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} cursor={{ fill: "rgba(81,86,190,.05)" }} />
          <Bar dataKey="count" name="Conversaciones" radius={[6, 6, 0, 0]} maxBarSize={64} isAnimationActive={false}>
            {data.map((d, i) => <Cell key={i} fill={d.pctInSla ? "#05825f" : "#c8c9ee"} />)}
          </Bar>
        </BarChart>
      )}
    </Measured>
  );
}

/* Barras verticales simples */
function ColumnChart({ data, dataKey = "value", labelKey = "label", height = 230, colors }) {
  return (
    <Measured height={height}>
      {(w) => (
        <BarChart width={w} height={height} data={data} margin={{ top: 8, right: 6, left: -16, bottom: 0 }}>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey={labelKey} axisLine={AXIS} tickLine={false} tick={AXIS.tick} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS.tick} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} cursor={{ fill: "rgba(81,86,190,.05)" }} />
          <Bar dataKey={dataKey} name="Conversaciones" radius={[6, 6, 0, 0]} maxBarSize={70} isAnimationActive={false}>
            {data.map((d, i) => <Cell key={i} fill={(colors && colors[i % colors.length]) || "#5156be"} />)}
          </Bar>
        </BarChart>
      )}
    </Measured>
  );
}

Object.assign(window, { TrendChart, DonutChart, HistogramChart, ColumnChart, RepTooltip, Measured });
