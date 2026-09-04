import type { LocationProfile } from '../LocationEditForm';
import { Select } from '../../catalog/forms/micro/Select';
import { t } from '../../../i18n/workspace';

export type WorkspaceContextControlsProps = {
    locationProfiles: LocationProfile[];
    catalogLocationId: number | null;
    onSelectLocation: (locationId: number) => void;
};

/**
 * Üst çubuktaki BAĞLAM — yalnız gerçekten seçim gerektiren şey.
 *
 * 2026-09-04'e kadar burada üç ad üst üste geliyordu: ürün adı (marka
 * işareti), çalışma alanı adı ve şube seçici. Üçü de "Zabuno" olan bir
 * kiracıda başlık "Zabuno Zabuno Zabuno" diye okunuyordu.
 *
 * Karar (`docs/50` §5 kapsam tablosu): çalışma alanı adı KENAR ÇUBUĞUNUN
 * üstündeki değiştiriciye aittir ve orada zaten duruyor; başlıkta ikinci bir
 * kopyası olmaz. Şube seçici bir SEÇİMDİR ve yalnız seçilecek birden fazla
 * şube varken anlamlıdır — tek şubede açılır liste, tek seçenekli bir
 * kontrol olarak yer kaplar ve hiçbir şey yapmaz.
 */
export function WorkspaceContextControls({
    locationProfiles,
    catalogLocationId,
    onSelectLocation,
}: WorkspaceContextControlsProps) {
    if (locationProfiles.length < 2 || catalogLocationId === null) {
        return null;
    }

    return (
        <Select
            aria-label={t('workspace.shell.currentLocation.label')}
            className="min-w-0 max-w-[16rem]"
            value={String(catalogLocationId)}
            onChange={(event) => onSelectLocation(Number(event.target.value))}
        >
            {locationProfiles.map((location) => (
                <option key={location.id} value={location.id}>
                    {location.display_name}
                </option>
            ))}
        </Select>
    );
}
