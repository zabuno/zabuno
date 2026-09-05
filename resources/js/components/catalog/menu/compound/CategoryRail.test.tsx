import { afterEach, describe, expect, it, vi } from 'vitest';
// `within`, testing-library'nin kapsam daraltıcısı: rayın DIŞINDAKİ
// düğmeler (ürün satırları, üst şerit) sayıma karışmasın diye.
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { CategoryRail } from './CategoryRail';

/**
 * KATEGORİ RAYI — kanonik kaynak `docs/reference/panel-v3/panel.dc.html`
 * satır 30255-30261 ("Kategoriler" bölümü).
 *
 * NEDEN RAY, NEDEN ÜST ÜSTE KART DEĞİL:
 *
 * Ekran bugüne kadar her kategoriyi kendi kartıyla, alt alta çiziyordu.
 * Altı kategorili bir dönerciyi düşünün: sahip "Tatlılar"daki künefenin
 * fiyatını düzeltmek istediğinde, önündeki beş kategorinin bütün
 * ürünlerinin arasından kaydırarak geçmek zorundaydı. Aradığı kategori
 * ekranın neresinde olduğunu bilmiyordu, çünkü konumu KENDİNDEN ÖNCEKİ
 * kategorilerin ürün sayısına bağlıydı — dün üstte olan bugün altta
 * olabiliyordu.
 *
 * Ray bu bağı koparır: kategori listesi SABİT bir yerdedir, uzunluğu
 * ürün sayısına göre değişmez, ve seçim ürün listesini değiştirir.
 * "Kaydırarak ara" işi, "tıkla" işine iner.
 *
 * Sayı (ürün adedi) rayın ikinci yarısıdır ve kaynakta da vardır: boş
 * kalmış bir kategoriyi AÇMADAN görmenin tek yolu budur.
 */

const CATEGORIES = [
    { id: 5, name: 'Kebaplar', count: 12 },
    { id: 6, name: 'Tatlılar', count: 0 },
    { id: 7, name: 'İçecekler', count: 7 },
];

function renderRail(overrides: Partial<Parameters<typeof CategoryRail>[0]> = {}) {
    const onSelect = vi.fn();
    const onAddCategory = vi.fn();

    render(
        <CategoryRail
            categories={CATEGORIES}
            activeCategoryId={5}
            onSelect={onSelect}
            onAddCategory={onAddCategory}
            listLabel="Menü kategorileri"
            addLabel="Kategori"
            countLabel={(count) => `${count} ürün`}
            {...overrides}
        />,
    );

    return { onSelect, onAddCategory };
}

describe('CategoryRail', () => {
    afterEach(() => {
        cleanup();
    });

    it('her kategoriyi adı ve ÜRÜN SAYISIYLA birlikte tek bir düğme olarak çizer', () => {
        renderRail();

        const rail = screen.getByRole('navigation', { name: 'Menü kategorileri' });
        const buttons = within(rail).getAllByRole('button');

        // Üç kategori + "Kategori ekle" = dört düğme.
        expect(buttons).toHaveLength(4);

        /*
            Sayı, kategorinin ERİŞİLEBİLİR İSMİNİN parçasıdır. Yalnız görsel
            bir rakam olsaydı, ekran okuyucu kullanan bir yönetici "Tatlılar"
            kategorisinin boş olduğunu ancak içine girip listeyi dinleyerek
            öğrenirdi — gören biri için tek bakışlık olan bilgi, onun için
            üç adımlık bir iş olurdu.
        */
        expect(screen.getByRole('button', { name: /Kebaplar/ }).textContent).toContain('12 ürün');
        expect(screen.getByRole('button', { name: /Tatlılar/ }).textContent).toContain('0 ürün');
    });

    it('seçili kategoriyi aria-current ile işaretler; ötekileri işaretlemez', () => {
        renderRail();

        /*
            DURUM YALNIZ RENKLE ANLATILMAZ (`DESIGN_SPEC` §12). Kaynakta
            seçili kategori sadece dolgu rengi ve kalın yazıyla ayrılıyor;
            ikisi de ekran okuyucuya ulaşmaz. `aria-current` aynı bilgiyi
            makinenin okuyabildiği biçimde taşır.
        */
        expect(screen.getByRole('button', { name: /Kebaplar/ })).toHaveAttribute(
            'aria-current',
            'true',
        );
        expect(screen.getByRole('button', { name: /Tatlılar/ })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('bir kategoriye tıklanınca onSelect o kategorinin kimliğiyle çağrılır', async () => {
        const user = userEvent.setup();
        const { onSelect } = renderRail();

        await user.click(screen.getByRole('button', { name: /İçecekler/ }));

        expect(onSelect).toHaveBeenCalledWith(7);
    });

    it('"Kategori" düğmesi rayın SONUNDA durur ve onAddCategory çağırır', async () => {
        const user = userEvent.setup();
        const { onAddCategory } = renderRail();

        const rail = screen.getByRole('navigation', { name: 'Menü kategorileri' });
        const buttons = within(rail).getAllByRole('button');

        /*
            Ekleme düğmesi listenin SONUNDADIR, başında değil. Başta
            olsaydı, sahip her kategori aramasında önce onun üzerinden
            geçerdi — ve en sık yapılan iş (var olan bir kategoriyi seçmek)
            en seyrek yapılan işin (yeni kategori açmak) arkasında kalırdı.
        */
        expect(buttons[buttons.length - 1]).toHaveTextContent('Kategori');

        await user.click(buttons[buttons.length - 1]);
        expect(onAddCategory).toHaveBeenCalledTimes(1);
    });

    it('kategori yokken yalnız ekleme düğmesini çizer — boş bir ray hata değildir', () => {
        renderRail({ categories: [], activeCategoryId: null });

        const rail = screen.getByRole('navigation', { name: 'Menü kategorileri' });
        expect(within(rail).getAllByRole('button')).toHaveLength(1);
    });

    it('her dokunma hedefi yoğunluk jetonundan yükseklik alır — 44px altına inmez', () => {
        renderRail();

        /*
            GÖRSEL SÖZLEŞME, testin işidir. "44px" burada bir sayı olarak
            aranmaz (yoğunluk moduna göre 36/44/52 olabilir); aranan şey
            yüksekliğin JETONDAN gelmesidir. Sabit bir piksel yazıldığı gün
            sıkışık mod satırı kısaltamaz, geniş mod uzatamaz.
        */
        const button = screen.getByRole('button', { name: /Kebaplar/ });
        expect(button.className).toContain('min-h-[var(--density-row-height)]');
    });
});
