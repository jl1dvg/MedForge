import { bootLegacyDataTablePage } from '../medforge/v2/bootLegacyDataTablePage';

bootLegacyDataTablePage('/js/pages/codes-index.js').catch((error) => {
    console.error('Unable to initialize the codes index page bundle.', error);
});
