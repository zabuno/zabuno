import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { PhonePreviewRegion } from './PhonePreviewRegion';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * TELEFONDA ÖNİZLE (kanonik kaynaktaki düğme; sahibin 2026-09-05 kararı).
 *
 * NEDEN: sahibin taslağı misafirin gözüyle görmesinin tek yolu bugüne kadar
 * YAYINLAMAKTI — yani kontrol etmek için önce riski almak. Bu bölge o sırayı
 * tersine çevirir: önce bakılır, sonra yayınlanır.
 *
 * Adresin MİSAFİRİN ADRESİ OLMADIĞI ekranda YAZAR. Sahibin en pahalı korkusu
 * basılı kartların ölmesidir; on beş dakikada ölen bir adresi kartın üstüne
 * bastırmak, kırk masayı ertesi gün boş bir sayfaya bakar hâlde bırakırdı.
 */
function menuTree(): DashboardMenuTree {
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
                ],
            },
        ],
    };
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as unknown as Response;
}

let fetchSpy: ReturnType<typeof vi.fn>;
let openSpy: ReturnType<typeof vi.fn>;

beforeEach(() => {
    fetchSpy = vi.fn(async () =>
        jsonResponse(201, {
            url: 'https://example.test/menu-preview/71/42?expires=1&signature=abc',
            expiresAt: '2026-09-05T10:15:00+00:00',
        }),
    );
    openSpy = vi.fn();

    vi.stubGlobal('fetch', fetchSpy);
    vi.stubGlobal('open', openSpy);
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

function region(): HTMLElement {
    return screen.getByRole('region', { name: /preview on a phone/i });
}

describe('PhonePreviewRegion — taslağa yayınlamadan bakmak', () => {
    it('misafirin göreceği menüyü ekranda dar bir sütunda gösterir', () => {
        render(<PhonePreviewRegion dashboardMenuTree={menuTree()} workspaceId={71} menuId={42} />);

        expect(within(region()).getByText(/this is what a guest will see/i)).toBeInTheDocument();
        expect(within(region()).getByText('Adana Kebap')).toBeInTheDocument();
    });

    it('düğmeye basınca sunucudan İMZALI adresi ister ve onu açar', async () => {
        render(<PhonePreviewRegion dashboardMenuTree={menuTree()} workspaceId={71} menuId={42} />);

        await userEvent.click(
            within(region()).getByRole('button', { name: /open the preview link/i }),
        );

        await waitFor(() => {
            expect(fetchSpy).toHaveBeenCalledWith(
                '/api/workspaces/71/menu/42/draft-preview-link',
                expect.objectContaining({ method: 'POST' }),
            );
        });

        await waitFor(() => {
            expect(openSpy).toHaveBeenCalledWith(
                'https://example.test/menu-preview/71/42?expires=1&signature=abc',
                '_blank',
                'noopener,noreferrer',
            );
        });
    });

    it('adresin SINIRLARINI düğmenin yanında yazar: süre, indekslenmeme ve QR’ın değişmediği', () => {
        render(<PhonePreviewRegion dashboardMenuTree={menuTree()} workspaceId={71} menuId={42} />);

        const text = region().textContent ?? '';

        expect(text).toMatch(/15 minutes/i);
        expect(text).toMatch(/search engines/i);
        expect(text).toMatch(/printed QR code/i);
    });

    it('adres üretilemezse dürüstçe söyler ve sahte bir bağlantı açmaz', async () => {
        fetchSpy.mockImplementation(async () => jsonResponse(500, { message: 'boom' }));

        render(<PhonePreviewRegion dashboardMenuTree={menuTree()} workspaceId={71} menuId={42} />);

        await userEvent.click(
            within(region()).getByRole('button', { name: /open the preview link/i }),
        );

        expect(await within(region()).findByRole('alert')).toBeInTheDocument();
        expect(openSpy).not.toHaveBeenCalled();
    });

    it('menü kimliği yokken adres düğmesi ÇİZİLMEZ', () => {
        /*
            Çalışamayacak bir düğme çizmek, sahibi tıklayıp bekleten ve
            hiçbir şey olmayınca ürüne güvenini yitiren bir yol açar.
        */
        render(
            <PhonePreviewRegion
                dashboardMenuTree={menuTree()}
                workspaceId={undefined}
                menuId={null}
            />,
        );

        expect(
            within(region()).queryByRole('button', { name: /open the preview link/i }),
        ).toBeNull();
    });

    it('adres gerçekten açıldığında haber verir; açılamazsa VERMEZ', async () => {
        /*
            Adım çizgisindeki "Önizleme" adımı bu haberle yanar. Düğmeye
            basılmış olması yetseydi, çizgi sahibin YAPMADIĞI bir kontrolü
            yapılmış gösterirdi.
        */
        const onPreviewOpened = vi.fn();

        const { rerender } = render(
            <PhonePreviewRegion
                dashboardMenuTree={menuTree()}
                workspaceId={71}
                menuId={42}
                onPreviewOpened={onPreviewOpened}
            />,
        );

        await userEvent.click(
            within(region()).getByRole('button', { name: /open the preview link/i }),
        );

        await waitFor(() => expect(onPreviewOpened).toHaveBeenCalledTimes(1));

        fetchSpy.mockImplementation(async () => jsonResponse(500, { message: 'boom' }));

        rerender(
            <PhonePreviewRegion
                dashboardMenuTree={menuTree()}
                workspaceId={71}
                menuId={42}
                onPreviewOpened={onPreviewOpened}
            />,
        );

        await userEvent.click(
            within(region()).getByRole('button', { name: /open the preview link/i }),
        );

        await within(region()).findByRole('alert');
        expect(onPreviewOpened).toHaveBeenCalledTimes(1);
    });

    it('sabit piksel, kırılım noktası veya yasak sınıf taşımaz', () => {
        const { container } = render(
            <PhonePreviewRegion dashboardMenuTree={menuTree()} workspaceId={71} menuId={42} />,
        );

        const classNames = Array.from(container.querySelectorAll<HTMLElement>('*'))
            .map((element) => (typeof element.className === 'string' ? element.className : ''))
            .join(' ');

        expect(classNames).not.toMatch(/(^|\s)(sm|md|lg|xl|2xl):/);
        expect(classNames).not.toMatch(/\[\d+px\]/);
        expect(classNames).not.toMatch(/font-semibold/);
        expect(classNames).not.toMatch(/rounded-full/);
    });
});
