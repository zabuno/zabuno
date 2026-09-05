import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, renderHook, waitFor } from '@testing-library/react';

import { ORDER_FEED_INTERVAL_MS, useOrderFeed } from './useOrderFeed';

/**
 * TAZELEME KARARININ TESTİ — `docs/115` §6 (FF-179).
 *
 * Plan yoklamayı seçti ve bunu bir eksiklik olarak yazdı. Bir kararın
 * yazılması onu uygulanmış yapmaz; bu dosya kararı KODA BAĞLAR:
 *
 * - liste kendi kendine tazelenir (aralık sabittir ve tek yerdedir),
 * - sayfa görünmezken yoklama DURUR — arka planda duran bir mutfak monitörü
 *   sunucuyu boşuna meşgul etmemeli,
 * - ekran geri geldiğinde beklemeden tazelenir,
 * - başarısız bir tazeleme elindeki listeyi SİLMEZ, yalnız eskimiş der.
 */

function setVisibility(state: 'visible' | 'hidden'): void {
    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => state,
    });
    document.dispatchEvent(new Event('visibilitychange'));
}

function feedResponse(): Response {
    return {
        ok: true,
        json: async () => ({
            data: [{ id: 1, status: 'pending', tableName: 'Masa 7', lines: [] }],
            serverTime: new Date().toISOString(),
        }),
    } as unknown as Response;
}

describe('useOrderFeed', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        setVisibility('visible');
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(feedResponse())),
        );
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('reads the feed once on mount and reports when it last succeeded', async () => {
        const { result } = renderHook(() => useOrderFeed('/api/orders/pending'));

        await waitFor(() => expect(result.current.status).toBe('ready'));

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(result.current.orders).toHaveLength(1);
        // Ekran "anlık" demez; son güncelleme ANINI yazar. Donmuş bir
        // monitörle dolu bir monitör aynı görünür (`docs/115` §6).
        expect(result.current.lastUpdatedAt).not.toBeNull();
    });

    it('refreshes itself on the single shared interval', async () => {
        renderHook(() => useOrderFeed('/api/orders/pending'));

        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));

        await act(async () => {
            await vi.advanceTimersByTimeAsync(ORDER_FEED_INTERVAL_MS);
        });

        expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('stops asking the server while the screen is not visible', async () => {
        renderHook(() => useOrderFeed('/api/orders/pending'));

        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));

        await act(async () => {
            setVisibility('hidden');
        });

        await act(async () => {
            await vi.advanceTimersByTimeAsync(ORDER_FEED_INTERVAL_MS * 3);
        });

        // Üç aralık geçti, tek bir istek bile gitmedi.
        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('catches up immediately when the screen comes back', async () => {
        renderHook(() => useOrderFeed('/api/orders/pending'));

        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));

        await act(async () => {
            setVisibility('hidden');
        });

        await act(async () => {
            setVisibility('visible');
        });

        // Aşçı ekrana döndüğünde on saniye eski bir liste görmemeli.
        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2));
    });

    it('keeps the list it already has when a refresh fails', async () => {
        const { result } = renderHook(() => useOrderFeed('/api/orders/pending'));

        await waitFor(() => expect(result.current.orders).toHaveLength(1));

        vi.mocked(fetch).mockRejectedValueOnce(new Error('network down'));

        await act(async () => {
            await vi.advanceTimersByTimeAsync(ORDER_FEED_INTERVAL_MS);
        });

        await waitFor(() => expect(result.current.stale).toBe(true));

        /*
            Liste DURUYOR. Servisin ortasında bir ağ kesintisi yüzünden
            aşçının elindeki tek listeyi silip "hata" yazmak, çözdüğü
            sorundan çok daha pahalı olurdu.
        */
        expect(result.current.orders).toHaveLength(1);
        expect(result.current.status).toBe('ready');
    });

    it('reports an error only when it never managed to read anything', async () => {
        vi.mocked(fetch).mockRejectedValue(new Error('network down'));

        const { result } = renderHook(() => useOrderFeed('/api/orders/pending'));

        await waitFor(() => expect(result.current.status).toBe('error'));
        expect(result.current.stale).toBe(false);
    });
});
