import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaJobQueueRegion } from './MediaJobQueueRegion';

/**
 * KUYRUK — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Kuyruk"), gerekçe `docs/108`
 * §3 madde 5.
 *
 * Restoran sahibinin yolculuğu: on fotoğraf yükledi, kütüphanede önizleme
 * çıkmadı. Tek sorusu "takıldı mı, yoksa hâlâ çalışıyor mu?" — ve bugüne
 * kadar cevabı hiçbir ekranda yoktu, o da aynı fotoğrafı tekrar tekrar
 * yükleyip kotasını kendi eliyle dolduruyordu.
 *
 * Bu dosyanın koruduğu üç şey:
 *
 *   1. Sayaçlar ve satırlar GERÇEK veriden gelir; `held` `failed`ten ayrı
 *      sayılır ve ayrı renktedir.
 *   2. İLERLEME UYDURULMAZ: çalışan işin `aria-valuenow`u YOKTUR.
 *   3. "Yeniden dene", var olan tek-varlık yeniden üretim ucuna gider —
 *      kuyruk kendi işleme hattını açmaz.
 */
const QUEUE_BODY = {
    data: [
        {
            id: 9,
            mediaAssetId: 41,
            assetName: 'Adana kebap',
            kind: 'rendition',
            state: 'running',
            attempts: 1,
            failureReason: null,
            finished: false,
            progress: null,
            startedAt: '2026-09-05 10:00:00',
            finishedAt: null,
        },
        {
            id: 8,
            mediaAssetId: 40,
            assetName: 'Lahmacun',
            kind: 'scan',
            state: 'held',
            attempts: 2,
            failureReason: 'Tarayıcı kurulu değil.',
            finished: true,
            progress: 1,
            startedAt: '2026-09-05 09:58:00',
            finishedAt: '2026-09-05 09:59:00',
        },
    ],
    counts: { pending: 0, running: 1, succeeded: 4, failed: 0, held: 1, total: 6 },
};

function stubFetch(overrides: Record<string, unknown> = {}) {
    const body = { ...QUEUE_BODY, ...overrides };
    const fetchMock = vi.fn(async (url: string) => {
        if (String(url).endsWith('/media/jobs')) {
            return { ok: true, status: 200, json: async () => body };
        }

        return { ok: true, status: 200, json: async () => ({ outcome: 'reprocessed' }) };
    });

    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaJobQueueRegion — "takıldı mı, çalışıyor mu?"', () => {
    it('sayaç kartlarını çizer ve rakamları hizalar', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        const running = await screen.findByTestId('media-queue-count-running');

        expect(running).toHaveTextContent('Running');
        expect(running).toHaveTextContent('1');
        expect(
            screen.getByTestId('media-queue-count-succeeded').querySelector('span:last-child'),
        ).toHaveClass('tabular-nums');
        // `held` AYRI bir sayaçtır: dosyada sorun yok, tarayıcı konuşamadı.
        expect(screen.getByTestId('media-queue-count-held')).toHaveTextContent('On hold');
    });

    it('bekleyen iş uyarı renginde, hata rengiyle karışmaz', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        const card = await screen.findByTestId('media-queue-count-held');
        const held = card.querySelector('span:last-child');

        expect(held).toHaveClass('text-fg-warning');
        // Hata sayacı SIFIRKEN tehlike rengine boyanmaz: sıfır bir hata
        // değildir ve kırmızı sıfır, olmayan bir sorunu duyururdu.
        expect(
            screen.getByTestId('media-queue-count-failed').querySelector('span:last-child'),
        ).toHaveClass('text-fg');
    });

    it('iş satırı ne yapıldığını ve hangi dosyada olduğunu yazar', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        expect(await screen.findByText('Generating sizes · Adana kebap')).toBeInTheDocument();
        expect(screen.getByText('Security scan · Lahmacun')).toBeInTheDocument();
        expect(screen.getByText('Tarayıcı kurulu değil.')).toBeInTheDocument();
    });

    it('satırlar kart değil, tek kartın içinde ayraçlı satırlardır', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        const row = (await screen.findByText('Generating sizes · Adana kebap')).closest('li');

        expect(row).toHaveClass('border-t');
        expect(row).toHaveClass('border-border');
        // Satırın KENDİ kartı yok: yarıçap ve yüzey listenin kabuğundadır.
        expect(row?.className).not.toContain('bg-surface');
    });

    it('çalışan iş için yüzde UYDURMAZ', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        const bars = await screen.findAllByRole('progressbar');
        const running = bars[0];

        // Tabloda yüzde sütunu yok; olmayan bir değeri ekran okuyucuya
        // söylemek de bir uydurmadır.
        expect(running).not.toHaveAttribute('aria-valuenow');
        expect(running).toHaveAttribute(
            'aria-valuetext',
            'Still running — no progress figure is recorded.',
        );
    });

    it('biten iş için ilerleme bellidir', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        const bars = await screen.findAllByRole('progressbar');

        expect(bars[1]).toHaveAttribute('aria-valuenow', '100');
    });

    it('"yeniden dene" var olan tek-varlık yeniden üretim ucuna gider', async () => {
        const fetchMock = stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Try Lahmacun again' }));

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/workspaces/4/media/40/reprocess',
                expect.objectContaining({ method: 'POST' }),
            );
        });

        expect(
            await screen.findByText('Started again. This row updates with the result.'),
        ).toBeInTheDocument();
    });

    it('çalışan işte yeniden deneme düğmesi yoktur', async () => {
        stubFetch();
        render(<MediaJobQueueRegion workspaceId={4} />);

        await screen.findByText('Generating sizes · Adana kebap');

        // Hâlâ çalışan bir işi "yeniden dene" düğmesiyle sunmak, sahibi
        // aynı işi ikinci kez başlatmaya davet ederdi.
        expect(
            screen.queryByRole('button', { name: 'Try Adana kebap again' }),
        ).not.toBeInTheDocument();
    });

    it('hiç iş yokken boş bir liste değil, bir cümle gösterir', async () => {
        stubFetch({
            data: [],
            counts: { pending: 0, running: 0, succeeded: 0, failed: 0, held: 0, total: 0 },
        });
        render(<MediaJobQueueRegion workspaceId={4} />);

        expect(await screen.findByText('No jobs yet.')).toBeInTheDocument();
    });
});
