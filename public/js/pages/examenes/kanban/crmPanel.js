import { showToast } from './toast.js';
import { createCrmPanel } from '../../shared/crmPanelFactory.js';
import { resolveReadPath, resolveWritePath } from './config.js';

const {
    setCrmOptions,
    refreshCrmPanelIfActive,
    getCrmKanbanPreferences,
    initCrmInteractions,
} = createCrmPanel({
    showToast,
    getBasePath: () => '/examenes',
    resolveReadPath,
    resolveWritePath,
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
