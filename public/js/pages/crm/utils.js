'use strict';

const htmlEscapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };

export function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value).replace(/[&<>"']/g, (match) => htmlEscapeMap[match]);
}

export function titleize(value) {
    if (!value) {
        return '';
    }
    return value
        .toString()
        .replace(/_/g, ' ')
        .split(/\s+/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export function parseDate(value) {
    if (!value) {
        return null;
    }
    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value, withTime) {
    const date = parseDate(value);
    if (!date) {
        return '-';
    }
    try {
        if (withTime) {
            return new Intl.DateTimeFormat('es-EC', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
        }
        return new Intl.DateTimeFormat('es-EC', { dateStyle: 'medium' }).format(date);
    } catch (error) {
        return date.toLocaleString();
    }
}

export function formatDateInput(value) {
    const date = parseDate(value);
    if (!date) {
        return '';
    }
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

export function pickValue(...values) {
    for (const value of values) {
        if (value === null || value === undefined) {
            continue;
        }
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed !== '') {
                return trimmed;
            }
            continue;
        }
        if (value !== '') {
            return value;
        }
    }
    return '';
}

export function buildPatientName(patient) {
    if (!patient) {
        return '';
    }
    const direct = pickValue(patient.name, patient.full_name);
    if (direct) {
        return direct;
    }
    const parts = [
        patient.first_name,
        patient.last_name,
        patient.fname,
        patient.mname,
        patient.lname,
        patient.lname2,
    ]
        .map((part) => (typeof part === 'string' ? part.trim() : part))
        .filter((part) => part);
    return parts.length ? parts.join(' ').replace(/\s+/g, ' ').trim() : '';
}

export function limitText(value, maxLength) {
    if (!value) {
        return '';
    }
    if (value.length <= maxLength) {
        return value;
    }
    return `${value.slice(0, maxLength - 1)}…`;
}

export function formatCurrency(value) {
    const amount = Number.isFinite(value) ? value : 0;
    try {
        return new Intl.NumberFormat('es-EC', { style: 'currency', currency: 'USD' }).format(amount);
    } catch (error) {
        return `$${amount.toFixed(2)}`;
    }
}

export function showToast(message, status) {
    let type = 'error';
    let text = typeof message === 'string' ? message : 'Ocurrió un error inesperado';

    if (typeof status === 'boolean') {
        type = status ? 'success' : 'error';
    } else if (typeof message === 'string' && typeof status === 'string') {
        type = message;
        text = status;
    }

    const method = type === 'success'
        ? 'success'
        : type === 'warning'
            ? 'warning'
            : type === 'info'
                ? 'info'
                : 'error';
    if (window.toastr && typeof window.toastr[method] === 'function') {
        window.toastr[method](text);
    } else if (window.Swal && window.Swal.fire) {
        window.Swal.fire(method === 'success' ? 'Éxito' : 'Aviso', text, method);
    } else {
        // eslint-disable-next-line no-alert
        alert(`${method === 'success' ? '✔' : method === 'warning' ? '⚠️' : '✖'} ${text}`);
    }
}

export function clearContainer(container) {
    while (container && container.firstChild) {
        container.removeChild(container.firstChild);
    }
}

export function setTextContent(element, value, fallback = '—') {
    if (!element) {
        return;
    }
    const display = value === null || value === undefined || value === '' ? fallback : value;
    element.textContent = display;
}

export function createStatusSelect(options, value) {
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm';
    const validOptions = Array.isArray(options) && options.length ? options : [];
    validOptions.forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = titleize(optionValue);
        select.appendChild(option);
    });
    if (value && validOptions.includes(value)) {
        select.value = value;
    }
    return select;
}

export function appendLine(container, text, iconClass) {
    if (!text) {
        return;
    }
    const span = document.createElement('span');
    span.className = 'd-block small text-muted';
    if (iconClass) {
        const icon = document.createElement('i');
        icon.className = `${iconClass} me-1`;
        span.appendChild(icon);
    }
    span.appendChild(document.createTextNode(text));
    container.appendChild(span);
}

export function setPlaceholderOptions(select) {
    if (!select) {
        return;
    }
    const currentPlaceholder = select.getAttribute('data-placeholder') || (select.options[0] ? select.options[0].textContent : 'Selecciona');
    clearContainer(select);
    const option = document.createElement('option');
    option.value = '';
    option.textContent = currentPlaceholder;
    select.appendChild(option);
}

export function serializeNumber(value) {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
        return null;
    }
    const parsed = Number(trimmed);
    return Number.isNaN(parsed) ? null : parsed;
}

export function normalizeHcNumber(value) {
    return String(value || '').trim().toUpperCase();
}
