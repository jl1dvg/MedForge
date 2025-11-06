import { poblarAfiliacionesUnicas, poblarDoctoresUnicos } from './kanban/filtros.js';
import { initKanban } from './kanban/index.js';
import { setCrmOptions } from './kanban/crmPanel.js';
import { showToast } from './kanban/toast.js';

document.addEventListener('DOMContentLoaded', () => {
    const realtimeConfig = window.MEDF_PusherConfig || {};
    const rawAutoDismiss = Number(realtimeConfig.auto_dismiss_seconds);
    const autoDismissSeconds = Number.isFinite(rawAutoDismiss) && rawAutoDismiss >= 0 ? rawAutoDismiss : null;
    const toastDurationMs = autoDismissSeconds === null
        ? 4000
        : autoDismissSeconds === 0
            ? 0
            : autoDismissSeconds * 1000;

    const maybeShowDesktopNotification = (title, body) => {
        if (!realtimeConfig.desktop_notifications || typeof window === 'undefined' || !('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }

        if (Notification.permission !== 'granted') {
            return;
        }

        const notification = new Notification(title, { body });
        if (autoDismissSeconds && autoDismissSeconds > 0) {
            setTimeout(() => notification.close(), autoDismissSeconds * 1000);
        }
    };

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
            .then(async (response) => {
                if (!response.ok) {
                    let serverMsg = '';
                    try {
                        const data = await response.json();
                        serverMsg = data?.error || JSON.stringify(data);
                    } catch (_) {
                        serverMsg = await response.text();
                    }
                    const msg = serverMsg ? `No se pudo cargar el tablero. Servidor: ${serverMsg}` : 'No se pudo cargar el tablero';
                    throw new Error(msg);
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

                if (options.crm) {
                    setCrmOptions(options.crm);
                } else {
                    setCrmOptions({});
                }

                initKanban(window.__solicitudesKanban);
            })
            .catch(error => {
                console.error('❌ Error cargando Kanban:', error);
                showToast(error?.message || 'No se pudo cargar el tablero de solicitudes', false);
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

    if (realtimeConfig.enabled) {
        if (typeof Pusher === 'undefined') {
            console.warn('Pusher no está disponible. Verifica que el script se haya cargado correctamente.');
        } else if (!realtimeConfig.key) {
            console.warn('No se configuró la APP Key de Pusher.');
        } else {
            const options = { forceTLS: true };
            if (realtimeConfig.cluster) {
                options.cluster = realtimeConfig.cluster;
            }

            const pusher = new Pusher(realtimeConfig.key, options);
            const channelName = realtimeConfig.channel || 'solicitudes-kanban';
            const eventName = realtimeConfig.event || 'nueva-solicitud';

            const channel = pusher.subscribe(channelName);
            channel.bind(eventName, data => {
                const nombre = data?.nombre || data?.hc_number || 'Paciente sin nombre';
                const mensaje = `🆕 Nueva solicitud: ${nombre}`;
                showToast(mensaje, true, toastDurationMs);
                maybeShowDesktopNotification('Nueva solicitud', mensaje);
                window.aplicarFiltros();
            });
        }
    }

    cargarKanban();
});
