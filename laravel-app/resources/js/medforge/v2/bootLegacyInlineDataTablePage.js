import '../../../css/medforge-datatables.css';
import {
    ensureDataTables,
    ensureDataTableLanguage,
} from './legacyRuntime';

export const bootLegacyInlineDataTablePage = async () => {
    await ensureDataTables();
    await ensureDataTableLanguage();
};
