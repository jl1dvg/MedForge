import { showToast } from './toast.js';
import { getKanbanConfig } from './config.js';
import { createCrmPanel } from '../../shared/crmPanelFactory.js';

const {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
} = createCrmPanel({
    showToast,
    getBasePath: () => getKanbanConfig().basePath,
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
