import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from 'tailwindcss';
import { resolve } from 'path';
import { viteProjectMapPlugin } from './scripts/dev/vite-plugin-project-map.mjs';

export default defineConfig(({ command }) => ({
    base: '/',
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                // SPA entry used by welcome.blade.php (@vite main.js).
                // Do not also enter resources/js/app.js — a second App mount entry
                // forces shared App/vendor chunks into the initial graph incorrectly.
                'resources/js/main.js'
            ],
            refresh: false,
        }),
        vue(),
        ...(command === 'serve' ? [viteProjectMapPlugin()] : []),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss()],
        },
    },
    build: {
        assetsDir: 'assets',
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    // Preserve SVG files with proper naming
                    if (assetInfo.name && assetInfo.name.endsWith('.svg')) {
                        return 'assets/[name].[hash][extname]';
                    }
                    return 'assets/[name].[hash][extname]';
                },
                /**
                 * Only split the Vue runtime. Extra vendor manualChunks previously
                 * absorbed shared modules and forced heavy libs (docs/apex/leaflet)
                 * into the initial graph. Heavy libs stay in their lazy route chunks.
                 */
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }
                    const n = id.split('\\').join('/');
                    if (
                        n.includes('/node_modules/vue/') ||
                        n.includes('/node_modules/@vue/') ||
                        n.includes('/node_modules/vue-router/')
                    ) {
                        return 'vendor-vue';
                    }
                },
            },
        },
    },
}));
