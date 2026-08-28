import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopInspectors } from './desktopInspectors';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

/**
 * Masaüstü bağlam panelleri — `docs/60`.
 *
 * Testler haritanın ÜZERİNDEN gider, bileşenleri doğrudan çağırmaz: kabuğun
 * gördüğü şey haritadır ve "bağlam yoksa panel yok" kararı orada verilir.
 */
function makeLocation(overrides: Record<string, unknown> = {}) {
    return {
        id: 923,
        workspace_id: 41,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

function makeContext(overrides: Record<string, unknown> = {}) {
    return {
        workspaceId: 41,
        catalogPhase: 'ready',
        dashboardMenuTree: null,
        brand: { id: 811, workspace_id: 41, name: 'Zeytin' },
        location: makeLocation(),
        locationProfiles: [makeLocation()],
        catalogLocationId: 923,
        onSelectLocation: vi.fn(),
        onLocationSaved: vi.fn(),
        onLocationCreated: vi.fn(),
        onBrandSaved: vi.fn(),
        onMenuTreeChange: vi.fn(),
        onNavigateToSection: vi.fn(),
        subPath: '',
        ...overrides,
    } as unknown as WorkspaceSectionRuntimeContext;
}

describe('marka paneli', () => {
    it('markanın kaç şubede göründüğünü ve şehirleri sayar', () => {
        const ctx = makeContext({
            locationProfiles: [
                makeLocation({ id: 1, city: 'İstanbul' }),
                makeLocation({ id: 2, city: 'Ankara' }),
                makeLocation({ id: 3, city: 'İstanbul' }),
            ],
        });

        render(<>{desktopInspectors.brand.render(ctx)}</>);

        expect(screen.getByText(/^locations$/i).parentElement).toHaveTextContent('3');
        // Şehirler TEKİLLEŞTİRİLİR: üç şube iki şehir eder.
        expect(screen.getByText('Ankara, İstanbul')).toBeInTheDocument();
    });

    /**
     * Boş bir "Şehirler" satırı doldurulmayı bekleyen bir alan gibi görünür;
     * oysa ortada eksik bir alan değil, henüz açılmamış bir şube vardır.
     */
    it('şube yokken şehir satırını uydurmaz', () => {
        render(<>{desktopInspectors.brand.render(makeContext({ locationProfiles: [] }))}</>);

        expect(screen.getByText(/^locations$/i).parentElement).toHaveTextContent('0');
        expect(screen.queryByText(/^cities$/i)).toBeNull();
    });

    it('marka yokken panel üretilmez', () => {
        expect(desktopInspectors.brand.render(makeContext({ brand: null }))).toBeNull();
    });
});

describe('şube paneli', () => {
    it('şubenin markasını ve şehrini gösterir', () => {
        render(<>{desktopInspectors.locations.render(makeContext())}</>);

        expect(screen.getByText(/^brand$/i).parentElement).toHaveTextContent('Zeytin');
        expect(screen.getByText(/^city$/i).parentElement).toHaveTextContent('İstanbul, TR');
    });

    /**
     * KRİTİK: yüklü menü ağacı çalışma alanında SEÇİLİ şubeye aittir. Başka
     * bir şube açıkken o sayıyı göstermek, yanlış şubenin verisini doğru
     * etiketle sunmak olurdu — bilgi vermemekten kötüdür.
     */
    it('menü satırını yalnız ağaç BU şubeye aitken gösterir', () => {
        const tree = {
            id: 7,
            workspaceId: 41,
            locationId: 923,
            name: 'Ana menü',
            state: 'draft',
            categories: [
                { id: 1, name: 'Kahvaltı', menuItems: [{ id: 9 }, { id: 10 }] },
                { id: 2, name: 'İçecekler', menuItems: [] },
            ],
        };

        const { unmount } = render(
            <>{desktopInspectors.locations.render(makeContext({ dashboardMenuTree: tree }))}</>,
        );
        expect(screen.getByText(/^menu$/i).parentElement).toHaveTextContent(
            '2 categories · 2 items',
        );
        unmount();

        // Aynı ağaç, BAŞKA şube açık: menü satırı hiç çizilmez.
        render(
            <>
                {desktopInspectors.locations.render(
                    makeContext({
                        dashboardMenuTree: tree,
                        location: makeLocation({ id: 999, display_name: 'Beşiktaş' }),
                    }),
                )}
            </>,
        );
        expect(screen.queryByText(/^menu$/i)).toBeNull();
    });

    it('menüsü olmayan şubede menüye kısayol vermez', () => {
        render(<>{desktopInspectors.locations.render(makeContext())}</>);

        expect(screen.queryByRole('button', { name: /open the menu/i })).toBeNull();
    });

    it('menüsü olan şubede kısayol menü ekranına götürür', async () => {
        const user = userEvent.setup();
        const onNavigateToSection = vi.fn();
        const ctx = makeContext({
            onNavigateToSection,
            dashboardMenuTree: {
                id: 7,
                workspaceId: 41,
                locationId: 923,
                name: 'Ana menü',
                state: 'draft',
                categories: [],
            },
        });

        render(<>{desktopInspectors.locations.render(ctx)}</>);
        await user.click(screen.getByRole('button', { name: /open the menu/i }));

        expect(onNavigateToSection).toHaveBeenCalledWith('menu');
    });

    it('şube seçili değilken panel üretilmez', () => {
        expect(desktopInspectors.locations.render(makeContext({ location: null }))).toBeNull();
    });
});
