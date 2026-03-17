import {
    ensureApexCharts,
    ensureDaterangepicker,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyDashboardPage = async (pageScriptPath) => {
    await ensureDaterangepicker();
    await ensureApexCharts();
    await loadLegacyScript(pageScriptPath);
};
