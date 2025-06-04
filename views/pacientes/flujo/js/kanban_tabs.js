// kanban_tabs.js

document.addEventListener("DOMContentLoaded", function () {
    // Listener de click en tabs
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

            // Renderiza el tablero correcto según el tab
            renderTabActivo();
        });
    });

    // Renderiza el tab activo al cargar la página (primera vez)
    const activeTab = document.querySelector('.tab-kanban.active');
    if (activeTab) {
        renderTabActivo();
    }
});