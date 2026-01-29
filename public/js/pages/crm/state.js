'use strict';

import { normalizeHcNumber } from './utils.js';

export let permissions = {};
export let canManageLeads = false;
export let canManageProjects = false;
export let canManageTasks = false;
export let canManageTickets = false;

export let state = {
    leadStatuses: [],
    leadSources: [],
    projectStatuses: [],
    taskStatuses: [],
    ticketStatuses: [],
    ticketPriorities: [],
    assignableUsers: [],
    leads: [],
    projects: [],
    tasks: [],
    tickets: [],
    proposalStatuses: [],
    proposals: [],
    focusProjectId: null,
    taskFilters: {},
};

export const proposalBuilder = {
    items: [],
    packages: [],
};

export const proposalDetailState = {
    current: null,
};

export const leadDetailState = {
    current: null,
};

export const leadFormState = {
    mode: 'create',
    currentHc: null,
};

export const projectDetailState = {
    currentId: null,
    tasksLoaded: false,
    tasks: [],
    tasksTable: null,
    loadingTasks: false,
    editing: false,
    taskStatusFilter: 'all',
};

export const taskPriorityOptions = ['baja', 'media', 'alta', 'urgente'];

export const leadFilters = {
    search: '',
    status: '',
    source: '',
    assigned: '',
};

export const leadTableState = {
    page: 1,
    pageSize: 10,
    total: 0,
    totalPages: 1,
};

export const projectFilters = {
    status: '',
    owner_id: '',
    lead_id: '',
    customer_id: '',
    hc_number: '',
    source_module: '',
    source_ref_id: '',
    form_id: '',
    episode_type: '',
    eye: '',
};

export const projectPagination = {
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
};

export const taskPagination = {
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
};

export const ticketFilters = {
    status: '',
    assigned_to: '',
    priority: '',
};

export const ticketPagination = {
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
};

export const proposalFilters = {
    status: '',
    search: '',
    lead_id: '',
};

export const proposalPagination = {
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
};

export const proposalUIState = {
    selectedId: null,
};

export const selectedLeads = new Set();

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

export function initState(bootstrapData) {
    permissions = (bootstrapData && typeof bootstrapData.permissions === 'object' && bootstrapData.permissions !== null)
        ? bootstrapData.permissions
        : {};
    canManageLeads = Boolean(permissions.manageLeads);
    canManageProjects = Boolean(permissions.manageProjects);
    canManageTasks = Boolean(permissions.manageTasks);
    canManageTickets = Boolean(permissions.manageTickets);

    state = {
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
        taskFilters: {},
    };

    state.leads = mapLeads(state.leads);
    state.proposals = mapProposals(state.proposals);

    leadTableState.total = state.leads.length;
    leadTableState.totalPages = leadTableState.pageSize
        ? Math.max(1, Math.ceil(leadTableState.total / leadTableState.pageSize))
        : 1;
    projectPagination.total = state.projects.length;
    projectPagination.totalPages = projectPagination.perPage
        ? Math.max(1, Math.ceil(projectPagination.total / projectPagination.perPage))
        : 1;
    taskPagination.total = state.tasks.length;
    taskPagination.totalPages = taskPagination.perPage
        ? Math.max(1, Math.ceil(taskPagination.total / taskPagination.perPage))
        : 1;
    ticketPagination.total = state.tickets.length;
    ticketPagination.totalPages = ticketPagination.perPage
        ? Math.max(1, Math.ceil(ticketPagination.total / ticketPagination.perPage))
        : 1;
    proposalPagination.total = state.proposals.length;
    proposalPagination.totalPages = proposalPagination.perPage
        ? Math.max(1, Math.ceil(proposalPagination.total / proposalPagination.perPage))
        : 1;
}
