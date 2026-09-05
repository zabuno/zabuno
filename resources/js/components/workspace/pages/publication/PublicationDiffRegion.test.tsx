import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { buildPublicationDiff, PublicationDiffRegion } from './PublicationDiffRegion';
import type { CurrentPublication } from './PublicationStatusRegion';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * YAYINLANACAK DEĞİŞİKLİK LİSTESİ — kanonik kaynak
 * `docs/reference/panel-v3/panel.dc.html`, `data-screen-label="Yayınlama"`
 * ("Yayınlanacak 3 değişiklik", `v14 → v15`).
 *
 * NEDEN BU TESTLER ÖNCE YAZILDI: bugün ekran "Yayınla" düğmesini, sahibin
 * ne yayınlayacağını SÖYLEMEDEN gösteriyor. Restoran sahibinin gerçek
 * yolculuğu şudur: sabah Mehmet Usta Adana Kebap'ın fiyatını 300'den 320'ye
 * çekmiştir, akşam sahip panele girer ve "şu an basarsam misafir ne görecek?"
 * diye sorar. Bugünkü ekranın bu soruya cevabı yok — sahip ya kör basar ya
 * da hiç basmaz. İkisi de üründe gördüğümüz en pahalı arızadır.
 *
 * Fark UYDURULMAZ: iki gerçek veriden üretilir — sunucudan gelen yayınlanmış
 * snapshot (`publications/current`) ile paneldeki taslak ağaç. "Kim değiştirdi
 * ve ne zaman" kaynakta yazıyor ama bu depoda menü satırı başına aktör/zaman
 * kaydı YOKTUR; o satır bilerek çizilmez ve bu testler onu ARAMAZ. Uydurulmuş
 * bir "Mehmet Usta · 1,5 saat önce" satırı, gerçek olanından daha zararlıdır.
 */
function draftTree(): DashboardMenuTree {
    return {
        id: 42,
        workspaceId: 71,
        locationId: 923,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: 42,
                name: 'Kebaplar',
                position: 0,
                menuItems: [
                    {
                        // Fiyatı DEĞİŞMİŞ satır: yayında 300,00 ₺, taslakta 320,00 ₺.
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 32000,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                    {
                        // GİZLENMİŞ satır: yayında görünüyor, taslakta kapalı.
                        id: 102,
                        categoryId: 5,
                        productId: 902,
                        productName: 'Ayran',
                        priceMinorAmount: 4000,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: false,
                    },
                    {
                        // ADI DEĞİŞMİŞ satır: aynı menü satırı kimliği, yeni ad.
                        id: 103,
                        categoryId: 5,
                        productId: 903,
                        productName: 'Beyti (acılı)',
                        priceMinorAmount: 45000,
                        currencyCode: 'TRY',
                        position: 2,
                        allergens: [],
                        isVisible: true,
                    },
                ],
            },
            {
                id: 6,
                menuId: 42,
                name: 'Tatlılar',
                position: 1,
                menuItems: [
                    {
                        // YENİ satır: yayında hiç yok.
                        id: 201,
                        categoryId: 6,
                        productId: 904,
                        productName: 'Künefe',
                        priceMinorAmount: 24000,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

function published(): CurrentPublication {
    return {
        id: 800,
        workspaceId: 71,
        menuId: 42,
        locationId: 923,
        version: 14,
        state: 'published',
        publishedAt: '2026-09-03T09:00:00Z',
        snapshot: {
            categories: [
                {
                    name: 'Kebaplar',
                    menuItems: [
                        {
                            menuItemId: 101,
                            productName: 'Adana Kebap',
                            priceMinorAmount: 30000,
                            currencyCode: 'TRY',
                        },
                        {
                            menuItemId: 102,
                            productName: 'Ayran',
                            priceMinorAmount: 4000,
                            currencyCode: 'TRY',
                        },
                        {
                            menuItemId: 103,
                            productName: 'Beyti',
                            priceMinorAmount: 45000,
                            currencyCode: 'TRY',
                        },
                        {
                            // Taslakta HİÇ YOK: satır silinmiş.
                            menuItemId: 104,
                            productName: 'Tavuk Şiş',
                            priceMinorAmount: 28000,
                            currencyCode: 'TRY',
                        },
                    ],
                },
            ],
        },
    };
}

function region(): HTMLElement {
    return screen.getByRole('region', { name: /changes waiting to be published/i });
}

describe('buildPublicationDiff — fark GERÇEK iki veriden üretilir', () => {
    it('fiyat, ad, gizlenen, silinen ve eklenen satırları ayrı ayrı ayırt eder', () => {
        const changes = buildPublicationDiff(draftTree(), published().snapshot);

        expect(changes.map((change) => `${change.kind}:${change.productName}`)).toEqual([
            'price:Adana Kebap',
            'renamed:Beyti (acılı)',
            'hidden:Ayran',
            'removed:Tavuk Şiş',
            'added:Künefe',
        ]);
    });

    it('fiyat değişiminde ESKİ ve YENİ tutarı birlikte taşır', () => {
        const change = buildPublicationDiff(draftTree(), published().snapshot).find(
            (candidate) => candidate.kind === 'price',
        );

        expect(change?.previousPrice).toEqual({ minorAmount: 30000, currencyCode: 'TRY' });
        expect(change?.nextPrice).toEqual({ minorAmount: 32000, currencyCode: 'TRY' });
    });

    it('hiç yayın yokken görünür her satır EKLENEN sayılır', () => {
        /*
            İlk yayında karşılaştıracak bir şey yoktur; "hiç değişiklik yok"
            demek, sahibin ilk menüsünü boşluğa yayınladığını sanmasına yol
            açardı.
        */
        const changes = buildPublicationDiff(draftTree(), null);

        expect(changes.every((change) => change.kind === 'added')).toBe(true);
        expect(changes.map((change) => change.productName)).toEqual([
            'Adana Kebap',
            'Beyti (acılı)',
            'Künefe',
        ]);
    });

    it('taslak ile yayın aynıysa hiçbir değişiklik üretmez', () => {
        const tree = draftTree();
        const current = published();

        // Taslağı yayının aynısına indir: tek görünür satır, aynı ad, aynı fiyat.
        tree.categories = [
            {
                id: 5,
                menuId: 42,
                name: 'Kebaplar',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 30000,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                ],
            },
        ];
        current.snapshot.categories = [
            {
                name: 'Kebaplar',
                menuItems: [
                    {
                        menuItemId: 101,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 30000,
                        currencyCode: 'TRY',
                    },
                ],
            },
        ];

        expect(buildPublicationDiff(tree, current.snapshot)).toEqual([]);
    });

    it('menü satırı kimliği taşımayan ESKİ bir yayında ada göre eşleştirir', () => {
        /*
            `menuItemId` snapshot'a sonradan eklendi (`docs/82`). Onsuz
            yayınlanmış bir sürümde kimlikle eşleştirmek, menünün TAMAMINI
            "silindi + eklendi" diye gösterirdi: kırk satırlık bir menüde
            seksen satırlık sahte bir fark.
        */
        const changes = buildPublicationDiff(draftTree(), {
            categories: [
                {
                    name: 'Kebaplar',
                    menuItems: [
                        {
                            productName: 'Adana Kebap',
                            priceMinorAmount: 30000,
                            currencyCode: 'TRY',
                        },
                    ],
                },
            ],
        });

        expect(changes.filter((change) => change.kind === 'price')).toHaveLength(1);
        expect(
            changes.some(
                (change) => change.productName === 'Adana Kebap' && change.kind === 'removed',
            ),
        ).toBe(false);
    });
});

describe('PublicationDiffRegion — sahip ne yayınlayacağını okur', () => {
    it('erişilebilir bir bölge ve sürüm geçişini v14 → v15 olarak yazar', () => {
        render(<PublicationDiffRegion dashboardMenuTree={draftTree()} current={published()} />);

        expect(within(region()).getByText(/v14\s*→\s*v15/)).toBeInTheDocument();
    });

    it('başlıkta bekleyen değişiklik SAYISINI verir', () => {
        render(<PublicationDiffRegion dashboardMenuTree={draftTree()} current={published()} />);

        expect(
            within(region()).getByText(/5 changes waiting to be published/i),
        ).toBeInTheDocument();
    });

    it('fiyat satırını "eski → yeni" olarak, tek satırda gösterir', () => {
        render(<PublicationDiffRegion dashboardMenuTree={draftTree()} current={published()} />);

        const row = within(region())
            .getAllByRole('listitem')
            .find((item) => /Adana Kebap/.test(item.textContent ?? '')) as HTMLElement;

        expect(row.textContent ?? '').toMatch(/300/);
        expect(row.textContent ?? '').toMatch(/320/);
        expect(row.textContent ?? '').toMatch(/→/);
    });

    it('her satırın TÜRÜ metinle de yazar — renk tek kanal değildir (WCAG 1.4.1)', () => {
        /*
            Eklenen yeşil, çıkarılan kırmızı bir noktayla anlatılsaydı, renk
            körü bir sahip "Ayran eklendi" ile "Ayran çıkarıldı" arasındaki
            farkı göremezdi; ikisi de menüde onun için ters yönde çalışır.
        */
        render(<PublicationDiffRegion dashboardMenuTree={draftTree()} current={published()} />);

        const text = region().textContent ?? '';

        expect(text).toMatch(/Price/i);
        expect(text).toMatch(/Added/i);
        expect(text).toMatch(/Hidden from guests/i);
        expect(text).toMatch(/Removed/i);
        expect(text).toMatch(/Name/i);
    });

    it('bekleyen değişiklik yokken misafirin HANGİ sürümü gördüğünü söyler', () => {
        const tree = draftTree();
        tree.categories = [];

        render(
            <PublicationDiffRegion
                dashboardMenuTree={tree}
                current={{
                    ...published(),
                    snapshot: { categories: [] },
                }}
            />,
        );

        expect(within(region()).getByText(/guests are seeing v14/i)).toBeInTheDocument();
        expect(within(region()).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('UYDURULMUŞ aktör veya zaman satırı çizmez', () => {
        /*
            Bu depoda menü satırı başına "kim değiştirdi, ne zaman" kaydı
            yoktur. Kaynaktaki "Fiyat · Mehmet Usta · 1,5 saat önce" satırının
            ikinci ve üçüncü parçası bugün üretilemez; üretilemeyen bir şeyi
            çizmek, sahibe var olmayan bir denetim izi vaat etmektir.
        */
        render(<PublicationDiffRegion dashboardMenuTree={draftTree()} current={published()} />);

        expect(region().textContent ?? '').not.toMatch(/usta|hours ago|minutes ago|ago\b/i);
    });

    it('sabit piksel veya kırılım noktası sınıfı taşımaz', () => {
        const { container } = render(
            <PublicationDiffRegion dashboardMenuTree={draftTree()} current={published()} />,
        );

        const classNames = Array.from(container.querySelectorAll<HTMLElement>('*'))
            .map((element) => (typeof element.className === 'string' ? element.className : ''))
            .join(' ');

        expect(classNames).not.toMatch(/(^|\s)(sm|md|lg|xl|2xl):/);
        expect(classNames).not.toMatch(/\[\d+px\]/);
        expect(classNames).not.toMatch(/font-semibold/);
        expect(classNames).not.toMatch(/rounded-full/);
        expect(classNames).not.toMatch(/\b(ml|mr|pl|pr)-/);
    });
});
