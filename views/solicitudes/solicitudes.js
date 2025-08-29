import {initKanban} from './js/kanban/index.js';

document.addEventListener('DOMContentLoaded', () => {
    if (window.allSolicitudes) {
        initKanban(window.allSolicitudes);
    } else {
        console.error('❌ No se encontró allSolicitudes');
    }
});