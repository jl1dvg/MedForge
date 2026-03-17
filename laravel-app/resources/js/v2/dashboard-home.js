import { bootLegacyDashboardHomePage } from '../medforge/v2/bootLegacyDashboardHomePage';

bootLegacyDashboardHomePage('/js/pages/dashboard3.js').catch((error) => {
    console.error('Unable to initialize the main dashboard page bundle.', error);
});
