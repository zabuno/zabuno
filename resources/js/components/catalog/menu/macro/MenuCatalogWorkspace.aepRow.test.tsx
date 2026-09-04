import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * MENÜ SATIRININ AEP RİTMİ — kanonik teslim paketi (`Restoran Paneli v2`,
 * `DESIGN_SPEC.md` §3).
 *
 * NEDEN AYRI BİR DOSYA: buradaki iddialar ürünün İŞ kurallarını değil,
 * satırın OKUNMA biçimini korur. İkisi aynı dosyada dururken bir sütun
 * kaydığında hangi sözleşmenin kırıldığı anlaşılmıyordu — "fiyat sağa
 * yaslıdır" ile "fiyat PUT ile kaydedilir" farklı şeylerdir ve farklı
 * sebeplerle değişirler.
 *
 * Referansın satır sözleşmesi (masaüstü sütunları):
 *   48px görsel · ad + meta · 110px fiyat · 44px bitti · 48px görünürlük ·
 *   eylemler
 *
 * Restoran sahibinin yolculuğu: akşam servisinde on beş ürüne bakıp
 * "hangisi kaç para, hangisi bitti, hangisi misafirde görünüyor" sorusunu
 * GÖZLE cevaplaması gerekir. Sütunlar hizalanmazsa bu üç soru için üç ayrı
 * tarama yapmak zorunda kalır; hizalanırsa tek bakışta okur.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function tree() {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: MENU_ID,
                name: 'Balıklar',
                position: 1,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Levrek',
                        priceMinorAmount: 42000,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                        outOfStock: false,
                        imageMediaAssetId: null,
                    },
                    {
                        id: 12,
                        categoryId: 5,
                        productId: 10,
                        productName: 'Çipura',
                        priceMinorAmount: 38000,
                        currencyCode: 'TRY',
                        position: 2,
                        isVisible: false,
                        allergens: [],
                        outOfStock: true,
                        imageMediaAssetId: 4,
                    },
                ],
            },
            {
                id: 6,
                menuId: MENU_ID,
                name: 'Tatlılar',
                position: 2,
                menuItems: [],
            },
        ],
    };
}

async function renderWorkspace() {
    const calls: { url: string; method: string; body: unknown }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            calls.push({
                url: String(url),
                method,
                body: init?.body ? JSON.parse(String(init.body)) : null,
            });

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree());
            }
            if (String(url).endsWith('/media')) {
                return jsonResponse(200, { data: [] });
            }
            if (String(url).endsWith('/visibility') && method === 'PUT') {
                return jsonResponse(200, { id: 11, isVisible: false });
            }

            return jsonResponse(200, { ok: true });
        }),
    );

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Balıklar' });

    return { calls, user: userEvent.setup() };
}

describe('menü satırı — AEP sütun ritmi', () => {
    it('her satır fotoğraf karesiyle başlar ve kare ürünün ayrıntısını açar', async () => {
        /*
            Referansta satırın İLK sütunu 48px'lik bir görsel karesidir ve
            fotoğrafı olmayan üründe bile durur (boş kare + `image` ikonu).
            Boş kare bir eksiklik göstergesidir: sahip listeye baktığında
            hangi ürünün fotoğrafı yok, saymadan görür — ve "fotoğraflı
            ürünlere 2,3× daha çok bakılıyor" cümlesinin karşılığı budur.

            Kare aynı zamanda ayrıntıya giden kapıdır: fotoğraf eklemek için
            önce taşma menüsünü açıp "Photo & text" aramak, iki adım fazladan
            karar demekti.
        */
        const { user } = await renderWorkspace();

        const thumb = screen.getByRole('button', { name: 'Open Levrek' });
        expect(thumb).toBeInTheDocument();

        await user.click(thumb);

        expect(await screen.findByRole('dialog')).toHaveAccessibleName('Levrek — details');
    });

    it('fotoğrafsız ürün satırında bunu METİNLE de söyler', async () => {
        // Boş kare tek başına yalnız GÖRSEL bir işarettir; ekran okuyucu
        // kullanan bir yönetici için hiçbir şey ifade etmez (DESIGN_SPEC §12:
        // "durum asla yalnız renkle anlatılmaz").
        await renderWorkspace();

        expect(screen.getByText('No photo')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('fiyat sağa yaslı ve eşit genişlikte rakamlarla çizilir', async () => {
        /*
            İki fiyatı karşılaştırmak ancak sağa yaslı ve `tabular-nums` ile
            mümkündür: sola yaslı bir sütunda "₺420,00" ile "₺38,00" farklı
            yerlerde biter ve göz hangisinin büyük olduğunu okuyamaz.
            Referansta fiyat ayrıca `surface-subtle` dolgulu bir düğmedir —
            "buraya tıklanır" bilgisini rengiyle değil, dolgusuyla verir.
        */
        await renderWorkspace();

        const price = screen.getByRole('button', { name: 'Edit price for Levrek' });
        expect(price).toHaveClass('justify-end');
        expect(price).toHaveClass('tabular-nums');
        expect(price).toHaveClass('bg-surface-subtle');

        vi.unstubAllGlobals();
    });

    it('tükenmiş ürün satırda rozetle anlatılır, yalnız düğmenin adıyla değil', async () => {
        /*
            Önceki hâlde "tükendi" bilgisi YALNIZ düğmenin metninden okunuyordu
            ("Back in stock" = demek ki tükenmiş). Bu bir çıkarımdır, bir
            bildirim değil; listeye bakan kişi her satırda ters mantık kurmak
            zorundaydı. Referans durumu satırın kendi meta alanında rozet
            olarak söyler.
        */
        await renderWorkspace();

        expect(screen.getByText('Sold out today')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('görünürlük satırdaki anahtarla değişir', async () => {
        /*
            Görünürlük günlük iştir ("bugün kuzu yok, gizle") ve referansta
            satırın kalıcı bir sütunudur — 48×28 bir anahtar. Taşma menüsünün
            arkasında dururken üç tık gerekiyordu ve durumu görmek için menüyü
            AÇMAK zorunluydu: kapalı bir menü hiçbir şey göstermez.
        */
        const { calls, user } = await renderWorkspace();

        const toggle = screen.getByRole('switch', { name: 'Show Levrek on the menu' });
        expect(toggle).toHaveAttribute('aria-checked', 'true');

        await user.click(toggle);

        await waitFor(() => {
            expect(
                calls.some(
                    (call) =>
                        call.url.endsWith('/menu-items/11/visibility') && call.method === 'PUT',
                ),
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });

    it('gizli ürünün satırı soluk çizilir', async () => {
        // Gizli satır silinmiş değildir, yalnız misafirde yoktur. Opaklık bu
        // farkı yeri değiştirmeden anlatır; satır listedeki sırasını korur.
        await renderWorkspace();

        const row = screen.getByRole('switch', { name: 'Show Çipura on the menu' }).closest('li');
        expect(row).toHaveClass('opacity-55');

        vi.unstubAllGlobals();
    });
});

describe('kategori satırı — AEP ray grameri', () => {
    it('kategori adının yanında ürün sayısı durur', async () => {
        // Referans rayında her kategori "ad + sayı" taşır: sahip hangi
        // kategorinin boş kaldığını listeyi açmadan görür.
        await renderWorkspace();

        expect(screen.getByText('2 products')).toBeInTheDocument();
        expect(screen.getByText('0 products')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('kategori sürükleme tutamacıyla yeniden sıralanır', async () => {
        /*
            Referansta kategori satırının başında bir sürükleme tutamacı
            (`dots-six-vertical`) vardır. Bu paket tutamacı GERÇEK yapar:
            görünüp de çalışmayan bir tutamaç, kullanıcıya olmayan bir söz
            vermektir.

            Klavye ve dokunmatik yolu KALDIRILMADI — tutamaç `aria-hidden`
            olarak fare kullanıcısına sunulur, aynı işi yapan yukarı/aşağı
            düğmeleri satırda durmaya devam eder. Sürükleme tek yol olsaydı,
            klavyeyle çalışan bir yönetici menüsünü sıralayamazdı.
        */
        const { calls } = await renderWorkspace();

        const handle = screen.getByTitle('Drag Balıklar to reorder');
        const target = screen.getByRole('heading', { name: 'Tatlılar' }).closest('li');

        fireEvent.dragStart(handle);
        fireEvent.dragOver(target as HTMLElement);
        fireEvent.drop(target as HTMLElement);

        await waitFor(() => {
            const put = calls.find((call) => call.url.endsWith(`/menu/${MENU_ID}/category-order`));
            expect(put?.body).toEqual({ categoryIds: [6, 5] });
        });

        vi.unstubAllGlobals();
    });
});

describe('ürün ayrıntı çekmecesi', () => {
    it('sunum düzenleyicisi sağdan açılan tek panelde toplanır', async () => {
        /*
            `DESIGN_SPEC` §3: ürün ayrıntısı masaüstünde SAĞDAN 460px bir
            çekmecedir. Sağ kenar kuralın istisnasıdır ve sebebi var
            (`DrawerPanel` §position): denetçi paneli açılırken soldaki liste
            ekranda kalmalı, çünkü sahip bir üründen diğerine geçerek çalışır.

            Önceki hâlde form satırın ALTINDA açılıyordu: aşağıdaki bütün
            ürünler aşağı kayıyor, sahibin bakmakta olduğu satır ekrandan
            çıkıyordu.
        */
        const { user } = await renderWorkspace();

        await user.click(screen.getByRole('button', { name: 'More actions for Levrek' }));
        await user.click(await screen.findByRole('menuitem', { name: 'Photo & text' }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveAccessibleName('Levrek — details');
        expect(screen.getByLabelText('Description')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Close' }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });
});
