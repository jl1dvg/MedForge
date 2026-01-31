import {getDataStore} from "../config.js";

export function findSolicitudById(id) {
    if (!id) {
        return null;
    }
    const store = getDataStore();
    if (!Array.isArray(store) || !store.length) {
        return null;
    }
    return (
        store.find(
            (item) =>
                String(item.id) === String(id) ||
                String(item.form_id) === String(id)
        ) || null
    );
}
