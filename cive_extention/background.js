const STORAGE_KEYS = {
  bootstrap: "civeExtensionBootstrap",
  config: "civeExtensionConfig",
  cachedHcNumber: "hcNumber",
  cachedExpiry: "fechaCaducidad",
};

const DEFAULT_CONTROL_ENDPOINT =
  "https://cive.consulmed.me/api/cive-extension/config";
const DEFAULT_API_BASE_URL = "https://asistentecive.consulmed.me/api";
const DEFAULT_REFRESH_INTERVAL_MS = 900000; // 15 minutos
const SYNC_ALARM_NAME = "civeExtension.sync";
const EXAM_COMMAND_MAP = {
  "ejecutar-examen-directo": "octm", // compatibilidad con atajo previo
  "ejecutar-examen-octno": "octno",
  "ejecutar-examen-angio": "angio",
};

function storageGet(keys) {
  return new Promise((resolve) => {
    chrome.storage.local.get(keys, (items) => {
      if (chrome.runtime.lastError) {
        console.error("storageGet error:", chrome.runtime.lastError.message);
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
        console.error("storageSet error:", chrome.runtime.lastError.message);
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
  const { [STORAGE_KEYS.bootstrap]: bootstrap } = await storageGet([
    STORAGE_KEYS.bootstrap,
  ]);
  if (
    bootstrap &&
    typeof bootstrap.controlEndpoint === "string" &&
    bootstrap.controlEndpoint !== ""
  ) {
    return bootstrap.controlEndpoint;
  }
  return DEFAULT_CONTROL_ENDPOINT;
}

async function determineRefreshInterval() {
  const [
    { [STORAGE_KEYS.config]: config },
    { [STORAGE_KEYS.bootstrap]: bootstrap },
  ] = await Promise.all([
    storageGet([STORAGE_KEYS.config]),
    storageGet([STORAGE_KEYS.bootstrap]),
  ]);

  const candidate =
    config?.refreshIntervalMs ??
    bootstrap?.refreshIntervalMs ??
    DEFAULT_REFRESH_INTERVAL_MS;
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

async function fetchRemoteConfig(reason = "auto") {
  const endpoint = await determineControlEndpoint();
  try {
    const response = await fetch(endpoint, {
      method: "GET",
      credentials: "include",
    });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const payload = await response.json();
    if (!payload || payload.success === false || !payload.config) {
      throw new Error(
        payload?.message || "Respuesta inesperada desde MedForge.",
      );
    }

    const config = {
      ...payload.config,
      fetchedAt: Date.now(),
      fetchedBy: reason,
    };
    await storageSet({ [STORAGE_KEYS.config]: config });
    return config;
  } catch (error) {
    console.error(
      "No fue posible sincronizar la configuración de CIVE Extension:",
      error,
    );
    throw error;
  }
}

async function initializeBackground() {
  try {
    await fetchRemoteConfig("startup");
  } catch (error) {
    // La sincronización puede fallar si el usuario no está autenticado todavía.
  }
  await scheduleConfigSync();
}

async function handleOpenAiRequest(prompt) {
  const { [STORAGE_KEYS.config]: config } = await storageGet([
    STORAGE_KEYS.config,
  ]);
  const openAi = config?.openAi || {};
  const apiKey = openAi.apiKey || "";
  const model = openAi.model || "gpt-4o-mini";

  if (!apiKey) {
    return {
      success: false,
      text: "OpenAI no está configurado desde MedForge.",
    };
  }

  try {
    const response = await fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`,
      },
      body: JSON.stringify({
        model,
        messages: [
          {
            role: "user",
            content: String(prompt ?? ""),
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
    const text = data?.choices?.[0]?.message?.content?.trim() ?? "";
    return {
      success: true,
      text,
    };
  } catch (error) {
    console.error("Error en OpenAI API:", error);
    return {
      success: false,
      text: "Error al procesar la solicitud con OpenAI.",
    };
  }
}

async function handleSubscriptionCheck() {
  const [
    { [STORAGE_KEYS.bootstrap]: bootstrap },
    { [STORAGE_KEYS.config]: config },
  ] = await Promise.all([
    storageGet([STORAGE_KEYS.bootstrap]),
    storageGet([STORAGE_KEYS.config]),
  ]);

  const subscriptionEndpoint =
    bootstrap?.subscriptionEndpoint ||
    (config?.api?.baseUrl
      ? `${config.api.baseUrl.replace(/\/$/, "")}/subscription/check.php`
      : null);

  if (!subscriptionEndpoint) {
    return {
      success: false,
      error: "No se encontró el endpoint de suscripción.",
    };
  }

  try {
    const response = await fetch(subscriptionEndpoint, {
      method: "GET",
      credentials: "include",
    });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const data = await response.json().catch(() => ({}));
    const isSubscribed = Boolean(data.isSubscribed ?? data.success ?? false);
    const isApproved = Boolean(data.isApproved ?? data.authorized ?? false);
    return { success: isSubscribed && isApproved, raw: data };
  } catch (error) {
    console.error("Error al verificar la suscripción:", error);
    return {
      success: false,
      error: "No fue posible verificar la suscripción en MedForge.",
    };
  }
}

chrome.runtime.onInstalled.addListener(() => {
  initializeBackground().catch((error) =>
    console.warn("Inicialización diferida de CIVE Extension:", error),
  );
});

chrome.runtime.onStartup.addListener(() => {
  initializeBackground().catch((error) =>
    console.warn("Inicialización diferida de CIVE Extension:", error),
  );
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm?.name !== SYNC_ALARM_NAME) {
    return;
  }
  fetchRemoteConfig("alarm").finally(() => {
    scheduleConfigSync().catch((error) =>
      console.warn(
        "No fue posible reprogramar la sincronización de CIVE Extension:",
        error,
      ),
    );
  });
});

chrome.storage.onChanged.addListener((changes, area) => {
  if (area !== "local") {
    return;
  }
  if (Object.prototype.hasOwnProperty.call(changes, STORAGE_KEYS.bootstrap)) {
    scheduleConfigSync().catch((error) =>
      console.warn(
        "Error al actualizar la planificación de sincronización:",
        error,
      ),
    );
    fetchRemoteConfig("bootstrap-update").catch(() => {
      // La sincronización puede fallar si no hay sesión activa todavía.
    });
  }
});

function ejecutarExamenEnPestanaActiva(examenId) {
  if (!examenId) {
    return;
  }
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    if (!tabs || tabs.length === 0) {
      console.error("No se encontró la pestaña activa.");
      return;
    }
    const tabId = tabs[0].id;
    if (!tabId) {
      console.error("No se pudo obtener el ID de la pestaña.");
      return;
    }

    chrome.scripting.executeScript(
      {
        target: { tabId },
        files: ["js/examenes.js"],
      },
      () => {
        if (chrome.runtime.lastError) {
          console.error(
            "Error al inyectar examenes.js:",
            chrome.runtime.lastError.message,
          );
          return;
        }
        chrome.scripting.executeScript({
          target: { tabId },
          func: (examenId) => {
            if (typeof ejecutarExamenes === "function") {
              ejecutarExamenes(examenId);
            } else {
              console.error("ejecutarExamenes no está definida.");
            }
          },
          args: [examenId],
        });
      },
    );
  });
}

function normalizeApiBaseUrl(rawValue) {
  const fallback = new URL(DEFAULT_API_BASE_URL);
  try {
    const parsed = new URL(String(rawValue || "").trim() || DEFAULT_API_BASE_URL);
    if (!parsed.pathname || parsed.pathname === "/") {
      parsed.pathname = "/api";
    }
    return parsed.toString().replace(/\/+$/, "");
  } catch (error) {
    return fallback.toString().replace(/\/+$/, "");
  }
}

async function resolveApiBaseUrl() {
  const result = await storageGet([STORAGE_KEYS.config, STORAGE_KEYS.bootstrap]);
  const config = result?.[STORAGE_KEYS.config] || {};
  const bootstrap = result?.[STORAGE_KEYS.bootstrap] || {};
  const candidate =
    config?.apiBaseUrl ||
    config?.api?.baseUrl ||
    bootstrap?.apiBaseUrl ||
    bootstrap?.api?.baseUrl ||
    DEFAULT_API_BASE_URL;
  return normalizeApiBaseUrl(candidate);
}

function normalizeApiPath(path) {
  let normalized = (path || "").toString().trim();
  if (!normalized.startsWith("/")) {
    normalized = `/${normalized}`;
  }
  // Evita duplicar /api cuando la base ya lo incluye.
  if (normalized.toLowerCase().startsWith("/api/")) {
    normalized = normalized.slice(4);
  }
  return normalized;
}

async function buildApiUrl(path, query = null) {
  const base = await resolveApiBaseUrl();
  const url = new URL(`${base}${normalizeApiPath(path)}`);
  if (query && typeof query === "object") {
    Object.entries(query).forEach(([k, v]) => {
      if (v !== undefined && v !== null) {
        url.searchParams.set(k, v);
      }
    });
  }
  return url.toString();
}

chrome.commands.onCommand.addListener((command) => {
  if (command === "ejecutar-examen-selector") {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs || tabs.length === 0) {
        console.error("No se encontró la pestaña activa.");
        return;
      }
      const tabId = tabs[0].id;
      if (!tabId) {
        console.error("No se pudo obtener el ID de la pestaña.");
        return;
      }

      chrome.scripting.executeScript(
        {
          target: { tabId },
          files: ["js/examenes.js"],
        },
        () => {
          if (chrome.runtime.lastError) {
            console.error(
              "Error al inyectar examenes.js:",
              chrome.runtime.lastError.message,
            );
            return;
          }
          chrome.scripting.executeScript({
            target: { tabId },
            func: () => {
              const url = chrome.runtime.getURL("data/examenes.json");
              fetch(url)
                .then((resp) => resp.json())
                .then((data) => {
                  const examenes = Array.isArray(data?.examenes)
                    ? data.examenes
                    : [];
                  if (!examenes.length) {
                    alert("No hay exámenes configurados.");
                    return;
                  }
                  const listado = examenes
                    .map((ex) => `${ex.id} — ${ex.cirugia || ""}`.trim())
                    .join("\n");
                  const input = prompt(
                    `Escribe el ID de examen a ejecutar:\n${listado}`,
                    examenes[0].id,
                  );
                  const seleccionado = examenes.find((ex) => ex.id === input);
                  if (seleccionado && typeof ejecutarExamenes === "function") {
                    ejecutarExamenes(seleccionado.id);
                  } else if (input) {
                    alert("ID de examen no reconocido.");
                  }
                })
                .catch((error) =>
                  console.error("Error cargando lista de exámenes:", error),
                );
            },
          });
        },
      );
    });
    return;
  }

  const examenId = EXAM_COMMAND_MAP[command];
  if (examenId) {
    ejecutarExamenEnPestanaActiva(examenId);
  }
});

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (!request || typeof request.action !== "string") {
    return false;
  }

  if (request.action === "getFechaCaducidad") {
    chrome.storage.local.get(
      [STORAGE_KEYS.cachedHcNumber, STORAGE_KEYS.cachedExpiry],
      (result) => {
        if (result[STORAGE_KEYS.cachedHcNumber] === request.hcNumber) {
          sendResponse({
            fechaCaducidad: result[STORAGE_KEYS.cachedExpiry] ?? null,
          });
        } else {
          sendResponse({ fechaCaducidad: null });
        }
      },
    );
    return true;
  }

  if (request.action === "consultaAnterior") {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs || tabs.length === 0) {
        console.error("No se encontró la pestaña activa.");
        return;
      }

      chrome.scripting.executeScript(
        {
          target: { tabId: tabs[0].id },
          files: ["js/consulta.js"],
        },
        () => {
          chrome.scripting.executeScript({
            target: { tabId: tabs[0].id },
            function: () => {
              if (typeof consultaAnterior === "function") {
                consultaAnterior();
              } else {
                console.error("consultaAnterior no está definida.");
              }
            },
          });
        },
      );
    });
    return false;
  }

  if (request.action === "openai_request") {
    handleOpenAiRequest(request.prompt)
      .then((result) =>
        sendResponse({ text: result.text, success: result.success }),
      )
      .catch(() =>
        sendResponse({
          text: "Error al procesar la solicitud con OpenAI.",
          success: false,
        }),
      );
    return true;
  }

  if (request.action === "ejecutarPopEnPagina") {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs || tabs.length === 0) {
        console.error("No se encontró la pestaña activa.");
        return;
      }

      chrome.scripting.executeScript(
        {
          target: { tabId: tabs[0].id },
          files: ["js/consulta.js"],
        },
        () => {
          chrome.scripting.executeScript({
            target: { tabId: tabs[0].id },
            function: () => {
              if (typeof ejecutarPopEnPagina === "function") {
                ejecutarPopEnPagina();
              } else {
                console.error("ejecutarPopEnPagina no está definida.");
              }
            },
          });
        },
      );
    });
    return false;
  }

  if (request.action === "generatePDF") {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs || tabs.length === 0) {
        sendResponse({
          success: false,
          error: "No se encontró una pestaña activa.",
        });
        return;
      }
      chrome.tabs.sendMessage(
        tabs[0].id,
        {
          action: "generatePDF",
          content: request.content,
        },
        (response) => {
          if (chrome.runtime.lastError) {
            console.error(
              "Error enviando generatePDF:",
              chrome.runtime.lastError,
            );
            sendResponse({
              success: false,
              error: chrome.runtime.lastError.message,
            });
            return;
          }
          sendResponse(response);
        },
      );
    });
    return true;
  }

  if (request.action === "checkSubscription") {
    handleSubscriptionCheck().then(sendResponse);
    return true;
  }

  if (request.action === "ejecutarProtocolo") {
    const item = request.item;
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (chrome.runtime.lastError || !tabs || tabs.length === 0) {
        console.error(
          "Error al obtener la pestaña activa:",
          chrome.runtime.lastError || "No se encontraron pestañas activas.",
        );
        return;
      }
      const tabId = tabs[0].id;
      if (!tabId) {
        console.error("No se pudo obtener el ID de la pestaña.");
        return;
      }

      chrome.scripting.executeScript(
        {
          target: { tabId },
          files: ["js/procedimientos.js"],
        },
        () => {
          chrome.scripting.executeScript({
            target: { tabId },
            func: (payload) => {
              if (typeof ejecutarProtocoloEnPagina === "function") {
                ejecutarProtocoloEnPagina(payload);
              } else {
                console.error("ejecutarProtocoloEnPagina no está definida.");
              }
            },
            args: [item],
          });
        },
      );
    });
    return false;
  }

  if (request.action === "ejecutarReceta") {
    const item = request.item;
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (chrome.runtime.lastError || !tabs || tabs.length === 0) {
        console.error(
          "Error al obtener la pestaña activa:",
          chrome.runtime.lastError || "No se encontraron pestañas activas.",
        );
        return;
      }
      const tabId = tabs[0].id;
      if (!tabId) {
        console.error("No se pudo obtener el ID de la pestaña.");
        return;
      }

      chrome.scripting.executeScript(
        {
          target: { tabId },
          files: ["js/recetas.js"],
        },
        () => {
          chrome.scripting.executeScript({
            target: { tabId },
            func: (payload) => {
              if (typeof ejecutarRecetaEnPagina === "function") {
                ejecutarRecetaEnPagina(payload);
              } else {
                console.error("ejecutarRecetaEnPagina no está definida.");
              }
            },
            args: [item],
          });
        },
      );
    });
    return false;
  }

  if (request.action === "solicitudesEstado") {
    const hcNumber = (request.hcNumber || "").toString().trim();
    const pageOrigin = (request.pageOrigin || "").toString();
    if (!hcNumber) {
      sendResponse({ success: false, error: "Falta hcNumber" });
      return false;
    }
    const primaryUrl = new URL(
      "https://asistentecive.consulmed.me/api/solicitudes/estado.php",
    );
    primaryUrl.searchParams.set("hcNumber", hcNumber);

    const fallbacks = [];
    if (pageOrigin && /^https?:\/\//i.test(pageOrigin)) {
      try {
        const alt = new URL("/api/solicitudes/estado.php", pageOrigin);
        alt.searchParams.set("hcNumber", hcNumber);
        fallbacks.push(alt.toString());
      } catch (e) {
        // ignore malformed origin
      }
    }

    const tryFetch = async (url) => {
      const resp = await fetch(url, { method: "GET", credentials: "include" });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      return resp.json();
    };

    (async () => {
      const errors = [];
      for (const url of [primaryUrl.toString(), ...fallbacks]) {
        try {
          const data = await tryFetch(url);
          sendResponse({ success: true, data });
          return;
        } catch (err) {
          errors.push(err.message || String(err));
        }
      }
      console.error("Error en solicitudesEstado:", errors.join(" | "));
      sendResponse({
        success: false,
        error: errors[errors.length - 1] || "Error al consultar solicitudes",
      });
    })();
    return true;
  }

  if (request.action === "proyeccionesGet") {
    const path = (request.path || "").toString();
    const query = request.query || {};
    if (!path.startsWith("/")) {
      sendResponse({ success: false, error: "Path inválido" });
      return false;
    }
    (async () => {
      let url = "";
      try {
        url = await buildApiUrl(path, query);
        const resp = await fetch(url, { method: "GET", credentials: "include" });
        const text = await resp.text();
        if (!resp.ok) {
          throw new Error(`HTTP ${resp.status}${text ? ` | ${text}` : ""}`);
        }
        const data = text ? JSON.parse(text) : {};
        sendResponse({ success: true, data });
      } catch (error) {
        console.error("Error en proyeccionesGet:", error);
        sendResponse({
          success: false,
          error: error.message || "Error al consultar proyecciones",
          url,
        });
      }
    })();
    return true;
  }

  // Proxy genérico de API para saltar CORS desde el content script
  if (request.action === "apiRequest") {
    const url = request.url || "";
    const method = (request.method || "GET").toUpperCase();
    const headers = request.headers || {};
    const expectJson = request.expectJson !== false;
    const bodyType = request.bodyType || null;
    const body = request.body;

    if (!/^https?:\/\//i.test(url)) {
      sendResponse({ success: false, error: "URL inválida" });
      return false;
    }

    const fetchOptions = {
      method,
      credentials: "include",
      headers: { ...headers },
    };

    if (method !== "GET" && body !== undefined) {
      if (bodyType === "form") {
        fetchOptions.headers["Content-Type"] =
          "application/x-www-form-urlencoded;charset=UTF-8";
        fetchOptions.body = new URLSearchParams(body).toString();
      } else if (bodyType === "raw") {
        fetchOptions.body = body;
      } else {
        // json o auto
        fetchOptions.headers["Content-Type"] = "application/json;charset=UTF-8";
        fetchOptions.body = JSON.stringify(body);
      }
    }

    fetch(url, fetchOptions)
      .then((resp) => {
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return expectJson ? resp.json() : resp.text();
      })
      .then((data) => sendResponse({ success: true, data }))
      .catch((error) => {
        console.error("Error en apiRequest:", error);
        sendResponse({
          success: false,
          error: error.message || "Error al hacer fetch en background",
        });
      });
    return true;
  }

  if (request.action === "proyeccionesPost") {
    const path = (request.path || "").toString();
    const body = request.body || {};
    if (!path.startsWith("/")) {
      sendResponse({ success: false, error: "Path inválido" });
      return false;
    }
    (async () => {
      let url = "";
      try {
        url = await buildApiUrl(path);
        const resp = await fetch(url, {
          method: "POST",
          credentials: "include",
          headers: { "Content-Type": "application/json;charset=UTF-8" },
          body: JSON.stringify(body),
        });
        const text = await resp.text();
        if (!resp.ok) {
          throw new Error(`HTTP ${resp.status}${text ? ` | ${text}` : ""}`);
        }
        const data = text ? JSON.parse(text) : {};
        sendResponse({ success: true, data });
      } catch (error) {
        console.error("Error en proyeccionesPost:", error);
        sendResponse({
          success: false,
          error: error.message || "Error al enviar proyecciones",
          url,
        });
      }
    })();
    return true;
  }

  if (request.action === "solicitudActualizar") {
    const id = request.id;
    const payload = request.payload || {};
    if (!id) {
      sendResponse({ success: false, error: "Falta id de solicitud" });
      return false;
    }
    (async () => {
      const endpoint =
        "https://asistentecive.consulmed.me/api/solicitudes/estado.php";
      const baseBody = { id, ...payload };

      const attempt = async (mode) => {
        const headers =
          mode === "form"
            ? {
                "Content-Type":
                  "application/x-www-form-urlencoded;charset=UTF-8",
              }
            : { "Content-Type": "application/json;charset=UTF-8" };
        const body =
          mode === "form"
            ? new URLSearchParams(
                Object.entries(baseBody).reduce((acc, [k, v]) => {
                  if (v === undefined) return acc;
                  acc[k] = v;
                  return acc;
                }, {}),
              )
            : JSON.stringify(baseBody);

        const resp = await fetch(endpoint, {
          method: "POST",
          credentials: "include",
          headers,
          body,
        });
        const text = await resp.text();
        const debug = {
          mode,
          status: resp.status,
          statusText: resp.statusText,
          body: text,
          bodyLength: text?.length || 0,
        };
        if (!resp.ok) {
          const err = new Error(
            `HTTP ${resp.status}${text ? ` | ${text}` : ""}`,
          );
          err.debug = debug;
          throw err;
        }
        if (!text) return { data: {}, debug };
        try {
          return { data: JSON.parse(text), debug };
        } catch (parseError) {
          const err = new Error(`Respuesta inválida JSON (${resp.status})`);
          err.debug = debug;
          throw err;
        }
      };

      try {
        const first = await attempt("json");
        sendResponse({ success: true, data: first.data, debug: first.debug });
      } catch (error) {
        try {
          const second = await attempt("form");
          sendResponse({
            success: true,
            data: second.data,
            debug: second.debug,
            fallback: "form",
          });
        } catch (error2) {
          console.error("Error en solicitudActualizar:", {
            error: error2,
            id,
            payload,
            debug: error2?.debug,
          });
          sendResponse({
            success: false,
            error: error2.message || "Error al actualizar solicitud",
            debug: error2?.debug,
          });
        }
      }
    })();
    return true;
  }

  if (request.action === "listarDoctores") {
    fetch(
      "https://asistentecive.consulmed.me/api/solicitudes/kanban_data.php",
      {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body: new URLSearchParams({}),
      },
    )
      .then((resp) => {
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp.json();
      })
      .then((data) => {
        const doctores = Array.isArray(data?.options?.doctores)
          ? data.options.doctores
          : [];
        sendResponse({ success: true, doctores });
      })
      .catch((error) => {
        console.error("Error en listarDoctores:", error);
        sendResponse({
          success: false,
          error: error.message || "No se pudo obtener doctores",
        });
      });
    return true;
  }

  if (request.action === "listarLentes") {
    fetch("https://asistentecive.consulmed.me/api/lentes/index.php", {
      method: "GET",
      credentials: "include",
    })
      .then((resp) => {
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp.json();
      })
      .then((data) => {
        const lentes = Array.isArray(data?.lentes) ? data.lentes : [];
        sendResponse({ success: true, lentes });
      })
      .catch((error) => {
        console.error("Error en listarLentes:", error);
        sendResponse({
          success: false,
          error: error.message || "No se pudo obtener lentes",
        });
      });
    return true;
  }

  return false;
});
