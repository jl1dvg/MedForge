export function poblarAfiliacionesUnicas(data) {
    const select = document.getElementById('kanbanAfiliacionFilter');
    if (!select) return;

    select.innerHTML = '<option value="">Todas</option>';

    const valores = Array.isArray(data)
        ? data
        : [];

    const afiliaciones = Array.from(new Set(valores.map(item => {
        if (typeof item === 'string') return item;
        if (item && typeof item === 'object') return item.afiliacion;
        return null;
    }).filter(Boolean)));

    afiliaciones
        .sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }))
        .forEach(af => {
            const option = document.createElement('option');
            option.value = af;
            option.textContent = af;
            select.appendChild(option);
        });
}

export function poblarDoctoresUnicos(data) {
    const select = document.getElementById('kanbanDoctorFilter');
    if (!select) return;

    select.innerHTML = '<option value="">Todos</option>';

    const valores = Array.isArray(data)
        ? data
        : [];

    const doctores = Array.from(new Set(valores.map(item => {
        if (typeof item === 'string') return item;
        if (item && typeof item === 'object') return item.doctor;
        return null;
    }).filter(Boolean)));

    doctores
        .sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }))
        .forEach(doc => {
            const option = document.createElement('option');
            option.value = doc;
            option.textContent = doc;
            select.appendChild(option);
        });
}
