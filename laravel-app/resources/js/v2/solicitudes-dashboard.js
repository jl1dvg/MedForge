import { bootLegacyDashboardPage } from '../medforge/v2/bootLegacyDashboardPage';

bootLegacyDashboardPage('/js/pages/solicitudes/dashboard.js').catch((error) => {
    console.error('Unable to initialize the solicitudes dashboard page bundle.', error);
});
