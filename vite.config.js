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
                'resources/js/app.js',
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
            },
        },
    },
}));
