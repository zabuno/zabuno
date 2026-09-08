/**
 * `tailwind-merge` TEK KOPYA kalır (FF-235, `docs/143` §3).
 *
 * `flowbite-react`, Tailwind 3 ve Tailwind 4 kurulumlarının ikisinde birden
 * çalışabilmek için `tailwind-merge`in iki ayrı sürümünü takma adla bağımlılık
 * listesine alır ve `helpers/tailwind-merge.js` ikisini de STATİK olarak içe
 * aktarır; hangisinin kullanılacağına çalışma zamanında karar verir. Paketleyici
 * için bu "ikisi de gerekli" demektir, ve depo kendi `cn()` yardımcısı için
 * üçüncü bir kopya taşır. Sonuç, masaüstü çalışma alanının AÇILIŞ paketinde
 * aynı kütüphanenin üç kopyasıydı: 270,5 KB kaynak, 11,13 KB gzip.
 *
 * `vite.config.ts` iki takma adı deponun kendi kopyasına yönlendirir. Bu
 * iddia, o takma adın hem YÜRÜRLÜKTE hem GÜVENLİ olduğunu ölçer. Takma ad
 * sessizce düşerse (ör. flowbite iç yapısını değiştirir) paket 11 KB büyür ve
 * bunu kimse fark etmez; ölçülmeyen bir kazanç, kazanç değildir.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';

describe('tailwind-merge tek kopya', () => {
    /*
        Takma adın YÜRÜRLÜKTE olduğunun kanıtı: üç tanımlayıcı da AYNI modül
        nesnesini verir. Vite `resolve.alias`ı test kipinde de uygular, yani
        burada ölçülen çözümleme paketlenen çözümlemenin ta kendisidir.
    */
    it('üç tanımlayıcı da aynı modüle çözülür', async () => {
        const [own, v2, v3] = await Promise.all([
            import('tailwind-merge'),
            import('tailwind-merge-v2'),
            import('tailwind-merge-v3'),
        ]);

        expect(v2).toBe(own);
        expect(v3).toBe(own);
    });

    /*
        Takma adın GÜVENLİ olduğunun kanıtı, iki koşulun birlikte tutmasıdır.

        (1) Depo Tailwind 4 kullanır. flowbite'ın yardımcısı yalnız
            `version === 3` iken v2 dalına girer; Tailwind 4'te o dal ÖLÜDÜR.
            Depo bir gün Tailwind 3'e dönerse bu iddia kırılır ve takma adın
            yeniden düşünülmesi gerektiğini söyler.
        (2) Deponun kendi kopyası tailwind-merge 3.x'tir, yani
            `tailwind-merge-v3` (3.4.0) ile aynı majör ve aynı API.
    */
    it('Tailwind 4 kullanılıyor — v2 dalı ölü', () => {
        const pkg = JSON.parse(readFileSync('package.json', 'utf8')) as {
            devDependencies: Record<string, string>;
        };

        expect(pkg.devDependencies.tailwindcss).toMatch(/^\^?4\./);
    });

    it('deponun kendi kopyası, değiştirdiği sürümle aynı majör', () => {
        const own = JSON.parse(
            readFileSync('node_modules/tailwind-merge/package.json', 'utf8'),
        ) as { version: string };

        expect(own.version.startsWith('3.')).toBe(true);
    });

    /*
        flowbite hâlâ tam olarak bu iki tanımlayıcıyı içe aktarıyor mu? Adlar
        değişirse takma ad hiçbir şeye bağlanmaz ve sessizce etkisiz kalır.
    */
    it('flowbite yardımcısı hâlâ iki takma adı da içe aktarıyor', () => {
        const helper = readFileSync(
            'node_modules/flowbite-react/dist/helpers/tailwind-merge.js',
            'utf8',
        );

        expect(helper).toContain('tailwind-merge-v2');
        expect(helper).toContain('tailwind-merge-v3');
    });

    /*
        Davranış aynı kalmalı: sınıf birleştirme hâlâ SONRAKİ sınıfı kazandırır.
        Bu, arayüzün her düğmesinin ve her kartının üzerinde durduğu kuraldır.
    */
    it('sınıf birleştirme davranışı değişmedi', async () => {
        const { twMerge } = await import('flowbite-react/helpers/tailwind-merge');

        expect(twMerge('px-2', 'px-4')).toBe('px-4');
        expect(twMerge('text-sm text-gray-500', 'text-gray-900')).toBe('text-sm text-gray-900');
        expect(twMerge('rounded-lg', 'shadow-sm')).toBe('rounded-lg shadow-sm');
    });
});
