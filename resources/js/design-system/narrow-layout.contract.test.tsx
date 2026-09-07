import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { HeatGrid } from '../components/catalog/data-display/compound/HeatGrid';
import { TrendChart } from '../components/catalog/data-display/compound/TrendChart';
import { MenuScreenActions } from '../components/catalog/menu/compound/MenuScreenActions';
import { PageHeader } from '../components/catalog/layout/macro/PageHeader';
import { MenuCatalogWorkspace } from '../components/catalog/menu/macro/MenuCatalogWorkspace';
import { QrCodeListItem } from '../components/workspace/pages/publication/qr-destination/QrCodeListItem';

/**
 * DS-NARROW-LAYOUT — dar ekran yerleşimi bir yapı sözleşmesidir (`docs/117` M6–M9).
 *
 * ═══ NE ÖLÇÜYOR, NE ÖLÇMÜYOR ═══
 *
 * Burası jsdom: hiçbir kutunun boyu yoktur. "320 pikselde satır kaç satıra
 * iniyor", "ızgara belgeyi kaydırıyor mu", "ad 12 piksel mi" soruları BURADA
 * sorulamaz; onları `scripts/mobile-ux-audit` gerçek Chrome'da 320×568'de
 * soruyor ve paketin asıl kabulü oradadır. Bu dosya "mobilde çalışıyor"
 * DEMEZ.
 *
 * Bu dosyanın işi tamamlayıcı: ölçülerek bulunmuş yapıyı DONDURMAK. Bir
 * bileşen ölçülmüş sınıfını bırakırsa (adres yeniden sarmaya başlar, tablo
 * yeniden çıplak kalır, ad yeniden tabansız esner) tarayıcı kapısı bunu
 * ancak bir sonraki tam ölçümde görür; burası aynı gün kırılır. Sınıflar
 * elle yazılmış bir kopyadan değil, GERÇEK render'dan okunur
 * (`touch-target.contract.test.tsx` deseni).
 *
 * Requirement IDs: DS-NARROW-LAYOUT-01 … DS-NARROW-LAYOUT-09.
 *
 * ═══ 08 VE 09 SAHTE-YERELLEŞTİRMEDEN GELDİ ═══
 *
 * `docs/121` §4: katalog metni gerçek bir dile çevrilmeden mekanik olarak
 * uzatılır ve mobil denetim aynı 320 pikselde yeniden koşar. Uzayan metnin
 * kırdığı düzen (Ö7) bugünden görünür — Almanca tek kelime yazılmadan.
 * Aşağıdaki iki madde o koşunun bulduğu iki kusurun yapı karşılığıdır.
 */

function classesOf(element: Element | null): Set<string> {
    expect(element, 'öğe gerçek DOM içinde bulunamadı').not.toBeNull();

    // SVG'de `className` bir dizge değil; öznitelik her öğede aynı okunur.
    return new Set((element!.getAttribute('class') ?? '').split(/\s+/).filter(Boolean));
}

function expectClass(element: Element | null, wanted: string, requirement: string) {
    expect(
        classesOf(element).has(wanted),
        `${requirement}: \`${wanted}\` taşımıyor — ölçülmüş yerleşim bırakılmış.`,
    ).toBe(true);
}

function expectNoClass(element: Element | null, banned: string, requirement: string) {
    expect(
        classesOf(element).has(banned),
        `${requirement}: \`${banned}\` geri gelmiş — ölçülmüş kusur geri döner.`,
    ).toBe(false);
}

const HOURS = (overrides: Record<number, number | null> = {}): (number | null)[] =>
    Array.from({ length: 24 }, (_, hour) => (hour in overrides ? overrides[hour] : 0));

function renderHeatGrid() {
    return render(
        <HeatGrid
            rows={[
                { label: 'Pazartesi', values: HOURS({ 12: 4 }) },
                { label: 'Salı', values: HOURS({ 13: 30, 3: null }) },
            ]}
            description="Saatlere göre yoğunluk"
            columnLabel="Gün"
            hourLabel={(hour) => `${String(hour).padStart(2, '0')}:00`}
            withheldLabel="gizlendi"
        />,
    );
}

describe('DS-NARROW-LAYOUT — ısı ızgarası (M8)', () => {
    it('ekran okuyucu tablosu bir kapta gizlenir; tablo belgeyi genişletemez', () => {
        /*
            Ölçüldü (320×568): `sr-only` doğrudan tablodaydı ve tablo 1
            piksele sığmadı — 1132 piksel, belge yana kaydı (K4, dört
            hikâye). Bir `div` sığar ve taşanı kırpar.
        */
        const { container } = renderHeatGrid();
        const table = container.querySelector('table');

        expect(table, 'DS-NARROW-LAYOUT-01: tablo yok').not.toBeNull();
        expectNoClass(table, 'sr-only', 'DS-NARROW-LAYOUT-01');
        expectClass(table!.parentElement, 'sr-only', 'DS-NARROW-LAYOUT-01');
        // Ekran okuyucu için hiçbir şey değişmedi: tablo ve başlığı yerinde.
        expect(screen.getByRole('table')).toHaveAccessibleName('Saatlere göre yoğunluk');
    });

    it('çizim kendi kaydırma kabında durur ve hücre tabanının altına inmez', () => {
        const { container } = renderHeatGrid();
        const scroller = container.querySelector('[data-role="heat-scroller"]');

        expectClass(scroller, 'overflow-x-auto', 'DS-NARROW-LAYOUT-02');
        expectClass(scroller, 'overscroll-x-contain', 'DS-NARROW-LAYOUT-02');
        expectClass(
            container.querySelector('svg'),
            'min-w-[calc(24*var(--space-2))]',
            'DS-NARROW-LAYOUT-02',
        );
        // Gün adları kayarken yerinde kalır; saat ekseni aynı kabın içindedir.
        expectClass(
            container.querySelector('[data-role="heat-days"]'),
            'sticky',
            'DS-NARROW-LAYOUT-02',
        );
        expect(scroller!.contains(container.querySelector('[data-role="heat-hours"]'))).toBe(true);
    });

    it('kaydırma ipucu hover değil, kenardaki solmadır — ve saat etiketi kesilmez', () => {
        /*
            Dokunmada üzerine gelme yoktur. İpucu iki kenar solmasıdır ve
            görünürlüğü kaydırma konumundan gelir; jsdom'da düzen olmadığı
            için ikisi de sönük doğar — bu doğru, çünkü orada taşma da yok.
        */
        const { container } = renderHeatGrid();
        const edges = container.querySelectorAll('[data-role="heat-scroll-edge"]');

        expect(edges).toHaveLength(2);
        for (const edge of edges) {
            expect(edge.getAttribute('aria-hidden')).toBe('true');
            expectClass(edge, 'pointer-events-none', 'DS-NARROW-LAYOUT-03');
            expectClass(edge, 'opacity-0', 'DS-NARROW-LAYOUT-03');
        }

        // 320 pikselde sütun 11 piksel: "00:00" kesilirse "0." okunur.
        const hours = container.querySelector('[data-role="heat-hours"]');
        for (const label of hours!.querySelectorAll('span')) {
            expectClass(label, 'whitespace-nowrap', 'DS-NARROW-LAYOUT-03');
            expectNoClass(label, 'truncate', 'DS-NARROW-LAYOUT-03');
        }
    });
});

describe('DS-NARROW-LAYOUT — karekod satırı (M6)', () => {
    const item = {
        id: 4021,
        workspaceId: 7,
        locationId: 3,
        menuId: 11,
        token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
        resolverUrl: 'https://zabuno.com/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
        tableName: 'T12',
        areaLabel: 'Bahçe',
        destinationType: 'published_menu',
        state: 'active',
    };

    it('adres sıkışır, kopyala düğmesi sıkışmaz; satır sarmaz', () => {
        /*
            Ölçüldü (320×568): adres 182 + düğme 101 piksel, satıra üç
            piksel sığmıyor ve düğme kendi satırına düşüyordu — her kodda
            44 piksel fazladan boy.
        */
        render(
            <ul>
                <QrCodeListItem item={item} onDisable={vi.fn()} onEnable={vi.fn()} />
            </ul>,
        );

        const link = screen.getByRole('link', { name: /zabuno\.com/ });
        expectClass(link, 'min-w-0', 'DS-NARROW-LAYOUT-04');
        expectClass(link, 'truncate', 'DS-NARROW-LAYOUT-04');
        expectNoClass(link.parentElement, 'flex-wrap', 'DS-NARROW-LAYOUT-04');
        expectClass(
            screen.getByRole('button', { name: 'Copy link' }),
            'shrink-0',
            'DS-NARROW-LAYOUT-04',
        );
    });

    it('şube seçici tam satırdır; Taşı ve Vazgeç birlikte durur', () => {
        render(
            <ul>
                <QrCodeListItem
                    item={item}
                    onDisable={vi.fn()}
                    onEnable={vi.fn()}
                    moving
                    otherLocations={[{ id: 2, displayName: 'Beşiktaş' }]}
                    onRetarget={vi.fn()}
                    onCancelMove={vi.fn()}
                />
            </ul>,
        );

        const select = screen.getByRole('combobox', { name: 'Move this code to' });
        expectClass(select, 'basis-full', 'DS-NARROW-LAYOUT-05');
        // İki düğme aynı sarmalı kapta, seçicinin ARDINDAN gelir.
        const move = screen.getByRole('button', { name: 'Move' });
        const cancel = screen.getByRole('button', { name: 'Cancel' });
        expect(move.parentElement).toBe(select.parentElement);
        expect(cancel.parentElement).toBe(select.parentElement);
        expect(
            select.compareDocumentPosition(move) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });
});

describe('DS-NARROW-LAYOUT — sayfa başlığı (M9)', () => {
    it('başlık bloğunun aralığı ölü alan ölçeğini okur; tavan bugünkü adımdır', () => {
        /*
            `gap-3` sabit 12 pikseldi. Dar ekranda ölçek daralır; `min()`
            masaüstünü 12'de tutar — masaüstü görünümü değişmez, kazanılan
            yer yalnız dar ekranda geri döner.
        */
        const { container } = render(
            <PageHeader
                title="Orders"
                description="All orders today."
                breadcrumbs={{
                    label: 'Breadcrumb',
                    emptyLabel: 'Empty breadcrumb trail',
                    items: [{ key: 'home', label: 'Home', href: '#' }],
                }}
                actions={<button type="button">Export</button>}
            />,
        );

        const rhythm = 'gap-[min(var(--space-3),var(--space-fluid-sm))]';
        const root = container.firstElementChild;
        expectClass(root, rhythm, 'DS-NARROW-LAYOUT-06');
        expectNoClass(root, 'gap-3', 'DS-NARROW-LAYOUT-06');
        expectClass(
            screen.getByRole('heading', { name: 'Orders' }).parentElement!.parentElement,
            rhythm,
            'DS-NARROW-LAYOUT-06',
        );
    });
});

describe('DS-NARROW-LAYOUT — menü kataloğu ürün satırı (M7)', () => {
    it('görsel + ad bir öbek, fiyat ve eylemler ikinci öbek; öbekler geniş ekranda ızgaraya erir', async () => {
        /*
            Ölçüldü (320×568): ürün adının "yeniden adlandır" düğmesi 12
            piksel genişliğindeydi — `flex-1`, sarmalı satırda adın esneme
            tabanını sıfırlıyordu. Ad artık görselle birlikte kendi öbeğinde
            ve ilk satırın tamamını alır. `sm:contents` masaüstü sütun
            ritmini olduğu gibi bırakır.
        */
        const jsonResponse = (status: number, body: unknown): Response =>
            ({
                ok: status < 300,
                status,
                headers: new Headers(),
                json: async () => body,
            }) as Response;

        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                if (String(url).endsWith('/brand')) {
                    return jsonResponse(200, { id: 1, workspaceId: 7, currency: 'TRY' });
                }
                if (String(url).endsWith('/locations/3/menu')) {
                    return jsonResponse(200, {
                        id: 42,
                        workspaceId: 7,
                        locationId: 3,
                        name: 'Ana Menü',
                        state: 'draft',
                        categories: [
                            {
                                id: 5,
                                menuId: 42,
                                name: 'Başlangıçlar',
                                position: 0,
                                menuItems: [
                                    {
                                        id: 11,
                                        categoryId: 5,
                                        productId: 9,
                                        productName: 'Mercimek Çorbası',
                                        priceMinorAmount: 4250,
                                        currencyCode: 'TRY',
                                        position: 0,
                                        isVisible: true,
                                        allergens: [],
                                    },
                                ],
                            },
                        ],
                    });
                }

                return jsonResponse(200, { data: [] });
            }),
        );

        render(<MenuCatalogWorkspace workspaceId={7} locationId={3} />);

        const rename = await screen.findByRole('button', { name: 'Rename Mercimek Çorbası' });
        const thumb = screen.getByRole('button', { name: 'Open Mercimek Çorbası' });
        const price = screen.getByRole('button', { name: 'Edit price for Mercimek Çorbası' });

        const lead = thumb.parentElement;
        expectClass(lead, 'basis-full', 'DS-NARROW-LAYOUT-07');
        expectClass(lead, 'sm:contents', 'DS-NARROW-LAYOUT-07');
        expect(lead!.contains(rename), 'DS-NARROW-LAYOUT-07: ad, görselin öbeğinde değil').toBe(
            true,
        );

        const trail = price.parentElement;
        expect(trail, 'DS-NARROW-LAYOUT-07: fiyat öbeksiz').not.toBe(lead);
        expectClass(trail, 'basis-full', 'DS-NARROW-LAYOUT-07');
        expectClass(trail, 'sm:contents', 'DS-NARROW-LAYOUT-07');
        // Ad artık tabansız esnemez: `flex-1` kendi öbeğinin içindedir, satırın değil.
        expectClass(rename.parentElement, 'flex-1', 'DS-NARROW-LAYOUT-07');

        vi.unstubAllGlobals();
    });
});

describe('DS-NARROW-LAYOUT — çubuk+çizgi grafiği (docs/121 Ö7)', () => {
    it('ekran okuyucu tablosu bir kapta gizlenir; uzayan başlık belgeyi genişletemez', () => {
        /*
            Sahte-yerelleştirilmiş katalogla ölçüldü (320×568): `sr-only`
            doğrudan tablonun üstündeydi ve bir tablo 1 piksele SIĞMAZ —
            CSS genişliği tabloda tavan değil TABANDIR. Başlık uzayınca tablo
            326–335 piksele çıktı ve belgeyi yana kaydırdı (beş hikâye).
            Isı ızgarasında aynı kusur, aynı çözüm (DS-NARROW-LAYOUT-01).
        */
        const { container } = render(
            <TrendChart
                points={[
                    { label: '29 Ağu', primary: 12, secondary: 9 },
                    { label: '30 Ağu', primary: 31, secondary: 25 },
                ]}
                primaryLabel="Taramalar"
                secondaryLabel="Menü açılışları"
                columnLabel="Gün"
                description="Son 7 günde tarama ve menü açılışı"
            />,
        );

        const table = container.querySelector('table');

        expect(table, 'DS-NARROW-LAYOUT-08: tablo yok').not.toBeNull();
        expectNoClass(table, 'sr-only', 'DS-NARROW-LAYOUT-08');
        expectClass(table!.parentElement, 'sr-only', 'DS-NARROW-LAYOUT-08');
        // Ekran okuyucu için hiçbir şey değişmedi.
        expect(screen.getByRole('table')).toHaveAccessibleName(
            'Son 7 günde tarama ve menü açılışı',
        );
    });
});

describe('DS-NARROW-LAYOUT — menü hapı (docs/121 Ö7)', () => {
    it('hap uzayan metinle daralır ve sarar; dokunma hedefi küçülmez', () => {
        /*
            Sahte-yerelleştirilmiş katalogla ölçüldü (320×568): hap `shrink-0`
            ile küçülemiyor, sarma izni olmadığı için de tek satırda kalıyordu.
            Üç parça (ad + saat ipucu + "şimdi açık") uzayınca kutu 340–376
            piksele çıktı; kendisi kenardan kırpıldı VE belgeyi kaydırdı
            (üç hikâye, altı bulgu).

            Asgari boy DEĞİŞMEZ: sarma boyu yalnız artırır. Büyük hedef,
            sıkı boşluk.
        */
        render(
            <MenuScreenActions
                label="Menu actions"
                menusLabel="Menus at this location"
                menus={[
                    {
                        id: 1,
                        name: 'Ana menü',
                        hint: '11:00–07:00',
                        isSelected: true,
                        isServingNow: true,
                    },
                ]}
                onSelectMenu={vi.fn()}
                addMenuLabel="New menu"
                onAddMenu={null}
                editMenuLabel="Edit menu"
                onEditMenu={null}
                servingNowLabel="open now"
                photoImport={null}
                csvLabel="CSV"
                onCsv={null}
                previewAndPublishLabel="Preview & publish"
                onPreviewAndPublish={null}
                addProductLabel="Add product"
                onAddProduct={null}
            />,
        );

        const pill = screen.getByRole('button', { name: /Ana menü/ });

        expectNoClass(pill, 'shrink-0', 'DS-NARROW-LAYOUT-09');
        expectClass(pill, 'max-w-full', 'DS-NARROW-LAYOUT-09');
        expectClass(pill, 'min-w-0', 'DS-NARROW-LAYOUT-09');
        expectClass(pill, 'flex-wrap', 'DS-NARROW-LAYOUT-09');
        // Hedefin asgari boyu yerinde: sarma boyu artırır, azaltmaz.
        expectClass(pill, 'min-h-[var(--control-height)]', 'DS-NARROW-LAYOUT-09');
        // Sarılan satırlar arası boşluk ölçeğin en dar adımı.
        expectClass(pill, 'gap-y-[var(--space-1)]', 'DS-NARROW-LAYOUT-09');

        // Ad da kendi kutusunda daralabilir; tek uzun kelime hapı taşırmaz.
        const name = screen.getByRole('heading', { name: 'Ana menü' });
        expectClass(name, 'min-w-0', 'DS-NARROW-LAYOUT-09');
        expectClass(name, 'break-words', 'DS-NARROW-LAYOUT-09');
    });
});
