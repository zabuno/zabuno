import { t } from '../../../../i18n/workspace';
import { InspectorFrame, type InspectorRow } from '../../inspectors/InspectorFrame';
import type { LocationProfile } from '../../LocationEditForm';
import type { DashboardMenuTree } from '../DashboardPage';

export type LocationInspectorProps = {
    location: LocationProfile;
    brandName: string | null;
    menuTree: DashboardMenuTree | null;
    onNavigateToSection: (section: string) => void;
};

/**
 * Şube formunun BAĞLAM PANELİ — `docs/60`.
 *
 * Şube düzenlerken formun cevaplamadığı soru şudur: bu şubenin menüsü var mı.
 * Adres alanları bunu söylemez, ama şube kaydını açan kişinin bilmek istediği
 * ilk şey çoğu zaman budur.
 *
 * KRİTİK: menü ağacı çalışma alanında SEÇİLİ şubeye aittir. Panelde başka bir
 * şube açıkken o ağacın sayısını göstermek, yanlış şubenin verisini doğru
 * etiketle sunmak olurdu — bilgi vermemekten kötüdür. Bu yüzden menü satırı
 * yalnız ağaç GERÇEKTEN bu şubeye aitken çizilir.
 */
export function LocationInspector({
    location,
    brandName,
    menuTree,
    onNavigateToSection,
}: LocationInspectorProps) {
    const rows: InspectorRow[] = [];

    if (brandName !== null) {
        rows.push({
            key: 'brand',
            label: t('workspace.locations.inspector.brand'),
            value: brandName,
        });
    }

    rows.push({
        key: 'city',
        label: t('workspace.locations.inspector.city'),
        value: `${location.city}, ${location.country_code}`,
    });

    const treeBelongsHere = menuTree !== null && menuTree.locationId === location.id;

    if (treeBelongsHere) {
        const itemCount = menuTree.categories.reduce(
            (total, category) => total + category.menuItems.length,
            0,
        );

        rows.push({
            key: 'menu',
            label: t('workspace.locations.inspector.menu'),
            value: t('workspace.locations.inspector.menu.summary', {
                categories: String(menuTree.categories.length),
                items: String(itemCount),
            }),
        });
    }

    return (
        <InspectorFrame
            title={t('workspace.locations.inspector.title')}
            rows={rows}
            shortcut={
                treeBelongsHere
                    ? {
                          label: t('workspace.locations.inspector.openMenu'),
                          onSelect: () => onNavigateToSection('menu'),
                      }
                    : undefined
            }
        />
    );
}

export default LocationInspector;
