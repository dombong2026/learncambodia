import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: { license: { fileName: 'third-party-licenses.md' } },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/js/workspace.ts', 'resources/js/focused-media.ts'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
