import pluginVue from 'eslint-plugin-vue';

export default [
    // Vue 3 recommended rules
    ...pluginVue.configs['flat/recommended'],

    {
        files: ['resources/**/*.{js,vue}'],
        rules: {
            // Prevent common bugs
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_|^e$|^error$|^err$', caughtErrors: 'none' }],
            'no-undef': 'error',
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'no-debugger': 'error',
            'no-alert': 'warn',
            'no-eval': 'error',
            'no-implied-eval': 'error',
            'eqeqeq': ['warn', 'always'],

            // Vue-specific rules relaxed for existing code
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn',
            'vue/require-default-prop': 'off',
            'vue/no-mutating-props': 'error',
            'vue/no-unused-vars': 'warn',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/html-closing-bracket-spacing': 'off',
            'vue/first-attribute-linebreak': 'off',
            'vue/attributes-order': 'off',
            'vue/multiline-html-element-content-newline': 'off',
        },
        languageOptions: {
            globals: {
                // Browser globals
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                setInterval: 'readonly',
                clearInterval: 'readonly',
                fetch: 'readonly',
                FormData: 'readonly',
                URLSearchParams: 'readonly',
                URL: 'readonly',
                AbortController: 'readonly',
                navigator: 'readonly',
                alert: 'readonly',
                confirm: 'readonly',
                MutationObserver: 'readonly',
                Event: 'readonly',
                CustomEvent: 'readonly',
                // WordPress globals
                wp: 'readonly',
            },
        },
    },

    // Node.js config files
    {
        files: ['resources/env.config.js'],
        languageOptions: {
            globals: {
                process: 'readonly',
                console: 'readonly',
                __dirname: 'readonly',
            },
        },
        rules: {
            'no-console': 'off',
        },
    },

    {
        ignores: [
            'node_modules/',
            'assets/',
            'build/',
            'vendor/',
        ],
    },
];
