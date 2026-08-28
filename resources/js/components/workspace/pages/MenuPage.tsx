import { t } from '../../../i18n/workspace';
import { Button } from '../../catalog/forms/micro/Button';
import { MenuCatalogWorkspace } from '../../catalog/menu/macro/MenuCatalogWorkspace';
import type { DashboardMenuTree } from './DashboardPage';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PageState } from './shared/PageState';
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
            return <PageState kind="loading" title={t('workspace.menu.loading')} />;
        }

        /*
            Hatayı bu sayfa SAHİPLENMEZ.

            Katalog yüklenemediğinde arıza yalnız menü ekranını değil, bütün
            bölümleri etkiler — kullanıcı Dashboard'dayken de aynı şey
            bozulmuştur. Bu yüzden hata, tek ve GENEL bir yüzeyde
            (`WorkspaceApp`) sunulur ve çalışan bir yeniden deneme taşır.

            Burada da bir hata çizmek, aynı olayı ekranda iki kez anlatırdı ve
            kullanıcıya hangisinin gerçek olduğunu sordururdu. Bu sayfanın
            görevi yalnız BEKLEME göstermemektir — asıl kusur buydu: hata
            hâlinde "Loading your menu…" yazıp duruyordu ve kullanıcı sonsuza
            kadar bekliyordu (docs/59).
        */
        if (catalogPhase === 'error') {
            return null;
        }

        // Marka yoksa konum da eklenemez; kullanıcıyı bir adım öncesine
        // yollamak, olmayan bir düğmeyi aramasından iyidir.
        const target = catalogPhase === 'brand-onboarding' ? 'brand' : 'locations';

        return (
            <PageState
                kind="prerequisite"
                title={
                    catalogPhase === 'brand-onboarding'
                        ? t('workspace.menu.empty.brand.body')
                        : t('workspace.menu.empty.location.body')
                }
                action={
                    <Button type="button" onClick={() => onNavigateToSection(target)}>
                        {catalogPhase === 'brand-onboarding'
                            ? t('workspace.menu.empty.brand.cta')
                            : t('workspace.menu.empty.location.cta')}
                    </Button>
                }
            />
        );
    }
}

export default MenuPage;
