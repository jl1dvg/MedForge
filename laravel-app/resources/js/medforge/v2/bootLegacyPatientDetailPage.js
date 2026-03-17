import {
    ensureApexCharts,
    ensureHorizontalTimeline,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyPatientDetailPage = async (pageScriptPath) => {
    await ensureApexCharts();
    await ensureHorizontalTimeline();
    await loadLegacyScript(pageScriptPath);
};
