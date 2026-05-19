import '../../css/solicitudes-crm-panel.css';

import('./solicitudes/crm-global.js').catch((error) => {
    console.error('Unable to initialize the solicitudes CRM global bundle.', error);
});
