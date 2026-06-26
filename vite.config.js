import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import liveReload from 'vite-plugin-live-reload';
import path from 'path';
import { fileURLToPath } from 'url';
import { copyFileSync, existsSync } from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// WordPress.org Plugin Check rejects file names with spaces or special
// characters. Vite names a shared chunk after the `@ui` directory, which would
// emit `@ui.[hash].js/.css` — the `@` is rejected. Sanitize every output name
// down to [A-Za-z0-9._-] so no built asset can ever fail that check.
const safeOutputName = (name) =>
    (name || 'chunk').replace(/[^A-Za-z0-9._-]/g, '-').replace(/^[-.]+/, '') || 'chunk';

// Plugin to copy manifest to assets root after build
const copyManifestPlugin = () => {
    return {
        name: 'copy-manifest',
        writeBundle() {
            const manifestSource = path.resolve(__dirname, 'assets/.vite/manifest.json');
            const manifestDest = path.resolve(__dirname, 'assets/manifest.json');

            if (existsSync(manifestSource)) {
                copyFileSync(manifestSource, manifestDest);
            }
        }
    };
};

const inputs = [
    'resources/admin/main.js',
    'resources/admin/js/gutenberg-alt-button.js',
    'resources/admin/js/block-editor-common.js',
    'resources/admin/js/kadence-alt-button.js',
    'resources/admin/js/spectra-alt-button.js',
    'resources/admin/js/media-button.js',
    'resources/scss/index.css',
];

export default defineConfig({
    base: process.env.NODE_ENV === 'production' ? './' : '/',
    assetsInclude: ['**/*.svg'],
    plugins: [
        vue(),
        tailwindcss(),
        liveReload([
            `${__dirname}/**/*.php`,
        ]),
        copyManifestPlugin(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
                silenceDeprecations: ['legacy-js-api', 'import'],
            },
        },
    },
    build: {
        manifest: true,
        outDir: 'assets',
        emptyOutDir: true,
        rolldownOptions: {
            input: inputs,
            output: {
                chunkFileNames: (chunk) => `${safeOutputName(chunk.name)}.[hash].js`,
                entryFileNames: (chunk) => `${safeOutputName(chunk.name)}.[hash].js`,
                assetFileNames: (asset) => {
                    const orig = (asset.names && asset.names[0]) || asset.name || 'asset';
                    const dot = orig.lastIndexOf('.');
                    const base = dot > 0 ? orig.slice(0, dot) : orig;
                    return `${safeOutputName(base)}.[hash].[ext]`;
                },
            },
        },
    },
    resolve: {
        alias: {
            'vue': 'vue/dist/vue.esm-bundler.js',
            '@': path.resolve(__dirname, 'resources/admin'),
        },
        extensions: ['.js', '.vue', '.json', '.css', '.scss'],
    },
    server: {
        port: 4000,
        strictPort: true,
        hmr: {
            port: 4000,
            host: 'localhost',
            protocol: 'ws',
        },
        cors: {
            origin: '*',
            methods: ['GET'],
            allowedHeaders: ['Content-Type', 'Authorization'],
        },
    },
});
