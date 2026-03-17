import {
    ensureApexCharts,
    ensureDataTables,
    ensureDataTableLanguage,
} from '../medforge/v2/legacyRuntime';

const READY_EVENT = 'medforge:billing-informe-particulares:ready';

const markReady = () => {
    window.__MEDFORGE_BILLING_PARTICULARES_READY__ = true;
    window.dispatchEvent(new CustomEvent(READY_EVENT));
};

Promise.all([
    ensureDataTables(),
    ensureDataTableLanguage(),
    ensureApexCharts(),
]).then(markReady).catch((error) => {
    console.error('Unable to initialize the billing particulares page bundle.', error);
});
