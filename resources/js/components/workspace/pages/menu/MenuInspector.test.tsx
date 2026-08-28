import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MenuInspector } from './MenuInspector';
import { desktopInspectors } from '../../inspectors/desktopInspectors';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * Menü editörünün bağlam paneli — `docs/50` §3.4, `docs/60`.
 */
const TREE: DashboardMenuTree = {
    id: 7,
    workspaceId: 41,
    locationId: 3,
    name: 'Ana menu',
    state: 'draft',
    categories: [
        { id: 1, name: 'Kebaplar', sortOrder: 1, menuItems: [] },
        {
            id: 2,
            name: 'Tatlılar',
            sortOrder: 2,
            menuItems: [
                { id: 10, productName: 'Trileçe', priceMinor: 11000, currencyCode: 'TRY' },
                { id: 11, productName: 'Muhallebi', priceMinor: 14500, currencyCode: 'TRY' },
            ],
        },
    ],
} as unknown as DashboardMenuTree;

function stubFetch(publication: unknown | null) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () =>
            publication === null
                ? ({ ok: false, status: 404, json: async () => ({}) } as Response)
                : ({ ok: true, status: 200, json: async () => publication } as Response),
        ),
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MenuInspector (docs/60)', () => {
    /**
     * Uygunluk kararı HARİTADA verilir, bileşende değil.
     *
     * Kabuk, panelin içinden `null` döndüğünü göremez: elementin kendisi her
     * zaman doğrudur. Karar haritada verilmezse kabuk adlandırılmış ama boş
     * bir sütun çizer ve olmayan bir bağlamı varmış gibi gösterir.
     */
    it('menü yokken harita panel üretmez', () => {
        const ctx = {
            workspaceId: 41,
            dashboardMenuTree: null,
            location: null,
            onNavigateToSection: vi.fn(),
        } as unknown as Parameters<(typeof desktopInspectors)['menu']['render']>[0];

        expect(desktopInspectors.menu.render(ctx)).toBeNull();
    });

    /**
     * Panel, menü düzenlerken sürekli sorulan ama ana alanda yeri olmayan
     * soruları cevaplar.
     */
    it('gerçek sayıları ve lokasyonu gösterir', async () => {
        stubFetch(null);

        render(
            <MenuInspector
                workspaceId={41}
                menuTree={TREE}
                locationName="Sefaköy"
                onNavigateToSection={vi.fn()}
            />,
        );

        expect(screen.getByText('Sefaköy')).toBeInTheDocument();

        /*
            Sayılar UYDURULMAZ, ağaçtan sayılır: iki kategori ve iki ürün.
            Etiketiyle birlikte aranıyor — yalnız "2" aramak, ekrandaki başka
            bir 2 ile tesadüfen eşleşebilirdi ve test hiçbir şey ölçmezdi.
        */
        const categories = screen.getByText(/^categories$/i).parentElement;
        expect(categories).toHaveTextContent('2');

        const items = screen.getByText(/^items$/i).parentElement;
        expect(items).toHaveTextContent('2');
    });

    it('yayındaki sürümü gösterir', async () => {
        stubFetch({ id: 12, version: 3, state: 'published' });

        render(
            <MenuInspector
                workspaceId={41}
                menuTree={TREE}
                locationName={null}
                onNavigateToSection={vi.fn()}
            />,
        );

        await waitFor(() => {
            expect(screen.getByText(/version 3/i)).toBeInTheDocument();
        });
    });

    /**
     * Panel yeni bir yetenek EKLEMEZ: bağlamı gösterir ve ana alanda zaten
     * bulunan yola kısa yol verir. Aksi hâlde bir kolaylık değil, gizli bir
     * ön koşul olurdu.
     */
    it('tek eylemi bilinen yayın yoluna götürür', async () => {
        stubFetch(null);
        const onNavigate = vi.fn();
        const user = (await import('@testing-library/user-event')).default.setup();

        render(
            <MenuInspector
                workspaceId={41}
                menuTree={TREE}
                locationName={null}
                onNavigateToSection={onNavigate}
            />,
        );

        await user.click(screen.getByRole('button', { name: /preview & publish/i }));

        expect(onNavigate).toHaveBeenCalledWith('publication');
    });
});
