
(function () {
    'use strict';

    const root = document.getElementById('crm-root');
    if (!root) {
        return;
    }

    let bootstrapData = {};
    try {
        bootstrapData = JSON.parse(root.getAttribute('data-bootstrap') || '{}');
    } catch (error) {
        console.warn('No se pudo interpretar los datos iniciales del CRM', error);
    }

    const permissions = (bootstrapData && typeof bootstrapData.permissions === 'object' && bootstrapData.permissions !== null)
        ? bootstrapData.permissions
        : {};
    const canManageLeads = Boolean(permissions.manageLeads);
    const canManageProjects = Boolean(permissions.manageProjects);
    const canManageTasks = Boolean(permissions.manageTasks);
    const canManageTickets = Boolean(permissions.manageTickets);

    const state = {
        leadStatuses: Array.isArray(bootstrapData.leadStatuses) ? bootstrapData.leadStatuses : [],
        leadSources: Array.isArray(bootstrapData.leadSources) ? bootstrapData.leadSources : [],
        projectStatuses: Array.isArray(bootstrapData.projectStatuses) ? bootstrapData.projectStatuses : [],
        taskStatuses: Array.isArray(bootstrapData.taskStatuses) ? bootstrapData.taskStatuses : [],
        ticketStatuses: Array.isArray(bootstrapData.ticketStatuses) ? bootstrapData.ticketStatuses : [],
        ticketPriorities: Array.isArray(bootstrapData.ticketPriorities) ? bootstrapData.ticketPriorities : [],
        assignableUsers: Array.isArray(bootstrapData.assignableUsers) ? bootstrapData.assignableUsers : [],
        leads: Array.isArray(bootstrapData.initialLeads) ? bootstrapData.initialLeads : [],
        projects: Array.isArray(bootstrapData.initialProjects) ? bootstrapData.initialProjects : [],
        tasks: Array.isArray(bootstrapData.initialTasks) ? bootstrapData.initialTasks : [],
        tickets: Array.isArray(bootstrapData.initialTickets) ? bootstrapData.initialTickets : [],
        proposalStatuses: Array.isArray(bootstrapData.proposalStatuses) ? bootstrapData.proposalStatuses : [],
        proposals: Array.isArray(bootstrapData.initialProposals) ? bootstrapData.initialProposals : [],
        focusProjectId: null,
    };

    const elements = {
        leadTableBody: root.querySelector('#crm-leads-table tbody'),
        leadTableInfo: root.querySelector('#lead-table-info'),
        leadPagination: root.querySelector('#lead-pagination'),
        leadPageSize: root.querySelector('#lead-page-size'),
        leadTableSearch: root.querySelector('#lead-table-search'),
        leadSelectAll: root.querySelector('#lead-select-all'),
        leadExportBtn: root.querySelector('#lead-export-btn'),
        leadBulkActionsBtn: root.querySelector('#lead-bulk-actions-btn'),
        leadReloadTable: root.querySelector('#lead-reload-table'),
        projectTableBody: root.querySelector('#crm-projects-table tbody'),
        taskTableBody: root.querySelector('#crm-tasks-table tbody'),
        ticketTableBody: root.querySelector('#crm-tickets-table tbody'),
        leadForm: root.querySelector('#lead-form'),
        convertForm: document.getElementById('lead-convert-form'),
        convertLeadHc: document.getElementById('convert-lead-hc'),
        convertHelper: document.getElementById('convert-helper'),
        convertSelected: document.getElementById('convert-lead-selected'),
        convertSubmit: document.querySelector('#lead-convert-form button[type="submit"]'),
        leadStatusSummary: root.querySelector('#lead-status-summary'),
        leadSearchInput: root.querySelector('#lead-search'),
        leadFilterStatus: root.querySelector('#lead-filter-status'),
        leadFilterSource: root.querySelector('#lead-filter-source'),
        leadFilterAssigned: root.querySelector('#lead-filter-assigned'),
        leadClearFilters: root.querySelector('#lead-clear-filters'),
        leadRefreshBtn: root.querySelector('#lead-refresh-btn'),
        leadBulkStatus: root.querySelector('#lead-bulk-status'),
        leadBulkSource: root.querySelector('#lead-bulk-source'),
        leadBulkAssigned: root.querySelector('#lead-bulk-assigned'),
        leadBulkDelete: root.querySelector('#lead-bulk-delete'),
        leadBulkLost: root.querySelector('#lead-bulk-lost'),
        leadBulkPublic: root.querySelector('#lead-bulk-public'),
        leadBulkApply: root.querySelector('#lead-bulk-apply'),
        leadBulkHelper: root.querySelector('#lead-bulk-helper'),
        projectForm: root.querySelector('#project-form'),
        taskForm: root.querySelector('#task-form'),
        ticketForm: root.querySelector('#ticket-form'),
        ticketReplyForm: root.querySelector('#ticket-reply-form'),
        ticketReplyId: root.querySelector('#ticket-reply-id'),
        ticketReplyHelper: root.querySelector('#ticket-reply-helper'),
        ticketReplySelected: root.querySelector('#ticket-reply-selected'),
        ticketReplyMessage: root.querySelector('#ticket-reply-message'),
        ticketReplyStatus: root.querySelector('#ticket-reply-status'),
        ticketReplySubmit: root.querySelector('#ticket-reply-form button[type="submit"]'),
        leadEmailForm: document.getElementById('lead-email-form'),
        leadEmailTo: document.getElementById('lead-email-to'),
        leadEmailSubject: document.getElementById('lead-email-subject'),
        leadEmailBody: document.getElementById('lead-email-body'),
        leadSelectForProject: root.querySelector('#project-lead'),
        leadSelectForTicket: root.querySelector('#ticket-lead'),
        projectSelectForTask: root.querySelector('#task-project'),
        projectSelectForTicket: root.querySelector('#ticket-project'),
        leadsCount: root.querySelector('#crm-leads-count'),
        projectsCount: root.querySelector('#crm-projects-count'),
        tasksCount: root.querySelector('#crm-tasks-count'),
        ticketsCount: root.querySelector('#crm-tickets-count'),
        proposalTableBody: root.querySelector('#crm-proposals-table tbody'),
        proposalStatusFilter: root.querySelector('#proposal-status-filter'),
        proposalRefreshBtn: root.querySelector('#proposal-refresh-btn'),
        proposalLeadSelect: root.querySelector('#proposal-lead'),
        proposalTitle: root.querySelector('#proposal-title'),
        proposalValidUntil: root.querySelector('#proposal-valid-until'),
        proposalTaxRate: root.querySelector('#proposal-tax-rate'),
        proposalNotes: root.querySelector('#proposal-notes'),
        proposalItemsBody: root.querySelector('#proposal-items-body'),
        proposalSubtotal: root.querySelector('#proposal-subtotal'),
        proposalTax: root.querySelector('#proposal-tax'),
        proposalTotal: root.querySelector('#proposal-total'),
        proposalSaveBtn: root.querySelector('#proposal-save-btn'),
        proposalAddPackageBtn: root.querySelector('#proposal-add-package-btn'),
        proposalAddCodeBtn: root.querySelector('#proposal-add-code-btn'),
        proposalAddCustomBtn: root.querySelector('#proposal-add-custom-btn'),
        proposalPackageModal: document.getElementById('proposal-package-modal'),
        proposalPackageSearch: document.getElementById('proposal-package-search'),
        proposalPackageList: document.getElementById('proposal-package-list'),
        proposalCodeModal: document.getElementById('proposal-code-modal'),
        proposalCodeSearchInput: document.getElementById('proposal-code-search-input'),
        proposalCodeSearchBtn: document.getElementById('proposal-code-search-btn'),
        proposalCodeResults: document.getElementById('proposal-code-results'),
        proposalSearchInput: document.getElementById('proposal-search'),
        proposalPreviewTitle: document.getElementById('proposal-preview-title'),
        proposalPreviewStatus: document.getElementById('proposal-preview-status'),
        proposalPreviewNumber: document.getElementById('proposal-preview-number'),
        proposalPreviewTo: document.getElementById('proposal-preview-to'),
        proposalPreviewValid: document.getElementById('proposal-preview-valid'),
        proposalPreviewTotal: document.getElementById('proposal-preview-total'),
        proposalPreviewOpen: document.getElementById('proposal-preview-open'),
        proposalPreviewRefresh: document.getElementById('proposal-preview-refresh'),
        proposalNewBtn: document.getElementById('proposal-new-btn'),
        proposalPipelineBtn: document.getElementById('proposal-pipeline-btn'),
        proposalExportBtn: document.getElementById('proposal-export-btn'),
        proposalDetailModal: document.getElementById('proposal-detail-modal'),
        proposalDetailTitle: document.getElementById('proposal-detail-title'),
        proposalDetailStatus: document.getElementById('proposal-detail-status'),
        proposalDetailSubtitle: document.getElementById('proposal-detail-subtitle'),
        proposalDetailContent: document.getElementById('proposal-detail-content'),
        proposalDetailEmpty: document.getElementById('proposal-detail-empty'),
        proposalDetailLoading: document.getElementById('proposal-detail-loading'),
        proposalDetailItemsBody: document.getElementById('proposal-detail-items-body'),
        proposalDetailItemsCount: document.getElementById('proposal-detail-items-count'),
        proposalDetailLead: document.getElementById('proposal-detail-lead'),
        proposalDetailValidUntil: document.getElementById('proposal-detail-valid-until'),
        proposalDetailCreated: document.getElementById('proposal-detail-created'),
        proposalDetailTaxRate: document.getElementById('proposal-detail-tax-rate'),
        proposalDetailSubtotal: document.getElementById('proposal-detail-subtotal'),
        proposalDetailDiscount: document.getElementById('proposal-detail-discount'),
        proposalDetailTax: document.getElementById('proposal-detail-tax'),
        proposalDetailTotal: document.getElementById('proposal-detail-total'),
        proposalDetailTimeline: document.getElementById('proposal-detail-timeline'),
        proposalDetailStatusSelect: document.getElementById('proposal-detail-status-select'),
        proposalDetailNotes: document.getElementById('proposal-detail-notes'),
        proposalDetailTerms: document.getElementById('proposal-detail-terms'),
        leadDetailModal: document.getElementById('lead-detail-modal'),
        leadDetailBody: document.getElementById('lead-detail-body'),
        leadDetailTitle: document.getElementById('lead-detail-title'),
        leadDetailId: document.getElementById('lead-detail-id'),
        leadDetailConvert: document.getElementById('lead-detail-convert'),
        leadDetailEdit: document.getElementById('lead-detail-edit'),
        leadDetailSave: document.getElementById('lead-detail-save'),
        leadDetailSaveFooter: document.getElementById('lead-detail-save-footer'),
        leadDetailCancel: document.getElementById('lead-detail-cancel'),
        leadDetailEditActions: document.getElementById('lead-detail-edit-actions'),
        leadDetailEditFooter: document.getElementById('lead-edit-footer'),
        leadDetailEditSection: document.getElementById('lead-edit-section'),
        leadDetailViewSection: document.getElementById('lead-view-section'),
        leadDetailNotesCount: document.getElementById('lead-notes-count'),
        leadProjectsList: document.getElementById('lead-projects-list'),
        leadProjectsEmpty: document.getElementById('lead-projects-empty'),
        leadProjectsCreate: document.getElementById('lead-project-create'),
        leadFormHelper: root.querySelector('#lead-form-helper'),
    };

    const proposalBuilder = {
        items: [],
        packages: [],
    };

    const proposalModals = {
        package: (window.bootstrap && elements.proposalPackageModal) ? new window.bootstrap.Modal(elements.proposalPackageModal) : null,
        code: (window.bootstrap && elements.proposalCodeModal) ? new window.bootstrap.Modal(elements.proposalCodeModal) : null,
        detail: (window.bootstrap && elements.proposalDetailModal) ? new window.bootstrap.Modal(elements.proposalDetailModal) : null,
    };

    const proposalDetailState = {
        current: null,
    };

    const leadDetailState = {
        current: null,
    };

    const leadModals = {
        convert: (window.bootstrap && document.getElementById('lead-convert-modal'))
            ? new window.bootstrap.Modal(document.getElementById('lead-convert-modal'))
            : null,
        detail: (window.bootstrap && elements.leadDetailModal)
            ? new window.bootstrap.Modal(elements.leadDetailModal)
            : null,
        email: (window.bootstrap && document.getElementById('lead-email-modal'))
            ? new window.bootstrap.Modal(document.getElementById('lead-email-modal'))
            : null,
        form: (window.bootstrap && document.getElementById('lead-modal'))
            ? new window.bootstrap.Modal(document.getElementById('lead-modal'))
            : null,
    };

    const leadFormState = {
        mode: 'create',
        currentHc: null,
    };

    const leadFilters = {
        search: '',
        status: '',
        source: '',
        assigned: '',
    };

    const leadTableState = {
        page: 1,
        pageSize: 10,
    };

    const proposalFilters = {
        status: '',
        search: '',
    };

    const proposalUIState = {
        selectedId: null,
    };

    const selectedLeads = new Set();

    state.leads = mapLeads(state.leads);
    state.proposals = mapProposals(state.proposals);

    const htmlEscapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).replace(/[&<>"']/g, (match) => htmlEscapeMap[match]);
    }

    function activateTab(tabId) {
        const tabLink = document.getElementById(tabId);
        if (!tabLink) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Tab) {
            const tab = new window.bootstrap.Tab(tabLink);
            tab.show();
            return;
        }

        tabLink.classList.add('active');
        const targetSelector = tabLink.getAttribute('href') || '';
        if (targetSelector) {
            const target = document.querySelector(targetSelector);
            if (target) {
                target.classList.add('active', 'show');
            }
        }
    }

    function applyUrlDeepLink() {
        const params = new URLSearchParams(window.location.search || '');
        const tab = params.get('tab');
        if (tab === 'projects') {
            activateTab('crm-tab-projects-link');
        }

        const rawProjectId = params.get('project_id');
        const projectId = rawProjectId ? Number.parseInt(rawProjectId, 10) : null;
        if (projectId && Number.isFinite(projectId)) {
            state.focusProjectId = projectId;
        }
    }

    function setTextContent(element, value) {
        if (!element) {
            return;
        }
        element.textContent = value || '—';
    }

    function titleize(value) {
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

    function parseDate(value) {
        if (!value) {
            return null;
        }
        const normalized = value.includes('T') ? value : value.replace(' ', 'T');
        const date = new Date(normalized);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDate(value, withTime) {
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

    function pickValue(...values) {
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

    function buildPatientName(patient) {
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

    function limitText(value, maxLength) {
        if (!value) {
            return '';
        }
        if (value.length <= maxLength) {
            return value;
        }
        return `${value.slice(0, maxLength - 1)}…`;
    }

    function formatCurrency(value) {
        const amount = Number.isFinite(value) ? value : 0;
        try {
            return new Intl.NumberFormat('es-EC', { style: 'currency', currency: 'USD' }).format(amount);
        } catch (error) {
            return `$${amount.toFixed(2)}`;
        }
    }

    function showToast(message, status) {
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

    async function request(url, options) {
        const fetchOptions = Object.assign(
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
            options || {}
        );

        if (fetchOptions.body && typeof fetchOptions.body !== 'string') {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(fetchOptions.body);
        }

        const response = await fetch(url, fetchOptions);
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        const success = response.ok && payload && payload.ok !== false;
        if (!success) {
            const message = payload && (payload.error || payload.message)
                ? payload.error || payload.message
                : `Error ${response.status || ''}`.trim();
            const error = new Error(message);
            error.response = response;
            error.payload = payload;
            throw error;
        }

        return payload;
    }

    function updateCounters(visibleLeadsCount) {
        if (elements.leadsCount) {
            const visible = typeof visibleLeadsCount === 'number' ? visibleLeadsCount : state.leads.length;
            const total = state.leads.length;
            elements.leadsCount.textContent = `Leads: ${visible}${visible !== total ? ` / ${total}` : ''}`;
        }
        if (elements.projectsCount) {
            elements.projectsCount.textContent = `Proyectos: ${state.projects.length}`;
        }
        if (elements.tasksCount) {
            elements.tasksCount.textContent = `Tareas: ${state.tasks.length}`;
        }
        if (elements.ticketsCount) {
            elements.ticketsCount.textContent = `Tickets: ${state.tickets.length}`;
        }
    }

    function clearContainer(container) {
        while (container && container.firstChild) {
            container.removeChild(container.firstChild);
        }
    }

    function setTextContent(element, value, fallback = '—') {
        if (!element) {
            return;
        }
        const display = value === null || value === undefined || value === '' ? fallback : value;
        element.textContent = display;
    }

    function syncPreviewStatusPill(element, status) {
        if (!element) {
            return;
        }
        const badge = proposalStatusBadge(status || 'draft');
        element.className = badge.className;
        element.textContent = badge.textContent;
    }

    function setProposalPreview(proposal) {
        if (!proposal) {
            setTextContent(elements.proposalPreviewTitle, 'Selecciona una propuesta');
            setTextContent(elements.proposalPreviewNumber, '—');
            setTextContent(elements.proposalPreviewTo, '—');
            setTextContent(elements.proposalPreviewValid, '—');
            setTextContent(elements.proposalPreviewTotal, '—');
            syncPreviewStatusPill(elements.proposalPreviewStatus, 'draft');
            if (elements.proposalPreviewOpen) elements.proposalPreviewOpen.disabled = true;
            if (elements.proposalPreviewRefresh) elements.proposalPreviewRefresh.disabled = true;
            return;
        }

        setTextContent(elements.proposalPreviewTitle, proposal.title || 'Propuesta');
        setTextContent(elements.proposalPreviewNumber, proposal.proposal_number || `#${proposal.id}`);
        setTextContent(elements.proposalPreviewTo, proposal.lead_name || proposal.customer_name || '—');
        setTextContent(elements.proposalPreviewValid, proposal.valid_until ? formatDate(proposal.valid_until, false) : '—');
        setTextContent(elements.proposalPreviewTotal, formatCurrency(proposal.total || 0));
        syncPreviewStatusPill(elements.proposalPreviewStatus, proposal.status);
        if (elements.proposalPreviewOpen) {
            elements.proposalPreviewOpen.disabled = false;
            elements.proposalPreviewOpen.dataset.proposalId = proposal.id;
        }
        if (elements.proposalPreviewRefresh) {
            elements.proposalPreviewRefresh.disabled = false;
            elements.proposalPreviewRefresh.dataset.proposalId = proposal.id;
        }
    }

    function setSelectedProposal(proposalId) {
        if (!proposalId) {
            proposalUIState.selectedId = null;
            setProposalPreview(null);
            return;
        }
        proposalUIState.selectedId = proposalId;
        const proposal = state.proposals.find((p) => Number(p.id) === Number(proposalId));
        setProposalPreview(proposal || null);
        if (elements.proposalTableBody) {
            elements.proposalTableBody.querySelectorAll('.proposal-row').forEach((row) => {
                if (String(row.dataset.proposalId) === String(proposalId)) {
                    row.classList.add('table-active');
                } else {
                    row.classList.remove('table-active');
                }
            });
        }
    }

    function createStatusSelect(options, value) {
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

    function appendLine(container, text, iconClass) {
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

    function setPlaceholderOptions(select) {
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

    function populateLeadSelects() {
        [elements.leadSelectForProject, elements.leadSelectForTicket, elements.proposalLeadSelect].forEach((select) => {
            if (!select) {
                return;
            }
            const currentValue = select.value;
            setPlaceholderOptions(select);
            state.leads.forEach((lead) => {
                const option = document.createElement('option');
                option.value = lead.id;
                const normalizedHc = normalizeHcNumber(lead.hc_number);
                if (lead.name && normalizedHc) {
                    option.textContent = `${lead.name} · ${normalizedHc}`;
                } else if (lead.name) {
                    option.textContent = lead.name;
                } else if (normalizedHc) {
                    option.textContent = `HC ${normalizedHc}`;
                } else {
                    option.textContent = `Lead #${lead.id}`;
                }
                select.appendChild(option);
            });
            if (currentValue && state.leads.some((lead) => String(lead.id) === String(currentValue))) {
                select.value = currentValue;
            }
        });
    }

    function populateProjectSelects() {
        [elements.projectSelectForTask, elements.projectSelectForTicket].forEach((select) => {
            if (!select) {
                return;
            }
            const currentValue = select.value;
            setPlaceholderOptions(select);
            state.projects.forEach((project) => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.title ? project.title : `Proyecto #${project.id}`;
                select.appendChild(option);
            });
            if (currentValue && state.projects.some((project) => String(project.id) === String(currentValue))) {
                select.value = currentValue;
            }
        });
    }

    function findLeadById(id) {
        return state.leads.find((lead) => Number(lead.id) === Number(id)) || null;
    }

    function findTicketById(id) {
        return state.tickets.find((ticket) => Number(ticket.id) === Number(id)) || null;
    }

    function getFilteredLeads() {
        const search = (leadFilters.search || '').toLowerCase();

        return state.leads.filter((lead) => {
            if (leadFilters.status) {
                if (leadFilters.status === 'sin_estado') {
                    if (lead.status) {
                        return false;
                    }
                } else if ((lead.status || '') !== leadFilters.status) {
                    return false;
                }
            }
            if (leadFilters.source && (lead.source || '').toLowerCase() !== leadFilters.source.toLowerCase()) {
                return false;
            }
            if (leadFilters.assigned && String(lead.assigned_to || '') !== String(leadFilters.assigned)) {
                return false;
            }
            if (search) {
                const haystack = [
                    lead.name,
                    lead.email,
                    lead.phone,
                    lead.source,
                    lead.assigned_name,
                    lead.hc_number,
                ]
                    .filter(Boolean)
                    .map((value) => String(value).toLowerCase())
                    .join(' ');
                if (!haystack.includes(search)) {
                    return false;
                }
            }
            return true;
        });
    }

    function clampPage(totalItems) {
        if (leadTableState.pageSize === -1) {
            leadTableState.page = 1;
            return;
        }
        const totalPages = Math.max(1, Math.ceil(totalItems / leadTableState.pageSize));
        if (leadTableState.page > totalPages) {
            leadTableState.page = totalPages;
        }
        if (leadTableState.page < 1) {
            leadTableState.page = 1;
        }
    }

    function getPaginatedLeads() {
        const filtered = getFilteredLeads();
        clampPage(filtered.length);
        if (leadTableState.pageSize === -1) {
            return { items: filtered, total: filtered.length, totalPages: 1 };
        }
        const start = (leadTableState.page - 1) * leadTableState.pageSize;
        const end = start + leadTableState.pageSize;
        const items = filtered.slice(start, end);
        const totalPages = Math.max(1, Math.ceil(filtered.length / leadTableState.pageSize));
        return { items, total: filtered.length, totalPages };
    }

    function renderPagination(totalPages) {
        if (!elements.leadPagination) {
            return;
        }
        clearContainer(elements.leadPagination);

        const prev = document.createElement('li');
        prev.className = `page-item ${leadTableState.page === 1 ? 'disabled' : ''}`;
        const prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.href = '#';
        prevLink.textContent = 'Anterior';
        prevLink.addEventListener('click', (event) => {
            event.preventDefault();
            if (leadTableState.page > 1) {
                leadTableState.page -= 1;
                renderLeads();
            }
        });
        prev.appendChild(prevLink);
        elements.leadPagination.appendChild(prev);

        for (let page = 1; page <= totalPages; page += 1) {
            const item = document.createElement('li');
            item.className = `page-item ${leadTableState.page === page ? 'active' : ''}`;
            const link = document.createElement('a');
            link.className = 'page-link';
            link.href = '#';
            link.textContent = String(page);
            link.addEventListener('click', (event) => {
                event.preventDefault();
                leadTableState.page = page;
                renderLeads();
            });
            item.appendChild(link);
            elements.leadPagination.appendChild(item);
        }

        const next = document.createElement('li');
        next.className = `page-item ${leadTableState.page === totalPages ? 'disabled' : ''}`;
        const nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.href = '#';
        nextLink.textContent = 'Siguiente';
        nextLink.addEventListener('click', (event) => {
            event.preventDefault();
            if (leadTableState.page < totalPages) {
                leadTableState.page += 1;
                renderLeads();
            }
        });
        next.appendChild(nextLink);
        elements.leadPagination.appendChild(next);
    }

    function renderLeadStatusSummary() {
        if (!elements.leadStatusSummary) {
            return;
        }

        clearContainer(elements.leadStatusSummary);

        const counts = {};
        const statuses = [...state.leadStatuses];

        state.leads.forEach((lead) => {
            const statusKey = lead.status || 'sin_estado';
            counts[statusKey] = (counts[statusKey] || 0) + 1;
            if (lead.status && !statuses.includes(lead.status)) {
                statuses.push(lead.status);
            }
        });

        const totalButton = document.createElement('button');
        totalButton.type = 'button';
        totalButton.className = `btn btn-sm ${leadFilters.status === '' ? 'btn-primary text-white' : 'btn-outline-secondary'} d-flex align-items-center gap-2`;
        totalButton.dataset.statusFilter = '';
        totalButton.innerHTML = `<span class="fw-600">Todos</span><span class="badge bg-light text-dark">${state.leads.length}</span>`;
        elements.leadStatusSummary.appendChild(totalButton);

        statuses.forEach((status) => {
            const count = counts[status] || 0;
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.statusFilter = status;
            button.className = `btn btn-sm ${leadFilters.status === status ? 'btn-primary text-white' : 'btn-outline-secondary'} d-flex align-items-center gap-2`;
            button.innerHTML = `<span class="fw-600">${titleize(status)}</span><span class="badge ${count ? 'bg-primary-light text-primary' : 'bg-light text-muted'}">${count}</span>`;
            elements.leadStatusSummary.appendChild(button);
        });

        if (counts.sin_estado && counts.sin_estado > 0 && !statuses.includes('sin_estado')) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.statusFilter = 'sin_estado';
            button.className = `btn btn-sm ${leadFilters.status === 'sin_estado' ? 'btn-primary text-white' : 'btn-outline-secondary'} d-flex align-items-center gap-2`;
            button.innerHTML = `<span class="fw-600">Sin estado</span><span class="badge bg-light text-dark">${counts.sin_estado}</span>`;
            elements.leadStatusSummary.appendChild(button);
        }
    }

    function syncLeadFiltersUI() {
        if (elements.leadSearchInput) {
            elements.leadSearchInput.value = leadFilters.search || '';
        }
        if (elements.leadFilterStatus) {
            elements.leadFilterStatus.value = leadFilters.status || '';
        }
        if (elements.leadFilterSource) {
            elements.leadFilterSource.value = leadFilters.source || '';
        }
        if (elements.leadFilterAssigned) {
            elements.leadFilterAssigned.value = leadFilters.assigned || '';
        }
    }

    function renderLeads() {
        if (!elements.leadTableBody) {
            return;
        }
        clearContainer(elements.leadTableBody);

        const { items: leadsToRender, total, totalPages } = getPaginatedLeads();

        if (!leadsToRender.length) {
            const emptyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 10;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'Aún no se han registrado leads.';
            emptyRow.appendChild(cell);
            elements.leadTableBody.appendChild(emptyRow);
        } else {
            leadsToRender.forEach((lead) => {
                const row = document.createElement('tr');

                const selectCell = document.createElement('td');
                selectCell.className = 'text-center';
                const selectInput = document.createElement('input');
                selectInput.type = 'checkbox';
                selectInput.className = 'form-check-input js-lead-select';
                selectInput.dataset.leadId = lead.id;
                selectInput.checked = selectedLeads.has(String(lead.id));
                selectInput.addEventListener('change', () => {
                    if (selectInput.checked) {
                        selectedLeads.add(String(lead.id));
                    } else {
                        selectedLeads.delete(String(lead.id));
                    }
                    syncLeadSelectionUI();
                });
                selectCell.appendChild(selectInput);
                row.appendChild(selectCell);

                const numberCell = document.createElement('td');
                numberCell.innerHTML = `<strong>${lead.id || '-'}</strong>`;
                row.appendChild(numberCell);

                const nameCell = document.createElement('td');
                const nameStrong = document.createElement('strong');
                const normalizedHc = normalizeHcNumber(lead.hc_number);
                if (lead.name) {
                    nameStrong.textContent = lead.name;
                } else if (normalizedHc) {
                    nameStrong.textContent = `HC ${normalizedHc}`;
                } else {
                    nameStrong.textContent = `Lead #${lead.id}`;
                }
                nameCell.appendChild(nameStrong);
                if (normalizedHc) {
                    appendLine(nameCell, `HC ${normalizedHc}`, 'mdi mdi-card-account-details-outline');
                }
                appendLine(nameCell, `Creado ${formatDate(lead.created_at, true)}`, 'mdi mdi-calendar-clock');
                row.appendChild(nameCell);

                const contactCell = document.createElement('td');
                appendLine(contactCell, lead.email, 'mdi mdi-email-outline');
                appendLine(contactCell, lead.phone, 'mdi mdi-phone-outline');
                if (!lead.email && !lead.phone) {
                    contactCell.innerHTML = '<span class="text-muted">-</span>';
                }
                row.appendChild(contactCell);

                const statusCell = document.createElement('td');
                if (canManageLeads) {
                    const statusSelect = createStatusSelect(state.leadStatuses, lead.status);
                    statusSelect.classList.add('js-lead-status');
                    statusSelect.dataset.leadHc = normalizedHc;
                    statusCell.appendChild(statusSelect);
                } else {
                    statusCell.textContent = lead.status ? titleize(lead.status) : 'Sin estado';
                }
                row.appendChild(statusCell);

                const sourceCell = document.createElement('td');
                sourceCell.textContent = lead.source ? titleize(lead.source) : '-';
                row.appendChild(sourceCell);

                const tagsCell = document.createElement('td');
                if (Array.isArray(lead.tags) && lead.tags.length) {
                    lead.tags.slice(0, 3).forEach((tag) => {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-light text-muted border me-1';
                        badge.textContent = limitText(tag, 18);
                        tagsCell.appendChild(badge);
                    });
                    if (lead.tags.length > 3) {
                        const extra = document.createElement('span');
                        extra.className = 'badge bg-secondary';
                        extra.textContent = `+${lead.tags.length - 3}`;
                        tagsCell.appendChild(extra);
                    }
                } else {
                    tagsCell.innerHTML = '<span class="text-muted">-</span>';
                }
                row.appendChild(tagsCell);

                const assignedCell = document.createElement('td');
                if (canManageLeads) {
                    const assignSelect = document.createElement('select');
                    assignSelect.className = 'form-select form-select-sm js-lead-assigned';
                    assignSelect.dataset.leadId = lead.id;

                    const emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.textContent = 'Sin asignar';
                    assignSelect.appendChild(emptyOption);

                    state.assignableUsers.forEach((user) => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.nombre || user.name || user.email || `ID ${user.id}`;
                        assignSelect.appendChild(option);
                    });

                    if (lead.assigned_to) {
                        assignSelect.value = lead.assigned_to;
                    }

                    assignedCell.appendChild(assignSelect);
                } else {
                    assignedCell.textContent = lead.assigned_name || 'Sin asignar';
                }
                row.appendChild(assignedCell);

                const updatedCell = document.createElement('td');
                updatedCell.textContent = formatDate(lead.updated_at, true);
                row.appendChild(updatedCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'text-end';
                if (canManageLeads) {
                    const group = document.createElement('div');
                    group.className = 'btn-group';

                    const viewButton = document.createElement('button');
                    viewButton.type = 'button';
                    viewButton.className = 'btn btn-sm btn-outline-secondary js-view-lead';
                    viewButton.dataset.leadId = lead.id;
                    viewButton.innerHTML = '<i class="mdi mdi-eye-outline"></i>';
                    group.appendChild(viewButton);

                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'btn btn-sm btn-outline-primary js-edit-lead';
                    editButton.dataset.leadId = lead.id;
                    editButton.innerHTML = '<i class="mdi mdi-tooltip-edit"></i>';
                    group.appendChild(editButton);

                    const emailButton = document.createElement('button');
                    emailButton.type = 'button';
                    emailButton.className = 'btn btn-sm btn-outline-info js-lead-email';
                    emailButton.dataset.leadId = lead.id;
                    emailButton.title = lead.email ? 'Enviar correo' : 'Sin correo disponible';
                    emailButton.disabled = !lead.email;
                    emailButton.innerHTML = '<i class="mdi mdi-email-outline"></i>';
                    group.appendChild(emailButton);

                    const convertButton = document.createElement('button');
                    convertButton.type = 'button';
                    convertButton.className = 'btn btn-sm btn-success js-select-lead';
                    convertButton.dataset.leadHc = normalizedHc;
                    const canConvert = Boolean(normalizedHc);
                    convertButton.disabled = !canConvert;
                    convertButton.title = canConvert
                        ? 'Convertir a paciente'
                        : 'Agrega una historia clínica para poder convertir';
                    convertButton.innerHTML = '<i class="mdi mdi-checkbox-marked-circle-outline"></i>';
                    group.appendChild(convertButton);

                    actionsCell.appendChild(group);
                } else {
                    actionsCell.innerHTML = '<span class="text-muted">Sin acciones</span>';
                }
                row.appendChild(actionsCell);

                elements.leadTableBody.appendChild(row);
            });
        }

        populateLeadSelects();
        syncConvertFormSelection();
        renderLeadStatusSummary();
        renderPagination(totalPages);
        updateCounters(total);
        syncLeadSelectionUI();
        renderLeadInfo(total, leadsToRender.length);
    }

    function renderLeadInfo(total, visible) {
        if (!elements.leadTableInfo) {
            return;
        }
        const pageSizeText = leadTableState.pageSize === -1 ? 'todos' : leadTableState.pageSize;
        elements.leadTableInfo.textContent = `Mostrando ${visible} de ${total} leads (página ${leadTableState.page}, ${pageSizeText} por página)`;
    }

    function syncLeadSelectionUI() {
        if (elements.leadSelectAll) {
            const paginated = getPaginatedLeads();
            const allSelected = paginated.items.length > 0 && paginated.items.every((lead) => selectedLeads.has(String(lead.id)));
            elements.leadSelectAll.checked = allSelected;
            elements.leadSelectAll.indeterminate = !allSelected && paginated.items.some((lead) => selectedLeads.has(String(lead.id)));
        }
        if (elements.leadBulkHelper) {
            const count = selectedLeads.size;
            elements.leadBulkHelper.textContent = count ? `${count} leads seleccionados.` : 'Selecciona al menos un lead para aplicar los cambios.';
        }
    }

    function renderProjects() {
        if (!elements.projectTableBody) {
            return;
        }
        clearContainer(elements.projectTableBody);

        if (!state.projects.length) {
            const emptyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'No hay proyectos registrados.';
            emptyRow.appendChild(cell);
            elements.projectTableBody.appendChild(emptyRow);
        } else {
            state.projects.forEach((project) => {
                const row = document.createElement('tr');
                row.dataset.projectId = project.id;

                const titleCell = document.createElement('td');
                const strong = document.createElement('strong');
                strong.textContent = project.title || `Proyecto #${project.id}`;
                titleCell.appendChild(strong);
                if (project.description) {
                    appendLine(titleCell, limitText(project.description, 80));
                }
                row.appendChild(titleCell);

                const statusCell = document.createElement('td');
                if (canManageProjects) {
                    const statusSelect = createStatusSelect(state.projectStatuses, project.status);
                    statusSelect.classList.add('js-project-status');
                    statusSelect.dataset.projectId = project.id;
                    statusCell.appendChild(statusSelect);
                } else {
                    statusCell.textContent = project.status ? titleize(project.status) : 'Sin estado';
                }
                row.appendChild(statusCell);

                const leadCell = document.createElement('td');
                leadCell.textContent = project.lead_name || (project.lead_id ? `Lead #${project.lead_id}` : '-');
                row.appendChild(leadCell);

                const ownerCell = document.createElement('td');
                ownerCell.textContent = project.owner_name || 'Sin asignar';
                row.appendChild(ownerCell);

                const startCell = document.createElement('td');
                startCell.textContent = formatDate(project.start_date, false);
                row.appendChild(startCell);

                const dueCell = document.createElement('td');
                dueCell.textContent = formatDate(project.due_date, false);
                row.appendChild(dueCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'text-end';
                const updatedBadge = document.createElement('span');
                updatedBadge.className = 'badge bg-light text-muted';
                updatedBadge.textContent = `Actualizado ${formatDate(project.updated_at, true)}`;
                actionsCell.appendChild(updatedBadge);
                row.appendChild(actionsCell);

                elements.projectTableBody.appendChild(row);
            });

            if (state.focusProjectId) {
                elements.projectTableBody
                    .querySelectorAll('.crm-project-focus')
                    .forEach((row) => row.classList.remove('crm-project-focus', 'table-active'));
                const focusedRow = elements.projectTableBody.querySelector(
                    `[data-project-id="${state.focusProjectId}"]`
                );
                if (focusedRow) {
                    focusedRow.classList.add('crm-project-focus', 'table-active');
                    requestAnimationFrame(() => {
                        focusedRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    });
                }
                state.focusProjectId = null;
            }
        }

        populateProjectSelects();
        updateCounters();
    }

    function renderTasks() {
        if (!elements.taskTableBody) {
            return;
        }
        clearContainer(elements.taskTableBody);

        if (!state.tasks.length) {
            const emptyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'No hay tareas registradas.';
            emptyRow.appendChild(cell);
            elements.taskTableBody.appendChild(emptyRow);
        } else {
            state.tasks.forEach((task) => {
                const row = document.createElement('tr');

                const titleCell = document.createElement('td');
                const strong = document.createElement('strong');
                strong.textContent = task.title || `Tarea #${task.id}`;
                titleCell.appendChild(strong);
                if (task.description) {
                    appendLine(titleCell, limitText(task.description, 80));
                }
                appendLine(titleCell, `Creada ${formatDate(task.created_at, true)}`, 'mdi mdi-calendar-plus');
                row.appendChild(titleCell);

                const projectCell = document.createElement('td');
                projectCell.textContent = task.project_title || (task.project_id ? `Proyecto #${task.project_id}` : '-');
                row.appendChild(projectCell);

                const assignedCell = document.createElement('td');
                assignedCell.textContent = task.assigned_name || 'Sin asignar';
                row.appendChild(assignedCell);

                const statusCell = document.createElement('td');
                if (canManageTasks) {
                    const statusSelect = createStatusSelect(state.taskStatuses, task.status);
                    statusSelect.classList.add('js-task-status');
                    statusSelect.dataset.taskId = task.id;
                    statusCell.appendChild(statusSelect);
                } else {
                    statusCell.textContent = task.status ? titleize(task.status) : 'Sin estado';
                }
                row.appendChild(statusCell);

                const dueCell = document.createElement('td');
                dueCell.textContent = task.due_date ? formatDate(task.due_date, false) : '-';
                row.appendChild(dueCell);

                const reminderCell = document.createElement('td');
                if (Array.isArray(task.reminders) && task.reminders.length) {
                    task.reminders.forEach((reminder) => {
                        appendLine(reminderCell, `${formatDate(reminder.remind_at, true)} (${titleize(reminder.channel)})`, 'mdi mdi-bell-ring-outline');
                    });
                } else {
                    reminderCell.innerHTML = '<span class="text-muted">Sin recordatorios</span>';
                }
                row.appendChild(reminderCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'text-end';
                const updatedBadge = document.createElement('span');
                updatedBadge.className = 'badge bg-light text-muted';
                updatedBadge.textContent = `Actualizado ${formatDate(task.updated_at, true)}`;
                actionsCell.appendChild(updatedBadge);
                row.appendChild(actionsCell);

                elements.taskTableBody.appendChild(row);
            });
        }

        updateCounters();
    }

    function createStatusBadge(status, map) {
        const span = document.createElement('span');
        const normalized = status ? status.toLowerCase() : '';
        const className = (map && map[normalized]) || 'badge bg-light text-muted';
        span.className = `${className} text-uppercase fw-600`;
        span.textContent = titleize(status) || '—';
        return span;
    }

    function renderTickets() {
        if (!elements.ticketTableBody) {
            return;
        }
        clearContainer(elements.ticketTableBody);

        if (!state.tickets.length) {
            const emptyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'No existen tickets de soporte.';
            emptyRow.appendChild(cell);
            elements.ticketTableBody.appendChild(emptyRow);
        } else {
            state.tickets.forEach((ticket) => {
                const row = document.createElement('tr');

                const subjectCell = document.createElement('td');
                const strong = document.createElement('strong');
                strong.textContent = ticket.subject || `Ticket #${ticket.id}`;
                subjectCell.appendChild(strong);
                appendLine(subjectCell, `Creado ${formatDate(ticket.created_at, true)}`, 'mdi mdi-calendar');
                row.appendChild(subjectCell);

                const statusCell = document.createElement('td');
                statusCell.appendChild(
                    createStatusBadge(ticket.status, {
                        abierto: 'badge bg-danger-light text-danger',
                        en_progreso: 'badge bg-warning-light text-warning',
                        resuelto: 'badge bg-success-light text-success',
                        cerrado: 'badge bg-secondary text-white',
                    })
                );
                row.appendChild(statusCell);

                const priorityCell = document.createElement('td');
                priorityCell.appendChild(
                    createStatusBadge(ticket.priority, {
                        baja: 'badge bg-light text-muted',
                        media: 'badge bg-info-light text-info',
                        alta: 'badge bg-warning text-white',
                        critica: 'badge bg-danger text-white',
                    })
                );
                row.appendChild(priorityCell);

                const reporterCell = document.createElement('td');
                reporterCell.textContent = ticket.reporter_name || '—';
                row.appendChild(reporterCell);

                const assignedCell = document.createElement('td');
                assignedCell.textContent = ticket.assigned_name || 'Sin asignar';
                row.appendChild(assignedCell);

                const relatedCell = document.createElement('td');
                const labels = [];
                if (ticket.lead_name) {
                    labels.push(`Lead: ${ticket.lead_name}`);
                } else if (ticket.related_lead_id) {
                    labels.push(`Lead #${ticket.related_lead_id}`);
                }
                if (ticket.project_title) {
                    labels.push(`Proyecto: ${ticket.project_title}`);
                } else if (ticket.related_project_id) {
                    labels.push(`Proyecto #${ticket.related_project_id}`);
                }
                if (!labels.length) {
                    relatedCell.textContent = '—';
                } else {
                    labels.forEach((label) => appendLine(relatedCell, label));
                }
                row.appendChild(relatedCell);

                const updatedCell = document.createElement('td');
                updatedCell.textContent = formatDate(ticket.updated_at, true);
                row.appendChild(updatedCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'text-end';
                if (canManageTickets) {
                    const replyButton = document.createElement('button');
                    replyButton.type = 'button';
                    replyButton.className = 'btn btn-sm btn-outline-info js-reply-ticket';
                    replyButton.dataset.ticketId = ticket.id;
                    replyButton.innerHTML = '<i class="mdi mdi-reply"></i>';
                    actionsCell.appendChild(replyButton);
                }
                const messageCount = Array.isArray(ticket.messages) ? ticket.messages.length : 0;
                if (messageCount) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-info-light text-info ms-2';
                    badge.textContent = `${messageCount} mensaje${messageCount === 1 ? '' : 's'}`;
                    actionsCell.appendChild(badge);
                }
                row.appendChild(actionsCell);

                elements.ticketTableBody.appendChild(row);
            });
        }

        syncTicketReplySelection();
        updateCounters();
    }

    function disableConvertForm() {
        if (!elements.convertForm) {
            return;
        }
        if (elements.convertLeadHc) {
            elements.convertLeadHc.value = '';
        }
        if (elements.convertSelected) {
            elements.convertSelected.textContent = 'Sin selección';
        }
        if (elements.convertHelper) {
            elements.convertHelper.textContent = 'Selecciona un lead en la tabla para precargar los datos.';
        }
        if (elements.convertSubmit) {
            elements.convertSubmit.disabled = true;
        }
        ['customer_name', 'customer_email', 'customer_phone', 'customer_document', 'customer_external_ref', 'customer_affiliation', 'customer_address'].forEach((field) => {
            const input = elements.convertForm.querySelector(`[name="${field}"]`);
            if (input) {
                input.value = '';
            }
        });
    }

    function resetLeadForm() {
        if (!elements.leadForm) {
            return;
        }
        elements.leadForm.reset();
        elements.leadForm.dataset.mode = 'create';
        elements.leadForm.dataset.hcNumber = '';
        leadFormState.mode = 'create';
        leadFormState.currentHc = null;
        const hcInput = elements.leadForm.querySelector('#lead-hc-number');
        if (hcInput) {
            hcInput.disabled = false;
        }
        if (elements.leadFormHelper) {
            elements.leadFormHelper.textContent = 'Completa los campos y guarda.';
        }
    }

    function applyLeadToForm(lead) {
        if (!elements.leadForm || !lead) {
            return;
        }
        const normalizedHc = normalizeHcNumber(lead.hc_number);
        elements.leadForm.dataset.mode = 'edit';
        elements.leadForm.dataset.hcNumber = normalizedHc;
        leadFormState.mode = 'edit';
        leadFormState.currentHc = normalizedHc;

        const hcInput = elements.leadForm.querySelector('#lead-hc-number');
        if (hcInput) {
            hcInput.disabled = true;
        }

        let firstName = lead.first_name || '';
        let lastName = lead.last_name || '';

        if (!firstName && lead.name) {
            const parts = lead.name.trim().split(/\s+/);
            firstName = parts.shift() || '';
            lastName = parts.join(' ');
        }

        const map = {
            name: lead.name || `${firstName} ${lastName}`.trim(),
            first_name: firstName,
            last_name: lastName,
            hc_number: normalizedHc,
            email: lead.email || '',
            phone: lead.phone || '',
            status: lead.status || '',
            source: lead.source || '',
            notes: lead.notes || '',
            assigned_to: lead.assigned_to || '',
        };

        Object.keys(map).forEach((field) => {
            const input = elements.leadForm.querySelector(`[name="${field}"]`);
            if (input) {
                input.value = map[field];
            }
        });

        if (elements.leadFormHelper) {
            elements.leadFormHelper.textContent = 'Editando lead existente. Guarda para aplicar los cambios.';
        }
    }

    function openLeadEdit(leadId) {
        const lead = findLeadById(leadId);
        if (!lead) {
            showToast('No pudimos cargar el lead seleccionado', false);
            return;
        }
        leadFormState.mode = 'edit';
        leadFormState.currentHc = normalizeHcNumber(lead.hc_number || '');
        applyLeadToForm(lead);
        if (leadModals.form) {
            leadModals.form.show();
        }
    }

    function toggleLeadEditMode(showEdit) {
        const editElements = [
            elements.leadDetailEditActions,
            elements.leadDetailEditFooter,
            elements.leadDetailEditSection,
        ];
        const viewElements = [
            elements.leadDetailViewSection,
        ];
        editElements.forEach((item) => {
            if (item) {
                item.classList.toggle('d-none', !showEdit);
            }
        });
        viewElements.forEach((item) => {
            if (item) {
                item.classList.toggle('d-none', showEdit);
            }
        });
    }

    function populateLeadDetailSelects(lead) {
        const statusSelect = document.getElementById('lead-detail-status');
        if (statusSelect) {
            statusSelect.innerHTML = '<option value="">Seleccionar</option>';
            state.leadStatuses.forEach((status) => {
                const option = document.createElement('option');
                option.value = status;
                option.textContent = titleize(status);
                statusSelect.appendChild(option);
            });
            statusSelect.value = lead.status || '';
        }

        const assignSelect = document.getElementById('lead-detail-assigned');
        if (assignSelect) {
            assignSelect.innerHTML = '<option value="">Sin asignar</option>';
            state.assignableUsers.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.nombre || user.name || user.email || `ID ${user.id}`;
                assignSelect.appendChild(option);
            });
            assignSelect.value = lead.assigned_to || '';
        }
    }

    function showLeadDetail(profile) {
        if (!elements.leadDetailBody || !profile) {
            return;
        }
        const lead = profile.lead ? profile.lead : profile;
        const patient = profile.patient || {};
        const computed = profile.computed || {};
        const normalizedHc = normalizeHcNumber(lead.hc_number);
        leadDetailState.current = lead;

        if (elements.leadDetailId) {
            elements.leadDetailId.value = lead.id || '';
        }
        if (elements.leadDetailTitle) {
            const idLabel = lead.id || normalizedHc || '—';
            const nameLabel = lead.name || buildPatientName(patient) || 'Lead';
            elements.leadDetailTitle.textContent = `#${idLabel} - ${nameLabel}`;
        }

        const isPublic = lead.is_public === true || lead.is_public === 1 || lead.is_public === '1';
        const patientName = buildPatientName(patient);
        const patientAddress = pickValue(patient.address, patient.direccion, patient.domicilio);
        const patientCity = pickValue(patient.ciudad, patient.city);
        const patientState = pickValue(patient.state, patient.provincia, patient.region);
        const patientCountry = pickValue(patient.country, patient.pais);
        const patientZip = pickValue(patient.zip, patient.codigo_postal, patient.postal_code);
        const displayAddress = pickValue(computed.display_address, patientAddress);
        const viewMap = {
            'lead-view-name': lead.name || patientName || 'Sin nombre',
            'lead-view-position': lead.title || lead.position || '—',
            'lead-view-email': lead.email || '—',
            'lead-view-website': lead.website || '—',
            'lead-view-phone': lead.phone || '—',
            'lead-view-value': lead.lead_value || '—',
            'lead-view-company': lead.company || '—',
            'lead-view-address': displayAddress || '—',
            'lead-view-city': patientCity || '—',
            'lead-view-state': patientState || '—',
            'lead-view-country': patientCountry || '—',
            'lead-view-zip': patientZip || '—',
            'lead-view-source': lead.source ? titleize(lead.source) : '—',
            'lead-view-language': lead.default_language || 'System Default',
            'lead-view-assigned': lead.assigned_name || 'Sin asignar',
            'lead-view-tags': Array.isArray(lead.tags) ? lead.tags.join(', ') : (lead.tags || '—'),
            'lead-view-created': formatDate(lead.created_at, true) || '—',
            'lead-view-last-contact': formatDate(lead.last_contact, true) || '—',
            'lead-view-public': isPublic ? 'Yes' : 'No',
            'lead-view-description': lead.notes || '—',
        };

        Object.keys(viewMap).forEach((id) => {
            setTextContent(document.getElementById(id), viewMap[id]);
        });

        const statusElement = document.getElementById('lead-view-status');
        if (statusElement) {
            const statusLabel = titleize(lead.status || 'Sin estado');
            statusElement.innerHTML = lead.status
                ? `<span class="label label-default">${escapeHtml(statusLabel)}</span>`
                : '—';
        }

        if (elements.leadDetailNotesCount) {
            const noteCount = Number(lead.notes_count || 0);
            elements.leadDetailNotesCount.textContent = noteCount;
        }

        populateLeadDetailSelects(lead);
        const editMap = {
            'lead-detail-name': lead.name || patientName || '',
            'lead-detail-email': lead.email || '',
            'lead-detail-phone': lead.phone || '',
            'lead-detail-company': pickValue(patient.company, patient.workplace, lead.company) || '',
            'lead-detail-source': lead.source || '',
            'lead-detail-address': patientAddress || '',
            'lead-detail-city': patientCity || '',
            'lead-detail-state': patientState || '',
            'lead-detail-zip': patientZip || '',
            'lead-detail-description': lead.notes || '',
        };
        Object.keys(editMap).forEach((id) => {
            const input = document.getElementById(id);
            if (input) {
                input.value = editMap[id];
            }
        });

        if (elements.leadDetailConvert) {
            elements.leadDetailConvert.dataset.leadHc = normalizedHc || '';
            elements.leadDetailConvert.disabled = !normalizedHc;
        }

        toggleLeadEditMode(false);

        if (leadModals.detail) {
            leadModals.detail.show();
        }

        loadLeadProjects(lead);
    }

    function renderLeadProjects(projects) {
        if (!elements.leadProjectsList) {
            return;
        }

        elements.leadProjectsList.innerHTML = '';

        if (!Array.isArray(projects) || projects.length === 0) {
            if (elements.leadProjectsEmpty) {
                elements.leadProjectsEmpty.classList.remove('d-none');
            }
            return;
        }

        if (elements.leadProjectsEmpty) {
            elements.leadProjectsEmpty.classList.add('d-none');
        }

        projects.forEach((project) => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-start gap-2 flex-wrap';

            const title = project.title || `Proyecto #${project.id}`;
            const status = project.status ? titleize(project.status) : 'Sin estado';
            const metaParts = [];
            if (project.hc_number) {
                metaParts.push(`HC ${project.hc_number}`);
            }
            if (project.form_id) {
                metaParts.push(`Form ${project.form_id}`);
            }

            item.innerHTML = `
                <div>
                    <div class="fw-semibold">${escapeHtml(title)}</div>
                    <div class="small text-muted">${escapeHtml(status)}${metaParts.length ? ` · ${escapeHtml(metaParts.join(' · '))}` : ''}</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="/crm?tab=projects&project_id=${encodeURIComponent(project.id)}" target="_blank" rel="noopener">
                        Abrir
                    </a>
                </div>
            `;

            elements.leadProjectsList.appendChild(item);
        });
    }

    function loadLeadProjects(lead) {
        if (!lead || !elements.leadProjectsList) {
            return Promise.resolve();
        }

        const params = new URLSearchParams();
        if (lead.id) {
            params.set('lead_id', lead.id);
        }
        const normalizedHc = normalizeHcNumber(lead.hc_number || '');
        if (normalizedHc) {
            params.set('hc', normalizedHc);
        }

        if ([...params.keys()].length === 0) {
            renderLeadProjects([]);
            return Promise.resolve();
        }

        return request(`/projects?${params.toString()}`)
            .then((data) => {
                renderLeadProjects(Array.isArray(data.data) ? data.data : []);
            })
            .catch((error) => {
                console.error('No se pudieron cargar los proyectos del lead', error);
                renderLeadProjects([]);
            });
    }

    function createLeadProject(lead) {
        if (!lead) {
            return;
        }

        const hcNumber = normalizeHcNumber(lead.hc_number || '');
        const title = lead.name ? `Caso ${lead.name}` : (hcNumber ? `Caso HC ${hcNumber}` : 'Nuevo caso');

        request('/projects/create', {
            method: 'POST',
            body: {
                title,
                lead_id: lead.id,
                hc_number: hcNumber || null,
                source_module: 'crm',
                source_ref_id: lead.id ? String(lead.id) : null,
            },
        })
            .then((data) => {
                const project = data.data || {};
                showToast(data.linked ? 'Caso vinculado' : 'Caso creado', true);
                loadLeadProjects(lead);
                if (project.id) {
                    window.open(`/crm?tab=projects&project_id=${project.id}`, '_blank', 'noopener');
                }
            })
            .catch((error) => {
                console.error('No se pudo crear el caso del lead', error);
                showToast(error.message || 'No se pudo crear el caso', false);
            });
    }

    async function openLeadProfile(leadId) {
        if (!leadId) {
            return;
        }
        const fallbackLead = findLeadById(leadId);
        try {
            const payload = await request(`/crm/leads/${leadId}/profile`);
            showLeadDetail(payload.data || fallbackLead);
        } catch (error) {
            console.error('No se pudo cargar el perfil del lead', error);
            if (fallbackLead) {
                showLeadDetail(fallbackLead);
            }
            showToast(error.message || 'No se pudo cargar el perfil del lead', false);
        }
    }

    function fillLeadEmailForm(draft, leadId) {
        if (!elements.leadEmailForm) {
            return;
        }
        elements.leadEmailForm.dataset.leadId = leadId ? String(leadId) : '';
        elements.leadEmailForm.dataset.status = draft && draft.context ? (draft.context.status || '') : '';

        if (elements.leadEmailTo) {
            elements.leadEmailTo.value = (draft && draft.to) || '';
        }
        if (elements.leadEmailSubject) {
            elements.leadEmailSubject.value = (draft && draft.subject) || '';
        }
        if (elements.leadEmailBody) {
            elements.leadEmailBody.value = (draft && draft.body) || '';
        }
    }

    function openLeadEmail(leadId) {
        if (!leadId) {
            return;
        }
        request(`/crm/leads/${leadId}/mail/compose`)
            .then((data) => {
                const draft = data.data || {};
                fillLeadEmailForm(draft, leadId);
                if (leadModals.email) {
                    leadModals.email.show();
                }
            })
            .catch((error) => {
                console.error('No se pudo preparar el correo', error);
                showToast(error.message || 'No se pudo preparar el correo', false);
            });
    }

    function fillConvertForm(lead, resetFields) {
        if (!elements.convertForm) {
            return;
        }
        if (elements.convertLeadHc) {
            elements.convertLeadHc.value = lead.hc_number || '';
        }
        const normalizedHc = normalizeHcNumber(lead.hc_number);
        const label = lead.name ? `${lead.name} · ${normalizedHc || 'HC sin registrar'}` : (normalizedHc ? `HC ${normalizedHc}` : 'Lead sin nombre');
        if (elements.convertSelected) {
            elements.convertSelected.textContent = label;
        }
        if (!normalizedHc) {
            if (elements.convertHelper) {
                elements.convertHelper.textContent = 'El lead no tiene historia clínica registrada. Actualiza el lead antes de convertir.';
            }
            if (elements.convertSubmit) {
                elements.convertSubmit.disabled = true;
            }
            return;
        }
        if (elements.convertHelper) {
            elements.convertHelper.textContent = 'Completa los datos y confirma la conversión.';
        }
        if (elements.convertSubmit) {
            elements.convertSubmit.disabled = false;
        }
        if (resetFields !== false) {
            const defaults = {
                customer_name: lead.name || '',
                customer_email: lead.email || '',
                customer_phone: lead.phone || '',
            };
            Object.keys(defaults).forEach((field) => {
                const input = elements.convertForm.querySelector(`[name="${field}"]`);
                if (input) {
                    input.value = defaults[field];
                }
            });
        }
    }

    function syncConvertFormSelection() {
        if (!elements.convertForm) {
            return;
        }
        const hcNumber = elements.convertLeadHc ? normalizeHcNumber(elements.convertLeadHc.value) : '';
        if (!hcNumber) {
            disableConvertForm();
            return;
        }
        const lead = findLeadByHcNumber(hcNumber);
        if (!lead) {
            disableConvertForm();
            return;
        }
        fillConvertForm(lead, false);
    }

    function disableTicketReplyForm() {
        if (!elements.ticketReplyForm) {
            return;
        }
        elements.ticketReplyId.value = '';
        elements.ticketReplySelected.textContent = 'Sin selección';
        elements.ticketReplyHelper.textContent = 'Selecciona un ticket en la tabla para responder.';
        elements.ticketReplyMessage.value = '';
        elements.ticketReplyMessage.disabled = true;
        elements.ticketReplyStatus.disabled = true;
        elements.ticketReplySubmit.disabled = true;
    }

    function applyTicketReply(ticket, resetMessage) {
        elements.ticketReplyId.value = ticket.id;
        elements.ticketReplySelected.textContent = ticket.subject || `Ticket #${ticket.id}`;
        elements.ticketReplyHelper.textContent = `Respondiendo ticket "${ticket.subject || ticket.id}"`;
        elements.ticketReplyMessage.disabled = false;
        if (resetMessage !== false) {
            elements.ticketReplyMessage.value = '';
        }
        if (elements.ticketReplyStatus) {
            elements.ticketReplyStatus.disabled = false;
            if (state.ticketStatuses.includes(ticket.status)) {
                elements.ticketReplyStatus.value = ticket.status;
            }
        }
        elements.ticketReplySubmit.disabled = false;
    }

    function syncTicketReplySelection() {
        if (!elements.ticketReplyForm) {
            return;
        }
        const ticketId = elements.ticketReplyId.value;
        if (!ticketId) {
            disableTicketReplyForm();
            return;
        }
        const ticket = findTicketById(ticketId);
        if (!ticket) {
            disableTicketReplyForm();
            return;
        }
        applyTicketReply(ticket, false);
    }

    function loadLeads() {
        return request('/crm/leads')
            .then((data) => {
                state.leads = mapLeads(data.data);
                selectedLeads.clear();
                leadTableState.page = 1;
                renderLeads();
            })
            .catch((error) => {
                console.error('Error cargando leads', error);
                showToast(error.message || 'No se pudieron cargar los leads', false);
            });
    }

    function loadProjects() {
        return request('/projects')
            .then((data) => {
                state.projects = Array.isArray(data.data) ? data.data : [];
                renderProjects();
            })
            .catch((error) => {
                console.error('Error cargando proyectos', error);
                showToast(error.message || 'No se pudieron cargar los proyectos', false);
            });
    }

    function loadTasks() {
        return request('/crm/tasks')
            .then((data) => {
                state.tasks = Array.isArray(data.data) ? data.data : [];
                renderTasks();
            })
            .catch((error) => {
                console.error('Error cargando tareas', error);
                showToast(error.message || 'No se pudieron cargar las tareas', false);
            });
    }

    function loadTickets() {
        return request('/crm/tickets')
            .then((data) => {
                state.tickets = Array.isArray(data.data) ? data.data : [];
                renderTickets();
            })
            .catch((error) => {
                console.error('Error cargando tickets', error);
                showToast(error.message || 'No se pudieron cargar los tickets', false);
            });
    }

    function serializeNumber(value) {
        const trimmed = String(value || '').trim();
        if (!trimmed) {
            return null;
        }
        const parsed = Number(trimmed);
        return Number.isNaN(parsed) ? null : parsed;
    }

    function normalizeHcNumber(value) {
        return String(value || '').trim().toUpperCase();
    }

    function findLeadByHcNumber(hcNumber) {
        const normalized = normalizeHcNumber(hcNumber);
        if (!normalized) {
            return null;
        }
        return (
            state.leads.find(
                (lead) => normalizeHcNumber(lead.hc_number) === normalized
            ) || null
        );
    }

    function findLeadById(id) {
        if (!id) {
            return null;
        }
        return state.leads.find((lead) => String(lead.id) === String(id)) || null;
    }

    function normalizeLead(lead) {
        if (!lead || typeof lead !== 'object') {
            return {};
        }
        const normalized = { ...lead };
        normalized.hc_number = normalizeHcNumber(lead.hc_number ?? lead.hcNumber ?? '');
        if (!normalized.name && (normalized.first_name || normalized.last_name)) {
            normalized.name = `${normalized.first_name || ''} ${normalized.last_name || ''}`.trim();
        }
        return normalized;
    }

    function mapLeads(leads) {
        return Array.isArray(leads) ? leads.map((lead) => normalizeLead(lead)) : [];
    }

    function mapProposals(proposals) {
        if (!Array.isArray(proposals)) {
            return [];
        }

        return proposals.map((proposal) => {
            const clone = { ...proposal };
            clone.total = Number(clone.total || 0);
            clone.subtotal = Number(clone.subtotal || 0);
            clone.tax_total = Number(clone.tax_total || 0);
            clone.discount_total = Number(clone.discount_total || 0);
            clone.tax_rate = Number(clone.tax_rate || 0);
            clone.status = clone.status || 'draft';
            clone.currency = clone.currency || 'USD';
            clone.items = Array.isArray(clone.items) ? clone.items : [];
            const parsedItemsCount = Number(clone.items_count);
            clone.items_count = Number.isFinite(parsedItemsCount) ? parsedItemsCount : clone.items.length;
            return clone;
        });
    }

    function getFilteredProposals() {
        const search = (proposalFilters.search || '').toLowerCase();
        const status = proposalFilters.status || '';

        return state.proposals.filter((proposal) => {
            const matchesStatus = status ? proposal.status === status : true;
            const matchesSearch = !search
                ? true
                : [proposal.proposal_number, proposal.title, proposal.lead_name, proposal.customer_name]
                    .some((value) => (value || '').toLowerCase().includes(search));
            return matchesStatus && matchesSearch;
        });
    }

    function proposalStatusBadge(status) {
        const map = {
            draft: 'bg-secondary',
            open: 'bg-primary',
            sent: 'bg-info',
            revised: 'bg-warning text-dark',
            accepted: 'bg-success',
            declined: 'bg-danger',
            expired: 'bg-dark',
        };
        const className = map[status] || 'bg-secondary';
        const badge = document.createElement('span');
        badge.className = `badge ${className}`;
        badge.textContent = titleize(status);
        return badge;
    }

    function renderProposals() {
        if (!elements.proposalTableBody) {
            return;
        }

        clearContainer(elements.proposalTableBody);
        const proposals = getFilteredProposals();

        if (!proposals.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'Aún no hay propuestas registradas.';
            row.appendChild(cell);
            elements.proposalTableBody.appendChild(row);
            setProposalPreview(null);
            return;
        }

        const hasSelected = proposals.some((p) => String(p.id) === String(proposalUIState.selectedId));
        if (!hasSelected) {
            proposalUIState.selectedId = null;
        }

        proposals.forEach((proposal) => {
            const row = document.createElement('tr');
            row.classList.add('proposal-row');
            row.dataset.proposalId = proposal.id;
            if (proposalUIState.selectedId && Number(proposalUIState.selectedId) === Number(proposal.id)) {
                row.classList.add('table-active');
            }

            const numberCell = document.createElement('td');
            const numberLink = document.createElement('a');
            numberLink.href = '#';
            numberLink.className = 'proposal-view-btn fw-semibold text-decoration-none';
            numberLink.dataset.proposalId = proposal.id;
            numberLink.textContent = proposal.proposal_number || `#${proposal.id}`;
            numberCell.appendChild(numberLink);
            const itemsBadge = document.createElement('span');
            itemsBadge.className = 'badge bg-light text-muted ms-2';
            itemsBadge.textContent = `${proposal.items_count ?? 0} ítems`;
            numberCell.appendChild(itemsBadge);
            row.appendChild(numberCell);

            const subjectCell = document.createElement('td');
            subjectCell.textContent = proposal.title || '—';
            row.appendChild(subjectCell);

            const leadCell = document.createElement('td');
            leadCell.textContent = proposal.lead_name || proposal.customer_name || '-';
            row.appendChild(leadCell);

            const totalCell = document.createElement('td');
            totalCell.className = 'text-end';
            totalCell.textContent = formatCurrency(proposal.total);
            row.appendChild(totalCell);

            const statusCell = document.createElement('td');
            statusCell.appendChild(proposalStatusBadge(proposal.status));
            row.appendChild(statusCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const actionsWrapper = document.createElement('div');
            actionsWrapper.className = 'd-flex justify-content-end align-items-center gap-2';
            const viewBtn = document.createElement('button');
            viewBtn.className = 'btn btn-outline-primary btn-xs proposal-view-btn';
            viewBtn.dataset.proposalId = proposal.id;
            viewBtn.innerHTML = '<i class="mdi mdi-eye"></i>';
            actionsWrapper.appendChild(viewBtn);
            if (canManageProjects) {
                const select = createStatusSelect(state.proposalStatuses, proposal.status);
                select.classList.add('form-select-sm', 'proposal-status-select');
                select.dataset.proposalId = proposal.id;
                actionsWrapper.appendChild(select);
            }
            actionCell.appendChild(actionsWrapper);
            row.appendChild(actionCell);

            elements.proposalTableBody.appendChild(row);
        });

        if (!proposalUIState.selectedId && proposals.length) {
            setSelectedProposal(proposals[0].id);
        }
    }

    function loadProposals() {
        const params = new URLSearchParams();
        if (elements.proposalStatusFilter && elements.proposalStatusFilter.value) {
            params.set('status', elements.proposalStatusFilter.value);
        }

        const url = params.toString() ? `/crm/proposals?${params.toString()}` : '/crm/proposals';

        return request(url)
            .then((data) => {
                state.proposals = mapProposals(data.data);
                renderProposals();
            })
            .catch((error) => {
                console.error('Error cargando propuestas', error);
                showToast(error.message || 'No se pudieron cargar las propuestas', false);
            });
    }

    function updateProposalStatus(proposalId, status, onSuccess) {
        if (!proposalId || !status) {
            return;
        }
        request('/crm/proposals/status', { method: 'POST', body: { proposal_id: proposalId, status } })
            .then((data) => {
                const updated = data.data;
                const index = state.proposals.findIndex((proposal) => Number(proposal.id) === Number(updated.id));
                if (index >= 0) {
                    state.proposals[index] = updated;
                    state.proposals = mapProposals(state.proposals);
                    renderProposals();
                } else {
                    loadProposals();
                }
                if (typeof onSuccess === 'function') {
                    onSuccess(updated);
                }
                showToast('Estado actualizado', true);
            })
            .catch((error) => {
                console.error('Error actualizando estado de propuesta', error);
                showToast(error.message || 'No se pudo actualizar el estado', false);
                loadProposals();
            });
    }

    function setProposalDetailLoading(isLoading) {
        if (elements.proposalDetailLoading) {
            elements.proposalDetailLoading.classList.toggle('d-none', !isLoading);
        }
        if (elements.proposalDetailContent) {
            elements.proposalDetailContent.classList.toggle('d-none', isLoading || !proposalDetailState.current);
        }
        if (elements.proposalDetailEmpty) {
            elements.proposalDetailEmpty.classList.toggle('d-none', isLoading || Boolean(proposalDetailState.current));
        }
    }

    function syncStatusPill(element, status) {
        if (!element) {
            return;
        }
        const badge = proposalStatusBadge(status || 'draft');
        element.className = badge.className;
        element.textContent = badge.textContent;
    }

    function populateProposalStatusSelect(select, status, proposalId) {
        if (!select) {
            return;
        }
        clearContainer(select);
        const statuses = Array.isArray(state.proposalStatuses) ? state.proposalStatuses : [];
        statuses.forEach((optionValue) => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = titleize(optionValue);
            select.appendChild(option);
        });
        select.value = status || '';
        select.dataset.proposalId = proposalId || '';
        select.disabled = !canManageProjects;
    }

    function renderProposalTimeline(proposal) {
        if (!elements.proposalDetailTimeline) {
            return;
        }
        clearContainer(elements.proposalDetailTimeline);
        const timeline = [
            { label: 'Creada', value: proposal.created_at, withTime: true },
            { label: 'Enviada', value: proposal.sent_at, withTime: true },
            { label: 'Aceptada', value: proposal.accepted_at, withTime: true },
            { label: 'Declinada', value: proposal.rejected_at, withTime: true },
            { label: 'Vence', value: proposal.valid_until, withTime: false },
        ].filter((entry) => entry.value);

        if (!timeline.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted mb-0';
            empty.textContent = 'Sin actividad registrada';
            elements.proposalDetailTimeline.appendChild(empty);
            return;
        }

        timeline.forEach((entry) => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center small mb-1';
            const label = document.createElement('span');
            label.className = 'text-muted';
            label.textContent = entry.label;
            const date = document.createElement('span');
            date.className = 'fw-semibold';
            date.textContent = formatDate(entry.value, Boolean(entry.withTime));
            row.appendChild(label);
            row.appendChild(date);
            elements.proposalDetailTimeline.appendChild(row);
        });
    }

    function renderProposalDetailItems(items) {
        if (!elements.proposalDetailItemsBody) {
            return;
        }
        clearContainer(elements.proposalDetailItemsBody);
        if (!items || !items.length) {
            const row = document.createElement('tr');
            row.className = 'text-center text-muted';
            row.innerHTML = '<td colspan="5">Sin ítems</td>';
            elements.proposalDetailItemsBody.appendChild(row);
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('tr');
            const discountValue = Number(item.discount_percent || 0);
            row.innerHTML = `
                <td>${item.description || ''}</td>
                <td class="text-center">${Number(item.quantity || 0).toFixed(2)}</td>
                <td class="text-end">${formatCurrency(item.unit_price || 0)}</td>
                <td class="text-end">${discountValue ? `${discountValue.toFixed(2)}%` : '—'}</td>
                <td class="text-end">${formatCurrency(calculateLineTotal(item))}</td>
            `;
            elements.proposalDetailItemsBody.appendChild(row);
        });
    }

    function renderProposalDetail() {
        const proposal = proposalDetailState.current;
        if (elements.proposalDetailEmpty) {
            elements.proposalDetailEmpty.classList.toggle('d-none', Boolean(proposal));
        }
        if (!proposal) {
            if (elements.proposalDetailContent) {
                elements.proposalDetailContent.classList.add('d-none');
            }
            return;
        }

        if (elements.proposalDetailContent) {
            elements.proposalDetailContent.classList.remove('d-none');
        }

        setTextContent(elements.proposalDetailTitle, proposal.title || 'Propuesta');
        setTextContent(
            elements.proposalDetailSubtitle,
            `${proposal.proposal_number || `#${proposal.id}`} · ${proposal.currency}`.trim(),
            proposal.proposal_number || `#${proposal.id}`
        );
        syncStatusPill(elements.proposalDetailStatus, proposal.status);
        setTextContent(elements.proposalDetailLead, proposal.lead_name || proposal.customer_name || '—');
        setTextContent(elements.proposalDetailValidUntil, formatDate(proposal.valid_until, false));
        setTextContent(elements.proposalDetailCreated, formatDate(proposal.created_at, true));
        setTextContent(elements.proposalDetailTaxRate, proposal.tax_rate ? `${proposal.tax_rate}%` : '—');
        setTextContent(elements.proposalDetailItemsCount, `${proposal.items_count || proposal.items.length || 0} ítems`, '0 ítems');
        setTextContent(elements.proposalDetailNotes, proposal.notes || '—');
        setTextContent(elements.proposalDetailTerms, proposal.terms || '—');

        if (elements.proposalDetailSubtotal) {
            elements.proposalDetailSubtotal.textContent = formatCurrency(proposal.subtotal || 0);
        }
        if (elements.proposalDetailDiscount) {
            elements.proposalDetailDiscount.textContent = formatCurrency(proposal.discount_total || 0);
        }
        if (elements.proposalDetailTax) {
            elements.proposalDetailTax.textContent = formatCurrency(proposal.tax_total || 0);
        }
        if (elements.proposalDetailTotal) {
            elements.proposalDetailTotal.textContent = formatCurrency(proposal.total || 0);
        }

        populateProposalStatusSelect(elements.proposalDetailStatusSelect, proposal.status, proposal.id);
        renderProposalDetailItems(proposal.items);
        renderProposalTimeline(proposal);
    }

    function openProposalDetail(proposalId) {
        if (!proposalId) {
            showToast('No encontramos la propuesta seleccionada', false);
            return;
        }
        setSelectedProposal(proposalId);
        proposalDetailState.current = null;
        setProposalDetailLoading(true);
        if (proposalModals.detail) {
            proposalModals.detail.show();
        }
        request(`/crm/proposals/${proposalId}`)
            .then((response) => {
                const proposals = mapProposals([response.data]);
                proposalDetailState.current = proposals[0] || null;
                renderProposalDetail();
                setProposalDetailLoading(false);
            })
            .catch((error) => {
                console.error('No se pudo cargar la propuesta', error);
                showToast(error.message || 'No se pudo cargar la propuesta', false);
                setProposalDetailLoading(false);
            });
    }

    function resetProposalBuilder() {
        proposalBuilder.items = [];
        if (elements.proposalLeadSelect) elements.proposalLeadSelect.value = '';
        if (elements.proposalTitle) elements.proposalTitle.value = '';
        if (elements.proposalValidUntil) elements.proposalValidUntil.value = '';
        if (elements.proposalTaxRate) elements.proposalTaxRate.value = '0';
        if (elements.proposalNotes) elements.proposalNotes.value = '';
        renderProposalItems();
        updateProposalTotals();
    }

    function addProposalItem(item = {}) {
        proposalBuilder.items.push({
            description: item.description || '',
            quantity: Number(item.quantity || 1),
            unit_price: Number(item.unit_price || 0),
            discount_percent: Number(item.discount_percent || 0),
            code_id: item.code_id || null,
            package_id: item.package_id || null,
        });
        renderProposalItems();
        updateProposalTotals();
    }

    function removeProposalItem(index) {
        proposalBuilder.items.splice(index, 1);
        renderProposalItems();
        updateProposalTotals();
    }

    function renderProposalItems() {
        if (!elements.proposalItemsBody) {
            return;
        }

        clearContainer(elements.proposalItemsBody);

        if (!proposalBuilder.items.length) {
            const row = document.createElement('tr');
            row.className = 'text-center text-muted';
            row.innerHTML = '<td colspan="6">Agrega un paquete o código para iniciar</td>';
            elements.proposalItemsBody.appendChild(row);
            return;
        }

        proposalBuilder.items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="form-control form-control-sm" value="${item.description}"></td>
                <td><input type="number" class="form-control form-control-sm text-center" step="0.01" min="0.01" value="${item.quantity}"></td>
                <td><input type="number" class="form-control form-control-sm text-center" step="0.01" value="${item.unit_price}"></td>
                <td><input type="number" class="form-control form-control-sm text-center" step="0.01" min="0" max="100" value="${item.discount_percent}"></td>
                <td class="text-end">${formatCurrency(calculateLineTotal(item))}</td>
                <td class="text-end">
                    <button class="btn btn-outline-danger btn-xs" data-index="${index}">
                        <i class="mdi mdi-delete"></i>
                    </button>
                </td>
            `;

            const [descInput, qtyInput, priceInput, discountInput] = row.querySelectorAll('input');
            descInput.addEventListener('input', (event) => {
                proposalBuilder.items[index].description = event.target.value;
            });
            qtyInput.addEventListener('input', (event) => {
                proposalBuilder.items[index].quantity = Number(event.target.value || 0);
                updateProposalTotals();
                renderProposalItems();
            });
            priceInput.addEventListener('input', (event) => {
                proposalBuilder.items[index].unit_price = Number(event.target.value || 0);
                updateProposalTotals();
                renderProposalItems();
            });
            discountInput.addEventListener('input', (event) => {
                proposalBuilder.items[index].discount_percent = Number(event.target.value || 0);
                updateProposalTotals();
                renderProposalItems();
            });

            const removeButton = row.querySelector('button');
            removeButton.addEventListener('click', (event) => {
                event.preventDefault();
                removeProposalItem(index);
            });

            elements.proposalItemsBody.appendChild(row);
        });
    }

    function calculateLineTotal(item) {
        const quantity = Number(item.quantity || 0);
        const unitPrice = Number(item.unit_price || 0);
        const discount = Number(item.discount_percent || 0);
        let line = quantity * unitPrice;
        line -= line * (discount / 100);
        return line;
    }

    function updateProposalTotals() {
        const subtotal = proposalBuilder.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
        const discount = proposalBuilder.items.reduce((sum, item) => {
            const line = item.quantity * item.unit_price;
            return sum + (line * (item.discount_percent / 100));
        }, 0);
        const taxable = Math.max(0, subtotal - discount);
        const taxRate = elements.proposalTaxRate ? Number(elements.proposalTaxRate.value || 0) : 0;
        const tax = taxable * (taxRate / 100);
        const total = taxable + tax;

        if (elements.proposalSubtotal) elements.proposalSubtotal.textContent = formatCurrency(subtotal);
        if (elements.proposalTax) elements.proposalTax.textContent = formatCurrency(tax);
        if (elements.proposalTotal) elements.proposalTotal.textContent = formatCurrency(total);
    }

    function collectProposalPayload() {
        const payload = {
            lead_id: serializeNumber(elements.proposalLeadSelect ? elements.proposalLeadSelect.value : '') || undefined,
            title: elements.proposalTitle ? String(elements.proposalTitle.value || '').trim() : '',
            valid_until: elements.proposalValidUntil ? String(elements.proposalValidUntil.value || '').trim() : null,
            tax_rate: elements.proposalTaxRate ? Number(elements.proposalTaxRate.value || 0) : 0,
            notes: elements.proposalNotes ? String(elements.proposalNotes.value || '').trim() : null,
            items: proposalBuilder.items.map((item) => ({
                description: item.description,
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount_percent: item.discount_percent,
                code_id: item.code_id,
                package_id: item.package_id,
            })),
        };

        return payload;
    }

    function saveProposal() {
        const payload = collectProposalPayload();
        if (!payload.lead_id) {
            showToast('Selecciona un lead', false);
            return;
        }
        if (!payload.title) {
            showToast('Asigna un título a la propuesta', false);
            return;
        }
        if (!payload.items.length) {
            showToast('Agrega al menos un ítem', false);
            return;
        }

        request('/crm/proposals', { method: 'POST', body: payload })
            .then((response) => {
                showToast('Propuesta creada', true);
                resetProposalBuilder();
                const created = response.data;
                state.proposals.unshift(created);
                state.proposals = mapProposals(state.proposals);
                renderProposals();
            })
            .catch((error) => {
                console.error('No se pudo crear la propuesta', error);
                showToast(error.message || 'No se pudo crear la propuesta', false);
            });
    }

    function loadProposalPackages(force) {
        if (!force && proposalBuilder.packages.length) {
            renderProposalPackages(proposalBuilder.packages);
            return Promise.resolve();
        }

        return request('/codes/api/packages?active=1&limit=100')
            .then((data) => {
                proposalBuilder.packages = Array.isArray(data.data) ? data.data : [];
                const currentTerm = elements.proposalPackageSearch ? elements.proposalPackageSearch.value : '';
                renderProposalPackages(proposalBuilder.packages, currentTerm);
            })
            .catch((error) => {
                console.error('No se pudieron obtener los paquetes', error);
                showToast(error.message || 'No se pudieron cargar los paquetes', false);
            });
    }

    function renderProposalPackages(packages, searchTerm = '') {
        if (!elements.proposalPackageList) {
            return;
        }

        clearContainer(elements.proposalPackageList);

        const normalized = searchTerm ? searchTerm.toLowerCase() : '';
        const filtered = packages.filter((pkg) => {
            if (!normalized) {
                return true;
            }
            const haystack = `${pkg.name ?? ''} ${pkg.description ?? ''}`.toLowerCase();
            return haystack.includes(normalized);
        });

        if (!filtered.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted text-center py-3';
            empty.textContent = 'No se encontraron paquetes';
            elements.proposalPackageList.appendChild(empty);
            return;
        }

        filtered.forEach((pkg) => {
            const col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = `
                <div class="border rounded p-3 h-100">
                    <h6 class="mb-1">${pkg.name ?? 'Paquete'}</h6>
                    <p class="text-muted small mb-2">${pkg.description ?? 'Sin descripción'}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-dark">${pkg.items_count ?? pkg.total_items ?? 0} ítems</span>
                        <button class="btn btn-sm btn-primary">Agregar</button>
                    </div>
                </div>
            `;
            const addButton = col.querySelector('button');
            addButton.addEventListener('click', () => {
                addPackageToProposal(pkg);
                if (proposalModals.package) {
                    proposalModals.package.hide();
                }
            });
            elements.proposalPackageList.appendChild(col);
        });
    }

    function addPackageToProposal(pkg) {
        if (!pkg || !Array.isArray(pkg.items)) {
            return;
        }

        pkg.items.forEach((item) => {
            addProposalItem({
                description: item.description,
                quantity: item.quantity || 1,
                unit_price: item.unit_price || 0,
                discount_percent: item.discount_percent || 0,
                code_id: item.code_id || null,
                package_id: pkg.id,
            });
        });
        updateProposalTotals();
    }

    function openPackageModal() {
        if (!proposalModals.package) {
            return;
        }
        loadProposalPackages().then(() => {
            if (elements.proposalPackageSearch) {
                elements.proposalPackageSearch.value = '';
            }
            proposalModals.package.show();
        });
    }

    function renderProposalCodeResults(results) {
        if (!elements.proposalCodeResults) {
            return;
        }

        clearContainer(elements.proposalCodeResults);

        if (!results.length) {
            const row = document.createElement('tr');
            row.className = 'text-center text-muted';
            row.innerHTML = '<td colspan="4">Sin resultados</td>';
            elements.proposalCodeResults.appendChild(row);
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
                    <button class="btn btn-primary btn-xs"><i class="mdi mdi-plus"></i></button>
                </td>
            `;
            const button = row.querySelector('button');
            button.addEventListener('click', () => {
                addProposalItem({
                    description: `${code.codigo} - ${code.descripcion ?? ''}`,
                    quantity: 1,
                    unit_price: price,
                    code_id: code.id,
                });
                if (proposalModals.code) {
                    proposalModals.code.hide();
                }
            });
            elements.proposalCodeResults.appendChild(row);
        });
    }

    function searchProposalCodes() {
        if (!elements.proposalCodeSearchInput) {
            return;
        }
        const query = elements.proposalCodeSearchInput.value.trim();
        if (!query) {
            showToast('Ingresa un término de búsqueda', false);
            return;
        }
        const url = `/codes/api/search?q=${encodeURIComponent(query)}`;

        request(url)
            .then((data) => {
                renderProposalCodeResults(data.data || []);
            })
            .catch((error) => {
                console.error('No se pudieron buscar los códigos', error);
                showToast(error.message || 'No se pudo buscar', false);
            });
    }

    function openProposalCodeModal() {
        if (!proposalModals.code) {
            return;
        }
        if (elements.proposalCodeSearchInput) {
            elements.proposalCodeSearchInput.value = '';
        }
        if (elements.proposalCodeResults) {
            elements.proposalCodeResults.innerHTML = '<tr class="text-center text-muted"><td colspan="4">Inicia una búsqueda</td></tr>';
        }
        proposalModals.code.show();
    }

    if (elements.leadForm && canManageLeads) {
        elements.leadForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.leadForm);
            const firstName = String(formData.get('first_name') || '').trim();
            const lastName = String(formData.get('last_name') || '').trim();
            const fullNameInput = String(formData.get('name') || '').trim();
            const composedName = fullNameInput || `${firstName} ${lastName}`.trim();

            const payload = { name: composedName };
            if (!payload.name) {
                showToast('El nombre es obligatorio', false);
                return;
            }

            if (firstName) {
                payload.first_name = firstName;
            }
            if (lastName) {
                payload.last_name = lastName;
            }
            const isEdit = elements.leadForm.dataset.mode === 'edit' && leadFormState.currentHc;
            const hcFromInput = normalizeHcNumber(formData.get('hc_number'));
            const hcNumber = isEdit ? (leadFormState.currentHc || hcFromInput) : hcFromInput;
            if (!hcNumber) {
                showToast('La historia clínica es obligatoria', false);
                return;
            }
            if (!isEdit) {
                payload.hc_number = hcNumber;
            }
            const email = String(formData.get('email') || '').trim();
            if (email) {
                payload.email = email;
            }
            const phone = String(formData.get('phone') || '').trim();
            if (phone) {
                payload.phone = phone;
            }
            const status = String(formData.get('status') || '').trim();
            if (status) {
                payload.status = status;
            }
            const source = String(formData.get('source') || '').trim();
            if (source) {
                payload.source = source;
            }
            const notes = String(formData.get('notes') || '').trim();
            if (notes) {
                payload.notes = notes;
            }
            const assignedTo = serializeNumber(formData.get('assigned_to'));
            if (assignedTo) {
                payload.assigned_to = assignedTo;
            }

            const endpoint = isEdit ? '/crm/leads/update' : '/crm/leads';
            const successMessage = isEdit ? 'Lead actualizado correctamente' : 'Lead creado correctamente';
            const body = isEdit ? { ...payload, hc_number: leadFormState.currentHc || hcNumber } : payload;

            request(endpoint, { method: 'POST', body })
                .then(() => {
                    showToast(successMessage, true);
                    resetLeadForm();
                    return loadLeads();
                })
                .catch((error) => {
                    console.error('No se pudo guardar el lead', error);
                    showToast(error.message || 'No se pudo guardar el lead', false);
                });
        });
    }

    if (elements.leadEmailForm && canManageLeads) {
        elements.leadEmailForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const leadId = serializeNumber(elements.leadEmailForm.dataset.leadId);
            const status = elements.leadEmailForm.dataset.status || '';
            const to = (elements.leadEmailTo && elements.leadEmailTo.value) ? elements.leadEmailTo.value.trim() : '';
            const subject = (elements.leadEmailSubject && elements.leadEmailSubject.value)
                ? elements.leadEmailSubject.value.trim()
                : '';
            const body = (elements.leadEmailBody && elements.leadEmailBody.value) ? elements.leadEmailBody.value.trim() : '';
            if (!leadId) {
                showToast('Selecciona un lead antes de enviar', false);
                return;
            }
            if (!to || !subject || !body) {
                showToast('Completa para, asunto y mensaje', false);
                return;
            }
            request(`/crm/leads/${leadId}/mail/send-template`, { method: 'POST', body: { status, to, subject, body } })
                .then(() => {
                    showToast('Correo enviado', true);
                    if (leadModals.email) {
                        leadModals.email.hide();
                    }
                })
                .catch((error) => {
                    console.error('No se pudo enviar el correo', error);
                    showToast(error.message || 'No se pudo enviar el correo', false);
                });
        });
    }

    if (elements.leadStatusSummary) {
        elements.leadStatusSummary.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-status-filter]');
            if (!button) {
                return;
            }
            const status = button.dataset.statusFilter || '';
            leadFilters.status = status === 'sin_estado' ? 'sin_estado' : status;
            syncLeadFiltersUI();
            renderLeads();
        });
    }

    if (elements.leadSearchInput) {
        let searchTimeout;
        elements.leadSearchInput.addEventListener('input', () => {
            const value = elements.leadSearchInput.value || '';
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                leadFilters.search = value.trim();
                renderLeads();
            }, 200);
        });
    }

    if (elements.leadFilterStatus) {
        elements.leadFilterStatus.addEventListener('change', () => {
            leadFilters.status = elements.leadFilterStatus.value || '';
            leadTableState.page = 1;
            renderLeads();
        });
    }

    if (elements.leadFilterSource) {
        elements.leadFilterSource.addEventListener('change', () => {
            leadFilters.source = elements.leadFilterSource.value || '';
            leadTableState.page = 1;
            renderLeads();
        });
    }

    if (elements.leadFilterAssigned) {
        elements.leadFilterAssigned.addEventListener('change', () => {
            leadFilters.assigned = elements.leadFilterAssigned.value || '';
            leadTableState.page = 1;
            renderLeads();
        });
    }

    if (elements.leadClearFilters) {
        elements.leadClearFilters.addEventListener('click', () => {
            leadFilters.search = '';
            leadFilters.status = '';
            leadFilters.source = '';
            leadFilters.assigned = '';
            syncLeadFiltersUI();
            leadTableState.page = 1;
            renderLeads();
        });
    }

    if (elements.leadRefreshBtn) {
        elements.leadRefreshBtn.addEventListener('click', () => {
            loadLeads();
        });
    }

    if (elements.leadTableSearch) {
        let searchTimeout;
        elements.leadTableSearch.addEventListener('input', () => {
            const value = elements.leadTableSearch.value || '';
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                leadFilters.search = value.trim();
                leadTableState.page = 1;
                renderLeads();
            }, 150);
        });
    }

    if (elements.leadPageSize) {
        elements.leadPageSize.addEventListener('change', () => {
            const value = Number(elements.leadPageSize.value);
            leadTableState.pageSize = Number.isNaN(value) ? 10 : value;
            leadTableState.page = 1;
            renderLeads();
        });
    }

    if (elements.leadSelectAll) {
        elements.leadSelectAll.addEventListener('change', () => {
            const paginated = getPaginatedLeads();
            paginated.items.forEach((lead) => {
                if (elements.leadSelectAll.checked) {
                    selectedLeads.add(String(lead.id));
                } else {
                    selectedLeads.delete(String(lead.id));
                }
            });
            syncLeadSelectionUI();
            renderLeads();
        });
    }

    if (elements.leadReloadTable) {
        elements.leadReloadTable.addEventListener('click', () => {
            leadTableState.page = 1;
            renderLeads();
        });
    }

    if (elements.leadDetailEdit) {
        elements.leadDetailEdit.addEventListener('click', () => toggleLeadEditMode(true));
    }

    if (elements.leadDetailCancel) {
        elements.leadDetailCancel.addEventListener('click', () => toggleLeadEditMode(false));
    }

    function notifyEditPlaceholder() {
        showToast('info', 'Edición del lead en desarrollo.');
    }

    if (elements.leadDetailSave) {
        elements.leadDetailSave.addEventListener('click', notifyEditPlaceholder);
    }

    if (elements.leadDetailSaveFooter) {
        elements.leadDetailSaveFooter.addEventListener('click', notifyEditPlaceholder);
    }

    if (elements.leadDetailConvert) {
        elements.leadDetailConvert.addEventListener('click', (event) => {
            event.preventDefault();
            if (!leadDetailState.current) {
                showToast('Selecciona un lead para convertir', false);
                return;
            }
            fillConvertForm(leadDetailState.current, true);
            if (leadModals.convert) {
                leadModals.convert.show();
            }
        });
    }

    if (elements.leadProjectsCreate) {
        elements.leadProjectsCreate.addEventListener('click', (event) => {
            event.preventDefault();
            if (!leadDetailState.current) {
                showToast('Selecciona un lead para crear un caso', false);
                return;
            }
            createLeadProject(leadDetailState.current);
        });
    }

    if (elements.leadExportBtn) {
        elements.leadExportBtn.addEventListener('click', () => {
            const data = getFilteredLeads();
            const csv = ['"ID","Nombre","Correo","Teléfono","Estado","Origen","Asignado"'];
            data.forEach((lead) => {
                csv.push([
                    lead.id,
                    escapeHtml(lead.name || ''),
                    escapeHtml(lead.email || ''),
                    escapeHtml(lead.phone || ''),
                    escapeHtml(titleize(lead.status || '')),
                    escapeHtml(titleize(lead.source || '')),
                    escapeHtml(lead.assigned_name || ''),
                ].map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','));
            });
            const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'leads.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            showToast('Exportación generada', true);
        });
    }

    function applyBulkChanges() {
        if (!selectedLeads.size) {
            showToast('Selecciona al menos un lead', false);
            return;
        }

        const status = elements.leadBulkStatus ? elements.leadBulkStatus.value : '';
        const source = elements.leadBulkSource ? elements.leadBulkSource.value : '';
        const assigned = elements.leadBulkAssigned ? elements.leadBulkAssigned.value : '';
        const shouldDelete = elements.leadBulkDelete && elements.leadBulkDelete.checked;
        const markLost = elements.leadBulkLost && elements.leadBulkLost.checked;

        state.leads = state.leads.reduce((acc, lead) => {
            const isSelected = selectedLeads.has(String(lead.id));
            if (!isSelected) {
                acc.push(lead);
                return acc;
            }

            if (shouldDelete) {
                return acc;
            }

            const updated = { ...lead };
            if (status) {
                updated.status = status;
            }
            if (source) {
                updated.source = source;
            }
            if (assigned) {
                updated.assigned_to = assigned;
                const user = state.assignableUsers.find((u) => String(u.id) === String(assigned));
                updated.assigned_name = user ? user.nombre : updated.assigned_name;
            }
            if (markLost) {
                updated.status = 'lost';
            }

            acc.push(updated);
            return acc;
        }, []);

        selectedLeads.clear();
        if (elements.leadBulkDelete) elements.leadBulkDelete.checked = false;
        if (elements.leadBulkLost) elements.leadBulkLost.checked = false;
        if (elements.leadBulkPublic) elements.leadBulkPublic.checked = false;
        if (elements.leadBulkStatus) elements.leadBulkStatus.value = '';
        if (elements.leadBulkSource) elements.leadBulkSource.value = '';
        if (elements.leadBulkAssigned) elements.leadBulkAssigned.value = '';

        renderLeads();
        showToast('Acciones masivas aplicadas', true);
    }

    if (elements.leadBulkApply) {
        elements.leadBulkApply.addEventListener('click', () => {
            applyBulkChanges();
        });
    }

    if (elements.convertForm && canManageLeads) {
        elements.convertForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const hcNumber = elements.convertLeadHc ? normalizeHcNumber(elements.convertLeadHc.value) : '';
            if (!hcNumber) {
                showToast('Selecciona un lead antes de convertir', false);
                return;
            }
            const formData = new FormData(elements.convertForm);
            const customer = {};
            const fieldsMap = {
                customer_name: 'name',
                customer_email: 'email',
                customer_phone: 'phone',
                customer_document: 'document',
                customer_external_ref: 'external_ref',
                customer_affiliation: 'affiliation',
                customer_address: 'address',
            };
            Object.keys(fieldsMap).forEach((field) => {
                const value = String(formData.get(field) || '').trim();
                if (value) {
                    customer[fieldsMap[field]] = value;
                }
            });

            request('/crm/leads/convert', { method: 'POST', body: { hc_number: hcNumber, customer } })
                .then(() => {
                    showToast('Lead convertido correctamente', true);
                    disableConvertForm();
                    return loadLeads();
                })
                .catch((error) => {
                    console.error('No se pudo convertir el lead', error);
                    showToast(error.message || 'No se pudo convertir el lead', false);
                });
        });
    }

    if (elements.projectForm && canManageProjects) {
        elements.projectForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.projectForm);
            const title = String(formData.get('title') || '').trim();
            if (!title) {
                showToast('El nombre del proyecto es obligatorio', false);
                return;
            }
            const payload = { title };
            const description = String(formData.get('description') || '').trim();
            if (description) {
                payload.description = description;
            }
            const status = String(formData.get('status') || '').trim();
            if (status) {
                payload.status = status;
            }
            const ownerId = serializeNumber(formData.get('owner_id'));
            if (ownerId) {
                payload.owner_id = ownerId;
            }
            const leadId = serializeNumber(formData.get('lead_id'));
            if (leadId) {
                payload.lead_id = leadId;
            }
            const customerId = serializeNumber(formData.get('customer_id'));
            if (customerId) {
                payload.customer_id = customerId;
            }
            const startDate = String(formData.get('start_date') || '').trim();
            if (startDate) {
                payload.start_date = startDate;
            }
            const dueDate = String(formData.get('due_date') || '').trim();
            if (dueDate) {
                payload.due_date = dueDate;
            }

            request('/projects/create', { method: 'POST', body: payload })
                .then(() => {
                    showToast('Proyecto registrado', true);
                    elements.projectForm.reset();
                    return loadProjects();
                })
                .catch((error) => {
                    console.error('No se pudo crear el proyecto', error);
                    showToast(error.message || 'No se pudo crear el proyecto', false);
                });
        });
    }

    if (elements.taskForm && canManageTasks) {
        elements.taskForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.taskForm);
            const projectId = serializeNumber(formData.get('project_id'));
            if (!projectId) {
                showToast('Selecciona un proyecto para la tarea', false);
                return;
            }
            const title = String(formData.get('title') || '').trim();
            if (!title) {
                showToast('El título de la tarea es obligatorio', false);
                return;
            }
            const payload = { project_id: projectId, title };
            const description = String(formData.get('description') || '').trim();
            if (description) {
                payload.description = description;
            }
            const status = String(formData.get('status') || '').trim();
            if (status) {
                payload.status = status;
            }
            const assignedTo = serializeNumber(formData.get('assigned_to'));
            if (assignedTo) {
                payload.assigned_to = assignedTo;
            }
            const dueDate = String(formData.get('due_date') || '').trim();
            if (dueDate) {
                payload.due_date = dueDate;
            }
            const remindAt = String(formData.get('remind_at') || '').trim();
            if (remindAt) {
                payload.remind_at = remindAt;
            }
            const remindChannel = String(formData.get('remind_channel') || '').trim();
            if (remindChannel) {
                payload.remind_channel = remindChannel;
            }

            request('/crm/tasks', { method: 'POST', body: payload })
                .then(() => {
                    showToast('Tarea creada', true);
                    elements.taskForm.reset();
                    return loadTasks();
                })
                .catch((error) => {
                    console.error('No se pudo crear la tarea', error);
                    showToast(error.message || 'No se pudo crear la tarea', false);
                });
        });
    }

    if (elements.ticketForm && canManageTickets) {
        elements.ticketForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.ticketForm);
            const subject = String(formData.get('subject') || '').trim();
            const message = String(formData.get('message') || '').trim();
            if (!subject || !message) {
                showToast('Asunto y mensaje son obligatorios', false);
                return;
            }
            const payload = { subject, message };
            const priority = String(formData.get('priority') || '').trim();
            if (priority) {
                payload.priority = priority;
            }
            const status = String(formData.get('status') || '').trim();
            if (status) {
                payload.status = status;
            }
            const assignedTo = serializeNumber(formData.get('assigned_to'));
            if (assignedTo) {
                payload.assigned_to = assignedTo;
            }
            const leadId = serializeNumber(formData.get('related_lead_id'));
            if (leadId) {
                payload.related_lead_id = leadId;
            }
            const projectId = serializeNumber(formData.get('related_project_id'));
            if (projectId) {
                payload.related_project_id = projectId;
            }

            request('/crm/tickets', { method: 'POST', body: payload })
                .then(() => {
                    showToast('Ticket creado', true);
                    elements.ticketForm.reset();
                    return loadTickets();
                })
                .catch((error) => {
                    console.error('No se pudo crear el ticket', error);
                    showToast(error.message || 'No se pudo crear el ticket', false);
                });
        });
    }

    if (elements.ticketReplyForm && canManageTickets) {
        elements.ticketReplyForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const ticketId = serializeNumber(elements.ticketReplyId.value);
            const message = String(elements.ticketReplyMessage.value || '').trim();
            if (!ticketId || !message) {
                showToast('Selecciona un ticket y escribe un mensaje', false);
                return;
            }
            const payload = { ticket_id: ticketId, message };
            const status = String(elements.ticketReplyStatus.value || '').trim();
            if (status) {
                payload.status = status;
            }

            request('/crm/tickets/reply', { method: 'POST', body: payload })
                .then(() => {
                    showToast('Respuesta registrada', true);
                    disableTicketReplyForm();
                    return loadTickets();
                })
                .catch((error) => {
                    console.error('No se pudo responder el ticket', error);
                    showToast(error.message || 'No se pudo responder el ticket', false);
                });
        });
    }

    if (elements.proposalRefreshBtn) {
        elements.proposalRefreshBtn.addEventListener('click', () => {
            loadProposals();
        });
    }

    if (elements.proposalStatusFilter) {
        elements.proposalStatusFilter.addEventListener('change', () => {
            proposalFilters.status = elements.proposalStatusFilter.value || '';
            loadProposals();
        });
    }

    if (elements.proposalSearchInput) {
        let searchTimeout;
        elements.proposalSearchInput.addEventListener('input', () => {
            const value = elements.proposalSearchInput.value || '';
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                proposalFilters.search = value.trim();
                renderProposals();
            }, 200);
        });
    }

    if (elements.proposalNewBtn) {
        elements.proposalNewBtn.addEventListener('click', () => {
            const target = elements.proposalTitle || elements.proposalLeadSelect;
            if (target && typeof target.focus === 'function') {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.focus();
            }
        });
    }

    if (elements.proposalPipelineBtn) {
        elements.proposalPipelineBtn.addEventListener('click', () => {
            showToast('info', 'Vista de pipeline aún no implementada en esta instancia.');
        });
    }

    if (elements.proposalExportBtn) {
        elements.proposalExportBtn.addEventListener('click', () => {
            showToast('info', 'Exportación masiva de PDF no está disponible en esta versión.');
        });
    }

    if (elements.proposalPreviewOpen) {
        elements.proposalPreviewOpen.addEventListener('click', (event) => {
            event.preventDefault();
            const proposalId = serializeNumber(elements.proposalPreviewOpen.dataset.proposalId);
            if (proposalId) {
                openProposalDetail(proposalId);
            }
        });
    }

    if (elements.proposalPreviewRefresh) {
        elements.proposalPreviewRefresh.addEventListener('click', (event) => {
            event.preventDefault();
            const proposalId = serializeNumber(elements.proposalPreviewRefresh.dataset.proposalId);
            if (proposalId) {
                openProposalDetail(proposalId);
            }
        });
    }

    if (elements.proposalSaveBtn && canManageProjects) {
        elements.proposalSaveBtn.addEventListener('click', (event) => {
            event.preventDefault();
            saveProposal();
        });
    }

    if (elements.proposalAddCustomBtn && canManageProjects) {
        elements.proposalAddCustomBtn.addEventListener('click', (event) => {
            event.preventDefault();
            addProposalItem({ description: '', quantity: 1, unit_price: 0 });
        });
    }

    if (elements.proposalAddPackageBtn && canManageProjects) {
        elements.proposalAddPackageBtn.addEventListener('click', (event) => {
            event.preventDefault();
            openPackageModal();
        });
    }

    if (elements.proposalAddCodeBtn && canManageProjects) {
        elements.proposalAddCodeBtn.addEventListener('click', (event) => {
            event.preventDefault();
            openProposalCodeModal();
        });
    }

    if (elements.proposalPackageSearch) {
        elements.proposalPackageSearch.addEventListener('input', (event) => {
            renderProposalPackages(proposalBuilder.packages, event.target.value);
        });
    }

    if (elements.proposalCodeSearchBtn) {
        elements.proposalCodeSearchBtn.addEventListener('click', (event) => {
            event.preventDefault();
            searchProposalCodes();
        });
    }

    if (elements.proposalCodeSearchInput) {
        elements.proposalCodeSearchInput.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchProposalCodes();
            }
        });
    }

    if (elements.proposalTaxRate) {
        elements.proposalTaxRate.addEventListener('input', () => {
            updateProposalTotals();
        });
    }

    if (canManageLeads || canManageProjects || canManageTasks) {
        root.addEventListener('change', (event) => {
            const target = event.target;
            if (canManageLeads && target.classList.contains('js-lead-status')) {
                const hcNumber = normalizeHcNumber(target.dataset.leadHc);
                const status = target.value;
                if (!hcNumber || !status) {
                    return;
                }
                request('/crm/leads/update', { method: 'POST', body: { hc_number: hcNumber, status } })
                    .then(() => loadLeads())
                    .catch((error) => {
                        console.error('Error actualizando lead', error);
                        showToast(error.message || 'No se pudo actualizar el lead', false);
                        loadLeads();
                    });
                return;
            }
            if (canManageLeads && target.classList.contains('js-lead-assigned')) {
                const leadId = serializeNumber(target.dataset.leadId);
                const assignedTo = target.value;
                if (!leadId) {
                    return;
                }
                request(`/crm/leads/${leadId}`, { method: 'PUT', body: { assigned_to: assignedTo || null } })
                    .then(() => loadLeads())
                    .catch((error) => {
                        console.error('Error asignando lead', error);
                        showToast(error.message || 'No se pudo asignar el lead', false);
                        loadLeads();
                    });
                return;
            }
            if (canManageProjects && target.classList.contains('js-project-status')) {
                const projectId = serializeNumber(target.dataset.projectId);
                const status = target.value;
                if (!projectId || !status) {
                    return;
                }
                request('/projects/status', { method: 'POST', body: { project_id: projectId, status } })
                    .then(() => loadProjects())
                    .catch((error) => {
                        console.error('Error actualizando proyecto', error);
                        showToast(error.message || 'No se pudo actualizar el proyecto', false);
                        loadProjects();
                    });
            }
            if (canManageTasks && target.classList.contains('js-task-status')) {
                const taskId = serializeNumber(target.dataset.taskId);
                const status = target.value;
                if (!taskId || !status) {
                    return;
                }
                request('/crm/tasks/status', { method: 'POST', body: { task_id: taskId, status } })
                    .then(() => loadTasks())
                    .catch((error) => {
                        console.error('Error actualizando tarea', error);
                        showToast(error.message || 'No se pudo actualizar la tarea', false);
                        loadTasks();
                    });
            }
            if (target.classList.contains('proposal-status-select')) {
                const proposalId = serializeNumber(target.dataset.proposalId);
                const status = target.value;
                if (!proposalId || !status) {
                    return;
                }
                updateProposalStatus(proposalId, status);
            }
            if (target.id === 'proposal-detail-status-select') {
                const proposalId = serializeNumber(target.dataset.proposalId);
                const status = target.value;
                if (!proposalId || !status) {
                    return;
                }
                updateProposalStatus(proposalId, status, () => openProposalDetail(proposalId));
            }
        });
    }

    root.addEventListener('click', (event) => {
        const proposalRow = event.target.closest('.proposal-row');
        if (proposalRow && !event.target.closest('select')) {
            const proposalId = serializeNumber(proposalRow.dataset.proposalId);
            setSelectedProposal(proposalId);
        }
        const proposalButton = event.target.closest('.proposal-view-btn');
        if (proposalButton) {
            event.preventDefault();
            const proposalId = serializeNumber(proposalButton.dataset.proposalId);
            setSelectedProposal(proposalId);
            openProposalDetail(proposalId);
        }
    });

    if (canManageLeads || canManageTickets) {
        root.addEventListener('click', (event) => {
            const toolbarAction = event.target.closest('.js-toolbar-action');
            if (toolbarAction) {
                event.preventDefault();
                const targetSelector = toolbarAction.dataset.target;
                if (targetSelector) {
                    const mirroredButton = root.querySelector(targetSelector);
                    if (mirroredButton) {
                        mirroredButton.click();
                    }
                }
                return;
            }

            if (canManageLeads) {
                const viewButton = event.target.closest('.js-view-lead');
                if (viewButton) {
                    const leadId = viewButton.dataset.leadId;
                    if (!leadId) {
                        showToast('No pudimos cargar el lead seleccionado', false);
                        return;
                    }
                    openLeadProfile(leadId);
                    return;
                }

                const editButton = event.target.closest('.js-edit-lead');
                if (editButton) {
                    const leadId = editButton.dataset.leadId;
                    openLeadEdit(leadId);
                    return;
                }

                const emailButton = event.target.closest('.js-lead-email');
                if (emailButton) {
                    const leadId = serializeNumber(emailButton.dataset.leadId);
                    if (!leadId) {
                        return;
                    }
                    openLeadEmail(leadId);
                    return;
                }

                const leadButton = event.target.closest('.js-select-lead');
                if (leadButton) {
                    const hcNumber = normalizeHcNumber(leadButton.dataset.leadHc);
                    if (!hcNumber) {
                        showToast('El lead no tiene historia clínica para convertir', false);
                        return;
                    }
                    const lead = findLeadByHcNumber(hcNumber);
                    if (!lead) {
                        showToast('No pudimos cargar el lead seleccionado', false);
                        return;
                    }
                    fillConvertForm(lead, true);
                    if (leadModals.convert) {
                        leadModals.convert.show();
                    }
                    return;
                }
            }

            if (canManageTickets) {
                const ticketButton = event.target.closest('.js-reply-ticket');
                if (ticketButton) {
                    const ticketId = serializeNumber(ticketButton.dataset.ticketId);
                    if (!ticketId) {
                        return;
                    }
                    const ticket = findTicketById(ticketId);
                    if (!ticket) {
                        showToast('No encontramos el ticket seleccionado', false);
                        return;
                    }
                    applyTicketReply(ticket, true);
                }
            }
        });
    }

    applyUrlDeepLink();
    resetLeadForm();
    disableConvertForm();
    disableTicketReplyForm();
    renderLeads();
    renderProjects();
    renderTasks();
    renderTickets();
    renderProposals();
    resetProposalBuilder();
    updateProposalTotals();

    if (!canManageProjects) {
        [elements.proposalSaveBtn, elements.proposalAddCustomBtn, elements.proposalAddPackageBtn, elements.proposalAddCodeBtn].forEach((btn) => {
            if (btn) {
                btn.disabled = true;
            }
        });
    }

    Promise.all([loadLeads(), loadProjects(), loadTasks(), loadTickets(), loadProposals()]).catch(() => {
        // errores ya se notifican individualmente
    });
})();
