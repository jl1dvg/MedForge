export function inicializarModalDetalles() {
    document.querySelectorAll('.kanban-board').forEach(board => {
        board.addEventListener('click', event => {
            const card = event.target.closest('.kanban-card.view-details');
            if (!card) {
                return;
            }

            document.querySelectorAll('.kanban-card').forEach(element => element.classList.remove('active'));
            card.classList.add('active');

            const hc = card.dataset.hc;
            const formId = card.dataset.form;
            if (!hc || !formId) {
                console.warn('⚠️ No se encontró hc_number o form_id en la tarjeta seleccionada');
                return;
            }

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

            fetch(`/solicitudes/prefactura?hc_number=${encodeURIComponent(hc)}&form_id=${encodeURIComponent(formId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('No se encontró la prefactura');
                    }
                    return response.text();
                })
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    console.error('❌ Error cargando prefactura:', error);
                    content.innerHTML = '<p class="text-danger mb-0">No se pudo cargar la información de la solicitud.</p>';
                });

            modalElement.addEventListener('hidden.bs.modal', () => {
                document.querySelectorAll('.kanban-card').forEach(element => element.classList.remove('active'));
            }, { once: true });
        });
    });
}
