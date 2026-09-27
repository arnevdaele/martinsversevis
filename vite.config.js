import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    resolve: {
        alias: { '@': '/resources/js' },
    },
    plugins: [
        laravel({
            input: ['resources/css/portal.css', 'resources/js/portal.tsx'],
            refresh: true,
            fonts: [bunny('Inter', { weights: [400, 500, 600, 700] })],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
