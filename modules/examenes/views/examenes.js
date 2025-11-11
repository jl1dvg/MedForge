import {poblarAfiliacionesUnicas, poblarDoctoresUnicos} from './js/kanban/filtros.js';
import {initKanban} from './js/kanban/index.js';

document.addEventListener('DOMContentLoaded', () => {
    function obtenerFiltros() {
        return {
            afiliacion: document.getElementById('kanbanAfiliacionFilter').value,
            doctor: document.getElementById('kanbanDoctorFilter').value,
            prioridad: document.getElementById('kanbanSemaforoFilter').value,
            fechaTexto: document.getElementById('kanbanDateFilter').value
        };
    }

    function cargarKanban(filtros = {}) {
        console.log('🔎 Filtros enviados:', filtros);
        fetch('/api/solicitudes/kanban_data.php', {
            method: 'POST', body: new URLSearchParams(filtros)
        })
            .then(res => res.json())
            .then(({data, options}) => {
                console.log('📥 Respuesta kanban_data:', {registros: (data || []).length, options});
                // 🧩 Poblar selects dinámicamente
                if (options) {
                    poblarAfiliacionesUnicas(options.afiliaciones || []);
                    poblarDoctoresUnicos(options.doctores || []);
                } else {
                    poblarAfiliacionesUnicas(data);
                    poblarDoctoresUnicos(data);
                }

                // 🖼️ Render del tablero
                initKanban(data);
                console.log('🧩 Renderizado completo con', (data || []).length, 'registros\n');
            })
            .catch(err => console.error('❌ Error cargando Kanban:', err));
    }

    // 🎛️ Listeners de filtros
    ['kanbanAfiliacionFilter', 'kanbanDoctorFilter', 'kanbanSemaforoFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => cargarKanban(obtenerFiltros()));
    });
// 📅 Filtro de fecha (daterangepicker)
    if (typeof $ !== 'undefined' && $.fn.daterangepicker) {
        $('#kanbanDateFilter').daterangepicker({
            locale: {
                format: 'DD-MM-YYYY', applyLabel: 'Aplicar', cancelLabel: 'Cancelar'
            }, autoUpdateInput: false
        })
            .on('apply.daterangepicker', function (ev, picker) {
                this.value = `${picker.startDate.format('DD-MM-YYYY')} - ${picker.endDate.format('DD-MM-YYYY')}`;
                cargarKanban(obtenerFiltros()); // usa tu función actual que consulta al backend
            })
            .on('cancel.daterangepicker', function () {
                this.value = '';
                cargarKanban(obtenerFiltros());
            });
    }
    // ▶️ Carga inicial
    cargarKanban();
});