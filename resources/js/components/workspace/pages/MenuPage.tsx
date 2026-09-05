import { t } from '../../../i18n/workspace';
import { Button } from '../../catalog/forms/micro/Button';
import { MenuCatalogWorkspace } from '../../catalog/menu/macro/MenuCatalogWorkspace';
import { MenuAuditRegion } from './menu/MenuAuditRegion';
import type { DashboardMenuTree } from './DashboardPage';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';
import { PageState } from './shared/PageState';
import type { CatalogPhase } from '../WorkspaceApp';

type MenuPageProps = {
    workspaceId: number;
    catalogPhase: CatalogPhase;
    locationId: number | null;
    onTreeChange: (tree: DashboardMenuTree) => void;
    onNavigateToSection: (section: string) => void;
    /** Bkz. `MenuCatalogWorkspaceProps.can` — tanımsızsa daraltma yapılmaz. */
    can?: (permission: string) => boolean;
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
    can,
}: MenuPageProps) {
    /*
        YAYINLAMA da menüyü değiştirmektir — hatta en geri alınamaz biçimde:
        misafirin masada gördüğü menüyü değiştirir. Mutfak rolü taslağı bile
        değiştiremezken yayın düğmesini görmesi, ekranın en yanıltıcı sözü
        olurdu.
    */
    const canManageMenu = can === undefined || can('menu.manage');
    return (
        <div id="section-menu">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.shell.nav.menus')}
                description={t('workspace.menu.operational.description')}
                actions={
                    /*
                        "ÖNİZLE VE YAYINLA" BAŞLIK SATIRINDA KALIR.

                        Kanonik kaynak (`panel.dc.html` satır 30199-30209)
                        bu eylemi "Menüler" başlığının yanındaki sırada
                        gösterir — sayfa çerçevesinin `actions` yuvası tam
                        olarak orası: başlık solda, eylemler sağda. Yani
                        kaynağın KONUMU burada zaten karşılanıyor.

                        Öteki üç eylem (fotoğraftan aktar · CSV · ürün ekle)
                        menünün kendi verisine ihtiyaç duyar — hangi menü,
                        hangi kategori — ve o veri `MenuCatalogWorkspace`
                        içinde yaşar; bu yüzden onlar oradaki şeritte, bir
                        satır aşağıda çizilir. Yayınlamayı da oraya taşımak,
                        onu menü sunucudan gelene kadar EKRANDA OLMAYAN bir
                        düğmeye çevirirdi: sahip menü ekranını açar, bir
                        saniye boyunca yayınlama yolunu göremez.

                        Yayınlamanın menüye ait olduğu kararı (`docs/50` §5)
                        değişmedi.
                    */
                    canManageMenu ? (
                        <button
                            type="button"
                            onClick={() => onNavigateToSection?.('publication')}
                            className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-bold text-action-fg"
                        >
                            {t('workspace.menu.previewAndPublish')}
                        </button>
                    ) : null
                }
            >
                <PanelCard>{renderCatalog()}</PanelCard>

                {/*
                    "DÜN KEBABIN FİYATINI KİM DEĞİŞTİRDİ?" (FF-163)

                    İZ MENÜNÜN ALTINDA, KENDİ KARTINDA. Sahip bu soruyu
                    Ayarlar'da değil, menüye BAKARKEN sorar: kebabın yanında
                    420 yazdığını görür ve "bu 380 değil miydi?" der. Depo
                    aynı soruyu medya için zaten böyle cevaplamıştı — medya
                    izi Medya ekranının altında duruyor (`MediaPage`), ayrı
                    bir bölümde değil.

                    Katalog kartının İÇİNE konmadı: iz menünün bir parçası
                    değil, menü hakkında bir kayıttır ve katalog kartı zaten
                    düzenlenebilir her şeyi taşıyor. Ayrı kart, "buradan
                    aşağısı düzenlenmez" sınırını da çizer.

                    YALNIZ MENÜYÜ DEĞİŞTİREBİLENE ÇİZİLİR. Uç `menu.manage`
                    istiyor (fiyat geçmişi ticari bir bilgidir); izni
                    olmayana bölümü göstermek, açtığında hata görmesi
                    demekti — kapalı bir başlık bile olmayan bir sözdür.
                */}
                {canManageMenu ? <MenuAuditRegion workspaceId={workspaceId} /> : null}
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
                    onNavigateToSection={onNavigateToSection}
                    can={can}
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
