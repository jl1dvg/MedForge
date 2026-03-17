import { bootLegacyDashboardPage } from '../medforge/v2/bootLegacyDashboardPage';

bootLegacyDashboardPage('/js/pages/billing/v2-dashboard.js').catch((error) => {
    console.error('Unable to initialize the billing dashboard page bundle.', error);
});
