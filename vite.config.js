import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/app/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        allowedHosts: ['node.kinerja.local'],
        host: '0.0.0.0',
        port: 5195,
        strictPort: true,
        hmr: {
            host: 'kinerja.orb.local',
            port: 5195,
        },
        watch: {
            usePolling: true,
        },
    },
});
