import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    // `worktrees/` bu deponun ikinci bir çalışan kopyasıdır (localhost
    // çalışma zamanı); lint edilmesi hem süreyi ikiye katlar hem de ayrık bir
    // checkout hakkında düzeltilemeyen uyarılar üretir.
    /*
        Üretilmiş çıktı taranmaz.

        `storybook-static/` yerelde bir Storybook derlemesinden kalır ve
        `.gitignore`'dadır — ama eslint `.gitignore` okumaz. Sonuç: yerelde
        `npm run lint` on dokuz binden fazla hata basıyor, CI'da (temiz
        checkout) hiç basmıyordu. Kapının yerelde işe yaramaz hâle gelmesi,
        kapının olmamasından kötüdür: kimse okumadığı bir çıktıya güvenmez.
    */
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/build/**',
            'storage/**',
            'worktrees/**',
            'storybook-static/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            globals: globals.browser,
        },
        plugins: {
            'react-hooks': reactHooks,
            'react-refresh': reactRefresh,
        },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
        },
    },
    {
        files: ['**/*.test.{ts,tsx}'],
        languageOptions: {
            globals: { ...globals.browser, ...globals.node },
        },
    },
    {
        files: ['vite.config.ts'],
        languageOptions: {
            globals: globals.node,
        },
    },
    {
        // Boru hattı script'leri Node'da çalışır: tarayıcı global'leri yok,
        // Node global'leri var. Aynı kuralları tarayıcı bağlamıyla ölçmek
        // yanlış pozitif üretirdi.
        files: ['scripts/**/*.mjs'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: globals.node,
        },
    },
);
