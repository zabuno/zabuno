import { t } from '../../../../i18n/workspace';
import type { DashboardMenuTree } from '../DashboardPage';
import { useCurrentPublication } from '../qr/useCurrentPublication';

export type MenuInspectorProps = {
    workspaceId: number;
    menuTree: DashboardMenuTree | null;
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
    const { current } = useCurrentPublication(workspaceId, menuTree?.id ?? null);

    if (menuTree === null) {
        return null;
    }

    const categoryCount = menuTree.categories.length;
    const itemCount = menuTree.categories.reduce(
        (total, category) => total + category.menuItems.length,
        0,
    );

    return (
        <div className="flex flex-col gap-[var(--space-fluid-md)]">
            <h2 className="text-body font-semibold text-fg">
                {t('workspace.menu.inspector.title')}
            </h2>

            <dl className="flex flex-col gap-3">
                <InspectorRow
                    label={t('workspace.menu.inspector.status')}
                    value={
                        current !== null
                            ? t('workspace.menu.inspector.status.published', {
                                  version: String(current.version),
                              })
                            : t('workspace.publication.status.notPublished')
                    }
                />
                {locationName !== null ? (
                    <InspectorRow
                        label={t('workspace.menu.inspector.location')}
                        value={locationName}
                    />
                ) : null}
                <InspectorRow
                    label={t('workspace.menu.inspector.categories')}
                    value={String(categoryCount)}
                />
                <InspectorRow
                    label={t('workspace.menu.inspector.items')}
                    value={String(itemCount)}
                />
            </dl>

            {/*
                Panelin tek eylemi, ana alanda ZATEN bulunan yayınlama yoluna
                götürür. Panel yeni bir yetenek eklemez; bağlamı gösterir ve
                bilinen yola kısa yol verir.
            */}
            <button
                type="button"
                onClick={() => onNavigateToSection('publication')}
                className="min-h-[var(--density-hit-area-min)] rounded-md border border-border px-3 py-2 text-body font-medium text-fg-secondary hover:bg-surface-hover"
            >
                {t('workspace.menu.previewAndPublish')}
            </button>
        </div>
    );
}

function InspectorRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-meta text-fg-muted">{label}</dt>
            <dd className="text-body text-fg">{value}</dd>
        </div>
    );
}

export default MenuInspector;
