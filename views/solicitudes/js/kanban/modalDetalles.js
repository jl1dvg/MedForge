// modalDetalles.js
export function inicializarModalDetalles() {
    document.querySelectorAll('.kanban-board').forEach(board => {
        board.addEventListener('click', function (e) {
            const card = e.target.closest('.kanban-card.view-details');
            if (!card) return;

            // ✅ Marcar tarjeta activa
            document.querySelectorAll('.kanban-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');

            const hc = card.getAttribute('data-hc');
            const formId = card.getAttribute('data-form');
            if (!hc || !formId) {
                console.warn('⚠️ No se encontró hc_number o form_id en la tarjeta seleccionada');
                return;
            }

            // ✅ Mostrar modal inmediatamente con spinner
            const modalElement = document.getElementById('prefacturaModal');
            const modal = new bootstrap.Modal(modalElement);
            const content = document.getElementById('prefacturaContent');

            content.innerHTML = `
                <div class="d-flex align-items-center justify-content-center py-5">
                    <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
                    <strong>Cargando información...</strong>
                </div>
            `;

            modal.show();
            document.querySelector('body > .wrapper')?.setAttribute('inert', '');

            // ✅ Hacer fetch en segundo plano mientras se muestra el spinner
            fetch(`get_prefactura.php?hc_number=${hc}&form_id=${formId}`)
                .then(res => {
                    if (!res.ok) throw new Error("No se encontró la prefactura");
                    return res.text();
                })
                .then(html => {
                    content.innerHTML = html;
                    document.activeElement.blur();
                })
                .catch(err => {
                    content.innerHTML = '<p class="text-danger">Error al cargar detalles de prefactura.</p>';
                    console.error('❌ Error cargando prefactura:', err);
                });

            // ✅ Limpieza visual del modal al cerrarlo
            modalElement.addEventListener('hidden.bs.modal', () => {
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style = '';
                document.querySelector('body > .wrapper')?.removeAttribute('inert');
            });
        });
    });
}