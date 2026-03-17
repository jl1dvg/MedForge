import { bootLegacyPatientDetailPage } from '../medforge/v2/bootLegacyPatientDetailPage';

bootLegacyPatientDetailPage('/js/pages/patient-detail.js').catch((error) => {
    console.error('Unable to initialize the patient detail page bundle.', error);
});
