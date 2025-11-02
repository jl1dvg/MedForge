import { poblarAfiliacionesUnicas, poblarDoctoresUnicos } from './kanban/filtros.js';
import { initKanban } from './kanban/index.js';
import { showToast } from './kanban/toast.js';

document.addEventListener('DOMContentLoaded', () => {
    const obtenerFiltros = () => ({
        afiliacion: document.getElementById('kanbanAfiliacionFilter')?.value ?? '',
        doctor: document.getElementById('kanbanDoctorFilter')?.value ?? '',
        prioridad: document.getElementById('kanbanSemaforoFilter')?.value ?? '',
        fechaTexto: document.getElementById('kanbanDateFilter')?.value ?? '',
    });

    const cargarKanban = (filtros = {}) => {
        console.groupCollapsed('%cKANBAN ▶ Filtros aplicados', 'color:#0b7285');
        console.log(filtros);
        console.groupEnd();

        return fetch('/solicitudes/kanban-data', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('No se pudo cargar el tablero');
                }
                return response.json();
            })
            .then(({ data = [], options = {} }) => {
                window.__solicitudesKanban = Array.isArray(data) ? data : [];

                if (options.afiliaciones) {
                    poblarAfiliacionesUnicas(options.afiliaciones);
                } else {
                    poblarAfiliacionesUnicas(window.__solicitudesKanban);
                }

                if (options.doctores) {
                    poblarDoctoresUnicos(options.doctores);
                } else {
                    poblarDoctoresUnicos(window.__solicitudesKanban);
                }

                initKanban(window.__solicitudesKanban);
            })
            .catch(error => {
                console.error('❌ Error cargando Kanban:', error);
                showToast('No se pudo cargar el tablero de solicitudes', false);
            });
    };

    window.aplicarFiltros = () => cargarKanban(obtenerFiltros());

    ['kanbanAfiliacionFilter', 'kanbanDoctorFilter', 'kanbanSemaforoFilter'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', () => window.aplicarFiltros());
        }
    });

    if (typeof $ !== 'undefined' && typeof $.fn.daterangepicker === 'function') {
        $('#kanbanDateFilter')
            .daterangepicker({
                locale: {
                    format: 'DD-MM-YYYY',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                },
                autoUpdateInput: false,
            })
            .on('apply.daterangepicker', function (ev, picker) {
                this.value = `${picker.startDate.format('DD-MM-YYYY')} - ${picker.endDate.format('DD-MM-YYYY')}`;
                window.aplicarFiltros();
            })
            .on('cancel.daterangepicker', function () {
                this.value = '';
                window.aplicarFiltros();
            });
    }

    if (typeof Pusher !== 'undefined') {
        const pusher = new Pusher('32ed6d21578f5bc44eef', {
            cluster: 'us2',
            encrypted: true,
        });

        const channel = pusher.subscribe('solicitudes-kanban');
        channel.bind('nueva-solicitud', data => {
            const nombre = data?.nombre || 'Paciente sin nombre';
            showToast(`🆕 Nueva solicitud: ${nombre}`);
            window.aplicarFiltros();
        });
    }

    cargarKanban();
});
