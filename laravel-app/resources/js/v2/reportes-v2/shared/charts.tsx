import React, { useRef, useState, useLayoutEffect } from 'react';
import {
  ComposedChart, AreaChart, Area, Line,
  BarChart, Bar, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip,
} from 'recharts';

export const PALETTE = ['#5156be', '#3596f7', '#05825f', '#d59623', '#0e9bb3', '#d34b5b', '#7C4DFF', '#7e8299'];

const AXIS_TICK = { fill: '#7e8299', fontSize: 11, fontFamily: '"IBM Plex Sans", sans-serif' };
const AXIS_LINE = { stroke: '#e4e6ef' };

interface MeasuredProps {
  height: number;
  className?: string;
  children: (width: number) => React.ReactNode;
}

export function Measured({ height, className = 'rep-chart', children }: MeasuredProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [w, setW] = useState(0);

  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const measure = () => {
      const cw = Math.round(el.clientWidth || el.getBoundingClientRect().width || 0);
      if (cw > 0) setW(prev => Math.abs(prev - cw) > 1 ? cw : prev);
    };
    measure();
    const raf = requestAnimationFrame(measure);
    let ro: ResizeObserver | undefined;
    if (typeof ResizeObserver !== 'undefined') {
      ro = new ResizeObserver(measure);
      ro.observe(el);
    }
    window.addEventListener('resize', measure);
    return () => { cancelAnimationFrame(raf); ro?.disconnect(); window.removeEventListener('resize', measure); };
  }, []);

  return (
    <div className={className} ref={ref} style={{ height, width: '100%' }}>
      {w > 0 ? children(w) : null}
    </div>
  );
}

interface TooltipPayloadItem {
  name: string;
  value: number;
  color?: string;
  fill?: string;
}

interface RepTooltipProps {
  active?: boolean;
  payload?: TooltipPayloadItem[];
  label?: string;
  unit?: string;
  prefix?: string;
  title?: string;
}

export function RepTooltip({ active, payload, label, unit = '', prefix = '', title }: RepTooltipProps) {
  if (!active || !payload?.length) return null;
  return (
    <div style={{ background: '#fff', border: '1px solid #e4e6ef', borderRadius: 8, boxShadow: '0 4px 12px rgba(16,24,40,.12)', padding: '9px 12px', font: '12px "IBM Plex Sans", sans-serif' }}>
      <div style={{ fontWeight: 700, color: '#172b4c', marginBottom: 5 }}>{title ?? label}</div>
      {(payload as TooltipPayloadItem[]).map((p, i) => (
        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 7, color: '#3f4254', marginTop: 2 }}>
          <span style={{ width: 9, height: 9, borderRadius: 2, background: p.color ?? p.fill, display: 'inline-block' }} />
          <span style={{ flex: 1 }}>{p.name}</span>
          <strong style={{ color: '#172b4c', fontVariantNumeric: 'tabular-nums' }}>{prefix}{Number(p.value).toLocaleString('es-EC')}{unit}</strong>
        </div>
      ))}
    </div>
  );
}

interface TrendAreaProps {
  data: Record<string, string | number>[];
  keys: [string, string];
  names: [string, string];
  height?: number;
}

export function TrendArea({ data, keys, names, height = 250 }: TrendAreaProps) {
  const [k1, k2] = keys;
  return (
    <Measured height={height}>
      {w => (
        <ComposedChart width={w} height={height} data={data} margin={{ top: 10, right: 6, left: -14, bottom: 0 }}>
          <defs>
            <linearGradient id="gA" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#5156be" stopOpacity={0.24} />
              <stop offset="100%" stopColor="#5156be" stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey="label" axisLine={AXIS_LINE} tickLine={false} tick={AXIS_TICK} interval="preserveStartEnd" minTickGap={14} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS_TICK} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} />
          <Area type="monotone" dataKey={k1} name={names[0]} stroke="#5156be" strokeWidth={2.4} fill="url(#gA)" isAnimationActive={false} />
          <Line type="monotone" dataKey={k2} name={names[1]} stroke="#05825f" strokeWidth={2.4} dot={false} isAnimationActive={false} />
        </ComposedChart>
      )}
    </Measured>
  );
}

interface AreaSeriesProps {
  data: Record<string, string | number>[];
  dataKey?: string;
  name?: string;
  color?: string;
  height?: number;
}

export function AreaSeries({ data, dataKey = 'value', name = 'Valor', color = '#3596f7', height = 220 }: AreaSeriesProps) {
  return (
    <Measured height={height}>
      {w => (
        <AreaChart width={w} height={height} data={data} margin={{ top: 10, right: 6, left: -16, bottom: 0 }}>
          <defs>
            <linearGradient id="gS" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.22} />
              <stop offset="100%" stopColor={color} stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey="label" axisLine={AXIS_LINE} tickLine={false} tick={AXIS_TICK} interval="preserveStartEnd" minTickGap={20} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS_TICK} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} />
          <Area type="monotone" dataKey={dataKey} name={name} stroke={color} strokeWidth={2.2} fill="url(#gS)" isAnimationActive={false} />
        </AreaChart>
      )}
    </Measured>
  );
}

interface ColumnChartDataItem {
  label?: string;
  value?: number;
  color?: string;
  [key: string]: unknown;
}

interface ColumnChartProps {
  data: ColumnChartDataItem[];
  dataKey?: string;
  labelKey?: string;
  name?: string;
  height?: number;
  colors?: string[];
  money?: boolean;
}

export function ColumnChart({ data, dataKey = 'value', labelKey = 'label', name = 'Valor', height = 230, colors, money = false }: ColumnChartProps) {
  return (
    <Measured height={height}>
      {w => (
        <BarChart width={w} height={height} data={data} margin={{ top: 8, right: 6, left: money ? -4 : -16, bottom: 0 }}>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey={labelKey} axisLine={AXIS_LINE} tickLine={false} tick={AXIS_TICK} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS_TICK} width={money ? 52 : 42} allowDecimals={false}
            tickFormatter={money ? (v: number) => '$' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) : undefined} />
          <Tooltip content={<RepTooltip prefix={money ? '$' : ''} />} cursor={{ fill: 'rgba(81,86,190,.05)' }} />
          <Bar dataKey={dataKey} name={name} radius={[6, 6, 0, 0] as [number, number, number, number]} maxBarSize={64} isAnimationActive={false}>
            {data.map((d, i) => (
              <Cell key={i} fill={(d.color as string) ?? colors?.[i % (colors?.length ?? 1)] ?? '#5156be'} />
            ))}
          </Bar>
        </BarChart>
      )}
    </Measured>
  );
}

interface DonutDataItem {
  label?: string;
  name?: string;
  total?: number;
  count?: number;
  color?: string;
  [key: string]: unknown;
}

interface DonutChartProps {
  data: DonutDataItem[];
  nameKey?: string;
  valueKey?: string;
  height?: number;
  unit?: string;
  innerR?: string;
  centerLabel?: string;
}

export function DonutChart({ data, nameKey = 'label', valueKey = 'total', height = 230, unit = '', innerR = '62%', centerLabel = 'total' }: DonutChartProps) {
  const total = data.reduce((a, d) => a + (Number(d[valueKey]) || 0), 0);
  return (
    <Measured height={height}>
      {w => (
        <div style={{ position: 'relative' }}>
          <PieChart width={w} height={height}>
            <Pie data={data} dataKey={valueKey} nameKey={nameKey} cx="50%" cy="50%" innerRadius={innerR} outerRadius="92%" paddingAngle={2} stroke="#fff" strokeWidth={2} isAnimationActive={false}>
              {data.map((d, i) => <Cell key={i} fill={(d.color as string) ?? PALETTE[i % PALETTE.length]} />)}
            </Pie>
            <Tooltip content={<RepTooltip unit={unit} />} />
          </PieChart>
          <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', pointerEvents: 'none' }}>
            <div style={{ textAlign: 'center' }}>
              <div style={{ font: '600 26px/1 "Rubik", sans-serif', color: '#172b4c', fontVariantNumeric: 'tabular-nums' }}>{total.toLocaleString('es-EC')}</div>
              <div style={{ font: '600 10px "IBM Plex Sans", sans-serif', textTransform: 'uppercase', letterSpacing: '.06em', color: '#7e8299', marginTop: 3 }}>{centerLabel}</div>
            </div>
          </div>
        </div>
      )}
    </Measured>
  );
}
