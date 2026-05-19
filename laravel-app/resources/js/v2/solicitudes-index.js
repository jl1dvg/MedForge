import '../../css/solicitudes-crm-panel.css';
import { ensurePusher } from '../medforge/v2/legacyRuntime';

const shouldLoadRealtime = () => {
    const config = window.MEDF_PusherConfig;
    return Boolean(config && typeof config === 'object' && config.enabled && config.key);
};

const boot = async () => {
    if (shouldLoadRealtime()) {
        await ensurePusher();
    }

    await import('./solicitudes/index.js');
};

boot().catch((error) => {
    console.error('Unable to initialize the solicitudes v2 page bundle.', error);
});
