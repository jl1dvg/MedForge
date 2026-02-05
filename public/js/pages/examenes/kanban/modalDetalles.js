import { handlePrefacturaClick } from "./modalDetalles/handlers.js";

let prefacturaListenerAttached = false;

export function inicializarModalDetalles() {
    if (prefacturaListenerAttached) {
        return;
    }

    prefacturaListenerAttached = true;
    document.addEventListener("click", handlePrefacturaClick);
}
