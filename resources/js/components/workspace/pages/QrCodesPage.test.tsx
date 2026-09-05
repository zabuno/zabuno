import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrCodesPage } from './QrCodesPage';

/**
 * QR KODLAR EKRANI — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Sahibin cümlesi: *"benzetmek değil DEĞİŞTİRMEKTİR."* Önceki hâl bir kartın
 * içine gömülü, yukarıdan aşağı akan tek sütunlu bir bölge yığınıydı: kod
 * listesi, alanlar, kart sihirbazı, deste, tek kod, toplu sihirbaz. Kaynağın
 * ekranı ise İKİ SÜTUNDUR ve soru sırası tersine çevrilmiştir — solda masa
 * kartları ızgarası (hangi masa?), sağda o masanın kartı (nasıl basılacak?).
 *
 * Fark bir zevk farkı değil: kırk masalı bir restoranda "Masa 17'nin kartı
 * çalışıyor mu" sorusu eski düzende hiç yanıtlanamıyordu. Izgara o soruyu tek
 * bakışta yanıtlar, sağ panel de ikinci soruyu — "yeniden bastırayım" —
 * tıklama uzağına getirir.
 *
 * AEP ağırlık ölçeği ÜÇ basamaklıdır: 400 gövde, 500 vurgulu satır, 700
 * başlık ve birincil eylem. 600 (`font-semibold`) ölçekte YOKTUR: 600
 * yazıldığında tarayıcı yüklü yazı tipinin 500 ve 700 kesimleri arasından
 * birini seçer ya da sentetik bir kalınlaştırma uydurur; aynı ekran iki
 * makinede iki farklı ağırlıkta çizilir.
 */

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

const MENU_TREE = {
    id: 42,
    workspaceId: 7,
    locationId: 923,
    name: 'Ana menü',
    state: 'draft',
    categories: [],
};

const CODES = [
    {
        id: 11,
        workspaceId: 7,
        locationId: 923,
        menuId: 42,
        token: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        resolverUrl: 'https://zabuno.test/q/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        destinationType: 'published_menu',
        state: 'active',
        tableName: 'Masa 1',
        areaLabel: 'Bahçe',
        areaId: 3,
        scanCount: 31,
    },
    {
        id: 12,
        workspaceId: 7,
        locationId: 923,
        menuId: 42,
        token: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        resolverUrl: 'https://zabuno.test/q/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        destinationType: 'published_menu',
        state: 'active',
        tableName: 'Masa 2',
        areaLabel: 'Bahçe',
        areaId: 3,
        scanCount: 0,
    },
];

function routeFetch(url: string): Response {
    if (url.includes('/publications/current')) {
        return jsonResponse(200, { id: 1, version: 14, state: 'published' });
    }
    if (url.includes('/qr-codes')) {
        return jsonResponse(200, CODES);
    }
    if (url.includes('/dining-areas')) {
        return jsonResponse(200, []);
    }

    return jsonResponse(200, []);
}

describe('QrCodesPage — panel v3 düzeni', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn((input: RequestInfo | URL) => Promise.resolve(routeFetch(String(input))));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('kaynağın vaadini başlığın altında yazar: basılı kod hiç değişmez', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        await waitFor(() => {
            expect(screen.getByText(/a printed code never changes/i)).toBeInTheDocument();
        });
    });

    it('sayfanın birincil eylemi "hepsini PDF indir"dir ve gerçek deste ucuna bağlıdır', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const downloadAll = await screen.findByRole('link', { name: /download every card/i });

        expect(
            downloadAll.getAttribute('href'),
            'Uydurulmuş bir uç değil, depoda var olan deste PDF ucu kullanılmalı.',
        ).toBe('/api/workspaces/7/brand/locations/923/qr-codes/print.pdf');
    });

    it('masa ızgarası ile sağ panel AYNI seçimi paylaşır', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        const selectedRegion = await screen.findByRole('region', { name: /selected code/i });
        expect(selectedRegion.textContent ?? '').toMatch(/Masa 1/);

        await userEvent.click(await screen.findByRole('button', { name: /masa 2/i }));

        await waitFor(() => {
            expect(selectedRegion.textContent ?? '').toMatch(/Masa 2/);
        });
    });

    it('yeni masalar için toplu kod bölümü ekranın kendisindedir', async () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={MENU_TREE} />);

        expect(await screen.findByRole('group', { name: /bulk qr wizard/i })).toBeInTheDocument();
    });

    it('menü yokken çıkış eylemi ölçeğin 700 basamağını taşır', () => {
        render(<QrCodesPage workspaceId={7} dashboardMenuTree={null} />);

        const action = screen.getByRole('button', { name: 'Go to your menu' });

        expect(action).toHaveClass('font-bold');
        expect(action).not.toHaveClass('font-semibold');
    });
});
