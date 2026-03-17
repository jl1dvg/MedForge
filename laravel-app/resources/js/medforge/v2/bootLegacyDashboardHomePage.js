import {
    ensureApexCharts,
    ensureOwlCarousel,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyDashboardHomePage = async (pageScriptPath) => {
    await ensureApexCharts();
    await ensureOwlCarousel();
    await loadLegacyScript(pageScriptPath);
};
