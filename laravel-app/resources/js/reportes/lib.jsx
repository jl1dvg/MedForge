/* Shared components: Cover, Section, ExecutiveMap, Toolbar, KPIs, BarsList, etc. */
export function fmt(n) { return Number(n).toLocaleString('es-EC'); }

export function SynthCell({ label, value, unit, delta, deltaSuffix = '%', invert = false }) {
    let cls = 'flat', icon = 'mdi-minus', shown = 0;
    if (delta != null && delta !== 0) {
        const positive = delta > 0;
        const good = invert ? !positive : positive;
        cls = good ? 'up' : 'down';
        icon = positive ? 'mdi-arrow-up-thin' : 'mdi-arrow-down-thin';
        shown = Math.abs(delta);
    }
    return (
        <div className="rep-synth-cell">
            <div className="rep-synth-l">{label}</div>
            <div className="rep-synth-v">{value}{unit ? <small>{unit}</small> : null}</div>
            <div className={`rep-synth-d ${cls}`}><i className={`mdi ${icon}`}></i>{shown}{deltaSuffix} vs. período anterior</div>
        </div>
    );
}

export function Toolbar({ unit, setUnit, period, periods, setPeriod, sede, sedeOptions, setSede, onExport, loading }) {
    const allSedes = [{ value: '', label: 'Todas' }, ...sedeOptions.map((s) => ({ value: s.value ?? s.id ?? s, label: s.label }))];
    return (
        <div className="rep-toolbar">
            <div className="rep-toolbar-inner">
                <div className="rep-tb-brand">
                    <img src="/assets/logo-on-light.png" alt="MedForge" />
                    <span className="rep-tb-div"></span>
                    <span className="rep-tb-tag">Reporte ejecutivo<small>Unidades de negocio</small></span>
                </div>
                <div className="rep-filters">
                    <span className="rep-flabel">Unidad</span>
                    <div className="rep-seg rep-seg--unit">
                        <button className={unit === 'cirugias' ? 'is-active' : ''} onClick={() => setUnit('cirugias')}><i className="mdi mdi-hospital-box-outline"></i>Cirugías</button>
                        <button className={unit === 'imagenes' ? 'is-active' : ''} onClick={() => setUnit('imagenes')}><i className="mdi mdi-radiology-box-outline"></i>Imágenes</button>
                    </div>
                    <span className="rep-flabel">Período</span>
                    <div className="rep-seg">
                        {Object.keys(periods).map((k) => (
                            <button key={k} className={period === k ? 'is-active' : ''} onClick={() => setPeriod(k)}>{periods[k].label}</button>
                        ))}
                    </div>
                    {allSedes.length > 1 && <>
                        <span className="rep-flabel">Sede</span>
                        <div className="rep-seg rep-seg--solid">
                            {allSedes.map((o) => (
                                <button key={o.value} className={sede === o.value ? 'is-active' : ''} onClick={() => setSede(o.value)}>{o.label}</button>
                            ))}
                        </div>
                    </>}
                </div>
                <button className="rep-btn rep-btn--primary" onClick={onExport} disabled={loading}>
                    <i className="mdi mdi-file-pdf-box"></i>Exportar PDF
                </button>
            </div>
        </div>
    );
}

export function Cover({ r, title, lede }) {
    return (
        <header className="rep-cover" data-unit={r.unit}>
            <div className="rep-cover-eyebrow">
                <i className={`mdi ${r.unitIcon}`}></i>Reporte ejecutivo
                <span className="rep-unit-chip"><i className={`mdi ${r.unitIcon}`}></i>{r.unitLabel}</span>
            </div>
            <h1>{title}</h1>
            <p className="rep-cover-lede">{lede}</p>
            <div className="rep-cover-meta">
                <div className="m"><div className="ml">Período</div><div className="mv"><i className="mdi mdi-calendar-range"></i>{r.period?.fromLabel} → {r.period?.toLabel}</div></div>
                <div className="m"><div className="ml">Sede</div><div className="mv"><i className="mdi mdi-map-marker"></i>{r.sede?.label}</div></div>
                <div className="m"><div className="ml">Unidad</div><div className="mv"><i className={`mdi ${r.unitIcon}`}></i>{r.unitLabel}</div></div>
                <div className="m"><div className="ml">Generado</div><div className="mv"><i className="mdi mdi-clock-outline"></i>{r.generatedAt}</div></div>
            </div>
            {r.synth?.length > 0 && (
                <div className="rep-synth">
                    {r.synth.map((s, i) => <SynthCell key={i} {...s} />)}
                </div>
            )}
        </header>
    );
}

export function Section({ num, kicker, title, lede, children }) {
    return (
        <section className="rep-section">
            <div className="rep-sec-head">
                <div className="rep-sec-num">{num}</div>
                <div className="rep-sec-headmain">
                    <div className="rep-sec-kicker">{kicker}</div>
                    <h2>{title}</h2>
                    {lede ? <p className="rep-sec-lede" dangerouslySetInnerHTML={{ __html: lede }}></p> : null}
                </div>
            </div>
            {children}
        </section>
    );
}

export function ExecutiveMap({ r }) {
    const e = r.exec ?? {};
    const ledMoney = (tone) => (tone === 'warn' ? 'is-warn' : tone === 'danger' ? 'is-danger' : '');
    return (
        <section className="rep-execmap" id="mapa-ejecutivo">
            <div className="rep-execmap-head">
                <div>
                    <span className="rep-execmap-kicker"><i className="mdi mdi-map-marker-path"></i>Mapa ejecutivo financiero</span>
                    <h2>De la solicitud al cobro: dónde se gana, se bloquea o se pierde</h2>
                    <p>Cada indicador está conectado con una etapa del flujo para diferenciar facturado real, oportunidad estimada, pendiente de pago y pérdida.</p>
                </div>
                <div className="rep-execmap-pills">
                    <span>{r.unitLabel}</span>
                    <span>{r.period?.label}</span>
                    <span>{r.sede?.label}</span>
                </div>
            </div>

            {e.kpis?.length > 0 && (
                <div className="rep-execkpis">
                    {e.kpis.map((k, i) => (
                        <div key={i} className={`rep-execkpi is-${k.cls}`}>
                            <span className="rep-execkpi-src"><i className="mdi mdi-checkbox-blank-circle"></i>{k.source}</span>
                            <div className="rep-execkpi-label">{k.label}</div>
                            <div className="rep-execkpi-val">{k.value}</div>
                            <div className="rep-execkpi-hint">{k.hint}</div>
                        </div>
                    ))}
                </div>
            )}

            <div className="rep-execmap-body">
                <div className="rep-flowwrap">
                    <div className="h">
                        <h3><i className="mdi mdi-transit-connection-variant"></i>Flujo conectado</h3>
                        <span className="note">Las fugas bajo cada etapa explican por qué el dinero no llega a facturación / cobro.</span>
                    </div>
                    <div className="rep-flow">
                        {(e.flow ?? []).map((stage, i) => (
                            <span key={stage.key}>
                                <div className={`rep-flow-stage is-${stage.cls}`}>
                                    <span className="st-label">{stage.label}</span>
                                    <span className="st-val">{fmt(stage.value)}</span>
                                    <span className="st-ctx">{stage.context}</span>
                                    {stage.leak && (stage.leak.count > 0 || stage.leak.amount > 0) ? (
                                        <div className="rep-flow-leak">
                                            <b><i className="mdi mdi-arrow-down-thin"></i>{stage.leak.label}</b>
                                            <em>{fmt(stage.leak.count)}{stage.leak.amount > 0 ? ' · $' + fmt(Math.round(stage.leak.amount)) : ''}</em>
                                        </div>
                                    ) : null}
                                </div>
                                {i < (e.flow?.length ?? 0) - 1 ? (
                                    <div className="rep-flow-link" aria-hidden="true">
                                        <span className="pct">{e.links?.[i]?.pct ?? ''}%</span>
                                        <i className="mdi mdi-arrow-right-thin"></i>
                                        <span className="pctlabel">conv.</span>
                                    </div>
                                ) : null}
                            </span>
                        ))}
                    </div>
                    {e.summary && (
                        <div className="rep-flow-summary">
                            <div><span className="s-l"><i className="mdi mdi-cash-multiple"></i>Oportunidad estimada</span><span className="s-v">{e.summary.oportunidad}</span></div>
                            <div><span className="s-l"><i className="mdi mdi-progress-clock"></i>Arrastre al corte</span><span className="s-v" style={{ fontSize: 13, lineHeight: 1.3 }}>{e.summary.arrastre}</span></div>
                            <div><span className="s-l"><i className="mdi mdi-timer-outline"></i>Informes / SLA</span><span className="s-v" style={{ fontSize: 13, lineHeight: 1.3 }}>{e.summary.sla}</span></div>
                        </div>
                    )}
                </div>

                <aside className="rep-actions">
                    <div className="h"><h3><i className="mdi mdi-flag-outline"></i>Acciones prioritarias</h3></div>
                    <div className="rep-action-list">
                        {(e.actions ?? []).map((a, i) => (
                            <a key={i} className={`rep-action sev-${a.severity}`} href="#mapa-ejecutivo">
                                <span className="dot"></span>
                                <span>
                                    <span className="a-title">{a.title}</span>
                                    <span className="a-meta"><b>{a.metric}</b> · {a.owner}</span>
                                    <span className="a-do">{a.action}</span>
                                </span>
                            </a>
                        ))}
                    </div>
                    {e.ledger?.length > 0 && (
                        <div className="rep-ledger">
                            {e.ledger.map((l, i) => (
                                <div key={i} className={ledMoney(l.tone)}>
                                    <span className="l-l">{l.label}</span>
                                    <span className="l-v">{l.value}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </aside>
            </div>
        </section>
    );
}

export function Kpi({ icon, label, value, unit, sub, accent }) {
    return (
        <div className="rep-kpi" style={accent ? { '--kpi-accent': accent } : undefined}>
            <div className="rep-kpi-top">
                {icon ? <span className="rep-kpi-ic"><i className={`mdi ${icon}`}></i></span> : null}
                <span className="rep-kpi-label">{label}</span>
            </div>
            <div className="rep-kpi-valrow">
                <span className="rep-kpi-value">{value}{unit ? <small>{unit}</small> : null}</span>
            </div>
            {sub ? <div className="rep-kpi-sub" dangerouslySetInnerHTML={{ __html: sub }}></div> : null}
        </div>
    );
}

export function BarsList({ items = [], valueKey = 'total', labelKey = 'label', color = 'var(--primary)', format, max, suffix = '' }) {
    const mx = max || Math.max(1, ...items.map((d) => d[valueKey] ?? 0));
    const f = format || ((v) => fmt(v) + suffix);
    return (
        <div className="rep-bars">
            {items.map((d, i) => (
                <div key={i}>
                    <div className="rep-bar-top">
                        <span className="rep-bar-name"><span className="rep-bar-txt">{d[labelKey] ?? d.name}</span></span>
                        <span className="rep-bar-meta"><strong>{f(d[valueKey] ?? 0)}</strong></span>
                    </div>
                    <div className="rep-bar-track"><div className="rep-bar-fill" style={{ width: ((d[valueKey] ?? 0) / mx * 100) + '%', background: color }}></div></div>
                </div>
            ))}
        </div>
    );
}

export function DonutLegend({ items = [], valueKey = 'total' }) {
    const total = items.reduce((a, d) => a + (d[valueKey] ?? 0), 0);
    return (
        <div className="rep-chart-legend" style={{ flexDirection: 'column', gap: 9, marginTop: 14 }}>
            {items.map((d, i) => (
                <div key={i} className="rep-leg" style={{ width: '100%' }}>
                    <b style={{ background: d.color }}></b>
                    <span style={{ flex: 1 }}>{d.label}</span>
                    <strong style={{ color: 'var(--fg-1)', fontVariantNumeric: 'tabular-nums' }}>{fmt(d[valueKey] ?? 0)}</strong>
                    <span style={{ color: 'var(--fg-mute)', minWidth: 42, textAlign: 'right' }}>{total > 0 ? Math.round((d[valueKey] ?? 0) / total * 100) : 0}%</span>
                </div>
            ))}
        </div>
    );
}

export function Read({ children }) {
    return <div className="rep-read"><i className="mdi mdi-lightbulb-on-outline lead"></i><p>{children}</p></div>;
}

export function Insight({ accent, title, children }) {
    return (
        <div className="rep-insight" style={accent ? { '--ins-accent': accent } : undefined}>
            <div className="rep-insight-h"><span className="dot"></span><h4>{title}</h4></div>
            <p dangerouslySetInnerHTML={{ __html: children }}></p>
        </div>
    );
}

export function Recs({ items = [] }) {
    return (
        <div className="rep-recs">
            {items.map((t, i) => <div key={i} className="rep-rec"><span className="rep-rec-num">{i + 1}</span><p dangerouslySetInnerHTML={{ __html: t }}></p></div>)}
        </div>
    );
}

export function RecsCard({ children }) {
    return (
        <div className="rep-card" style={{ background: 'var(--bg-soft)' }}>
            <div className="rep-card-head"><h3><i className="mdi mdi-clipboard-check-outline"></i>Recomendaciones del período</h3></div>
            {children}
        </div>
    );
}

export function Footnote() {
    return (
        <footer className="rep-footnote">
            <span className="fn-legend"><span className="fn-chip">Reporte</span>Lectura ejecutiva — recalcula con los filtros de unidad, período y sede.</span>
            <span className="fn-brand">MedForge by Consulmed · Generado automáticamente</span>
        </footer>
    );
}
