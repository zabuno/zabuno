import { t } from '../../../../i18n/workspace';
import { InspectorFrame, type InspectorRow } from '../../inspectors/InspectorFrame';
import type { LocationProfile } from '../../LocationEditForm';

export type BrandInspectorProps = {
    brandName: string;
    locations: LocationProfile[];
    onNavigateToSection: (section: string) => void;
};

/**
 * Marka formunun BAĞLAM PANELİ — `docs/60`.
 *
 * Marka formunda sürekli sorulan ama formda yeri olmayan soru şudur: bu adı ve
 * bu logoyu değiştirirsem NEREYİ değiştirmiş olurum. Form bunu cevaplayamaz,
 * çünkü form yalnız markanın kendisini gösterir.
 *
 * Panel bu yüzden markanın KAPSAMINI gösterir: kaç şubede görünüyor ve hangi
 * şehirlerde. Sayı da şehirler de eldeki şube listesinden gelir; yeni bir
 * istek atılmaz ve hiçbir şey tahmin edilmez.
 */
export function BrandInspector({ brandName, locations, onNavigateToSection }: BrandInspectorProps) {
    const cities = Array.from(new Set(locations.map((location) => location.city))).sort((a, b) =>
        a.localeCompare(b),
    );

    const rows: InspectorRow[] = [
        { key: 'name', label: t('workspace.brand.inspector.name'), value: brandName },
        {
            key: 'locations',
            label: t('workspace.brand.inspector.locations'),
            value: String(locations.length),
        },
    ];

    /*
        Şehir satırı yalnız şehir varsa çizilir. Boş bir "Şehirler" satırı,
        doldurulmayı bekleyen bir alan gibi görünür — oysa ortada eksik bir
        alan değil, henüz açılmamış bir şube vardır.
    */
    if (cities.length > 0) {
        rows.push({
            key: 'cities',
            label: t('workspace.brand.inspector.cities'),
            value: cities.join(', '),
        });
    }

    return (
        <InspectorFrame
            title={t('workspace.brand.inspector.title')}
            rows={rows}
            shortcut={{
                label: t('workspace.brand.inspector.manageLocations'),
                onSelect: () => onNavigateToSection('locations'),
            }}
        />
    );
}

export default BrandInspector;
