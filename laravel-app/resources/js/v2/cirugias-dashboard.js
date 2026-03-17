import { bootLegacyApexPage } from '../medforge/v2/bootLegacyApexPage';

bootLegacyApexPage('/js/pages/cirugias_dashboard.js').catch((error) => {
    console.error('Unable to initialize the cirugias dashboard page bundle.', error);
});
