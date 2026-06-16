import React, { useState, useEffect, useMemo } from 'react';
import type { PacientesCatalogos, Patient, WizardFormData } from '../types';
import { MEDICOS, SEDES, ASEGURADORAS } from '../data';
import { validarCedula, emailOk, telOk, edadDe, fmtDateLong, initials } from '../utils';
import { Avatar } from '../components';
import { MEDICO_MAP, SEDE_MAP } from '../data';

const BLANK: WizardFormData = {
  docTipo: 'cedula', cedula: '', fname: '', mname: '', lname: '', lname2: '',
  nombres: '', apellidos: '', fecha_nac: '', sexo: '',
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
  catalogos?: PacientesCatalogos | null;
  mode?: 'create' | 'edit';
  initialPatient?: Patient | null;
  onCancel: () => void;
  onCreate: (p: Patient) => void | Promise<void>;
  onUpdate?: (hcNumber: string, data: WizardFormData) => void | Promise<void>;
  onOpenExisting: (id: number) => void;
}

export default function WizardView({ patients, catalogos = null, mode = 'create', initialPatient = null, onCancel, onCreate, onUpdate, onOpenExisting }: Props) {
  const isEdit = mode === 'edit';
  const [step, setStep] = useState(1);
  const [f, setF] = useState<WizardFormData>(() => initialPatient ? formFromPatient(initialPatient) : BLANK);
  const [touched, setTouched] = useState<Record<string, boolean>>({});
  const [showErr, setShowErr] = useState(false);
  const [checking, setChecking] = useState(false);
  const [dup, setDup] = useState<Patient | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState('');

  const set = (k: keyof WizardFormData, v: string) => setF(s => ({ ...s, [k]: v }));
  const setNamePart = (k: 'fname' | 'mname' | 'lname' | 'lname2', v: string) => setF(s => {
    const next = { ...s, [k]: v };
    return {
      ...next,
      nombres: [next.fname, next.mname].filter(Boolean).join(' ').trim(),
      apellidos: [next.lname, next.lname2].filter(Boolean).join(' ').trim(),
    };
  });
  const touch = (k: string) => setTouched(t => ({ ...t, [k]: true }));
  const showFor = (k: string) => touched[k] || showErr;
  const medicoOptions = catalogos?.medicos?.length ? catalogos.medicos : MEDICOS;
  const sedeOptions = catalogos?.sedes?.length ? catalogos.sedes : SEDES;
  const afiliacionOptions = catalogos?.afiliaciones?.length ? catalogos.afiliaciones : [];
  const aseguradoraOptions = catalogos?.aseguradoras?.length ? catalogos.aseguradoras : ASEGURADORAS;

  const cedulaValida = isEdit && !f.cedula.trim()
    ? true
    : f.docTipo === 'pasaporte'
      ? f.cedula.trim().length >= 5
      : validarCedula(f.cedula);

  useEffect(() => {
    if (isEdit && initialPatient) {
      setF(formFromPatient(initialPatient));
      setStep(1);
      setTouched({});
      setShowErr(false);
      setDup(null);
      setSaveError('');
    }
  }, [isEdit, initialPatient?.hc_number]);

  useEffect(() => {
    setDup(null);
    if (isEdit && !f.cedula.trim()) return;
    if (f.docTipo === 'pasaporte') {
      if (f.cedula.trim().length >= 5) {
        const ex = patients.find(p =>
          p.cedula.toLowerCase() === f.cedula.trim().toLowerCase()
          && (!isEdit || p.hc_number !== initialPatient?.hc_number)
        );
        if (ex) setDup(ex);
      }
      return;
    }
    if (!validarCedula(f.cedula)) return;
    setChecking(true);
    const t = setTimeout(() => {
      const ex = patients.find(p =>
        p.cedula === f.cedula
        && (!isEdit || p.hc_number !== initialPatient?.hc_number)
      );
      setDup(ex || null);
      setChecking(false);
    }, 650);
    return () => { clearTimeout(t); setChecking(false); };
  }, [f.cedula, f.docTipo, patients, isEdit, initialPatient?.hc_number]);

  const valid1 = isEdit
    ? !!(f.fname.trim() && f.lname.trim() && cedulaValida && !dup && !checking)
    : !!(f.fname.trim() && f.lname.trim() && cedulaValida && !dup && !checking && f.fecha_nac && f.sexo);
  const valid2 = isEdit
    ? !!(!f.email || emailOk(f.email))
    : !!(telOk(f.telefono) && (!f.email || emailOk(f.email)) && f.direccion.trim() && f.ciudad.trim() && f.afiliacion && (f.afiliacion !== 'seguro' || (f.aseguradora.trim() && f.poliza.trim())));
  const valid3 = isEdit || !!(f.medico && f.sede);
  const stepValid = step === 1 ? valid1 : step === 2 ? valid2 : step === 3 ? valid3 : true;

  const next = () => {
    if (!stepValid) { setShowErr(true); return; }
    setShowErr(false); setStep(s => s + 1);
    document.querySelector('.page')?.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const back = () => { setShowErr(false); setStep(s => s - 1); };

  const save = async () => {
    setSaveError('');
    setSaving(true);
    try {
      if (isEdit && initialPatient && onUpdate) {
        await onUpdate(initialPatient.hc_number, f);
        return;
      }
      const newP = buildPatient(f, patients);
      await onCreate(newP);
    } catch (error: any) {
      setSaveError(error?.message || 'No se pudo guardar el paciente.');
    } finally {
      setSaving(false);
    }
  };

  const cedulaState = checking ? 'load' : (f.cedula && !cedulaValida ? 'err' : (f.cedula && cedulaValida && !dup ? 'ok' : null));
  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className="page">
      <div className="page-inner">
        <div className="wiz-page">
          <button className="dt-back" onClick={onCancel}><i className="mdi mdi-arrow-left" />{isEdit ? 'Cancelar y volver a la ficha' : 'Cancelar y volver a la lista'}</button>
          <div className="wiz-head">
            <h1>{isEdit ? 'Editar paciente' : 'Nuevo paciente'}</h1>
            <p>{isEdit ? `Actualiza los datos del paciente HC ${initialPatient?.hc_number || ''} con el mismo flujo de registro.` : 'Completa los tres pasos para registrar un paciente en MedForge.'}</p>
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
                      {f.cedula && cedulaValida && !dup && !checking && <div className="field-msg ok"><i className="mdi mdi-check" />Documento válido y disponible.</div>}
                      {isEdit && !f.cedula && <div className="field-msg hint"><i className="mdi mdi-information-outline" />Este registro no tiene documento guardado; puedes conservarlo vacío.</div>}
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
                      <label>Primer nombre <span className="req">*</span></label>
                      <input className={showFor('fname') && !f.fname.trim() ? 'invalid' : ''} placeholder="Ej. María" value={f.fname} onChange={e => setNamePart('fname', e.target.value)} onBlur={() => touch('fname')} />
                      {showFor('fname') && !f.fname.trim() && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field">
                      <label>Segundo nombre <span className="opt">(opcional)</span></label>
                      <input placeholder="Ej. Fernanda" value={f.mname} onChange={e => setNamePart('mname', e.target.value)} />
                    </div>
                    <div className="field">
                      <label>Primer apellido <span className="req">*</span></label>
                      <input className={showFor('lname') && !f.lname.trim() ? 'invalid' : ''} placeholder="Ej. Cordero" value={f.lname} onChange={e => setNamePart('lname', e.target.value)} onBlur={() => touch('lname')} />
                      {showFor('lname') && !f.lname.trim() && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field">
                      <label>Segundo apellido <span className="opt">(opcional)</span></label>
                      <input placeholder="Ej. Plúas" value={f.lname2} onChange={e => setNamePart('lname2', e.target.value)} />
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
                      <label>Plan de afiliación {!isEdit ? <span className="req">*</span> : <span className="opt">(opcional)</span>}</label>
                      <input
                        list="pac-afiliaciones-list"
                        className={!isEdit && showFor('afiliacion') && !f.afiliacion ? 'invalid' : ''}
                        value={f.afiliacion}
                        placeholder="Busca o selecciona un plan…"
                        onChange={e => set('afiliacion', e.target.value)}
                        onBlur={() => touch('afiliacion')}
                      />
                      <datalist id="pac-afiliaciones-list">
                        {afiliacionOptions.map(a => {
                          const value = a.nombre || a.label || a.id;
                          return <option key={a.id || value} value={value}>{a.label || value}</option>;
                        })}
                      </datalist>
                      {!isEdit && showFor('afiliacion') && !f.afiliacion && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>

                    {f.afiliacion === 'seguro' && (
                      <div className="wiz-fieldset">
                        <div className="fs-legend"><i className="mdi mdi-shield-check-outline" />Datos del seguro privado</div>
                        <div className="form-grid">
                          <div className="field">
                            <label>Aseguradora <span className="req">*</span></label>
                            <select className={showFor('aseguradora') && !f.aseguradora ? 'invalid' : ''} value={f.aseguradora} onChange={e => set('aseguradora', e.target.value)} onBlur={() => touch('aseguradora')}>
                              <option value="">Selecciona…</option>
                              {aseguradoraOptions.map(a => <option key={a} value={a}>{a}</option>)}
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
                        {isEdit && f.medico && !medicoOptions.some(m => m.id === f.medico) && (
                          <option value={f.medico}>{f.medico}</option>
                        )}
                        {medicoOptions.map(m => <option key={m.id} value={m.id}>{m.full || m.nombre || m.id} — {m.esp || m.especialidad || ''}</option>)}
                      </select>
                      {showFor('medico') && !f.medico && !isEdit && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
                    </div>
                    <div className="field">
                      <label>Sede principal <span className="req">*</span></label>
                      <select className={showFor('sede') && !f.sede ? 'invalid' : ''} value={f.sede} onChange={e => set('sede', e.target.value)} onBlur={() => touch('sede')}>
                        <option value="">Selecciona…</option>
                        {isEdit && f.sede && !sedeOptions.some(s => s.id === f.sede) && (
                          <option value={f.sede}>{f.sede}</option>
                        )}
                        {sedeOptions.map(s => <option key={s.id} value={s.id}>{s.label || s.nombre || s.id}</option>)}
                      </select>
                      {showFor('sede') && !f.sede && !isEdit && <div className="field-msg err"><i className="mdi mdi-alert-circle-outline" />Campo obligatorio.</div>}
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
            {step === 4 && <ConfirmStep f={f} catalogos={catalogos} onEdit={n => setStep(n)} />}

            <div className="wiz-foot">
              {step > 1 ? (
                <button className="wbtn ghost" onClick={back}><i className="mdi mdi-arrow-left" />Atrás</button>
              ) : (
                <button className="wbtn ghost" onClick={onCancel}>Cancelar</button>
              )}
              {showErr && !stepValid && (
                <span className="step-error"><i className="mdi mdi-alert-circle-outline" />Completa los campos obligatorios.</span>
              )}
              {saveError && (
                <span className="step-error"><i className="mdi mdi-alert-circle-outline" />{saveError}</span>
              )}
              <div className="wf-spacer" />
              {step < 4 ? (
                <button className={`wbtn primary ${stepValid ? '' : 'is-wait'}`} onClick={next}>
                  Siguiente<i className="mdi mdi-arrow-right" />
                </button>
              ) : (
                <button className="wbtn save" onClick={save} disabled={saving}>
                  {saving ? <><i className="mdi mdi-loading mdi-spin" />Guardando…</> : <><i className="mdi mdi-check-circle-outline" />{isEdit ? 'Guardar cambios' : 'Guardar paciente'}</>}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---- Confirmation step ---- */
function ConfirmStep({ f, catalogos, onEdit }: { f: WizardFormData; catalogos?: PacientesCatalogos | null; onEdit: (n: number) => void }) {
  const medicos = catalogos?.medicos?.length ? catalogos.medicos : MEDICOS;
  const sedes = catalogos?.sedes?.length ? catalogos.sedes : SEDES;
  const m = medicos.find(item => item.id === f.medico) || MEDICO_MAP[f.medico];
  const sede = sedes.find(item => item.id === f.sede);
  const afilLabel = f.afiliacion || '—';
  return (
    <>
      <div className="wiz-card-head"><h2>Revisa y confirma</h2><p>Verifica que todos los datos sean correctos antes de guardar.</p></div>
      <div className="wiz-card-body">
        <div className="confirm-grid">
          <div className="confirm-sec">
            <div className="cs-head"><i className="mdi mdi-card-account-details-outline" /><h3>Datos básicos</h3><button className="cs-edit" onClick={() => onEdit(1)}><i className="mdi mdi-pencil-outline" />Editar</button></div>
            <div className="cs-grid">
              <div className="ci span2"><div className="k">Nombre completo</div><div className="v">{[f.fname, f.mname, f.lname, f.lname2].filter(Boolean).join(' ')}</div></div>
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
              <div className="ci"><div className="k">Médico tratante</div><div className="v">{m ? (m.full || m.nombre || m.id) : '—'}</div></div>
              <div className="ci"><div className="k">Sede principal</div><div className="v">{sede ? (sede.label || sede.nombre || sede.id) : (f.sede ? SEDE_MAP[f.sede]?.label || f.sede : '—')}</div></div>
              <div className="ci span3"><div className="k">Motivo de consulta</div><div className={`v ${f.motivo ? '' : 'muted'}`}>{f.motivo || 'No especificado'}</div></div>
              <div className="ci span3"><div className="k">Alerta clínica</div><div className={`v ${f.alerta ? '' : 'muted'}`}>{f.alerta || 'Ninguna'}</div></div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

function formFromPatient(p: Patient): WizardFormData {
  const cedula = p.cedula || '';
  const sexo = String(p.sexo || '').toLowerCase().startsWith('f')
    ? 'F'
    : String(p.sexo || '').toLowerCase().startsWith('m')
      ? 'M'
      : p.sexo || '';

  return {
    docTipo: cedula && !validarCedula(cedula) ? 'pasaporte' : 'cedula',
    cedula,
    fname: p.fname || p.nombres?.split(/\s+/)[0] || '',
    mname: p.mname || p.nombres?.split(/\s+/).slice(1).join(' ') || '',
    lname: p.lname || p.apellidos?.split(/\s+/)[0] || '',
    lname2: p.lname2 || p.apellidos?.split(/\s+/).slice(1).join(' ') || '',
    nombres: p.nombres || [p.fname, p.mname].filter(Boolean).join(' '),
    apellidos: p.apellidos || [p.lname, p.lname2].filter(Boolean).join(' '),
    fecha_nac: p.fecha_nac?.slice(0, 10) || '',
    sexo,
    telefono: p.telefono || '',
    telefono_alt: p.telefono_alt || '',
    email: p.email || '',
    direccion: p.direccion || '',
    ciudad: p.ciudad || 'Guayaquil',
    afiliacion: p.afiliacion || '',
    aseguradora: p.aseguradora || '',
    poliza: p.poliza || '',
    titular: p.titular || '',
    medico: p.medico_tratante?.id ? String(p.medico_tratante.id) : (p.medico || ''),
    sede: p.sede || p.sede_info?.id || '',
    motivo: '',
    alerta: p.alerta || '',
  };
}

/* ---- Build patient from form ---- */
function buildPatient(f: WizardFormData, patients: Patient[]): Patient {
  const nowIso = new Date().toISOString();
  const maxHc = Math.max(...patients.map(p => parseInt(p.hc_number, 10) || 0), 10000);
  const hc = String(maxHc + 1).padStart(6, '0');
  const nombres = [f.fname, f.mname].filter(Boolean).join(' ').trim();
  const apellidos = [f.lname, f.lname2].filter(Boolean).join(' ').trim();
  const ini = initials(nombres || 'P', apellidos || 'P');
  const m = MEDICO_MAP[f.medico];
  const notas = f.motivo ? [{ txt: 'Motivo de consulta inicial: ' + f.motivo, by: m?.full || 'Recepción', at: nowIso }] : [];
  const p: Patient = {
    id: Math.max(...patients.map(x => x.id), 0) + 1,
    hc_number: hc, fname: f.fname, mname: f.mname, lname: f.lname, lname2: f.lname2, nombres, apellidos,
    full_name: `${apellidos} ${nombres}`, display_name: `${nombres} ${apellidos}`, initials: ini,
    cedula: f.cedula, fecha_nac: f.fecha_nac, edad: edadDe(f.fecha_nac), sexo: f.sexo,
    telefono: f.telefono, telefono_alt: f.telefono_alt || null, email: f.email || null,
    direccion: f.direccion, ciudad: f.ciudad, sede: f.sede, sede_info: null, medico: f.medico, medico_tratante: null,
    afiliacion: f.afiliacion, tipo_afiliacion: 'otros', afiliacion_info: null, aseguradora: f.aseguradora || null, poliza: f.poliza || null, titular: f.titular || null,
    emergencia: { nombre: '—', rel: '—', tel: '—' },
    ultima_visita: nowIso, proxima_cita: null, alerta: f.alerta || null, deuda: 0,
    citas: [], solicitudes: [], examenes: [], notas, facturas: [], comunicaciones: [],
    sol_activa: 0, created_at: nowIso,
    timeline: [{ at: nowIso, tipo: 'nota', icon: 'mdi-account-plus-outline', txt: 'Paciente registrado en MedForge', by: 'Recepción' }],
  };
  return p;
}
