export function showToast(message, isSuccess = true, timeoutMs = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.style.minWidth = '250px';
    toast.style.marginBottom = '10px';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
    `;

    container.appendChild(toast);
    if (Number.isFinite(timeoutMs) && timeoutMs > 0) {
        setTimeout(() => toast.remove(), timeoutMs);
    }
}
