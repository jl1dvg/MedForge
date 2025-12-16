(function () {
    const CACHE = new Map();
    const inFlight = new Map();

    const DEFAULT_API_BASE_URL = (() => {
        try {
            if (typeof window !== 'undefined' && window.location && window.location.origin) {
                const base = window.location.origin.replace(/\/$/, '');
                return `${base}/api`;
            }
        } catch (e) {
            // ignore
        }
        return 'https://asistentecive.consulmed.me/api';
    })();
    const API_HOST_FIXES = {
        'asitentecive.consulmed.me': 'asistentecive.consulmed.me',
    };

    const DEFAULTS = {
        apiBaseUrl: DEFAULT_API_BASE_URL,
        timeoutMs: 12000,
        maxRetries: 2,
        retryDelayMs: 600,
        credentialsMode: 'include',
        proceduresCacheTtlMs: 300000,
    };

    // Proxy helper: usa el background script para saltar CORS cuando sea posible.
    const sendBg = (action, payload) => new Promise((resolve, reject) => {
        try {
            if (!chrome || !chrome.runtime || !chrome.runtime.sendMessage) {
                return reject(new Error('Background no disponible'));
            }
        } catch (error) {
            return reject(new Error('Background no disponible'));
        }

        chrome.runtime.sendMessage({action, ...payload}, (resp) => {
            const err = chrome.runtime.lastError;
            if (err) return reject(new Error(err.message || 'Error de runtime'));
            if (resp && resp.success === false) return reject(new Error(resp.error || 'Fallo en background'));
            resolve(resp && resp.data !== undefined ? resp.data : resp);
        });
    });

    function now() {
        return Date.now();
    }

    function delay(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function normalizePath(path) {
        if (typeof path !== 'string' || path === '') {
            return '';
        }
        return path.startsWith('/') ? path : `/${path}`;
    }

    function buildUrl(baseUrl, path, query = {}) {
        const url = new URL(baseUrl);
        const normalizedPath = normalizePath(path);
        url.pathname = `${url.pathname.replace(/\/$/, '')}${normalizedPath}`;
        Object.entries(query)
            .filter(([, value]) => value !== undefined && value !== null && value !== '')
            .forEach(([key, value]) => url.searchParams.set(key, value));
        return url.toString();
    }

    function createCacheKey(keyParts) {
        return JSON.stringify(keyParts);
    }

    function isSameOrigin(url) {
        try {
            const requestOrigin = new URL(url).origin;
            if (typeof window !== 'undefined' && window.location && window.location.origin) {
                return window.location.origin === requestOrigin;
            }
        } catch (error) {
            // ignore parse errors; assume same origin to avoid unnecessary downgrades
        }
        return true;
    }

    function normalizeApiBaseUrl(value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return DEFAULT_API_BASE_URL;
        }

        let normalized = value.trim();
        let parsed;

        try {
            parsed = new URL(normalized);
        } catch (error) {
            try {
                parsed = new URL(`https://${normalized.replace(/^\/+/, '')}`);
            } catch (innerError) {
                return DEFAULT_API_BASE_URL;
            }
        }

        const fix = API_HOST_FIXES[parsed.hostname.toLowerCase()];
        if (fix) {
            parsed.hostname = fix;
        }

        normalized = parsed.toString();
        return normalized.replace(/\/+$/, '');
    }

    function readConfig() {
        if (!window.configCIVE || typeof window.configCIVE.get !== 'function') {
            return {...DEFAULTS};
        }
        const state = window.configCIVE.get();
        return {
            apiBaseUrl: normalizeApiBaseUrl(state.apiBaseUrl || DEFAULTS.apiBaseUrl),
            timeoutMs: state.apiTimeoutMs || DEFAULTS.timeoutMs,
            maxRetries: typeof state.apiMaxRetries === 'number' ? state.apiMaxRetries : DEFAULTS.maxRetries,
            retryDelayMs: typeof state.apiRetryDelayMs === 'number' ? state.apiRetryDelayMs : DEFAULTS.retryDelayMs,
            credentialsMode: state.apiCredentialsMode || DEFAULTS.credentialsMode,
            proceduresCacheTtlMs: typeof state.proceduresCacheTtlMs === 'number' ? state.proceduresCacheTtlMs : DEFAULTS.proceduresCacheTtlMs,
        };
    }

    async function executeRequest(method, path, options = {}) {
        await (window.configCIVE ? window.configCIVE.ready : Promise.resolve());
        const config = readConfig();
        const {
            query = {},
            body = undefined,
            bodyType = method === 'GET' ? null : (options.bodyType || 'json'),
            cacheKey = null,
            cacheTtlMs = null,
            useCache = false,
            expectJson = options.expectJson !== false,
            headers = {},
            retries = typeof options.retries === 'number' ? options.retries : config.maxRetries,
            timeoutMs = typeof options.timeoutMs === 'number' ? options.timeoutMs : config.timeoutMs,
            retryDelayMs = typeof options.retryDelayMs === 'number' ? options.retryDelayMs : config.retryDelayMs,
            credentials = options.credentials ?? config.credentialsMode ?? DEFAULTS.credentialsMode,
        } = options;

        const finalCacheKey = cacheKey || (useCache ? createCacheKey([method, path, query, body]) : null);
        if (finalCacheKey && CACHE.has(finalCacheKey)) {
            const record = CACHE.get(finalCacheKey);
            if (record && record.expiresAt > now()) {
                return record.data;
            }
            CACHE.delete(finalCacheKey);
        }

        const url = path.startsWith('http://') || path.startsWith('https://')
            ? path
            : buildUrl(config.apiBaseUrl, path, query);

        const bodyStrategies = [];
        if (method === 'GET' || body === undefined) {
            bodyStrategies.push(null);
        } else if (bodyType === 'auto') {
            bodyStrategies.push('json', 'form');
        } else {
            bodyStrategies.push(bodyType);
        }

        const attemptFetch = async (encoding, credentialMode) => {
            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            const fetchOptions = {
                method,
                headers: {...headers},
                credentials: credentialMode,
            };
            if (controller) {
                fetchOptions.signal = controller.signal;
            }

            if (body !== undefined && method !== 'GET') {
                if (encoding === 'form') {
                    fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
                    fetchOptions.body = new URLSearchParams(body).toString();
                } else if (encoding === 'json') {
                    fetchOptions.headers['Content-Type'] = 'application/json;charset=UTF-8';
                    fetchOptions.body = JSON.stringify(body);
                } else if (encoding === 'raw') {
                    fetchOptions.body = body;
                }
            }

            const timer = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;
            try {
                const response = await fetch(url, fetchOptions);
                if (!response.ok) {
                    const error = new Error(`HTTP ${response.status}`);
                    error.response = response;
                    throw error;
                }

                if (!expectJson) {
                    return response;
                }

                return await response.json();
            } finally {
                if (timer) {
                    clearTimeout(timer);
                }
            }
        };

        const inflightKey = finalCacheKey ? `cache:${finalCacheKey}` : createCacheKey([method, url, body]);
        if (inFlight.has(inflightKey)) {
            return inFlight.get(inflightKey);
        }

        const credentialModes = (() => {
            if (credentials === 'include' && !isSameOrigin(url)) {
                // Evita ruido CORS: primero intenta vía background y, si falla, retrocede a fetch sin credenciales.
                return ['background', 'include', 'omit'];
            }
            return [credentials];
        })();

        const runner = (async () => {
            let lastError;
            let triedBackground = false;
            for (let attempt = 0; attempt <= retries; attempt++) {
                for (const credentialMode of credentialModes) {
                    if (credentialMode === 'background') {
                        const canProxy = !isSameOrigin(url);
                        if (canProxy && !triedBackground) {
                            triedBackground = true;
                            try {
                                const data = await sendBg('apiRequest', {
                                    url,
                                    method,
                                    headers,
                                    body,
                                    bodyType: bodyStrategies.find(Boolean) || null,
                                    expectJson: options.expectJson !== false,
                                });
                                if (finalCacheKey && cacheTtlMs) {
                                    CACHE.set(finalCacheKey, {
                                        data,
                                        expiresAt: now() + cacheTtlMs,
                                    });
                                }
                                return data;
                            } catch (error) {
                                lastError = error;
                            }
                        }
                        continue;
                    }

                    for (const encoding of bodyStrategies) {
                        try {
                            const result = await attemptFetch(encoding, credentialMode);
                            if (finalCacheKey && cacheTtlMs) {
                                CACHE.set(finalCacheKey, {
                                    data: result,
                                    expiresAt: now() + cacheTtlMs,
                                });
                            }
                            return result;
                        } catch (error) {
                            lastError = error;
                        }
                    }
                    if (credentialMode !== credentials && credentialMode !== 'background') {
                        console.info(`[CIVE] Reintentando ${method} ${url} sin credenciales por política CORS.`);
                    }
                }

                if (attempt < retries) {
                    await delay(retryDelayMs);
                }
            }

            throw lastError;
        })();

        inFlight.set(inflightKey, runner);
        try {
            return await runner;
        } finally {
            inFlight.delete(inflightKey);
        }
    }

    window.CiveApiClient = {
        get(path, options = {}) {
            return executeRequest('GET', path, {...options, body: undefined});
        },
        post(path, options = {}) {
            return executeRequest('POST', path, options);
        },
        request(method, path, options = {}) {
            return executeRequest(method.toUpperCase(), path, options);
        },
        invalidate(cacheKey) {
            CACHE.delete(cacheKey);
        },
        baseUrl() {
            const config = readConfig();
            return config.apiBaseUrl;
        },
        apiOrigin() {
            try {
                return new URL(this.baseUrl()).origin;
            } catch (error) {
                return this.baseUrl();
            }
        },
    };
})();
