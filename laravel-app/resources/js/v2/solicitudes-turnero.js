import {
    ensurePusher,
    loadLegacyModuleScript,
} from '../medforge/v2/legacyRuntime';

const shouldLoadRealtime = () => {
    const options = window.app && window.app.options && typeof window.app.options === 'object'
        ? window.app.options
        : {};

    const enabled = String(options.pusher_realtime_notifications ?? '').trim() === '1';
    const key = String(options.pusher_app_key ?? '').trim();

    return enabled && key !== '';
};

const boot = async () => {
    if (shouldLoadRealtime()) {
        await ensurePusher();
    }

    await loadLegacyModuleScript('/js/pages/solicitudes/turnero.js');
};

boot().catch((error) => {
    console.error('Unable to initialize the solicitudes turnero page bundle.', error);
});
