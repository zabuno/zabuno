import { t } from '../../../i18n/dashboard';
import { StatCard } from '../../catalog/data-display/compound/StatCard';
import { ResponsiveDataTable } from '../../catalog/data-display/compound/ResponsiveDataTable';
import { OpsCard } from '../../ops/OpsCard';
import type { DataTableColumn } from '../../catalog/data-display/compound/ResponsiveDataTable';
import { DashboardSetupJourney } from './dashboard/DashboardSetupJourney';
import { DashboardGreeting } from './dashboard/DashboardGreeting';
import { DashboardSuggestions } from './dashboard/DashboardSuggestions';
import { DashboardQuickActions } from './dashboard/DashboardQuickActions';
import { DashboardTopViewed } from './dashboard/DashboardTopViewed';
import { useMenuInsights } from './dashboard/useMenuInsights';
import type { BrandProfile } from '../BrandEditForm';
import type { LocationProfile } from '../LocationEditForm';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PageState } from './shared/PageState';
import { Button } from 'flowbite-react';

export type DashboardMenuItemRow = {
    id: number;
    categoryId: number;
    productId: number;
    productName?: string;
    priceMinorAmount: number;
    currencyCode: string;
    position: number;
    allergens: string[];
    isVisible: boolean;
};

export type DashboardCategoryRow = {
    id: number;
    menuId: number;
    name: string;
    position: number;
    menuItems: DashboardMenuItemRow[];
};

export type DashboardMenuTree = {
    id: number;
    workspaceId: number;
    locationId: number;
    name: string;
    state: string;
    categories: DashboardCategoryRow[];
};

type DashboardMenuItemTableRow = DashboardMenuItemRow & { categoryName: string };

const DASHBOARD_MENU_ITEM_COLUMNS: readonly DataTableColumn<DashboardMenuItemTableRow>[] = [
    {
        key: 'productName',
        header: t('dashboard.table.column.item'),
        render: (row) => `${row.productName ?? `#${row.productId}`} (${row.categoryName})`,
    },
    {
        key: 'isVisible',
        header: t('dashboard.table.column.visible'),
        render: (row) => (row.isVisible ? 'Yes' : 'No'),
    },
];

type DashboardPageProps = {
    dashboardMenuTree: DashboardMenuTree | null;
    brand?: BrandProfile | null;
    location?: LocationProfile | null;
    workspaceId?: number;
    onNavigateToSection?: (section: string) => void;
    /** Pennant `novice-home` (FF-74): kiracıda kapatılırsa 'şimdi' kutusu çizilmez. */
    noviceHome?: boolean;
};

export function DashboardPage({
    dashboardMenuTree,
    brand = null,
    location = null,
    workspaceId,
    onNavigateToSection,
    noviceHome = true,
}: DashboardPageProps) {
    /*
        Ölçüm BİR KEZ okunur ve iki bölüm onu paylaşır (`docs/109` §6.1):
        öneriler de "en çok bakılanlar" da aynı olgunun iki yüzüdür. İki ayrı
        istek atsaydık, aralarına giren tek bir görüntülenme öneriyi tabloyla
        çelişir hâle getirir ve sahip hangisine inanacağını bilemezdi.
    */
    const insights = useMenuInsights(workspaceId);

    const menuItemCount = dashboardMenuTree
        ? dashboardMenuTree.categories.reduce(
              (total, category) => total + category.menuItems.length,
              0,
          )
        : 0;
    const visibleItemCount = dashboardMenuTree
        ? dashboardMenuTree.categories.reduce(
              (total, category) =>
                  total + category.menuItems.filter((item) => item.isVisible).length,
              0,
          )
        : 0;
    const hiddenItemCount = menuItemCount - visibleItemCount;

    return (
        <div id="section-dashboard">
            {/*
                Başlık ARTIK ÇERÇEVEDEN GELMİYOR (FF-131, AEP `DESIGN_SPEC` §2).

                `WorkspacePageFrame`'in `title`/`description` çifti her sayfaya
                aynı açılışı verir: başlık, altında bir açıklama paragrafı.
                Home'da bu yanlıştı — teslim paketinin çalışan ekranı bir
                KARŞILAMAYLA açılıyor ve panelin ne yaptığını anlatmıyor. Her
                sabah aynı ekranı açan bir restoran sahibine ürünün kendini
                tanıtması, ikinci günden itibaren okunmayan bir paragraftır.

                Tek `h1` kuralı korunuyor (`docs/102` §4): başlık bloğun
                içinde, sayfada bir tane.
            */}
            <WorkspacePageFrame measure="standard">
                <DashboardGreeting brand={brand} />

                <DashboardSetupJourney
                    brand={brand}
                    location={location}
                    dashboardMenuTree={dashboardMenuTree}
                    workspaceId={workspaceId}
                    onNavigateToSection={onNavigateToSection}
                    noviceHome={noviceHome}
                />

                {/*
                    ÖLÇÜMDEN ÇIKAN ÖNERİLER — kaynağın Home'unda "Şimdi"
                    kartının hemen altında (`docs/109` §6.1). Ölçüm
                    okunamadıysa bölüm kendini çizmez; karar bileşenin
                    içindedir, çünkü "veri var mı" sorusunu ancak veriyi
                    okuyan cevaplayabilir.
                */}
                <DashboardSuggestions
                    insights={insights}
                    onNavigateToSection={onNavigateToSection}
                />

                {/*
                    Kurulum kartı "ilk gün"ün listesidir ve bir kez biter;
                    bu dört karo "her gün"ün listesidir ve hiç bitmez.
                */}
                <DashboardQuickActions onNavigateToSection={onNavigateToSection} />

                {dashboardMenuTree ? (
                    <>
                        {/*
                            Kart genişliği 12rem'den 14rem'e çıktı: rakam AEP
                            metrik ölçeğine (2–3rem) yükseldi ve eski asgari
                            genişlikte "Visible items" gibi iki kelimelik bir
                            etiket ile üç haneli bir sayı aynı kutuda sıkışıp
                            ikisi de sarıyordu.
                        */}
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))] gap-4">
                            <StatCard
                                label={t('dashboard.stats.categories')}
                                value={dashboardMenuTree.categories.length}
                            />
                            {/*
                                DESTEK SATIRI, kaynağın "delta" yuvası
                                (`docs/109` §1). Kaynak oraya bir geçmiş dönem
                                karşılaştırması yazıyor ("%12 · geçen
                                perşembe"); depoda o karşılaştırma ÖLÇÜLMÜYOR
                                (`analytics_events` üzerinde günlük seri ya da
                                önceki dönem sorgusu yok) ve uydurulmuyor.
                                Yerine aynı sayının gerçek bileşimi duruyor:
                                kaç ürün gizli. Sıfırken cümle olumsuzlanmaz —
                                "0 gizli" okuyana bir eksiklik ima eder.
                            */}
                            <StatCard
                                label={t('dashboard.stats.items')}
                                value={menuItemCount}
                                support={
                                    hiddenItemCount > 0
                                        ? t('dashboard.stats.hidden', {
                                              count: String(hiddenItemCount),
                                          })
                                        : t('dashboard.stats.allVisible')
                                }
                            />
                            <StatCard
                                label={t('dashboard.stats.visible')}
                                value={`${visibleItemCount} / ${menuItemCount}`}
                            />
                        </div>

                        {/*
                            Home'un tek "dışarıdan gelen haber"i: misafirin
                            gözü menüde nereye gidiyor. Üstteki sayaçlar
                            sahibin zaten bildiğini söyler, bu tablo
                            bilmediğini.
                        */}
                        <DashboardTopViewed
                            insights={insights}
                            dashboardMenuTree={dashboardMenuTree}
                            onNavigateToSection={onNavigateToSection}
                        />

                        <OpsCard title={t('dashboard.table.heading')} padded={false}>
                            <ResponsiveDataTable<DashboardMenuItemTableRow>
                                caption={t('dashboard.table.caption')}
                                columns={DASHBOARD_MENU_ITEM_COLUMNS}
                                rows={dashboardMenuTree.categories.flatMap((category) =>
                                    category.menuItems.map((item) => ({
                                        ...item,
                                        categoryName: category.name,
                                    })),
                                )}
                                getRowKey={(row) => String(row.id)}
                            />
                        </OpsCard>
                    </>
                ) : (
                    /*
                        Boş durumun TEK çıkış yolu bir ölü bağlantıydı:
                        `href="#menu"`, adres tabanlı gezintiye geçildiğinden
                        beri hiçbir şey yapmıyordu (`docs/70`). Menüsü olmayan
                        bir kullanıcının Home'da yapabileceği tek şey buydu.
                    */
                    <div className="flex flex-col gap-3">
                        <PageState
                            kind="empty"
                            title={t('dashboard.empty')}
                            {...(onNavigateToSection
                                ? {
                                      action: (
                                          <Button
                                              size="sm"
                                              onClick={() => onNavigateToSection('menu')}
                                          >
                                              {t('dashboard.empty.openMenu')}
                                          </Button>
                                      ),
                                  }
                                : { whyNoAction: t('dashboard.empty') })}
                        />
                    </div>
                )}
            </WorkspacePageFrame>
        </div>
    );
}

export default DashboardPage;
