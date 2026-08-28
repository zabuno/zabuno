import { t } from '../../../../i18n/workspace';
import { InspectorFrame, type InspectorRow } from '../../inspectors/InspectorFrame';
import type { DashboardMenuTree } from '../DashboardPage';
import { useCurrentPublication } from '../qr/useCurrentPublication';

export type MenuInspectorProps = {
    workspaceId: number;
    menuTree: DashboardMenuTree;
    locationName: string | null;
    onNavigateToSection: (section: string) => void;
};

/**
 * Menü editörünün BAĞLAM PANELİ — `docs/50` §3.4 ve §13.
 *
 * Sağ panel bir gezinti rayı değildir: üzerinde çalışılan nesnenin ikincil
 * bilgisi ve ayarlarıdır. Menü düzenlerken sürekli sorulan ama ana alanda yeri
 * olmayan sorular buraya gelir — hangi lokasyona ait, yayında mı, hangi sürüm,
 * kaç kategori var.
 *
 * İki kural bu paneli tanımlar:
 *
 * 1. **Temel görev buna BAĞIMLI DEĞİLDİR.** Panel kapalıyken de menü
 *    düzenlenebilir, kategori ve ürün eklenebilir. Panel mobil pakette hiç
 *    bulunmaz ve orada ürün eksiksiz çalışır.
 * 2. **Uydurulmuş alan yoktur.** Şablonda tema, desteklenen diller ve yayın
 *    zamanlaması da geçiyor; bu ürün henüz o üçünü tutmuyor. Var olmayan bir
 *    ayarı boş ya da devre dışı göstermek, kullanıcıya olmayan bir yetenek
 *    vaat etmek olurdu — yalnız GERÇEK veri gösterilir (docs/60).
 */
export function MenuInspector({
    workspaceId,
    menuTree,
    locationName,
    onNavigateToSection,
}: MenuInspectorProps) {
    const { current } = useCurrentPublication(workspaceId, menuTree.id);

    const categoryCount = menuTree.categories.length;
    const itemCount = menuTree.categories.reduce(
        (total, category) => total + category.menuItems.length,
        0,
    );

    const rows: InspectorRow[] = [
        {
            key: 'status',
            label: t('workspace.menu.inspector.status'),
            value:
                current !== null
                    ? t('workspace.menu.inspector.status.published', {
                          version: String(current.version),
                      })
                    : t('workspace.publication.status.notPublished'),
        },
    ];

    if (locationName !== null) {
        rows.push({
            key: 'location',
            label: t('workspace.menu.inspector.location'),
            value: locationName,
        });
    }

    rows.push(
        {
            key: 'categories',
            label: t('workspace.menu.inspector.categories'),
            value: String(categoryCount),
        },
        { key: 'items', label: t('workspace.menu.inspector.items'), value: String(itemCount) },
    );

    return (
        <InspectorFrame
            title={t('workspace.menu.inspector.title')}
            rows={rows}
            shortcut={{
                label: t('workspace.menu.previewAndPublish'),
                onSelect: () => onNavigateToSection('publication'),
            }}
        />
    );
}

export default MenuInspector;
