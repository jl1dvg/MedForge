import {
    ensurePusher,
    loadLegacyScript,
} from '../medforge/v2/legacyRuntime';

const shouldLoadRealtime = () => {
    const config = window.MEDF_PusherConfig;
    return Boolean(config && typeof config === 'object' && config.enabled && config.key);
};

const boot = async () => {
    if (shouldLoadRealtime()) {
        await ensurePusher();
    }

    await loadLegacyScript('/js/pages/solicitudes/v2-index.js');
};

boot().catch((error) => {
    console.error('Unable to initialize the solicitudes v2 page bundle.', error);
});
