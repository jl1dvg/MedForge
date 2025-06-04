// kanban_tabs.js

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.tab-kanban').forEach(tab => {
        tab.addEventListener('click', function () {
            // Quita clase activa de todos y agrégala solo al seleccionado
            document.querySelectorAll('.tab-kanban').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Limpiar columnas y resumen antes de renderizar
            document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');
            if (document.getElementById('kanban-summary')) {
                document.getElementById('kanban-summary').remove();
            }

            const tipo = this.dataset.tipo; // "cirugia", "consulta", "examen", etc.

            // Llama función de render del archivo correspondiente
            if (tipo === 'cirugia' && typeof renderKanbanCirugia === "function") {
                renderKanbanCirugia();
            } else if (tipo === 'consulta' && typeof renderKanbanConsulta === "function") {
                renderKanbanConsulta();
            } else if (tipo === 'examen' && typeof renderKanbanExamen === "function") {
                renderKanbanExamen();
            } else {
                // Por defecto, usa render general
                renderKanban();
            }
        });
    });
});