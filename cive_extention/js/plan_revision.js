(function () {
    const PLAN_SELECTOR = '#docsolicitudprocedimientos-observacion_consulta';
    const TOOLBAR_ID = 'cive-plan-toolbar';
    const STATUS_ID = 'cive-plan-status';
    let toastrReady;
    let syncInProgress = false;
    let lastPlan = null;

    function resolveAsset(path) {
        try {
            if (typeof chrome !== 'undefined' && chrome.runtime && typeof chrome.runtime.getURL === 'function') {
                return chrome.runtime.getURL(path);
            }
        } catch (error) {
            // ignore
        }
        return `https://raw.githubusercontent.com/jl1dvg/cive_extention/main/${path}`;
    }

    function ensureToastr() {
        if (window.toastr) {
            return Promise.resolve(window.toastr);
        }
        if (toastrReady) {
            return toastrReady;
        }

        toastrReady = new Promise((resolve) => {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = resolveAsset('js/assets/toastr.min.css');
            document.head.appendChild(css);

            const script = document.createElement('script');
            script.src = resolveAsset('js/assets/toastr.min.js');
            script.onload = () => resolve(window.toastr || null);
            script.onerror = () => resolve(window.toastr || null);
            document.head.appendChild(script);

            setTimeout(() => resolve(window.toastr || null), 1500);
        });

        return toastrReady;
    }

    async function notify(type, message, title = 'Plan MedForge') {
        const lib = await ensureToastr();
        if (lib && typeof lib[type] === 'function') {
            lib.options = lib.options || {};
            lib.options.positionClass = lib.options.positionClass || 'toast-top-right';
            lib.options.preventDuplicates = true;
            lib[type](message, title);
        } else {
            console.log(`[${title}] ${type.toUpperCase()}: ${message}`);
        }
    }

    function getIdentifiers() {
        const params = new URLSearchParams(window.location.search);
        const formId = params.get('idSolicitud') || params.get('id') || params.get('form_id') || null;

        let hcNumber = null;
        const hcInput = document.querySelector('#numero-historia-clinica');
        if (hcInput && hcInput.value) {
            hcNumber = hcInput.value.trim();
        } else {
            const hcFromMedia = document.querySelector('.media-body p:nth-of-type(2)');
            if (hcFromMedia) {
                const text = hcFromMedia.textContent || '';
                const parts = text.split('HC #:');
                if (parts[1]) {
                    hcNumber = parts[1].trim();
                }
            }
        }

        if (!hcNumber) {
            try {
                const stored = JSON.parse(localStorage.getItem('datosPacienteSeleccionado') || '{}');
                hcNumber = stored.identificacion || stored.hcNumber || null;
            } catch (error) {
                // ignore parse errors
            }
        }

        return {formId, hcNumber};
    }

    function setStatus(text, tone = 'muted') {
        const status = document.getElementById(STATUS_ID);
        if (!status) return;
        const colors = {
            ok: '#198754',
            warn: '#d39e00',
            error: '#c82333',
            muted: '#6c757d',
        };
        status.textContent = text;
        status.dataset.tone = tone;
        status.style.color = colors[tone] || colors.muted;
    }

    async function fetchPlan() {
        await (window.configCIVE ? window.configCIVE.ready : Promise.resolve());
        const {formId, hcNumber} = getIdentifiers();
        if (!formId || !hcNumber) {
            throw new Error('No se pudo detectar form_id o HC para sincronizar plan.');
        }

        return window.CiveApiClient.get('/consultas/plan.php', {
            query: {form_id: formId, hcNumber},
            retries: 1,
            retryDelayMs: 800,
        });
    }

    async function savePlan(plan) {
        await (window.configCIVE ? window.configCIVE.ready : Promise.resolve());
        const {formId, hcNumber} = getIdentifiers();
        if (!formId || !hcNumber) {
            throw new Error('No se pudo detectar form_id o HC para guardar el plan.');
        }

        return window.CiveApiClient.post('/consultas/plan.php', {
            body: {form_id: formId, hcNumber, plan},
        });
    }

    function buildToolbar(textarea) {
        if (!textarea || document.getElementById(TOOLBAR_ID)) {
            return;
        }

        const toolbar = document.createElement('div');
        toolbar.id = TOOLBAR_ID;
        toolbar.style.display = 'flex';
        toolbar.style.gap = '8px';
        toolbar.style.alignItems = 'center';
        toolbar.style.marginTop = '6px';

        const status = document.createElement('span');
        status.id = STATUS_ID;
        status.textContent = 'Plan sin sincronizar';
        status.style.fontSize = '12px';
        status.style.color = '#6c757d';

        const loadBtn = document.createElement('button');
        loadBtn.type = 'button';
        loadBtn.className = 'btn btn-sm btn-outline-info';
        loadBtn.textContent = 'Revisar plan (MedForge)';
        loadBtn.addEventListener('click', () => refreshPlan(textarea, false));

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-sm btn-primary';
        saveBtn.textContent = 'Guardar plan en MedForge';
        saveBtn.addEventListener('click', () => pushPlan(textarea));

        toolbar.appendChild(loadBtn);
        toolbar.appendChild(saveBtn);
        toolbar.appendChild(status);

        textarea.parentElement?.appendChild(toolbar);
    }

    async function refreshPlan(textarea, silent = true) {
        if (syncInProgress) return;
        syncInProgress = true;
        setStatus('Consultando plan en MedForge...', 'muted');

        try {
            const resp = await fetchPlan();
            if (resp?.success && resp.data) {
                lastPlan = (resp.data.plan || '').trim();
                setStatus('Plan cargado desde MedForge', 'ok');

                if (!textarea.value || textarea.value.trim() === '') {
                    textarea.value = lastPlan;
                }

                if (!silent) {
                    await notify('info', 'Plan cargado desde MedForge');
                }
            } else {
                setStatus('Sin plan registrado en MedForge', 'warn');
                if (!silent) {
                    await notify('warning', 'No se encontró un plan guardado para este paciente/solicitud.');
                }
            }
        } catch (error) {
            setStatus('No se pudo consultar el plan', 'error');
            if (!silent) {
                await notify('error', error?.message || 'Error al consultar plan en MedForge.');
            }
        } finally {
            syncInProgress = false;
        }
    }

    async function pushPlan(textarea) {
        if (syncInProgress) return;
        const planText = (textarea.value || '').trim();
        if (planText === '') {
            await notify('warning', 'El plan está vacío, agrega contenido antes de enviarlo.');
            return;
        }

        syncInProgress = true;
        setStatus('Guardando plan en MedForge...', 'muted');

        try {
            const resp = await savePlan(planText);
            if (resp?.success) {
                lastPlan = planText;
                setStatus('Plan actualizado en MedForge', 'ok');
                await notify('success', 'Plan actualizado correctamente en MedForge.');
            } else {
                setStatus('No se pudo guardar el plan', 'error');
                await notify('error', resp?.message || 'No se pudo guardar el plan en MedForge.');
            }
        } catch (error) {
            setStatus('Error al guardar el plan', 'error');
            await notify('error', error?.message || 'Error al guardar plan en MedForge.');
        } finally {
            syncInProgress = false;
        }
    }

    function init() {
        const textarea = document.querySelector(PLAN_SELECTOR);
        if (!textarea) return;
        if (!window.CiveApiClient || typeof window.CiveApiClient.get !== 'function') {
            console.warn('CIVE Extension: CiveApiClient no está disponible para sincronizar el plan.');
            return;
        }
        buildToolbar(textarea);
        refreshPlan(textarea, true).catch(() => {
            // silencioso: ya se manejó estado/notify dentro de refreshPlan
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
