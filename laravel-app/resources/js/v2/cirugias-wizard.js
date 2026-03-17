import '../../css/medforge-datatables.css';
import '../../css/v2/cirugias-wizard.css';
import {
    ensureDataTables,
    ensureSweetAlert,
    loadLegacyScript,
} from '../medforge/v2/legacyRuntime';

const boot = async () => {
    await ensureDataTables();
    await ensureSweetAlert();
    await loadLegacyScript('/assets/vendor_components/tiny-editable/mindmup-editabletable.js');
    await loadLegacyScript('/assets/vendor_components/tiny-editable/numeric-input-example.js');
    await loadLegacyScript('/assets/vendor_components/jquery-steps-master/build/jquery.steps.js');
    await loadLegacyScript('/assets/vendor_components/jquery-validation-1.17.0/dist/jquery.validate.min.js');
    await loadLegacyScript('/js/pages/steps.js');
    await loadLegacyScript('/js/modules/cirugias_wizard.js');
};

boot().catch((error) => {
    console.error('Unable to initialize the cirugias wizard page bundle.', error);
});
