import {
    ensureApexCharts,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyApexPage = async (pageScriptPath) => {
    await ensureApexCharts();
    await loadLegacyScript(pageScriptPath);
};
