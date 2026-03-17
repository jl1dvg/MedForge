import { bootLegacyInteractiveTablePage } from '../medforge/v2/bootLegacyInteractiveTablePage';

bootLegacyInteractiveTablePage('/js/pages/cirugias.js').catch((error) => {
    console.error('Unable to initialize the cirugias index page bundle.', error);
});
