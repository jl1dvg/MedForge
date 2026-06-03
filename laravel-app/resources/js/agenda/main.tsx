import React, { useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { createAppointment } from './api';
import type { AgendaBootstrap, AppointmentForm } from './types';
import '../../css/agenda-react.css';

const emptyBootstrap: AgendaBootstrap = {
  defaults: { fecha: new Date().toISOString().slice(0, 10), hora: '08:00' },
  options: { tiposAtencion: [], doctores: [], sedes: [], afiliaciones: [] },
};

function readBootstrap(): AgendaBootstrap {
  const node = document.getElementById('agenda-react-bootstrap');
  if (!node?.textContent) return emptyBootstrap;
  try {
    return { ...emptyBootstrap, ...JSON.parse(node.textContent) } as AgendaBootstrap;
  } catch {
    return emptyBootstrap;
  }
}

function initialForm(bootstrap: AgendaBootstrap): AppointmentForm {
  return {
    hc_number: '',
    paciente: '',
    telefono: '',
    fecha: bootstrap.defaults.fecha,
    hora: bootstrap.defaults.hora,
    tipo_atencion: bootstrap.options.tiposAtencion[0]?.value ?? '',
    codigo_atencion: '',
    detalle_atencion: '',
    doctor: '',
    sede: '',
    afiliacion: '',
  };
}

function App() {
  const bootstrap = useMemo(() => readBootstrap(), []);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState<AppointmentForm>(() => initialForm(bootstrap));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const setField = (field: keyof AppointmentForm, value: string) => {
    setForm(current => ({ ...current, [field]: value }));
  };

  const close = () => {
    if (saving) return;
    setOpen(false);
    setError('');
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const result = await createAppointment(form);
      const targetDate = result.data?.fecha ?? form.fecha;
      const params = new URLSearchParams(window.location.search);
      params.set('fecha_inicio', targetDate);
      params.set('fecha_fin', targetDate);
      window.location.href = `/v2/agenda?${params.toString()}`;
    } catch (err) {
      const typed = err as Error & { details?: { errors?: Record<string, string[]> } };
      const firstValidation = typed.details?.errors ? Object.values(typed.details.errors)[0]?.[0] : '';
      setError(firstValidation || typed.message || 'No se pudo crear la cita');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="agenda-react-shell">
      <button type="button" className="agenda-react-primary" onClick={() => setOpen(true)}>
        <i className="mdi mdi-calendar-plus-outline" aria-hidden="true" />
        Nueva cita
      </button>

      {open && (
        <div className="agenda-react-backdrop" role="dialog" aria-modal="true" aria-labelledby="agenda-create-title">
          <form className="agenda-react-dialog" onSubmit={submit}>
            <div className="agenda-react-header">
              <div>
                <h4 id="agenda-create-title">Nueva cita</h4>
                <span>Registro manual en agenda</span>
              </div>
              <button type="button" className="agenda-react-icon" onClick={close} aria-label="Cerrar">
                <i className="mdi mdi-close" aria-hidden="true" />
              </button>
            </div>

            {error && <div className="agenda-react-error">{error}</div>}

            <div className="agenda-react-grid">
              <label>
                <span>Historia clínica</span>
                <input value={form.hc_number} onChange={e => setField('hc_number', e.target.value)} required maxLength={64} />
              </label>
              <label>
                <span>Paciente</span>
                <input value={form.paciente} onChange={e => setField('paciente', e.target.value)} required maxLength={191} />
              </label>
              <label>
                <span>Teléfono</span>
                <input value={form.telefono} onChange={e => setField('telefono', e.target.value)} maxLength={64} />
              </label>
              <label>
                <span>Fecha</span>
                <input type="date" value={form.fecha} onChange={e => setField('fecha', e.target.value)} required />
              </label>
              <label>
                <span>Hora</span>
                <input type="time" value={form.hora} onChange={e => setField('hora', e.target.value)} required />
              </label>
              <label>
                <span>Tipo atención</span>
                <select value={form.tipo_atencion} onChange={e => setField('tipo_atencion', e.target.value)} required>
                  <option value="">Seleccionar</option>
                  {bootstrap.options.tiposAtencion.map(option => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Código</span>
                <input value={form.codigo_atencion} onChange={e => setField('codigo_atencion', e.target.value)} maxLength={80} />
              </label>
              <label className="agenda-react-span-2">
                <span>Detalle atención</span>
                <input value={form.detalle_atencion} onChange={e => setField('detalle_atencion', e.target.value)} required maxLength={160} />
              </label>
              <label>
                <span>Doctor</span>
                <input list="agenda-react-doctores" value={form.doctor} onChange={e => setField('doctor', e.target.value)} maxLength={191} />
              </label>
              <label>
                <span>Sede</span>
                <select value={form.sede} onChange={e => setField('sede', e.target.value)}>
                  <option value="">Sin sede</option>
                  {bootstrap.options.sedes.map(option => (
                    <option key={option.value} value={option.label}>{option.label}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Afiliación</span>
                <select value={form.afiliacion} onChange={e => setField('afiliacion', e.target.value)}>
                  <option value="">Sin afiliación</option>
                  {bootstrap.options.afiliaciones.map(option => (
                    <option key={option.value} value={option.label}>{option.label}</option>
                  ))}
                </select>
              </label>
            </div>

            <datalist id="agenda-react-doctores">
              {bootstrap.options.doctores.map(option => (
                <option key={option.value} value={option.label} />
              ))}
            </datalist>

            <div className="agenda-react-footer">
              <button type="button" className="agenda-react-secondary" onClick={close} disabled={saving}>
                Cancelar
              </button>
              <button type="submit" className="agenda-react-primary" disabled={saving}>
                <i className={`mdi ${saving ? 'mdi-loading mdi-spin' : 'mdi-content-save-outline'}`} aria-hidden="true" />
                {saving ? 'Guardando' : 'Guardar cita'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}

const container = document.getElementById('agenda-react-root');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
