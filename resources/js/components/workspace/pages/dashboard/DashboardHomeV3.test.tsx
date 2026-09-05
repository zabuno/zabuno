import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { DashboardSuggestions } from './DashboardSuggestions';
import { DashboardQuickActions } from './DashboardQuickActions';
import { DashboardTopViewed } from './DashboardTopViewed';
import { MENU_INSIGHTS_RANGE, type MenuInsights } from './useMenuInsights';
import { DashboardPage, type DashboardMenuTree } from '../DashboardPage';

/**
 * HOME v3 — `docs/109` §1 ve §6.1/6.2.
 *
 * Kaynak (`docs/reference/panel-v3/panel.dc.html`, `data-screen-label="Home"`)
 * dört bölüm getiriyor ve üçü depoda hiç doğmamıştı: ölçümden çıkan öneriler,
 * dört hızlı eylem karosu ve "en çok bakılanlar" tablosu.
 *
 * Bu takımın ASIL konusu doğruluk değil DÜRÜSTLÜKTÜR. Bir önerinin ekrana
 * gelmesi, arkasında GERÇEK bir ölçüm olduğunu iddia eder: "Vejetaryen 14 kez
 * arandı" cümlesi okuyan kişiye menüsünü değiştirtir. Ölçüm yokken o kutuyu
 * çizmek — içi boş bile olsa — sahibi olmayan bir veriye güvendirmektir ve o
 * güven bir kez kırıldığında panelin tamamı bir daha okunmaz.
 *
 * Bu yüzden testlerin çoğu "ÇİZİLMEZ" testidir.
 */

function insights(overrides: Partial<MenuInsights> = {}): MenuInsights {
    return {
        state: 'ready',
        mostViewed: [],
        neverViewed: [],
        searchesWithNoResults: [],
        ...overrides,
    };
}

function menuTree(): DashboardMenuTree {
    return {
        id: 1,
        workspaceId: 71,
        locationId: 34,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 1,
                menuId: 1,
                name: 'Kebaplar',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 1,
                        productId: 901,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 32000,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                    {
                        id: 102,
                        categoryId: 1,
                        productId: 902,
                        productName: 'Lahmacun',
                        priceMinorAmount: 9500,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: false,
                    },
                ],
            },
        ],
    };
}

describe('Home v3 — ölçümden çıkan öneriler (HOME_V3_SUGGESTIONS_RED)', () => {
    /*
        ÖLÇÜM YOKSA BÖLÜM YOK. Uç henüz cevap vermemişken ya da hiç
        çağrılamamışken "Bugün için 0 öneri" yazan bir kutu, sahibe
        "baktım, önerecek bir şey bulamadım" der. Oysa doğrusu "daha
        bakmadım"dır ve ikisi farklı şeylerdir.
    */
    it('ölçüm gelmediğinde hiç çizilmez', () => {
        const { container } = render(
            <DashboardSuggestions insights={null} onNavigateToSection={vi.fn()} />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    /*
        Uç, eşiğin altında `not_enough_data` döner: üç ziyaretçinin baktığı
        bir ürünü "en çok bakılan" diye sunmak, sahibi GÜRÜLTÜYE göre menü
        düzenlettirmek olurdu. Arayüz o kararı ezmez.
    */
    it('ölçüm eşiğin altındayken hiç çizilmez', () => {
        const { container } = render(
            <DashboardSuggestions
                insights={insights({
                    state: 'not_enough_data',
                    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
                })}
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('gerçek ölçümden başlık, gerekçe ve tek eylem taşıyan satırlar üretir', () => {
        render(
            <DashboardSuggestions
                insights={insights({
                    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
                    neverViewed: [
                        {
                            menuItemId: 102,
                            productName: 'Tavuk Şiş',
                            categoryName: 'Kebaplar',
                            viewers: 0,
                        },
                    ],
                })}
                onNavigateToSection={vi.fn()}
            />,
        );

        const region = screen.getByRole('region', { name: /suggestions/i });

        expect(
            within(region).getByText('2 suggestions from your measurements'),
        ).toBeInTheDocument();
        expect(
            within(region).getByText(
                'It suggests, you approve. Nothing changes without your approval.',
            ),
        ).toBeInTheDocument();

        expect(
            within(region).getByText('“Vejetaryen” was searched 14 times but is not on the menu'),
        ).toBeInTheDocument();
        expect(
            within(region).getByText('Searches with no results · last 30 days'),
        ).toBeInTheDocument();

        expect(
            within(region).getByText('Tavuk Şiş has not been opened once in the last 30 days'),
        ).toBeInTheDocument();
        expect(within(region).getByText('Menu engineering · never viewed')).toBeInTheDocument();
    });

    /*
        "Öneri yapar, sen onaylarsın. Onaysız hiçbir şey değişmez." Eylem
        düğmesi HİÇBİR ŞEY UYGULAMAZ: sahibi kararı verebileceği ekrana
        götürür, orada değişikliği kendisi yapar.
    */
    it('eylem düğmesi hiçbir şeyi uygulamaz, yalnız karar ekranına götürür', async () => {
        const user = userEvent.setup();
        const onNavigateToSection = vi.fn();

        render(
            <DashboardSuggestions
                insights={insights({
                    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
                })}
                onNavigateToSection={onNavigateToSection}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Open the menu' }));

        expect(onNavigateToSection).toHaveBeenCalledExactlyOnceWith('menu');
    });

    it('kapatılan öneri listeden düşer; hepsi kapanınca bölüm kaybolur', async () => {
        const user = userEvent.setup();

        const { container } = render(
            <DashboardSuggestions
                insights={insights({
                    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
                })}
                onNavigateToSection={vi.fn()}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Dismiss this suggestion' }));

        expect(container).toBeEmptyDOMElement();
    });

    /*
        Kaynak üç satır gösteriyor. Bir sabah on beş "hiç bakılmayan" ürün
        çıkarsa liste ekranı yutar ve ekranın asıl işi ("şimdi ne yapmalıyım")
        gözden kaybolur. Öneri bir GÜNDEM maddesidir, bir rapor değil.
    */
    it('en çok üç öneri gösterir', () => {
        render(
            <DashboardSuggestions
                insights={insights({
                    searchesWithNoResults: [
                        { term: 'Vejetaryen', searches: 14 },
                        { term: 'Vegan', searches: 9 },
                    ],
                    neverViewed: [
                        { menuItemId: 1, productName: 'A', categoryName: 'K', viewers: 0 },
                        { menuItemId: 2, productName: 'B', categoryName: 'K', viewers: 0 },
                        { menuItemId: 3, productName: 'C', categoryName: 'K', viewers: 0 },
                    ],
                })}
                onNavigateToSection={vi.fn()}
            />,
        );

        const region = screen.getByRole('region', { name: /suggestions/i });

        expect(within(region).getAllByRole('listitem')).toHaveLength(3);
        expect(
            within(region).getByText('3 suggestions from your measurements'),
        ).toBeInTheDocument();
    });
});

describe('Home v3 — dört hızlı eylem (HOME_V3_QUICK_ACTIONS_RED)', () => {
    it('kaynağın dört karosunu kendi ekranına bağlar', async () => {
        const user = userEvent.setup();
        const onNavigateToSection = vi.fn();

        render(<DashboardQuickActions onNavigateToSection={onNavigateToSection} />);

        const region = screen.getByRole('region', { name: /quick actions/i });

        await user.click(within(region).getByRole('button', { name: 'Change a price' }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('menu');

        await user.click(within(region).getByRole('button', { name: 'Hide / sold out' }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('menu');

        await user.click(within(region).getByRole('button', { name: 'Download a QR code' }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('qr-codes');

        await user.click(within(region).getByRole('button', { name: 'Add a photo' }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('media');
    });

    /*
        Gezinti bağlı değilse karo ÇİZİLMEZ. Hiçbir yere götürmeyen bir düğme,
        tıklanana kadar çalışıyor görünür — ve tıklandığı an ürünün bozuk
        olduğunu söyler.
    */
    it('gezinti bağlı değilken hiç çizilmez', () => {
        const { container } = render(<DashboardQuickActions />);

        expect(container).toBeEmptyDOMElement();
    });
});

describe('Home v3 — bugün en çok bakılanlar (HOME_V3_TOP_VIEWED_RED)', () => {
    it('ölçüm gelmediğinde ya da eşiğin altındayken hiç çizilmez', () => {
        const withoutData = render(
            <DashboardTopViewed insights={null} dashboardMenuTree={menuTree()} />,
        );
        expect(withoutData.container).toBeEmptyDOMElement();
        withoutData.unmount();

        const belowThreshold = render(
            <DashboardTopViewed
                insights={insights({ state: 'not_enough_data' })}
                dashboardMenuTree={menuTree()}
            />,
        );
        expect(belowThreshold.container).toBeEmptyDOMElement();
        belowThreshold.unmount();

        const empty = render(
            <DashboardTopViewed insights={insights()} dashboardMenuTree={menuTree()} />,
        );
        expect(empty.container).toBeEmptyDOMElement();
    });

    it('sıra, ürün, bakan sayısı ve menüden okunan fiyatı gösterir', () => {
        render(
            <DashboardTopViewed
                insights={insights({
                    mostViewed: [
                        {
                            menuItemId: 101,
                            productName: 'Adana Kebap',
                            categoryName: 'Kebaplar',
                            viewers: 61,
                        },
                        {
                            menuItemId: 102,
                            productName: 'Lahmacun',
                            categoryName: 'Kebaplar',
                            viewers: 31,
                        },
                    ],
                })}
                dashboardMenuTree={menuTree()}
            />,
        );

        const rows = screen.getAllByRole('listitem');

        expect(within(rows[0]).getByText('1')).toBeInTheDocument();
        expect(within(rows[0]).getByText('Adana Kebap')).toBeInTheDocument();
        expect(within(rows[0]).getByText('61')).toBeInTheDocument();
        expect(rows[0].textContent).toContain('320');

        expect(within(rows[1]).getByText('2')).toBeInTheDocument();
        expect(rows[1].textContent).toContain('95');
    });

    /*
        Fiyat UYDURULMAZ. Ölçüm menüden silinmiş bir ürüne ait olabilir;
        o satırda sıfır ya da eski bir fiyat göstermek, sahibin bugün
        geçerli sandığı bir rakam üretirdi.
    */
    it('menüde karşılığı olmayan satırda fiyat uydurmaz', () => {
        render(
            <DashboardTopViewed
                insights={insights({
                    mostViewed: [
                        {
                            menuItemId: 999,
                            productName: 'Silinmiş Ürün',
                            categoryName: 'Kebaplar',
                            viewers: 12,
                        },
                    ],
                })}
                dashboardMenuTree={menuTree()}
            />,
        );

        const row = screen.getByRole('listitem');

        expect(within(row).getByText('—')).toBeInTheDocument();
        expect(row.textContent).not.toMatch(/\d+[.,]\d\d/);
    });

    it('"tümü" bağlantısı analitik ekranına götürür', async () => {
        const user = userEvent.setup();
        const onNavigateToSection = vi.fn();

        render(
            <DashboardTopViewed
                insights={insights({
                    mostViewed: [
                        {
                            menuItemId: 101,
                            productName: 'Adana Kebap',
                            categoryName: 'Kebaplar',
                            viewers: 61,
                        },
                    ],
                })}
                dashboardMenuTree={menuTree()}
                onNavigateToSection={onNavigateToSection}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'See all' }));

        expect(onNavigateToSection).toHaveBeenCalledExactlyOnceWith('analytics');
    });

    /*
        Çubuk aynı sayıyı İKİNCİ KEZ söyler; ekran okuyucuya iki kez
        okutmak gürültüdür. Karşılaştırma en çok bakılana göre kurulur —
        yani ilk satır her zaman dolu, diğerleri ona oranla.
    */
    it('bakış çubuğu en çok bakılana oranlanır ve ekran okuyucuya tekrar edilmez', () => {
        render(
            <DashboardTopViewed
                insights={insights({
                    mostViewed: [
                        {
                            menuItemId: 101,
                            productName: 'Adana Kebap',
                            categoryName: 'Kebaplar',
                            viewers: 60,
                        },
                        {
                            menuItemId: 102,
                            productName: 'Lahmacun',
                            categoryName: 'Kebaplar',
                            viewers: 30,
                        },
                    ],
                })}
                dashboardMenuTree={menuTree()}
            />,
        );

        const rows = screen.getAllByRole('listitem');
        const first = rows[0].querySelector('[data-viewer-bar]') as HTMLElement | null;
        const second = rows[1].querySelector('[data-viewer-bar]') as HTMLElement | null;

        expect(first?.style.width).toBe('100%');
        expect(second?.style.width).toBe('50%');
        expect(first?.closest('[aria-hidden="true"]')).not.toBeNull();
    });
});

const WORKSPACE_ID = 71;

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

describe('Home v3 — ölçümün Home ekranına bağlanması (HOME_V3_WIRING_RED)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    /*
        Çalışma alanı bilinmeden ölçüm İSTENMEZ. Kimliksiz bir istek ya 404
        döner ya da başka bir kiracının verisine bakmayı dener; ikisi de
        kabul edilemez.
    */
    it('çalışma alanı bilinmeden ölçüm ucuna istek atılmaz', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        render(<DashboardPage dashboardMenuTree={menuTree()} />);

        expect(fetchMock).not.toHaveBeenCalled();
    });

    /*
        İŞLETME DEĞİŞİNCE ÖLÇÜM DE DEĞİŞİR — ve arada BOŞLUK olur.

        İki şubesi olan bir sahip üst çubuktan ikinci işletmesine geçtiğinde,
        yeni yanıt gelene kadar ekranda birinci işletmenin önerileri kalırsa
        "Vejetaryen 14 kez arandı" cümlesi YANLIŞ restoranın altında durur.
        Sahip o cümleye bakıp yanlış menüye kategori ekler. Birkaç yüz
        milisaniyelik bir yanlışlık, kalıcı bir yanlış karara dönüşür.
    */
    it('çalışma alanı değişince önceki işletmenin ölçümü ekranda kalmaz', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url).startsWith(`/api/workspaces/${WORKSPACE_ID}/analytics`)) {
                return jsonResponse(200, {
                    state: 'ready',
                    mostViewed: [],
                    neverViewed: [],
                    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
                });
            }

            // İkinci işletmenin ölçümü henüz yolda.
            return new Promise<Response>(() => {});
        });
        vi.stubGlobal('fetch', fetchMock);

        const { rerender } = render(
            <DashboardPage
                workspaceId={WORKSPACE_ID}
                dashboardMenuTree={menuTree()}
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(
            await screen.findByText('“Vejetaryen” was searched 14 times but is not on the menu'),
        ).toBeInTheDocument();

        rerender(
            <DashboardPage
                workspaceId={WORKSPACE_ID + 1}
                dashboardMenuTree={menuTree()}
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(screen.queryByRole('region', { name: /suggestions/i })).toBeNull();
    });

    it('tek istekle hem önerileri hem en çok bakılanları doğurur', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (
                String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/analytics/menu-engineering?range=${MENU_INSIGHTS_RANGE}`
            ) {
                return jsonResponse(200, {
                    state: 'ready',
                    threshold: 5,
                    observedViewers: 92,
                    mostViewed: [
                        {
                            menuItemId: 101,
                            productName: 'Adana Kebap',
                            categoryName: 'Kebaplar',
                            viewers: 61,
                        },
                    ],
                    neverViewed: [
                        {
                            menuItemId: 102,
                            productName: 'Lahmacun',
                            categoryName: 'Kebaplar',
                            viewers: 0,
                        },
                    ],
                    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
                });
            }

            return jsonResponse(404, {});
        });
        vi.stubGlobal('fetch', fetchMock);

        render(
            <DashboardPage
                workspaceId={WORKSPACE_ID}
                dashboardMenuTree={menuTree()}
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(
            await screen.findByText('“Vejetaryen” was searched 14 times but is not on the menu'),
        ).toBeInTheDocument();
        expect(screen.getByText('Most viewed in the last 30 days')).toBeInTheDocument();

        const menuEngineeringCalls = fetchMock.mock.calls.filter((call) =>
            String(call[0]).includes('menu-engineering'),
        );

        expect(menuEngineeringCalls).toHaveLength(1);
    });

    /*
        Uç yetki vermediğinde (404) ya da plan kapalıyken ekran SESSİZ kalır:
        boş bir öneri kutusu ya da "ölçüm alınamadı" uyarısı, sahibin
        yapabileceği bir şey olmadığı hâlde ekranı meşgul ederdi.
    */
    it('ölçüm alınamadığında iki bölüm de hiç çizilmez', async () => {
        const fetchMock = vi.fn(async () => jsonResponse(404, {}));
        vi.stubGlobal('fetch', fetchMock);

        render(
            <DashboardPage
                workspaceId={WORKSPACE_ID}
                dashboardMenuTree={menuTree()}
                onNavigateToSection={vi.fn()}
            />,
        );

        await screen.findByRole('region', { name: /quick actions/i });

        expect(screen.queryByRole('region', { name: /suggestions/i })).toBeNull();
        expect(screen.queryByText('Most viewed in the last 30 days')).toBeNull();
    });
});

/**
 * GÖRSEL SÖZLEŞME muhafızı — `docs/109` görsel kuralları.
 *
 * Bu kuralların her biri bir kez ihlal edildiğinde gözle fark edilmez, ama
 * ürünün tamamına yayıldığında panel "iki farklı yerden yapılmış" görünür.
 * Kural metinde değil, TESTTE durmalı: yeni bir bölüm yazan kişi belgeyi
 * okumayı unutabilir, testi atlayamaz.
 */
const DASHBOARD_DIR = path.dirname(fileURLToPath(import.meta.url));

const DASHBOARD_SOURCES = globSync('*.tsx', { cwd: DASHBOARD_DIR })
    .filter((file) => !file.includes('.test.') && !file.includes('.stories.'))
    .map((file) => path.join(DASHBOARD_DIR, file));

describe('Home v3 — görsel sözleşme (HOME_V3_VISUAL_CONTRACT_RED)', () => {
    it('taranacak dosya bulunmadan geçmez', () => {
        expect(DASHBOARD_SOURCES.length).toBeGreaterThanOrEqual(4);
    });

    it.each([
        /*
            600 ağırlık AEP merdiveninde YOK. Roboto ayrı bir 600 kesimi
            yüklemediği için tarayıcı onu sentezler: harfler kalınlaşırken
            biçimleri bozulur ve aynı sayfada iki farklı "kalın" belirir.
        */
        ['font-semibold', /\bfont-semibold\b/],
        // Büyük harfe çevirme okuma hızını düşürür ve ekran okuyucuda kısaltma sanılır.
        ['uppercase', /\buppercase\b/],
        // Tam yuvarlak yalnız `rounded-pill` jetonuyla; ham sınıf jetonu atlar.
        ['rounded-full', /\brounded-full\b/],
        /*
            Fiziksel yön sınıfı Arapçada ekranı ters çevirir: `ml-` sağdan
            sola yazan bir kiracıda metni yanlış tarafa iter. Mantıksal
            karşılıkları (`ms-`, `me-`, `text-start`) her iki yönde doğrudur.
        */
        ['fiziksel yön sınıfı', /\b(ml-|mr-|pl-|pr-|text-left|text-right)/],
    ])('hiçbir Home bölümü `%s` taşımaz', (_label, pattern) => {
        const offenders = DASHBOARD_SOURCES.filter((file) =>
            pattern.test(readFileSync(file, 'utf8')),
        ).map((file) => path.basename(file));

        expect(offenders).toEqual([]);
    });
});
