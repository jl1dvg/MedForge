import { showToast } from './toast.js';
import { createCrmPanel } from '../../shared/crmPanelFactory.js';

const {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
} = createCrmPanel({
    showToast,
    getBasePath: () => '/examenes',
    entityLabel: 'examen',
    entityArticle: 'el',
    entitySelectionSuffix: 'seleccionado',
    datasetIdKey: 'examenId',
});

export {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
};
