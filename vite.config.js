import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        // Production minifier (default in Vite >=5 is esbuild, kept explicit for clarity).
        minify: 'esbuild',
        cssMinify: true,
        // Match modern browser baseline so we ship smaller, faster JS.
        target: 'es2020',
        // Split heavy vendor libs out of the main `app-*.js` so first paint downloads less.
        // Map (Leaflet/Turf) and Lucide are only needed on specific pages, so they go to
        // their own chunks that the bundler will load on demand via dynamic imports.
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('leaflet')) {
                            return 'vendor-leaflet';
                        }
                        if (id.includes('@turf')) {
                            return 'vendor-turf';
                        }
                        if (id.includes('lucide')) {
                            return 'vendor-lucide';
                        }
                        if (id.includes('axios')) {
                            return 'vendor-axios';
                        }
                    }
                    return undefined;
                },
            },
        },
        // Keep the Vite warning friendly; per-chunk size remains well under this limit.
        chunkSizeWarningLimit: 600,
    },
});
