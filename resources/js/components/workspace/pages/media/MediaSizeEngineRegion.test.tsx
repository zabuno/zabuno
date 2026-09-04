import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaSizeEngineRegion } from './MediaSizeEngineRegion';

/**
 * BOYUT MOTORU — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Boyut motoru"), somut tablo
 * `docs/108` §6.1.
 *
 * Restoran sahibinin yolculuğu: menü kartındaki fotoğrafın bulanık
 * çıktığını görüyor ve "hangi ölçü bu kartı besliyor?" diye soruyor. Sayı
 * listesi ona bir şey söylemez; `small · menü kartı, telefon · sığdır`
 * söyler. Bu dosya üç şeyi korur:
 *
 *   1. Kural ADIYLA ve İŞİYLE çizilir.
 *   2. Üretilmeyen ölçü "henüz üretilmiyor" diye YAZILIR — gizlenmez.
 *   3. Ölçüm yoksa "ölçülen kazanç" bölümü HİÇ çizilmez.
 */
const RULES_BODY = {
    rules: [
        {
            name: 'thumb',
            width: 160,
            height: null,
            fit: 'crop',
            formats: ['avif', 'webp'],
            producedBySlots: [],
        },
        {
            name: 'small',
            width: 320,
            height: null,
            fit: 'contain',
            formats: ['avif', 'webp'],
            producedBySlots: ['itemImage', 'gallery'],
        },
        {
            name: 'social',
            width: 1200,
            height: 630,
            fit: 'crop',
            formats: ['jpeg'],
            producedBySlots: ['ogImage'],
        },
    ],
    regeneration: { affectedAssets: 12, existingRenditions: 48, batchLimit: 25 },
    measured: { assets: 12, originalBytes: 10 * 1048576, largestRenditionBytes: 2 * 1048576 },
};

function stubFetch(overrides: Record<string, unknown> = {}) {
    const body = { ...RULES_BODY, ...overrides };
    const fetchMock = vi.fn(async (url: string) => {
        if (String(url).endsWith('/derivative-rules')) {
            return { ok: true, status: 200, json: async () => body };
        }

        return {
            ok: true,
            status: 200,
            json: async () => ({
                processed: 12,
                succeeded: 12,
                failed: 0,
                skipped: 0,
                remaining: 3,
            }),
        };
    });

    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaSizeEngineRegion — kural bir sayı değil bir karardır', () => {
    it('her kuralı adı, ölçüsü, işi ve sığdırmasıyla çizer', async () => {
        stubFetch();
        render(<MediaSizeEngineRegion workspaceId={4} />);

        expect(await screen.findByText('small')).toBeInTheDocument();
        expect(screen.getByText('Menu card, phone · Fitted')).toBeInTheDocument();
        // Sabit çerçeveli tek kural iki ölçü taşır: 1200 × 630.
        expect(screen.getByText('1200 × 630 px')).toBeInTheDocument();
        expect(screen.getByText('List row · Cropped')).toBeInTheDocument();
    });

    it('ölçü rakamları tabular-nums ile yazılır', async () => {
        stubFetch();
        render(<MediaSizeEngineRegion workspaceId={4} />);

        // Altı ölçü ALT ALTA okunur; orantılı rakamda sütun titrer.
        expect(await screen.findByText('320 px')).toHaveClass('tabular-nums');
    });

    it('hiçbir slotta üretilmeyen ölçüyü gizlemez', async () => {
        stubFetch();
        render(<MediaSizeEngineRegion workspaceId={4} />);

        // `thumb` (160 px) bugün hiçbir slotun türev listesinde yok.
        expect(await screen.findByText('Not produced yet')).toBeInTheDocument();
        expect(screen.getByText('Produced in 2 place(s)')).toBeInTheDocument();
    });

    it('yeniden üretim istatistiklerini gerçek sayılarla gösterir', async () => {
        stubFetch();
        render(<MediaSizeEngineRegion workspaceId={4} />);

        expect(await screen.findByText('Files that would be touched')).toBeInTheDocument();
        expect(screen.getByText('12')).toHaveClass('tabular-nums');
        expect(screen.getByText('48')).toBeInTheDocument();
    });

    it('yeniden üretim düğmesi var olan toplu uca gider ve kalanı söyler', async () => {
        const fetchMock = stubFetch();
        render(<MediaSizeEngineRegion workspaceId={4} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Start regeneration' }));

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/workspaces/4/media/reprocess',
                expect.objectContaining({ method: 'POST' }),
            );
        });

        /*
            "Bitti" demek yeterli değil: senkron çalışan bir toplu iş
            sınırda kesilir ve sahip kalanı O AN öğrenmeli, yoksa ekranın
            önünde bekler.
        */
        expect(
            await screen.findByText(/12 file\(s\) regenerated as a new version\./),
        ).toHaveTextContent('3 file(s) left. Press again to continue.');
    });

    it('dokunulacak dosya yokken düğme kapalıdır', async () => {
        stubFetch({
            regeneration: { affectedAssets: 0, existingRenditions: 0, batchLimit: 25 },
            measured: { assets: 0, originalBytes: 0, largestRenditionBytes: 0 },
        });
        render(<MediaSizeEngineRegion workspaceId={4} />);

        expect(await screen.findByRole('button', { name: 'Start regeneration' })).toBeDisabled();
    });

    it('gerçek bir bayt farkı varken ölçülen kazancı çizer', async () => {
        stubFetch();
        render(<MediaSizeEngineRegion workspaceId={4} />);

        expect(await screen.findByText('Measured saving')).toBeInTheDocument();
        expect(screen.getByText('80% smaller')).toBeInTheDocument();
    });

    it('ölçüm yokken "ölçülen kazanç" bölümünü HİÇ çizmez', async () => {
        // Kaynak "AVIF ~%74 küçük" gösteriyor; o biçimin genel iddiasıdır,
        // BU kiracının ölçümü değil. Ölçüm yoksa bölüm de yoktur.
        stubFetch({ measured: { assets: 0, originalBytes: 0, largestRenditionBytes: 0 } });
        render(<MediaSizeEngineRegion workspaceId={4} />);

        expect(await screen.findByText('small')).toBeInTheDocument();
        expect(screen.queryByText('Measured saving')).not.toBeInTheDocument();
    });

    it('türev asıldan küçük değilse kazanç bölümü çizilmez', async () => {
        stubFetch({
            measured: { assets: 3, originalBytes: 1000, largestRenditionBytes: 1200 },
        });
        render(<MediaSizeEngineRegion workspaceId={4} />);

        expect(await screen.findByText('small')).toBeInTheDocument();
        expect(screen.queryByText('Measured saving')).not.toBeInTheDocument();
    });
});
