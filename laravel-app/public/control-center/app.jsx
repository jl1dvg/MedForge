/* MedForge Control Center — app shell, routing, topbar, sidebar, tweaks */

const CC_NAV = [
  { grp: "Plataforma", items: [
    { id: "overview",  icon: "mdi-view-dashboard-outline", label: "Overview" },
    { id: "clientes",  icon: "mdi-domain",                 label: "Clientes", pill: "5", pillMut: true },
    { id: "licencias", icon: "mdi-license",                label: "Licencias y Planes" },
  ]},
  { grp: "Operación", items: [
    { id: "estado",    icon: "mdi-toggle-switch-outline",  label: "Estado Operativo", pill: "2" },
    { id: "features",  icon: "mdi-flag-variant-outline",   label: "Feature Flags" },
    { id: "servicios", icon: "mdi-server-network",         label: "Servicios", pill: "3" },
  ]},
  { grp: "Entrega", items: [
    { id: "deploys",   icon: "mdi-rocket-launch-outline",  label: "Deploys y Versiones" },
    { id: "consumo",   icon: "mdi-chart-areaspline",       label: "Consumo" },
    { id: "auditoria", icon: "mdi-clipboard-text-clock-outline", label: "Auditoría" },
  ]},
];

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "dark",
  "density": "comfortable",
  "accent": "#7b80ff"
}/*EDITMODE-END*/;

function EnvSelector({ env, setEnv }) {
  const opts = [{ id: "prod", label: "Producción", cls: "prod" }, { id: "beta", label: "Beta", cls: "beta" }, { id: "exp", label: "Experimental", cls: "exp" }];
  return (
    <div className="cc-env" title="Ambiente activo">
      {opts.map(o => (
        <button key={o.id} className={`${o.cls} ${env === o.id ? "on " + o.cls : ""}`} onClick={() => setEnv(o.id)}>
          <span className="led"></span>{o.label}
        </button>
      ))}
    </div>
  );
}

function GlobalClientSelector({ selected, onPick }) {
  const [open, setOpen] = useState(false);
  const c = CC_CLIENTS.find(x => x.id === selected);
  return (
    <div className="cc-clientsel">
      <button onClick={() => setOpen(o => !o)}>
        {c ? <React.Fragment><span className="ava" style={{ background: c.color }}>{c.inicial}</span>{c.nombre}</React.Fragment>
           : <React.Fragment><span className="ava" style={{ background: "linear-gradient(135deg,#1ECCDD,#7C4DFF)" }}><i className="mdi mdi-earth" style={{ fontSize: 14 }}></i></span>Todos los clientes</React.Fragment>}
        <i className="mdi mdi-chevron-down chev"></i>
      </button>
      {open && (
        <div className="cc-menu" onMouseLeave={() => setOpen(false)}>
          <div className="head">Cliente global</div>
          <div className={`item ${!selected ? "on" : ""}`} onClick={() => { onPick(null); setOpen(false); }}>
            <span className="ava" style={{ background: "linear-gradient(135deg,#1ECCDD,#7C4DFF)" }}><i className="mdi mdi-earth" style={{ fontSize: 15 }}></i></span>
            <div><div className="nm">Todos los clientes</div><div className="dm">vista consolidada</div></div>
          </div>
          {CC_CLIENTS.map(x => (
            <div key={x.id} className={`item ${selected === x.id ? "on" : ""}`} onClick={() => { onPick(x.id); setOpen(false); }}>
              <span className="ava" style={{ background: x.color }}>{x.inicial}</span>
              <div><div className="nm">{x.nombre}</div><div className="dm">{x.dominio}</div></div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function App() {
  const [t, setTweak] = (typeof useTweaks === "function") ? useTweaks(TWEAK_DEFAULTS) : [TWEAK_DEFAULTS, () => {}];
  const [route, setRoute] = useState("overview");
  const [detailId, setDetailId] = useState(null);
  const [selectedClient, setSelectedClient] = useState(null); // global selector (null = all)
  const [env, setEnv] = useState("prod");
  const [collapsed, setCollapsed] = useState(false);

  // apply theme + density + accent
  useEffect(() => {
    document.documentElement.setAttribute("data-cc-theme", t.theme);
    document.documentElement.setAttribute("data-cc-density", t.density === "compact" ? "compact" : "comfortable");
    document.documentElement.setAttribute("data-cc-collapsed", String(collapsed));
    if (t.theme === "dark") document.documentElement.style.setProperty("--cc-accent", t.accent);
    else document.documentElement.style.removeProperty("--cc-accent");
  }, [t.theme, t.density, t.accent, collapsed]);

  const openClient = (id) => { setDetailId(id); window.scrollTo(0, 0); };
  const go = (r) => { setRoute(r); setDetailId(null); };

  // estado operativo sidebar entry => clientes filtered to operational, or detail. We route it to a dedicated view.
  let content;
  if (detailId) {
    content = <ClientDetail clientId={detailId} onBack={() => setDetailId(null)} onNav={go} />;
  } else {
    content = {
      overview:  <ScreenOverview onOpenClient={openClient} onNav={go} env={env} />,
      clientes:  <ScreenClientes onOpenClient={openClient} />,
      licencias: <ScreenLicencias />,
      estado:    <ScreenEstadoGlobal onOpenClient={openClient} />,
      features:  <ScreenFeatures selectedClient={selectedClient || "cive"} onPickClient={setSelectedClient} />,
      servicios: <ScreenServicios selectedClient={selectedClient || "cive"} onPickClient={setSelectedClient} />,
      deploys:   <ScreenDeploys />,
      consumo:   <ScreenConsumo />,
      auditoria: <ScreenAuditoria />,
    }[route];
  }

  return (
    <div className="cc-app">
      <div className="cc-brand">
        <div className="mark"><i className="mdi mdi-lightning-bolt"></i></div>
        <div className="txt"><b>MedForge</b><span>Control Center</span></div>
      </div>

      <header className="cc-top">
        <button className="cc-iconbtn" onClick={() => setCollapsed(v => !v)} title="Colapsar menú"><i className="mdi mdi-menu"></i></button>
        <EnvSelector env={env} setEnv={setEnv} />
        <GlobalClientSelector selected={selectedClient} onPick={(id) => { setSelectedClient(id); if (id && (route === "features" || route === "servicios")) {} }} />
        <div className="cc-search">
          <i className="mdi mdi-magnify"></i>
          <input placeholder="Buscar cliente, dominio, versión…" />
          <kbd>⌘K</kbd>
        </div>
        <div style={{ flex: 1 }}></div>
        <button className="cc-iconbtn" title="Cambiar tema" onClick={() => setTweak("theme", t.theme === "dark" ? "light" : "dark")}>
          <i className={`mdi ${t.theme === "dark" ? "mdi-weather-night" : "mdi-white-balance-sunny"}`}></i>
        </button>
        <button className="cc-iconbtn" title="Notificaciones"><i className="mdi mdi-bell-outline"></i><span className="dot"></span></button>
        <div className="cc-userchip" title="Equipo MedForge">
          <div style={{ textAlign: "right" }}><div className="nm">Carlos Andrade</div><div className="rl">Operaciones</div></div>
          <div className="ava">CA</div>
        </div>
      </header>

      <nav className="cc-nav">
        {CC_NAV.map(sec => (
          <React.Fragment key={sec.grp}>
            <div className="grp">{sec.grp}</div>
            {sec.items.map(it => (
              <a key={it.id} className={(route === it.id && !detailId) ? "on" : ""} onClick={() => go(it.id)} title={it.label}>
                <i className={`mdi ${it.icon}`}></i>
                <span className="lbl">{it.label}</span>
                {it.pill && <span className={`pill ${it.pillMut ? "mut" : ""}`}>{it.pill}</span>}
              </a>
            ))}
          </React.Fragment>
        ))}
        <div className="spacer"></div>
        <div className="railcard">
          <b>Estado de la plataforma</b>
          <p>4 de 5 clientes operativos · 1 incidencia de backup.</p>
          <button className="cc-btn primary sm" style={{ width: "100%", justifyContent: "center" }} onClick={() => go("servicios")}>Ver estado</button>
        </div>
      </nav>

      <main className="cc-main">{content}</main>

      {typeof TweaksPanel === "function" && (
        <TweaksPanel title="Tweaks">
          <TweakSection label="Apariencia" />
          <TweakRadio label="Tema" value={t.theme} options={[{ value: "dark", label: "Dark" }, { value: "light", label: "Light" }]} onChange={(v) => setTweak("theme", v)} />
          <TweakRadio label="Densidad" value={t.density} options={[{ value: "comfortable", label: "Cómoda" }, { value: "compact", label: "Compacta" }]} onChange={(v) => setTweak("density", v)} />
          <TweakSection label="Marca" />
          <TweakColor label="Acento (dark)" value={t.accent} options={["#7b80ff", "#28d6e6", "#18c08a", "#f5b53d", "#ff5575"]} onChange={(v) => setTweak("accent", v)} />
        </TweaksPanel>
      )}
    </div>
  );
}

/* Estado Operativo global view — list of clients with their operational state + quick access */
function ScreenEstadoGlobal({ onOpenClient }) {
  return (
    <div className="cc-page fade-in">
      <PageHead title="Estado Operativo" sub="Modo de operación de cada cliente. Cambia a Producción, Mantenimiento, Solo lectura o Suspendido desde la ficha del cliente." />
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        {Object.values(CC_STATES).map(s => {
          const n = CC_CLIENTS.filter(c => c.estado === s.key).length;
          return (
            <div key={s.key} className="cc-kpi fade-in">
              <div className="top"><div className="lbl">{s.label}</div><div className="tile" style={{ background: `var(--st-${s.cls}-bg)`, color: `var(--st-${s.cls})` }}><i className={`mdi ${s.icon}`}></i></div></div>
              <div className="val">{n}</div>
              <div className="foot"><span className="muted">{n === 1 ? "cliente" : "clientes"}</span></div>
            </div>
          );
        })}
      </div>
      <Card flush>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Empresa</th><th>Estado operativo</th><th>Impacto</th><th>Usuarios</th><th></th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => {
                const s = CC_STATES[c.estado];
                return (
                  <tr key={c.id} className="clickable" onClick={() => onOpenClient(c.id)}>
                    <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                    <td><StateBadge estado={c.estado} /></td>
                    <td className="muted" style={{ fontSize: 12.5, maxWidth: 340 }}>{s.impact.split(".")[0]}.</td>
                    <td><span className="cc-mono">{c.usuarios}</span></td>
                    <td style={{ textAlign: "right" }}><button className="cc-btn line sm" onClick={(e) => { e.stopPropagation(); onOpenClient(c.id); }}><i className="mdi mdi-swap-horizontal"></i>Gestionar</button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, { ScreenEstadoGlobal });
ReactDOM.createRoot(document.getElementById("root")).render(<App />);
