import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import liveReload from 'vite-plugin-live-reload';
import path from 'path';
import { fileURLToPath } from 'url';
import { copyFileSync, existsSync } from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

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
                chunkFileNames: '[name].js',
                entryFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
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
