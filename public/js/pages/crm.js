
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
    };

    const elements = {
        leadTableBody: root.querySelector('#crm-leads-table tbody'),
        projectTableBody: root.querySelector('#crm-projects-table tbody'),
        taskTableBody: root.querySelector('#crm-tasks-table tbody'),
        ticketTableBody: root.querySelector('#crm-tickets-table tbody'),
        leadForm: root.querySelector('#lead-form'),
        convertForm: root.querySelector('#lead-convert-form'),
        convertLeadHc: root.querySelector('#convert-lead-hc'),
        convertHelper: root.querySelector('#convert-helper'),
        convertSelected: root.querySelector('#convert-lead-selected'),
        convertSubmit: root.querySelector('#lead-convert-form button[type="submit"]'),
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
    };

    const proposalBuilder = {
        items: [],
        packages: [],
    };

    const proposalModals = {
        package: (window.bootstrap && elements.proposalPackageModal) ? new window.bootstrap.Modal(elements.proposalPackageModal) : null,
        code: (window.bootstrap && elements.proposalCodeModal) ? new window.bootstrap.Modal(elements.proposalCodeModal) : null,
    };

    state.leads = mapLeads(state.leads);
    state.proposals = mapProposals(state.proposals);

    const htmlEscapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).replace(/[&<>"']/g, (match) => htmlEscapeMap[match]);
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

    function limitText(value, maxLength) {
        if (!value) {
            return '';
        }
        if (value.length <= maxLength) {
            return value;
        }
        return `${value.slice(0, maxLength - 1)}…`;
    }

    function showToast(type, message) {
        const text = typeof message === 'string' ? message : 'Ocurrió un error inesperado';
        const method = type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'error';
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

    function updateCounters() {
        if (elements.leadsCount) {
            elements.leadsCount.textContent = `Leads: ${state.leads.length}`;
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

    function renderLeads() {
        if (!elements.leadTableBody) {
            return;
        }
        clearContainer(elements.leadTableBody);

        if (!state.leads.length) {
            const emptyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'Aún no se han registrado leads.';
            emptyRow.appendChild(cell);
            elements.leadTableBody.appendChild(emptyRow);
        } else {
            state.leads.forEach((lead) => {
                const row = document.createElement('tr');

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

                const assignedCell = document.createElement('td');
                assignedCell.textContent = lead.assigned_name || 'Sin asignar';
                row.appendChild(assignedCell);

                const updatedCell = document.createElement('td');
                updatedCell.textContent = formatDate(lead.updated_at, true);
                row.appendChild(updatedCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'text-end';
                if (canManageLeads) {
                    const convertButton = document.createElement('button');
                    convertButton.type = 'button';
                    convertButton.className = 'btn btn-sm btn-success js-select-lead';
                    convertButton.dataset.leadHc = normalizedHc;
                    convertButton.innerHTML = '<i class="mdi mdi-account-check-outline me-1"></i>Convertir';
                    actionsCell.appendChild(convertButton);
                } else {
                    actionsCell.innerHTML = '<span class="text-muted">Sin acciones</span>';
                }
                row.appendChild(actionsCell);

                elements.leadTableBody.appendChild(row);
            });
        }

        populateLeadSelects();
        syncConvertFormSelection();
        updateCounters();
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
        elements.convertSelected.textContent = 'Sin selección';
        elements.convertHelper.textContent = 'Selecciona un lead en la tabla para precargar los datos.';
        elements.convertSubmit.disabled = true;
        ['customer_name', 'customer_email', 'customer_phone', 'customer_document', 'customer_external_ref', 'customer_affiliation', 'customer_address'].forEach((field) => {
            const input = elements.convertForm.querySelector(`[name="${field}"]`);
            if (input) {
                input.value = '';
            }
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
        elements.convertSelected.textContent = label;
        elements.convertHelper.textContent = 'Completa los datos y confirma la conversión.';
        elements.convertSubmit.disabled = false;
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
                renderLeads();
            })
            .catch((error) => {
                console.error('Error cargando leads', error);
                showToast('error', error.message || 'No se pudieron cargar los leads');
            });
    }

    function loadProjects() {
        return request('/crm/projects')
            .then((data) => {
                state.projects = Array.isArray(data.data) ? data.data : [];
                renderProjects();
            })
            .catch((error) => {
                console.error('Error cargando proyectos', error);
                showToast('error', error.message || 'No se pudieron cargar los proyectos');
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
                showToast('error', error.message || 'No se pudieron cargar las tareas');
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
                showToast('error', error.message || 'No se pudieron cargar los tickets');
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

    function normalizeLead(lead) {
        if (!lead || typeof lead !== 'object') {
            return {};
        }
        const normalized = { ...lead };
        normalized.hc_number = normalizeHcNumber(lead.hc_number ?? lead.hcNumber ?? '');
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
            clone.status = clone.status || 'draft';
            return clone;
        });
    }

    function proposalStatusBadge(status) {
        const map = {
            draft: 'bg-secondary',
            sent: 'bg-info',
            accepted: 'bg-success',
            declined: 'bg-danger',
            expired: 'bg-warning',
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

        if (!state.proposals.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'text-center text-muted py-4';
            cell.textContent = 'Aún no hay propuestas registradas.';
            row.appendChild(cell);
            elements.proposalTableBody.appendChild(row);
            return;
        }

        state.proposals.forEach((proposal) => {
            const row = document.createElement('tr');

            const numberCell = document.createElement('td');
            numberCell.innerHTML = `<strong>${proposal.proposal_number || `#${proposal.id}`}</strong>`;
            row.appendChild(numberCell);

            const leadCell = document.createElement('td');
            leadCell.textContent = proposal.lead_name || proposal.customer_name || '-';
            row.appendChild(leadCell);

            const statusCell = document.createElement('td');
            statusCell.appendChild(proposalStatusBadge(proposal.status));
            row.appendChild(statusCell);

            const totalCell = document.createElement('td');
            totalCell.className = 'text-end';
            totalCell.textContent = formatCurrency(proposal.total);
            row.appendChild(totalCell);

            const validCell = document.createElement('td');
            validCell.textContent = proposal.valid_until ? formatDate(proposal.valid_until, false) : '—';
            row.appendChild(validCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const select = createStatusSelect(state.proposalStatuses, proposal.status);
            select.classList.add('form-select-sm', 'proposal-status-select');
            select.dataset.proposalId = proposal.id;
            actionCell.appendChild(select);
            row.appendChild(actionCell);

            elements.proposalTableBody.appendChild(row);
        });
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
                showToast('error', error.message || 'No se pudieron cargar las propuestas');
            });
    }

    function updateProposalStatus(proposalId, status) {
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
                showToast('success', 'Estado actualizado');
            })
            .catch((error) => {
                console.error('Error actualizando estado de propuesta', error);
                showToast('error', error.message || 'No se pudo actualizar el estado');
                loadProposals();
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
            showToast('error', 'Selecciona un lead');
            return;
        }
        if (!payload.title) {
            showToast('error', 'Asigna un título a la propuesta');
            return;
        }
        if (!payload.items.length) {
            showToast('error', 'Agrega al menos un ítem');
            return;
        }

        request('/crm/proposals', { method: 'POST', body: payload })
            .then((response) => {
                showToast('success', 'Propuesta creada');
                resetProposalBuilder();
                const created = response.data;
                state.proposals.unshift(created);
                state.proposals = mapProposals(state.proposals);
                renderProposals();
            })
            .catch((error) => {
                console.error('No se pudo crear la propuesta', error);
                showToast('error', error.message || 'No se pudo crear la propuesta');
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
                showToast('error', error.message || 'No se pudieron cargar los paquetes');
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
            showToast('error', 'Ingresa un término de búsqueda');
            return;
        }
        const url = `/codes/api/search?q=${encodeURIComponent(query)}`;

        request(url)
            .then((data) => {
                renderProposalCodeResults(data.data || []);
            })
            .catch((error) => {
                console.error('No se pudieron buscar los códigos', error);
                showToast('error', error.message || 'No se pudo buscar');
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
            const payload = { name: String(formData.get('name') || '').trim() };
            if (!payload.name) {
                showToast('error', 'El nombre es obligatorio');
                return;
            }
            const hcNumber = normalizeHcNumber(formData.get('hc_number'));
            if (!hcNumber) {
                showToast('error', 'La historia clínica es obligatoria');
                return;
            }
            payload.hc_number = hcNumber;
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

            request('/crm/leads', { method: 'POST', body: payload })
                .then(() => {
                    showToast('success', 'Lead creado correctamente');
                    elements.leadForm.reset();
                    return loadLeads();
                })
                .catch((error) => {
                    console.error('No se pudo crear el lead', error);
                    showToast('error', error.message || 'No se pudo crear el lead');
                });
        });
    }

    if (elements.convertForm && canManageLeads) {
        elements.convertForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const hcNumber = normalizeHcNumber(elements.convertLeadHc.value);
            if (!hcNumber) {
                showToast('error', 'Selecciona un lead antes de convertir');
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
                    showToast('success', 'Lead convertido correctamente');
                    disableConvertForm();
                    return loadLeads();
                })
                .catch((error) => {
                    console.error('No se pudo convertir el lead', error);
                    showToast('error', error.message || 'No se pudo convertir el lead');
                });
        });
    }

    if (elements.projectForm && canManageProjects) {
        elements.projectForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.projectForm);
            const title = String(formData.get('title') || '').trim();
            if (!title) {
                showToast('error', 'El nombre del proyecto es obligatorio');
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

            request('/crm/projects', { method: 'POST', body: payload })
                .then(() => {
                    showToast('success', 'Proyecto registrado');
                    elements.projectForm.reset();
                    return loadProjects();
                })
                .catch((error) => {
                    console.error('No se pudo crear el proyecto', error);
                    showToast('error', error.message || 'No se pudo crear el proyecto');
                });
        });
    }

    if (elements.taskForm && canManageTasks) {
        elements.taskForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(elements.taskForm);
            const projectId = serializeNumber(formData.get('project_id'));
            if (!projectId) {
                showToast('error', 'Selecciona un proyecto para la tarea');
                return;
            }
            const title = String(formData.get('title') || '').trim();
            if (!title) {
                showToast('error', 'El título de la tarea es obligatorio');
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
                    showToast('success', 'Tarea creada');
                    elements.taskForm.reset();
                    return loadTasks();
                })
                .catch((error) => {
                    console.error('No se pudo crear la tarea', error);
                    showToast('error', error.message || 'No se pudo crear la tarea');
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
                showToast('error', 'Asunto y mensaje son obligatorios');
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
                    showToast('success', 'Ticket creado');
                    elements.ticketForm.reset();
                    return loadTickets();
                })
                .catch((error) => {
                    console.error('No se pudo crear el ticket', error);
                    showToast('error', error.message || 'No se pudo crear el ticket');
                });
        });
    }

    if (elements.ticketReplyForm && canManageTickets) {
        elements.ticketReplyForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const ticketId = serializeNumber(elements.ticketReplyId.value);
            const message = String(elements.ticketReplyMessage.value || '').trim();
            if (!ticketId || !message) {
                showToast('error', 'Selecciona un ticket y escribe un mensaje');
                return;
            }
            const payload = { ticket_id: ticketId, message };
            const status = String(elements.ticketReplyStatus.value || '').trim();
            if (status) {
                payload.status = status;
            }

            request('/crm/tickets/reply', { method: 'POST', body: payload })
                .then(() => {
                    showToast('success', 'Respuesta registrada');
                    disableTicketReplyForm();
                    return loadTickets();
                })
                .catch((error) => {
                    console.error('No se pudo responder el ticket', error);
                    showToast('error', error.message || 'No se pudo responder el ticket');
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
            loadProposals();
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
                        showToast('error', error.message || 'No se pudo actualizar el lead');
                        loadLeads();
                    });
            }
            if (canManageProjects && target.classList.contains('js-project-status')) {
                const projectId = serializeNumber(target.dataset.projectId);
                const status = target.value;
                if (!projectId || !status) {
                    return;
                }
                request('/crm/projects/status', { method: 'POST', body: { project_id: projectId, status } })
                    .then(() => loadProjects())
                    .catch((error) => {
                        console.error('Error actualizando proyecto', error);
                        showToast('error', error.message || 'No se pudo actualizar el proyecto');
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
                        showToast('error', error.message || 'No se pudo actualizar la tarea');
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
        });
    }

    if (canManageLeads || canManageTickets) {
        root.addEventListener('click', (event) => {
            if (canManageLeads) {
                const leadButton = event.target.closest('.js-select-lead');
                if (leadButton) {
                    const hcNumber = normalizeHcNumber(leadButton.dataset.leadHc);
                    if (!hcNumber) {
                        return;
                    }
                    const lead = findLeadByHcNumber(hcNumber);
                    if (!lead) {
                        showToast('error', 'No pudimos cargar el lead seleccionado');
                        return;
                    }
                    fillConvertForm(lead, true);
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
                        showToast('error', 'No encontramos el ticket seleccionado');
                        return;
                    }
                    applyTicketReply(ticket, true);
                }
            }
        });
    }

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
