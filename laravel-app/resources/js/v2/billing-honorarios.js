import '../../../../public/assets/vendor_components/bootstrap-daterangepicker/daterangepicker.css';
import '../../../../public/assets/vendor_components/datatable/datatables.min.css';
import {
    ensureApexCharts,
    ensureDataTableLanguage,
    ensureDataTables,
    ensureDaterangepicker,
    loadLegacyScript,
} from '../medforge/v2/legacyRuntime';

Promise.all([
    ensureDaterangepicker(),
    ensureApexCharts(),
    ensureDataTables(),
    ensureDataTableLanguage(),
])
    .then(() => loadLegacyScript('/js/pages/billing/v2-honorarios.js?v=20260503-honorarios-sede-filter'))
    .catch((error) => {
    console.error('Unable to initialize the billing honorarios page bundle.', error);
    });
