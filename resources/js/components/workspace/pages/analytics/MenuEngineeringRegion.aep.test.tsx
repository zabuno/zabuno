import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MenuEngineeringRegion } from './MenuEngineeringRegion';

/**
 * MENÜ MÜHENDİSLİĞİNİN AEP RİTMİ — kanonik teslim paketi
 * (`DESIGN_SPEC.md` §5 "Menü mühendisliği" ve "Aranıp bulunamayanlar";
 * `Restoran Paneli v2.dc.html` Insights bölümü).
 *
 * Sahibin yolculuğu: "Levrek'e 8 kişi bakmış, Hamsi'ye hiç kimse; ayrıca
 * 4 kişi 'karides güveç' aramış ve bulamamış." Bu üç bilgi ÜÇ AYRI KARARA
 * götürür — birini büyüt, birini gizle, birini menüye ekle.
 *
 * Bugünkü hâli üçünü de aynı çıplak listeye diziyor: kart sınırı yok, grup
 * başlıkları satırlardan ayrışmıyor, sayılar sona yaslanmıyor. Sahip
 * "Hamsi" satırını "en çok bakılan"ın devamı sanabiliyor — yani ekran ona
 * ürünü GİZLEMESİ gereken yerde BÜYÜTMESİ gerektiğini söylüyor.
 *
 * Teslim paketinin cevabı iki ayrı karttır: "Menümde ne işe yarıyor?" ve
 * "Aranıp bulunamayanlar". Her kartın içinde satırlar ince ayraçlı; satırın
 * kendisi kart değil.
 */
const READY_BODY = {
    state: 'ready',
    threshold: 5,
    observedViewers: 9,
    mostViewed: [{ menuItemId: 1, productName: 'Levrek', categoryName: 'Balıklar', viewers: 8 }],
    neverViewed: [{ menuItemId: 3, productName: 'Hamsi', categoryName: 'Balıklar', viewers: 0 }],
    searchesWithNoResults: [{ term: 'karides güveç', searches: 4 }],
};

function mountReady() {
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                ({
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => READY_BODY,
                }) as Response,
        ),
    );

    return render(<MenuEngineeringRegion workspaceId={7} range="30d" />);
}

function classNamesOf(root: HTMLElement): string[] {
    const all: string[] = [];
    if (root.className) all.push(String(root.className));
    root.querySelectorAll<HTMLElement>('*').forEach((element) => {
        if (typeof element.className === 'string' && element.className) {
            all.push(element.className);
        }
    });

    return all;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MenuEngineeringRegion — AEP kart grameri', () => {
    it('ürün ilgisi ile bulunamayan aramaları AYRI kartlara koyar', async () => {
        mountReady();

        const productCard = (await screen.findByText('Levrek')).closest('section');
        const searchCard = screen.getByText('karides güveç').closest('section');

        expect(productCard).not.toBeNull();
        expect(searchCard).not.toBeNull();
        /*
            İki karar, iki kart. Aynı kartta durduklarında "aranıp
            bulunamayan" satırları menüde VAR OLAN ürünlerin devamı gibi
            okunuyor — oysa onlar menüde HİÇ YOK.
        */
        expect(searchCard).not.toBe(productCard);

        expect(productCard).toHaveClass('rounded-[var(--radius-lg)]');
        expect(productCard).toHaveClass('bg-[var(--color-surface)]');
        expect(searchCard).toHaveClass('rounded-[var(--radius-lg)]');
    });

    it('grup başlıkları 700 ağırlıkta ve cümle düzenindedir', async () => {
        mountReady();

        const mostViewed = await screen.findByText('Most looked at');

        expect(mostViewed).toHaveClass('font-bold');
        expect(mostViewed.className).not.toMatch(/font-semibold/);
        expect(mostViewed.className).not.toMatch(/\buppercase\b/);
    });

    /**
     * AYRAÇ ÜSTE KONUR ve grup başlığının hemen altındaki ilk satırda
     * susturulur: başlık zaten ayırıcıdır, üstüne bir de çizgi konduğunda
     * grup başlığı kendi listesinden kopmuş görünür.
     */
    it('satırlar üstten ince ayraçlıdır ve kendileri kart değildir', async () => {
        mountReady();

        const row = (await screen.findByText('Levrek')).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('first:border-t-0');
        expect(row).not.toHaveClass('border-b');
        expect(row?.className ?? '').not.toMatch(/rounded/);
    });

    it('satır yüksekliği yoğunluk jetonundan, yatay dolgusu kart başlığından gelir', async () => {
        mountReady();

        const row = (await screen.findByText('Levrek')).closest('li');

        expect(row).toHaveClass('min-h-[var(--density-row-height)]');
        expect(row).toHaveClass('px-[var(--space-5)]');
    });

    it('sayıyı satırın sonuna yaslar ve eşit genişlikli rakamlarla çizer', async () => {
        mountReady();

        const viewers = await screen.findByText('8 visitors');

        expect(viewers).toHaveClass('tabular-nums');
        /*
            Sayı sona yaslanmazsa ürün adının uzunluğuna göre sağa sola
            kayar; alt alta iki sayıyı karşılaştırmak için göz her satırda
            sayıyı yeniden ARAMAK zorunda kalır.
        */
        expect(viewers).toHaveClass('ms-auto');
    });

    it('AEP ölçeğinin dışında sınıf taşımaz', async () => {
        const { container } = mountReady();

        await screen.findByText('Levrek');

        for (const className of classNamesOf(container)) {
            expect(className).not.toMatch(/font-semibold/);
            expect(className).not.toMatch(/\buppercase\b/);
            expect(className).not.toMatch(/rounded-full/);
        }
    });
});
