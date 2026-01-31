export function syncPrefacturaContext({formId, hcNumber, solicitudId}) {
    const payload = {
        formId: formId || "",
        hcNumber: hcNumber || "",
        solicitudId: solicitudId || "",
    };
    window.__prefacturaCurrent = payload;

    const button = document.getElementById("btnRescrapeDerivacion");
    if (!button) {
        return;
    }

    button.dataset.formId = payload.formId;
    button.dataset.hcNumber = payload.hcNumber;
    button.dataset.solicitudId = payload.solicitudId;
}

export function getPrefacturaContextFromButton(button) {
    const fallback = window.__prefacturaCurrent || {};
    const formId = button?.dataset?.formId || fallback.formId || "";
    const hcNumber = button?.dataset?.hcNumber || fallback.hcNumber || "";
    const solicitudId = button?.dataset?.solicitudId || fallback.solicitudId || "";

    return {formId, hcNumber, solicitudId};
}
