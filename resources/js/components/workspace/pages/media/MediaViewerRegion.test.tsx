import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { MediaViewerRegion } from './MediaViewerRegion';
import type { MediaAsset } from '../MediaPage';

/**
 * GÖRÜNTÜLE — dosya türüne göre okuyucu (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Görüntüle"; sıra `docs/108` §3 madde 8).
 *
 * Restoran sahibinin yolculuğu: kütüphanede "alerjen-tablosu.pdf" satırını
 * görüyor, içinde ne yazdığını okumak istiyor. Bugün yapabildiği tek şey
 * dosyayı indirmek — telefonunda indirilenler klasörüne düşen dosyayı ayrı
 * bir uygulamada açıyor, panele dönüyor, hangi dosyaya baktığını unutuyor.
 *
 * Bu dosya DÖRT sözü dondurur:
 *
 *   1. HANGİ DOSYAYA BAKILDIĞI HER AN YAZILIDIR (kaynağın "dosya hapları").
 *   2. SAYFA SAYISI BİLİNMİYORSA SAYFA GEZİNTİSİ HİÇ ÇİZİLMEZ. "1 / 12"
 *      yazıp 12'yi bilmemek, olmayan bir kesinlik satmaktır.
 *   3. AÇILAMAYAN DOSYA SEBEBİYLE BİRLİKTE SÖYLENİR ve kullanıcıya bir
 *      sonraki adım verilir (indir). Sebepsiz bir "açılmıyor", kullanıcıyı
 *      ne yapacağını bilmeden bırakır.
 *   4. KLAVYE tek başına yeter: sonraki/önceki sayfa ve kapatma fareye
 *      bağlı değildir.
 */
const ASSETS: MediaAsset[] = [
    {
        id: 7,
        altText: 'Alerjen tablosu',
        slot: 'document',
        status: 'ready',
        originalName: 'alerjen-tablosu.pdf',
        sizeBytes: 471040,
    },
    {
        id: 9,
        altText: 'Künefe tepsisi',
        slot: 'itemImage',
        status: 'ready',
        originalName: 'kunefe-tepsi.jpg',
        sizeBytes: 172032,
    },
];

type ViewerFacts = {
    id: number;
    kind: string;
    mimeType: string;
    originalName: string;
    sizeBytes: number;
    status: string;
    embeddable: boolean;
    blockedReason: string | null;
    previewUrl: string | null;
    pageCount: number | null;
};

const PDF_FACTS: ViewerFacts = {
    id: 7,
    kind: 'pdf',
    mimeType: 'application/pdf',
    originalName: 'alerjen-tablosu.pdf',
    sizeBytes: 471040,
    status: 'ready',
    embeddable: true,
    blockedReason: null,
    previewUrl: '/api/workspaces/3/media/7/preview',
    pageCount: 2,
};

const IMAGE_FACTS: ViewerFacts = {
    id: 9,
    kind: 'image',
    mimeType: 'image/jpeg',
    originalName: 'kunefe-tepsi.jpg',
    sizeBytes: 172032,
    status: 'ready',
    embeddable: true,
    blockedReason: null,
    previewUrl: '/api/workspaces/3/media/9/preview',
    pageCount: null,
};

function stubFetch(byId: Record<number, ViewerFacts>, extra?: (url: string) => unknown) {
    const fetchMock = vi.fn(async (url: string) => {
        const viewerMatch = /\/media\/(\d+)\/viewer$/.exec(String(url));

        if (viewerMatch) {
            const facts = byId[Number(viewerMatch[1])];

            return facts === undefined
                ? { ok: false, status: 404, json: async () => ({}) }
                : { ok: true, status: 200, json: async () => facts };
        }

        return { ok: true, status: 200, json: async () => extra?.(String(url)) ?? {} };
    });

    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('MediaViewerRegion', () => {
    it('opens the first file and says which file is on screen', async () => {
        stubFetch({ 7: PDF_FACTS, 9: IMAGE_FACTS });

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        // Dosya hapları: hangi dosyaya bakıldığı hem seçili hapta hem
        // okuyucunun başlığında yazılıdır.
        const chip = await screen.findByRole('button', { name: /alerjen-tablosu\.pdf/i });
        expect(chip).toHaveAttribute('aria-pressed', 'true');

        const frame = await screen.findByTitle(/alerjen-tablosu\.pdf/i);
        expect(frame.getAttribute('src')).toBe('/api/workspaces/3/media/7/preview#page=1');
    });

    it('walks a pdf page by page and never steps past the last page', async () => {
        stubFetch({ 7: PDF_FACTS, 9: IMAGE_FACTS });
        const user = userEvent.setup();

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        expect(await screen.findByText('Page 1 / 2')).toBeInTheDocument();
        // İlk sayfadayken "önceki" YOKTUR: basılabilir ama hiçbir şey
        // yapmayan bir düğme, kullanıcıya kendi hatasını aratır.
        expect(screen.getByRole('button', { name: 'Previous page' })).toBeDisabled();

        await user.click(screen.getByRole('button', { name: 'Next page' }));

        expect(await screen.findByText('Page 2 / 2')).toBeInTheDocument();
        expect((await screen.findByTitle(/alerjen-tablosu\.pdf/i)).getAttribute('src')).toBe(
            '/api/workspaces/3/media/7/preview#page=2',
        );
        expect(screen.getByRole('button', { name: 'Next page' })).toBeDisabled();
    });

    it('moves between pages and closes the file from the keyboard alone', async () => {
        stubFetch({ 7: PDF_FACTS, 9: IMAGE_FACTS });
        const user = userEvent.setup();

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        await screen.findByText('Page 1 / 2');

        // Klavye: ok tuşları sayfayı çevirir, Escape dosyayı kapatır.
        await user.tab();
        await user.keyboard('{ArrowRight}');
        expect(await screen.findByText('Page 2 / 2')).toBeInTheDocument();

        await user.keyboard('{ArrowLeft}');
        expect(await screen.findByText('Page 1 / 2')).toBeInTheDocument();

        await user.keyboard('{Escape}');
        expect(await screen.findByText(/choose a file/i)).toBeInTheDocument();
        expect(screen.queryByTitle(/alerjen-tablosu\.pdf/i)).not.toBeInTheDocument();
    });

    it('draws no page controls when the page count could not be read', async () => {
        stubFetch({ 7: { ...PDF_FACTS, pageCount: null }, 9: IMAGE_FACTS });

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        // Sayfa sayısı yoksa "sonraki sayfa" da yoktur: nereye gittiğini
        // bilmeyen bir ileri düğmesi, son sayfadan sonra sessizce yalan
        // söylerdi (ekran "Sayfa 40" derken belge 12'de durur).
        expect(await screen.findByTitle(/alerjen-tablosu\.pdf/i)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Next page' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Previous page' })).not.toBeInTheDocument();
        expect(screen.getByText(/page count could not be read/i)).toBeInTheDocument();
    });

    it('shows an image with its own description instead of a frame', async () => {
        stubFetch({ 7: PDF_FACTS, 9: IMAGE_FACTS });
        const user = userEvent.setup();

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        await user.click(await screen.findByRole('button', { name: /kunefe-tepsi\.jpg/i }));

        const image = await screen.findByAltText('Künefe tepsisi');
        expect(image).toHaveAttribute('src', '/api/workspaces/3/media/9/preview');
        expect(screen.queryByTitle(/kunefe-tepsi\.jpg/i)).not.toBeInTheDocument();
    });

    it('says why a file that has not cleared the scan is not opened', async () => {
        stubFetch({
            7: {
                ...PDF_FACTS,
                embeddable: false,
                blockedReason: 'scan',
                previewUrl: null,
                pageCount: null,
                status: 'quarantined',
            },
            9: IMAGE_FACTS,
        });

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        expect(await screen.findByText(/security scan/i)).toBeInTheDocument();
        expect(screen.queryByTitle(/alerjen-tablosu\.pdf/i)).not.toBeInTheDocument();
    });

    it('offers the download when the panel cannot open the type', async () => {
        const fetchMock = stubFetch(
            {
                7: {
                    ...PDF_FACTS,
                    kind: 'other',
                    mimeType: 'text/csv',
                    embeddable: false,
                    blockedReason: 'type',
                    previewUrl: null,
                    pageCount: null,
                },
                9: IMAGE_FACTS,
            },
            () => ({ url: 'https://example.test/signed', expiresAt: '2026-09-05T10:00:00+03:00' }),
        );
        const open = vi.spyOn(window, 'open').mockReturnValue(null);
        const user = userEvent.setup();

        render(<MediaViewerRegion workspaceId={3} assets={ASSETS} />);

        // Açılamayan tür bir ÇIKMAZ SOKAK olmamalı: bir sonraki adım
        // (indir) aynı ekranda durur.
        expect(await screen.findByText(/does not open/i)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /download/i }));

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/workspaces/3/media/7/download-link',
                expect.objectContaining({ method: 'POST' }),
            );
        });
        await waitFor(() => {
            expect(open).toHaveBeenCalledWith(
                'https://example.test/signed',
                '_blank',
                'noopener,noreferrer',
            );
        });
    });

    it('asks for nothing when there is no file to open', () => {
        const fetchMock = stubFetch({});

        render(<MediaViewerRegion workspaceId={3} assets={[]} />);

        expect(screen.getByText(/no file to open/i)).toBeInTheDocument();
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
