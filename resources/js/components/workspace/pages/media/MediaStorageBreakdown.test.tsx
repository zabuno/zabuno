import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

import { MediaStorageBreakdown } from './MediaStorageBreakdown';

/**
 * "YERİ NE DOLDURUYOR?" — kanonik kaynak `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html` (ekran etiketi "Kota ve çöp"), somut liste
 * `docs/108` §6.4.
 *
 * Restoran sahibinin yolculuğu: telefondan fotoğraf yüklerken "yer kalmadı"
 * duvarına tosluyor. Kota şeridi ona "185 MB / 200 MB" diyor ve o bunu
 * okuduğunda NE YAPACAĞINI bilmiyor. Bu bölüm tek bir soruyu cevaplar:
 * hangi dosyaları silsin?
 *
 * Bu dosya dört şeyi korur:
 *
 *   1. Kart yalnız SAYILAN şey için çizilir. Kaynak dört kart sayıyor;
 *      depoda "dönüştürme" ve "CDN trafiği" için ne sayaç ne sınır var.
 *      Uydurulmuş bir kart, sahibi olmayan bir yeteneğe güvendirir.
 *   2. Kırılım satırı SLOT ADI değil, sahibin dilini konuşur.
 *   3. Çöp AYRI ve uyarı renginde: boşaltılabilir bir yer kaplar ve
 *      sahibin bugün elindeki tek geri kazanma düğmesidir.
 *   4. Uç okunamazsa bölüm sessizce çekilir — kota bir kapı değil,
 *      göstergedir.
 */
const BODY = {
    totals: {
        planLabel: 'Starter',
        bytesUsed: 15 * 1048576,
        bytesLimit: 200 * 1048576,
        assetsUsed: 3,
        assetsLimit: 100,
    },
    categories: [
        { key: 'documents', bytes: 8 * 1048576, assets: 1 },
        { key: 'products', bytes: 4 * 1048576, assets: 2 },
    ],
    trash: { bytes: 3 * 1048576, assets: 1 },
};

function mount(body: unknown = BODY, ok = true) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({ ok, status: ok ? 200 : 500, json: async () => body })),
    );

    return render(<MediaStorageBreakdown workspaceId={4} />);
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MediaStorageBreakdown — sahip "hangi dosyayı sileyim?" sorusunu buradan cevaplar', () => {
    it('yalnız SAYILAN kartları çizer; dönüştürme ve CDN kartı yoktur', async () => {
        mount();

        expect(await screen.findByText('15 MB of 200 MB')).toBeInTheDocument();
        expect(screen.getByText('3 of 100')).toBeInTheDocument();

        // Kaynağın diğer iki kartı bu depoda ÖLÇÜLMÜYOR; çizilmez.
        expect(screen.queryByText(/Conversions/i)).toBeNull();
        expect(screen.queryByText(/CDN/i)).toBeNull();
    });

    it('sayılar sabit genişlikli rakamla yazılır', async () => {
        mount();

        const ratio = await screen.findByText('15 MB of 200 MB');

        // Sayaç her yüklemede değişir; orantılı rakamda şerit yatayda titrer.
        expect(ratio).toHaveClass('tabular-nums');
    });

    it('kırılım satırı slot adı değil, sahibin dilini konuşur ve payı okunur', async () => {
        mount();

        // 8 MB / 15 MB = %53; 4 MB / 15 MB = %27.
        expect(await screen.findByText('Documents and scans')).toBeInTheDocument();
        expect(screen.getByText('Product photos')).toBeInTheDocument();
        expect(screen.getByText('8.0 MB · 53% of what is stored')).toBeInTheDocument();

        // Ham slot adı sahibe hiçbir şey söylemez.
        expect(screen.queryByText('itemImage')).toBeNull();
        expect(screen.queryByText('menuImportSource')).toBeNull();
    });

    it('çöp AYRI satırdadır ve uyarı rengini taşır', async () => {
        mount();

        const trash = await screen.findByText('Trash');
        const row = trash.closest('li');

        expect(row).not.toBeNull();
        expect(row?.className).toContain('text-fg-warning');
        expect(screen.getByText(/Deleting does not free space/)).toBeInTheDocument();
    });

    it('çöp boşsa satır hiç çizilmez: boşaltılacak bir şey yoktur', async () => {
        mount({ ...BODY, trash: { bytes: 0, assets: 0 } });

        await screen.findByText('Documents and scans');

        expect(screen.queryByText('Trash')).toBeNull();
    });

    it('sınıra yaklaşınca kart notu uyarı rengine döner', async () => {
        mount({
            ...BODY,
            totals: { ...BODY.totals, bytesUsed: 190 * 1048576 },
            categories: [{ key: 'products', bytes: 190 * 1048576, assets: 3 }],
            trash: { bytes: 0, assets: 0 },
        });

        const note = await screen.findByText(/Close to the limit/);

        expect(note.className).toContain('text-fg-warning');
    });

    it('uç okunamazsa bölüm sessizce çekilir', async () => {
        const { container } = mount({}, false);

        await waitFor(() => {
            expect(container.querySelector('section')).toBeNull();
        });
    });

    it('hiç dosya yoksa kartlar durur, kırılım "henüz bir şey yok" der', async () => {
        mount({
            totals: {
                planLabel: 'Starter',
                bytesUsed: 0,
                bytesLimit: 200 * 1048576,
                assetsUsed: 0,
                assetsLimit: 100,
            },
            categories: [],
            trash: { bytes: 0, assets: 0 },
        });

        expect(await screen.findByText('Nothing is stored yet.')).toBeInTheDocument();
        expect(screen.getByText('0 of 100')).toBeInTheDocument();
    });
});
