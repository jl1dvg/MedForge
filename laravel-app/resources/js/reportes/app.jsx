import { useState, useMemo, useEffect, useCallback } from 'react';
import { Cover, Section, ExecutiveMap, Toolbar, Footnote } from './lib.jsx';
import { CirugiasReport } from './cirugias-sections.jsx';
import { ImagenesReport } from './imagenes-sections.jsx';

const COPY = {
    cirugias: {
        title: 'Cómo rindió la unidad de Cirugías',
        lede: 'Lectura ejecutiva del quirófano en el período: de la solicitud al cobro. Empieza por el mapa financiero —dónde se gana, se bloquea o se pierde— y baja al detalle de producción, procedimientos, equipo y calidad.',
    },
    imagenes: {
        title: 'Cómo rindió la unidad de Imágenes',
        lede: 'Lectura ejecutiva de imagenología en el período: de la solicitud al cobro. Empieza por el mapa financiero —dónde se gana, se bloquea o se pierde— y baja al detalle económico, operación, SLA y mezcla de estudios.',
    },
};

const PERIODS = {
    mes:  { label: 'Mes',        months: 1 },
    trim: { label: 'Trimestre',  months: 3 },
    sem:  { label: 'Semestre',   months: 6 },
    ano:  { label: 'Año',        months: 12 },
};

function periodToDates(period) {
    const now = new Date();
    const y = now.getFullYear(), m = now.getMonth();
    const pad = (n) => String(n).padStart(2, '0');
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const lastDay = new Date(y, m + 1, 0);
    const months = PERIODS[period]?.months ?? 1;
    const firstDay = new Date(y, m - (months - 1), 1);
    return { start: fmt(firstDay), end: fmt(lastDay) };
}

function loadPref(key, def) {
    try { return localStorage.getItem('mf-rep2-' + key) || def; } catch { return def; }
}
function savePref(key, val) {
    try { localStorage.setItem('mf-rep2-' + key, val); } catch { /* ignore */ }
}

export default function App({ config }) {
    const endpoints = config.endpoints ?? {};
    const sedeOptions = config.sedeOptions ?? [];

    const [unit, setUnit]     = useState(() => loadPref('unit', 'cirugias'));
    const [period, setPeriod] = useState(() => loadPref('period', 'trim'));
    const [sede, setSede]     = useState(() => loadPref('sede', ''));
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => { savePref('unit', unit); savePref('period', period); savePref('sede', sede); }, [unit, period, sede]);

    const dates = useMemo(() => periodToDates(period), [period]);

    const fetchReport = useCallback(async () => {
        const url = endpoints[unit];
        if (!url) return;
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams({ start: dates.start, end: dates.end, sede });
            const res = await fetch(`${url}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setReport(data);
        } catch (e) {
            setError(e.message);
        } finally {
            setLoading(false);
        }
    }, [unit, dates.start, dates.end, sede, endpoints]);

    useEffect(() => { fetchReport(); }, [fetchReport]);

    useEffect(() => {
        if (report) {
            document.title = `Reporte ${report.unitLabel ?? unit} · ${report.period?.fromLabel ?? dates.start} → ${report.period?.toLabel ?? dates.end}`;
        }
    }, [report, unit, dates]);

    const copy = COPY[unit];

    return (
        <div className="rep-app" data-unit={unit}>
            <Toolbar
                unit={unit} setUnit={setUnit}
                period={period} periods={PERIODS} setPeriod={setPeriod}
                sede={sede} sedeOptions={sedeOptions} setSede={setSede}
                onExport={() => window.print()}
                loading={loading}
            />

            {loading && (
                <div style={{ display: 'flex', justifyContent: 'center', padding: '80px 0', color: 'var(--fg-mute)' }}>
                    <i className="mdi mdi-loading mdi-spin" style={{ fontSize: 28, marginRight: 10 }}></i>
                    Cargando reporte…
                </div>
            )}

            {error && !loading && (
                <div style={{ maxWidth: 800, margin: '60px auto', padding: '24px 32px', background: '#fff3f5', borderRadius: 12, border: '1px solid #ffc7ce', color: '#721c24' }}>
                    <b>No se pudo cargar el reporte</b><br />{error}
                    <button onClick={fetchReport} style={{ marginTop: 12, display: 'block', padding: '6px 16px', borderRadius: 6, border: '1px solid #721c24', background: 'transparent', cursor: 'pointer', color: '#721c24' }}>Reintentar</button>
                </div>
            )}

            {report && !loading && (
                <>
                    <div className="rep-print-head" style={{ alignItems: 'center', justifyContent: 'space-between', padding: '0 0 10px', marginBottom: 8, borderBottom: '1px solid #e4e6ef' }}>
                        <span style={{ font: '600 11px "IBM Plex Sans", sans-serif', color: '#5e6278' }}>
                            Reporte ejecutivo · {report.unitLabel} · {report.period?.fromLabel} → {report.period?.toLabel}
                        </span>
                    </div>

                    <main className="rep-doc">
                        <Cover r={report} title={copy.title} lede={copy.lede} />

                        <Section num="01" kicker="Mapa ejecutivo financiero"
                            title="El flujo conectado, de la solicitud al cobro"
                            lede="Bloque compartido por todas las unidades de negocio. Conecta cada KPI financiero con la etapa del flujo que lo origina, para distinguir facturado real, oportunidad estimada, pendiente de pago y pérdida.">
                            <ExecutiveMap r={report} />
                        </Section>

                        {unit === 'cirugias' ? <CirugiasReport r={report} /> : <ImagenesReport r={report} />}

                        <Footnote />
                    </main>
                </>
            )}
        </div>
    );
}
