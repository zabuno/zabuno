import { t } from '../../../i18n/workspace';
import { Button } from '../../catalog/forms/micro/Button';
import { MenuCatalogWorkspace } from '../../catalog/menu/macro/MenuCatalogWorkspace';
import type { DashboardMenuTree } from './DashboardPage';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
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
    return (
        <div id="section-menu">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.shell.nav.menus')}
                description={t('workspace.menu.operational.description')}
                actions={
                    /*
                        Yayınlama menüye AİTTİR, ayrı bir modül değil.

                        Ana menüde kalıcı bir "Publication" maddesi olarak
                        durduğunda, hangi menüyü yayınladığı belirsizdi — bir
                        çalışma alanında birden fazla menü olabilir. Buradan
                        açıldığında hangi menü olduğu sorusu hiç doğmaz
                        (`docs/50` §5).
                    */
                    <button
                        type="button"
                        onClick={() => onNavigateToSection?.('publication')}
                        className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-semibold text-action-fg"
                    >
                        {t('workspace.menu.previewAndPublish')}
                    </button>
                }
            >
                {renderCatalog()}
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
