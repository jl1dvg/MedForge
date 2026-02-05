import { getTableBodySelector } from "../config.js";

export function cssEscape(value) {
    if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
        return CSS.escape(value);
    }

    return String(value).replace(/([ #;?%&,.+*~\':"!^$\[\]()=>|\/\\@])/g, "\\$1");
}

export function highlightSelection({ cardId, rowId }) {
    document
        .querySelectorAll(".kanban-card")
        .forEach((element) => element.classList.remove("active"));

    const tableSelector = getTableBodySelector();
    document
        .querySelectorAll(`${tableSelector} tr`)
        .forEach((row) => row.classList.remove("table-active"));

    if (cardId) {
        const card = document.querySelector(
            `.kanban-card[data-id="${cssEscape(cardId)}"]`
        );
        if (card) {
            card.classList.add("active");
        }
    }

    if (rowId) {
        const row = document.querySelector(
            `${tableSelector} tr[data-id="${cssEscape(rowId)}"]`
        );
        if (row) {
            row.classList.add("table-active");
        }
    }
}

export function resolverDataset(trigger) {
    const container = trigger.closest("[data-hc][data-form]") ?? trigger;
    const hc = trigger.dataset.hc || container?.dataset.hc || "";
    const formId = trigger.dataset.form || container?.dataset.form || "";
    const examenId = trigger.dataset.id || container?.dataset.id || "";

    return { hc, formId, examenId };
}
