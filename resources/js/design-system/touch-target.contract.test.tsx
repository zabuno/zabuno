import { describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { render, screen } from '@testing-library/react';

import { IconButton } from '../components/catalog/navigation/micro/IconButton';
import { CloseButton } from '../components/catalog/overlays/micro/CloseButton';
import { BrandMark } from '../components/catalog/layout/micro/BrandMark';
import { Tabs } from '../components/catalog/navigation/compound/Tabs';
import { CheckboxField } from '../components/catalog/forms/compound/CheckboxField';
import { Input } from '../components/storybook-demo/micro/Input';

/**
 * DS-TOUCH-TARGET — hedef ölçeği ile ÖLÜ ALAN ölçeği ayrı iki köktür.
 *
 * ═══ NEDEN VAR ═══
 *
 * `docs/117` 320 pikselde ölçtü: metin girdisi 42, ikon düğmesi 36, çekmece
 * kapatma 32, marka bağlantısı 24 pikseldi — 49 hikâyede. Aynı ölçüm ekranın
 * %43'ünün iç içe dolgulara gittiğini de gösterdi. İki bulgu tek bir kök
 * hatanın iki yüzü: hedef boyu ile boşluk aynı ölçekten besleniyordu ve dar
 * ekranda İKİSİ BİRDEN büyüyordu.
 *
 * Sahibin kararı: **büyük hedef + sıkı boşluk.** Font küçülmez, hedef
 * küçülmez; küçülen tek şey hedefler arasındaki ölü alandır.
 *
 * ═══ NE ÖLÇÜYOR, NE ÖLÇMÜYOR ═══
 *
 * Burası jsdom: hiçbir kutunun boyu yoktur, yani "44 piksel mi" sorusu
 * BURADA sorulamaz. Onu `scripts/mobile-ux-audit` gerçek Chrome'da 320×568'de
 * soruyor ve paketin asıl kabulü oradadır.
 *
 * Bu dosyanın işi başka ve tamamlayıcı: **bağlantıyı dondurmak.** Bir bileşen
 * hedef jetonunu bırakıp ham bir piksele (`h-9`, `px-3 py-2`) dönerse tarayıcı
 * kapısı bunu ancak bir sonraki tam ölçümde görür; burası aynı gün kırılır.
 * Düzenek gerçek bileşenden sapamaz: sınıflar elle yazılmış bir kopyadan
 * değil, GERÇEK render'dan okunur (`AdminShell.contract.test.tsx` deseni).
 *
 * Requirement IDs: DS-TOUCH-TARGET-01 … DS-TOUCH-TARGET-09.
 */

const CSS = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../css/app.css');

/** Dokunma hedefinin kökü. Bileşen ham 44 yazmaz, bunu okur. */
const TARGET = 'var(--density-hit-area-min)';

/** Kontrol geometrisi — hedeften TÜREYEN, altına inemeyen yükseklik. */
const CONTROL = 'var(--control-height)';

function classesOf(element: Element | null): Set<string> {
    expect(element, 'öğe gerçek DOM içinde bulunamadı').not.toBeNull();

    return new Set(element!.className.split(/\s+/).filter(Boolean));
}

function expectClass(element: Element | null, wanted: string, requirement: string) {
    expect(
        classesOf(element).has(wanted),
        `${requirement}: \`${wanted}\` taşımıyor — hedef jetonu bırakılmış.`,
    ).toBe(true);
}

describe('DS-TOUCH-TARGET — jeton kökü hedefi ve ölü alanı ayırır', () => {
    it('hedef ölçeği 44 pikselde sabittir ve ölü alan ölçeğinden BESLENMEZ', () => {
        const css = readFileSync(CSS, 'utf8');

        expect(
            css,
            'DS-TOUCH-TARGET-01: `--density-hit-area-min` 44px değil; hedef kökü kaybolmuş.',
        ).toMatch(/--density-hit-area-min:\s*44px/);

        // Kontrol yüksekliği hedefin ALTINA inemez — `max()` bunu garanti eder.
        expect(
            css,
            'DS-TOUCH-TARGET-02: `--control-height` artık hedef tabanını `max()` ile taşımıyor.',
        ).toMatch(
            /--control-height:\s*max\(var\(--density-row-height\),\s*var\(--density-hit-area-min\)\)/,
        );

        /*
            AYRIM BURADA DURUYOR: ölü alan ölçeği (`--space-fluid-*`) hedef
            ölçeğine sızmamalı. Sızarsa dar ekranda boşluk daralırken hedef de
            daralır — düzeltilen kusurun aynısı geri gelir.
        */
        const targetLines = css
            .split('\n')
            .filter((line) =>
                /--(density-row-height|density-hit-area-min|control-height):/.test(line),
            );

        expect(
            targetLines.length,
            'DS-TOUCH-TARGET-03: hedef jetonları bulunamadı.',
        ).toBeGreaterThan(0);

        for (const line of targetLines) {
            expect(
                line.includes('space-fluid'),
                `DS-TOUCH-TARGET-03: hedef jetonu ölü alan ölçeğinden besleniyor → ${line.trim()}`,
            ).toBe(false);
        }
    });

    it('ölü alan ölçeği DAR EKRANI taban alır, geniş ekranı zenginleştirme', () => {
        const css = readFileSync(CSS, 'utf8');

        /*
            Eski tabanlar masaüstü ölçüsüydü (12/16/24px) ve `clamp()` alt
            sınırı 320 pikselde ZATEN devredeydi: ölçek dar ekranda hiç
            daralmıyordu. Yeni tabanlar 8/12/16px — tavanlar değişmedi, yani
            masaüstü görünümü korunur.
        */
        const floors: Array<[string, string, string]> = [
            ['sm', '0.5rem', '1rem'],
            ['md', '0.75rem', '1.5rem'],
            ['lg', '1rem', '2.5rem'],
        ];

        for (const [step, floor, ceiling] of floors) {
            const rule = new RegExp(
                `--space-fluid-${step}:\\s*clamp\\(${floor.replace('.', '\\.')},[^,]+,\\s*${ceiling.replace('.', '\\.')}\\)`,
            );

            expect(
                css,
                `DS-TOUCH-TARGET-04: --space-fluid-${step} dar ekran tabanı ${floor} / geniş ekran tavanı ${ceiling} değil.`,
            ).toMatch(rule);
        }
    });
});

describe('DS-TOUCH-TARGET — bileşenler hedef jetonunu gerçekten taşıyor', () => {
    it('ikon düğmesi: ikon büyümez, dokunma alanı hedefe bağlanır', () => {
        const { container } = render(<IconButton icon={<span>x</span>} label="Menüyü aç" />);

        expectClass(container.querySelector('button'), `size-[${TARGET}]`, 'DS-TOUCH-TARGET-05');

        const source = readFileSync(
            path.resolve(
                path.dirname(fileURLToPath(import.meta.url)),
                '../components/catalog/navigation/micro/IconButton.tsx',
            ),
            'utf8',
        );

        expect(
            /\bh-9\b|\bw-9\b/.test(source),
            'DS-TOUCH-TARGET-05: 36 pikselik ham geometri geri gelmiş.',
        ).toBe(false);
    });

    it('çekmece/diyalog kapatma düğmesi de aynı hedefi taşır', () => {
        const { container } = render(<CloseButton onClick={vi.fn()} />);

        expectClass(container.querySelector('button'), `size-[${TARGET}]`, 'DS-TOUCH-TARGET-06');
    });

    it('marka bağlantısı bir dokunma hedefidir; süs değil', () => {
        const { container } = render(<BrandMark name="Zabuno" href="#" />);

        expectClass(container.querySelector('a'), `min-h-[${TARGET}]`, 'DS-TOUCH-TARGET-07');
    });

    it('sekme düğmesi kontrol yüksekliğinden gelir, 42 pikselik dolgu toplamından değil', () => {
        render(
            <Tabs
                label="Sipariş"
                selectedKey="details"
                onChange={vi.fn()}
                items={[
                    { key: 'details', label: 'Details', panel: <p>a</p> },
                    { key: 'items', label: 'Items', panel: <p>b</p> },
                ]}
            />,
        );

        expectClass(
            screen.getByRole('tab', { name: 'Items' }),
            `min-h-[${CONTROL}]`,
            'DS-TOUCH-TARGET-08',
        );
    });

    it('metin girdisi kontrol yüksekliğine bağlıdır', () => {
        const { container } = render(<Input aria-label="Ad" />);

        expectClass(container.querySelector('input'), `min-h-[${CONTROL}]`, 'DS-TOUCH-TARGET-08');
    });

    it('onay kutusunun hedefi ETİKETİDİR ve etiket hedef boyundadır', () => {
        /*
            Kutunun kendisi 16 pikseldir ve öyle kalır: onay kutusunu parmak
            boyuna büyütmek dar ekranda satırın yarısını yerdi. Kullanıcının
            dokunduğu şey zaten etikettir — `htmlFor` ile bağlı bir etikete
            dokunmak kutuyu değiştirir. Ölçülen hedef ikisinin BİRLEŞİMİDİR
            (`scripts/mobile-ux-audit`), yani yüksekliği etiket taşır.
        */
        const { container } = render(<CheckboxField id="terms" label="Şartları kabul ediyorum" />);

        expectClass(
            container.querySelector('label[for="terms"]'),
            `min-h-[${TARGET}]`,
            'DS-TOUCH-TARGET-09',
        );
    });
});
