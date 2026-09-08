import { configDefaults, defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import flowbiteReact from 'flowbite-react/plugin/vite';
import { fileURLToPath, URL } from 'node:url';
import { execSync } from 'node:child_process';

/**
 * Paketin İÇİNE gömülen kaynak sürümü.
 *
 * Burada `git` çağırmak serbest, çünkü bu kod derleme başına BİR KEZ çalışır
 * — istek başına değil. Sunucu tarafı aynı şeyi asla böyle yapmaz
 * (app/Support/Build/GitHead.php), ve ayrım kasıtlıdır.
 *
 * Bu değerin tek işi, tarayıcıya inen JavaScript'in HANGİ kaynaktan
 * üretildiğini kendi içinde taşımasıdır. Sunucunun söylediği sürümle
 * karşılaştırıldığında, ikisinin ayrıştığı an tespit edilebilir hâle gelir;
 * aksi hâlde "eski arayüze bakıp güncel sanmak" tamamen görünmezdir.
 */
function resolveBuildRevision(): string {
    if (process.env.ZABUNO_BUILD_REVISION) {
        return process.env.ZABUNO_BUILD_REVISION;
    }

    try {
        return execSync('git rev-parse HEAD', { stdio: ['ignore', 'pipe', 'ignore'] })
            .toString()
            .trim();
    } catch {
        // Git yoksa sürüm bilinmiyor demektir. Uydurulmuş bir değer
        // döndürmek, karşılaştırmayı her zaman "eşit" yapıp dedektörü
        // sessizce işe yaramaz hâle getirirdi.
        return '';
    }
}

export default defineConfig(({ mode }) => ({
    define: {
        __ZABUNO_BUILD_REVISION__: JSON.stringify(resolveBuildRevision()),
    },
    plugins: [
        ...(mode === 'test'
            ? []
            : [
                  laravel({
                      input: [
                          'resources/css/app.css',
                          'resources/js/auth.tsx',
                          // Cihaz başına ayrı giriş: seçim SUNUCUDA yapılır
                          // (App\Support\Device\DeviceClass), tarayıcıda medya
                          // sorgusuyla değil. Ortak kod Vite tarafından paylaşılan
                          // parçaya çıkar; cihaza özgü kabuk yalnız kendi paketinde kalır.
                          'resources/js/workspace.mobile.tsx',
                          'resources/js/workspace.desktop.tsx',
                          'resources/js/platform.tsx',
                          'resources/js/engineering.tsx',
                          // KURUMSAL SİTENİN TEK BETİĞİ (`docs/138`).
                          // React DEĞİL ve React'e bağlı değil: kurumsal
                          // sayfalar sıfır React yüklemeye devam eder.
                          // Kendi giriş noktası, çünkü kabuğa değil yalnız
                          // sahne taşıyan sayfaya bağlanır.
                          'resources/js/site-motion.ts',
                      ],
                      refresh: true,
                  }),
              ]),
        tailwindcss(),
        react(),
        ...(mode === 'test' ? [] : [flowbiteReact()]),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            /*
                AYNI KÜTÜPHANENİN ÜÇ KOPYASI, ÜÇÜ DE PAKETİN İÇİNDE (FF-235).

                `flowbite-react`, hem Tailwind 3 hem Tailwind 4 kurulumlarında
                çalışabilmek için `tailwind-merge`in İKİ ayrı sürümünü birden
                takma adla bağımlılık listesine alır
                (`tailwind-merge-v2` = tailwind-merge@2.6.1,
                `tailwind-merge-v3` = tailwind-merge@3.4.0) ve
                `helpers/tailwind-merge.js` ikisini de STATİK olarak içe
                aktarır — hangisinin çalışacağına ÇALIŞMA ZAMANINDA karar
                verir. Paketleyici için bu, "ikisi de gerekli" demektir.
                Depo ayrıca kendi `cn()` yardımcısı için tailwind-merge@3.6.0
                kullanır. Toplam: masaüstü çalışma alanının AÇILIŞ paketinde
                aynı kütüphanenin üç kopyası, 270,5 KB kaynak (ölçüm:
                `docs/143` §3).

                Zabuno Tailwind 4 kullanır (`package.json` → tailwindcss ^4)
                ve `.flowbite-react/config.json` sürümü 4'tür; yani v2 dalı
                HİÇ çalışmaz ve v3 kopyası deponun kendi 3.6.0 kopyasıyla
                aynı majördür. Üç kopya tek kopyaya indirilir.

                Ölçülen etki: masaüstü kapanışı 199,65 → 188,53 KB gzip
                (−11,13 KB). Takma adı KALDIRAN bir değişiklik kapıyı
                kırar; güvenliğini `tailwind-merge-dedupe.test.ts` kanıtlar.
            */
            'tailwind-merge-v2': 'tailwind-merge',
            'tailwind-merge-v3': 'tailwind-merge',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        /**
         * İÇ İÇE WORKTREE'LER TARANMAZ.
         *
         * `worktrees/` altında bu deponun ÇALIŞAN İKİNCİ BİR KOPYASI durur
         * (localhost çalışma zamanı). Dışlanmadığında vitest onun test
         * dosyalarını da toplar ve paket, üzerinde çalışılan koda ek olarak
         * AYRIK ve ESKİ bir checkout'u ölçer.
         *
         * Ölçüldüğü hâliyle testlerin yaklaşık YARISI oradan geliyordu. Bu,
         * sayıyı şişirmekten daha kötüsünü yapar: yeşil bir paket, düzenlenen
         * kodun sınandığı anlamına gelmez hâle gelir — ki bu, docs/52'nin
         * kapatmak için yazıldığı arıza ailesinin ta kendisidir.
         */
        exclude: [...configDefaults.exclude, 'worktrees/**'],
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
