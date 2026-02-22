import { showToast } from './toast.js';
import { getKanbanConfig, resolveWritePath as resolveKanbanWritePath } from './config.js';
import { createCrmPanel } from '../../shared/crmPanelFactory.js';

function resolveSolicitudesCrmWritePath(path) {
    const rawPath = (path ?? '').toString();
    if (rawPath === '') {
        return rawPath;
    }

    const migratedPatterns = [
        /\/\d+\/crm$/,
        /\/\d+\/crm\/notas$/,
        /\/\d+\/crm\/tareas$/,
        /\/\d+\/crm\/tareas\/estado$/,
    ];

    if (!migratedPatterns.some(pattern => pattern.test(rawPath))) {
        return rawPath;
    }

    return resolveKanbanWritePath(rawPath);
}

const {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
} = createCrmPanel({
    showToast,
    getBasePath: () => getKanbanConfig().basePath,
    resolveWritePath: resolveSolicitudesCrmWritePath,
    entityLabel: 'solicitud',
    entityArticle: 'la',
    entitySelectionSuffix: 'seleccionada',
    datasetIdKey: 'solicitudId',
});

export {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
};
