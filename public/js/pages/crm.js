
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
    };

    state.leads = mapLeads(state.leads);

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
        [elements.leadSelectForProject, elements.leadSelectForTicket].forEach((select) => {
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
                const statusSelect = createStatusSelect(state.leadStatuses, lead.status);
                statusSelect.classList.add('js-lead-status');
                statusSelect.dataset.leadHc = normalizedHc;
                statusCell.appendChild(statusSelect);
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
                const convertButton = document.createElement('button');
                convertButton.type = 'button';
                convertButton.className = 'btn btn-sm btn-success js-select-lead';
                convertButton.dataset.leadHc = normalizedHc;
                convertButton.innerHTML = '<i class="mdi mdi-account-check-outline me-1"></i>Convertir';
                actionsCell.appendChild(convertButton);
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
                const statusSelect = createStatusSelect(state.projectStatuses, project.status);
                statusSelect.classList.add('js-project-status');
                statusSelect.dataset.projectId = project.id;
                statusCell.appendChild(statusSelect);
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
                const statusSelect = createStatusSelect(state.taskStatuses, task.status);
                statusSelect.classList.add('js-task-status');
                statusSelect.dataset.taskId = task.id;
                statusCell.appendChild(statusSelect);
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
                const replyButton = document.createElement('button');
                replyButton.type = 'button';
                replyButton.className = 'btn btn-sm btn-outline-info js-reply-ticket';
                replyButton.dataset.ticketId = ticket.id;
                replyButton.innerHTML = '<i class="mdi mdi-reply"></i>';
                actionsCell.appendChild(replyButton);
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

    if (elements.leadForm) {
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

    if (elements.convertForm) {
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

    if (elements.projectForm) {
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

    if (elements.taskForm) {
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

    if (elements.ticketForm) {
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

    if (elements.ticketReplyForm) {
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

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (target.classList.contains('js-lead-status')) {
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
        if (target.classList.contains('js-project-status')) {
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
        if (target.classList.contains('js-task-status')) {
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
    });

    root.addEventListener('click', (event) => {
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
    });

    disableConvertForm();
    disableTicketReplyForm();
    renderLeads();
    renderProjects();
    renderTasks();
    renderTickets();

    Promise.all([loadLeads(), loadProjects(), loadTasks(), loadTickets()]).catch(() => {
        // errores ya se notifican individualmente
    });
})();
