function resolveInitialValue(select) {
    return select.value || select.dataset.initialValue || '';
}

function normalizeOption(item, fallbackKey = '') {
    if (typeof item === 'string') {
        return { value: item, label: item };
    }
    if (!item || typeof item !== 'object') {
        return null;
    }

    const value = item.value ?? item[fallbackKey] ?? item.id ?? '';
    const label = item.label ?? item.nombre ?? item.name ?? item[fallbackKey] ?? value;
    if (value === '' && label === '') {
        return null;
    }

    return { value: String(value), label: String(label) };
}

export function poblarSelectOptions(selectId, options, allLabel = 'Todas', fallbackKey = '') {
    const select = document.getElementById(selectId);
    if (!select) return;

    const selected = resolveInitialValue(select);
    select.innerHTML = '';

    const normalized = (Array.isArray(options) ? options : [])
        .map(item => normalizeOption(item, fallbackKey))
        .filter(Boolean);

    const hasEmpty = normalized.some(option => option.value === '');
    if (!hasEmpty) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = allLabel;
        select.appendChild(option);
    }

    const seen = new Set();
    normalized.forEach(({ value, label }) => {
        if (seen.has(value)) return;
        seen.add(value);
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
    });

    if (selected && Array.from(select.options).some(option => option.value === selected)) {
        select.value = selected;
    }
}

export function poblarAfiliacionesUnicas(data) {
    const valores = Array.isArray(data) ? data : [];

    const afiliaciones = Array.from(new Set(valores.map(item => {
        if (typeof item === 'string') return item;
        if (item && typeof item === 'object') return item.afiliacion;
        return null;
    }).filter(Boolean)));

    poblarSelectOptions(
        'kanbanAfiliacionFilter',
        afiliaciones.sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' })),
        'Todas'
    );
}

export function poblarDoctoresUnicos(data) {
    const valores = Array.isArray(data) ? data : [];

    const doctores = Array.from(new Set(valores.map(item => {
        if (typeof item === 'string') return item;
        if (item && typeof item === 'object') return item.doctor;
        return null;
    }).filter(Boolean)));

    poblarSelectOptions(
        'kanbanDoctorFilter',
        doctores.sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' })),
        'Todos'
    );
}
