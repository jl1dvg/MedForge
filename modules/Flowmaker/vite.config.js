import { defineConfig } from 'vite';
import laravel  from 'laravel-vite-plugin';
import tailwindcss from 'tailwindcss';
import react from '@vitejs/plugin-react'
import path from "path";

export default defineConfig({
    plugins: [
        react(),
        tailwindcss({
            config: './tailwind.config.js',
        }),
        laravel({
            input: [
                'Resources/assets/js/index.tsx',
            ]
        }),
    ],
    resolve: {
        alias: {
          "@": path.resolve(__dirname, "./Resources/assets/js"),
        },
      },
});
