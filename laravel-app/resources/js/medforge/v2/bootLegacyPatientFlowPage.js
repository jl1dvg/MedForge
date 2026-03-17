import {
    ensureBootstrapDatepicker,
    ensureMoment,
    ensureSweetAlert,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyPatientFlowPage = async (pageScriptPath) => {
    await ensureMoment();
    await ensureSweetAlert();
    await ensureBootstrapDatepicker();
    await loadLegacyScript(pageScriptPath);
};
