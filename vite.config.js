import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from 'tailwindcss';
import { resolve } from 'path';
import fs from 'fs';
import path from 'path';
import { viteProjectMapPlugin } from './scripts/dev/vite-plugin-project-map.mjs';

/**
 * With cssCodeSplit:false Vite emits one style-*.css, but Laravel's manifest
 * often omits it from the JS entry `css` array — so @vite never injects <link>.
 * Attach the bundle CSS to resources/js/main.js after write.
 */
function attachBundledCssToMainEntry() {
    return {
        name: 'attach-bundled-css-to-main-entry',
        apply: 'build',
        enforce: 'post',
        closeBundle() {
            const candidates = [
                resolve(__dirname, 'public/build/manifest.json'),
                resolve(__dirname, 'public/build/.vite/manifest.json'),
            ];
            for (const manifestPath of candidates) {
                if (!fs.existsSync(manifestPath)) continue;
                const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
                const main = manifest['resources/js/main.js'];
                if (!main) continue;

                let styleFile = null;
                for (const [key, val] of Object.entries(manifest)) {
                    if (!val?.file || !String(val.file).endsWith('.css')) continue;
                    if (key === 'style.css' || String(val.file).includes('/style.')) {
                        styleFile = val.file;
                        break;
                    }
                }
                if (!styleFile) {
                    // Fallback: largest css asset in build output
                    const assetsDir = resolve(__dirname, 'public/build/assets');
                    if (fs.existsSync(assetsDir)) {
                        const cssFiles = fs
                            .readdirSync(assetsDir)
                            .filter((f) => f.endsWith('.css'))
                            .map((f) => ({
                                f,
                                size: fs.statSync(path.join(assetsDir, f)).size,
                            }))
                            .sort((a, b) => b.size - a.size);
                        if (cssFiles[0]) styleFile = `assets/${cssFiles[0].f}`;
                    }
                }
                if (!styleFile) continue;

                const existing = Array.isArray(main.css) ? main.css : [];
                if (!existing.includes(styleFile)) {
                    main.css = [...existing, styleFile];
                    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
                    console.log(`[attach-bundled-css] linked ${styleFile} → resources/js/main.js`);
                }
            }
        },
    };
}

export default defineConfig(({ command }) => ({
    // Do NOT set base: '/' — laravel-vite-plugin must use /build/ in production
    // so dynamic imports resolve to /build/assets/*.js (not /assets/*.js → HTML 404).
    plugins: [
        laravel({
            input: [
                // Single SPA entry (CSS imported from main.js).
                'resources/js/main.js',
            ],
            refresh: false,
        }),
        vue(),
        attachBundledCssToMainEntry(),
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
        // One CSS file for the whole SPA so vue-router pages cannot miss styles
        // (split CSS chunks were emitted but not loaded by async JS).
        cssCodeSplit: false,
        assetsDir: 'assets',
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.svg')) {
                        return 'assets/[name].[hash][extname]';
                    }
                    return 'assets/[name].[hash][extname]';
                },
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
