(function () {
    'use strict';

    const root = document.getElementById('crm-root');
    if (!root) {
        return;
    }

    import('./crm/index.js').catch((error) => {
        console.error('No se pudo cargar el CRM modular', error);
    });
})();
