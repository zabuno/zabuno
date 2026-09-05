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

/*
    Değişiklik geçmişi burada TAKLİT EDİLİR (FF-163).

    Bölüm kendi verisini çeker ve kendi "yükleniyor" durumunu gösterir;
    gerçeği burada çizmek, bu dosyanın asıl sorusunu ("menü ekranı katalog
    geldiğinde bekleme göstermiyor mu?") başka bir bölümün beklemesiyle
    karıştırırdı. Kendi sözleşmesi `menu/MenuAuditRegion.test.tsx` içinde
    dondurulmuştur — `MenuCatalogWorkspace` ile aynı gerekçe.
*/
vi.mock('./menu/MenuAuditRegion', () => ({
    MenuAuditRegion: (props: { workspaceId: number }) => (
        <div data-testid="menu-audit-region" data-workspace-id={props.workspaceId} />
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

    /**
     * DEĞİŞİKLİK GEÇMİŞİ MENÜ EKRANINDA DURUR (FF-163).
     *
     * "Dün kebabın fiyatını kim değiştirdi?" sorusu Ayarlar'da değil, menüye
     * BAKARKEN sorulur: sahip kebabın yanında 420 yazdığını görür ve "bu 380
     * değil miydi?" der. Depo aynı soruyu medya için zaten böyle
     * cevaplamıştı — medya izi Medya ekranının altındadır.
     */
    it('menü ekranının altında değişiklik geçmişini çizer', () => {
        render(
            <MenuPage
                workspaceId={WORKSPACE_ID}
                catalogPhase="menu-catalog"
                locationId={LOCATION_ID}
                onTreeChange={vi.fn()}
                onNavigateToSection={vi.fn()}
                can={() => true}
            />,
        );

        expect(screen.getByTestId('menu-audit-region')).toHaveAttribute(
            'data-workspace-id',
            String(WORKSPACE_ID),
        );
    });

    /**
     * FİYAT GEÇMİŞİ TİCARİ BİR BİLGİDİR.
     *
     * Uç `menu.manage` istiyor; Mutfak rolünde o izin yok. Bölümü yine de
     * çizmek, açıldığında hata gösteren bir başlık demekti — kapalı bir
     * başlık bile olmayan bir sözdür.
     */
    it('menüyü değiştiremeyen role değişiklik geçmişini hiç göstermez', () => {
        render(
            <MenuPage
                workspaceId={WORKSPACE_ID}
                catalogPhase="menu-catalog"
                locationId={LOCATION_ID}
                onTreeChange={vi.fn()}
                onNavigateToSection={vi.fn()}
                can={(permission) => permission !== 'menu.manage'}
            />,
        );

        expect(screen.queryByTestId('menu-audit-region')).not.toBeInTheDocument();
    });
});
