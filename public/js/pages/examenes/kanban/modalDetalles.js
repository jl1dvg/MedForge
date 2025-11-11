let prefacturaListenerAttached = false;

function cssEscape(value) {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(value);
    }

    return String(value).replace(/([ #;?%&,.+*~\':"!^$\[\]()=>|\/\\@])/g, '\\$1');
}

function highlightSelection({ cardId, rowId }) {
    document.querySelectorAll('.kanban-card').forEach(element => element.classList.remove('active'));
    document.querySelectorAll('#solicitudesTable tbody tr').forEach(row => row.classList.remove('table-active'));

    if (cardId) {
        const card = document.querySelector(`.kanban-card[data-id="${cssEscape(cardId)}"]`);
        if (card) {
            card.classList.add('active');
        }
    }

    if (rowId) {
        const row = document.querySelector(`#solicitudesTable tbody tr[data-id="${cssEscape(rowId)}"]`);
        if (row) {
            row.classList.add('table-active');
        }
    }
}

function resolverDataset(trigger) {
    const container = trigger.closest('[data-hc][data-form]') ?? trigger;
    const hc = trigger.dataset.hc || container?.dataset.hc || '';
    const formId = trigger.dataset.form || container?.dataset.form || '';
    const solicitudId = trigger.dataset.id || container?.dataset.id || '';

    return { hc, formId, solicitudId };
}

function abrirPrefactura({ hc, formId, solicitudId }) {
    if (!hc || !formId) {
        console.warn('⚠️ No se encontró hc_number o form_id en la selección actual');
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
        document.querySelectorAll('#solicitudesTable tbody tr').forEach(row => row.classList.remove('table-active'));
    }, { once: true });
}

function handlePrefacturaClick(event) {
    const trigger = event.target.closest('[data-prefactura-trigger]');
    if (!trigger) {
        return;
    }

    const { hc, formId, solicitudId } = resolverDataset(trigger);
    highlightSelection({ cardId: solicitudId, rowId: solicitudId });
    abrirPrefactura({ hc, formId, solicitudId });
}

export function inicializarModalDetalles() {
    if (prefacturaListenerAttached) {
        return;
    }

    prefacturaListenerAttached = true;
    document.addEventListener('click', handlePrefacturaClick);
}
