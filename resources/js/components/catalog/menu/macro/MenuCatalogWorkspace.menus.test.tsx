import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * ÇOKLU MENÜ VE SAAT BAZLI GEÇİŞ — sahibin 2026-09-05 kararı,
 * `docs/109-PANEL-V3.md` §7.1: *"çoklu menü YAPILSIN, saat bazlı geçişli"*.
 *
 * Kaynak (`panel.dc.html`, `data-screen-label="Menüler"`) üç menü hapı
 * gösteriyor: "Ana menü yayında · Kahvaltı 07–11 · Ramazan kapalı". Ama
 * kaynağın KENDİ hapları tıklandığında hiçbir şey yapmıyor — yalnız bir
 * bildirim çıkarıyor, kategori ve ürün listesi olduğu gibi kalıyor.
 *
 * Bu dosyanın işi tam olarak o farkı korumaktır: buradaki haplar SAHTE
 * DEĞİLDİR. Basıldığında sunucudan O MENÜNÜN ağacı gelir ve ekranda
 * gerçekten o menünün ürünleri durur. Aksi hâlde sahip "Kahvaltı"ya
 * basar, ekranda hâlâ akşam menüsünü görür ve eklediği ürünün hangi
 * menüye gittiğini bir daha asla bilemez.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MAIN_MENU_ID = 41;
const BREAKFAST_MENU_ID = 42;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function tree(id: number, name: string, categoryName: string, productName: string) {
    return {
        id,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name,
        state: 'active',
        categories: [
            {
                id: id * 10,
                menuId: id,
                name: categoryName,
                position: 1,
                menuItems: [
                    {
                        id: id * 100,
                        categoryId: id * 10,
                        productId: id * 100,
                        productName,
                        priceMinorAmount: 25000,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: true,
                        description: 'Bir açıklama.',
                    },
                ],
            },
        ],
    };
}

/*
    SUNUCUNUN GÖNDERDİĞİ SIRA GÜNÜN AKIŞIDIR — oluşturma sırası değil.

    Burada iki gerçek KASITLI OLARAK ayrışıyor: `sortOrder` ana menüde 0,
    kahvaltıda 1 (ana menü önce kurulmuş), ama liste kahvaltıyla başlıyor
    çünkü gün 07:00'de başlıyor. Yatıştırılmış bir kopya olsaydı — ikisi de
    aynı yöne baksaydı — ekranın kendi başına sıralayıp sıralamadığını bu
    dosya hiçbir zaman yakalayamazdı.
*/
function menuRows() {
    return {
        data: [
            {
                id: BREAKFAST_MENU_ID,
                name: 'Kahvaltı',
                state: 'active',
                sortOrder: 1,
                startsAt: '07:00',
                endsAt: '11:00',
                windows: [{ startsAt: '07:00', endsAt: '11:00' }],
                isServingNow: true,
                isAddressAnchor: false,
            },
            {
                id: MAIN_MENU_ID,
                name: 'Ana menü',
                state: 'active',
                sortOrder: 0,
                startsAt: '11:00',
                endsAt: '07:00',
                windows: [{ startsAt: '11:00', endsAt: '07:00' }],
                isServingNow: false,
                isAddressAnchor: true,
            },
        ],
    };
}

type Call = { url: string; method: string; body: unknown };

function stubFetch(calls: Call[]) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            const u = String(url);
            calls.push({
                url: u,
                method,
                body: typeof init?.body === 'string' ? JSON.parse(init.body) : null,
            });

            if (u === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (u.endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (u.endsWith(`/locations/${LOCATION_ID}/menus`) && method === 'GET') {
                return jsonResponse(200, menuRows());
            }
            if (u.endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree(MAIN_MENU_ID, 'Ana menü', 'Kebaplar', 'Adana'));
            }
            if (u.endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'POST') {
                return jsonResponse(201, {
                    id: 99,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_ID,
                    name: 'Gece',
                    state: 'draft',
                    sortOrder: 2,
                });
            }
            if (u.endsWith(`/menu/${BREAKFAST_MENU_ID}`) && method === 'GET') {
                return jsonResponse(
                    200,
                    tree(BREAKFAST_MENU_ID, 'Kahvaltı', 'Kahvaltılıklar', 'Menemen'),
                );
            }
            if (u.endsWith('/menu/99') && method === 'GET') {
                return jsonResponse(200, {
                    id: 99,
                    workspaceId: WORKSPACE_ID,
                    locationId: LOCATION_ID,
                    name: 'Gece',
                    state: 'draft',
                    categories: [],
                });
            }

            return jsonResponse(200, { ok: true });
        }),
    );
}

async function renderWorkspace() {
    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{
            workspaceId: number;
            locationId: number;
            onNavigateToSection?: (section: string) => void;
        }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

    await screen.findByRole('navigation', { name: 'Menu categories' });
}

function pills() {
    return screen.getByRole('group', { name: 'Menus at this location' });
}

describe('Menüler ekranı — çoklu menü ve saat bazlı geçiş', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('şubenin bütün menülerini, GERÇEK saat aralıklarıyla hap olarak çizer', async () => {
        stubFetch([]);
        await renderWorkspace();

        const main = await within(pills()).findByRole('button', { name: /Ana menü/ });
        const breakfast = within(pills()).getByRole('button', { name: /Kahvaltı/ });

        expect(main.textContent).toContain('11:00–07:00');
        expect(breakfast.textContent).toContain('07:00–11:00');

        /*
            "Şimdi açık" YALNIZ RENKLE anlatılmaz. Misafirin o an gördüğü
            menü hangisi sorusu, rengi ayırt edemeyen bir sahip için de
            cevaplı olmalı.
        */
        expect(breakfast.textContent).toContain('open now');
        expect(main.textContent).not.toContain('open now');
    });

    it('hapları SUNUCUNUN sırasıyla çizer; kendi sıralamasını yapmaz', async () => {
        stubFetch([]);
        await renderWorkspace();

        await within(pills()).findByRole('button', { name: /Kahvaltı/ });

        /*
            SIRALAMA TEK YERDE: SUNUCUDA.

            Ekran da sıralasaydı iki gerçek doğardı ve bir gün ayrışırlardı —
            aynı liste yarın bir başka tüketiciye (misafir yüzeyi, karekod
            sayfası) gittiğinde sıra orada başka türlü çıkardı. Bu yüzden
            ekranın işi sunucunun sırasını AYNEN çizmektir.

            `sortOrder` burada tam ters yönde duruyor; ekran ona göre bir
            sıralama yapsaydı bu bekleyiş kırılırdı.
        */
        const order = within(pills())
            .getAllByRole('button')
            // "+" ve "Menüyü düzenle" hap değildir: seçilebilir olan `aria-pressed` taşır.
            .filter((button) => button.getAttribute('aria-pressed') !== null)
            .map((pill) => pill.firstElementChild?.textContent ?? '');

        expect(order).toEqual(['Kahvaltı', 'Ana menü']);
    });

    it('hapa basmak O MENÜNÜN kategorilerini ve ürünlerini GERÇEKTEN getirir', async () => {
        const calls: Call[] = [];
        stubFetch(calls);
        await renderWorkspace();
        const user = userEvent.setup();

        // Öncül: ekranda ana menü var.
        expect(screen.getByText('Adana')).toBeTruthy();

        await user.click(await within(pills()).findByRole('button', { name: /Kahvaltı/ }));

        expect(await screen.findByText('Menemen')).toBeTruthy();
        expect(screen.queryByText('Adana')).toBeNull();

        // Sunucudan gerçekten O MENÜ istendi — ekran veriyi uydurmadı.
        expect(
            calls.some(
                (call) =>
                    call.method === 'GET' &&
                    call.url === `/api/workspaces/${WORKSPACE_ID}/menu/${BREAKFAST_MENU_ID}`,
            ),
        ).toBe(true);

        // Seçim ekran okuyucuya da bildirilir.
        expect(
            within(pills())
                .getByRole('button', { name: /Kahvaltı/ })
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('"+" yeni bir menü açar ve saat aralığını AYNI formda kaydeder', async () => {
        const calls: Call[] = [];
        stubFetch(calls);
        await renderWorkspace();
        const user = userEvent.setup();

        await user.click(await within(pills()).findByRole('button', { name: 'New menu' }));

        const form = screen.getByRole('form', { name: 'New menu' });
        await user.type(within(form).getByLabelText('Menu name'), 'Gece');
        await user.type(within(form).getByLabelText('Starts at'), '22:00');
        await user.type(within(form).getByLabelText('Ends at'), '02:00');
        await user.click(within(form).getByRole('button', { name: 'Save menu' }));

        await screen.findByText('Gece');

        const created = calls.find(
            (call) =>
                call.method === 'POST' &&
                call.url === `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu`,
        );
        expect(created?.body).toEqual({ name: 'Gece' });

        /*
            GECE YARISINI AŞAN ARALIK ÖZEL DURUM DEĞİLDİR: sunucuya
            olduğu gibi gider ve orada iki geçiş anına çevrilir. Ekranın
            "22:00 > 02:00, demek ki ertesi gün" diye bir hesap yapması
            gerekmez — yaparsa o hesap bir gün sunucununkinden ayrışır.
        */
        const scheduled = calls.find(
            (call) => call.method === 'PUT' && call.url.endsWith('/menu/99/service-window'),
        );
        expect(scheduled?.body).toEqual({ startsAt: '22:00', endsAt: '02:00' });
    });

    it('saatin yalnız YARISINI vermek reddedilir — istek hiç gitmez', async () => {
        const calls: Call[] = [];
        stubFetch(calls);
        await renderWorkspace();
        const user = userEvent.setup();

        await user.click(await within(pills()).findByRole('button', { name: 'New menu' }));

        const form = screen.getByRole('form', { name: 'New menu' });
        await user.type(within(form).getByLabelText('Menu name'), 'Gece');
        await user.type(within(form).getByLabelText('Starts at'), '22:00');
        await user.click(within(form).getByRole('button', { name: 'Save menu' }));

        /*
            "07:00'de başlar, hiç bitmez" bir aralık değildir. Yarım bir
            aralığı sunucuya göndermek, orada ya reddedilir (sahip sebebi
            geç öğrenir) ya da bir varsayımla tamamlanırdı — ikincisi çok
            daha kötü: sahibin yazmadığı bir saat, misafirin gördüğü menüyü
            belirlerdi.
        */
        expect(
            screen.getByText('Enter both a start and an end time, or leave both empty.'),
        ).toBeTruthy();
        expect(
            calls.some(
                (call) =>
                    call.method === 'POST' && call.url.endsWith(`/locations/${LOCATION_ID}/menu`),
            ),
        ).toBe(false);
    });

    it('menü silme İKİ ADIMDIR ve seçili menüye uygulanır', async () => {
        const calls: Call[] = [];
        stubFetch(calls);
        await renderWorkspace();
        const user = userEvent.setup();

        await user.click(await within(pills()).findByRole('button', { name: /Kahvaltı/ }));
        await screen.findByText('Menemen');

        await user.click(within(pills()).getByRole('button', { name: 'Edit menu' }));

        const form = screen.getByRole('form', { name: 'Edit menu' });
        await user.click(within(form).getByRole('button', { name: 'Delete menu' }));

        /*
            Menü silmek altmış ürünü birden götürür ve geri alınamaz. Tek
            tıklamaya bırakılamaz; onay adımı, sahibin yanlış hapı seçmiş
            olma ihtimalini yakalar.
        */
        expect(screen.getByText('This removes the menu and everything in it.')).toBeTruthy();
        expect(
            calls.some(
                (call) =>
                    call.method === 'DELETE' && call.url.endsWith(`/menu/${BREAKFAST_MENU_ID}`),
            ),
        ).toBe(false);

        await user.click(screen.getByRole('button', { name: 'Yes, delete it' }));

        expect(
            calls.some(
                (call) =>
                    call.method === 'DELETE' &&
                    call.url === `/api/workspaces/${WORKSPACE_ID}/menu/${BREAKFAST_MENU_ID}`,
            ),
        ).toBe(true);
    });

    it('"Bu menüyü kapat" menüyü SİLMEZ, yalnız rotasyondan çıkarır', async () => {
        const calls: Call[] = [];
        stubFetch(calls);
        await renderWorkspace();
        const user = userEvent.setup();

        await user.click(await within(pills()).findByRole('button', { name: /Kahvaltı/ }));
        await screen.findByText('Menemen');
        await user.click(within(pills()).getByRole('button', { name: 'Edit menu' }));

        const form = screen.getByRole('form', { name: 'Edit menu' });
        await user.click(within(form).getByRole('button', { name: 'Close this menu' }));

        /*
            "Ramazan kapalı" hapı: menü gelecek yıl geri gelecek ve altmış
            ürünü yeniden yazmak kimsenin işine yaramaz. Kapatmak SİLMEK
            DEĞİLDİR ve ikisi ayrı yollardır.
        */
        expect(
            calls.some(
                (call) =>
                    call.method === 'DELETE' &&
                    call.url.endsWith(`/menu/${BREAKFAST_MENU_ID}/service-window`),
            ),
        ).toBe(true);
        expect(
            calls.some(
                (call) =>
                    call.method === 'DELETE' &&
                    call.url === `/api/workspaces/${WORKSPACE_ID}/menu/${BREAKFAST_MENU_ID}`,
            ),
        ).toBe(false);
    });
});
