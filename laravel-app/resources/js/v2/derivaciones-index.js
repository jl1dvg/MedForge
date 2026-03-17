import { bootLegacyDataTablePage } from '../medforge/v2/bootLegacyDataTablePage';

bootLegacyDataTablePage('/js/pages/derivaciones.js').catch((error) => {
    console.error('Unable to initialize the referrals index page bundle.', error);
});
