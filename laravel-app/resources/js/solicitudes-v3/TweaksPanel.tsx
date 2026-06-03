// ============================================================
// MedForge · Solicitudes v3 — TweaksPanel (simplified)
// ============================================================
import React, { useState, useRef, useEffect, useCallback } from 'react';
import type { TweakValues } from './types';

// ---- useTweaks hook -----------------------------------------

export function useTweaks(defaults: TweakValues): [TweakValues, (key: keyof TweakValues, val: TweakValues[keyof TweakValues]) => void] {
  const [values, setValues] = useState<TweakValues>(defaults);
  const setTweak = useCallback((key: keyof TweakValues, val: TweakValues[keyof TweakValues]) => {
    setValues((prev) => ({ ...prev, [key]: val }));
  }, []);
  return [values, setTweak];
}

// ---- Styles -------------------------------------------------

const PANEL_STYLE = `
  .twk-panel{position:fixed;right:16px;bottom:16px;z-index:2147483646;width:280px;
    max-height:calc(100vh - 32px);display:flex;flex-direction:column;
    background:rgba(250,249,247,.88);color:#29261b;
    -webkit-backdrop-filter:blur(24px) saturate(160%);backdrop-filter:blur(24px) saturate(160%);
    border:.5px solid rgba(255,255,255,.6);border-radius:14px;
    box-shadow:0 1px 0 rgba(255,255,255,.5) inset,0 12px 40px rgba(0,0,0,.18);
    font:11.5px/1.4 ui-sans-serif,system-ui,-apple-system,sans-serif;overflow:hidden}
  .twk-hd{display:flex;align-items:center;justify-content:space-between;
    padding:10px 8px 10px 14px;cursor:move;user-select:none}
  .twk-hd b{font-size:12px;font-weight:600;letter-spacing:.01em}
  .twk-x{appearance:none;border:0;background:transparent;color:rgba(41,38,27,.55);
    width:22px;height:22px;border-radius:6px;cursor:pointer;font-size:13px;line-height:1}
  .twk-x:hover{background:rgba(0,0,0,.06);color:#29261b}
  .twk-body{padding:2px 14px 14px;display:flex;flex-direction:column;gap:10px;
    overflow-y:auto;overflow-x:hidden;min-height:0}
  .twk-sect{font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
    color:rgba(41,38,27,.45);padding:10px 0 0}
  .twk-sect:first-child{padding-top:0}
  .twk-row{display:flex;flex-direction:column;gap:5px}
  .twk-row-h{flex-direction:row !important;align-items:center;justify-content:space-between;gap:10px}
  .twk-lbl{display:flex;justify-content:space-between;align-items:baseline;color:rgba(41,38,27,.72)}
  .twk-lbl>span:first-child{font-weight:500}
  .twk-seg{position:relative;display:flex;padding:2px;border-radius:8px;
    background:rgba(0,0,0,.06);user-select:none}
  .twk-seg-thumb{position:absolute;top:2px;bottom:2px;border-radius:6px;
    background:rgba(255,255,255,.9);box-shadow:0 1px 2px rgba(0,0,0,.12);
    transition:left .15s cubic-bezier(.3,.7,.4,1),width .15s}
  .twk-seg button{appearance:none;position:relative;z-index:1;flex:1;border:0;
    background:transparent;color:inherit;font:inherit;font-weight:500;min-height:22px;
    border-radius:6px;cursor:pointer;padding:4px 6px;line-height:1.2}
  .twk-toggle{position:relative;width:32px;height:18px;border:0;border-radius:999px;
    background:rgba(0,0,0,.15);transition:background .15s;cursor:pointer;padding:0}
  .twk-toggle[data-on="1"]{background:#34c759}
  .twk-toggle i{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;
    background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s;display:block}
  .twk-toggle[data-on="1"] i{transform:translateX(14px)}
  .twk-chips{display:flex;gap:6px}
  .twk-chip{position:relative;appearance:none;flex:1;min-width:0;height:32px;
    padding:0;border:0;border-radius:6px;overflow:hidden;cursor:pointer;
    box-shadow:0 0 0 .5px rgba(0,0,0,.12);transition:box-shadow .12s}
  .twk-chip[data-on="1"]{box-shadow:0 0 0 2px rgba(0,0,0,.85)}
  .twk-chip-check{position:absolute;top:4px;left:4px;width:12px;height:12px}
`;

// ---- Controls -----------------------------------------------

function TweakSection({ label }: { label: string }) {
  return <div className="twk-sect">{label}</div>;
}

interface TweakToggleProps { label: string; value: boolean; onChange: (v: boolean) => void; }
function TweakToggle({ label, value, onChange }: TweakToggleProps) {
  return (
    <div className="twk-row twk-row-h">
      <div className="twk-lbl"><span>{label}</span></div>
      <button type="button" className="twk-toggle" data-on={value ? '1' : '0'} onClick={() => onChange(!value)}><i /></button>
    </div>
  );
}

interface TweakRadioOption { value: string; label: string; }
interface TweakRadioProps { label: string; value: string; options: TweakRadioOption[]; onChange: (v: string) => void; }
function TweakRadio({ label, value, options, onChange }: TweakRadioProps) {
  const n = options.length;
  const idx = Math.max(0, options.findIndex((o) => o.value === value));
  return (
    <div className="twk-row">
      <div className="twk-lbl"><span>{label}</span></div>
      <div className="twk-seg">
        <div className="twk-seg-thumb" style={{ left: `calc(2px + ${idx} * (100% - 4px) / ${n})`, width: `calc((100% - 4px) / ${n})` }} />
        {options.map((o) => (
          <button key={o.value} type="button" onClick={() => onChange(o.value)}>{o.label}</button>
        ))}
      </div>
    </div>
  );
}

function isLight(hex: string): boolean {
  const h = hex.replace('#', '');
  const x = h.length === 3 ? h.replace(/./g, (c) => c + c) : h.padEnd(6, '0');
  const n = parseInt(x.slice(0, 6), 16);
  if (Number.isNaN(n)) return true;
  const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
  return r * 299 + g * 587 + b * 114 > 148000;
}

interface TweakColorProps { label: string; value: string; options: string[]; onChange: (v: string) => void; }
function TweakColor({ label, value, options, onChange }: TweakColorProps) {
  return (
    <div className="twk-row">
      <div className="twk-lbl"><span>{label}</span></div>
      <div className="twk-chips">
        {options.map((c) => (
          <button key={c} type="button" className="twk-chip" data-on={c === value ? '1' : '0'} style={{ background: c }} onClick={() => onChange(c)} title={c}>
            {c === value && (
              <svg className="twk-chip-check" viewBox="0 0 14 14" aria-hidden="true">
                <path d="M3 7.2 5.8 10 11 4.2" fill="none" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" stroke={isLight(c) ? 'rgba(0,0,0,.78)' : '#fff'} />
              </svg>
            )}
          </button>
        ))}
      </div>
    </div>
  );
}

// ---- TweaksPanel main export --------------------------------

interface TweaksPanelProps {
  tweaks: TweakValues;
  setTweak: (key: keyof TweakValues, val: TweakValues[keyof TweakValues]) => void;
}

export function TweaksPanel({ tweaks, setTweak }: TweaksPanelProps) {
  const [open, setOpen] = useState(false);
  const dragRef = useRef<HTMLDivElement>(null);
  const offsetRef = useRef({ x: 16, y: 16 });

  const clamp = useCallback(() => {
    const panel = dragRef.current;
    if (!panel) return;
    const w = panel.offsetWidth, h = panel.offsetHeight;
    offsetRef.current = {
      x: Math.min(Math.max(16, window.innerWidth - w - 16), offsetRef.current.x),
      y: Math.min(Math.max(16, window.innerHeight - h - 16), offsetRef.current.y),
    };
    panel.style.right = offsetRef.current.x + 'px';
    panel.style.bottom = offsetRef.current.y + 'px';
  }, []);

  useEffect(() => { if (open) clamp(); }, [open, clamp]);

  const onDragStart = (e: React.MouseEvent) => {
    const panel = dragRef.current;
    if (!panel) return;
    const r = panel.getBoundingClientRect();
    const sx = e.clientX, sy = e.clientY;
    const sr = window.innerWidth - r.right, sb = window.innerHeight - r.bottom;
    const move = (ev: MouseEvent) => {
      offsetRef.current = { x: sr - (ev.clientX - sx), y: sb - (ev.clientY - sy) };
      clamp();
    };
    const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); };
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
  };

  if (!open) {
    return (
      <button
        style={{ position: 'fixed', right: 16, bottom: 16, zIndex: 9000, width: 40, height: 40, border: '1px solid var(--border)', borderRadius: 12, background: '#fff', cursor: 'pointer', display: 'grid', placeItems: 'center', fontSize: 20, boxShadow: 'var(--shadow)' }}
        title="Tweaks"
        onClick={() => setOpen(true)}
      >
        <i className="mdi mdi-tune-variant" style={{ color: 'var(--fg-3)' }}></i>
      </button>
    );
  }

  return (
    <>
      <style>{PANEL_STYLE}</style>
      <div ref={dragRef} className="twk-panel" style={{ right: offsetRef.current.x, bottom: offsetRef.current.y }}>
        <div className="twk-hd" onMouseDown={onDragStart}>
          <b>Tweaks</b>
          <button className="twk-x" onClick={() => setOpen(false)}>✕</button>
        </div>
        <div className="twk-body">
          <TweakSection label="Dirección visual" />
          <TweakRadio
            label="Estilo"
            value={tweaks.direction}
            options={[{ value: 'a', label: 'Clínico' }, { value: 'b', label: 'Aireado' }, { value: 'c', label: 'Denso' }]}
            onChange={(v) => setTweak('direction', v as TweakValues['direction'])}
          />
          <TweakSection label="Disposición" />
          <TweakRadio
            label="Densidad"
            value={tweaks.density}
            options={[{ value: 'comodo', label: 'Cómodo' }, { value: 'compacto', label: 'Compacto' }]}
            onChange={(v) => setTweak('density', v as TweakValues['density'])}
          />
          <TweakToggle label="Agrupar por fase" value={tweaks.groupPhases} onChange={(v) => setTweak('groupPhases', v)} />
          <TweakToggle label="Color por afiliación" value={tweaks.afilColor} onChange={(v) => setTweak('afilColor', v)} />
          <TweakToggle label="Avatar del doctor" value={tweaks.showDoctorAvatar} onChange={(v) => setTweak('showDoctorAvatar', v)} />
          <TweakSection label="Marca" />
          <TweakColor
            label="Acento"
            value={tweaks.accent}
            options={['#5156be', '#3596f7', '#1f9d7a', '#7C4DFF', '#0c6fb0']}
            onChange={(v) => setTweak('accent', v)}
          />
        </div>
      </div>
    </>
  );
}
