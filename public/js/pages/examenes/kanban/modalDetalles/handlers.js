import { abrirPrefactura } from "./prefactura.js";
import {
    renderEstadoContext,
    renderPatientSummaryFallback,
} from "./state.js";
import { highlightSelection, resolverDataset } from "./ui.js";

export function handlePrefacturaClick(event) {
    const trigger = event.target.closest("[data-prefactura-trigger]");
    if (!trigger) {
        return;
    }

    const { hc, formId, examenId } = resolverDataset(trigger);
    highlightSelection({ cardId: examenId, rowId: examenId });
    renderEstadoContext(examenId);
    renderPatientSummaryFallback(examenId);
    abrirPrefactura({ hc, formId, examenId });
}
