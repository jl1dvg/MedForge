import '../../../../public/assets/vendor_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css';
import { bootLegacyPatientFlowPage } from '../medforge/v2/bootLegacyPatientFlowPage';

bootLegacyPatientFlowPage('/js/pages/pacientes/flujo.js').catch((error) => {
    console.error('Unable to initialize the flujo de pacientes page bundle.', error);
});
