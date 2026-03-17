import { bootLegacyDataTablePage } from '../medforge/v2/bootLegacyDataTablePage';

bootLegacyDataTablePage('/js/pages/patients.js').catch((error) => {
    console.error('Unable to initialize the patients index page bundle.', error);
});
