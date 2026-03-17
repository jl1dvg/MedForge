import {
    ensureCkeditor,
    ensureDaterangepicker,
    ensurePusher,
    ensureSortable,
    ensureSweetAlert,
    loadLegacyModuleScript,
} from '../medforge/v2/legacyRuntime';

const shouldLoadRealtime = () => {
    const config = window.MEDF_PusherConfig;
    return Boolean(config && typeof config === 'object' && config.enabled && config.key);
};

const boot = async () => {
    await ensureDaterangepicker();
    await ensureSweetAlert();
    await ensureSortable();
    await ensureCkeditor();

    if (shouldLoadRealtime()) {
        await ensurePusher();
    }

    await loadLegacyModuleScript('/js/pages/examenes/index.js');
};

boot().catch((error) => {
    console.error('Unable to initialize the examenes page bundle.', error);
});
