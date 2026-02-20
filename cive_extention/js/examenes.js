if (window.__CIVE_EXAMENES_JS_LOADED__) {
    console.debug('examenes.js ya estaba cargado; se omite reinyección.');
} else {
    window.__CIVE_EXAMENES_JS_LOADED__ = true;

// ==== Helpers de contexto de extensión ====
    function isExtensionContextActive() {
        try {
            return !!(typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.id);
        } catch (e) {
            return false;
        }
    }

    function safeGetURL(path) {
        if (isExtensionContextActive()) {
            try {
                return chrome.runtime.getURL(path);
            } catch (e) {
                console.warn('getURL falló:', e);
            }
        }
        return null; // En este archivo preferimos no usar remoto para no romper postMessage
    }

    const MODAL_STYLE_ID = 'cive-exam-modal-style';

    function ensureModalStyles() {
        if (document.getElementById(MODAL_STYLE_ID)) return;
        const style = document.createElement('style');
        style.id = MODAL_STYLE_ID;
        style.innerHTML = `
    .cive-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(12, 18, 38, 0.55);
        backdrop-filter: blur(2px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 16px;
    }
    .cive-modal-window {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        min-width: 340px;
        max-width: 90vw;
        width: 600px;
        max-height: 92vh;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.25);
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    }
    .cive-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px 12px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    }
    .cive-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #f8fafc;
    }
    .cive-modal-kicker {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 11px;
        color: #94a3b8;
    }
    .cive-modal-close {
        background: none;
        border: none;
        color: #e2e8f0;
        font-size: 24px;
        cursor: pointer;
        line-height: 1;
        transition: transform 120ms ease, color 120ms ease;
    }
    .cive-modal-close:hover {
        color: #38bdf8;
        transform: scale(1.05);
    }
    .cive-modal-body {
        padding: 14px 20px 6px;
        overflow-y: auto;
        max-height: 65vh;
    }
    .cive-form-group {
        margin-bottom: 12px;
    }
    .cive-form-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        color: #cbd5e1;
    }
    .cive-input {
        width: 100%;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        background: rgba(15, 23, 42, 0.6);
        color: #e2e8f0;
        padding: 10px 12px;
        font-size: 14px;
        transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }
    .cive-input:focus {
        outline: none;
        border-color: #38bdf8;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
        background: rgba(15, 23, 42, 0.8);
    }
    .cive-modal-footer {
        padding: 12px 20px 18px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-top: 1px solid rgba(148, 163, 184, 0.2);
    }
    .cive-btn {
        border: 1px solid transparent;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 100ms ease, box-shadow 120ms ease, background 120ms ease, color 120ms ease;
    }
    .cive-btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35);
    }
    .cive-btn-primary {
        background: linear-gradient(120deg, #0ea5e9, #6366f1);
        color: #f8fafc;
        box-shadow: 0 10px 30px rgba(14, 165, 233, 0.25);
    }
    .cive-btn-primary:hover {
        transform: translateY(-1px);
    }
    .cive-btn-ghost {
        background: rgba(148, 163, 184, 0.15);
        color: #e2e8f0;
        border-color: rgba(148, 163, 184, 0.3);
    }
    .cive-btn-ghost:hover {
        background: rgba(148, 163, 184, 0.25);
    }
    .cive-iframe-shell {
        position: relative;
        padding: 10px 10px 16px;
    }
    .cive-iframe-shell iframe {
        width: 100%;
        height: 600px;
        border: none;
        border-radius: 12px;
        overflow: auto;
        background: #0b1120;
    }
    @media (max-width: 640px) {
        .cive-modal-window { width: 100%; }
        .cive-iframe-shell iframe { height: 70vh; }
    }
    `;
        document.head.appendChild(style);
    }

    function crearPopupFallbackOD_OI() {
        return new Promise((resolve) => {
            ensureModalStyles();
            const overlay = document.createElement('div');
            overlay.className = 'cive-modal-backdrop';

            const box = document.createElement('div');
            box.className = 'cive-modal-window';
            box.innerHTML = `
      <div class="cive-modal-header">
        <div>
          <p class="cive-modal-kicker">Exámenes</p>
          <h3>Ingresar recomendaciones</h3>
        </div>
        <button id="xClose" class="cive-modal-close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="cive-modal-body">
        <div class="cive-form-group">
          <label for="odTxt">OD</label>
          <textarea id="odTxt" rows="3" class="cive-input"></textarea>
        </div>
        <div class="cive-form-group">
          <label for="oiTxt">OI</label>
          <textarea id="oiTxt" rows="3" class="cive-input"></textarea>
        </div>
      </div>
      <div class="cive-modal-footer">
        <button id="cancelBtn" class="cive-btn cive-btn-ghost" type="button">Cancelar</button>
        <button id="okBtn" class="cive-btn cive-btn-primary" type="button">Aceptar</button>
      </div>
    `;
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            const close = (data = null) => {
                document.body.removeChild(overlay);
                resolve(data);
            };
            box.querySelector('#xClose').addEventListener('click', () => close(null));
            box.querySelector('#cancelBtn').addEventListener('click', () => close(null));
            box.querySelector('#okBtn').addEventListener('click', () => {
                const OD = box.querySelector('#odTxt').value || '';
                const OI = box.querySelector('#oiTxt').value || '';
                close({OD, OI});
            });
        });
    }

// ==== Fin helpers ====

    const RESULTADO_CONTEXT_STORAGE_KEY = 'cive_resultado_modal_context';
    const RESULTADO_TRIGGER_STORAGE_KEY = 'cive_resultado_modal_trigger_context';

    function firstNonEmpty(values) {
        for (const value of values) {
            const clean = (value ?? '').toString().trim();
            if (clean !== '') return clean;
        }
        return '';
    }

    function pickBestHcNumber(candidates) {
        const cleaned = (Array.isArray(candidates) ? candidates : [])
            .map((value) => (value ?? '').toString().trim())
            .filter(Boolean);

        if (cleaned.length === 0) {
            return '';
        }

        // Prioriza HC numérico completo (normalmente 10+ dígitos).
        const longNumeric = cleaned.find((value) => /^\d{10,}$/.test(value));
        if (longNumeric) {
            return longNumeric;
        }

        // Fallback: usa el valor más largo disponible.
        return cleaned.sort((a, b) => b.length - a.length)[0];
    }

    function readStoredResultadoContext() {
        try {
            const raw = localStorage.getItem(RESULTADO_CONTEXT_STORAGE_KEY);
            if (!raw) return {};
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function readStoredTriggerContext() {
        try {
            const raw = localStorage.getItem(RESULTADO_TRIGGER_STORAGE_KEY);
            if (!raw) return {};
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function extractResultadoContextFromForm(form) {
        if (!form) return {};

        const fields = {};
        try {
            const fd = new FormData(form);
            fd.forEach((value, key) => {
                if (!(key in fields) && typeof value === 'string') {
                    fields[key] = value;
                }
            });
        } catch (e) {
            // ignore
        }

        const actionAttr = (form.getAttribute('action') || '').toString().trim();
        let actionUrl = '';
        let pksS = '';
        try {
            const url = new URL(actionAttr || '', window.location.origin);
            actionUrl = url.toString();
            pksS = (url.searchParams.get('pksS') || '').toString().trim();
        } catch (e) {
            actionUrl = actionAttr;
        }

        const formId = firstNonEmpty([
            fields['OrdenExamen[form_id]'],
            fields['OrdenExamen[docSolicitud_id]'],
            fields['OrdenExamen[docsolicitud_id]'],
            fields['OrdenExamen[id_docsolicitud]'],
            fields['form_id'],
            fields['idSolicitud'],
            fields['docsolicitud_id']
        ]);

        const hcNumber = firstNonEmpty([
            fields['OrdenExamen[hc_number]'],
            fields['OrdenExamen[hc]'],
            fields['HistoriaClinica[historiaClinica]'],
            fields['historiaClinica'],
            fields['hc_number'],
            fields['hc'],
            fields['identificacion']
        ]);

        return {
            formId,
            hcNumber,
            pksS,
            actionUrl,
            capturedAt: Date.now()
        };
    }

    function persistResultadoContext(ctx) {
        if (!ctx || typeof ctx !== 'object') return;
        const existing = readStoredResultadoContext();
        const merged = {
            ...existing,
            ...ctx,
            formId: firstNonEmpty([(ctx.formId || ''), (existing.formId || '')]),
            hcNumber: firstNonEmpty([(ctx.hcNumber || ''), (existing.hcNumber || '')]),
            pksS: firstNonEmpty([(ctx.pksS || ''), (existing.pksS || '')]),
        };
        const hasUseful = Boolean((merged.formId || '').toString().trim() || (merged.hcNumber || '').toString().trim() || (merged.pksS || '').toString().trim());
        if (!hasUseful) return;
        try {
            localStorage.setItem(RESULTADO_CONTEXT_STORAGE_KEY, JSON.stringify(merged));
        } catch (e) {
            // ignore
        }
        window.__civeResultadoModalContext = merged;
        console.debug('[CIVE] Contexto modal capturado', {
            formId: merged.formId || '',
            hcNumber: merged.hcNumber || '',
            pksS: merged.pksS || ''
        });
    }

    function persistResultadoTriggerContext(ctx) {
        if (!ctx || typeof ctx !== 'object') return;
        const hasUseful = Boolean(
            (ctx.formId || '').toString().trim()
            || (ctx.hcNumber || '').toString().trim()
            || (ctx.pksS || '').toString().trim()
            || (ctx.href || '').toString().trim()
        );
        if (!hasUseful) return;
        try {
            localStorage.setItem(RESULTADO_TRIGGER_STORAGE_KEY, JSON.stringify(ctx));
        } catch (e) {
            // ignore
        }
        window.__civeResultadoTriggerContext = ctx;
        console.debug('[CIVE] Contexto trigger capturado', {
            formId: ctx.formId || '',
            hcNumber: ctx.hcNumber || '',
            pksS: ctx.pksS || ''
        });
    }

    function getLiveResultadoFormContext() {
        const form = document.querySelector('#ajaxCrudModal #resultado-form') || document.querySelector('#resultado-form');
        return extractResultadoContextFromForm(form);
    }

    function extractFromHref(href) {
        if (!href) return {};
        const out = {};
        try {
            const url = new URL(href, window.location.origin);
            const getFirst = (...keys) => {
                for (const key of keys) {
                    const value = (url.searchParams.get(key) || '').toString().trim();
                    if (value !== '') return value;
                }
                return '';
            };
            out.formId = getFirst('form_id', 'idSolicitud', 'docSolicitud_id', 'docsolicitud_id', 'solicitud_id');
            out.hcNumber = getFirst('hc_number', 'hc', 'identificacion');
            out.pksS = getFirst('pksS', 'id', 'orden_id');
            out.href = url.toString();
        } catch (e) {
            out.href = href;
        }
        return out;
    }

    function extractFromGridRow(triggerEl) {
        const row = triggerEl && typeof triggerEl.closest === 'function'
            ? triggerEl.closest('tr[data-key], tr')
            : null;
        if (!row) return {};

        const getCellText = (seq) => {
            const cell = row.querySelector(`[data-col-seq="${seq}"]`);
            const value = (cell?.textContent || '').toString().replace(/\s+/g, ' ').trim();
            return value;
        };

        const parseHrefForFormId = (href) => {
            if (!href) return '';
            try {
                const url = new URL(href, window.location.origin);
                return firstNonEmpty([
                    url.searchParams.get('form_id'),
                    url.searchParams.get('idSolicitud'),
                    url.searchParams.get('docSolicitud_id'),
                    url.searchParams.get('docsolicitud_id'),
                    url.searchParams.get('id')
                ]);
            } catch (e) {
                return '';
            }
        };

        const hcFromCell = getCellText(7);
        const hcNumber = /^[0-9A-Za-z-]{6,}$/.test(hcFromCell) ? hcFromCell : '';

        let formId = '';
        const prefacturaLink = row.querySelector('a[href*="modificar-prefactura"]');
        formId = parseHrefForFormId(prefacturaLink?.getAttribute('href') || '');

        if (!formId) {
            const links = row.querySelectorAll('a[href]');
            for (const link of links) {
                formId = parseHrefForFormId(link.getAttribute('href') || '');
                if (formId) break;
            }
        }

        const rowHref = row.querySelector('a[href*="guardar-resultado-paciente"]')?.getAttribute('href') || '';
        const pksS = extractFromHref(rowHref).pksS || '';

        return {
            formId,
            hcNumber,
            pksS
        };
    }

    function installResultadoFormCaptureHooks() {
        if (window.__civeResultadoFormCaptureInstalled) return;
        window.__civeResultadoFormCaptureInstalled = true;

        document.addEventListener('submit', (event) => {
            const form = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('#resultado-form')
                : null;
            if (!form) return;
            persistResultadoContext(extractResultadoContextFromForm(form));
        }, true);

        document.addEventListener('click', (event) => {
            const triggerModalRemote = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[role="modal-remote"], a[href*="/historiaClinica/orden-examen/"]')
                : null;
            if (triggerModalRemote) {
                const href = (triggerModalRemote.getAttribute('href') || triggerModalRemote.dataset?.url || '').toString().trim();
                const fromHref = extractFromHref(href);
                const fromRow = extractFromGridRow(triggerModalRemote);
                persistResultadoTriggerContext({
                    ...fromHref,
                    ...fromRow,
                    formId: firstNonEmpty([fromRow.formId, fromHref.formId]),
                    hcNumber: firstNonEmpty([fromRow.hcNumber, fromHref.hcNumber]),
                    pksS: firstNonEmpty([fromHref.pksS, fromRow.pksS]),
                    capturedAt: Date.now()
                });
            }

            const trigger = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('#btn-guardar, .btn-success[onclick*="guardarTerminar"], .btn-warning[onclick*="guardar"]')
                : null;
            if (!trigger) return;
            const form = document.querySelector('#ajaxCrudModal #resultado-form') || document.querySelector('#resultado-form');
            persistResultadoContext(extractResultadoContextFromForm(form));
        }, true);
    }

    installResultadoFormCaptureHooks();

    function leerPacienteSeleccionado() {
        try {
            if (window.datosPacienteSeleccionado && typeof window.datosPacienteSeleccionado === 'object') {
                return window.datosPacienteSeleccionado;
            }
            const raw = localStorage.getItem('datosPacienteSeleccionado');
            if (!raw) return {};
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function resolverContextoInforme() {
        const params = new URLSearchParams(window.location.search || '');
        const paciente = leerPacienteSeleccionado();
        const liveModalCtx = getLiveResultadoFormContext();
        const storedModalCtx = readStoredResultadoContext();
        const triggerCtx = readStoredTriggerContext();
        const modalCtx = {
            // Prioriza contexto fresco del click en la fila (trigger) sobre contexto viejo persistido.
            formId: firstNonEmpty([liveModalCtx.formId, triggerCtx.formId, storedModalCtx.formId]),
            hcNumber: firstNonEmpty([liveModalCtx.hcNumber, triggerCtx.hcNumber, storedModalCtx.hcNumber]),
            pksS: firstNonEmpty([liveModalCtx.pksS, triggerCtx.pksS, storedModalCtx.pksS]),
        };
        persistResultadoContext({
            ...storedModalCtx,
            ...liveModalCtx,
            formId: modalCtx.formId,
            hcNumber: modalCtx.hcNumber,
            pksS: modalCtx.pksS
        });

        const fromDom = (selectors) => {
            for (const selector of selectors) {
                const node = document.querySelector(selector);
                const value = (node && 'value' in node ? node.value : node?.textContent) || '';
                const clean = value.toString().trim();
                if (clean !== '') return clean;
            }
            return '';
        };

        const fromRegex = (patterns, source) => {
            const text = (source || '').toString();
            for (const re of patterns) {
                const match = text.match(re);
                if (match && match[1]) {
                    const clean = match[1].toString().trim();
                    if (clean !== '') return clean;
                }
            }
            return '';
        };

        const pathname = (window.location && window.location.pathname ? window.location.pathname : '');

        const formId = (
            modalCtx.formId
            || params.get('idSolicitud')
            || params.get('form_id')
            || params.get('id')
            || paciente.form_id
            || paciente.idSolicitud
            || paciente.formId
            || paciente.id
            || document.querySelector('input[name="form_id"]')?.value
            || document.querySelector('#form_id')?.value
            || fromDom([
                'input[name="idSolicitud"]',
                '#idSolicitud',
                'input[name="solicitud_id"]',
                '#solicitud_id'
            ])
            || fromRegex(
                [
                    /(?:^|[?&])(?:idSolicitud|form_id|id)=([0-9]+)/i,
                    /\/(?:historia-automatica|consulta|solicitud)\/([0-9]+)/i
                ],
                `${window.location.href || ''} ${pathname}`
            )
            || ''
        ).toString().trim();

        const hcNumber = pickBestHcNumber([
            modalCtx.hcNumber,
            paciente.hc_number,
            paciente.hcNumber,
            paciente.hc,
            paciente.identificacion,
            paciente.identificacion_paciente,
            params.get('hc_number'),
            params.get('hc'),
            params.get('OrdenExamenPacienteSearch[identificacion]'),
            params.get('OrdenExamenHistoricoPacienteSearch[identificacion]'),
            document.querySelector('input[name="hc_number"]')?.value,
            document.querySelector('input[name="hc"]')?.value,
            document.querySelector('#hc_number')?.value,
            document.querySelector('#hc')?.value,
            fromDom([
                'input[name="identificacion"]',
                '#identificacion',
                '#pacienteIdentificacion'
            ]),
            fromRegex(
                [
                    /(?:^|[?&])(?:hc_number|hc|identificacion)=([0-9A-Za-z-]+)/i
                ],
                window.location.href || ''
            )
        ]);

        return {formId, hcNumber};
    }

    function resolveMedforgeOrigin() {
        const currentOrigin = (window.location && window.location.origin ? window.location.origin : '').replace(/\/$/, '');
        if (currentOrigin && currentOrigin.includes('cive.consulmed.me')) {
            return currentOrigin;
        }

        try {
            const controlEndpoint = window.configCIVE?.get?.()?.controlEndpoint || '';
            if (controlEndpoint) {
                const origin = new URL(controlEndpoint).origin.replace(/\/$/, '');
                if (origin.includes('cive.consulmed.me')) {
                    return origin;
                }
            }
        } catch (e) {
            // ignore
        }

        return 'https://cive.consulmed.me';
    }

function postToMedforgeViaBackground(endpoint, body) {
    return new Promise((resolve, reject) => {
        if (!isExtensionContextActive()) {
            reject(new Error('Extensión no disponible para proxy background'));
            return;
        }

        const extensionId = (window.configCIVE?.get?.('extensionId', '') || '').toString().trim();
        const headers = {};
        if (extensionId !== '') {
            headers['X-Cive-Extension-Id'] = extensionId;
        }

        try {
            chrome.runtime.sendMessage({
                action: 'apiRequest',
                url: endpoint,
                method: 'POST',
                headers,
                bodyType: 'json',
                body,
                expectJson: true
            }, (resp) => {
                    const err = chrome.runtime?.lastError;
                    if (err) {
                        reject(new Error(err.message || 'Error runtime en apiRequest'));
                        return;
                    }
                    if (!resp || resp.success === false) {
                        reject(new Error(resp?.error || 'Fallo proxy background'));
                        return;
                    }
                    const data = resp.data !== undefined ? resp.data : resp;
                    resolve(data);
                });
            } catch (error) {
                reject(error);
            }
        });
    }

    function mapTipoExamenInforme(item) {
        const byId = {
            octm: 'OCT MACULAR',
            octno: 'OCT NERVIO OPTICO',
            eco: 'ECOGRAFIA MODO B',
            angulo: 'OCT ANGULO',
            angio: 'ANGIOGRAFIA RETINAL',
            auto: 'AUTOFLOURESCENCIA',
            retino: 'RETINOGRAFIA',
            cv: 'CAMPO VISUAL',
            cornea: 'TOPOGRAFIA CORNEAL',
            biometria: 'BIOMETRIA OCULAR',
            microespecular: '281197 - MICROSCOPIA ESPECULAR (AO)'
        };
        return byId[item?.id] || item?.cirugia || item?.nombre || 'EXAMEN DE IMAGEN';
    }

    function mapPlantillaInforme(item) {
        const byId = {
            octm: 'octm',
            octno: 'octno',
            eco: 'eco',
            angulo: 'angulo',
            angio: 'angio',
            auto: 'auto',
            retino: 'retino',
            cv: 'cv',
            cornea: 'cornea',
            biometria: 'biometria',
            microespecular: 'microespecular'
        };
        return byId[item?.id] || '';
    }

    function construirPayloadInforme(item, popupResult) {
        const result = popupResult && typeof popupResult === 'object' ? popupResult : {};
        if (result.payload && typeof result.payload === 'object') {
            return result.payload;
        }

        return {
            inputOD: (result.OD || '').toString(),
            inputOI: (result.OI || '').toString()
        };
    }

    async function guardarInformeEnMedforge(item, popupResult) {
        const {formId, hcNumber} = resolverContextoInforme();
        if (!formId) {
            console.warn('[CIVE] Informe no guardado en MedForge: form_id no disponible.');
            return false;
        }

        const tipoExamen = mapTipoExamenInforme(item);
        const plantilla = mapPlantillaInforme(item);
        const payload = construirPayloadInforme(item, popupResult);
        const endpoint = `${resolveMedforgeOrigin()}/imagenes/informes/guardar`;

        const body = {
            form_id: formId,
            hc_number: hcNumber,
            tipo_examen: tipoExamen,
            plantilla,
            payload
        };
        window.__civeLastMedforgeInformeRequest = {
            endpoint,
            body,
            ts: Date.now()
        };
        console.info('[CIVE] Enviando informe a MedForge', {
            form_id: formId,
            hc_number: hcNumber,
            tipo_examen: tipoExamen,
            plantilla
        });

        try {
            // En Sigcenter (origen distinto) priorizamos background para evitar CORS del content script.
            if (isExtensionContextActive()) {
                const data = await postToMedforgeViaBackground(endpoint, body);
                if (!data || data.success !== true) {
                    throw new Error(data?.error || 'Respuesta inválida al guardar informe');
                }
                window.__civeLastMedforgeInformeResponse = {ok: true, data, ts: Date.now(), via: 'background'};
            } else if (window.CiveApiClient && typeof window.CiveApiClient.post === 'function') {
                const data = await window.CiveApiClient.post(endpoint, {
                    body,
                    bodyType: 'json',
                    expectJson: true,
                    retries: 1
                });
                if (!data || data.success !== true) {
                    throw new Error(data?.error || 'Respuesta inválida al guardar informe');
                }
                window.__civeLastMedforgeInformeResponse = {ok: true, data, ts: Date.now(), via: 'apiClient'};
            } else {
                const resp = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {'Content-Type': 'application/json;charset=UTF-8'},
                    body: JSON.stringify(body)
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data || data.success !== true) {
                    throw new Error(data?.error || `HTTP ${resp.status}`);
                }
                window.__civeLastMedforgeInformeResponse = {
                    ok: true,
                    data,
                    status: resp.status,
                    ts: Date.now(),
                    via: 'fetch'
                };
            }
            console.info('[CIVE] Informe sincronizado en MedForge', {form_id: formId, plantilla});
            return true;
        } catch (error) {
            console.error('[CIVE] No se pudo guardar informe en MedForge:', error);
            window.__civeLastMedforgeInformeResponse = {
                ok: false,
                error: (error && error.message) ? error.message : String(error),
                ts: Date.now()
            };
            return false;
        }
    }

    function ejecutarEnPagina(item) {
        console.log("Datos recibidos en ejecutarEnPagina:", item);

        function mostrarPopup(url) {
            return new Promise((resolve) => {
                // Si el contexto de la extensión no está activo (MV3 descargado/reload del paquete), usar fallback de formulario simple
                if (!isExtensionContextActive()) {
                    console.warn('Contexto de extensión inválido. Usando popup fallback (OD/OI) sin iframe.');
                    return crearPopupFallbackOD_OI().then(resolve);
                }

                ensureModalStyles();

                const popup = document.createElement('div');
                popup.className = 'cive-modal-backdrop';

                const popupURL = safeGetURL(url);
                if (!popupURL) {
                    console.warn('No se pudo obtener URL interna de la extensión. Usando popup fallback de texto.');
                    return crearPopupFallbackOD_OI().then(resolve);
                }

                popup.innerHTML = `
        <div class="cive-modal-window">
          <div class="cive-modal-header">
            <div>
              <p class="cive-modal-kicker">Exámenes</p>
              <h3>Completar información</h3>
            </div>
            <button id="btnClose" class="cive-modal-close" aria-label="Cerrar">&times;</button>
          </div>
          <div class="cive-iframe-shell">
            <iframe class="content-panel-frame placeholder-frame" id="placeholder-dialog" src="${popupURL}"></iframe>
          </div>
        </div>
      `;
                document.body.appendChild(popup);

                function cerrarPopup() {
                    document.body.removeChild(popup);
                    window.removeEventListener('message', onMessage);
                }

                function onMessage(event) {
                    // Validar el origen del mensaje solo si hay runtime; si no, ya habríamos usado el fallback
                    const expectedOrigin = chrome && chrome.runtime ? chrome.runtime.getURL('/').slice(0, -1) : null;
                    if (expectedOrigin && event.origin !== expectedOrigin) return;

                    if (event.data && (event.data.OD !== undefined && event.data.OI !== undefined)) {
                        cerrarPopup();
                        resolve(event.data);
                    } else if (event.data && event.data.close) {
                        cerrarPopup();
                        resolve(null);
                    }
                }

                window.addEventListener('message', onMessage);
                popup.querySelector('#btnClose').addEventListener('click', () => {
                    cerrarPopup();
                    resolve(null);
                });
            });
        }

        function ejecutarTecnicos(item) {
            if (!Array.isArray(item.tecnicos)) return Promise.resolve();

            return item.tecnicos.reduce((promise, tecnico) => {
                return promise.then(() => {
                    return hacerClickEnSelect2(tecnico.selector)
                        .then(() => establecerBusqueda(tecnico.selector, tecnico.funcion))
                        .then(() => seleccionarOpcion())
                        .then(() => hacerClickEnSelect2(tecnico.trabajador))
                        .then(() => establecerBusqueda(tecnico.trabajador, tecnico.nombre))
                        .then(() => seleccionarOpcion())
                        .catch(error => console.error(`Error procesando técnico ${tecnico.nombre}:`, error));
                });
            }, Promise.resolve());
        }

        function hacerClickEnSelect2(selector) {
            return new Promise((resolve, reject) => {
                const tecnicoContainer = document.querySelector(selector);
                if (tecnicoContainer) {
                    console.log(`Haciendo clic en el contenedor: ${selector}`);
                    const event = new MouseEvent('mousedown', {
                        view: window, bubbles: true, cancelable: true
                    });
                    tecnicoContainer.dispatchEvent(event);
                    setTimeout(resolve, 100); // Añadir un retraso para asegurar que el menú se despliegue
                } else {
                    reject(`El contenedor "${selector}" no se encontró.`);
                }
            });
        }

        function establecerBusqueda(selector, valor) {
            return new Promise((resolve, reject) => {
                let attempts = 0;
                const maxAttempts = 20;

                const searchForField = () => {
                    const searchField = document.querySelector('input.select2-search__field');
                    if (searchField) {
                        console.log('Estableciendo búsqueda:', valor);
                        searchField.value = valor;
                        const inputEvent = new Event('input', {
                            bubbles: true, cancelable: true
                        });
                        searchField.dispatchEvent(inputEvent);
                        setTimeout(() => resolve(searchField), 300); // Añadir un retraso para asegurar que los resultados se carguen
                    } else if (attempts < maxAttempts) {
                        console.log(`Esperando campo de búsqueda del Select2... intento ${attempts + 1}`);
                        attempts++;
                        hacerClickEnSelect2(selector)
                            .then(() => {
                                setTimeout(searchForField, 300); // Espera y reintenta
                            })
                            .catch(error => reject(error));
                    } else {
                        reject('El campo de búsqueda del Select2 no se encontró.');
                    }
                };

                searchForField();
            });
        }

        function seleccionarOpcion() {
            return new Promise((resolve, reject) => {
                const searchField = document.querySelector('input.select2-search__field');
                if (searchField) {
                    console.log('Seleccionando opción');
                    const enterEvent = new KeyboardEvent('keydown', {
                        key: 'Enter', keyCode: 13, bubbles: true, cancelable: true
                    });
                    searchField.dispatchEvent(enterEvent);
                    setTimeout(resolve, 200); // Añadir un retraso para asegurar que la opción se seleccione
                } else {
                    reject('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
                }
            });
        }

        function hacerClickEnBotonTerminar() {
            return new Promise((resolve, reject) => {
                const botonTerminar = document.querySelector('button.btn.btn-success[onclick="guardarTerminar()"]');
                if (botonTerminar) {
                    console.log('Haciendo clic en el botón "Terminar"');
                    botonTerminar.click();
                    resolve();
                } else {
                    reject('El botón "Terminar" no se encontró.');
                }
            });
        }

        function finalizarFlujoExamen(item, result) {
            return guardarInformeEnMedforge(item, result)
                .catch((error) => {
                    console.error('[CIVE] Error sincronizando informe:', error);
                    return false;
                })
                .then(() => ejecutarTecnicos(item))
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        }

        if (item.id === 'octno') {
            mostrarPopup('js/popup/popup.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = 'SE REALIZA TOMOGRAFIA CON PRUEBAS PROVOCATIVAS DE CAPA DE FIBRAS NERVIOSAS RETINALES CON TOMOGRAFO SPECTRALIS (HEIDELBERG ENGINEERING)'; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `\n${OD}\n`;
                }
                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `\n${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'eco') {
            mostrarPopup('js/eco/eco.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = 'SE REALIZA ESTUDIO CON EQUIPO EYE CUBED ELLEX DE ECOGRAFIA MODO B POR CONTACTO TRANSPALPEBRAL EN:\n    '; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `\nOD: ${OD}\n`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `\nOI: ${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'angulo') {
            mostrarPopup('js/angulo/angulo.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = 'SE REALIZA ESTUDIO DE TOMOGRAFIA CON PRUEBAS PROVOCATIVAS DE ANGULO IRIDOCORNEAL CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, VISUALIZANDO LA ESTRUCTURA ANGULAR.\n' + '\n' + 'APERTURA EN GRADOS DEL ANGULO IRIDOCORNEAL:'; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `\n${OD}\n`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `\n${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'octm') {
            mostrarPopup('js/octm/octm.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = 'SE REALIZA ESTUDIO DE TOMOGRAFIA CON PRUEBAS PROVOCATIVAS MACULAR CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, VISUALIZANDO LAS DIFERENTES CAPAS DE LA RETINA NEUROSENSORIAL, EPITELIO PIGMENTADO DE LA RETINA, MEMBRANA DE BRUCH Y COROIDES ANTERIOR DE ÁREA MACULAR. \n'; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `\n${OD}\n`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `\n${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'retino') {
            mostrarPopup('js/retino/retino.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = ''; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `EL ESTUDIO DE LAS FOTOGRAFIAS SE REALIZA CON EQUIPO OPTOS DAYTONA, OBTENIENDO IMAGENES SUGESTIVAS DE LOS SIGUIENTES PROBABLES DIAGNOSTICOS:

OD: ${OD}`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `

EL ESTUDIO DE LAS FOTOGRAFIAS SE REALIZA CON EQUIPO OPTOS DAYTONA, OBTENIENDO IMAGENES SUGESTIVAS DE LOS SIGUIENTES PROBABLES DIAGNOSTICOS:

OI: ${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'auto') {
            mostrarPopup('js/auto/auto.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = 'SE REALIZA ESTUDIO DE AUTOFLOURESCENCIA CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, VISUALIZANDO: \n'; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `\nOD: ${OD}\n`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `\nOI: ${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'angio') {
            mostrarPopup('js/angio/angio.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = 'SE REALIZA ESTUDIO DE ANGIOGRAFIA RETINAL CON FLUORESCEINA SÓDICA CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, PREVIO A INYECCION DE 5ML DE FLUORESCEINA SODICA AL 10% EN LA VENA DEL CODO VISUALIZANDO LAS DIFERENTES FASES DE LA CIRCULACION COROIDO RETINAL.  SE DOCUMENTA LAS FASES COROIDEA, ARTERIAL TEMPRANA, ARTERIOVENOSA, FASE VENOSA Y DE RECIRCULACION. \n'; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `\nOD: ${OD}\n`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `\nOI: ${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'cornea') {
            mostrarPopup('js/cornea/cornea.html').then((result) => {
                if (!result) return;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                if (!recomendaciones) return;

                const payload = (result.payload && typeof result.payload === 'object') ? result.payload : {};
                const lines = ['SE REALIZA ESTUDIO DE TOPOGRAFIA CORNEAL.'];

                function appendEye(prefix, label) {
                    const kFlat = (payload['kFlat' + prefix] || '').toString().trim();
                    const axisFlat = (payload['axisFlat' + prefix] || '').toString().trim();
                    const kSteep = (payload['kSteep' + prefix] || '').toString().trim();
                    const axisSteep = (payload['axisSteep' + prefix] || '').toString().trim();
                    const cilindro = (payload['cilindro' + prefix] || '').toString().trim();
                    const kPromedio = (payload['kPromedio' + prefix] || '').toString().trim();
                    if (!kFlat && !axisFlat && !kSteep && !axisSteep && !cilindro && !kPromedio) return;

                    lines.push('');
                    lines.push(label + ':');
                    if (kFlat) lines.push('K Flat: ' + kFlat);
                    if (axisFlat) lines.push('Axis: ' + axisFlat);
                    if (kSteep) lines.push('K Steep: ' + kSteep);
                    if (axisSteep) lines.push('Axis (steep): ' + axisSteep);
                    if (cilindro) lines.push('Cilindro: ' + cilindro);
                    if (kPromedio) lines.push('K Promedio: ' + kPromedio);
                }

                appendEye('OD', 'OD');
                appendEye('OI', 'OI');
                recomendaciones.value = lines.join('\n');

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'cv') {
            mostrarPopup('js/cv/cv.html').then((result) => {
                if (!result) return;
                const {OD, OI} = result;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                recomendaciones.value = ''; // Inicializa las recomendaciones

                // Recomendaciones para OD
                if (OD) {
                    recomendaciones.value += `${OD}\n\n`;
                }

                // Recomendaciones para OI
                if (OI) {
                    recomendaciones.value += `${OI}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'microespecular') {
            mostrarPopup('js/microespecular/microespecular.html').then((result) => {
                if (!result) return;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                if (!recomendaciones) return;

                const od = (result.OD || '').toString().trim();
                const oi = (result.OI || '').toString().trim();
                recomendaciones.value = 'SE REALIZA ESTUDIO DE MICROSCOPIA ESPECULAR DE AMBOS OJOS.\n';
                if (od) {
                    recomendaciones.value += `\n${od}\n`;
                }
                if (oi) {
                    recomendaciones.value += `\n${oi}`;
                }

                finalizarFlujoExamen(item, result);
            });
        } else if (item.id === 'biometria') {
            mostrarPopup('js/biometria/biometria.html').then((result) => {
                if (!result) return;
                const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
                if (!recomendaciones) return;

                const payload = (result.payload && typeof result.payload === 'object') ? result.payload : {};
                const lines = ['SE REALIZA ESTUDIO DE BIOMETRIA OCULAR.'];

                const od = [];
                if ((payload.camaraOD || '').trim() !== '') od.push('Camara anterior: ' + payload.camaraOD.trim());
                if ((payload.cristalinoOD || '').trim() !== '') od.push('Cristalino: ' + payload.cristalinoOD.trim());
                if ((payload.axialOD || '').trim() !== '') od.push('Longitud axial: ' + payload.axialOD.trim());
                if (od.length) {
                    lines.push('');
                    lines.push('OD:');
                    lines.push(...od);
                }

                const oi = [];
                if ((payload.camaraOI || '').trim() !== '') oi.push('Camara anterior: ' + payload.camaraOI.trim());
                if ((payload.cristalinoOI || '').trim() !== '') oi.push('Cristalino: ' + payload.cristalinoOI.trim());
                if ((payload.axialOI || '').trim() !== '') oi.push('Longitud axial: ' + payload.axialOI.trim());
                if (oi.length) {
                    lines.push('');
                    lines.push('OI:');
                    lines.push(...oi);
                }

                recomendaciones.value = lines.join('\n');
                finalizarFlujoExamen(item, result);
            });
        }
    }

// Exponer funciones principales para otros scripts/inyecciones
    window.ejecutarEnPagina = ejecutarEnPagina;
    if (typeof ejecutarExamenes === 'function') {
        window.ejecutarExamenes = ejecutarExamenes;
    }
    window.crearPopupFallbackOD_OI = crearPopupFallbackOD_OI;
}
