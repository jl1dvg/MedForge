import React, { useState, useCallback, useEffect, useRef } from 'react';
import type { Patient, AppRoute, Toast as ToastType } from './types';
import { TIPO_CITA, MEDICO_MAP } from './data';
import { fetchPatientList, fetchPatientDetail, createPatient } from './api';
import { Toast, AgendarModal } from './components';
import ListView from './views/ListView';
import DetailView from './views/DetailView';
import WizardView from './views/WizardView';

export default function App() {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [route, setRoute] = useState<AppRoute>('list');
  const [selectedHc, setSelectedHc] = useState<string | null>(null);
  const [detailPatient, setDetailPatient] = useState<Patient | null>(null);
  const [, setDetailLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [toast, setToast] = useState<ToastType | null>(null);
  const [agendar, setAgendar] = useState<{ patient: Patient | null; open: boolean }>({ patient: null, open: false });
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    fetchPatientList()
      .then(list => setPatients(list))
      .catch(() => showToast('Error cargando pacientes', 'mdi-alert-circle-outline', 'err'))
      .finally(() => setLoading(false));
  }, []);

  const showToast = useCallback((msg: string, icon = 'mdi-check-circle', kind = 'ok') => {
    setToast({ msg, icon, kind });
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(null), 2800);
  }, []);

  const goList = useCallback(() => {
    setRoute('list');
    setSelectedHc(null);
    setDetailPatient(null);
  }, []);

  const openPatient = useCallback((id: number) => {
    const p = patients.find(x => x.id === id);
    if (!p) return;
    setSelectedHc(p.hc_number);
    setDetailPatient(p);
    setRoute('detail');
    setDetailLoading(true);
    fetchPatientDetail(p.hc_number)
      .then(full => { if (full) setDetailPatient(full); })
      .catch(() => {})
      .finally(() => setDetailLoading(false));
  }, [patients]);

  const goCreate = useCallback(() => {
    setRoute('create');
  }, []);

  const onWhats = useCallback((p: Patient) => {
    const tel = p.telefono.replace(/\D/g, '');
    if (tel) window.open(`https://wa.me/593${tel.replace(/^0/, '')}`, '_blank');
    else showToast('Paciente sin teléfono registrado', 'mdi-alert-circle-outline', 'warn');
  }, [showToast]);

  const onAgendar = useCallback((p: Patient) => {
    setAgendar({ patient: p, open: true });
  }, []);

  const confirmAgendar = useCallback((patientId: number, data: { fecha: string; hora: string; tipo: string }) => {
    const iso = new Date(`${data.fecha}T${data.hora || '09:00'}:00`).toISOString();
    const tipoCita = TIPO_CITA[data.tipo];
    setPatients(list => list.map(p => {
      if (p.id !== patientId) return p;
      const cita = { fecha: iso, medico: p.medico, tipo: data.tipo, estado: 'agendada', det: tipoCita?.label || data.tipo };
      const nowDate = new Date(iso);
      const existDate = p.proxima_cita ? new Date(p.proxima_cita.fecha) : null;
      const newProx = (!existDate || nowDate < existDate) && nowDate >= new Date()
        ? { fecha: iso, medico: p.medico, tipo: data.tipo }
        : p.proxima_cita;
      const newTimeline = [
        { at: new Date().toISOString(), tipo: 'cita', icon: tipoCita?.icon || 'mdi-calendar', txt: `Cita agendada: ${tipoCita?.label || data.tipo}`, by: 'Recepción' },
        ...p.timeline,
      ];
      return { ...p, citas: [cita, ...p.citas], proxima_cita: newProx, timeline: newTimeline };
    }));
    if (detailPatient?.id === patientId) {
      setDetailPatient(prev => {
        if (!prev) return prev;
        const cita = { fecha: iso, medico: prev.medico, tipo: data.tipo, estado: 'agendada', det: tipoCita?.label || data.tipo };
        const nowDate = new Date(iso);
        const existDate = prev.proxima_cita ? new Date(prev.proxima_cita.fecha) : null;
        const newProx = (!existDate || nowDate < existDate) && nowDate >= new Date()
          ? { fecha: iso, medico: prev.medico, tipo: data.tipo }
          : prev.proxima_cita;
        return { ...prev, citas: [cita, ...prev.citas], proxima_cita: newProx };
      });
    }
    setAgendar({ patient: null, open: false });
    showToast('Cita agendada correctamente', 'mdi-calendar-check');
  }, [detailPatient, showToast]);

  const onAddNote = useCallback((patientId: number, txt: string) => {
    const nowIso = new Date().toISOString();
    setPatients(list => list.map(p => {
      if (p.id !== patientId) return p;
      const medicoFull = MEDICO_MAP[p.medico]?.full || 'Sistema';
      return {
        ...p,
        notas: [{ txt, by: medicoFull, at: nowIso }, ...p.notas],
        timeline: [{ at: nowIso, tipo: 'nota', icon: 'mdi-note-text-outline', txt, by: medicoFull }, ...p.timeline],
      };
    }));
    if (detailPatient?.id === patientId) {
      const medicoFull = MEDICO_MAP[detailPatient.medico]?.full || 'Sistema';
      setDetailPatient(prev => prev ? {
        ...prev,
        notas: [{ txt, by: medicoFull, at: nowIso }, ...prev.notas],
        timeline: [{ at: nowIso, tipo: 'nota', icon: 'mdi-note-text-outline', txt, by: medicoFull }, ...prev.timeline],
      } : prev);
    }
    showToast('Nota clínica guardada', 'mdi-note-check-outline');
  }, [detailPatient, showToast]);

  const onPatientCreated = useCallback(async (localPatient: Patient) => {
    try {
      await createPatient({
        nombres: localPatient.nombres,
        apellidos: localPatient.apellidos,
        cedula: localPatient.cedula,
        fecha_nac: localPatient.fecha_nac,
        sexo: localPatient.sexo,
        telefono: localPatient.telefono,
        telefono_alt: localPatient.telefono_alt,
        email: localPatient.email,
        direccion: localPatient.direccion,
        ciudad: localPatient.ciudad,
        sede: localPatient.sede,
        medico: localPatient.medico,
        afiliacion: localPatient.afiliacion,
        aseguradora: localPatient.aseguradora,
        poliza: localPatient.poliza,
        titular: localPatient.titular,
        alerta: localPatient.alerta,
      });
      showToast('Paciente creado correctamente', 'mdi-account-check');
      setLoading(true);
      const list = await fetchPatientList();
      setPatients(list);
      setLoading(false);
      const newP = list.find(p => p.cedula === localPatient.cedula || p.hc_number === localPatient.hc_number);
      if (newP) openPatient(newP.id);
      else goList();
    } catch (err: any) {
      showToast(err.message || 'Error al crear paciente', 'mdi-alert-circle-outline', 'err');
    }
  }, [showToast, openPatient, goList]);

  return (
    <div className="pac-root">
      {/* Module topbar */}
      <div className="mod-topbar">
        <div className="mod-topbar-left">
          {route !== 'list' && (
            <button className="back-btn" onClick={goList}>
              <i className="mdi mdi-arrow-left" />
              <span>Pacientes</span>
            </button>
          )}
          <h1 className="mod-title">
            <i className="mdi mdi-account-group-outline" />
            {route === 'list' && 'Pacientes'}
            {route === 'detail' && (detailPatient?.display_name || 'Cargando…')}
            {route === 'create' && 'Nuevo paciente'}
          </h1>
        </div>
        {route === 'list' && (
          <div className="mod-topbar-right">
            <div className="search-wrap">
              <i className="mdi mdi-magnify" />
              <input
                type="search"
                placeholder="Buscar por nombre, cédula, HC…"
                value={search}
                onChange={e => setSearch(e.target.value)}
              />
              {search && (
                <button onClick={() => setSearch('')}><i className="mdi mdi-close" /></button>
              )}
            </div>
            <button className="wbtn primary" onClick={goCreate}>
              <i className="mdi mdi-account-plus" />
              Nuevo paciente
            </button>
          </div>
        )}
      </div>

      {/* Views */}
      {route === 'list' && (
        <ListView
          patients={patients}
          loading={loading}
          search={search}
          setSearch={setSearch}
          onOpen={openPatient}
          onAgendar={onAgendar}
          onWhats={onWhats}
        />
      )}

      {route === 'detail' && detailPatient && (
        <DetailView
          p={detailPatient}
          onBack={goList}
          onAgendar={onAgendar}
          onWhats={onWhats}
          onAddNote={onAddNote}
          onOpenCRM={(s) => showToast(`Abriendo solicitud ${s.id}…`, 'mdi-arrow-top-right')}
          onEditar={(p) => showToast('Modo edición próximamente', 'mdi-pencil-outline')}
          onNuevaSolicitud={(p) => showToast(`Nueva solicitud para ${p.nombres}…`, 'mdi-clipboard-plus-outline')}
        />
      )}
      {route === 'detail' && !detailPatient && (
        <div className="page"><div className="page-inner list-empty">
          <i className="mdi mdi-account-search-outline" /><h3>Cargando paciente…</h3>
        </div></div>
      )}

      {route === 'create' && (
        <WizardView
          patients={patients}
          onCancel={goList}
          onCreate={onPatientCreated}
          onOpenExisting={openPatient}
        />
      )}

      <AgendarModal
        patient={agendar.patient}
        open={agendar.open}
        onClose={() => setAgendar({ patient: null, open: false })}
        onConfirm={confirmAgendar}
      />

      <Toast toast={toast} />
    </div>
  );
}
