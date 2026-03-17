import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/medforge.css',
                'resources/js/medforge.js',
                'resources/js/v2/codes-index.js',
                'resources/js/v2/patients-index.js',
                'resources/js/v2/derivaciones-index.js',
                'resources/js/v2/billing-dashboard.js',
                'resources/js/v2/billing-honorarios.js',
                'resources/js/v2/solicitudes-dashboard.js',
                'resources/js/v2/billing-no-facturados.js',
                'resources/js/v2/cirugias-index.js',
                'resources/js/v2/cirugias-dashboard.js',
                'resources/js/v2/dashboard-home.js',
                'resources/js/v2/patient-detail.js',
                'resources/js/v2/imagenes-dashboard.js',
                'resources/js/v2/imagenes-realizadas.js',
                'resources/js/v2/pacientes-flujo.js',
                'resources/js/v2/solicitudes-index.js',
                'resources/js/v2/examenes-index.js',
                'resources/js/v2/examenes-turnero.js',
                'resources/js/v2/solicitudes-turnero.js',
                'resources/js/v2/code-packages.js',
                'resources/js/v2/code-form.js',
                'resources/js/v2/cirugias-wizard.js',
                'resources/js/v2/billing-informe-particulares.js',
                'resources/js/v2/user-edit.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
