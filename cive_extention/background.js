const STORAGE_KEYS = {
    bootstrap: 'civeExtensionBootstrap',
    config: 'civeExtensionConfig',
    cachedHcNumber: 'hcNumber',
    cachedExpiry: 'fechaCaducidad',
};

const DEFAULT_CONTROL_ENDPOINT = 'https://cive.consulmed.me/api/cive-extension/config';
const DEFAULT_REFRESH_INTERVAL_MS = 900000; // 15 minutos
const SYNC_ALARM_NAME = 'civeExtension.sync';

function storageGet(keys) {
    return new Promise((resolve) => {
        chrome.storage.local.get(keys, (items) => {
            if (chrome.runtime.lastError) {
                console.error('storageGet error:', chrome.runtime.lastError.message);
                resolve({});
                return;
            }
            resolve(items);
        });
    });
}

function storageSet(values) {
    return new Promise((resolve) => {
        chrome.storage.local.set(values, () => {
            if (chrome.runtime.lastError) {
                console.error('storageSet error:', chrome.runtime.lastError.message);
            }
            resolve();
        });
    });
}

function sanitizeInterval(ms) {
    const parsed = Number(ms);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return DEFAULT_REFRESH_INTERVAL_MS;
    }
    return Math.max(60000, parsed);
}

async function determineControlEndpoint() {
    const { [STORAGE_KEYS.bootstrap]: bootstrap } = await storageGet([STORAGE_KEYS.bootstrap]);
    if (bootstrap && typeof bootstrap.controlEndpoint === 'string' && bootstrap.controlEndpoint !== '') {
        return bootstrap.controlEndpoint;
    }
    return DEFAULT_CONTROL_ENDPOINT;
}

async function determineRefreshInterval() {
    const [{ [STORAGE_KEYS.config]: config }, { [STORAGE_KEYS.bootstrap]: bootstrap }] = await Promise.all([
        storageGet([STORAGE_KEYS.config]),
        storageGet([STORAGE_KEYS.bootstrap]),
    ]);

    const candidate = config?.refreshIntervalMs ?? bootstrap?.refreshIntervalMs ?? DEFAULT_REFRESH_INTERVAL_MS;
    return sanitizeInterval(candidate);
}

async function scheduleConfigSync() {
    const intervalMs = await determineRefreshInterval();
    const intervalMinutes = Math.max(1, intervalMs / 60000);

    chrome.alarms.clear(SYNC_ALARM_NAME, () => {
        chrome.alarms.create(SYNC_ALARM_NAME, {
            periodInMinutes: intervalMinutes,
            delayInMinutes: Math.min(intervalMinutes, 0.5),
        });
    });
}

async function fetchRemoteConfig(reason = 'auto') {
    const endpoint = await determineControlEndpoint();
    try {
        const response = await fetch(endpoint, {
            method: 'GET',
            credentials: 'include',
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload || payload.success === false || !payload.config) {
            throw new Error(payload?.message || 'Respuesta inesperada desde MedForge.');
        }

        const config = {...payload.config, fetchedAt: Date.now(), fetchedBy: reason};
        await storageSet({[STORAGE_KEYS.config]: config});
        return config;
    } catch (error) {
        console.error('No fue posible sincronizar la configuración de CIVE Extension:', error);
        throw error;
    }
}

async function initializeBackground() {
    try {
        await fetchRemoteConfig('startup');
    } catch (error) {
        // La sincronización puede fallar si el usuario no está autenticado todavía.
    }
    await scheduleConfigSync();
}

async function handleOpenAiRequest(prompt) {
    const { [STORAGE_KEYS.config]: config } = await storageGet([STORAGE_KEYS.config]);
    const openAi = config?.openAi || {};
    const apiKey = openAi.apiKey || '';
    const model = openAi.model || 'gpt-4o-mini';

    if (!apiKey) {
        return {
            success: false,
            text: 'OpenAI no está configurado desde MedForge.',
        };
    }

    try {
        const response = await fetch('https://api.openai.com/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${apiKey}`,
            },
            body: JSON.stringify({
                model,
                messages: [
                    {
                        role: 'user',
                        content: String(prompt ?? ''),
                    },
                ],
                temperature: 0.2,
                max_tokens: 512,
            }),
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const data = await response.json();
        const text = data?.choices?.[0]?.message?.content?.trim() ?? '';
        return {
            success: true,
            text,
        };
    } catch (error) {
        console.error('Error en OpenAI API:', error);
        return {
            success: false,
            text: 'Error al procesar la solicitud con OpenAI.',
        };
    }
}

async function handleSubscriptionCheck() {
    const [{ [STORAGE_KEYS.bootstrap]: bootstrap }, { [STORAGE_KEYS.config]: config }] = await Promise.all([
        storageGet([STORAGE_KEYS.bootstrap]),
        storageGet([STORAGE_KEYS.config]),
    ]);

    const subscriptionEndpoint = bootstrap?.subscriptionEndpoint
        || (config?.api?.baseUrl ? `${config.api.baseUrl.replace(/\/$/, '')}/subscription/check.php` : null);

    if (!subscriptionEndpoint) {
        return {success: false, error: 'No se encontró el endpoint de suscripción.'};
    }

    try {
        const response = await fetch(subscriptionEndpoint, {
            method: 'GET',
            credentials: 'include',
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json().catch(() => ({}));
        const isSubscribed = Boolean(data.isSubscribed ?? data.success ?? false);
        const isApproved = Boolean(data.isApproved ?? data.authorized ?? false);
        return {success: isSubscribed && isApproved, raw: data};
    } catch (error) {
        console.error('Error al verificar la suscripción:', error);
        return {success: false, error: 'No fue posible verificar la suscripción en MedForge.'};
    }
}

chrome.runtime.onInstalled.addListener(() => {
    initializeBackground().catch((error) => console.warn('Inicialización diferida de CIVE Extension:', error));
});

chrome.runtime.onStartup.addListener(() => {
    initializeBackground().catch((error) => console.warn('Inicialización diferida de CIVE Extension:', error));
});

chrome.alarms.onAlarm.addListener((alarm) => {
    if (alarm?.name !== SYNC_ALARM_NAME) {
        return;
    }
    fetchRemoteConfig('alarm').finally(() => {
        scheduleConfigSync().catch((error) => console.warn('No fue posible reprogramar la sincronización de CIVE Extension:', error));
    });
});

chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'local') {
        return;
    }
    if (Object.prototype.hasOwnProperty.call(changes, STORAGE_KEYS.bootstrap)) {
        scheduleConfigSync().catch((error) => console.warn('Error al actualizar la planificación de sincronización:', error));
        fetchRemoteConfig('bootstrap-update').catch(() => {
            // La sincronización puede fallar si no hay sesión activa todavía.
        });
    }
});

chrome.commands.onCommand.addListener((command) => {
    if (command !== 'ejecutar-examen-directo') {
        return;
    }

    chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
        if (!tabs || tabs.length === 0) {
            console.error('No se encontró la pestaña activa.');
            return;
        }

        chrome.scripting.executeScript({
            target: {tabId: tabs[0].id},
            files: ['js/examenes.js']
        }, () => {
            chrome.scripting.executeScript({
                target: {tabId: tabs[0].id},
                func: (examenId) => {
                    if (typeof ejecutarExamenes === 'function') {
                        ejecutarExamenes(examenId);
                    } else {
                        console.error('ejecutarExamenes no está definida.');
                    }
                },
                args: ['octm']
            });
        });
    });
});

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (!request || typeof request.action !== 'string') {
        return false;
    }

    if (request.action === 'getFechaCaducidad') {
        chrome.storage.local.get([STORAGE_KEYS.cachedHcNumber, STORAGE_KEYS.cachedExpiry], (result) => {
            if (result[STORAGE_KEYS.cachedHcNumber] === request.hcNumber) {
                sendResponse({fechaCaducidad: result[STORAGE_KEYS.cachedExpiry] ?? null});
            } else {
                sendResponse({fechaCaducidad: null});
            }
        });
        return true;
    }

    if (request.action === 'consultaAnterior') {
        chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
            if (!tabs || tabs.length === 0) {
                console.error('No se encontró la pestaña activa.');
                return;
            }

            chrome.scripting.executeScript({
                target: {tabId: tabs[0].id}, files: ['js/consulta.js']
            }, () => {
                chrome.scripting.executeScript({
                    target: {tabId: tabs[0].id}, function: () => {
                        if (typeof consultaAnterior === 'function') {
                            consultaAnterior();
                        } else {
                            console.error('consultaAnterior no está definida.');
                        }
                    }
                });
            });
        });
        return false;
    }

    if (request.action === 'openai_request') {
        handleOpenAiRequest(request.prompt)
            .then((result) => sendResponse({text: result.text, success: result.success}))
            .catch(() => sendResponse({text: 'Error al procesar la solicitud con OpenAI.', success: false}));
        return true;
    }

    if (request.action === 'ejecutarPopEnPagina') {
        chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
            if (!tabs || tabs.length === 0) {
                console.error('No se encontró la pestaña activa.');
                return;
            }

            chrome.scripting.executeScript({
                target: {tabId: tabs[0].id}, files: ['js/consulta.js']
            }, () => {
                chrome.scripting.executeScript({
                    target: {tabId: tabs[0].id}, function: () => {
                        if (typeof ejecutarPopEnPagina === 'function') {
                            ejecutarPopEnPagina();
                        } else {
                            console.error('ejecutarPopEnPagina no está definida.');
                        }
                    }
                });
            });
        });
        return false;
    }

    if (request.action === 'generatePDF') {
        chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
            if (!tabs || tabs.length === 0) {
                sendResponse({success: false, error: 'No se encontró una pestaña activa.'});
                return;
            }
            chrome.tabs.sendMessage(tabs[0].id, {
                action: 'generatePDF',
                content: request.content
            }, (response) => {
                if (chrome.runtime.lastError) {
                    console.error('Error enviando generatePDF:', chrome.runtime.lastError);
                    sendResponse({success: false, error: chrome.runtime.lastError.message});
                    return;
                }
                sendResponse(response);
            });
        });
        return true;
    }

    if (request.action === 'checkSubscription') {
        handleSubscriptionCheck().then(sendResponse);
        return true;
    }

    if (request.action === 'ejecutarProtocolo') {
        const item = request.item;
        chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
            if (chrome.runtime.lastError || !tabs || tabs.length === 0) {
                console.error('Error al obtener la pestaña activa:', chrome.runtime.lastError || 'No se encontraron pestañas activas.');
                return;
            }
            const tabId = tabs[0].id;
            if (!tabId) {
                console.error('No se pudo obtener el ID de la pestaña.');
                return;
            }

            chrome.scripting.executeScript({
                target: {tabId}, files: ['js/procedimientos.js']
            }, () => {
                chrome.scripting.executeScript({
                    target: {tabId},
                    func: (payload) => {
                        if (typeof ejecutarProtocoloEnPagina === 'function') {
                            ejecutarProtocoloEnPagina(payload);
                        } else {
                            console.error('ejecutarProtocoloEnPagina no está definida.');
                        }
                    },
                    args: [item],
                });
            });
        });
        return false;
    }

    if (request.action === 'ejecutarReceta') {
        const item = request.item;
        chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
            if (chrome.runtime.lastError || !tabs || tabs.length === 0) {
                console.error('Error al obtener la pestaña activa:', chrome.runtime.lastError || 'No se encontraron pestañas activas.');
                return;
            }
            const tabId = tabs[0].id;
            if (!tabId) {
                console.error('No se pudo obtener el ID de la pestaña.');
                return;
            }

            chrome.scripting.executeScript({
                target: {tabId}, files: ['js/recetas.js']
            }, () => {
                chrome.scripting.executeScript({
                    target: {tabId},
                    func: (payload) => {
                        if (typeof ejecutarRecetaEnPagina === 'function') {
                            ejecutarRecetaEnPagina(payload);
                        } else {
                            console.error('ejecutarRecetaEnPagina no está definida.');
                        }
                    },
                    args: [item],
                });
            });
        });
        return false;
    }

    if (request.action === 'solicitudesEstado') {
        const hcNumber = (request.hcNumber || '').toString().trim();
        if (!hcNumber) {
            sendResponse({success: false, error: 'Falta hcNumber'});
            return false;
        }
        const base = 'https://asistentecive.consulmed.me/api/solicitudes/estado.php';
        const url = new URL(base);
        url.searchParams.set('hcNumber', hcNumber);
        fetch(url.toString(), {
            method: 'GET',
            credentials: 'include',
        })
            .then((resp) => {
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                return resp.json();
            })
            .then((data) => sendResponse({success: true, data}))
            .catch((error) => {
                console.error('Error en solicitudesEstado:', error);
                sendResponse({success: false, error: error.message || 'Error al consultar solicitudes'});
            });
        return true;
    }

    if (request.action === 'proyeccionesGet') {
        const path = (request.path || '').toString();
        const query = request.query || {};
        if (!path.startsWith('/')) {
            sendResponse({success: false, error: 'Path inválido'});
            return false;
        }
        const url = new URL(`https://asistentecive.consulmed.me${path}`);
        Object.entries(query).forEach(([k, v]) => {
            if (v !== undefined && v !== null) url.searchParams.set(k, v);
        });
        fetch(url.toString(), {method: 'GET', credentials: 'include'})
            .then((resp) => {
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                return resp.json();
            })
            .then((data) => sendResponse({success: true, data}))
            .catch((error) => {
                console.error('Error en proyeccionesGet:', error);
                sendResponse({success: false, error: error.message || 'Error al consultar proyecciones'});
            });
        return true;
    }

    return false;
});
