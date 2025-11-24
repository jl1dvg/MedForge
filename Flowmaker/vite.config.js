// vite.config.js
import { defineConfig } from 'vite'

export default defineConfig({
    plugins: [],
    build: {
        outDir: 'public/build',
        rollupOptions: {
            input: [
                'Resources/assets/js/app.js',
            ],
        },
    },
    base: './',
})