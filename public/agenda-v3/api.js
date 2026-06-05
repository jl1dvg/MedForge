/* MedForge Agendamiento V3 — API client
   Reemplaza data.js (mock). Expone window.AgendaAPI y un stub window.AG
   que se rellena con Object.assign() desde app.jsx tras cargar config. */
(function () {
  'use strict';

  const BASE  = (window.__MF__ && window.__MF__.apiBase) || '/api/v3/agenda';
  const CSRF  = (window.__MF__ && window.__MF__.csrf)
    || (document.querySelector('meta[name=csrf-token]') || {}).content
    || '';

  function headers() {
    return {
      'Content-Type'    : 'application/json',
      'Accept'          : 'application/json',
      'X-CSRF-TOKEN'    : CSRF,
      'X-Requested-With': 'XMLHttpRequest',
    };
  }

  /* ---------- shape helpers ---------- */
  function normalizeCita(c) {
    const isPP = c._source === 'pp';
    return {
      id            : (isPP ? 'P' : 'C') + c.id,
      _dbId         : c.id,
      fecha         : c.fecha,
      sede          : c.sede_id,
      medico        : c.medico_id,
      sala          : c.sala_id,
      tipo          : c.tipo_id,
      paciente      : c.paciente,
      hc            : c.hc_number || '',
      edad          : c.edad,
      afil          : c.afiliacion || '',
      tel           : c.tel || '',
      ini           : (c.hora_ini || '').substring(0, 5),
      fin           : (c.hora_fin || '').substring(0, 5),
      area          : c.area || '',
      dur           : c.dur_minutos || 20,
      estado        : c.estado,
      whatsapp      : c.whatsapp_estado || 'na',
      sobreturno    : !!c.sobreturno,
      horaLlegada   : c.hora_llegada      ? (c.hora_llegada).substring(0, 5)      : null,
      horaSala      : c.hora_sala          ? (c.hora_sala).substring(0, 5)          : null,
      horaConsulta  : c.hora_consulta      ? (c.hora_consulta).substring(0, 5)      : null,
      horaFin       : c.hora_fin_atencion  ? (c.hora_fin_atencion).substring(0, 5)  : null,
      notas         : c.notas || '',
      hcLlena       : !!c.hc_llena,
      hcData        : c.hc_data || null,
      _source       : c._source || 'v3',
      _readonly     : !!c._readonly,
    };
  }

  function normalizeBloqueo(b) {
    return {
      id    : 'b' + b.db_id,
      _dbId : b.db_id,
      scope : b.scope,
      ref   : b.ref,
      fecha : b.fecha,
      ini   : (b.ini || '').substring(0, 5),
      fin   : (b.fin || '').substring(0, 5),
      motivo: b.motivo,
      tipo  : b.tipo,
    };
  }

  /* ---------- API ---------- */
  const AgendaAPI = {

    async fetchConfig() {
      const r = await fetch(BASE + '/config', { headers: headers() });
      if (!r.ok) throw new Error('No se pudo cargar configuración de agenda');
      return r.json();
    },

    async fetchCitas(fecha, sedeId) {
      const p = new URLSearchParams({ fecha: fecha || new Date().toISOString().slice(0, 10) });
      if (sedeId) p.set('sede', sedeId);
      const r = await fetch(BASE + '/citas?' + p, { headers: headers() });
      if (!r.ok) throw new Error('No se pudieron cargar las citas');
      const data = await r.json();
      return (data.data || data).map(normalizeCita);
    },

    async fetchBloqueos(fecha) {
      const p = new URLSearchParams({ fecha: fecha || new Date().toISOString().slice(0, 10) });
      const r = await fetch(BASE + '/bloqueos?' + p, { headers: headers() });
      if (!r.ok) throw new Error('No se pudieron cargar los bloqueos');
      const data = await r.json();
      return (data.data || data).map(normalizeBloqueo);
    },

    async createCita(payload) {
      const r = await fetch(BASE + '/citas', {
        method : 'POST',
        headers: headers(),
        body   : JSON.stringify(payload),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al crear cita');
      return normalizeCita(data.data);
    },

    async updateCita(dbId, payload) {
      if (dbId === null || dbId === undefined) throw new Error('Cita de SigCenter — no editable en Agenda V3');
      const r = await fetch(BASE + '/citas/' + dbId, {
        method : 'PUT',
        headers: headers(),
        body   : JSON.stringify(payload),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al actualizar cita');
      return normalizeCita(data.data);
    },

    async avanzarCita(dbId, extra) {
      const r = await fetch(BASE + '/citas/' + dbId + '/avanzar', {
        method : 'POST',
        headers: headers(),
        body   : JSON.stringify(extra || {}),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al avanzar estado');
      return normalizeCita(data.data);
    },

    async cancelarCita(dbId) {
      const r = await fetch(BASE + '/citas/' + dbId, {
        method : 'DELETE',
        headers: headers(),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al cancelar cita');
      return data;
    },

    async finalizarConsulta(dbId, hcData) {
      const r = await fetch(BASE + '/citas/' + dbId + '/consulta', {
        method : 'POST',
        headers: headers(),
        body   : JSON.stringify({ hc_data: hcData }),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al guardar consulta');
      return normalizeCita(data.data);
    },

    async createBloqueo(payload) {
      const r = await fetch(BASE + '/bloqueos', {
        method : 'POST',
        headers: headers(),
        body   : JSON.stringify(payload),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al crear bloqueo');
      return normalizeBloqueo(data.data);
    },

    async deleteBloqueo(dbId) {
      const r = await fetch(BASE + '/bloqueos/' + dbId, {
        method : 'DELETE',
        headers: headers(),
      });
      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error || 'Error al eliminar bloqueo');
      return data;
    },
  };

  /* ---------- stub window.AG (relleno real viene de app.jsx) ---------- */
  var _toMin  = function (t) { var p = t.split(':'); return parseInt(p[0]) * 60 + parseInt(p[1]); };
  var _toHHMM = function (m) {
    return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
  };

  window.AG = {
    HOY          : new Date().toISOString().slice(0, 10),
    AREAS        : [],
    SEDES        : [],
    MEDICOS      : [],
    SALAS        : [],
    TIPOS        : [],
    HORARIOS     : [],
    BLOQUEOS     : [],
    CITAS        : [],
    ESTADOS      : [],
    AFILIACIONES : [],
    util         : { toMin: _toMin, toHHMM: _toHHMM },
  };

  window.AgendaAPI = AgendaAPI;
  window.toMin     = _toMin;
  window.toHHMM    = _toHHMM;
})();
