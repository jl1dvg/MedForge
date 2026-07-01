import { Section, Kpi, BarsList, DonutLegend, Read, Recs, RecsCard, fmt } from './lib.jsx';
import { TrendArea, AreaSeries, ColumnChart, DonutChart } from './charts.jsx';

function ImgReconTable({ rows = [] }) {
    return (
        <table className="rep-table">
            <thead><tr><th>Categoría</th><th className="num">Facturadas</th><th className="num">Pendientes</th><th className="num">Estimado</th></tr></thead>
            <tbody>
                {rows.map((r, i) => (
                    <tr key={i}>
                        <td className="name">{r.cat}</td>
                        <td className="num">{fmt(r.fact ?? 0)}</td>
                        <td className="num">{fmt(r.pend ?? 0)}</td>
                        <td className="num">{(r.estimado ?? 0) > 0 ? '$' + fmt(Math.round(r.estimado)) : '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function ImgDoctorTable({ rows = [], total }) {
    const mx = Math.max(1, ...rows.map((r) => r.total ?? 0));
    return (
        <table className="rep-table">
            <thead><tr><th>#</th><th>Médico solicitante</th><th className="num">Solicitudes</th><th>Participación</th></tr></thead>
            <tbody>
                {rows.map((r, i) => {
                    const initials = (r.name ?? '').replace(/^Dr[a]?\.\s*/, '').split(' ').map((w) => w[0] ?? '').join('').slice(0, 2).toUpperCase();
                    return (
                        <tr key={i}>
                            <td style={{ color: 'var(--fg-mute)', fontWeight: 700 }}>{i + 1}</td>
                            <td className="name"><div style={{ display: 'flex', alignItems: 'center', gap: 10 }}><span className="rep-av" style={{ background: '#e6f9fc', color: '#0b5e6e' }}>{initials}</span>{r.name}</div></td>
                            <td className="num">{fmt(r.total ?? 0)}</td>
                            <td><div style={{ display: 'flex', alignItems: 'center', gap: 8 }}><span className="rep-mini-bar"><i style={{ width: ((r.total ?? 0) / mx * 100) + '%', background: '#0e9bb3' }}></i></span><span style={{ color: 'var(--fg-mute)', fontVariantNumeric: 'tabular-nums' }}>{total > 0 ? Math.round((r.total ?? 0) / total * 100) : 0}%</span></div></td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

export function ImagenesReport({ r }) {
    const m = r.metrics ?? {};
    return (
        <>
            <Section num="02" kicker="Rendimiento económico"
                title="Facturado, oportunidad y reconciliación por categoría"
                lede={`El facturado real del período es <b>$${fmt(Math.round(m.facturadoReal ?? 0))}</b>. Junto a la oportunidad estimada, la reconciliación por categoría muestra dónde se concentra el pendiente y qué bloquea su cobro.`}>
                <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
                    {(r.rendimientoEconomico ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-cash-multiple"></i>Rendimiento económico</h3></div>
                            <ColumnChart data={r.rendimientoEconomico ?? []} money height={236} />
                        </div>
                    )}
                    {(r.reconciliacion ?? []).length > 0 && (
                        <div className="rep-card rep-span2">
                            <div className="rep-card-head"><h3><i className="mdi mdi-scale-balance"></i>Reconciliación financiera por categoría</h3><span className="rep-card-note">Facturado vs pendiente</span></div>
                            <ImgReconTable rows={r.reconciliacion ?? []} />
                            {(m.pendientesSinTarifa ?? 0) > 0 && (
                                <p className="rep-kpi-sub" style={{ marginTop: 12 }}>Pendientes sin tarifa resoluble: <b>{fmt(m.pendientesSinTarifa)}</b> — requieren completar tarifa por código/categoría antes de facturar.</p>
                            )}
                        </div>
                    )}
                </div>
                <div className="rep-grid rep-grid--2">
                    {(r.backlogCategoria ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-layers-triple-outline"></i>Backlog por categoría</h3><span className="rep-card-note">Realizados sin facturar</span></div>
                            <BarsList items={r.backlogCategoria ?? []} valueKey="count" color="#0e9bb3" />
                        </div>
                    )}
                    {(r.trazabilidad ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-chart-donut"></i>Trazabilidad facturación</h3></div>
                            <div className="rep-grid rep-grid--2" style={{ alignItems: 'center', gap: 8 }}>
                                <DonutChart data={r.trazabilidad ?? []} centerLabel="realizados" height={176} />
                                <DonutLegend items={r.trazabilidad ?? []} />
                            </div>
                        </div>
                    )}
                </div>
                <div style={{ marginTop: 16 }}>
                    <Read>La oportunidad estimada{r.exec?.summary?.oportunidad ? ` de <b>${r.exec.summary.oportunidad}</b>` : ''} se desbloquea en dos frentes: resolver las tarifas faltantes y agendar las solicitudes que hoy no llegan a realizarse.</Read>
                </div>
            </Section>

            <Section num="03" kicker="Operación, agenda y SLA"
                title="Capacidad de agenda, cierre y oportunidad de informe"
                lede={`El cumplimiento al corte es del <b>${m.cumplimiento ?? 0}%</b>${m.sla48 != null ? ` y el SLA de informe ≤48h alcanza <b>${m.sla48}%</b>` : ''}. La serie diaria revela los picos de carga que conviene equilibrar entre sedes.`}>
                <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
                    {m.sla48 != null && <Kpi icon="mdi-timer-check-outline" label="SLA informe ≤48h" value={m.sla48} unit="%" sub="Estudios informados dentro de meta" accent="var(--success)" />}
                    {m.tatProm != null && <Kpi icon="mdi-timer-sand" label="TAT informe (prom.)" value={Math.round(m.tatProm)} unit="h" sub="Desde realización a informe" accent="var(--warning)" />}
                    {m.tatP90 != null && <Kpi icon="mdi-speedometer" label="TAT informe P90" value={Math.round(m.tatP90)} unit="h" sub="90% se informa antes" accent="var(--warning)" />}
                    {m.ticketProm != null && <Kpi icon="mdi-ticket-confirmation-outline" label="Ticket promedio" value={'$' + fmt(Math.round(m.ticketProm))} sub="Facturado por estudio" accent="#0e9bb3" />}
                </div>
                {(r.agendaVsCierre ?? []).length > 0 && (
                    <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
                        <div className="rep-card rep-span2">
                            <div className="rep-card-head"><h3><i className="mdi mdi-chart-areaspline"></i>Agenda vs cierre</h3><span className="rep-card-note">Agendadas vs realizadas</span></div>
                            <TrendArea data={r.agendaVsCierre ?? []} keys={['agendadas', 'realizadas']} names={['Agendadas', 'Realizadas']} height={234} />
                            <div className="rep-chart-legend"><span className="rep-leg"><b style={{ background: '#5156be' }}></b>Agendadas</span><span className="rep-leg line"><b style={{ background: '#05825f' }}></b>Realizadas</span></div>
                        </div>
                        {(r.traficoPorDia ?? []).length > 0 && (
                            <div className="rep-card">
                                <div className="rep-card-head"><h3><i className="mdi mdi-calendar-week"></i>Tráfico por día</h3></div>
                                <ColumnChart data={r.traficoPorDia ?? []} colors={['#0e9bb3']} height={234} />
                            </div>
                        )}
                    </div>
                )}
                {(r.serieDiaria ?? []).length > 0 && (
                    <div className="rep-card">
                        <div className="rep-card-head"><h3><i className="mdi mdi-pulse"></i>Serie diaria de estudios realizados</h3><span className="rep-card-note">Picos de carga operativa</span></div>
                        <AreaSeries data={r.serieDiaria ?? []} color="#0e9bb3" name="Realizados" height={210} />
                    </div>
                )}
            </Section>

            <Section num="04" kicker="Demanda y mezcla de estudios"
                title="Qué se pide, qué se realiza y quién lo origina"
                lede="El OCT y el campo visual concentran la demanda. Cruzar lo solicitado con lo realizado ayuda a dimensionar agenda y equipos por sede.">
                <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
                    {(r.topExamenesRealizados ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-check-decagram-outline"></i>Top exámenes realizados</h3></div>
                            <BarsList items={(r.topExamenesRealizados ?? []).slice(0, 7)} color="#0e9bb3" />
                        </div>
                    )}
                    {(r.topExamenesSolicitados ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-clipboard-text-outline"></i>Top exámenes solicitados</h3></div>
                            <BarsList items={(r.topExamenesSolicitados ?? []).slice(0, 7)} color="var(--info)" />
                        </div>
                    )}
                </div>
                <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
                    {(r.topMedicos ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-account-arrow-right-outline"></i>Top médicos solicitantes</h3></div>
                            <ImgDoctorTable rows={(r.topMedicos ?? []).slice(0, 8)} total={m.solicitudes ?? 0} />
                        </div>
                    )}
                    {(r.porConvenio ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-bank-outline"></i>Estudios por empresa de seguro</h3></div>
                            <BarsList items={(r.porConvenio ?? []).slice(0, 8)} color="var(--primary)" />
                        </div>
                    )}
                </div>
                <RecsCard>
                    <Recs items={[
                        (m.pendientesSinTarifa ?? 0) > 0 ? `Resolver las <b>${fmt(m.pendientesSinTarifa)}</b> tarifas faltantes es el desbloqueo de facturación más rápido y de menor esfuerzo.` : `Mantener la actualización de tarifas al día para no bloquear facturación.`,
                        `Recuperar agenda: las solicitudes sin agendar son la mayor fuga de conversión${r.exec?.kpis?.[2] ? `, con <b>${r.exec.kpis[2].value}</b> de pérdida estimada` : ''}.`,
                        m.sla48 != null ? `Sostener el SLA ≤48h en <b>${m.sla48}%</b> reasignando lectura en los picos que muestra la serie diaria.` : `Monitorear el SLA de informes para mantener la conversión de realización a facturación.`,
                        `Equilibrar la carga entre sedes según el tráfico por día para reducir ausentismo y reprogramaciones.`,
                    ]} />
                </RecsCard>
            </Section>
        </>
    );
}
