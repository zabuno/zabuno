import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * MENÜLER EKRANININ YENİLENMİŞ DÜZENİ — kanonik kaynak
 * `docs/reference/panel-v3/panel.dc.html`, `data-screen-label="Menüler"`
 * (satır 30198-30283).
 *
 * Bu dosya üç şeyi birden kanıtlar, çünkü üçü de aynı düzen kararının
 * parçası ve ayrı ayrı doğru olup birlikte yanlış olabilirler:
 *
 * 1. KATEGORİ RAYI ürün listesini gerçekten FİLTRELER. Rayı çizip
 *    altında yine bütün kategorileri göstermek, ekrana bir gezinti
 *    çubuğu eklemek ama hiçbir yere götürmemek olurdu.
 * 2. EYLEM ŞERİDİ başlıkla aynı sıradadır ve içindeki eylemler gerçek
 *    işleri açar (CSV kutusu, fotoğraf sihirbazı).
 * 3. AÇIKLAMASI OLMAYAN ürün satırda uyarı taşır — kaynağın `p.meta`
 *    alanının karşılığı.
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

function menuTree() {
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
                name: 'Kebaplar',
                position: 1,
                menuItems: [
                    {
                        id: 9,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Adana',
                        priceMinorAmount: 25000,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: true,
                        // Açıklaması YOK — satırda uyarı bekliyoruz.
                        description: null,
                    },
                ],
            },
            {
                id: 6,
                menuId: MENU_ID,
                name: 'Tatlılar',
                position: 2,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 6,
                        productId: 11,
                        productName: 'Künefe',
                        priceMinorAmount: 18000,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: true,
                        // Açıklaması VAR — uyarı çıkmamalı.
                        description: 'Antep fıstıklı, tel kadayıflı.',
                    },
                ],
            },
        ],
    };
}

function stubFetch() {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const u = String(url);
            if (u === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (u.endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (u.endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, menuTree());
            }
            return jsonResponse(200, { ok: true });
        }),
    );
}

async function renderWorkspace() {
    const goToSection = vi.fn();
    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{
            workspaceId: number;
            locationId: number;
            onNavigateToSection?: (section: string) => void;
        }>;
    };
    render(
        <MenuCatalogWorkspace
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            onNavigateToSection={goToSection}
        />,
    );
    await screen.findByRole('navigation', { name: 'Menu categories' });
    return { goToSection };
}

describe('Menüler ekranı — kategori rayı ve eylem şeridi', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('rayı çizer ve YALNIZ seçili kategorinin ürünlerini gösterir', async () => {
        stubFetch();
        await renderWorkspace();

        const rail = screen.getByRole('navigation', { name: 'Menu categories' });

        // Ray her iki kategoriyi de sayısıyla taşır.
        expect(within(rail).getByRole('button', { name: /Kebaplar/ }).textContent).toContain(
            '1 products',
        );
        expect(within(rail).getByRole('button', { name: /Tatlılar/ })).toBeTruthy();

        /*
            AMA ÜRÜN LİSTESİ TEKTİR. Önceki hâlde iki kategori iki ayrı
            kart olarak alt alta duruyordu ve sahip künefeye ulaşmak için
            kebapların hepsini geçmek zorundaydı. Ray varken bile bütün
            kategorileri çizmeye devam etseydik, rayı ekrana koymuş ama
            hiçbir işe yaramamış olurduk.
        */
        expect(screen.getByText('Adana')).toBeTruthy();
        expect(screen.queryByText('Künefe')).toBeNull();
    });

    it('raydaki başka bir kategoriye tıklamak ürün listesini DEĞİŞTİRİR', async () => {
        stubFetch();
        await renderWorkspace();
        const user = userEvent.setup();

        const rail = screen.getByRole('navigation', { name: 'Menu categories' });
        await user.click(within(rail).getByRole('button', { name: /Tatlılar/ }));

        expect(await screen.findByText('Künefe')).toBeTruthy();
        expect(screen.queryByText('Adana')).toBeNull();

        // Seçim ekran okuyucuya da bildirilir, yalnız renkle değil.
        expect(within(rail).getByRole('button', { name: /Tatlılar/ })).toHaveAttribute(
            'aria-current',
            'true',
        );
    });

    it('eylem şeridi CSV ve "Ürün ekle" düğmelerini AÇIK ÇİZER — kapalı bir kutunun içinde değil', async () => {
        stubFetch();
        await renderWorkspace();

        /*
            Bu iki düğme daha önce "Bring in a whole menu" başlıklı kapalı
            bir `<details>` içindeydi. Kapalı bir kutu, orada bir yol
            olduğunu SÖYLEMEZ: basılı menüsünü CSV'den aktarabileceğini
            bilmeyen sahip altmış ürünü tek tek yazmaya başlıyordu.
        */
        const actions = screen.getByRole('group', { name: 'Menu actions' });
        expect(within(actions).getByRole('button', { name: 'CSV' })).toBeVisible();
        expect(within(actions).getByRole('button', { name: 'Add product' })).toBeVisible();

        /*
            "Önizle ve yayınla" bu şeritte DEĞİL, bir satır yukarıda —
            sayfa başlığının yanındaki eylem yuvasında (`MenuPage`).
            Gerekçesi orada yazılı: konum kaynağınkiyle aynı, ve buraya
            taşınsaydı menü sunucudan gelene kadar yayınlama yolu ekranda
            hiç görünmezdi.
        */
        expect(within(actions).queryByRole('button', { name: /publish/i })).toBeNull();
    });

    it('menü listesi gelmese bile açık menüyü tek hap olarak çizer', async () => {
        stubFetch();
        await renderWorkspace();

        /*
            BU BEKLENTİ 2026-09-05'te DEĞİŞTİ.

            Önceki hâli tek bir KİMLİK ÇİPİ (`role="status"`) arıyordu ve
            gerekçesi veri modeliydi: `menus.location_id` UNIQUE'ti, şube
            başına tek menü vardı. Sahip o gün açıkça soruldu ve "çoklu
            menü YAPILSIN, saat bazlı geçişli" dedi (`docs/109` §7.1);
            kilit gevşetildi ve çipin yerini menü hapları aldı.

            Burada hap listesini getiren yol taklit edilmiyor, yani liste
            boş dönüyor. Beklenen davranış: ekran yine de AÇIK OLAN
            menüyü tek hap olarak çizer. Boş bir hap sırası, menüsü olan
            bir sahibe "menün yok" derdi.
        */
        const pills = screen.getByRole('group', { name: 'Menus at this location' });
        const pill = within(pills).getByRole('button', { name: /Ana Menü/ });

        expect(pill.getAttribute('aria-pressed')).toBe('true');
        // Ad ekranın başlığı olmaya devam eder.
        expect(screen.getByRole('heading', { name: 'Ana Menü' })).toBeTruthy();
        // Yeni menü açma yolu hap sırasındadır (kaynağın "+" düğmesi).
        expect(within(pills).getByRole('button', { name: 'New menu' })).toBeTruthy();
    });

    it('açıklaması olmayan ürün satırında uyarı taşır; açıklaması olan taşımaz', async () => {
        stubFetch();
        await renderWorkspace();
        const user = userEvent.setup();

        /*
            Kaynakta bu, ürün satırının `p.meta` alanıdır: "açıklama yok".
            Neden satırda, ayrıntı çekmecesinde değil: misafirin ürünün
            altında okuyacağı cümle eksikse, sahip bunu ancak ürünü teker
            teker açarak öğrenebilirdi. Altmış ürünlü bir menüde bu altmış
            tıklama demektir; satırdaki tek kelime aynı işi bir bakışta
            yapar.
        */
        expect(screen.getByText('No description')).toBeTruthy();

        const rail = screen.getByRole('navigation', { name: 'Menu categories' });
        await user.click(within(rail).getByRole('button', { name: /Tatlılar/ }));

        await screen.findByText('Künefe');
        expect(screen.queryByText('No description')).toBeNull();
    });
});
