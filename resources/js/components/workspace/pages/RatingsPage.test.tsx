import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { RatingsPage } from './RatingsPage';
import type { DashboardMenuTree } from './DashboardPage';

/**
 * Y4 RED — SAHİBİN PUAN EKRANI (`docs/122` Y4, `docs/116` P5/P6/Ö3).
 *
 * Uç aylardır ayakta; ekran yoktu. Yani misafir masada oy veriyor, sahip
 * onu HİÇBİR YERDE göremiyordu. Bu paketin işi o boşluğu kapatmak, ama
 * kapatırken puanlamanın üç kemik kuralını ekranda da tutmak:
 *
 * 1. EŞİK ALTINDA PUAN GÖSTERİLMEZ ve yerine SIFIR YILDIZ konmaz. Sıfır bir
 *    ölçümdür, bilinmeyenin yerine geçemez.
 * 2. HER PUAN KENDİ ALGORİTMA SÜRÜMÜNÜ VE HESAPLANMA ANINI TAŞIR (Ö3).
 *    "Bu ürünün puanı neden düştü?" sorusunun cevabı orada: yeni oy mu
 *    geldi, kural mı değişti.
 * 3. SAHİP YANIT VERİR, PUANI KALDIRAMAZ. Kaldırılabilen bir ortalama bir
 *    ölçüm değil, bir reklamdır.
 *
 * Requirement ID'leri: RATING-SCREEN-BELOW-THRESHOLD-01,
 * RATING-SCREEN-COUNT-VISIBLE-02, RATING-SCREEN-VERSION-03,
 * RATING-SCREEN-REPLY-04, RATING-SCREEN-NO-DELETE-05.
 */

const WORKSPACE_ID = 7;
const MENU_ID = 31;

function menuTree(): DashboardMenuTree {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: 3,
        name: 'Akşam menüsü',
        state: 'published',
        categories: [],
    };
}

type Row = {
    menuItemId: number;
    productId: number;
    productName: string;
    score: number | null;
    scaleMax: number;
    signalCount: number;
    meetsDisplayThreshold: boolean;
    computedAt: string | null;
    reply: { body: string; publishedAt: string | null } | null;
};

function row(overrides: Partial<Row> = {}): Row {
    return {
        menuItemId: 101,
        productId: 501,
        productName: 'Adana Kebap',
        score: 4.2,
        scaleMax: 5,
        signalCount: 42,
        meetsDisplayThreshold: true,
        computedAt: '2026-09-05T09:00:00+00:00',
        reply: null,
        ...overrides,
    };
}

function listResponse(rows: Row[]) {
    return {
        ok: true,
        status: 200,
        json: async () => ({
            data: rows,
            algorithmVersion: 'v3',
            scaleMax: 5,
        }),
    } as unknown as Response;
}

function okResponse(body: Record<string, unknown> = {}) {
    return { ok: true, status: 200, json: async () => body } as unknown as Response;
}

function renderPage(rows: Row[]) {
    vi.stubGlobal(
        'fetch',
        vi.fn((input: RequestInfo | URL) =>
            Promise.resolve(
                String(input).includes('/ratings') && !String(input).includes('/reply')
                    ? listResponse(rows)
                    : okResponse(),
            ),
        ),
    );

    render(
        <RatingsPage
            workspaceId={WORKSPACE_ID}
            menuTree={menuTree()}
            can={() => true}
            onNavigateToSection={vi.fn()}
        />,
    );
}

describe('RatingsPage', () => {
    beforeEach(() => {
        vi.stubGlobal('document', document);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    // --- RATING-SCREEN-BELOW-THRESHOLD-01 ---------------------------------

    it('draws no score under the threshold, and no zero stars either', async () => {
        /*
            Sunucu eşik altında `score: null` gönderir; ekran da o `null`ı
            SIFIRA ÇEVİRMEZ. Çevirseydi hiç oy almamış her yeni ürün menünün
            en kötüsü gibi görünürdü — ve sahip onu menüden kaldırırdı.
        */
        renderPage([row({ score: null, meetsDisplayThreshold: false, signalCount: 2 })]);

        expect(await screen.findByText('Not enough ratings yet')).toBeTruthy();
        expect(screen.queryByText(/out of 5/)).toBeNull();
        expect(screen.queryByText(/^0/)).toBeNull();
    });

    // --- RATING-SCREEN-COUNT-VISIBLE-02 -----------------------------------

    it('still tells the owner how many votes came in below the threshold', async () => {
        /*
            Gizlenen şey PUAN, yani henüz güvenilmeyen türetilmiş değerdir.
            Kaç oy geldiği bilinen bir ölçümdür ve sahibin "eşiğe ne kadar
            kaldı?" sorusunun tek cevabıdır.
        */
        renderPage([row({ score: null, meetsDisplayThreshold: false, signalCount: 2 })]);

        expect(await screen.findByText('Votes so far: 2')).toBeTruthy();
    });

    it('shows the score itself once the threshold is met', async () => {
        renderPage([row()]);

        expect(await screen.findByText('4.2 out of 5')).toBeTruthy();
    });

    // --- RATING-SCREEN-VERSION-03 -----------------------------------------

    it('says which method produced these numbers and when they were worked out', async () => {
        /*
            `docs/116` Ö3. Sürüm görünmezse "bu puan neden düştü — kural mı
            değişti, oy mu geldi?" sorusunun cevabı hiçbir yerde yoktur.
        */
        renderPage([row()]);

        expect(await screen.findByText('Scoring method v3')).toBeTruthy();
        expect(screen.getAllByText(/Worked out /).length).toBeGreaterThan(0);
    });

    it('says a score has not been worked out yet instead of inventing a time', async () => {
        renderPage([row({ computedAt: null, score: null, meetsDisplayThreshold: false })]);

        expect(await screen.findByText('Not worked out yet')).toBeTruthy();
    });

    // --- RATING-SCREEN-REPLY-04 -------------------------------------------

    it('publishes the owner reply against the product, not the menu row', async () => {
        /*
            OKUMA ADRESİ MENÜYE DAYANIR, YANIT ADRESİ ÜRÜNE — aynı tabak iki
            menüde birden olabilir ve restoranın onun hakkında söylediği söz
            tektir (`routes/api/rating.php`).
        */
        const user = userEvent.setup();
        renderPage([row()]);

        const box = await screen.findByLabelText('Your reply');
        await user.type(box, 'Tarifi değiştirdik, tekrar deneyin.');
        await user.click(screen.getByRole('button', { name: 'Publish reply' }));

        await waitFor(() => {
            const call = vi
                .mocked(fetch)
                .mock.calls.find(([url]) => String(url).includes('/reply'));

            expect(call).toBeTruthy();
            expect(String(call?.[0])).toBe(
                `/api/workspaces/${String(WORKSPACE_ID)}/ratings/products/501/reply`,
            );
            expect((call?.[1] as RequestInit | undefined)?.method).toBe('PUT');
        });
    });

    it('lets the owner take back their own sentence', async () => {
        const user = userEvent.setup();
        renderPage([
            row({ reply: { body: 'Teşekkürler!', publishedAt: '2026-09-05T10:00:00+00:00' } }),
        ]);

        await user.click(await screen.findByRole('button', { name: 'Withdraw reply' }));

        await waitFor(() => {
            const call = vi
                .mocked(fetch)
                .mock.calls.find(
                    ([url, init]) =>
                        String(url).includes('/reply') &&
                        (init as RequestInit | undefined)?.method === 'DELETE',
                );

            expect(call).toBeTruthy();
        });
    });

    it('refuses to publish an empty reply instead of quietly withdrawing one', async () => {
        /*
            "Boş gönder = sil" bir gün kazayla silinen bir cümledir. Sunucu da
            422 döner; ekranın işi o 422'yi sahibe yaşatmamaktır.
        */
        const user = userEvent.setup();
        renderPage([row()]);

        await user.click(await screen.findByRole('button', { name: 'Publish reply' }));

        expect(
            await screen.findByText(
                'Write something. An empty reply shows the guest an empty box.',
            ),
        ).toBeTruthy();
        expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).includes('/reply'))).toBe(
            false,
        );
    });

    // --- RATING-SCREEN-NO-DELETE-05 ---------------------------------------

    it('offers no way to take a rating down, and says why', async () => {
        renderPage([row()]);

        expect(
            await screen.findByText(/You can reply to a rating\. You cannot take one down/),
        ).toBeTruthy();

        for (const button of screen.getAllByRole('button')) {
            expect(button.textContent ?? '').not.toMatch(/delete|remove rating/i);
        }
    });

    // --- ÇİZİLMEYEN HÂLLER -------------------------------------------------

    it('asks for a menu instead of drawing an empty table', async () => {
        vi.stubGlobal('fetch', vi.fn());

        render(
            <RatingsPage
                workspaceId={WORKSPACE_ID}
                menuTree={null}
                can={() => true}
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(await screen.findByText('Pick a menu first')).toBeTruthy();
        expect(vi.mocked(fetch)).not.toHaveBeenCalled();
    });

    it('does not fetch anything for somebody whose role cannot see ratings', async () => {
        vi.stubGlobal('fetch', vi.fn());

        render(
            <RatingsPage
                workspaceId={WORKSPACE_ID}
                menuTree={menuTree()}
                can={() => false}
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(await screen.findByText('Ratings are not part of your role')).toBeTruthy();
        expect(vi.mocked(fetch)).not.toHaveBeenCalled();
    });
});
