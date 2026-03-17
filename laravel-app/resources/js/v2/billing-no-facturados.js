import { bootLegacyInteractiveTablePage } from '../medforge/v2/bootLegacyInteractiveTablePage';

bootLegacyInteractiveTablePage('/js/pages/billing/v2-no-facturados.js', { peity: true }).catch((error) => {
    console.error('Unable to initialize the billing no-facturados page bundle.', error);
});
