import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import flowbiteReact from 'flowbite-react/plugin/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => ({
    plugins: [
        ...(mode === 'test'
            ? []
            : [
                  laravel({
                      input: [
                          'resources/css/app.css',
                          'resources/js/app.tsx',
                          'resources/js/auth.tsx',
                          'resources/js/workspace.tsx',
                          'resources/js/platform.tsx',
                      ],
                      refresh: true,
                  }),
              ]),
        tailwindcss(),
        react(),
        flowbiteReact(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test/setup.ts'],
        // Varsayılan 5 saniye, SOĞUK bir başlangıçta yetmiyor: Flowbite
        // eklentisi ilk çalıştırmada `.flowbite-react/class-list.json`
        // dosyasını üretir ve paralel worker'lar o üretimi beklerken en ağır
        // WorkspaceApp testleri zaman aşımına düşer. Bu bir yavaşlık
        // sorunudur, bir hata değil — sürekli entegrasyon her koşuda soğuk
        // başlar, yani bu yarış orada da vardır.
        //
        // Süreyi uzatmak hiçbir iddiayı zayıflatmaz: gerçekten asılı kalan
        // bir test yine düşer, yalnız daha geç.
        testTimeout: 20_000,
    },
}));
