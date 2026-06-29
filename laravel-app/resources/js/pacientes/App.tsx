import React, { useState, useCallback, useEffect, useRef } from 'react';
import type { PacientesCatalogos, Patient, AppRoute, Toast as ToastType, WizardFormData } from './types';
import { MEDICO_MAP } from './data';
import { fetchPatientList, fetchPatientDetail, fetchPatientCatalogos, createPatient, updatePatient } from './api';
import { Toast } from './components';
import ListView from './views/ListView';
import DetailView from './views/DetailView';
import WizardView from './views/WizardView';

export default function App() {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [catalogos, setCatalogos] = useState<PacientesCatalogos | null>(null);
  const [loading, setLoading] = useState(true);
  const [route, setRoute] = useState<AppRoute>('list');
  const [selectedHc, setSelectedHc] = useState<string | null>(null);
  const [detailPatient, setDetailPatient] = useState<Patient | null>(null);
  const [, setDetailLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [toast, setToast] = useState<ToastType | null>(null);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    Promise.all([fetchPatientList(), fetchPatientCatalogos()])
      .then(([list, cats]) => {
        setPatients(list);
        setCatalogos(cats);
      })
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

  useEffect(() => {
    if (loading || route !== 'list') return;

    const params = new URLSearchParams(window.location.search);
    const hcNumber = String(params.get('hc_number') || '').trim();
    if (!hcNumber) return;

    const target = patients.find(p => p.hc_number === hcNumber);
    if (target) {
      openPatient(target.id);
      return;
    }

    setSelectedHc(hcNumber);
    setRoute('detail');
    setDetailLoading(true);
    fetchPatientDetail(hcNumber)
      .then(full => {
        if (full) {
          setDetailPatient(full);
        } else {
          showToast('Paciente no encontrado', 'mdi-account-alert-outline', 'warn');
          setRoute('list');
        }
      })
      .catch(() => {
        showToast('Error cargando paciente', 'mdi-alert-circle-outline', 'err');
        setRoute('list');
      })
      .finally(() => setDetailLoading(false));
  }, [loading, openPatient, patients, route, showToast]);

  const goCreate = useCallback(() => {
    setRoute('create');
  }, []);

  const onWhats = useCallback((p: Patient) => {
    const tel = (p.telefono || '').replace(/\D/g, '');
    const search = tel || p.hc_number || p.display_name || p.full_name;
    if (!search) {
      showToast('Paciente sin teléfono ni HC registrado', 'mdi-alert-circle-outline', 'warn');
      return;
    }

    const params = new URLSearchParams();
    params.set('search', search);
    if (p.hc_number) params.set('hc_number', p.hc_number);
    if (tel) params.set('number', tel);
    window.location.href = `/v3/whatsapp/chat?${params.toString()}`;
  }, [showToast]);

  const openAgendaForPatient = useCallback((p: Patient) => {
    const params = new URLSearchParams();
    params.set('nuevo', '1');
    params.set('hc_number', p.hc_number);
    params.set('paciente', p.display_name || p.full_name || p.hc_number);
    if (p.telefono) params.set('telefono', p.telefono);
    if (p.afiliacion) params.set('afiliacion', p.afiliacion);
    if (p.sede_info?.nombre || p.sede) params.set('sede', p.sede_info?.nombre || p.sede);
    if (p.medico_tratante?.nombre || p.medico) params.set('doctor', p.medico_tratante?.nombre || p.medico);
    window.location.href = `/v2/agenda?${params.toString()}`;
  }, []);

  const openSolicitudesForPatient = useCallback((p: Patient) => {
    const params = new URLSearchParams();
    params.set('hc_number', p.hc_number);
    params.set('paciente', p.display_name || p.full_name || p.hc_number);
    if (p.telefono) params.set('telefono', p.telefono);
    if (p.email) params.set('email', p.email);
    window.location.href = `/v2/solicitudes?${params.toString()}`;
  }, []);

  const openSolicitudCrm = useCallback((solicitud: any) => {
    const params = new URLSearchParams();
    const solicitudId = String(solicitud?.id || solicitud?.solicitud_id || '').trim();
    if (solicitudId) params.set('open_crm', solicitudId);
    if (solicitud?.hc_number) params.set('hc_number', String(solicitud.hc_number));
    window.location.href = `/v2/solicitudes${params.toString() ? `?${params.toString()}` : ''}`;
  }, []);

  const onAgendar = useCallback((p: Patient) => {
    openAgendaForPatient(p);
  }, [openAgendaForPatient]);

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

  const onEditar = useCallback((p: Patient) => {
    setSelectedHc(p.hc_number);
    setDetailPatient(p);
    setRoute('edit');
    setDetailLoading(true);
    fetchPatientDetail(p.hc_number)
      .then(full => { if (full) setDetailPatient(full); })
      .catch(() => {})
      .finally(() => setDetailLoading(false));
  }, []);

  const onSaveEdit = useCallback(async (hcNumber: string, data: WizardFormData) => {
    const nombres = [data.fname, data.mname].filter(Boolean).join(' ').trim();
    const apellidos = [data.lname, data.lname2].filter(Boolean).join(' ').trim();
    const payload = {
      fname: data.fname,
      mname: data.mname,
      lname: data.lname,
      lname2: data.lname2,
      fecha_nacimiento: data.fecha_nac,
      sexo: data.sexo,
      celular: data.telefono,
      telefono_alt: data.telefono_alt,
      afiliacion: data.afiliacion,
      ciudad: data.ciudad,
      email: data.email,
      direccion: data.direccion,
      medico_tratante_id: data.medico,
      sede_principal: data.sede,
    };
    await updatePatient(hcNumber, payload);
    const patch = {
      fname: data.fname,
      mname: data.mname,
      lname: data.lname,
      lname2: data.lname2,
      nombres,
      apellidos,
      full_name: `${apellidos} ${nombres}`.trim(),
      display_name: `${nombres} ${apellidos}`.trim(),
      sexo: data.sexo,
      cedula: data.cedula || hcNumber,
      telefono: data.telefono || '',
      telefono_alt: data.telefono_alt || null,
      email: data.email || null,
      direccion: data.direccion,
      ciudad: data.ciudad,
      sede: data.sede,
      medico: data.medico,
      fecha_nac: data.fecha_nac || '',
      afiliacion: data.afiliacion,
      aseguradora: data.aseguradora || null,
      poliza: data.poliza || null,
      titular: data.titular || null,
      alerta: data.alerta || null,
    };
    setPatients(list => list.map(p => p.hc_number === hcNumber ? { ...p, ...patch } : p));
    if (detailPatient?.hc_number === hcNumber) {
      setDetailPatient(prev => prev ? { ...prev, ...patch } : prev);
    }
    showToast('Paciente actualizado correctamente', 'mdi-account-check');
    setRoute('detail');
  }, [detailPatient, showToast]);

  const onPatientCreated = useCallback(async (localPatient: Patient) => {
    try {
      const created = await createPatient({
        hc_number: localPatient.hc_number,
        nombres: localPatient.nombres,
        apellidos: localPatient.apellidos,
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
      const newP = list.find(p => p.hc_number === created.hc_number || p.hc_number === localPatient.hc_number);
      if (newP) openPatient(newP.id);
      else goList();
    } catch (err: any) {
      showToast(err.message || 'Error al crear paciente', 'mdi-alert-circle-outline', 'err');
    }
  }, [showToast, openPatient, goList]);

  return (
    <div className="pac-root">
      {/* Search + action bar — list view only */}
      {route === 'list' && (
        <div className="pac-searchbar">
          <div className="psb-search">
            <i className="mdi mdi-magnify" />
            <input
              type="search"
              placeholder="Buscar por nombre, cédula, HC, teléfono…"
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
            {search && (
              <button className="psb-clear" onClick={() => setSearch('')}><i className="mdi mdi-close" /></button>
            )}
          </div>
          <button className="wbtn primary" onClick={goCreate}>
            <i className="mdi mdi-account-plus" />
            Nuevo paciente
          </button>
        </div>
      )}

      {/* Views */}
      {route === 'list' && (
        <ListView
          patients={patients}
          loading={loading}
          search={search}
          setSearch={setSearch}
          catalogos={catalogos}
          onOpen={openPatient}
          onAgendar={onAgendar}
          onEditar={onEditar}
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
          onOpenCRM={openSolicitudCrm}
          onEditar={onEditar}
          onNuevaSolicitud={openSolicitudesForPatient}
        />
      )}
      {route === 'detail' && !detailPatient && (
        <div className="page"><div className="page-inner list-empty">
          <i className="mdi mdi-account-search-outline" /><h3>Cargando paciente…</h3>
        </div></div>
      )}

      {route === 'create' && (
        <WizardView
          mode="create"
          patients={patients}
          catalogos={catalogos}
          onCancel={goList}
          onCreate={onPatientCreated}
          onOpenExisting={openPatient}
        />
      )}

      {route === 'edit' && detailPatient && (
        <WizardView
          mode="edit"
          patients={patients}
          catalogos={catalogos}
          initialPatient={detailPatient}
          onCancel={() => setRoute('detail')}
          onCreate={onPatientCreated}
          onUpdate={onSaveEdit}
          onOpenExisting={openPatient}
        />
      )}

      <Toast toast={toast} />
    </div>
  );
}
