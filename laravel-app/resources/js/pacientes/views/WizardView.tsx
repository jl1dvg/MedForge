import React, { useState, useEffect, useMemo } from 'react';
import type { Patient, WizardFormData } from '../types';
import { MEDICOS, SEDES, ASEGURADORAS } from '../data';
import { validarCedula, emailOk, telOk, edadDe, fmtDateLong, initials } from '../utils';
import { Avatar } from '../components';
import { MEDICO_MAP, SEDE_MAP } from '../data';

const BLANK: WizardFormData = {
  docTipo: 'cedula', cedula: '', nombres: '', apellidos: '', fecha_nac: '', sexo: '',
  telefono: '', telefono_alt: '', email: '', direccion: '', ciudad: 'Guayaquil',
  afiliacion: '', aseguradora: '', poliza: '', titular: '',
  medico: '', sede: '', motivo: '', alerta: '',
};

const STEPS = [
  { n: 1, label: 'Datos básicos' },
  { n: 2, label: 'Contacto y afiliación' },
  { n: 3, label: 'Datos clínicos' },
  { n: 4, label: 'Confirmar' },
];

interface Props {
  patients: Patient[];
  onCancel: () => void;
  onCreate: (p: Patient) => void;
  onOpenExisting: (id: number) => void;
}

export default function WizardView({ patients, onCancel, onCreate, onOpenExisting }: Props) {
  const [step, setStep] = useState(1);
  const [f, setF] = useState<WizardFormData>(BLANK);
  const [touched, setTouched] = useState<Record<string, boolean>>({});
  const [showErr, setShowErr] = useState(false);
  const [checking, setChecking] = useState(false);
  const [dup, setDup] = useState<Patient | null>(null);

  const set = (k: keyof WizardFormData, v: string) => setF(s => ({ ...s, [k]: v }));
  const touch = (k: string) => setTouched(t => ({ ...t, [k]: true }));
  const showFor = (k: string) => touched[k] || showErr;

  const cedulaValida = f.docTipo === 'pasaporte' ? f.cedula.trim().length >= 5 : validarCedula(f.cedula);

  useEffect(() => {
    setDup(null);
    if (f.docTipo === 'pasaporte') {
      if (f.cedula.trim().length >= 5) {
        const ex = patients.find(p => p.cedula.toLowerCase() === f.cedula.trim().toLowerCase());
        if (ex) setDup(ex);
      }
      return;
    }
    if (!validarCedula(f.cedula)) return;
    setChecking(true);
    const t = setTimeout(() => {
      const ex = patients.find(p => p.cedula === f.cedula);
      setDup(ex || null);
      setChecking(false);
    }, 650);
    return () => { clearTimeout(t); setChecking(false); };
  }, [f.cedula, f.docTipo, patients]);

  const valid1 = !!(f.nombres.trim() && f.apellidos.trim() && cedulaValida && !dup && !checking && f.fecha_nac && f.sexo);
  const valid2 = !!(telOk(f.telefono) && (!f.email || emailOk(f.email)) && f.direccion.trim() && f.ciudad.trim() && f.afiliacion && (f.afiliacion !== 'seguro' || (f.aseguradora.trim() && f.poliza.trim())));
  const valid3 = !!(f.medico && f.sede);
  const stepValid = step === 1 ? valid1 : step === 2 ? valid2 : step === 3 ? valid3 : true;

  const next = () => {
    if (!stepValid) { setShowErr(true); return; }
    setShowErr(false); setStep(s => s + 1);
    document.querySelector('.page')?.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const back = () => { setShowErr(false); setStep(s => s - 1); };

  const save = () => {
    const newP = buildPatient(f, patients);
    onCreate(newP);
  };

  const cedulaState = checking ? 'load' : (f.cedula && !cedulaValida ? 'err' : (cedulaValida && !dup ? 'ok' : null));
  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className="page">
      <div className="page-inner">
        <div className="wiz-page">
          <button className="dt-back" onClick={onCancel}><i className="mdi mdi-arrow-left" />Cancelar y volver a la lista</button>
          <div className="wiz-head">
            <h1>Nuevo paciente</h1>
            <p>Completa los tres pasos para registrar un paciente en MedForge.</p>
          </div>

          <div className="wiz-steps">
            {STEPS.map((s, i) => (
              <React.Fragment key={s.n}>
                <div className={`wiz-step ${step === s.n ? 'current' : step > s.n ? 'done' : ''}`}>
                  <span className="ws-dot">{step > s.n ? <i className="mdi mdi-check" /> : s.n}</span>
                  <span className="ws-lbl">{s.label}</span>
                </div>
                {i < STEPS.length - 1 && <span className={`wiz-conn ${step > s.n ? 'done' : ''}`} />}
              </React.Fragment>
            ))}
          </div>

          <div className="wiz-card">
            {/* Step 1 — Datos básicos */}
            {step === 1 && (
              <>
                <div className="wiz-card-head"><h2>Datos básicos</h2><p>Identificación del paciente. Verificamos la cédula automáticamente para evitar duplicados.</p></div>
                <div className="wiz-card-body">
                  <div className="form-grid">
                    <div className="field fg-span2">
                      <label>Tipo de documento</label>
                      <div className="seg-radio">
                        <button className={f.docTipo === 'cedula' ? 'sel' : ''} onClick={() => { set('docTipo', 'cedula'); set('cedula', ''); }} type="button">
                          <i className="mdi mdi-card-account-details-outline" />Cédula
                        </button>
                        <button className={f.docTipo === 'pasaporte' ? 'sel' : ''} onClick={() => { set('docTipo', 'pasaporte'); set('cedula', ''); }} type="button">
                          <i className="mdi mdi-passport" />Pasaporte
                        </button>
                      </div>
                    </div>

                    <div className="field fg-span2">
                      <label>{f.docTipo === 'cedula' ? 'Cédula' : 'Pasaporte'} <span className="req">*</span></label>
                      <div className="input-icon">
                        <input
                          inputMode={f.docTipo === 'cedula' ? 'numeric' : 'text'}
                          maxLength={f.docTipo === 'cedula' ? 10 : 20}
                          className={showFor('cedula') && f.cedula && !cedulaValida ? 'invalid' : cedulaValida && !dup ? 'valid' : ''}
                          placeholder={f.docTipo === 'cedula' ? 'Ingresa los 10 dígitos' : 'Ej. AB123456'}
                          value={f.cedula}
                          onChange={e => set('cedula', f.docTipo === 'cedula' ? e.target.value.replace(/\D/g, '') : e.target.value)}
                          onBlur={() => touch('cedula')}
                        />
                        {cedulaState && <i className={`mdi ${cedulaState === 'load' ? 'mdi-loading load' : cedulaState === 'ok' ? 'mdi-check-circle ok' : 'mdi-alert-circle err'} ii`} />}
                      </div>
                      {f.docTipo === 'cedula' && showFor('cedula') && f.cedula && !cedulaValida && !checking && (
                        <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />La cédula ecuatoriana no es válida.</div>
                      )}
                      {checking && <div className="field-msg hint"><i className="mdi mdi-magnify" />Verificando si el paciente ya existe…</div>}
                      {cedulaValida && !dup && !checking && <div className="field-msg ok"><i className="mdi mdi-check" />Documento válido y disponible.</div>}
                    </div>

                    {dup && (
                      <div className="dup-alert">
                        <span className="da-ic"><i className="mdi mdi-account-alert-outline" /></span>
                        <div className="da-body">
                          <div className="da-title">Este paciente ya está registrado</div>
                          <div className="da-sub">Ya existe un paciente con esta cédula. No se puede crear un registro duplicado — abre la ficha existente.</div>
                          <div className="dup-card">
                            <Avatar initials={dup.initials} sede={dup.sede} size={42} />
                            <div className="dc-info">
                              <div className="dc-name">{dup.display_name}</div>
                              <div className="dc-sub">HC {dup.hc_number} · {dup.edad} años · {SEDE_MAP[dup.sede]?.label || dup.sede}</div>
                            </div>
                            <button className="dc-open" onClick={() => onOpenExisting(dup!.id)}><i className="mdi mdi-arrow-expand" />Abrir ficha</button>
                          </div>
                        </div>
                      </div>
                    )}

                    <div className="field">
                      <label>Nombres <span className="req">*</span></label>
                      <input className={showFor('nombres') && !f.nombres.trim() ? 'invalid' : ''} placeholder="Ej. María Fernanda" value={f.nombres} onChange={e => set('nombres', e.target.value)} onBlur={() => touch('nombres')} />
                      {showFor('nombres') && !f.nombres.trim() && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field">
                      <label>Apellidos <span className="req">*</span></label>
                      <input className={showFor('apellidos') && !f.apellidos.trim() ? 'invalid' : ''} placeholder="Ej. Cordero Plúas" value={f.apellidos} onChange={e => set('apellidos', e.target.value)} onBlur={() => touch('apellidos')} />
                      {showFor('apellidos') && !f.apellidos.trim() && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>

                    <div className="field">
                      <label>Fecha de nacimiento <span className="req">*</span></label>
                      <input type="date" max={today} className={showFor('fecha_nac') && !f.fecha_nac ? 'invalid' : ''} value={f.fecha_nac} onChange={e => set('fecha_nac', e.target.value)} onBlur={() => touch('fecha_nac')} />
                      {f.fecha_nac && <div className="field-msg hint"><i className="mdi mdi-cake-variant-outline" />{edadDe(f.fecha_nac)} años</div>}
                    </div>
                    <div className="field">
                      <label>Sexo <span className="req">*</span></label>
                      <div className="seg-radio">
                        <button className={f.sexo === 'F' ? 'sel' : ''} onClick={() => set('sexo', 'F')} type="button"><i className="mdi mdi-gender-female" />Femenino</button>
                        <button className={f.sexo === 'M' ? 'sel' : ''} onClick={() => set('sexo', 'M')} type="button"><i className="mdi mdi-gender-male" />Masculino</button>
                      </div>
                      {showFor('sexo') && !f.sexo && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Selecciona el sexo.</div>}
                    </div>
                  </div>
                </div>
              </>
            )}

            {/* Step 2 — Contacto y afiliación */}
            {step === 2 && (
              <>
                <div className="wiz-card-head"><h2>Contacto y afiliación</h2><p>Datos de contacto y tipo de cobertura del paciente.</p></div>
                <div className="wiz-card-body">
                  <div className="form-grid">
                    <div className="field">
                      <label>Teléfono principal <span className="req">*</span></label>
                      <input inputMode="tel" className={showFor('telefono') && !telOk(f.telefono) ? 'invalid' : ''} placeholder="Ej. 0991 224 558" value={f.telefono} onChange={e => set('telefono', e.target.value)} onBlur={() => touch('telefono')} />
                      {showFor('telefono') && !telOk(f.telefono) && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Ingresa un teléfono válido.</div>}
                    </div>
                    <div className="field">
                      <label>Teléfono alternativo <span className="opt">(opcional)</span></label>
                      <input inputMode="tel" placeholder="Ej. 04 268 1140" value={f.telefono_alt} onChange={e => set('telefono_alt', e.target.value)} />
                    </div>
                    <div className="field fg-span2">
                      <label>Correo electrónico <span className="opt">(opcional)</span></label>
                      <input inputMode="email" className={showFor('email') && f.email && !emailOk(f.email) ? 'invalid' : ''} placeholder="Ej. paciente@correo.com" value={f.email} onChange={e => set('email', e.target.value)} onBlur={() => touch('email')} />
                      {showFor('email') && f.email && !emailOk(f.email) && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />El correo no tiene un formato válido.</div>}
                    </div>
                    <div className="field fg-span2">
                      <label>Dirección <span className="req">*</span></label>
                      <input className={showFor('direccion') && !f.direccion.trim() ? 'invalid' : ''} placeholder="Ej. Cdla. Los Ceibos, Av. del Bombero 312" value={f.direccion} onChange={e => set('direccion', e.target.value)} onBlur={() => touch('direccion')} />
                      {showFor('direccion') && !f.direccion.trim() && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field">
                      <label>Ciudad <span className="req">*</span></label>
                      <input className={showFor('ciudad') && !f.ciudad.trim() ? 'invalid' : ''} placeholder="Ej. Guayaquil" value={f.ciudad} onChange={e => set('ciudad', e.target.value)} onBlur={() => touch('ciudad')} />
                    </div>
                    <div className="field">
                      <label>Tipo de afiliación <span className="req">*</span></label>
                      <select className={showFor('afiliacion') && !f.afiliacion ? 'invalid' : ''} value={f.afiliacion} onChange={e => set('afiliacion', e.target.value)} onBlur={() => touch('afiliacion')}>
                        <option value="">Selecciona…</option>
                        <option value="privado">Privado</option>
                        <option value="iess">IESS</option>
                        <option value="seguro">Seguro privado</option>
                      </select>
                      {showFor('afiliacion') && !f.afiliacion && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>

                    {f.afiliacion === 'seguro' && (
                      <div className="wiz-fieldset">
                        <div className="fs-legend"><i className="mdi mdi-shield-check-outline" />Datos del seguro privado</div>
                        <div className="form-grid">
                          <div className="field">
                            <label>Aseguradora <span className="req">*</span></label>
                            <select className={showFor('aseguradora') && !f.aseguradora ? 'invalid' : ''} value={f.aseguradora} onChange={e => set('aseguradora', e.target.value)} onBlur={() => touch('aseguradora')}>
                              <option value="">Selecciona…</option>
                              {ASEGURADORAS.map(a => <option key={a} value={a}>{a}</option>)}
                            </select>
                          </div>
                          <div className="field">
                            <label>N.º de póliza <span className="req">*</span></label>
                            <input className={showFor('poliza') && !f.poliza.trim() ? 'invalid' : ''} placeholder="Ej. SS-88421-03" value={f.poliza} onChange={e => set('poliza', e.target.value)} onBlur={() => touch('poliza')} />
                          </div>
                          <div className="field fg-span2">
                            <label>Titular de la póliza <span className="opt">(si es distinto al paciente)</span></label>
                            <input placeholder="Ej. nombre del titular" value={f.titular} onChange={e => set('titular', e.target.value)} />
                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </>
            )}

            {/* Step 3 — Datos clínicos */}
            {step === 3 && (
              <>
                <div className="wiz-card-head"><h2>Datos clínicos iniciales</h2><p>Asignación del médico y registro clínico de partida.</p></div>
                <div className="wiz-card-body">
                  <div className="form-grid">
                    <div className="field">
                      <label>Médico tratante <span className="req">*</span></label>
                      <select className={showFor('medico') && !f.medico ? 'invalid' : ''} value={f.medico} onChange={e => set('medico', e.target.value)} onBlur={() => touch('medico')}>
                        <option value="">Selecciona…</option>
                        {MEDICOS.map(m => <option key={m.id} value={m.id}>{m.full} — {m.esp}</option>)}
                      </select>
                      {showFor('medico') && !f.medico && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field">
                      <label>Sede principal <span className="req">*</span></label>
                      <select className={showFor('sede') && !f.sede ? 'invalid' : ''} value={f.sede} onChange={e => set('sede', e.target.value)} onBlur={() => touch('sede')}>
                        <option value="">Selecciona…</option>
                        {SEDES.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}
                      </select>
                      {showFor('sede') && !f.sede && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field fg-span2">
                      <label>Motivo de consulta inicial <span className="opt">(opcional)</span></label>
                      <textarea placeholder="Ej. Disminución de visión en ojo derecho, sospecha de catarata…" value={f.motivo} onChange={e => set('motivo', e.target.value)} />
                    </div>
                    <div className="field fg-span2">
                      <label>Alerta clínica <span className="opt">(alergias, condición especial)</span></label>
                      <textarea placeholder="Ej. Hipertenso · alérgico a penicilina · diabético tipo 2…" value={f.alerta} onChange={e => set('alerta', e.target.value)} />
                      <div className="field-msg hint"><i className="mdi mdi-information-outline" />Se mostrará destacada en la ficha del paciente.</div>
                    </div>
                  </div>
                </div>
              </>
            )}

            {/* Step 4 — Confirmación */}
            {step === 4 && <ConfirmStep f={f} onEdit={n => setStep(n)} />}

            <div className="wiz-foot">
              {step > 1 ? (
                <button className="wbtn ghost" onClick={back}><i className="mdi mdi-arrow-left" />Atrás</button>
              ) : (
                <button className="wbtn ghost" onClick={onCancel}>Cancelar</button>
              )}
              {showErr && !stepValid && (
                <span className="step-error"><i className="mdi mdi-alert-circle-outline" />Completa los campos obligatorios.</span>
              )}
              <div className="wf-spacer" />
              {step < 4 ? (
                <button className={`wbtn primary ${stepValid ? '' : 'is-wait'}`} onClick={next}>
                  Siguiente<i className="mdi mdi-arrow-right" />
                </button>
              ) : (
                <button className="wbtn save" onClick={save}><i className="mdi mdi-check-circle-outline" />Guardar paciente</button>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---- Confirmation step ---- */
function ConfirmStep({ f, onEdit }: { f: WizardFormData; onEdit: (n: number) => void }) {
  const m = MEDICO_MAP[f.medico];
  const afilLabel = f.afiliacion === 'privado' ? 'Privado' : f.afiliacion === 'iess' ? 'IESS' : 'Seguro privado';
  return (
    <>
      <div className="wiz-card-head"><h2>Revisa y confirma</h2><p>Verifica que todos los datos sean correctos antes de guardar.</p></div>
      <div className="wiz-card-body">
        <div className="confirm-grid">
          <div className="confirm-sec">
            <div className="cs-head"><i className="mdi mdi-card-account-details-outline" /><h3>Datos básicos</h3><button className="cs-edit" onClick={() => onEdit(1)}><i className="mdi mdi-pencil-outline" />Editar</button></div>
            <div className="cs-grid">
              <div className="ci span2"><div className="k">Nombre completo</div><div className="v">{f.nombres} {f.apellidos}</div></div>
              <div className="ci"><div className="k">{f.docTipo === 'cedula' ? 'Cédula' : 'Pasaporte'}</div><div className="v">{f.cedula}</div></div>
              <div className="ci"><div className="k">Nacimiento</div><div className="v">{fmtDateLong(f.fecha_nac)}</div></div>
              <div className="ci"><div className="k">Edad</div><div className="v">{edadDe(f.fecha_nac)} años</div></div>
              <div className="ci"><div className="k">Sexo</div><div className="v">{f.sexo === 'F' ? 'Femenino' : 'Masculino'}</div></div>
            </div>
          </div>

          <div className="confirm-sec">
            <div className="cs-head"><i className="mdi mdi-phone-outline" /><h3>Contacto y afiliación</h3><button className="cs-edit" onClick={() => onEdit(2)}><i className="mdi mdi-pencil-outline" />Editar</button></div>
            <div className="cs-grid">
              <div className="ci"><div className="k">Teléfono</div><div className="v">{f.telefono}</div></div>
              <div className="ci"><div className="k">Tel. alternativo</div><div className={`v ${f.telefono_alt ? '' : 'muted'}`}>{f.telefono_alt || 'No registrado'}</div></div>
              <div className="ci"><div className="k">Correo</div><div className={`v ${f.email ? '' : 'muted'}`}>{f.email || 'No registrado'}</div></div>
              <div className="ci span2"><div className="k">Dirección</div><div className="v">{f.direccion}, {f.ciudad}</div></div>
              <div className="ci"><div className="k">Afiliación</div><div className="v">{afilLabel}</div></div>
              {f.afiliacion === 'seguro' && <>
                <div className="ci"><div className="k">Aseguradora</div><div className="v">{f.aseguradora}</div></div>
                <div className="ci"><div className="k">Póliza</div><div className="v">{f.poliza}</div></div>
                <div className="ci"><div className="k">Titular</div><div className={`v ${f.titular ? '' : 'muted'}`}>{f.titular || 'El propio paciente'}</div></div>
              </>}
            </div>
          </div>

          <div className="confirm-sec">
            <div className="cs-head"><i className="mdi mdi-stethoscope" /><h3>Datos clínicos</h3><button className="cs-edit" onClick={() => onEdit(3)}><i className="mdi mdi-pencil-outline" />Editar</button></div>
            <div className="cs-grid">
              <div className="ci"><div className="k">Médico tratante</div><div className="v">{m ? m.full : '—'}</div></div>
              <div className="ci"><div className="k">Sede principal</div><div className="v">{f.sede ? SEDE_MAP[f.sede]?.label || f.sede : '—'}</div></div>
              <div className="ci span3"><div className="k">Motivo de consulta</div><div className={`v ${f.motivo ? '' : 'muted'}`}>{f.motivo || 'No especificado'}</div></div>
              <div className="ci span3"><div className="k">Alerta clínica</div><div className={`v ${f.alerta ? '' : 'muted'}`}>{f.alerta || 'Ninguna'}</div></div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

/* ---- Build patient from form ---- */
function buildPatient(f: WizardFormData, patients: Patient[]): Patient {
  const nowIso = new Date().toISOString();
  const maxHc = Math.max(...patients.map(p => parseInt(p.hc_number, 10) || 0), 10000);
  const hc = String(maxHc + 1).padStart(6, '0');
  const ini = initials(f.nombres || 'P', f.apellidos || 'P');
  const m = MEDICO_MAP[f.medico];
  const notas = f.motivo ? [{ txt: 'Motivo de consulta inicial: ' + f.motivo, by: m?.full || 'Recepción', at: nowIso }] : [];
  const p: Patient = {
    id: Math.max(...patients.map(x => x.id), 0) + 1,
    hc_number: hc, nombres: f.nombres, apellidos: f.apellidos,
    full_name: `${f.apellidos} ${f.nombres}`, display_name: `${f.nombres} ${f.apellidos}`, initials: ini,
    cedula: f.cedula, fecha_nac: f.fecha_nac, edad: edadDe(f.fecha_nac), sexo: f.sexo,
    telefono: f.telefono, telefono_alt: f.telefono_alt || null, email: f.email || null,
    direccion: f.direccion, ciudad: f.ciudad, sede: f.sede, medico: f.medico,
    afiliacion: f.afiliacion, aseguradora: f.aseguradora || null, poliza: f.poliza || null, titular: f.titular || null,
    emergencia: { nombre: '—', rel: '—', tel: '—' },
    ultima_visita: nowIso, proxima_cita: null, alerta: f.alerta || null, deuda: 0,
    citas: [], solicitudes: [], examenes: [], notas, facturas: [], comunicaciones: [],
    sol_activa: 0, created_at: nowIso,
    timeline: [{ at: nowIso, tipo: 'nota', icon: 'mdi-account-plus-outline', txt: 'Paciente registrado en MedForge', by: 'Recepción' }],
  };
  return p;
}
