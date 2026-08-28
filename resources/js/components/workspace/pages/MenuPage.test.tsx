import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';

/**
 * MenuPage kabul testi.
 *
 * Bu dosyanın önceki hâli, konumu olmayan çalışma alanında gösterilen
 * "Loading your menu…" yazısını *"honest loading state"* diye
 * dondurmuştu. Dürüst değildi: konum eklenmemiş bir çalışma alanında o
 * yazı hiç kaybolmaz, çünkü beklenen şey hiç gelmez. Kullanıcı kendisinden
 * bir şey istendiğini bilmeden bekler.
 *
 * Buradaki kural artık şu: **"yükleniyor" yalnız gerçekten veri yoldayken
 * gösterilir.** Boşluk ve hata kendi ekranlarını gösterir, ve boş durum
 * çıkış yolunu da verir.
 *
 * MenuCatalogWorkspace burada taklit edilir; kendi sözleşmesi
 * `catalog/menu/macro/MenuCatalogWorkspace.test.tsx` içinde dondurulmuştur.
 */

vi.mock('../../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

vi.mock('../ai/AiAssistPanel', () => ({
    AiAssistPanel: ({ context }: { context: string }) => (
        <div data-testid="ai-assist-panel" data-context={context} />
    ),
}));

import { MenuPage } from './MenuPage';
import type { CatalogPhase } from '../WorkspaceApp';

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function renderPage(
    catalogPhase: CatalogPhase,
    locationId: number | null,
    onNavigateToSection = vi.fn(),
) {
    render(
        <MenuPage
            workspaceId={WORKSPACE_ID}
            catalogPhase={catalogPhase}
            locationId={locationId}
            onTreeChange={vi.fn()}
            onNavigateToSection={onNavigateToSection}
        />,
    );

    return { onNavigateToSection };
}

describe('MenuPage', () => {
    it('shows the loading status only while the catalog is genuinely being fetched', () => {
        renderPage('loading', null);

        expect(screen.getByText(/loading your menu/i)).toBeInTheDocument();
        expect(screen.queryByTestId('menu-catalog-workspace')).not.toBeInTheDocument();
    });

    it('tells a workspace without a location what to do instead of pretending to load forever', () => {
        renderPage('location-onboarding', null);

        // Asıl gerileme koruması: konum yokken beklenecek bir şey yoktur.
        expect(screen.queryByText(/loading your menu/i)).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /add a location/i })).toBeInTheDocument();
    });

    it('sends the user to the locations section from the empty state', () => {
        const { onNavigateToSection } = renderPage('location-onboarding', null);

        fireEvent.click(screen.getByRole('button', { name: /add a location/i }));

        expect(onNavigateToSection).toHaveBeenCalledWith('locations');
    });

    it('sends the user one step earlier when the brand itself is missing', () => {
        const { onNavigateToSection } = renderPage('brand-onboarding', null);

        expect(screen.queryByText(/loading your menu/i)).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: /set up brand/i }));

        expect(onNavigateToSection).toHaveBeenCalledWith('brand');
    });

    /**
     * Asıl kusur bir hata GÖSTERMEMEK değil, hata hâlinde BEKLEME göstermekti:
     * sayfa "Loading your menu…" yazıp duruyordu ve kullanıcı sonsuza kadar
     * bekliyordu.
     *
     * Hatanın kendisi bu sayfaya ait değil: katalog yüklenemediğinde arıza
     * bütün bölümleri etkiler ve tek bir GENEL yüzeyde, çalışan bir yeniden
     * deneme ile sunulur (`WorkspaceApp`). Burada da çizmek, aynı olayı
     * ekranda iki kez anlatırdı (docs/59).
     */
    it('hata hâlinde bekleme göstermez ve hatayı sahiplenmez', () => {
        renderPage('error', null);

        expect(screen.queryByText(/loading your menu/i)).not.toBeInTheDocument();
        expect(screen.queryByRole('alert')).toBeNull();
        // Sayfa kabuğu yerinde kalır; içinde yalnız hata GÖSTERİLMEZ.
        expect(document.querySelector('#section-menu')).not.toBeNull();
    });

    it('renders the menu catalog workspace with the exact workspaceId/locationId once a location is selected', () => {
        renderPage('menu-catalog', LOCATION_ID);

        const menuRoot = document.querySelector('#section-menu') as HTMLElement;
        const workspace = within(menuRoot).getByTestId('menu-catalog-workspace');
        expect(workspace).toHaveAttribute('data-workspace-id', String(WORKSPACE_ID));
        expect(workspace).toHaveAttribute('data-location-id', String(LOCATION_ID));
    });

    it('does not swap in the loading status once a location and its catalog workspace are rendered', () => {
        renderPage('menu-catalog', LOCATION_ID);

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
