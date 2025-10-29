// js/kanban/filtros.js
export function poblarAfiliacionesUnicas(data) {
    const select = document.getElementById('kanbanAfiliacionFilter');
    if (!select) return;

    // Limpia el select
    select.innerHTML = '<option value="">Todas</option>';

    // Detectar si son strings o objetos
    let afiliaciones = [];
    if (Array.isArray(data) && data.length) {
        if (typeof data[0] === 'string') {
            afiliaciones = data; // viene del backend como ["IESS", "MSP"]
        } else if (typeof data[0] === 'object') {
            afiliaciones = [...new Set(data.map(d => d.afiliacion).filter(Boolean))];
        }
    }

    afiliaciones.sort().forEach(af => {
        const option = document.createElement('option');
        option.value = af;
        option.textContent = af;
        select.appendChild(option);
    });
}

export function poblarDoctoresUnicos(data) {
    const select = document.getElementById('kanbanDoctorFilter');
    if (!select) return;
    select.innerHTML = '<option value="">Todos</option>';

    let doctores = [];
    if (Array.isArray(data) && data.length) {
        if (typeof data[0] === 'string') {
            doctores = data; // viene del backend como ["Dr. Polit", "Dra. Ortega"]
        } else if (typeof data[0] === 'object') {
            doctores = [...new Set(data.map(d => d.doctor).filter(Boolean))];
        }
    }

    doctores.sort().forEach(doc => {
        const option = document.createElement('option');
        option.value = doc;
        option.textContent = doc;
        select.appendChild(option);
    });
}

export function filtrarSolicitudes(data, {afiliacion, doctor, fechaTexto, estadoSemaforo}) {
    let fechaInicio = null, fechaFin = null;

    if (fechaTexto?.includes(' - ')) {
        const [inicio, fin] = fechaTexto.split(' - ');
        fechaInicio = moment(inicio, 'DD-MM-YYYY').startOf('day');
        fechaFin = moment(fin, 'DD-MM-YYYY').endOf('day');
    }

    return data.filter(s => {
        const matchAfiliacion = !afiliacion || (s.afiliacion?.toLowerCase().includes(afiliacion));
        const matchDoctor = !doctor || s.doctor === doctor;
        const fecha = moment(s.fecha_creacion);
        const hoy = moment();
        const dias = hoy.diff(fecha, 'days');
        const estadoCalculado = dias <= 3 ? 'normal' : dias <= 7 ? 'pendiente' : 'urgente';
        const matchSemaforo = !estadoSemaforo || estadoSemaforo === estadoCalculado;
        const matchFecha = (!fechaInicio || fecha.isSameOrAfter(fechaInicio)) &&
            (!fechaFin || fecha.isSameOrBefore(fechaFin));
        return matchAfiliacion && matchDoctor && matchFecha && matchSemaforo;
    });
}