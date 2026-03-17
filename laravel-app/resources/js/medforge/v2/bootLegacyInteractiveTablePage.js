import '../../../css/medforge-datatables.css';
import {
    ensureDataTables,
    ensureDataTableLanguage,
    ensurePeity,
    ensureSweetAlert,
    loadLegacyScript,
} from './legacyRuntime';

export const bootLegacyInteractiveTablePage = async (pageScriptPath, options = {}) => {
    await ensureDataTables();
    await ensureDataTableLanguage();
    await ensureSweetAlert();

    if (options.peity) {
        await ensurePeity();
    }

    await loadLegacyScript(pageScriptPath);
};
