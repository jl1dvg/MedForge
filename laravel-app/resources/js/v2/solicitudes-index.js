import {
    ensurePusher,
    loadLegacyScript,
} from '../medforge/v2/legacyRuntime';

const shouldLoadRealtime = () => {
    const config = window.MEDF_PusherConfig;
    return Boolean(config && typeof config === 'object' && config.enabled && config.key);
};

const getAssetSuffix = () => {
    const config = window.__SOLICITUDES_V2_UI__;
    const version = config && typeof config === 'object'
        ? String(config.assetVersion || '').trim()
        : '';

    return version ? `?v=${encodeURIComponent(version)}` : '';
};

const boot = async () => {
    if (shouldLoadRealtime()) {
        await ensurePusher();
    }

    await loadLegacyScript(`/js/pages/solicitudes/v2-index.js${getAssetSuffix()}`);
};

boot().catch((error) => {
    console.error('Unable to initialize the solicitudes v2 page bundle.', error);
});
