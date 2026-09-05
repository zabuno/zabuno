import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MenuEngineeringRegion } from './MenuEngineeringRegion';

/**
 * MENÜ MÜHENDİSLİĞİ — `docs/84` (P1-08).
 *
 * Sahip "menümde ne işe yarıyor?" diye sorar; bugüne kadarki cevap "menün
 * 214 kez açıldı"ydı ve bu, menüyü DEĞİŞTİRMEK için hiçbir şey söylemez.
 */
function jsonResponse(body: unknown): Response {
    return {
        ok: true,
        status: 200,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function mount(body: unknown) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => jsonResponse(body)),
    );

    render(<MenuEngineeringRegion workspaceId={7} range="30d" />);
}

describe('menü mühendisliği (docs/84)', () => {
    it('en çok ve hiç bakılmayan ürünleri ayrı ayrı gösterir', async () => {
        mount({
            state: 'ready',
            threshold: 5,
            observedViewers: 9,
            mostViewed: [
                { menuItemId: 1, productName: 'Levrek', categoryName: 'Balıklar', viewers: 8 },
            ],
            neverViewed: [
                { menuItemId: 3, productName: 'Hamsi', categoryName: 'Balıklar', viewers: 0 },
            ],
            searchesWithNoResults: [{ term: 'karides güveç', searches: 4 }],
        });

        expect(await screen.findByText('Levrek')).toBeInTheDocument();
        expect(screen.getByText('8 visitors')).toBeInTheDocument();

        // Olayın YOKLUĞU da bir cevaptır: hiç bakılmayan ürün listelenir.
        expect(screen.getByText('Hamsi')).toBeInTheDocument();

        // Sahibin göremediği tek talep: menüde OLMAYAN şeyin talebi.
        expect(screen.getByText('karides güveç')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('veri yetersizse boş tablo değil, sebep ve eşik gösterir', async () => {
        mount({
            state: 'not_enough_data',
            threshold: 5,
            observedViewers: 2,
            mostViewed: [],
            neverViewed: [],
            searchesWithNoResults: [],
        });

        // Boş bir tablo, sahibe "ürünüm bozuk" dedirtir.
        expect(
            await screen.findByText(
                'Not enough visitors yet to rank your dishes: 2 of 5. Keep the menu published and check back.',
            ),
        ).toBeInTheDocument();

        expect(screen.queryByText('Most looked at')).toBeNull();

        vi.unstubAllGlobals();
    });

    /**
     * "HİÇ BAKILMAYAN" SATIRI BİR YERE GÖTÜRÜR — kanonik kaynak
     * (`docs/reference/panel-v3/panel-v3.1.dc.html`, Insights: `neverViewed`
     * satırındaki düğme).
     *
     * Satır bugüne kadar yalnız bir gözlemdi: "Hamsi'ye kimse bakmadı." Sahip
     * bunu okuyup ne yapacağını biliyordu ama nereye gideceğini bilmiyordu —
     * ürünü gizlemek, adını değiştirmek ya da fiyatını düşürmek menü
     * ekranının işi ve o ekran buradan üç tıklama uzaktaydı.
     *
     * Kaynak düğmeye "Gizle" yazıyor; bu ekran gizlemez, gizlenecek yere
     * götürür ve etiketi de bunu söyler. Bastığı düğmenin adı yalan olan bir
     * liste, ölçüme duyulan güveni tek tıkta harcar.
     */
    it('hiç bakılmayan ürünü menü ekranına götürür ve düğme adı ne yaptığını söyler', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();
        const onReviewProduct = vi.fn();

        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                jsonResponse({
                    state: 'ready',
                    threshold: 5,
                    observedViewers: 9,
                    mostViewed: [
                        {
                            menuItemId: 1,
                            productName: 'Levrek',
                            categoryName: 'Balıklar',
                            viewers: 8,
                        },
                    ],
                    neverViewed: [
                        {
                            menuItemId: 3,
                            productName: 'Hamsi',
                            categoryName: 'Balıklar',
                            viewers: 0,
                        },
                    ],
                    searchesWithNoResults: [],
                }),
            ),
        );

        render(
            <MenuEngineeringRegion workspaceId={7} range="30d" onReviewProduct={onReviewProduct} />,
        );

        const button = await screen.findByRole('button', { name: /Edit “Hamsi” on the menu/ });

        await user.click(button);

        expect(onReviewProduct).toHaveBeenCalledWith('Hamsi');

        vi.unstubAllGlobals();
    });

    /**
     * Gidecek yeri olmayan düğme HİÇ ÇİZİLMEZ. Basıldığında hiçbir şey
     * yapmayan bir düğme, kullanıcıya olmayan bir yol göstermektir.
     */
    it('hedef verilmediğinde hiç bakılmayan satırına düğme koymaz', async () => {
        mount({
            state: 'ready',
            threshold: 5,
            observedViewers: 9,
            mostViewed: [],
            neverViewed: [
                { menuItemId: 3, productName: 'Hamsi', categoryName: 'Balıklar', viewers: 0 },
            ],
            searchesWithNoResults: [],
        });

        expect(await screen.findByText('Hamsi')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /on the menu/ })).toBeNull();

        vi.unstubAllGlobals();
    });

    it('yükleme başarısızsa sessiz kalınmaz', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({ ok: false, status: 500, headers: new Headers() }) as Response),
        );

        render(<MenuEngineeringRegion workspaceId={7} range="30d" />);

        expect(await screen.findByText('Menu figures could not be loaded.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
