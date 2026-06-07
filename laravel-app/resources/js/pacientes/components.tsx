import React, { useState, useEffect } from 'react';
import type { Patient, Solicitud } from './types';
import { SEDE_MAP, AFIL_MAP, ESTADO_SOL, TIPO_CITA, MEDICO_MAP } from './data';
import { fmtMoney, fmtDateShort, fmtTime, hasTime, isToday, relDays } from './utils';

/* ---- Avatar ---- */
export function Avatar({ initials, size = 38, sede }: { initials: string; size?: number; sede?: string }) {
  const extra = sede === 'villaclub' ? { background: 'linear-gradient(45deg,#3596f7,#0c6fb0)' } : {};
  return (
    <span
      className="pf-av"
      style={{ width: size, height: size, fontSize: size * 0.34, borderRadius: Math.round(size * 0.28), ...extra }}
    >
      {initials}
    </span>
  );
}

/* ---- SedeBadge ---- */
export function SedeBadge({ id }: { id: string }) {
  const s = SEDE_MAP[id];
  if (!s) return null;
  return (
    <span className={`sede-badge sede-${id}`}>
      <span className="sd-dot" />
      {s.label}
    </span>
  );
}

/* ---- AfilChip ---- */
export function AfilChip({ id }: { id: string }) {
  const a = AFIL_MAP[id];
  if (!a) return <span className="afil-chip tone-neutral">{id}</span>;
  const icon = id === 'iess' ? 'mdi-shield-account-outline' : id === 'seguro' ? 'mdi-shield-check-outline' : 'mdi-account-cash-outline';
  return <span className={`afil-chip tone-${a.tone}`}><i className={`mdi ${icon}`} />{a.label}</span>;
}

/* ---- SolBadge ---- */
export function SolBadge({ estado, small }: { estado: string; small?: boolean }) {
  const s = ESTADO_SOL[estado] || { label: estado, tone: 'neutral' };
  return <span className={`sol-badge tone-${s.tone} ${small ? 'sm' : ''}`}>{s.label}</span>;
}

/* ---- PatientBadges ---- */
export function PatientBadges({ p, compact }: { p: Patient; compact?: boolean }) {
  const badges: { k: string; icon: string; label: string; tone: string; title?: string }[] = [];
  if (p.sol_activa > 0) badges.push({ k: 'sol', icon: 'mdi-clipboard-text-clock-outline', label: compact ? String(p.sol_activa) : `${p.sol_activa} solicitud${p.sol_activa > 1 ? 'es' : ''}`, tone: 'sol' });
  if (p.deuda > 0) badges.push({ k: 'deuda', icon: 'mdi-cash-remove', label: compact ? fmtMoney(p.deuda) : `Deuda ${fmtMoney(p.deuda)}`, tone: 'deuda' });
  if (p.alerta) badges.push({ k: 'alerta', icon: 'mdi-alert-circle-outline', label: compact ? '' : 'Alerta clínica', tone: 'alerta', title: p.alerta });
  if (!badges.length) return <span className="pb-none">—</span>;
  return (
    <div className="pf-badges">
      {badges.map(b => (
        <span key={b.k} className={`pf-badge tone-${b.tone}`} title={b.title || b.label}>
          <i className={`mdi ${b.icon}`} />
          {b.label !== '' && <span>{b.label}</span>}
        </span>
      ))}
    </div>
  );
}

/* ---- MedicoCell ---- */
export function MedicoCell({ id }: { id: string }) {
  const m = MEDICO_MAP[id];
  if (!m) return <span className="tc-muted">{id || '—'}</span>;
  return (
    <div className="medico-cell">
      <span className="md-name">{m.full}</span>
      <span className="md-esp">{m.esp}</span>
    </div>
  );
}

/* ---- ProxCita ---- */
export function ProxCita({ cita }: { cita: Patient['proxima_cita'] }) {
  if (!cita) return <span className="px-none">Sin próxima cita</span>;
  const t = TIPO_CITA[cita.tipo] || { icon: 'mdi-calendar', cat: 'consulta' };
  const today = isToday(cita.fecha);
  return (
    <span className={`prox-cita ${today ? 'is-today' : ''} cat-${t.cat}`}>
      <i className={`mdi ${t.icon}`} />
      <span>{fmtDateShort(cita.fecha)}{hasTime(cita.fecha) ? ` · ${fmtTime(cita.fecha)}` : ''}</span>
      {today && <span className="px-flag">HOY</span>}
    </span>
  );
}

/* ---- KPI card ---- */
export function Kpi({ tone, icon, value, label, sub, active, onClick }: {
  tone: string; icon: string; value: number | string; label: string;
  sub?: string; active?: boolean; onClick?: (() => void) | null;
}) {
  return (
    <button
      className={`pkpi tone-${tone} ${active ? 'is-active' : ''} ${onClick ? '' : 'is-static'}`}
      onClick={onClick || undefined}
      type="button"
    >
      <span className="pkpi-ic"><i className={`mdi ${icon}`} /></span>
      <span className="pkpi-body">
        <span className="pkpi-value">{value}</span>
        <span className="pkpi-label">{label}</span>
      </span>
      {sub && <span className="pkpi-sub">{sub}</span>}
    </button>
  );
}

/* ---- Row quick actions ---- */
export function RowActions({ p, onOpen, onAgendar, onWhats }: {
  p: Patient; onOpen: (id: number) => void; onAgendar: (p: Patient) => void; onWhats: (p: Patient) => void;
}) {
  return (
    <div className="row-actions" onClick={e => e.stopPropagation()}>
      <button className="ra-btn ra-view" title="Ver detalle" onClick={() => onOpen(p.id)}><i className="mdi mdi-arrow-expand" /></button>
      <button className="ra-btn ra-cal" title="Agendar cita" onClick={() => onAgendar(p)}><i className="mdi mdi-calendar-plus" /></button>
      <button className="ra-btn ra-wa" title="Enviar WhatsApp" onClick={() => onWhats(p)}><i className="mdi mdi-whatsapp" /></button>
    </div>
  );
}

/* ---- Toast ---- */
export function Toast({ toast }: { toast: { msg: string; icon: string; kind: string } | null }) {
  if (!toast) return null;
  return (
    <div className="toast-wrap">
      <div className={`toast ${toast.kind || 'ok'}`}>
        <i className={`mdi ${toast.icon}`} />{toast.msg}
      </div>
    </div>
  );
}

/* ---- Collapsible section ---- */
export function Section({ id, icon, title, count, badge, open, onToggle, children }: {
  id: string; icon: string; title: string; count?: number; badge?: React.ReactNode;
  open: boolean; onToggle: (id: string) => void; children: React.ReactNode;
}) {
  return (
    <section className={`fsec ${open ? 'is-open' : ''}`} id={`sec-${id}`}>
      <button className="fsec-head" onClick={() => onToggle(id)} type="button">
        <span className="fsec-ic"><i className={`mdi ${icon}`} /></span>
        <span className="fsec-title">{title}</span>
        {count != null && <span className="fsec-count">{count}</span>}
        {badge}
        <span className="fsec-chev"><i className="mdi mdi-chevron-down" /></span>
      </button>
      <div className="fsec-body">{children}</div>
    </section>
  );
}

/* ---- Empty mini state ---- */
export function EmptyMini({ icon, msg, children }: { icon?: string; msg?: string; children?: React.ReactNode }) {
  return <div className="mini-empty"><i className={`mdi ${icon || 'mdi-information-outline'}`} />{children ?? msg}</div>;
}

/* ---- Edit patient modal ---- */
export function EditPatientModal({ patient, open, onClose, onSave }: {
  patient: Patient | null; open: boolean; onClose: () => void;
  onSave: (hcNumber: string, data: Record<string, any>) => Promise<void>;
}) {
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [form, setForm] = useState({
    fname: '', mname: '', lname: '', lname2: '',
    fecha_nacimiento: '', sexo: 'M', celular: '', afiliacion: 'privado',
  });

  useEffect(() => {
    if (open && patient) {
      const nombres = patient.nombres.trim().split(/\s+/);
      const apellidos = patient.apellidos.trim().split(/\s+/);
      setForm({
        fname: nombres[0] || '',
        mname: nombres.slice(1).join(' ') || '',
        lname: apellidos[0] || '',
        lname2: apellidos.slice(1).join(' ') || '',
        fecha_nacimiento: patient.fecha_nac?.slice(0, 10) || '',
        sexo: patient.sexo || 'M',
        celular: patient.telefono || '',
        afiliacion: patient.afiliacion || 'privado',
      });
      setError('');
    }
  }, [open, patient?.hc_number]);

  const set = (k: string, v: string) => setForm(f => ({ ...f, [k]: v }));

  const handleSave = async () => {
    if (!patient) return;
    setSaving(true);
    setError('');
    try {
      await onSave(patient.hc_number, form);
      onClose();
    } catch (e: any) {
      setError(e.message || 'Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className={`modal-backdrop ${open ? 'open' : ''}`} onClick={onClose}>
      <div className="modal modal-lg" onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <span className="mh-ic"><i className="mdi mdi-account-edit-outline" /></span>
          <div>
            <h2>Editar paciente</h2>
            {patient && <p>HC {patient.hc_number} · {patient.cedula}</p>}
          </div>
          <button className="mh-close" onClick={onClose}><i className="mdi mdi-close" /></button>
        </div>
        <div className="modal-body">
          {error && <div className="edit-error"><i className="mdi mdi-alert-circle-outline" />{error}</div>}
          <p className="edit-section-label">Nombres</p>
          <div className="form-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="field"><label>Primer nombre</label><input value={form.fname} onChange={e => set('fname', e.target.value)} /></div>
            <div className="field"><label>Segundo nombre</label><input value={form.mname} onChange={e => set('mname', e.target.value)} /></div>
          </div>
          <p className="edit-section-label">Apellidos</p>
          <div className="form-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="field"><label>Primer apellido</label><input value={form.lname} onChange={e => set('lname', e.target.value)} /></div>
            <div className="field"><label>Segundo apellido</label><input value={form.lname2} onChange={e => set('lname2', e.target.value)} /></div>
          </div>
          <p className="edit-section-label">Datos personales</p>
          <div className="form-grid" style={{ gridTemplateColumns: '1fr 1fr 1fr' }}>
            <div className="field"><label>Fecha de nacimiento</label><input type="date" value={form.fecha_nacimiento} onChange={e => set('fecha_nacimiento', e.target.value)} /></div>
            <div className="field"><label>Sexo</label>
              <select value={form.sexo} onChange={e => set('sexo', e.target.value)}>
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
              </select>
            </div>
            <div className="field"><label>Teléfono / celular</label><input value={form.celular} onChange={e => set('celular', e.target.value)} /></div>
          </div>
          <p className="edit-section-label">Afiliación</p>
          <div className="form-grid" style={{ gridTemplateColumns: '1fr' }}>
            <div className="field"><label>Tipo de afiliación</label>
              <select value={form.afiliacion} onChange={e => set('afiliacion', e.target.value)}>
                <option value="privado">Privado</option>
                <option value="iess">IESS</option>
                <option value="seguro">Seguro privado</option>
              </select>
            </div>
          </div>
        </div>
        <div className="modal-foot">
          <button className="wbtn ghost" onClick={onClose} disabled={saving}>Cancelar</button>
          <button className="wbtn primary" onClick={handleSave} disabled={saving}>
            {saving ? <><i className="mdi mdi-loading mdi-spin" />Guardando…</> : <><i className="mdi mdi-check" />Guardar cambios</>}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---- Agendar modal ---- */
export function AgendarModal({ patient, open, onClose, onConfirm }: {
  patient: Patient | null; open: boolean; onClose: () => void;
  onConfirm: (patientId: number, data: { fecha: string; hora: string; tipo: string }) => void;
}) {
  const [fecha, setFecha] = React.useState('');
  const [hora, setHora] = React.useState('09:00');
  const [tipo, setTipo] = React.useState('consulta');

  React.useEffect(() => { if (open) { setFecha(''); setHora('09:00'); setTipo('consulta'); } }, [open, patient?.id]);

  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className={`modal-backdrop ${open ? 'open' : ''}`} onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <span className="mh-ic"><i className="mdi mdi-calendar-plus" /></span>
          <div>
            <h2>Agendar cita</h2>
            {patient && <p>{patient.display_name} · HC {patient.hc_number}</p>}
          </div>
          <button className="mh-close" onClick={onClose}><i className="mdi mdi-close" /></button>
        </div>
        <div className="modal-body">
          <div className="field">
            <label>Tipo de cita</label>
            <select value={tipo} onChange={e => setTipo(e.target.value)}>
              {Object.entries(TIPO_CITA).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </select>
          </div>
          <div className="form-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="field"><label>Fecha</label><input type="date" min={today} value={fecha} onChange={e => setFecha(e.target.value)} /></div>
            <div className="field"><label>Hora</label><input type="time" value={hora} onChange={e => setHora(e.target.value)} /></div>
          </div>
        </div>
        <div className="modal-foot">
          <button className="wbtn ghost" onClick={onClose}>Cancelar</button>
          <button
            className="wbtn primary"
            disabled={!fecha || !patient}
            style={!fecha ? { opacity: .5, pointerEvents: 'none' } : {}}
            onClick={() => patient && onConfirm(patient.id, { fecha, hora, tipo })}
          >
            <i className="mdi mdi-check" />Confirmar cita
          </button>
        </div>
      </div>
    </div>
  );
}
