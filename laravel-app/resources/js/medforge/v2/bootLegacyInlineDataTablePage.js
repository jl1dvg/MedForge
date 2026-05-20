import '../../../css/medforge-datatables.css';
import {
    ensureDataTables,
    ensureDataTableLanguage,
} from './legacyRuntime';

export const bootLegacyInlineDataTablePage = async () => {
    await ensureDataTables();
    await ensureDataTableLanguage();

    if (typeof window !== 'undefined') {
        window.__medforgeLegacyInlineDataTableReady = true;
        window.dispatchEvent(new CustomEvent('medforge:legacy-inline-datatable-ready'));
    }
};
