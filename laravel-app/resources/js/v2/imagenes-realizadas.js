const parseDateCell = (value) => {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (!match) {
        return null;
    }

    const [, day, month, year] = match;
    const iso = `${year}-${month}-${day}T00:00:00`;
    const timestamp = Date.parse(iso);
    return Number.isNaN(timestamp) ? null : timestamp;
};

const normalizeCellText = (row, columnIndex) => {
    const cell = row.children[columnIndex];
    if (!cell) {
        return '';
    }

    return (cell.textContent || '').replace(/\s+/g, ' ').trim();
};

class SimpleImagenesRealizadasTable {
    constructor(table, options = {}) {
        this.table = table;
        this.tbody = table.tBodies[0];
        this.options = {
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[1, 'desc']],
            orderableColumns: [1, 2, 3, 4, 5, 6, 7],
            labels: {
                search: 'Buscar:',
                length: 'Mostrar',
                records: 'registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                previous: 'Anterior',
                next: 'Siguiente',
            },
            ...options,
        };

        this.allRows = Array.from(this.tbody.querySelectorAll('tr[data-id]'));
        this.pageLength = Number(this.options.pageLength) || 25;
        this.currentPage = 1;
        this.searchTerm = '';
        this.sortColumn = Array.isArray(this.options.order?.[0]) ? Number(this.options.order[0][0]) : 1;
        this.sortDirection = Array.isArray(this.options.order?.[0]) ? String(this.options.order[0][1] || 'asc') : 'asc';
        this.externalFilter = null;
        this.drawListeners = [];

        this.buildUi();
        this.bindSorting();
        this.draw();
    }

    buildUi() {
        const container = document.createElement('div');
        container.className = 'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-3';

        const left = document.createElement('div');
        left.className = 'd-flex align-items-center gap-2';
        left.innerHTML = `
            <label class="d-inline-flex align-items-center gap-2 mb-0">
                <span>${this.options.labels.length}</span>
                <select class="form-select form-select-sm" data-simple-table-length></select>
                <span>${this.options.labels.records}</span>
            </label>
        `;

        const right = document.createElement('div');
        right.className = 'd-flex align-items-center gap-2 ms-auto';
        right.innerHTML = `
            <label class="d-inline-flex align-items-center gap-2 mb-0">
                <span>${this.options.labels.search}</span>
                <input type="search" class="form-control form-control-sm" data-simple-table-search>
            </label>
        `;

        container.appendChild(left);
        container.appendChild(right);

        const footer = document.createElement('div');
        footer.className = 'd-flex flex-wrap justify-content-between align-items-center gap-3 mt-3';
        footer.innerHTML = `
            <div class="small text-muted" data-simple-table-info></div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Paginación" data-simple-table-pagination></div>
        `;

        this.table.parentNode.insertBefore(container, this.table);
        this.table.parentNode.appendChild(footer);

        this.lengthSelect = container.querySelector('[data-simple-table-length]');
        this.searchInput = container.querySelector('[data-simple-table-search]');
        this.infoEl = footer.querySelector('[data-simple-table-info]');
        this.paginationEl = footer.querySelector('[data-simple-table-pagination]');

        this.options.lengthMenu.forEach((value) => {
            const option = document.createElement('option');
            option.value = String(value);
            option.textContent = String(value);
            if (value === this.pageLength) {
                option.selected = true;
            }
            this.lengthSelect.appendChild(option);
        });

        this.lengthSelect.addEventListener('change', () => {
            this.pageLength = Math.max(1, Number(this.lengthSelect.value) || 25);
            this.currentPage = 1;
            this.draw();
        });

        this.searchInput.addEventListener('input', () => {
            this.searchTerm = String(this.searchInput.value || '').trim().toLowerCase();
            this.currentPage = 1;
            this.draw();
        });
    }

    bindSorting() {
        const headers = Array.from(this.table.tHead?.rows?.[0]?.cells || []);
        headers.forEach((header, index) => {
            if (!this.options.orderableColumns.includes(index)) {
                return;
            }

            header.style.cursor = 'pointer';
            header.dataset.sortable = 'true';
            header.addEventListener('click', () => {
                if (this.sortColumn === index) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = index;
                    this.sortDirection = 'asc';
                }
                this.currentPage = 1;
                this.draw();
            });
        });
    }

    setExternalFilter(callback) {
        this.externalFilter = typeof callback === 'function' ? callback : null;
        this.currentPage = 1;
        this.draw();
    }

    onDraw(callback) {
        if (typeof callback === 'function') {
            this.drawListeners.push(callback);
        }
    }

    getFilteredRows() {
        return this.allRows.filter((row) => {
            if (this.externalFilter && !this.externalFilter(row)) {
                return false;
            }

            if (!this.searchTerm) {
                return true;
            }

            const text = (row.textContent || '').replace(/\s+/g, ' ').toLowerCase();
            return text.includes(this.searchTerm);
        });
    }

    sortRows(rows) {
        const columnIndex = this.sortColumn;
        const direction = this.sortDirection === 'desc' ? -1 : 1;

        return rows.slice().sort((rowA, rowB) => {
            const dateA = columnIndex === 1 ? parseDateCell(normalizeCellText(rowA, columnIndex)) : null;
            const dateB = columnIndex === 1 ? parseDateCell(normalizeCellText(rowB, columnIndex)) : null;

            if (dateA !== null && dateB !== null) {
                return (dateA - dateB) * direction;
            }

            const valueA = normalizeCellText(rowA, columnIndex);
            const valueB = normalizeCellText(rowB, columnIndex);
            return valueA.localeCompare(valueB, 'es', { sensitivity: 'base', numeric: true }) * direction;
        });
    }

    getOrderedFilteredRows() {
        return this.sortRows(this.getFilteredRows());
    }

    getCurrentPageRows() {
        const ordered = this.getOrderedFilteredRows();
        const start = (this.currentPage - 1) * this.pageLength;
        return ordered.slice(start, start + this.pageLength);
    }

    rows(options = {}) {
        let rows = this.getOrderedFilteredRows();
        if (options.page === 'current') {
            rows = this.getCurrentPageRows();
        }

        return {
            nodes() {
                return {
                    toArray() {
                        return rows.slice();
                    },
                };
            },
        };
    }

    columns = {
        adjust: () => this,
    };

    updateHeaderState() {
        const headers = Array.from(this.table.tHead?.rows?.[0]?.cells || []);
        headers.forEach((header, index) => {
            if (!header.dataset.sortable) {
                return;
            }

            header.classList.remove('sorting-asc', 'sorting-desc');
            if (index === this.sortColumn) {
                header.classList.add(this.sortDirection === 'asc' ? 'sorting-asc' : 'sorting-desc');
            }
        });
    }

    updateInfo(totalRows, visibleRows) {
        if (!this.infoEl) {
            return;
        }

        if (visibleRows === 0) {
            this.infoEl.textContent = this.options.labels.infoEmpty;
            return;
        }

        const start = (this.currentPage - 1) * this.pageLength + 1;
        const end = start + visibleRows - 1;
        this.infoEl.textContent = this.options.labels.info
            .replace('_START_', String(start))
            .replace('_END_', String(end))
            .replace('_TOTAL_', String(totalRows));
    }

    updatePagination(totalRows) {
        if (!this.paginationEl) {
            return;
        }

        const totalPages = Math.max(1, Math.ceil(totalRows / this.pageLength));
        if (this.currentPage > totalPages) {
            this.currentPage = totalPages;
        }

        this.paginationEl.innerHTML = '';

        const makeButton = (label, disabled, page) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                this.currentPage = page;
                this.draw();
            });
            return button;
        };

        this.paginationEl.appendChild(
            makeButton(this.options.labels.previous, this.currentPage <= 1, this.currentPage - 1)
        );

        const pageLabel = document.createElement('button');
        pageLabel.type = 'button';
        pageLabel.className = 'btn btn-outline-primary disabled';
        pageLabel.textContent = `${this.currentPage}/${totalPages}`;
        this.paginationEl.appendChild(pageLabel);

        this.paginationEl.appendChild(
            makeButton(this.options.labels.next, this.currentPage >= totalPages, this.currentPage + 1)
        );
    }

    renderRows() {
        const ordered = this.getOrderedFilteredRows();
        const currentPageRows = this.getCurrentPageRows();
        const currentPageSet = new Set(currentPageRows);

        ordered.forEach((row) => {
            this.tbody.appendChild(row);
        });

        this.allRows.forEach((row) => {
            row.style.display = currentPageSet.has(row) ? '' : 'none';
        });

        this.updateHeaderState();
        this.updateInfo(ordered.length, currentPageRows.length);
        this.updatePagination(ordered.length);
    }

    draw() {
        this.renderRows();
        this.drawListeners.forEach((listener) => {
            try {
                listener();
            } catch (error) {
                console.error('SimpleImagenesRealizadasTable draw listener failed.', error);
            }
        });
        return this;
    }
}

window.createImagenesRealizadasTable = function createImagenesRealizadasTable(table, options = {}) {
    if (!(table instanceof HTMLTableElement)) {
        throw new Error('createImagenesRealizadasTable requires a table element.');
    }

    return new SimpleImagenesRealizadasTable(table, options);
};

window.dispatchEvent(new CustomEvent('medforge:imagenes-realizadas-module-ready'));

if (typeof window.initImagenesRealizadasPage === 'function') {
    window.initImagenesRealizadasPage();
}
