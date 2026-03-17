import { bootLegacyDashboardPage } from '../medforge/v2/bootLegacyDashboardPage';

bootLegacyDashboardPage('/js/pages/billing/v2-honorarios.js').catch((error) => {
    console.error('Unable to initialize the billing honorarios page bundle.', error);
});
