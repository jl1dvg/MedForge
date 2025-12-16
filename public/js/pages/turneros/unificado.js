const REFRESH_INTERVAL = 30000;
const ESTADOS_PARAM = encodeURIComponent('Recibido,Llamado,En atención,Atendido');
const AUDIO_PREFS_KEY = 'turnero_audio_prefs';
const PIN_STORAGE_KEY = 'turnero_turnos_pineados';
const CALL_SOUND_COOLDOWN = 7000;
const SPEAK_COOLDOWN = 12000;
const PRIORITY_SPEAK_DELAY = 650;

const container = document.getElementById('turneroGrid');
const panelConfigs = (typeof window !== 'undefined' && window.TURNERO_UNIFICADO_PANELES) || {};

const elements = {
    clock: document.getElementById('turneroClock'),
    refresh: document.getElementById('turneroRefresh'),
    lastUpdate: document.getElementById('turneroLastUpdate'),
    soundToggle: document.getElementById('soundToggle'),
    volume: document.getElementById('volumeControl'),
    quietToggle: document.getElementById('quietToggle'),
    quietStart: document.getElementById('quietStart'),
    quietEnd: document.getElementById('quietEnd'),
    ttsToggle: document.getElementById('ttsToggle'),
    ttsRepeat: document.getElementById('ttsRepeat'),
    voiceSelect: document.getElementById('voiceSelect'),
};

const stateOrder = ['en espera', 'llamado', 'en atencion', 'atendido'];
const estadoClases = new Map([
    ['en espera', 'recibido'],
    ['llamado', 'llamado'],
    ['en atencion', 'en-atencion'],
    ['atendido', 'atendido'],
]);

const defaultPrefs = {
    soundEnabled: true,
    volume: 0.7,
    quiet: {enabled: false, start: '22:00', end: '06:00'},
    ttsEnabled: true,
    voice: '',
    ttsRepeat: false,
};

let preferences = {...defaultPrefs};
let lastCallSoundAt = 0;
const previousStates = {};
const spokenRegistry = new Map();
const latestData = {};
let audioUnlocked = false;

const pinnedTurnos = (() => {
    try {
        const raw = localStorage.getItem(PIN_STORAGE_KEY);
        if (!raw) return new Set();
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return new Set();
        return new Set(parsed.map(String));
    } catch (err) {
        console.warn('No se pudo leer pines locales', err);
        return new Set();
    }
})();

const normalizeText = value => {
    if (typeof value !== 'string') return '';
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[_-]+/g, ' ')
        .toLowerCase()
        .trim();
};

const normalizeEstado = raw => {
    const normalized = normalizeText(raw);
    if (normalized === 'recibido' || normalized === '') return 'en espera';
    return normalized;
};

const formatTurno = turno => {
    const numero = Number.parseInt(turno, 10);
    if (Number.isNaN(numero) || numero <= 0) return '--';
    return String(numero).padStart(2, '0');
};

const getItemId = item => {
    if (item && (item.id || item.id === 0)) return String(item.id);
    if (item && (item.turno || item.turno === 0)) return `turno-${item.turno}`;
    const name = normalizeText(item?.full_name || '');
    return name || `tmp-${Math.random().toString(36).slice(2, 8)}`;
};

const isPriorityItem = item => {
    const normalized = normalizeText(item?.prioridad || '');
    if (['si', 'alta', 'urgente', 'prioridad', 'critico', 'critica', 'crítico', 'crítica'].includes(normalized)) {
        return true;
    }
    if (item?.urgente === true) return true;
    return false;
};

const isPinned = item => pinnedTurnos.has(getItemId(item));

const savePinned = () => {
    try {
        localStorage.setItem(PIN_STORAGE_KEY, JSON.stringify([...pinnedTurnos]));
    } catch (err) {
        console.warn('No se pudo guardar pines', err);
    }
};

const priorityScore = item => {
    if (isPinned(item)) return -1;
    if (isPriorityItem(item)) return 0;
    const normalized = normalizeText(item?.prioridad || '');
    if (normalized) return 1;
    return 2;
};

const estadoScore = estado => {
    const normalized = normalizeEstado(estado);
    const index = stateOrder.indexOf(normalized);
    return index >= 0 ? index : stateOrder.length + 1;
};

const parseTurnoNumero = turno => {
    const numero = Number.parseInt(turno, 10);
    return Number.isNaN(numero) ? Number.POSITIVE_INFINITY : numero;
};

const loadPreferences = () => {
    try {
        const raw = localStorage.getItem(AUDIO_PREFS_KEY);
        if (!raw) {
            preferences = {...defaultPrefs};
            return;
        }
        const stored = JSON.parse(raw);
        preferences = {
            ...defaultPrefs,
            ...stored,
            quiet: {...defaultPrefs.quiet, ...(stored?.quiet || {})},
        };
    } catch (err) {
        console.warn('No se pudieron cargar preferencias de audio', err);
        preferences = {...defaultPrefs};
    }
};

const persistPreferences = () => {
    try {
        localStorage.setItem(AUDIO_PREFS_KEY, JSON.stringify(preferences));
    } catch (err) {
        console.warn('No se pudieron guardar preferencias', err);
    }
};

loadPreferences();

const audioEngine = {
    ctx: null,
};

const ensureAudioContext = () => {
    if (audioEngine.ctx) return audioEngine.ctx;
    const Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;
    audioEngine.ctx = new Ctor();
    if (audioEngine.ctx?.state === 'running') {
        audioUnlocked = true;
    }
    return audioEngine.ctx;
};

const unlockAudio = () => {
    const ctx = ensureAudioContext();
    if (!ctx) return;
    if (ctx.state === 'running') {
        audioUnlocked = true;
        return;
    }
    ctx.resume().then(() => {
        audioUnlocked = true;
    }).catch(() => {
        audioUnlocked = false;
    });
};

const isQuietHoursActive = () => {
    if (!preferences?.quiet?.enabled) return false;
    const start = preferences.quiet.start || '22:00';
    const end = preferences.quiet.end || '06:00';
    const now = new Date();
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const startMinutes = sh * 60 + (sm || 0);
    const endMinutes = eh * 60 + (em || 0);
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    if (startMinutes <= endMinutes) {
        return nowMinutes >= startMinutes && nowMinutes <= endMinutes;
    }
    // Tramo que pasa medianoche.
    return nowMinutes >= startMinutes || nowMinutes <= endMinutes;
};

// Nota: el navegador bloquea audio hasta interacción del usuario.
// Aquí validamos solo preferencias; el desbloqueo real lo maneja playTone con AudioContext.resume().
const canPlayAudio = () => preferences.soundEnabled && !isQuietHoursActive() && (preferences.volume ?? 0) > 0;

const playTone = (frequency = 880, duration = 180, type = 'sine', volumeScale = 1) => {
    if (!canPlayAudio()) return;

    const ctx = ensureAudioContext();
    if (!ctx) return;

    const doPlay = () => {
        // Si el contexto ya está corriendo, marcamos como desbloqueado.
        if (ctx.state === 'running') audioUnlocked = true;

        const gainNode = ctx.createGain();
        gainNode.gain.value = Math.max(0, Math.min(1, preferences.volume ?? 0.7)) * volumeScale;

        const osc = ctx.createOscillator();
        osc.type = type;
        osc.frequency.value = frequency;

        osc.connect(gainNode);
        gainNode.connect(ctx.destination);

        const now = ctx.currentTime;
        osc.start(now);
        osc.stop(now + duration / 1000);
    };

    // Muchos navegadores requieren interacción del usuario antes de permitir audio.
    // Si está suspendido, intentamos resumir y luego reproducimos.
    if (ctx.state === 'suspended') {
        ctx.resume()
            .then(() => {
                audioUnlocked = true;
                doPlay();
            })
            .catch(() => {
                audioUnlocked = false;
            });
        return;
    }

    doPlay();
};

const playNewTurnTone = () => {
    playTone(1040, 140, 'triangle', 1);
    setTimeout(() => playTone(820, 140, 'sine', 0.85), 120);
};

const playPriorityTone = () => {
    playTone(980, 200, 'triangle', 1);
    setTimeout(() => playTone(1180, 180, 'triangle', 0.9), 120);
};

const playCallTone = () => {
    const now = Date.now();
    if (now - lastCallSoundAt < CALL_SOUND_COOLDOWN) return;
    lastCallSoundAt = now;
    playTone(520, 220, 'square', 1.1);
    setTimeout(() => playTone(780, 260, 'sawtooth', 1), 160);
    setTimeout(() => playTone(660, 200, 'square', 0.9), 360);
};

const highlightCard = card => {
    if (!card) return;
    card.classList.add('turno-flash');
    setTimeout(() => card.classList.remove('turno-flash'), 1600);
};

const voices = [];

const populateVoices = () => {
    if (!('speechSynthesis' in window)) return;
    const available = window.speechSynthesis.getVoices();
    if (!available || !available.length) return;
    voices.splice(0, voices.length, ...available.filter(v => v.lang && v.lang.toLowerCase().startsWith('es')));

    if (!elements.voiceSelect) return;
    const current = preferences.voice || elements.voiceSelect.value;
    elements.voiceSelect.innerHTML = '<option value=\"\">Automático (ES)</option>';
    voices.forEach(voice => {
        const option = document.createElement('option');
        option.value = voice.name;
        option.textContent = `${voice.name} · ${voice.lang}`;
        if (current && current === voice.name) option.selected = true;
        elements.voiceSelect.appendChild(option);
    });
};

const getVoice = () => {
    if (!voices.length) return null;
    if (preferences.voice) {
        const voice = voices.find(v => v.name === preferences.voice);
        if (voice) return voice;
    }
    return voices.find(v => v.lang.toLowerCase().startsWith('es')) || null;
};

const speakNameForItem = (item, reason) => {
    if (!preferences.ttsEnabled || isQuietHoursActive()) return;
    if (!('speechSynthesis' in window)) return;

    const id = getItemId(item);
    const now = Date.now();
    const lastSpoken = spokenRegistry.get(id) || 0;
    if (now - lastSpoken < SPEAK_COOLDOWN) return;
    spokenRegistry.set(id, now);
    const firstSpokenAt = now;

    const createUtterance = () => {
        const utterance = new SpeechSynthesisUtterance(item?.full_name || 'Paciente');
        utterance.lang = 'es-ES';
        const voice = getVoice();
        if (voice) {
            utterance.voice = voice;
            utterance.lang = voice.lang;
        }
        utterance.volume = Math.max(0, Math.min(1, preferences.volume ?? 0.8));
        utterance.rate = reason === 'priority' ? 0.95 : 1;
        utterance.pitch = 1;
        return utterance;
    };

    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(createUtterance());

    if (preferences.ttsRepeat) {
        setTimeout(() => {
            const last = spokenRegistry.get(id) || 0;
            // Evitar bucles si ya hubo otra llamada posterior.
            if (last > firstSpokenAt && (Date.now() - last) < SPEAK_COOLDOWN / 2) return;
            spokenRegistry.set(id, Date.now());
            window.speechSynthesis.speak(createUtterance());
        }, 2200);
    }
};

const buildDetalle = item => {
    const partes = [];
    if (item?.fecha) partes.push(item.fecha);
    if (item?.hora) partes.push(item.hora);
    if (partes.length === 0) return '';
    if (partes.length === 1) return partes[0];
    return `${partes[0]} • ${partes[1]}`;
};

const buildExtras = item => {
    const extras = [];
    if (item?.hc_number) extras.push({label: 'HC', value: item.hc_number});
    if (item?.kanban_estado) extras.push({label: 'Estado CRM', value: item.kanban_estado});
    if (item?.doctor) extras.push({label: 'Médico', value: item.doctor});
    if (item?.sala) extras.push({label: 'Sala', value: item.sala});
    const detalle = buildDetalle(item);
    if (detalle) extras.push({label: 'Registro', value: detalle});
    return extras;
};

const buildCard = (item, columnKey, eventById) => {
    const card = document.createElement('article');
    card.className = 'turno-card';
    card.setAttribute('role', 'listitem');
    card.dataset.id = getItemId(item);
    card.dataset.column = columnKey;

    const pinned = isPinned(item);

    const pinBtn = document.createElement('button');
    pinBtn.type = 'button';
    pinBtn.className = 'turno-pin';
    pinBtn.dataset.pinned = String(pinned);
    pinBtn.textContent = pinned ? 'Prioritario' : 'Pin';
    pinBtn.addEventListener('click', () => {
        const id = getItemId(item);
        const wasPinned = pinnedTurnos.has(id);
        if (wasPinned) {
            pinnedTurnos.delete(id);
        } else {
            pinnedTurnos.add(id);
            playPriorityTone();
            setTimeout(() => speakNameForItem(item, 'priority'), PRIORITY_SPEAK_DELAY);
        }
        savePinned();
        renderColumn(columnKey, latestData[columnKey] || []);
    });
    card.appendChild(pinBtn);

    const numero = document.createElement('div');
    numero.className = 'turno-numero';
    numero.textContent = `#${formatTurno(item.turno)}`;
    card.appendChild(numero);

    const detalles = document.createElement('div');
    detalles.className = 'turno-detalles';
    card.appendChild(detalles);

    const nombre = document.createElement('div');
    nombre.className = 'turno-nombre';
    nombre.textContent = item?.full_name ? String(item.full_name) : 'Paciente sin nombre';
    detalles.appendChild(nombre);

    const descripcionTexto = item?.examen_nombre || item?.procedimiento || '';
    if (descripcionTexto) {
        const descripcion = document.createElement('div');
        descripcion.className = 'turno-descripcion';
        descripcion.textContent = descripcionTexto;
        detalles.appendChild(descripcion);
    }

    const meta = document.createElement('div');
    meta.className = 'turno-meta mt-1';
    detalles.appendChild(meta);

    const prioridadBadge = item?.prioridad ? String(item.prioridad) : '';
    if (prioridadBadge || pinned) {
        const badge = document.createElement('span');
        badge.className = 'turno-badge';
        badge.title = pinned ? 'Tarjeta fijada como prioritaria' : 'Prioridad';
        badge.textContent = pinned ? 'PRIORITARIO' : prioridadBadge.toUpperCase();
        meta.appendChild(badge);
    }

    const estado = normalizeEstado(item?.estado);
    if (estado) {
        const estadoEl = document.createElement('span');
        const estadoClass = estadoClases.get(estado) ?? '';
        estadoEl.className = `turno-estado${estadoClass ? ` ${estadoClass}` : ''}`;
        estadoEl.textContent = estado.replace('en ', 'En ');
        meta.appendChild(estadoEl);
        card.dataset.estado = estado;
        if (estado === 'llamado') {
            card.classList.add('is-llamado');
            card.setAttribute('aria-live', 'assertive');
        }
    }

    const core = document.createElement('div');
    core.className = 'turno-core';
    meta.appendChild(core);

    const hora = item?.hora || '';
    if (hora) {
        const horaEl = document.createElement('span');
        horaEl.className = 'turno-hora';
        horaEl.textContent = hora;
        core.appendChild(horaEl);
    }

    const extras = buildExtras(item);
    if (extras.length) {
        const detailBox = document.createElement('details');
        detailBox.className = 'turno-detalles-extendidos';
        const summary = document.createElement('summary');
        summary.textContent = 'Más detalles';
        detailBox.appendChild(summary);
        const extraList = document.createElement('div');
        extraList.className = 'turno-extra-list';
        extras.forEach(extra => {
            const row = document.createElement('div');
            row.innerHTML = `<span>${extra.label}:</span> ${extra.value}`;
            extraList.appendChild(row);
        });
        detailBox.appendChild(extraList);
        detalles.appendChild(detailBox);
    }

    if (pinned || isPriorityItem(item)) {
        card.classList.add('is-priority');
        if (pinned) {
            card.classList.add('is-pinned');
        }
    }

    const event = eventById?.get(card.dataset.id);
    if (event) {
        card.dataset.event = event.type;
        highlightCard(card);
    }

    return card;
};

const columns = {};

const initColumns = () => {
    if (!container || typeof panelConfigs !== 'object') return;

    Object.entries(panelConfigs).forEach(([key, config]) => {
        columns[key] = {
            key,
            config,
            listado: document.getElementById(`listado-${key}`),
            empty: document.getElementById(`empty-${key}`),
            counters: container.querySelectorAll(`[data-counter^=\"${key}-\"]`),
            filterButtons: container.querySelectorAll(`[data-key=\"${key}\"] .chip-filter`),
            filterState: 'all',
        };
    });
};

const setEmptyVisibility = (columnKey, visible) => {
    const column = columns[columnKey];
    if (!column?.empty) return;
    column.empty.setAttribute('aria-hidden', visible ? 'false' : 'true');
};

const clearListado = columnKey => {
    const column = columns[columnKey];
    if (!column?.listado) return;
    if (typeof column.listado.replaceChildren === 'function') {
        column.listado.replaceChildren();
    } else {
        column.listado.innerHTML = '';
    }
};

const updateCounters = (columnKey, items) => {
    const column = columns[columnKey];
    if (!column) return;
    const totals = {
        'en espera': 0,
        llamado: 0,
        'en atencion': 0,
        atendido: 0,
    };

    items.forEach(item => {
        const estado = normalizeEstado(item.estado);
        const key = Object.prototype.hasOwnProperty.call(totals, estado) ? estado : 'en espera';
        totals[key] += 1;
    });

    column.counters.forEach(counter => {
        const state = counter.dataset.counter?.split('-')[1];
        counter.textContent = totals[state] ?? 0;
    });
};

const shouldRenderItem = (columnKey, item) => {
    const column = columns[columnKey];
    if (!column) return false;
    if (column.filterState === 'all') return true;
    const estado = normalizeEstado(item.estado);
    return estado === column.filterState;
};

const sortItems = items => {
    return [...items].sort((a, b) => {
        const prioridadDiff = priorityScore(a) - priorityScore(b);
        if (prioridadDiff !== 0) return prioridadDiff;

        const estadoDiff = estadoScore(a.estado) - estadoScore(b.estado);
        if (estadoDiff !== 0) return estadoDiff;

        const turnoDiff = parseTurnoNumero(a.turno) - parseTurnoNumero(b.turno);
        if (!Number.isNaN(turnoDiff) && turnoDiff !== 0) return turnoDiff;

        const fechaA = a.created_at ? new Date(a.created_at).getTime() : 0;
        const fechaB = b.created_at ? new Date(b.created_at).getTime() : 0;
        return fechaA - fechaB;
    });
};

const detectEvents = (columnKey, items) => {
    const prev = previousStates[columnKey] || new Map();
    const next = new Map();
    const events = [];

    items.forEach(item => {
        const id = getItemId(item);
        const estado = normalizeEstado(item.estado);
        const priorityFlag = isPriorityItem(item) || isPinned(item);
        const prevEntry = prev.get(id);

        if (!prevEntry) {
            events.push({ type: 'new', id, item });
            if (priorityFlag) {
                events.push({ type: 'priority', id, item });
            }
        } else {
            if (prevEntry.estado !== estado && estado === 'llamado') {
                events.push({ type: 'call', id, item });
            }
            if (!prevEntry.priority && priorityFlag) {
                events.push({ type: 'priority', id, item });
            }
        }

        next.set(id, { estado, priority: priorityFlag });
    });

    previousStates[columnKey] = next;
    return events;
};

const triggerEvents = events => {
    events.forEach(event => {
        if (event.type === 'new') {
            playNewTurnTone();
        } else if (event.type === 'call') {
            playCallTone();
            speakNameForItem(event.item, 'call');
        } else if (event.type === 'priority') {
            playPriorityTone();
            setTimeout(() => speakNameForItem(event.item, 'priority'), PRIORITY_SPEAK_DELAY);
        }
    });
};

const renderColumn = (columnKey, items) => {
    const column = columns[columnKey];
    if (!column?.listado || !column.empty) return;

    const safeItems = Array.isArray(items) ? items : [];

    latestData[columnKey] = safeItems;
    const events = detectEvents(columnKey, safeItems);
    const eventById = new Map(events.map(event => [event.id, event]));
    const filtered = sortItems(safeItems).filter(item => shouldRenderItem(columnKey, item));
    clearListado(columnKey);

    if (filtered.length === 0) {
        setEmptyVisibility(columnKey, true);
        updateCounters(columnKey, safeItems);
        return;
    }

    setEmptyVisibility(columnKey, false);
    const fragment = document.createDocumentFragment();
    filtered.forEach(item => fragment.appendChild(buildCard(item, columnKey, eventById)));
    column.listado.appendChild(fragment);
    updateCounters(columnKey, safeItems);
    triggerEvents(events);
};

const renderClock = () => {
    if (!elements.clock) return;
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    elements.clock.textContent = formatter.format(now);
};

const setLastUpdate = () => {
    if (!elements.lastUpdate) return;
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    elements.lastUpdate.textContent = `Última actualización: ${formatter.format(now)}`;
};

const fetchColumn = async columnKey => {
    const column = columns[columnKey];
    const endpoint = column?.config?.endpoint;
    if (!endpoint) return [];

    const response = await fetch(`${endpoint}?estado=${ESTADOS_PARAM}`, {
        credentials: 'same-origin',
    });

    if (!response.ok) return [];
    const payload = await response.json();
    if (!payload || !Array.isArray(payload.data)) return [];
    return payload.data;
};

const refresh = async () => {
    if (!container) return;
    const keys = Object.keys(columns);
    const results = await Promise.all(keys.map(key => fetchColumn(key)));
    keys.forEach((key, index) => renderColumn(key, results[index] || []));
    setLastUpdate();
};

const bindFilters = () => {
    Object.values(columns).forEach(column => {
        column.filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                column.filterButtons.forEach(other => other.setAttribute('aria-pressed', 'false'));
                btn.setAttribute('aria-pressed', 'true');
                column.filterState = btn.dataset.filterState || 'all';
                renderColumn(column.key, latestData[column.key] || []);
            });
        });
    });
};

const bindPreferences = () => {
    if (elements.soundToggle) {
        elements.soundToggle.checked = preferences.soundEnabled;
        elements.soundToggle.addEventListener('change', () => {
            preferences.soundEnabled = Boolean(elements.soundToggle.checked);
            persistPreferences();
        });
    }
    if (elements.volume) {
        elements.volume.value = preferences.volume;
        elements.volume.addEventListener('input', () => {
            preferences.volume = Number.parseFloat(elements.volume.value) || 0;
            persistPreferences();
        });
    }
    if (elements.quietToggle) {
        elements.quietToggle.checked = Boolean(preferences.quiet?.enabled);
        elements.quietToggle.addEventListener('change', () => {
            preferences.quiet.enabled = Boolean(elements.quietToggle.checked);
            persistPreferences();
        });
    }
    if (elements.quietStart) {
        elements.quietStart.value = preferences.quiet.start;
        elements.quietStart.addEventListener('change', () => {
            preferences.quiet.start = elements.quietStart.value || '22:00';
            persistPreferences();
        });
    }
    if (elements.quietEnd) {
        elements.quietEnd.value = preferences.quiet.end;
        elements.quietEnd.addEventListener('change', () => {
            preferences.quiet.end = elements.quietEnd.value || '06:00';
            persistPreferences();
        });
    }
    if (elements.ttsToggle) {
        elements.ttsToggle.checked = preferences.ttsEnabled;
        elements.ttsToggle.addEventListener('change', () => {
            preferences.ttsEnabled = Boolean(elements.ttsToggle.checked);
            persistPreferences();
        });
    }
    if (elements.ttsRepeat) {
        elements.ttsRepeat.checked = preferences.ttsRepeat;
        elements.ttsRepeat.addEventListener('change', () => {
            preferences.ttsRepeat = Boolean(elements.ttsRepeat.checked);
            persistPreferences();
        });
    }
    if (elements.voiceSelect) {
        elements.voiceSelect.addEventListener('change', () => {
            preferences.voice = elements.voiceSelect.value || '';
            persistPreferences();
        });
    }
};

const start = () => {
    initColumns();
    if (!Object.keys(columns).length) return;

    bindFilters();
    bindPreferences();
    ['pointerdown', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, unlockAudio, {once: true, passive: true});
    });
    elements.refresh?.addEventListener('click', unlockAudio);
    elements.soundToggle?.addEventListener('click', unlockAudio);
    // Primer click/tap en cualquier parte desbloquea audio; además, al presionar "Actualizar" intentamos sonar.
    elements.refresh?.addEventListener('click', () => {
        playNewTurnTone();
    });
    populateVoices();
    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = populateVoices;
    }
    renderClock();
    setInterval(renderClock, 1000);

    elements.refresh?.addEventListener('click', refresh);

    refresh();
    setInterval(refresh, REFRESH_INTERVAL);
};

document.addEventListener('DOMContentLoaded', start);
