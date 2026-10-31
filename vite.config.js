import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            'lucide-react': fileURLToPath(new URL('./resources/js/Components/Restaurant/Icons.jsx', import.meta.url)),
            'gsap': fileURLToPath(new URL('./node_modules/gsap', import.meta.url)),
            'lenis': fileURLToPath(new URL('./node_modules/lenis', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/gsap') || id.includes('node_modules/lenis')) {
                        return 'vendor-motion';
                    }
                    if (
                        id.includes('node_modules/react') ||
                        id.includes('node_modules/scheduler') ||
                        id.includes('node_modules/@inertiajs')
                    ) {
                        return 'vendor-react';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
