import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            publicDirectory: 'public_html',
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5153,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
            port: 5153,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
