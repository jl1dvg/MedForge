import {
    ensureDataTables,
    ensureDataTableLanguage,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyDataTablePage = async (pageScriptPath) => {
    await ensureDataTables();
    await ensureDataTableLanguage();
    await loadLegacyScript(pageScriptPath);
};
