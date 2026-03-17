import {
    ensureSortable,
    loadLegacyScript,
} from '../medforge/v2/legacyRuntime';

const boot = async () => {
    await ensureSortable();
    await loadLegacyScript('/js/pages/code-packages.js');
};

boot().catch((error) => {
    console.error('Unable to initialize the code packages page bundle.', error);
});
