import { showToast } from './toast.js';
import {
    getKanbanConfig,
    resolveReadPath as resolveKanbanReadPath,
    resolveWritePath as resolveKanbanWritePath,
} from './config.js';
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
        /\/\d+\/crm\/adjuntos$/,
        /\/\d+\/crm\/bloqueo$/,
    ];

    if (!migratedPatterns.some(pattern => pattern.test(rawPath))) {
        return rawPath;
    }

    return resolveKanbanWritePath(rawPath);
}

function resolveSolicitudesCrmReadPath(path) {
    const rawPath = (path ?? '').toString();
    if (rawPath === '') {
        return rawPath;
    }

    const migratedPatterns = [
        /\/\d+\/crm$/,
        /\/\d+\/crm\/checklist-state$/,
    ];

    if (!migratedPatterns.some(pattern => pattern.test(rawPath))) {
        return rawPath;
    }

    return resolveKanbanReadPath(rawPath);
}

const {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
} = createCrmPanel({
    showToast,
    getBasePath: () => getKanbanConfig().basePath,
    resolveReadPath: resolveSolicitudesCrmReadPath,
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
