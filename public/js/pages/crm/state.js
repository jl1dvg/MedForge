export function normalizeHcNumber(value) {
    return String(value || '').trim().toUpperCase();
}

export function normalizeLead(lead) {
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

export function mapLeads(leads) {
    return Array.isArray(leads) ? leads.map((lead) => normalizeLead(lead)) : [];
}

export function mapProposals(proposals) {
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

export function createState(bootstrapData) {
    return {
        leadStatuses: Array.isArray(bootstrapData.leadStatuses) ? bootstrapData.leadStatuses : [],
        leadSources: Array.isArray(bootstrapData.leadSources) ? bootstrapData.leadSources : [],
        projectStatuses: Array.isArray(bootstrapData.projectStatuses) ? bootstrapData.projectStatuses : [],
        taskStatuses: Array.isArray(bootstrapData.taskStatuses) ? bootstrapData.taskStatuses : [],
        ticketStatuses: Array.isArray(bootstrapData.ticketStatuses) ? bootstrapData.ticketStatuses : [],
        ticketPriorities: Array.isArray(bootstrapData.ticketPriorities) ? bootstrapData.ticketPriorities : [],
        assignableUsers: Array.isArray(bootstrapData.assignableUsers) ? bootstrapData.assignableUsers : [],
        leads: mapLeads(bootstrapData.initialLeads),
        projects: Array.isArray(bootstrapData.initialProjects) ? bootstrapData.initialProjects : [],
        tasks: Array.isArray(bootstrapData.initialTasks) ? bootstrapData.initialTasks : [],
        tickets: Array.isArray(bootstrapData.initialTickets) ? bootstrapData.initialTickets : [],
        proposalStatuses: Array.isArray(bootstrapData.proposalStatuses) ? bootstrapData.proposalStatuses : [],
        proposals: mapProposals(bootstrapData.initialProposals),
        focusProjectId: null,
        taskFilters: {},
        leadFilters: {
            search: '',
            status: '',
            source: '',
            assigned: '',
        },
        leadTableState: {
            page: 1,
            pageSize: 10,
        },
        proposalFilters: {
            status: '',
            search: '',
        },
        proposalUIState: {
            selectedId: null,
        },
        selectedLeads: new Set(),
        leadFormState: {
            mode: 'create',
            currentHc: null,
        },
        projectDetailState: {
            currentId: null,
            tasksLoaded: false,
            tasks: [],
            tasksTable: null,
            loadingTasks: false,
            editing: false,
            taskStatusFilter: 'all',
        },
        proposalDetailState: {
            current: null,
        },
        leadDetailState: {
            current: null,
        },
        proposalBuilder: {
            items: [],
            packages: [],
        },
    };
}

export function updateTaskInCrmState(state, taskId, updates) {
    const index = state.tasks.findIndex((task) => String(task.id) === String(taskId));
    if (index === -1) {
        return;
    }
    state.tasks[index] = { ...state.tasks[index], ...updates };
}

export function updateProjectInState(state, projectId, updates) {
    const index = state.projects.findIndex((project) => String(project.id) === String(projectId));
    if (index === -1) {
        return;
    }
    state.projects[index] = { ...state.projects[index], ...updates };
}
