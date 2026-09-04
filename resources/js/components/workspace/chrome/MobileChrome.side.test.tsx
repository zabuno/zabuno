import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

import { MobileNavigationDrawer } from './MobileChrome';

/**
 * SHELL-DRAWER-SIDE-01 — FF-115.
 *
 * Sahibin bildirimi (2026-09-04): "sidebar, sağdan değil, soldan açılmalı.
 * tüm sayfalarda. shell standardı bu olsun."
 *
 * Kural keyfi değil: masaüstünde kenar çubuğu SOLDA duruyor ve onu açan düğme
 * de solda. Telefonda aynı menünün sağdan girmesi, parmağın bastığı yerle
 * panelin açıldığı yerin ters olması demek — aynı ürünün iki farklı zihinsel
 * haritası.
 *
 * Bu dosya iki şey ölçer: (1) telefon kabuğunun ÇIKTISI gerçekten soldan
 * geliyor mu, (2) kural bir dosyanın varsayılanına değil KABUK AİLESİNİN
 * tamamına yazılmış mı — yarın biri bir çağrı noktasına `position="right"`
 * eklerse burada yakalanır. `OpsShell.layout.test.tsx` aynı deseni kullanır:
 * bazı kurallar tek bir bileşenin çıktısı değil, bir yazım kuralıdır.
 */
const GROUPS = [
    {
        key: 'primary',
        label: 'Operations',
        items: [{ key: 'home', label: 'Home', href: '/home' }],
    },
];

/**
 * Sağdan açılmasına izin verilen TEK yüzey: medya denetçisi.
 *
 * Gezinme ile inceleme farklı işlerdir. Soldaki listeden seçilen bir dosyanın
 * ayrıntısı sağda açılır; liste ekranda kalır ve kullanıcı hangi dosyaya
 * baktığını görür. Listeye yeni bir satır eklemek bilinçli bir karardır.
 */
const INSPECTOR_ALLOWLIST = [
    'resources/js/components/workspace/pages/media/MediaAssetDetailDrawer.tsx',
    /*
        ÜRÜN DENETÇİSİ (FF-131). Menü çalışma alanındaki sunum/alerjen/fiyat
        düzenleyicileri sağdan açılan tek bir çekmecede toplandı.

        Kural gezinti içindir ve gerekçesi yön değil KONUM: gezinti soldan
        gelir çünkü kabuğun rayı orada durur. Denetçi ise seçili SATIRIN
        detayıdır; solda açılsaydı gezintinin üstüne biner ve kullanıcı
        "hangi menüdeydim" bağlamını kaybederdi.

        İzin listesi bir muafiyet değil bir sözleşmedir: aşağıdaki test her
        dosyanın o kararı hâlâ YAZDIĞINI doğrular.
    */
    'resources/js/components/catalog/menu/macro/MenuCatalogWorkspace.tsx',
];

function drawerCallSites(): string[] {
    const output = execFileSync('git', ['grep', '-l', '--', '<DrawerPanel', 'resources/js'], {
        encoding: 'utf8',
    });

    return output
        .split('\n')
        .filter((line) => line !== '')
        .filter((line) => !line.endsWith('.test.tsx') && !line.endsWith('.stories.tsx'));
}

describe('Kabuk standardı: gezinme çekmecesi soldan gelir', () => {
    it('telefon kabuğunun çıktısı gerçekten soldan gelir', () => {
        render(
            <MobileNavigationDrawer
                navGroups={GROUPS}
                navLabel="Restaurant admin"
                workspaceName="Zabuno"
                open
                onClose={() => {}}
            />,
        );

        expect(screen.getByRole('dialog').className).toMatch(/(^|\s)left-0(\s|$)/);
    });

    it('hiçbir gezinme yüzeyi sağdan açılmaz', () => {
        const offenders = drawerCallSites().filter((file) => {
            if (INSPECTOR_ALLOWLIST.includes(file)) {
                return false;
            }

            return /position=["']right["']/.test(readFileSync(file, 'utf8'));
        });

        expect(offenders).toEqual([]);
    });

    it('izin listesindeki denetçi gerçekten sağdan açıldığını YAZAR', () => {
        // İzin listesi bir muafiyet değil, bir sözleşmedir: dosya o kararı
        // hâlâ taşımıyorsa listeden düşmelidir.
        for (const file of INSPECTOR_ALLOWLIST) {
            expect(readFileSync(file, 'utf8')).toMatch(/position="right"/);
        }
    });
});
