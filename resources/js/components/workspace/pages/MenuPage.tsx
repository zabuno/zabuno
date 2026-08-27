import { t } from '../../../i18n/workspace';
import { Button } from '../../catalog/forms/micro/Button';
import { MenuCatalogWorkspace } from '../../catalog/menu/macro/MenuCatalogWorkspace';
import type { DashboardMenuTree } from './DashboardPage';
import { AiAssistPanel } from '../ai/AiAssistPanel';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';
import type { CatalogPhase } from '../WorkspaceApp';

type MenuPageProps = {
    workspaceId: number;
    catalogPhase: CatalogPhase;
    locationId: number | null;
    onTreeChange: (tree: DashboardMenuTree) => void;
    onNavigateToSection: (section: string) => void;
};

/**
 * Menü bölümü.
 *
 * Burada tek bir kural var ve pahalıya mal olmuştu: **"yükleniyor", bir
 * bekleme durumudur — bir sonuç değil.** Bu bileşen daha önce `locationId`
 * null olduğunda "Loading your menu…" yazıyordu. Henüz konum eklememiş bir
 * çalışma alanında `locationId` hiçbir zaman dolmaz, dolayısıyla o yazı
 * hiçbir zaman kaybolmazdı: yeni açılan her hesabın gördüğü ilk ekran,
 * bitmeyen bir yükleme oluyordu. Kullanıcı beklemekten başka bir şey
 * yapamaz, çünkü kendisinden bir şey istendiğini bilmiyor.
 *
 * Uygulama konumun olmadığını zaten biliyordu (`catalogPhase`); bilgi
 * buraya taşınmıyordu. Artık taşınıyor ve her durum kendi ekranını
 * gösteriyor — bekleme, boşluk ve hata birbirinden ayrı şeylerdir.
 */
export function MenuPage({
    workspaceId,
    catalogPhase,
    locationId,
    onTreeChange,
    onNavigateToSection,
}: MenuPageProps) {
    const badges: WorkspacePageStatusBadge[] =
        locationId !== null
            ? [{ key: 'menu-location', status: 'success', label: `#${locationId}` }]
            : [];

    return (
        <div id="section-menu">
            <WorkspacePageFrame
                title={t('workspace.shell.nav.menu')}
                description={t('workspace.menu.operational.description')}
                badges={badges}
            >
                {renderCatalog()}

                <AiAssistPanel context={t('workspace.shell.nav.menu')} />
            </WorkspacePageFrame>
        </div>
    );

    function renderCatalog() {
        if (locationId !== null) {
            return (
                <MenuCatalogWorkspace
                    workspaceId={workspaceId}
                    locationId={locationId}
                    onTreeChange={onTreeChange}
                />
            );
        }

        // Gerçek bekleme: veri yolda. Tek meşru "yükleniyor" hâli budur.
        if (catalogPhase === 'loading') {
            return (
                <p role="status" className="text-body text-fg-secondary">
                    {t('workspace.menu.loading')}
                </p>
            );
        }

        if (catalogPhase === 'error') {
            return (
                <p role="alert" className="text-body text-fg-secondary">
                    {t('workspace.menu.error')}
                </p>
            );
        }

        // Marka yoksa konum da eklenemez; kullanıcıyı bir adım öncesine
        // yollamak, olmayan bir düğmeyi aramasından iyidir.
        const target = catalogPhase === 'brand-onboarding' ? 'brand' : 'locations';

        return (
            <div role="status" className="flex flex-col items-start gap-3">
                <p className="text-body text-fg">
                    {catalogPhase === 'brand-onboarding'
                        ? t('workspace.menu.empty.brand.body')
                        : t('workspace.menu.empty.location.body')}
                </p>
                <Button type="button" onClick={() => onNavigateToSection(target)}>
                    {catalogPhase === 'brand-onboarding'
                        ? t('workspace.menu.empty.brand.cta')
                        : t('workspace.menu.empty.location.cta')}
                </Button>
            </div>
        );
    }
}

export default MenuPage;
