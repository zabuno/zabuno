import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MenuScreenActions } from './MenuScreenActions';

/**
 * MENÜ EKRANININ ÜST ŞERİDİ — kanonik kaynak
 * `docs/reference/panel-v3/panel.dc.html`, `data-screen-label="Menüler"`.
 *
 * BU DOSYA BİR SÖZLEŞMEYİ DEĞİŞTİRİYOR — NEDENİ BURADA YAZILI.
 *
 * Önceki hâli, "menü hapları ÇİZİLMEZ" kararını koruyordu ve o kararın
 * gerekçesi veri modeliydi: `menus.location_id` UNIQUE'ti, şube başına tek
 * menü vardı ve hapları çizmek tıklanınca hiçbir şey yapmayan üç düğme
 * koymak olurdu.
 *
 * **Sahip 2026-09-05'te açıkça soruldu ve "çoklu menü YAPILSIN, saat bazlı
 * geçişli" dedi** (`docs/109-PANEL-V3.md` §7.1). Kilit gevşetildi; haplar
 * artık gerçek bir veri modeline dayanıyor, bu yüzden çizilmeleri gerekiyor
 * ve bu dosya onların GERÇEKTEN çalıştığını korur.
 *
 * Değişmeyen şey: hap sahte olamaz. Basıldığında menü DEĞİŞMELİ, ipucu
 * gerçek veriden gelmeli ve "şimdi açık" işareti yalnız renkle
 * anlatılmamalı.
 */

const MENUS = [
    { id: 1, name: 'Ana menü', hint: '11:00–07:00', isSelected: true, isServingNow: false },
    { id: 2, name: 'Kahvaltı', hint: '07:00–11:00', isSelected: false, isServingNow: true },
    { id: 3, name: 'Ramazan', hint: 'kapalı', isSelected: false, isServingNow: false },
];

function renderActions(overrides: Partial<Parameters<typeof MenuScreenActions>[0]> = {}) {
    const onPhotoImport = vi.fn();
    const onCsv = vi.fn();
    const onPreviewAndPublish = vi.fn();
    const onAddProduct = vi.fn();
    const onSelectMenu = vi.fn();
    const onAddMenu = vi.fn();
    const onEditMenu = vi.fn();

    render(
        <MenuScreenActions
            label="Menü eylemleri"
            menusLabel="Menüler"
            menus={MENUS}
            onSelectMenu={onSelectMenu}
            addMenuLabel="Yeni menü"
            onAddMenu={onAddMenu}
            editMenuLabel="Menüyü düzenle"
            onEditMenu={onEditMenu}
            servingNowLabel="şimdi açık"
            photoImport={{ kind: 'available', label: 'Fotoğraftan aktar', onClick: onPhotoImport }}
            csvLabel="CSV"
            onCsv={onCsv}
            previewAndPublishLabel="Önizle ve yayınla"
            onPreviewAndPublish={onPreviewAndPublish}
            addProductLabel="Ürün ekle"
            onAddProduct={onAddProduct}
            {...overrides}
        />,
    );

    return {
        onPhotoImport,
        onCsv,
        onPreviewAndPublish,
        onAddProduct,
        onSelectMenu,
        onAddMenu,
        onEditMenu,
    };
}

describe('MenuScreenActions', () => {
    afterEach(() => {
        cleanup();
    });

    it('dört eylemi kaynaktaki SIRAYLA, tek bir grupta çizer', async () => {
        const user = userEvent.setup();
        const { onPhotoImport, onCsv, onPreviewAndPublish, onAddProduct } = renderActions();

        const group = screen.getByRole('group', { name: 'Menü eylemleri' });
        const labels = within(group)
            .getAllByRole('button')
            .map((button) => button.textContent?.trim());

        /*
            SIRA RASTGELE DEĞİL: soldan sağa "çok iş → az iş" gider. Bütün
            bir menüyü getirmek (fotoğraf, CSV) en solda; tek bir ürün
            eklemek en sağda ve tek dolgulu düğme odur.
        */
        expect(labels).toEqual(['Fotoğraftan aktar', 'CSV', 'Önizle ve yayınla', 'Ürün ekle']);

        await user.click(within(group).getByRole('button', { name: 'Fotoğraftan aktar' }));
        await user.click(within(group).getByRole('button', { name: 'CSV' }));
        await user.click(within(group).getByRole('button', { name: 'Önizle ve yayınla' }));
        await user.click(within(group).getByRole('button', { name: 'Ürün ekle' }));

        expect(onPhotoImport).toHaveBeenCalledTimes(1);
        expect(onCsv).toHaveBeenCalledTimes(1);
        expect(onPreviewAndPublish).toHaveBeenCalledTimes(1);
        expect(onAddProduct).toHaveBeenCalledTimes(1);
    });

    it('AI kapalıyken "Fotoğraftan aktar" ÇİZİLMEZ, yerine sebebi yazılır', () => {
        renderActions({
            photoImport: {
                kind: 'blocked',
                reason: 'Bu ayki AI bütçesi bitti. Geri kalan her şey çalışmaya devam eder.',
            },
        });

        /*
            `docs/97` R9 / AIV-07: çalışmayan bir eylem GÖSTERİLMEZ ama
            yerinde bir boşluk da bırakılmaz. Üç sebep üç ayrı çözüme gider
            (yönetici açar / bütçe artar / sağlayıcı anahtarı girilir).
        */
        expect(screen.queryByRole('button', { name: 'Fotoğraftan aktar' })).toBeNull();
        expect(
            screen.getByText('Bu ayki AI bütçesi bitti. Geri kalan her şey çalışmaya devam eder.'),
        ).toBeTruthy();

        expect(screen.getByRole('button', { name: 'CSV' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Ürün ekle' })).toBeTruthy();
    });

    it('şubenin BÜTÜN menülerini hap olarak, adı ve saat ipucuyla çizer', () => {
        renderActions();

        const pills = screen.getByRole('group', { name: 'Menüler' });

        expect(within(pills).getByRole('button', { name: /Ana menü/ }).textContent).toContain(
            '11:00–07:00',
        );
        expect(within(pills).getByRole('button', { name: /Kahvaltı/ }).textContent).toContain(
            '07:00–11:00',
        );
        // "Ramazan kapalı" — kaynağın üçüncü hapı. Kapatılmış bir menü de
        // ekranda durur: gelecek yıl geri gelecek ve altmış ürünü yeniden
        // yazmak kimsenin işine yaramaz.
        expect(within(pills).getByRole('button', { name: /Ramazan/ }).textContent).toContain(
            'kapalı',
        );
    });

    it('hapa basmak menü seçimini BİLDİRİR — sahte bir düğme değildir', async () => {
        const user = userEvent.setup();
        const { onSelectMenu } = renderActions();

        await user.click(screen.getByRole('button', { name: /Kahvaltı/ }));

        /*
            Kaynağın kendi hapları tıklandığında yalnız bir bildirim
            gösteriyor, kategori ve ürün listesini değiştirmiyordu. Buradaki
            haplar gerçekten menü değiştirir; bu çağrı olmasaydı sahip
            "Kahvaltı"ya basar, ekranda hâlâ akşam menüsünü görür ve
            eklediği ürünün hangi menüye gittiğini bilemezdi.
        */
        expect(onSelectMenu).toHaveBeenCalledWith(2);
    });

    it('seçili hap ekran okuyucuya da bildirilir ve başlık olmaya devam eder', () => {
        renderActions();

        const selected = screen.getByRole('button', { name: /Ana menü/ });
        expect(selected.getAttribute('aria-pressed')).toBe('true');
        expect(screen.getByRole('button', { name: /Kahvaltı/ }).getAttribute('aria-pressed')).toBe(
            'false',
        );

        // Seçili menünün adı ekranın başlığıdır; hap olması bunu
        // değiştirmez.
        expect(screen.getByRole('heading', { level: 2 }).textContent).toContain('Ana menü');
    });

    it('"şimdi açık" durumu yalnız renkle değil KELİMEYLE anlatılır', () => {
        renderActions();

        /*
            Misafirin o an gördüğü menü hangisi? Bunu yalnız bir çerçeve
            rengiyle söylemek, rengi ayırt edemeyen bir sahibi ekranda
            cevapsız bırakırdı. Kelime hapın içindedir.
        */
        const serving = screen.getByRole('button', { name: /Kahvaltı/ });
        expect(serving.textContent).toContain('şimdi açık');

        expect(screen.getByRole('button', { name: /Ana menü/ }).textContent).not.toContain(
            'şimdi açık',
        );
    });

    it('yeni menü ve menü düzenleme yolları hap sırasındadır', async () => {
        const user = userEvent.setup();
        const { onAddMenu, onEditMenu } = renderActions();

        await user.click(screen.getByRole('button', { name: 'Yeni menü' }));
        expect(onAddMenu).toHaveBeenCalledTimes(1);

        await user.click(screen.getByRole('button', { name: 'Menüyü düzenle' }));
        // Düzenleme SEÇİLİ menüye uygulanır: sahibin baktığı menü ile
        // düzenlediği menü ayrışırsa yanlış menünün saatini değiştirir.
        expect(onEditMenu).toHaveBeenCalledWith(1);
    });

    it('haplar rounded-full değil rounded-pill kullanır ve yarı-kalın taşımaz', () => {
        renderActions();

        const pill = screen.getByRole('button', { name: /Kahvaltı/ });
        expect(pill.className).toContain('rounded-pill');
        expect(pill.className).not.toContain('rounded-full');
        expect(pill.className).not.toContain('font-semibold');
    });

    it('birincil eylem 700 ağırlık taşır — yarı-kalın (font-semibold) yasaktır', () => {
        renderActions();

        /*
            Görsel sözleşme: ağırlık 400/500/700. `font-semibold` (600) ara
            bir basamaktır ve ekranda 500 ile 700 arasında üçüncü bir
            hiyerarşi kademesi açar.
        */
        const primary = screen.getByRole('button', { name: 'Ürün ekle' });
        expect(primary.className).toContain('font-bold');
        expect(primary.className).not.toContain('font-semibold');
    });
});
