import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome, mobileChrome } from '../../test/workspaceChrome';
import { desktopInspectors } from './inspectors/desktopInspectors';

/**
 * RED test freezing the real AdminShell composition contract for
 * WorkspaceApp's current-workspace view (S1-WP01A admin shell migration).
 * WorkspaceApp does not compose AdminShell yet, so this must fail RED
 * against the frozen production anchors in the delivery contract.
 */

vi.mock('./BrandOnboardingForm', () => ({
    BrandOnboardingForm: () => <div data-testid="brand-onboarding-form" />,
}));

vi.mock('./LocationOnboardingForm', () => ({
    LocationOnboardingForm: () => <div data-testid="location-onboarding-form" />,
}));

vi.mock('../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeUser() {
    return { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' };
}

function makeWorkspace() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        state: 'active',
    };
}

function makeLocation(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 923,
        workspace_id: WORKSPACE_ID,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

/**
 * Menü ağacı — bağlam panelinin GERÇEK verisi.
 *
 * Fikstür başta menüsüz olduğu için panel `null` dönüyordu ve hiçbir test
 * panelin gerçekten ÇİZİLDİĞİNİ görmüyordu. Şikâyet tam buydu: "sağ sidebar
 * hiçbir ekranda yok". Panelin var olduğunu kanıtlayan tek şey, onu menülü bir
 * ekranda görmektir.
 */
function makeMenuTree() {
    // Şekil `ShowMenuController` ile birebir: uydurma bir şekil, kanıtladığını
    // sandığı şeyi kanıtlamaz.
    return {
        id: 4501,
        workspaceId: WORKSPACE_ID,
        locationId: 923,
        name: 'Ana menü',
        state: 'draft',
        categories: [
            {
                id: 71,
                menuId: 4501,
                name: 'Kahvaltı',
                position: 1,
                menuItems: [
                    {
                        id: 900,
                        categoryId: 71,
                        productId: 5,
                        productName: 'Menemen',
                        priceMinorAmount: 18500,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                    },
                ],
            },
            { id: 72, menuId: 4501, name: 'İçecekler', position: 2, menuItems: [] },
        ],
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === '/api/user' && method === 'GET') {
            return jsonResponse(200, makeUser());
        }
        if (String(url) === '/api/workspaces' && method === 'GET') {
            return jsonResponse(200, [makeWorkspace()]);
        }
        if (String(url) === '/api/workspace-context' && method === 'GET') {
            return jsonResponse(200, makeWorkspace());
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'GET') {
            return jsonResponse(200, { id: 811, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return jsonResponse(200, [makeLocation()]);
        }
        if (
            String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations/923/menu` &&
            method === 'GET'
        ) {
            return jsonResponse(200, makeMenuTree());
        }
        if (
            String(url) === `/api/workspaces/${WORKSPACE_ID}/menu/4501/publications/current` &&
            method === 'GET'
        ) {
            // Henüz yayınlanmamış: panel sürüm satırını UYDURMAZ.
            return jsonResponse(404, {});
        }

        throw new Error(`Unhandled fetch in WorkspaceApp shell test: ${method} ${String(url)}`);
    });
}

async function renderCurrentWorkspace(chrome: object = desktopChrome) {
    const fetchMock = buildFetchMock();
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{
        WorkspaceApp: React.ComponentType<typeof desktopChrome & typeof mobileChrome>;
    }>();
    render(<WorkspaceApp {...chrome} />);

    if (chrome === desktopChrome) {
        await screen.findByRole('navigation', { name: 'Restaurant admin' });
    } else {
        await screen.findByRole('button', { name: 'Open menu' });
    }

    return fetchMock;
}

describe('WorkspaceApp — real AdminShell composition (S1-WP01A, RED)', () => {
    beforeEach(() => {
        // Her test tarayıcıyı YENİ açmış gibi başlar: gezinti artık adresi
        // gerçekten değiştiriyor ve bir testin bıraktığı adres sonrakini
        // sessizce başka bir ekranda açardı.
        history.replaceState(null, '', '/');
    });

    it('renders the current workspace inside the real AdminShell: brand, accessible nav, skip link, and main landmark hosting catalog content once Menu is selected', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        expect(screen.getByText('Zabuno')).toBeInTheDocument();

        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute(
            'href',
            '#main-content',
        );

        const main = screen.getByRole('main');
        expect(main).toHaveAttribute('id', 'main-content');

        expect(screen.getByRole('navigation', { name: 'Restaurant admin' })).toBeInTheDocument();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menus' }));

        expect(within(main).getByTestId('menu-catalog-workspace')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('surfaces the current workspace name and user email, and preserves accessible Switch workspace and Log out controls', async () => {
        await renderCurrentWorkspace();

        const banner = screen.getByRole('banner');
        expect(
            within(banner).getByRole('button', { name: 'Zeytin Restoranları' }),
        ).toBeInTheDocument();
        expect(screen.getByText('ada@example.com')).toBeInTheDocument();

        /*
            Hesap kontrolleri artık kenar çubuğunun dibinde değil, kimlik
            alanındaki hesap menüsünde. Kenar çubuğu yalnız GÖREV gezintisi
            taşıyor; hesap işleri gezinti değildir ve görev maddelerinin
            arasına karıştığında ikisi de okunmaz olur.

            Kontroller kaybolmadı — bu yüzden test menüyü AÇIP arıyor.
        */
        const accountMenu = within(banner).getByRole('button', { name: 'Account' });
        expect(accountMenu).toBeInTheDocument();

        await userEvent.click(accountMenu);

        expect(
            await screen.findByRole('menuitem', { name: 'Switch workspace' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('menuitem', { name: 'Log out' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    // Niyet aynı: çekmece açılınca gezinti gelir, kapanınca gider, masaüstü
    // gezintisi kalır. Ölçüm değişti. Eskiden bu test AÇIKKEN İKİ `navigation`
    // landmark'ı bekliyordu — yani axe'in `landmark-unique` ihlali olarak
    // bildirdiği kusurun kendisini doğruluyordu: aynı adı taşıyan iki landmark
    // ekran okuyucu listesinde ayırt edilemez. Çekmece zaten adlandırılmış bir
    // diyalog olduğu için içindeki gezinti artık landmark değil; landmark
    // sayısı HER ZAMAN bir kalır ve çekmecenin varlığı içeriğinden okunur.
    /**
     * TELEFON kabuğu: kalıcı ray YOK, gezinti çekmeceden gelir.
     *
     * Eski sözleşmede ikisi birden çiziliyor, kalıcı olan CSS ile
     * gizleniyordu — yani telefon her ikisinin de kodunu indiriyordu. Artık
     * cihaz ayrımı sunucuda yapılıyor (docs/54) ve telefon paketinde kalıcı
     * ray hiç bulunmuyor; bu yüzden test, çekmece kapalıyken gezintinin DOM'da
     * hiç olmadığını da sınıyor.
     */
    it('telefon kabuğunda gezinti yalnız çekmeceden gelir ve kapanınca kaybolur', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace(mobileChrome);

        const dashboardLinks = () => screen.queryAllByRole('link', { name: 'Home' });

        expect(dashboardLinks()).toHaveLength(0);

        await user.click(screen.getByRole('button', { name: 'Open menu' }));

        expect(dashboardLinks()).toHaveLength(1);
        expect(screen.getAllByRole('navigation', { name: 'Restaurant admin' })).toHaveLength(1);

        await user.click(screen.getByRole('button', { name: 'Close' }));

        await waitFor(() => {
            expect(dashboardLinks()).toHaveLength(0);
        });

        vi.unstubAllGlobals();
    });

    // Bölüm bir "sayfa"dır ve yeni sayfa baştan başlar. Kaydırmayı elle
    // sıfırlamak gerekir: tek sayfa uygulamasında adres değişse de tarayıcı
    // sayfayı yeniden yüklemez, dolayısıyla kaydırma konumu olduğu yerde
    // kalır ve kullanıcı yeni ekranın ortasına düşer.
    it('returns the page to the top when the active section changes', async () => {
        const user = userEvent.setup();
        const scrollTo = vi.fn();
        vi.stubGlobal('scrollTo', scrollTo);

        await renderCurrentWorkspace();

        scrollTo.mockClear();

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(
                scrollTo,
                'Bölüm değiştiğinde sayfa başa dönmeli; aksi hâlde kullanıcı yeni ekranın ortasında açılır.',
            ).toHaveBeenCalledWith(expect.objectContaining({ top: 0 }));
        });

        // Yumuşak kaydırma her gezinmede gürültüdür ve azaltılmış hareket
        // tercihini çiğner.
        expect(scrollTo).not.toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

        vi.unstubAllGlobals();
    });

    // Bu test, "sıçrama düzeldi" dedikten SONRA hâlâ sıçradığı için var.
    //
    // Kök sebep düzeltmenin kendisiydi: sayfa başa alınıyor, ardından
    // `focus()` onu geri aşağı çekiyordu. `focus()` varsayılan olarak
    // elemanı görünür alana kaydırır ve `main` üst çubuğun altında başlar.
    //
    // Gerçek tarayıcıda ölçülen (720px viewport, 2400px içerik):
    //   scrollTo({top: 0}) -> 0 ;  ardından focus() -> 1680 ;
    //   focus({ preventScroll: true }) -> 0
    //
    // jsdom'da `focus()` kaydırmadığı için bu hata bir birim testinde
    // GÖRÜNMEZ. Bu yüzden sözleşme davranış üzerinden değil, seçeneğin
    // verilmesi üzerinden zorlanır — kaybedilen tek şey budur ve bilerek
    // kabul edilmiştir.
    // Sıçramanın GERÇEK sebebi buydu ve iki düzeltme denemesinden sonra
    // ancak tarayıcıda ölçerek bulundu.
    //
    // İçerik değişince belge kısalır (ölçülen: Medya 3802px -> Analitik
    // 1250px) ve tarayıcı kaydırmayı KIRPAR: 1000 -> 122. O kırpma bizim
    // kodumuzdan ÖNCE olur; `scrollTo` çağrımız çoktan kırpılmış bir
    // konuma varır. Kullanıcının gördüğü sıçrama tam olarak o kırpmadır.
    //
    // Çözüm sırayı tersine çevirmek: kaydırma zaten 0'dayken içerik
    // değişirse kırpacak bir şey kalmaz.
    //
    // jsdom düzen hesaplamaz, yani kırpmayı ÜRETEMEZ. Ama SIRAYI
    // gösterebilir: `scrollTo` çağrıldığı anda hâlâ eski bölüm mü
    // basılı? Tutulabilen sözleşme budur.
    it('resets the scroll while the previous section is still mounted', async () => {
        const user = userEvent.setup();
        let previousSectionStillMounted: boolean | null = null;

        vi.stubGlobal(
            'scrollTo',
            vi.fn(() => {
                if (previousSectionStillMounted === null) {
                    // Kapsayıcı id'si ile bakıyoruz: metinle aramak
                    // belirsizdir (nav bağlantısı ve başlık aynı kelimeyi
                    // taşır) ve testi kırılgan yapar.
                    previousSectionStillMounted =
                        document.getElementById('section-dashboard') !== null;
                }
            }),
        );

        await renderCurrentWorkspace();
        previousSectionStillMounted = null;

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(
                previousSectionStillMounted,
                'Kaydırma sıfırlanmadan önce içerik değişirse tarayıcı konumu kırpar ve sayfa sıçrar.',
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });

    it('moves focus without letting the browser scroll the page back down', async () => {
        const user = userEvent.setup();
        vi.stubGlobal('scrollTo', vi.fn());

        const focusSpy = vi.spyOn(HTMLElement.prototype, 'focus');

        await renderCurrentWorkspace();
        focusSpy.mockClear();

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(
                focusSpy.mock.calls.some(([options]) => options?.preventScroll === true),
                'Bölüm değişiminde odak `preventScroll: true` ile taşınmalı; aksi hâlde tarayıcı sayfayı geri aşağı kaydırır.',
            ).toBe(true);
        });

        focusSpy.mockRestore();
        vi.unstubAllGlobals();
    });

    it('transitions to the existing choose-workspace journey when Switch workspace is activated', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        // Hesap menüsü önce açılır: kontrol kenar çubuğundan kimlik alanına taşındı.
        await user.click(screen.getByRole('button', { name: 'Account' }));
        await user.click(await screen.findByRole('menuitem', { name: 'Switch workspace' }));

        expect(screen.getByRole('heading', { name: 'Choose a workspace' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('shows Dashboard active by default, with a Dashboard nav item, a dashboard destination that does not host the catalog, and a separate menu destination for the Menu nav link once selected', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        const dashboardLink = within(nav).getByRole('link', { name: 'Home' });
        expect(dashboardLink).toHaveAttribute('href', '/app/zeytin-restoranlari/dashboard');
        expect(dashboardLink).toHaveAttribute('aria-current', 'page');

        const menuLink = within(nav).getByRole('link', { name: 'Menus' });
        expect(menuLink).toHaveAttribute('href', '/app/zeytin-restoranlari/menu');
        expect(menuLink).not.toHaveAttribute('aria-current', 'page');

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-dashboard')).not.toBeNull();
        expect(main.querySelector('#section-menu')).toBeNull();

        await user.click(menuLink);

        expect(main.querySelector('#section-dashboard')).toBeNull();
        expect(main.querySelector('#section-menu')).not.toBeNull();

        const catalogDestination = within(main)
            .getByTestId('menu-catalog-workspace')
            .closest('#section-menu');
        expect(catalogDestination).not.toBeNull();

        vi.unstubAllGlobals();
    });

    it('telefon kabuğunda çekmeceden gezinmek çekmeceyi kapatır ve hedefi çizer', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace(mobileChrome);

        await user.click(screen.getByRole('button', { name: 'Open menu' }));

        const dialog = screen.getByRole('dialog');
        await user.click(within(dialog).getByRole('link', { name: 'Menus' }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        const main = screen.getByRole('main');
        expect(within(main).getByTestId('menu-catalog-workspace')).toBeInTheDocument();
        expect(main.querySelector('#section-menu')).not.toBeNull();

        vi.unstubAllGlobals();
    });
    // ANALYTICS-TENANT-SEAM: sahibin kilit kuralının ürün içindeki kanıtı.
    //
    // Tek sayfa uygulamasında `history.pushState` tarayıcıya göre sayfa
    // DEĞİŞTİRMEZ; GA4 ve Metrica kendiliğinden hiçbir şey ölçmez. Bu test
    // olmadan panelde on ekran gezen bir kullanıcı, ölçümde tek sayfalık bir
    // ziyaret gibi görünürdü — ve bunun tarayıcıda hiçbir belirtisi olmazdı.
    it('reports every section change to the dataLayer with the tenant attached', async () => {
        const user = userEvent.setup();
        const dataLayer: Array<Record<string, unknown>> = [];
        (window as unknown as { dataLayer: unknown[] }).dataLayer = dataLayer;

        await renderCurrentWorkspace();

        dataLayer.length = 0;

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(dataLayer).toHaveLength(1);
        });

        expect(dataLayer[0]).toMatchObject({
            event: 'page_view',
            page_path: '/app/zeytin-restoranlari/media',
            zabuno_tenant_slug: 'zeytin-restoranlari',
        });

        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;
        vi.unstubAllGlobals();
    });
    // Kullanıcı panele `/app` adresinden girer; orada hangi restoranın hangi
    // ekranı olduğu yazmaz. Böyle bir adres paylaşılamaz, yer imine
    // konamaz ve sunucu günlüğünde bütün restoranlar tek satıra karışır.
    it('rewrites the bare /app address to the tenant and section it is actually showing', async () => {
        history.replaceState(null, '', '/app');

        await renderCurrentWorkspace();

        await waitFor(() => {
            expect(window.location.pathname).toBe('/app/zeytin-restoranlari/dashboard');
        });

        vi.unstubAllGlobals();
    });
    /**
     * Kenar çubuğu bir LİSTE değil, bir SIRA.
     *
     * Önceden dokuz madde tek ve adsız bir yığındı: Dashboard, Brand,
     * Locations, Menu, Media, Publication, Analytics, Team, Billing — hepsi
     * aynı görsel ağırlıkta. Dokuz eşit seçenek, kullanıcıyı her seferinde
     * listenin tamamını okumaya zorlar. Oysa bunlar bağımsız değil, bir
     * sıranın adımları.
     */
    it('kenar çubuğunu docs/50 §5 bilgi mimarisine göre gruplar', async () => {
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        // Operasyon: her gün gidilen yerler.
        const operations = within(nav).getByRole('list', { name: 'Operations' });
        expect(within(operations).getByRole('link', { name: 'Home' })).toBeInTheDocument();
        expect(within(operations).getByRole('link', { name: 'Menus' })).toBeInTheDocument();
        expect(within(operations).getByRole('link', { name: 'QR codes' })).toBeInTheDocument();
        expect(within(operations).getByRole('link', { name: 'Insights' })).toBeInTheDocument();

        // Yönetim: ara sıra düzenlenen kayıtlar.
        const management = within(nav).getByRole('list', { name: 'Management' });
        expect(within(management).getByRole('link', { name: 'Locations' })).toBeInTheDocument();
        expect(within(management).getByRole('link', { name: 'Media' })).toBeInTheDocument();
        expect(within(management).getByRole('link', { name: 'Team' })).toBeInTheDocument();

        // Ayarlar: nadiren açılan işler.
        const settings = within(nav).getByRole('list', { name: 'Settings' });
        expect(within(settings).getByRole('link', { name: 'Settings' })).toBeInTheDocument();

        // Günlük operasyon OLMAYANLAR ana menüde kalıcı yer işgal etmez —
        // ama adresleri çalışır (docs/50 §5).
        expect(within(nav).queryByRole('link', { name: 'Brand' })).toBeNull();
        expect(within(nav).queryByRole('link', { name: 'Billing' })).toBeNull();
        expect(within(nav).queryByRole('link', { name: 'Publication' })).toBeNull();

        // Hesap işleri kenar çubuğunda DEĞİL. Gezinti değildirler ve görev
        // maddelerinin arasına karıştıklarında ikisi de okunmaz olur.
        expect(within(nav).queryByRole('link', { name: 'Log out' })).toBeNull();
        expect(within(nav).queryByRole('button', { name: 'Log out' })).toBeNull();

        vi.unstubAllGlobals();
    });
    /**
     * PANEL GERÇEKTEN ÇİZİLİR.
     *
     * Bu testin yokluğu, paketin ilk hâlinde kimsenin fark etmediği boşluktu:
     * bileşenin kendi testi geçiyordu, kabuk yuvası açıktı, ama hiçbir test
     * ikisinin BİRLEŞTİĞİNİ görmüyordu. Şikâyet de tam buydu — "sağ sidebar
     * hiçbir ekranda yok".
     */
    it('menü ekranında bağlam paneli gerçek veriyle çizilir', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace({ ...desktopChrome, inspectors: desktopInspectors });

        await user.click(await screen.findByRole('link', { name: 'Menus' }));

        const inspector = await screen.findByRole('complementary', { name: /this menu/i });

        // Sayılar ağaçtan gelir: iki kategori, bir ürün.
        expect(within(inspector).getByText(/^categories$/i).parentElement).toHaveTextContent('2');
        expect(within(inspector).getByText(/^items$/i).parentElement).toHaveTextContent('1');

        // Menünün bağlı olduğu lokasyon — panelin cevapladığı asıl soru.
        expect(within(inspector).getByText('Kadıköy')).toBeInTheDocument();

        // Yayınlanmamış menüde sürüm satırı UYDURULMAZ.
        expect(within(inspector).queryByText(/version/i)).toBeNull();

        vi.unstubAllGlobals();
    });

    /**
     * Panelin tek eylemi ana alanda ZATEN var olan yola götürür; yeni bir yol
     * açsaydı panel bir kolaylık değil gizli bir ön koşul olurdu.
     */
    it('paneldeki kısayol bilinen yayın ekranına götürür', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace({ ...desktopChrome, inspectors: desktopInspectors });

        await user.click(await screen.findByRole('link', { name: 'Menus' }));
        const inspector = await screen.findByRole('complementary', { name: /this menu/i });
        await user.click(within(inspector).getByRole('button', { name: /preview & publish/i }));

        expect(screen.getByRole('main').querySelector('#section-publication')).not.toBeNull();

        vi.unstubAllGlobals();
    });

    /**
     * Panel BULUNMAYAN sayfada çizilmez.
     *
     * Her sayfanın sağ paneli olmaz: bir özet ekranında panel, doldurulacak
     * bir şey olmadığı için ya boş durur ya da uydurulmuş bilgiyle dolar.
     * Panel haritası bu yüzden kısmîdir, her bölüm için bir giriş içermez.
     */
    it('haritada olmayan bölümde panel hiç çizilmez', async () => {
        await renderCurrentWorkspace({ ...desktopChrome, inspectors: desktopInspectors });

        // Varsayılan bölüm Home; masaüstü panel haritasında yok.
        expect(screen.queryByRole('complementary', { name: /this menu/i })).toBeNull();

        vi.unstubAllGlobals();
    });

    /**
     * TEMEL GÖREV panele bağımlı DEĞİLDİR.
     *
     * Telefon paketinde panel hiç bulunmaz. Menü ekranı orada da eksiksiz
     * çalışmalı — aksi hâlde panel bir kolaylık değil, gizli bir ön koşul
     * olurdu.
     */
    it('telefon kabuğunda panel yokken menü ekranı çalışır', async () => {
        const user = userEvent.setup();
        // Telefon girişi panel haritasını HİÇ vermez — `desktopInspectors`
        // dosyasına dokunmadığı için panel kodu o pakete girmez (docs/54).
        await renderCurrentWorkspace(mobileChrome);

        await user.click(screen.getByRole('button', { name: 'Open menu' }));
        await user.click(await screen.findByRole('link', { name: 'Menus' }));

        expect(screen.queryByRole('complementary')).toBeNull();

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-menu')).not.toBeNull();

        vi.unstubAllGlobals();
    });
});
