import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaConvertRegion } from './MediaConvertRegion';

/**
 * DÖNÜŞTÜR — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Dönüştür"), hedef listesi
 * `docs/108` §6.3.
 *
 * Kaynağın cümlesi: "Eski biçimleri modern biçime çevir. Aslı korunur,
 * dönüşen dosya yeni sürüm olur."
 *
 * Restoran sahibinin yolculuğu: kütüphanesinde telefondan çıkmış 3 MB'lık
 * JPEG'ler duruyor, menü mobil veriyle açılıyor ve faturayı misafir
 * ödüyor. Sahip "bunları küçült" diyebilmeli — ASLINI kaybetme korkusu
 * olmadan.
 *
 * Bu dosya dört şeyi korur:
 *
 *   1. Kaynağın dört kartı çizilir, seçili olan İŞARETLİDİR.
 *   2. Bu kurulumda YAPILAMAYAN hedef "yapılabilir" gibi görünmez:
 *      kart durur, sebebiyle birlikte, ve seçilemez.
 *   3. Kaynak dosyalar AYRAÇLI SATIRDIR, kart değil — elli dosya elli
 *      kart olsaydı liste okunmaz olurdu.
 *   4. Kazanç UYDURULMAZ: kaynağın yüzdesi "yaklaşık" diye yazılır,
 *      ölçülmüş olan ise ayrı ve yalnız gerçekten ölçüldüyse.
 */
const TARGETS_BODY = {
    targets: [
        {
            format: 'avif',
            family: 'image',
            claimedSavingPercent: 74,
            supported: true,
            limitation: null,
        },
        {
            format: 'webp',
            family: 'image',
            claimedSavingPercent: 58,
            supported: true,
            limitation: null,
        },
        {
            format: 'webm',
            family: 'video',
            claimedSavingPercent: 62,
            supported: false,
            limitation: 'no-video-pipeline',
        },
        {
            format: 'jpeg',
            family: 'image',
            claimedSavingPercent: 40,
            supported: true,
            limitation: null,
        },
    ],
    sources: [
        { id: 1, name: 'kunefe.jpg', sizeBytes: 3 * 1048576, format: 'jpeg' },
        { id: 2, name: 'adana.png', sizeBytes: 1048576, format: 'png' },
    ],
    measured: {},
    batchLimit: 25,
};

function stubFetch(
    overrides: Record<string, unknown> = {},
    convertBody: Record<string, unknown> = {},
) {
    const body = { ...TARGETS_BODY, ...overrides };
    const fetchMock = vi.fn(async (url: string) => {
        if (String(url).endsWith('/conversion-targets')) {
            return { ok: true, status: 200, json: async () => body };
        }

        return {
            ok: true,
            status: 200,
            json: async () => ({
                format: 'avif',
                processed: 2,
                succeeded: 2,
                failed: 0,
                skipped: 0,
                remaining: 0,
                ...convertBody,
            }),
        };
    });

    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaConvertRegion — asıl korunur, dönüşen yeni sürüm olur', () => {
    it('kaynağın dört hedefini iddia edilen kazancıyla çizer', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        expect(await screen.findByRole('radio', { name: /AVIF/ })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: /WebP/ })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: /WebM/ })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: /JPEG/ })).toBeInTheDocument();

        // "~%74" biçimin GENEL iddiasıdır; metin bunu "about" diye söyler.
        expect(screen.getByText('about 74% smaller')).toBeInTheDocument();
    });

    it('seçili hedefi işaretler ve seçim değiştirilebilir', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        const avif = await screen.findByRole('radio', { name: /AVIF/ });
        // Varsayılan, kaynağın ilk kartıdır: en küçüğü.
        expect(avif).toHaveAttribute('aria-checked', 'true');

        await userEvent.click(screen.getByRole('radio', { name: /WebP/ }));

        expect(screen.getByRole('radio', { name: /WebP/ })).toHaveAttribute('aria-checked', 'true');
        expect(screen.getByRole('radio', { name: /AVIF/ })).toHaveAttribute(
            'aria-checked',
            'false',
        );
    });

    it('bu kurulumda yapılamayan hedefi sebebiyle yazar ve seçtirmez', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        const webm = await screen.findByRole('radio', { name: /WebM/ });

        expect(
            screen.getByText('Not possible here: there is no video conversion pipeline.'),
        ).toBeInTheDocument();
        expect(webm).toBeDisabled();

        await userEvent.click(webm);

        // Tıklamak seçimi DEĞİŞTİRMEZ: kart görünür ama yapılamaz.
        expect(screen.getByRole('radio', { name: /AVIF/ })).toHaveAttribute('aria-checked', 'true');
    });

    it('kaynak dosyaları ayraçlı satır olarak çizer, kart olarak değil', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        expect(await screen.findByText('kunefe.jpg')).toBeInTheDocument();
        // Satır "jpeg → AVIF" der: sahip dönüşümün yönünü okur.
        expect(screen.getByText('jpeg → AVIF')).toBeInTheDocument();
        expect(screen.getByText('png → AVIF')).toBeInTheDocument();
    });

    it('"tümünü seç" bütün satırları işaretler, ikinci basış bırakır', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        const toggleAll = await screen.findByRole('button', { name: 'Select all' });
        await userEvent.click(toggleAll);

        expect(screen.getByRole('checkbox', { name: 'kunefe.jpg' })).toHaveAttribute(
            'aria-checked',
            'true',
        );

        await userEvent.click(screen.getByRole('button', { name: 'Clear' }));

        expect(screen.getByRole('checkbox', { name: 'kunefe.jpg' })).toHaveAttribute(
            'aria-checked',
            'false',
        );
    });

    it('hiçbir dosya seçili değilken dönüştür düğmesi kapalıdır', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        // Basılabilen ama hiçbir şey yapmayan bir düğme, ürünün bozuk
        // olduğunu düşündürür.
        expect(await screen.findByRole('button', { name: 'Choose a file' })).toBeDisabled();
    });

    it('seçilen dosyaları dönüştürme ucuna gönderir ve sonucu söyler', async () => {
        const fetchMock = stubFetch({}, { succeeded: 2, failed: 0, remaining: 0 });
        render(<MediaConvertRegion workspaceId={4} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Select all' }));
        await userEvent.click(screen.getByRole('button', { name: 'Convert 2 file(s) to AVIF' }));

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/workspaces/4/media/convert',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ format: 'avif', assetIds: [1, 2] }),
                }),
            );
        });

        expect(
            await screen.findByText(
                /2 file\(s\) converted as a new version\. The original is kept\./,
            ),
        ).toBeInTheDocument();
    });

    it('başarısızlığı ve kalanı gizlemez', async () => {
        stubFetch({}, { succeeded: 1, failed: 1, remaining: 3 });
        render(<MediaConvertRegion workspaceId={4} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Select all' }));
        await userEvent.click(screen.getByRole('button', { name: 'Convert 2 file(s) to AVIF' }));

        /*
            "Bitti" demek yeterli değil: senkron çalışan bir iş sınırda
            kesilir ve sahip kalanı O AN öğrenmeli. Düşen dosyayı
            söylememek ise onu olmamış işi olmuş sanmaya iter.
        */
        const notice = await screen.findByRole('status');
        expect(notice).toHaveTextContent(
            '1 file(s) could not be converted; their current version stayed valid.',
        );
        expect(notice).toHaveTextContent('3 file(s) left. Press again to continue.');
    });

    it('ölçüm yokken ölçülen kazancı HİÇ yazmaz', async () => {
        stubFetch();
        render(<MediaConvertRegion workspaceId={4} />);

        expect(await screen.findByText('about 74% smaller')).toBeInTheDocument();
        // Kaynaktaki yüzde bir İDDİADIR; onu bu kiracının ölçümü gibi
        // göstermek, sonradan tutmayacak bir sayıya güvendirirdi.
        expect(screen.queryByText(/Measured on your own/)).not.toBeInTheDocument();
    });

    it('gerçekten tartılmış bayt varsa ölçülen kazancı ayrıca yazar', async () => {
        stubFetch({
            measured: {
                avif: { assets: 4, originalBytes: 1000, convertedBytes: 260 },
            },
        });
        render(<MediaConvertRegion workspaceId={4} />);

        expect(
            await screen.findByText('Measured on your own 4 file(s): 74% smaller.'),
        ).toBeInTheDocument();
    });

    it('dönüştürülebilir dosya yokken listeyi boş bırakmaz, sebebini yazar', async () => {
        stubFetch({ sources: [] });
        render(<MediaConvertRegion workspaceId={4} />);

        expect(await screen.findByText('No file can be converted yet.')).toBeInTheDocument();
    });
});
