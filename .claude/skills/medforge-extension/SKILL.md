---
name: medforge-extension
description: >
  Usar cuando se trabaja en la extensión Chrome de MedForge (Manifest V3) para la clínica CIVE.
  Cubre scraping/lectura de SigCenter, mensajería background↔content↔popup, autofill de
  formularios en SigCenter, envío de datos a la API de MedForge, y debugging de la extensión.
  Aplicar también cuando hay problemas de permisos CSP, storage, o comunicación entre scripts.
---

# MedForge Chrome Extension

## Visión general

La extensión es el **robot intermediario** entre SigCenter (sistema legado de CIVE) y MedForge (plataforma propia). Opera en dos direcciones:

- **→ Lectura**: Extrae datos de SigCenter (pacientes, citas, protocolos, exámenes) y los envía a MedForge.
- **← Escritura**: Recibe instrucciones de MedForge y autofill formularios en SigCenter.

Stack: **Chrome Extension Manifest V3** + comunicación con backend **Laravel** via REST API.

---

## Arquitectura de la extensión

```
medforge-extension/
├── manifest.json              # MV3: permisos, scripts, CSP
├── background/
│   └── service-worker.js      # Lógica central, llamadas API a MedForge
├── content/
│   ├── sigcenter-reader.js    # Extrae datos de páginas SigCenter
│   └── sigcenter-filler.js    # Autofill de formularios en SigCenter
├── popup/
│   ├── popup.html
│   └── popup.js               # UI del ícono de extensión
└── lib/
    └── medforge-api.js        # Cliente REST hacia Laravel (MedForge)
```

---

## Patrones clave MV3

### 1. Service Worker (background)

En MV3 el background es un **service worker sin DOM** — muere cuando no hay actividad.

```js
// background/service-worker.js
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'PATIENT_DATA') {
    sendToMedForge(message.payload)
      .then(result => sendResponse({ ok: true, data: result }))
      .catch(err => sendResponse({ ok: false, error: err.message }));
    return true; // ← CRÍTICO: mantiene canal abierto para respuesta async
  }
});

async function sendToMedForge(payload) {
  const { token } = await chrome.storage.local.get('token');
  const response = await fetch('https://medforge.cive.ec/api/extension/ingest', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(payload)
  });
  if (!response.ok) throw new Error(`API error: ${response.status}`);
  return response.json();
}
```

### 2. Content Script — Leer SigCenter

```js
// content/sigcenter-reader.js
function extractPatientData() {
  return {
    cedula:    document.querySelector('#lblCedula')?.textContent?.trim(),
    nombre:    document.querySelector('#lblNombre')?.textContent?.trim(),
    historia:  document.querySelector('#lblHistoria')?.textContent?.trim(),
    fecha:     document.querySelector('#lblFechaAtencion')?.textContent?.trim(),
    // Añadir selectores según página activa de SigCenter
  };
}

// Enviar al service worker
chrome.runtime.sendMessage(
  { type: 'PATIENT_DATA', payload: extractPatientData() },
  (response) => {
    if (response?.ok) console.log('[MedForge] Datos enviados:', response.data);
    else console.error('[MedForge] Error:', response?.error);
  }
);
```

### 3. Content Script — Autofill SigCenter

```js
// content/sigcenter-filler.js
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === 'FILL_PROTOCOL') {
    fillProtocol(message.data);
    sendResponse({ ok: true });
  }
});

function fillProtocol(data) {
  setField('#txtDiagnostico', data.diagnostico);
  setField('#txtTratamiento', data.tratamiento);
  // Trigger events para que SigCenter reconozca el cambio
}

function setField(selector, value) {
  const el = document.querySelector(selector);
  if (!el) return;
  const nativeInputSetter = Object.getOwnPropertyDescriptor(
    window.HTMLInputElement.prototype, 'value'
  ).set;
  nativeInputSetter.call(el, value);
  el.dispatchEvent(new Event('input', { bubbles: true }));
  el.dispatchEvent(new Event('change', { bubbles: true }));
}
```

---

## manifest.json (base MV3)

```json
{
  "manifest_version": 3,
  "name": "MedForge CIVE",
  "version": "1.0.0",
  "description": "Integración MedForge ↔ SigCenter para CIVE",
  "permissions": ["storage", "activeTab", "scripting"],
  "host_permissions": [
    "https://sigcenter.cive.ec/*",
    "https://medforge.cive.ec/*"
  ],
  "background": {
    "service_worker": "background/service-worker.js"
  },
  "content_scripts": [
    {
      "matches": ["https://sigcenter.cive.ec/*"],
      "js": ["content/sigcenter-reader.js", "content/sigcenter-filler.js"],
      "run_at": "document_idle"
    }
  ],
  "action": {
    "default_popup": "popup/popup.html",
    "default_icon": "icons/icon48.png"
  },
  "content_security_policy": {
    "extension_pages": "script-src 'self'; object-src 'self'"
  }
}
```

---

## Autenticación con MedForge (Laravel)

El token Sanctum/JWT se guarda en `chrome.storage.local` (no localStorage — no accesible desde service worker).

```js
// Guardar token al login
await chrome.storage.local.set({ token: 'sanctum_token_aqui' });

// Leer token
const { token } = await chrome.storage.local.get('token');
```

En Laravel, el endpoint de la extensión debe tener middleware `auth:sanctum` y un guard específico si es necesario separar acceso de extensión vs. web.

---

## Flujos principales

### Flujo A — Lectura automática al abrir ficha de paciente
```
SigCenter carga página → content script detecta URL/DOM →
extrae datos → mensaje al SW → SW llama API MedForge →
MedForge guarda/procesa datos
```

### Flujo B — Autofill desde MedForge
```
Usuario en MedForge genera protocolo → MedForge llama API →
SW recibe (polling o WebSocket) → SW envía mensaje al content script →
content script rellena campos en SigCenter
```

### Flujo C — Autofill iniciado desde popup
```
Usuario hace clic en ícono extensión → popup.js solicita datos a MedForge →
popup.js envía mensaje al content script activo → autofill
```

---

## Errores frecuentes y soluciones

| Error | Causa | Solución |
|-------|-------|---------|
| `Cannot use XMLHttpRequest in service worker` | MV3 no permite XHR en SW | Usar `fetch()` siempre |
| `Extension context invalidated` | SW fue terminado | Envolver en try/catch, reintentar |
| `Could not establish connection` | Content script no cargado | Verificar `matches` en manifest y que la página esté lista |
| Campo no se actualiza en SigCenter | Framework JS no detecta cambio | Usar `nativeInputSetter` + dispatchar eventos `input`/`change` |
| `CSP blocked` | Política de contenido | Revisar `content_security_policy` en manifest |
| Token expirado | Sanctum/JWT vencido | Manejar 401 en SW y emitir mensaje al popup para re-login |

---

## Debugging

```bash
# Cargar extensión en modo desarrollo
chrome://extensions/ → "Cargar descomprimida" → seleccionar carpeta

# Ver logs del Service Worker
chrome://extensions/ → "Detalles" → "Inspeccionar vistas: service worker"

# Ver logs del content script
F12 en página SigCenter → Consola (filtrar por "MedForge")
```

---

## Consideraciones de seguridad / HIPAA-adjacent

- **Nunca** loguear datos de pacientes en `console.log` en producción
- Token almacenado solo en `chrome.storage.local` (no sync, no sessionStorage)
- Toda comunicación via HTTPS
- Los `host_permissions` deben ser los dominios exactos, no wildcards amplios
- Revisar si SigCenter tiene mecanismos anti-scraping antes de automatizar

---

## Referencias adicionales

- `references/sigcenter-selectors.md` — Mapa de selectores DOM de SigCenter por módulo
- `references/medforge-api-endpoints.md` — Endpoints Laravel disponibles para la extensión
- `references/autofill-modules.md` — Módulos de autofill implementados (protocolos, exámenes, citas)
