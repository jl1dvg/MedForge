let cachedConfig = null;

const DEFAULT_STRINGS = {
    singular: 'solicitud',
    plural: 'solicitudes',
    capitalizedPlural: 'Solicitudes',
    articleSingular: 'la',
    articleSingularShort: 'la',
};

function normalizePath(pathname) {
    const raw = (pathname ?? '').toString().trim();
    if (raw === '' || raw === '/') {
        return '/';
    }
    return raw.startsWith('/') ? raw : `/${raw}`;
}

function normalizePrefix(prefix) {
    const raw = (prefix ?? '').toString().trim();
    if (raw === '' || raw === '/') {
        return '';
    }
    const cleaned = raw.replace(/^\/+|\/+$/g, '');
    return cleaned ? `/${cleaned}` : '';
}

function computeIds(key, rawSelectors = {}) {
    const prefix = rawSelectors.prefix || key;
    const defaults = {
        ViewKanban: `${prefix}ViewKanban`,
        ViewTable: `${prefix}ViewTable`,
        TotalCount: `${prefix}TotalCount`,
        Overview: `${prefix}Overview`,
        Table: `${prefix}Table`,
        TableEmpty: `${prefix}TableEmpty`,
        Filters: `${prefix}Filters`,
        NotificationToggle: `${prefix}NotificationToggle`,
    };

    return {
        ...defaults,
        ...(rawSelectors.ids || {}),
    };
}

function computeConfig() {
    const raw = window.__KANBAN_MODULE__ || {};
    const key = (raw.key || 'solicitudes').toString();
    const selectors = raw.selectors || {};
    const ids = computeIds(key, selectors);
    const normalizedWritePrefix = normalizePrefix(
        raw.writePrefix || (raw.v2WritesEnabled ? '/v2' : '')
    );

    const globalRealtime = (() => {
        if (typeof window === 'undefined') {
            return {};
        }

        const candidate = window.MEDF_PusherConfig;
        if (!candidate || typeof candidate !== 'object') {
            return {};
        }

        const { kanban, ...rest } = candidate;
        const kanbanOverrides = kanban && typeof kanban === 'object' ? kanban : {};

        return { ...rest, ...kanbanOverrides };
    })();

    return {
        key,
        basePath: raw.basePath || '/solicitudes',
        apiBasePath: raw.apiBasePath || '/api',
        v2WritesEnabled: Boolean(raw.v2WritesEnabled),
        writePrefix: normalizedWritePrefix,
        storageKeyView: raw.storageKeyView || `${key}:view-mode`,
        dataKey: raw.dataKey || `__${key}Kanban`,
        estadosMetaKey: raw.estadosMetaKey || `__${key}EstadosMeta`,
        reporting: raw.reporting || {},
        turnero: raw.turnero || {},
        selectors: {
            prefix: selectors.prefix || key,
            viewAttr: selectors.viewAttr || `data-${key}-view`,
            toggleAttr: selectors.toggleAttr || `data-${key}-toggle`,
            tableBodySelector: selectors.tableBodySelector || `#${ids.Table} tbody`,
            ids,
        },
        strings: {
            ...DEFAULT_STRINGS,
            ...(raw.strings || {}),
        },
        realtime: {
            ...globalRealtime,
            ...(raw.realtime || {}),
        },
    };
}

function ensureConfig() {
    if (!cachedConfig) {
        cachedConfig = computeConfig();
    }
    return cachedConfig;
}

export function resetKanbanConfig() {
    cachedConfig = null;
}

export function getKanbanConfig() {
    return ensureConfig();
}

export function resolveId(name) {
    const config = ensureConfig();
    return config.selectors.ids[name];
}

export function resolveAttr(name) {
    const config = ensureConfig();
    if (name === 'view') {
        return config.selectors.viewAttr;
    }
    if (name === 'toggle') {
        return config.selectors.toggleAttr;
    }
    return `data-${config.key}-${name}`;
}

export function getTableBodySelector() {
    return ensureConfig().selectors.tableBodySelector;
}

export function getStrings() {
    return ensureConfig().strings;
}

export function getRealtimeConfig() {
    return ensureConfig().realtime || {};
}

export function getDataStore() {
    const config = ensureConfig();
    const store = window[config.dataKey];
    return Array.isArray(store) ? store : [];
}

export function setDataStore(data) {
    const config = ensureConfig();
    window[config.dataKey] = Array.isArray(data) ? data : [];
    return window[config.dataKey];
}

export function getEstadosMeta() {
    const config = ensureConfig();
    const meta = window[config.estadosMetaKey];
    if (meta && typeof meta === 'object') {
        return meta;
    }
    return {};
}

export function getReportingConfig() {
    const config = ensureConfig();
    return config.reporting || {};
}

export function getTurneroConfig() {
    const config = ensureConfig();
    return config.turnero || {};
}

export function setEstadosMeta(meta) {
    const config = ensureConfig();
    window[config.estadosMetaKey] = meta;
    return window[config.estadosMetaKey];
}

export function getWritePrefix() {
    return ensureConfig().writePrefix || '';
}

export function resolveWritePath(pathname) {
    const path = normalizePath(pathname);
    const writePrefix = getWritePrefix();

    if (!writePrefix) {
        return path;
    }

    if (path === writePrefix || path.startsWith(`${writePrefix}/`)) {
        return path;
    }

    if (
        path.includes(`${writePrefix}/solicitudes`)
        || path.includes(`${writePrefix}/api/solicitudes`)
    ) {
        return path;
    }

    const moduleMarkerIndex = path.indexOf('/solicitudes');
    if (moduleMarkerIndex >= 0) {
        const rootPrefix = path.slice(0, moduleMarkerIndex);
        const moduleSuffix = path.slice(moduleMarkerIndex);
        return `${rootPrefix}${writePrefix}${moduleSuffix}`;
    }

    const apiMarkerIndex = path.indexOf('/api/solicitudes');
    if (apiMarkerIndex >= 0) {
        const rootPrefix = path.slice(0, apiMarkerIndex);
        const apiSuffix = path.slice(apiMarkerIndex);
        return `${rootPrefix}${writePrefix}${apiSuffix}`;
    }

    return `${writePrefix}${path === '/' ? '' : path}`;
}
