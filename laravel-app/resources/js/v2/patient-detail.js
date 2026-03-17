import '../../../../public/assets/vendor_components/horizontal-timeline/css/horizontal-timeline.css';
import '../../css/v2/patient-detail.css';
import { bootLegacyPatientDetailPage } from '../medforge/v2/bootLegacyPatientDetailPage';

function getAssetSuffix() {
    const config = window.__SOLICITUDES_V2_UI__ || {};
    const version = String(config.assetVersion || '').trim();
    return version ? `?v=${encodeURIComponent(version)}` : '';
}

bootLegacyPatientDetailPage(`/js/pages/patient-detail.js${getAssetSuffix()}`).catch((error) => {
    console.error('Unable to initialize the patient detail page bundle.', error);
});
