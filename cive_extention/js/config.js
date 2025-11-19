(function () {
    const DEFAULT_ORIGIN = (() => {
        try {
            if (window.location && window.location.origin) {
                const origin = window.location.origin.replace(/\/$/, '');
                if (origin) {
                    return origin;
                }
            }
        } catch (error) {
            // ignore
        }
        return 'https://cive.consulmed.me';
    })();

    const DEFAULT_API_BASE_URL = 'https://asistentecive.consulmed.me/api';
    const API_HOST_FIXES = {
        'asitentecive.consulmed.me': 'asistentecive.consulmed.me',
    };
    const VALID_CREDENTIALS = ['omit', 'same-origin', 'include'];
    const LEGACY_KEY_MAP = {
        ES_LOCAL: 'esLocal',
        EXTENSION_ID: 'extensionId',
    };

    function detectLocalEnvironment() {
        try {
            const {hostname = '', port = ''} = window.location || {};
            if (!hostname) {
                return false;
            }

            const normalizedHost = hostname.toLowerCase();
            if (['localhost', '127.0.0.1'].includes(normalizedHost)) {
                return true;
            }

            if (
                normalizedHost.startsWith('192.168.')
                || normalizedHost.startsWith('10.')
                || normalizedHost.endsWith('.local')
            ) {
                return true;
            }

            if (normalizedHost.includes('ddns') || port === '8085') {
                return true;
            }
        } catch (error) {
            console.warn('No fue posible detectar el entorno local:', error);
        }
        return false;
    }

    function normalizeCredentialsMode(value, fallback) {
        const candidate = (value || '').toString().trim().toLowerCase();
        if (VALID_CREDENTIALS.includes(candidate)) {
            return candidate;
        }
        return fallback;
    }

    function normalizeApiBaseUrl(value, fallback = DEFAULT_API_BASE_URL) {
        if (typeof value !== 'string' || value.trim() === '') {
            return fallback;
        }

        let normalized = value.trim();
        try {
            const parsed = new URL(normalized);
            const hostFix = API_HOST_FIXES[parsed.hostname.toLowerCase()];
            if (hostFix) {
                parsed.hostname = hostFix;
            }
            normalized = parsed.toString();
        } catch (error) {
            return fallback;
        }

        return normalized.replace(/\/+$/, '');
    }

    const defaultLocal = detectLocalEnvironment();

    const DEFAULT_STATE = {
        controlEndpoint: `${DEFAULT_ORIGIN}/api/cive-extension/config`,
        healthEndpoint: `${DEFAULT_ORIGIN}/api/cive-extension/health-check`,
        healthHistoryEndpoint: `${DEFAULT_ORIGIN}/api/cive-extension/health-checks`,
        subscriptionEndpoint: `${DEFAULT_API_BASE_URL}/subscription/check.php`,
        refreshIntervalMs: 900000,
        apiBaseUrl: DEFAULT_API_BASE_URL,
        apiTimeoutMs: 12000,
        apiMaxRetries: 2,
        apiRetryDelayMs: 600,
        apiCredentialsMode: 'include',
        proceduresCacheTtlMs: 300000,
        healthEnabled: false,
        healthEndpoints: [],
        healthMaxAgeMinutes: 60,
        openAi: {
            apiKey: '',
            model: 'gpt-4o-mini'
        },
        esLocal: defaultLocal,
        extensionId: defaultLocal ? 'JORGE' : 'CIVE',
    };

    let current = {...DEFAULT_STATE};
    const listeners = new Set();
    let resolveReady;
    const ready = new Promise((resolve) => {
        resolveReady = resolve;
    });

    function cloneState() {
        return JSON.parse(JSON.stringify(current));
    }

    function notify() {
        const snapshot = cloneState();
        listeners.forEach((listener) => {
            try {
                listener(snapshot);
            } catch (error) {
                console.warn('configCIVE listener error:', error);
            }
        });
    }

    function applyPartial(partial) {
        if (!partial || typeof partial !== 'object') {
            return;
        }
        const next = {...current};
        Object.entries(partial).forEach(([key, value]) => {
            if (value === undefined) {
                return;
            }
            if (key === 'openAi' && value && typeof value === 'object') {
                next.openAi = {...next.openAi, ...value};
            } else if (key === 'esLocal') {
                next.esLocal = Boolean(value);
            } else if (key === 'extensionId') {
                next.extensionId = typeof value === 'string' ? value : next.extensionId;
            } else if (key === 'apiBaseUrl') {
                next.apiBaseUrl = normalizeApiBaseUrl(value, next.apiBaseUrl || DEFAULT_API_BASE_URL);
            } else if (key === 'apiCredentialsMode') {
                next.apiCredentialsMode = normalizeCredentialsMode(
                    value,
                    next.apiCredentialsMode || DEFAULT_STATE.apiCredentialsMode
                );
            } else {
                next[key] = value;
            }
        });
        current = next;
        notify();
    }

    function normalizeBootstrap(bootstrap) {
        if (!bootstrap || typeof bootstrap !== 'object') {
            return {};
        }

        const flags = bootstrap.flags || {};
        const esLocalFlag = Object.prototype.hasOwnProperty.call(bootstrap, 'esLocal')
            ? bootstrap.esLocal
            : (Object.prototype.hasOwnProperty.call(flags, 'esLocal') ? flags.esLocal : undefined);
        const extensionIdFlag = Object.prototype.hasOwnProperty.call(bootstrap, 'extensionId')
            ? bootstrap.extensionId
            : (Object.prototype.hasOwnProperty.call(flags, 'extensionId') ? flags.extensionId : undefined);
        const credentialsMode = bootstrap.api?.credentialsMode
            ?? bootstrap.apiCredentialsMode
            ?? flags.apiCredentialsMode;

        return {
            controlEndpoint: bootstrap.controlEndpoint || DEFAULT_STATE.controlEndpoint,
            healthEndpoint: bootstrap.healthEndpoint || DEFAULT_STATE.healthEndpoint,
            healthHistoryEndpoint: bootstrap.healthHistoryEndpoint || DEFAULT_STATE.healthHistoryEndpoint,
            subscriptionEndpoint: bootstrap.subscriptionEndpoint || DEFAULT_STATE.subscriptionEndpoint,
            refreshIntervalMs: typeof bootstrap.refreshIntervalMs === 'number' ? bootstrap.refreshIntervalMs : DEFAULT_STATE.refreshIntervalMs,
            apiBaseUrl: normalizeApiBaseUrl(
                bootstrap.api?.baseUrl || bootstrap.apiBaseUrl || current.apiBaseUrl || DEFAULT_API_BASE_URL,
                current.apiBaseUrl || DEFAULT_API_BASE_URL
            ),
            apiTimeoutMs: typeof (bootstrap.api?.timeoutMs ?? bootstrap.apiTimeoutMs) === 'number' ? (bootstrap.api?.timeoutMs ?? bootstrap.apiTimeoutMs) : DEFAULT_STATE.apiTimeoutMs,
            apiMaxRetries: typeof (bootstrap.api?.maxRetries ?? bootstrap.apiMaxRetries) === 'number' ? (bootstrap.api?.maxRetries ?? bootstrap.apiMaxRetries) : DEFAULT_STATE.apiMaxRetries,
            apiRetryDelayMs: typeof (bootstrap.api?.retryDelayMs ?? bootstrap.apiRetryDelayMs) === 'number' ? (bootstrap.api?.retryDelayMs ?? bootstrap.apiRetryDelayMs) : DEFAULT_STATE.apiRetryDelayMs,
            apiCredentialsMode: normalizeCredentialsMode(credentialsMode, current.apiCredentialsMode || DEFAULT_STATE.apiCredentialsMode),
            proceduresCacheTtlMs: typeof (bootstrap.api?.cacheTtlMs ?? bootstrap.proceduresCacheTtlMs) === 'number' ? (bootstrap.api?.cacheTtlMs ?? bootstrap.proceduresCacheTtlMs) : DEFAULT_STATE.proceduresCacheTtlMs,
            healthEnabled: Boolean(bootstrap.health?.enabled ?? bootstrap.healthEnabled),
            healthEndpoints: Array.isArray(bootstrap.health?.endpoints) ? bootstrap.health.endpoints : DEFAULT_STATE.healthEndpoints,
            healthMaxAgeMinutes: typeof (bootstrap.health?.maxAgeMinutes ?? bootstrap.healthMaxAgeMinutes) === 'number' ? (bootstrap.health?.maxAgeMinutes ?? bootstrap.healthMaxAgeMinutes) : DEFAULT_STATE.healthMaxAgeMinutes,
            esLocal: typeof esLocalFlag === 'boolean' ? esLocalFlag : current.esLocal,
            extensionId: typeof extensionIdFlag === 'string' && extensionIdFlag !== '' ? extensionIdFlag : current.extensionId,
        };
    }

    function normalizeRemote(config) {
        if (!config || typeof config !== 'object') {
            return {};
        }

        const flags = config.flags || {};
        const credentialsMode = config.api?.credentialsMode
            ?? config.apiCredentialsMode
            ?? flags.apiCredentialsMode;

        return {
            apiBaseUrl: normalizeApiBaseUrl(config.api?.baseUrl || current.apiBaseUrl, current.apiBaseUrl || DEFAULT_API_BASE_URL),
            apiTimeoutMs: typeof config.api?.timeoutMs === 'number' ? config.api.timeoutMs : current.apiTimeoutMs,
            apiMaxRetries: typeof config.api?.maxRetries === 'number' ? config.api.maxRetries : current.apiMaxRetries,
            apiRetryDelayMs: typeof config.api?.retryDelayMs === 'number' ? config.api.retryDelayMs : current.apiRetryDelayMs,
            proceduresCacheTtlMs: typeof config.api?.cacheTtlMs === 'number' ? config.api.cacheTtlMs : current.proceduresCacheTtlMs,
            refreshIntervalMs: typeof config.refreshIntervalMs === 'number' ? config.refreshIntervalMs : current.refreshIntervalMs,
            healthEnabled: Boolean(config.health?.enabled),
            healthEndpoints: Array.isArray(config.health?.endpoints) ? config.health.endpoints : current.healthEndpoints,
            healthMaxAgeMinutes: typeof config.health?.maxAgeMinutes === 'number' ? config.health.maxAgeMinutes : current.healthMaxAgeMinutes,
            openAi: config.openAi && typeof config.openAi === 'object' ? config.openAi : current.openAi,
            subscriptionEndpoint: current.subscriptionEndpoint,
            apiCredentialsMode: normalizeCredentialsMode(credentialsMode, current.apiCredentialsMode || DEFAULT_STATE.apiCredentialsMode),
            esLocal: typeof flags.esLocal === 'boolean' ? flags.esLocal : current.esLocal,
            extensionId: typeof flags.extensionId === 'string' && flags.extensionId !== '' ? flags.extensionId : current.extensionId,
        };
    }

    function persistBootstrap(bootstrap) {
        if (typeof chrome === 'undefined' || !chrome.storage || !chrome.storage.local) {
            return;
        }
        chrome.storage.local.set({civeExtensionBootstrap: bootstrap});
    }

    function bootstrapFromPage() {
        const medf = window.MEDF || {};
        if (medf.civeExtension && typeof medf.civeExtension === 'object') {
            const normalized = normalizeBootstrap(medf.civeExtension);
            applyPartial(normalized);
            persistBootstrap(medf.civeExtension);
        }
    }

    function loadFromStorage() {
        if (typeof chrome === 'undefined' || !chrome.storage || !chrome.storage.local) {
            resolveReady(cloneState());
            return;
        }

        chrome.storage.local.get(['civeExtensionBootstrap', 'civeExtensionConfig'], (items) => {
            if (items && items.civeExtensionBootstrap) {
                applyPartial(normalizeBootstrap(items.civeExtensionBootstrap));
            }
            if (items && items.civeExtensionConfig) {
                applyPartial(normalizeRemote(items.civeExtensionConfig));
            }
            resolveReady(cloneState());
        });
    }

    if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.onChanged) {
        chrome.storage.onChanged.addListener((changes, area) => {
            if (area !== 'local') {
                return;
            }
            if (changes.civeExtensionBootstrap) {
                applyPartial(normalizeBootstrap(changes.civeExtensionBootstrap.newValue));
            }
            if (changes.civeExtensionConfig) {
                applyPartial(normalizeRemote(changes.civeExtensionConfig.newValue));
            }
        });
    }

    bootstrapFromPage();
    loadFromStorage();

    window.configCIVE = {
        ready,
        get(key, fallback = undefined) {
            if (!key) {
                return cloneState();
            }
            const normalizedKey = LEGACY_KEY_MAP[key] || key;
            return Object.prototype.hasOwnProperty.call(current, normalizedKey)
                ? current[normalizedKey]
                : fallback;
        },
        subscribe(callback) {
            if (typeof callback !== 'function') {
                return () => {};
            }
            listeners.add(callback);
            callback(cloneState());
            return () => listeners.delete(callback);
        },
        refreshFromStorage() {
            if (typeof chrome === 'undefined' || !chrome.storage || !chrome.storage.local) {
                return Promise.resolve(cloneState());
            }
            return new Promise((resolve) => {
                chrome.storage.local.get(['civeExtensionBootstrap', 'civeExtensionConfig'], (items) => {
                    if (items && items.civeExtensionBootstrap) {
                        applyPartial(normalizeBootstrap(items.civeExtensionBootstrap));
                    }
                    if (items && items.civeExtensionConfig) {
                        applyPartial(normalizeRemote(items.civeExtensionConfig));
                    }
                    resolve(cloneState());
                });
            });
        },
    };

    Object.defineProperties(window.configCIVE, {
        ES_LOCAL: {
            get() {
                return Boolean(current.esLocal);
            },
            set(value) {
                const nextValue = Boolean(value);
                if (current.esLocal !== nextValue) {
                    current.esLocal = nextValue;
                    notify();
                }
            },
            configurable: false,
            enumerable: true,
        },
        EXTENSION_ID: {
            get() {
                const baseId = current.esLocal ? 'JORGE' : 'CIVE';
                return current.extensionId && current.extensionId !== '' ? current.extensionId : baseId;
            },
            set(value) {
                const normalized = (value || '').toString().trim();
                if (current.extensionId !== normalized) {
                    current.extensionId = normalized;
                    notify();
                }
            },
            configurable: false,
            enumerable: true,
        },
    });
})();
