import { Section, Kpi, BarsList, DonutLegend, Read, Recs, RecsCard, fmt } from './lib.jsx';
import { TrendArea, DonutChart } from './charts.jsx';

function CirSurgeonTable({ rows = [], total }) {
    const mx = Math.max(1, ...rows.map((r) => r.realizadas ?? 0));
    return (
        <table className="rep-table">
            <thead><tr><th>#</th><th>Cirujano</th><th className="num">Realizadas</th><th>Participación</th></tr></thead>
            <tbody>
                {rows.map((r, i) => {
                    const initials = (r.name ?? '').replace(/^Dr[a]?\.\s*/, '').split(' ').map((w) => w[0] ?? '').join('').slice(0, 2).toUpperCase();
                    return (
                        <tr key={i}>
                            <td style={{ color: 'var(--fg-mute)', fontWeight: 700 }}>{i + 1}</td>
                            <td className="name"><div style={{ display: 'flex', alignItems: 'center', gap: 10 }}><span className="rep-av">{initials}</span>{r.name}</div></td>
                            <td className="num">{fmt(r.realizadas ?? 0)}</td>
                            <td><div style={{ display: 'flex', alignItems: 'center', gap: 8 }}><span className="rep-mini-bar"><i style={{ width: ((r.realizadas ?? 0) / mx * 100) + '%', background: 'var(--primary)' }}></i></span><span style={{ color: 'var(--fg-mute)', fontVariantNumeric: 'tabular-nums' }}>{total > 0 ? Math.round((r.realizadas ?? 0) / total * 100) : 0}%</span></div></td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

export function CirugiasReport({ r }) {
    const m = r.metrics ?? {};
    return (
        <>
            <Section num="02" kicker="Producción quirúrgica"
                title="Volumen realizado y trazabilidad de facturación"
                lede={`En el período se realizaron <b>${fmt(m.realizadas ?? 0)} cirugías</b> de ${fmt(m.programadas ?? m.solicitudes ?? 0)} programadas. La curva separa lo realizado de lo efectivamente facturado: la brecha entre ambas líneas es backlog de billing, no producción perdida.`}>
                <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
                    <div className="rep-card rep-span2">
                        <div className="rep-card-head"><h3><i className="mdi mdi-chart-areaspline"></i>Producción por mes</h3><span className="rep-card-note">Realizadas vs facturadas</span></div>
                        <TrendArea data={r.produccionMensual ?? []} keys={['realizadas', 'facturadas']} names={['Realizadas', 'Facturadas']} height={246} />
                        <div className="rep-chart-legend"><span className="rep-leg"><b style={{ background: '#5156be' }}></b>Realizadas</span><span className="rep-leg line"><b style={{ background: '#05825f' }}></b>Facturadas</span></div>
                    </div>
                    <div className="rep-card">
                        <div className="rep-card-head"><h3><i className="mdi mdi-chart-donut"></i>Trazabilidad</h3></div>
                        <DonutChart data={r.trazabilidad ?? []} centerLabel="realizadas" height={188} />
                        <DonutLegend items={r.trazabilidad ?? []} />
                    </div>
                </div>
                <Read>El <b>{m.cumplimiento ?? 0}% de cumplimiento al corte</b> es la métrica clave; el frente de valor está en cerrar los protocolos operatorios pendientes y emitir el billing de las realizadas.</Read>
            </Section>

            <Section num="03" kicker="Mezcla quirúrgica"
                title="Procedimientos, ingreso y equipo quirúrgico"
                lede="La facoemulsificación concentra el volumen, pero los procedimientos de retina y glaucoma aportan un ingreso por caso muy superior. Vale leer volumen e ingreso en paralelo.">
                <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
                    <div className="rep-card">
                        <div className="rep-card-head"><h3><i className="mdi mdi-format-list-numbered"></i>Top procedimientos</h3><span className="rep-card-note">Por volumen</span></div>
                        <BarsList items={(r.topProcedimientos ?? []).slice(0, 7)} color="var(--primary)" />
                    </div>
                    <div className="rep-card">
                        <div className="rep-card-head"><h3><i className="mdi mdi-account-arrow-right-outline"></i>Top doctores solicitantes</h3><span className="rep-card-note">Origen de la demanda</span></div>
                        <BarsList items={(r.topSolicitantes ?? []).slice(0, 7)} labelKey="name" color="var(--info)" />
                    </div>
                </div>
                <div className="rep-card">
                    <div className="rep-card-head"><h3><i className="mdi mdi-doctor"></i>Top cirujanos</h3><span className="rep-card-note">Realizadas en el período</span></div>
                    <CirSurgeonTable rows={(r.topCirujanos ?? []).slice(0, 8)} total={m.realizadas ?? 0} />
                </div>
            </Section>

            <Section num="04" kicker="Pagadores y calidad"
                title="Mezcla de financiadores e indicadores de calidad"
                lede="El sector público (IESS y afines) domina la mezcla; conviene vigilar su impacto en cartera. Los indicadores de calidad cierran la lectura clínica del período.">
                <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
                    {m.tatProm != null && <Kpi icon="mdi-timer-sand" label="TAT protocolo (prom.)" value={Math.round(m.tatProm)} unit="h" sub={`Mediana <b>${Math.round(m.tatMed ?? m.tatProm)}h</b> · muestra ${fmt(m.tatMuestra ?? 0)}`} accent="var(--warning)" />}
                    {m.tatP90 != null && <Kpi icon="mdi-speedometer" label="TAT protocolo P90" value={Math.round(m.tatP90)} unit="h" sub="90% se cierra antes de este tiempo" accent="var(--warning)" />}
                    {m.duracionProm != null && <Kpi icon="mdi-clock-fast" label="Duración promedio" value={m.duracionProm} unit=" min" sub="Tiempo de quirófano por caso" accent="var(--primary)" />}
                    {m.reingreso != null && <Kpi icon="mdi-restore-alert" label="Reingreso mismo dx" value={m.reingreso} sub={`${m.realizadas ?? 0} realizadas en período`} accent="var(--cat-cirugia)" />}
                </div>
                <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
                    <div className="rep-card">
                        <div className="rep-card-head"><h3><i className="mdi mdi-bank-outline"></i>Cirugías por empresa de seguro</h3><span className="rep-card-note">Volumen realizado</span></div>
                        <BarsList items={(r.porConvenio ?? []).slice(0, 8)} color="var(--primary)" />
                    </div>
                    {(r.mixCategoria ?? []).length > 0 && (
                        <div className="rep-card">
                            <div className="rep-card-head"><h3><i className="mdi mdi-chart-pie"></i>Mezcla por categoría</h3></div>
                            <DonutChart data={r.mixCategoria ?? []} valueKey="count" centerLabel="realizadas" height={188} />
                            <DonutLegend items={r.mixCategoria ?? []} valueKey="count" />
                        </div>
                    )}
                </div>
                <RecsCard>
                    <Recs items={[
                        `Cerrar los protocolos operatorios pendientes para destrabar facturación${m.tatP90 ? ` y reducir el TAT P90 de ${Math.round(m.tatP90)}h` : ''}.`,
                        `Emitir el billing del backlog realizado: recupera la mayor parte de la oportunidad estimada${r.exec?.summary?.oportunidad ? ` de <b>${r.exec.summary.oportunidad}</b>` : ''}.`,
                        `Vigilar la concentración del sector público en cartera para anticipar el pendiente de pago${m.pendientePagoN ? ` (${fmt(m.pendientePagoN)} registros)` : ''}.`,
                    ]} />
                </RecsCard>
            </Section>
        </>
    );
}
