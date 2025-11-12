(function () {
    const root = document.getElementById('code-packages-root');
    if (!root) {
        return;
    }

    const state = {
        packages: [],
        filteredPackages: [],
        currentPackage: null,
        items: [],
        loading: false,
    };

    const elements = {
        list: document.getElementById('package-list'),
        searchInput: document.getElementById('package-search-input'),
        refreshBtn: document.getElementById('package-refresh-btn'),
        newBtn: document.getElementById('package-new-btn'),
        form: document.getElementById('package-form'),
        id: document.getElementById('package-id'),
        name: document.getElementById('package-name'),
        category: document.getElementById('package-category'),
        active: document.getElementById('package-active'),
        description: document.getElementById('package-description'),
        addCustomBtn: document.getElementById('package-add-custom'),
        addCodeBtn: document.getElementById('package-add-code'),
        itemsBody: document.getElementById('package-items-body'),
        itemsTable: document.getElementById('package-items-table'),
        total: document.getElementById('package-total'),
        saveBtn: document.getElementById('package-save-btn'),
        resetBtn: document.getElementById('package-reset-btn'),
        deleteBtn: document.getElementById('package-delete-btn'),
        codeModal: document.getElementById('code-search-modal'),
        codeSearchInput: document.getElementById('code-search-input'),
        codeSearchBtn: document.getElementById('code-search-btn'),
        codeResults: document.getElementById('code-search-results'),
    };

    const endpoints = {
        list: '/codes/api/packages',
        show: (id) => `/codes/api/packages/${id}`,
        save: (id) => (id ? `/codes/api/packages/${id}` : '/codes/api/packages'),
        delete: (id) => `/codes/api/packages/${id}/delete`,
        codeSearch: '/codes/api/search',
    };

    let sortableInstance = null;
    const bootstrapModal = window.bootstrap && elements.codeModal ? new window.bootstrap.Modal(elements.codeModal) : null;

    function toast(type, message) {
        const text = message || 'Operación realizada';
        if (window.toastr && typeof window.toastr[type] === 'function') {
            window.toastr[type](text);
        } else {
            const title = type === 'success' ? '✔' : type === 'error' ? '✖' : 'ℹ️';
            // eslint-disable-next-line no-alert
            alert(`${title} ${text}`);
        }
    }

    function parseInitialPackages() {
        const payload = root.getAttribute('data-initial-packages');
        if (!payload) {
            return [];
        }

        try {
            const parsed = JSON.parse(payload);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('No se pudieron leer los paquetes iniciales', error);
            return [];
        }
    }

    function setPackages(list) {
        state.packages = Array.isArray(list) ? list : [];
        filterPackages(elements.searchInput ? elements.searchInput.value : '');
    }

    function filterPackages(term) {
        const normalized = term ? term.toLowerCase() : '';
        if (!normalized) {
            state.filteredPackages = [...state.packages];
        } else {
            state.filteredPackages = state.packages.filter((pkg) => {
                const haystack = `${pkg.name ?? ''} ${pkg.description ?? ''}`.toLowerCase();
                return haystack.includes(normalized);
            });
        }

        renderPackageList();
    }

    function formatCurrency(value) {
        const number = Number.isFinite(value) ? value : 0;
        return new Intl.NumberFormat('es-EC', { style: 'currency', currency: 'USD' }).format(number);
    }

    function renderPackageList() {
        if (!elements.list) {
            return;
        }

        elements.list.innerHTML = '';

        if (!state.filteredPackages.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted text-center py-3';
            empty.textContent = 'Sin paquetes';
            elements.list.appendChild(empty);
            return;
        }

        state.filteredPackages.forEach((pkg) => {
            const card = document.createElement('div');
            card.className = 'code-package-card';
            if (state.currentPackage && state.currentPackage.id === pkg.id) {
                card.classList.add('active');
            }

            card.innerHTML = `
                <h6>${pkg.name ?? 'Sin título'}</h6>
                <small>${pkg.category ?? 'General'} · ${pkg.items_count ?? pkg.total_items ?? 0} ítems</small>
                <div class="fw-600">${formatCurrency(pkg.total_amount ?? pkg.computed_total ?? 0)}</div>
            `;

            card.addEventListener('click', () => {
                loadPackage(pkg.id);
            });

            elements.list.appendChild(card);
        });
    }

    function resetForm() {
        state.currentPackage = null;
        state.items = [];
        if (elements.form) {
            elements.form.reset();
        }
        if (elements.id) {
            elements.id.value = '';
        }
        updateItemsTable();
        updateTotals();
        updateDeleteButton();
    }

    function populateForm(pkg) {
        if (!pkg) {
            resetForm();
            return;
        }

        state.currentPackage = pkg;
        state.items = Array.isArray(pkg.items) ? pkg.items.map((item) => ({
            id: item.id ?? null,
            description: item.description ?? '',
            quantity: Number(item.quantity ?? 1),
            unit_price: Number(item.unit_price ?? 0),
            discount_percent: Number(item.discount_percent ?? 0),
            code_id: item.code_id ?? null,
        })) : [];

        if (elements.id) elements.id.value = pkg.id ?? '';
        if (elements.name) elements.name.value = pkg.name ?? '';
        if (elements.category) elements.category.value = pkg.category ?? '';
        if (elements.description) elements.description.value = pkg.description ?? '';
        if (elements.active) elements.active.value = (pkg.active ?? 1).toString();

        updateItemsTable();
        updateTotals();
        updateDeleteButton();
    }

    function updateDeleteButton() {
        if (!elements.deleteBtn) {
            return;
        }
        elements.deleteBtn.disabled = !state.currentPackage;
    }

    function addItem(item = {}) {
        const entry = {
            description: item.description ?? '',
            quantity: Number(item.quantity ?? 1) || 1,
            unit_price: Number(item.unit_price ?? 0) || 0,
            discount_percent: Number(item.discount_percent ?? 0) || 0,
            code_id: item.code_id ?? null,
        };
        state.items.push(entry);
        updateItemsTable();
        updateTotals();
    }

    function removeItem(index) {
        state.items.splice(index, 1);
        updateItemsTable();
        updateTotals();
    }

    function updateItemsTable() {
        if (!elements.itemsBody) {
            return;
        }

        elements.itemsBody.innerHTML = '';

        if (!state.items.length) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'text-center text-muted';
            emptyRow.innerHTML = '<td colspan="7">Agrega ítems para comenzar</td>';
            elements.itemsBody.appendChild(emptyRow);
            return;
        }

        state.items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-muted cursor-move"><i class="mdi mdi-drag"></i></td>
                <td><input type="text" class="form-control form-control-sm" value="${item.description}" data-field="description"></td>
                <td><input type="number" class="form-control form-control-sm text-center" step="0.01" min="0.01" value="${item.quantity}" data-field="quantity"></td>
                <td><input type="number" class="form-control form-control-sm text-center" step="0.01" value="${item.unit_price}" data-field="unit_price"></td>
                <td><input type="number" class="form-control form-control-sm text-center" step="0.01" min="0" max="100" value="${item.discount_percent}" data-field="discount_percent"></td>
                <td class="text-end">${formatCurrency(calculateLineTotal(item))}</td>
                <td class="text-end">
                    <button class="btn btn-xs btn-outline-danger" data-action="remove"><i class="mdi mdi-delete-outline"></i></button>
                </td>
            `;

            row.querySelectorAll('input[data-field]').forEach((input) => {
                input.addEventListener('input', (event) => {
                    const field = input.getAttribute('data-field');
                    let value = event.target.value;
                    if (field === 'quantity' || field === 'unit_price' || field === 'discount_percent') {
                        value = parseFloat(value) || 0;
                    }
                    state.items[index][field] = value;
                    updateItemsTable();
                    updateTotals();
                });
            });

            const removeBtn = row.querySelector('button[data-action="remove"]');
            if (removeBtn) {
                removeBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    removeItem(index);
                });
            }

            elements.itemsBody.appendChild(row);
        });

        initSortable();
    }

    function initSortable() {
        if (!elements.itemsBody || typeof Sortable === 'undefined') {
            return;
        }

        if (sortableInstance) {
            sortableInstance.destroy();
        }

        sortableInstance = new Sortable(elements.itemsBody, {
            handle: '.mdi-drag',
            animation: 150,
            onEnd: (event) => {
                const { oldIndex, newIndex } = event;
                if (oldIndex === newIndex || oldIndex == null || newIndex == null) {
                    return;
                }
                const moved = state.items.splice(oldIndex, 1)[0];
                state.items.splice(newIndex, 0, moved);
                updateItemsTable();
            },
        });
    }

    function calculateLineTotal(item) {
        const quantity = Number(item.quantity || 0);
        const price = Number(item.unit_price || 0);
        const discountPercent = Number(item.discount_percent || 0);
        let total = quantity * price;
        total -= total * (discountPercent / 100);
        return total;
    }

    function updateTotals() {
        if (!elements.total) {
            return;
        }

        const total = state.items.reduce((sum, item) => sum + calculateLineTotal(item), 0);
        elements.total.textContent = formatCurrency(total);
    }

    async function loadPackage(id) {
        if (!id) {
            resetForm();
            return;
        }

        try {
            const response = await fetch(endpoints.show(id));
            const payload = await response.json();
            if (payload && payload.ok) {
                populateForm(payload.data);
                highlightCurrentInList(id);
            } else {
                toast('error', payload?.error || 'No se pudo cargar el paquete');
            }
        } catch (error) {
            console.error(error);
            toast('error', 'No se pudo cargar el paquete');
        }
    }

    function highlightCurrentInList(id) {
        if (!id) {
            state.currentPackage = null;
        } else {
            state.currentPackage = state.packages.find((pkg) => Number(pkg.id) === Number(id)) || state.currentPackage;
        }
        renderPackageList();
    }

    async function refreshPackages() {
        if (state.loading) {
            return;
        }
        state.loading = true;
        try {
            const response = await fetch(endpoints.list);
            const payload = await response.json();
            if (payload && payload.ok) {
                setPackages(payload.data || []);
            } else {
                toast('error', payload?.error || 'No se pudo cargar la lista');
            }
        } catch (error) {
            console.error(error);
            toast('error', 'No se pudo cargar la lista');
        } finally {
            state.loading = false;
        }
    }

    function collectPayload() {
        const payload = {
            id: elements.id?.value ? Number(elements.id.value) : null,
            name: elements.name?.value?.trim() || '',
            category: elements.category?.value?.trim() || null,
            description: elements.description?.value?.trim() || null,
            active: elements.active?.value === '0' ? 0 : 1,
            items: state.items,
        };
        return payload;
    }

    async function savePackage() {
        const payload = collectPayload();
        if (!payload.name) {
            toast('error', 'El nombre es obligatorio');
            return;
        }
        if (!payload.items.length) {
            toast('error', 'Agrega al menos un ítem');
            return;
        }

        const id = payload.id;
        try {
            const response = await fetch(endpoints.save(id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!result || result.ok === false) {
                throw new Error(result?.error || 'No se pudo guardar el paquete');
            }

            toast('success', 'Paquete guardado');
            populateForm(result.data);
            await refreshPackages();
        } catch (error) {
            toast('error', error.message || 'Error al guardar');
        }
    }

    async function deletePackage() {
        if (!state.currentPackage || !state.currentPackage.id) {
            return;
        }

        const confirmed = window.confirm('¿Eliminar este paquete de forma permanente?');
        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(endpoints.delete(state.currentPackage.id), { method: 'POST' });
            const payload = await response.json();
            if (payload && payload.ok) {
                toast('success', 'Paquete eliminado');
                resetForm();
                await refreshPackages();
            } else {
                throw new Error(payload?.error || 'No se pudo eliminar');
            }
        } catch (error) {
            toast('error', error.message || 'No se pudo eliminar');
        }
    }

    async function searchCodes() {
        const term = elements.codeSearchInput.value.trim();
        if (!term) {
            toast('info', 'Ingresa un término para buscar');
            return;
        }

        try {
            const url = new URL(endpoints.codeSearch, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url.toString());
            const payload = await response.json();
            if (payload && payload.ok) {
                renderCodeResults(payload.data || []);
            } else {
                throw new Error(payload?.error || 'No se pudo buscar');
            }
        } catch (error) {
            toast('error', error.message || 'No se pudo buscar');
        }
    }

    function renderCodeResults(results) {
        elements.codeResults.innerHTML = '';
        if (!results.length) {
            const row = document.createElement('tr');
            row.className = 'text-center text-muted';
            row.innerHTML = '<td colspan="4">Sin coincidencias</td>';
            elements.codeResults.appendChild(row);
            return;
        }

        results.forEach((code) => {
            const price = Number(code.valor_facturar_nivel1 ?? code.valor_facturar_nivel2 ?? code.valor_facturar_nivel3 ?? 0);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${code.codigo}</strong></td>
                <td>${code.descripcion ?? ''}</td>
                <td class="text-end">${formatCurrency(price)}</td>
                <td class="text-end">
                    <button class="btn btn-xs btn-primary"><i class="mdi mdi-plus"></i></button>
                </td>
            `;
            const btn = row.querySelector('button');
            if (btn) {
                btn.addEventListener('click', () => {
                    addItem({
                        description: `${code.codigo} - ${code.descripcion ?? ''}`.trim(),
                        quantity: 1,
                        unit_price: price,
                        code_id: code.id,
                    });
                    if (bootstrapModal) {
                        bootstrapModal.hide();
                    }
                });
            }
            elements.codeResults.appendChild(row);
        });
    }

    function bindEvents() {
        if (elements.searchInput) {
            elements.searchInput.addEventListener('input', (event) => {
                filterPackages(event.target.value);
            });
        }

        if (elements.refreshBtn) {
            elements.refreshBtn.addEventListener('click', () => {
                refreshPackages();
            });
        }

        if (elements.newBtn) {
            elements.newBtn.addEventListener('click', () => {
                resetForm();
                highlightCurrentInList(null);
            });
        }

        if (elements.addCustomBtn) {
            elements.addCustomBtn.addEventListener('click', (event) => {
                event.preventDefault();
                addItem({ description: '', quantity: 1, unit_price: 0 });
            });
        }

        if (elements.addCodeBtn) {
            elements.addCodeBtn.addEventListener('click', (event) => {
                event.preventDefault();
                if (bootstrapModal) {
                    elements.codeSearchInput.value = '';
                    elements.codeResults.innerHTML = '<tr class="text-center text-muted"><td colspan="4">Escribe para buscar</td></tr>';
                    bootstrapModal.show();
                    setTimeout(() => elements.codeSearchInput.focus(), 150);
                }
            });
        }

        if (elements.saveBtn) {
            elements.saveBtn.addEventListener('click', (event) => {
                event.preventDefault();
                savePackage();
            });
        }

        if (elements.resetBtn) {
            elements.resetBtn.addEventListener('click', (event) => {
                event.preventDefault();
                resetForm();
                highlightCurrentInList(null);
            });
        }

        if (elements.deleteBtn) {
            elements.deleteBtn.addEventListener('click', (event) => {
                event.preventDefault();
                deletePackage();
            });
        }

        if (elements.codeSearchBtn) {
            elements.codeSearchBtn.addEventListener('click', (event) => {
                event.preventDefault();
                searchCodes();
            });
        }

        if (elements.codeSearchInput) {
            elements.codeSearchInput.addEventListener('keyup', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchCodes();
                }
            });
        }
    }

    function init() {
        const initial = parseInitialPackages();
        setPackages(initial);
        resetForm();
        bindEvents();
    }

    init();
})();
