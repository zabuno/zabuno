import { createContext, useContext } from 'react';
import { t } from '../../i18n/theme';

/**
 * ARAYÜZ YOĞUNLUĞU — ölü sabitten gerçek bir tercihe (FF-128).
 *
 * Külliyat üç mod tanımlıyor ve CSS'te üçü de vardı, ama `ThemeRoot` içinde
 * `INTERFACE_DENSITY = 'standard'` diye SABİT yazılıydı: mod tanımlıydı,
 * ölçülüydü, test ediliydi — ve hiç kimse değiştiremiyordu. Bir ayarın var
 * olması, ona erişilebilmesi demek değildir.
 *
 * Yoğunluk KİŞİSELDİR ve cihaza bağlıdır: aynı restoranın müdürü ofiste geniş
 * bir ekranda çok satır görmek ister, serviste tabletle dolaşırken parmakla
 * dokunacağı büyük hedefler ister. Bu yüzden tema gibi tarayıcıda saklanır ve
 * çalışma alanıyla birlikte taşınmaz.
 *
 * Dokunma hedefi hiçbir modda küçülmez: `--density-hit-area-min` üç modda da
 * 44 pikseldir. Yoğunluk yalnız DOLGUDAN gelir, hedefi küçültmekten değil.
 */
export type DensityPreference = 'comfortable' | 'standard' | 'compact';

export const DENSITY_OPTION_ORDER: readonly DensityPreference[] = [
    'comfortable',
    'standard',
    'compact',
];

export const DENSITY_STORAGE_KEY = 'zabuno.density';

export const DEFAULT_DENSITY: DensityPreference = 'standard';

export const densityOptionLabels: Record<DensityPreference, () => string> = {
    comfortable: () => t('density.comfortable'),
    standard: () => t('density.standard'),
    compact: () => t('density.compact'),
};

export function isDensityPreference(value: unknown): value is DensityPreference {
    return value === 'comfortable' || value === 'standard' || value === 'compact';
}

export type DensityControl = {
    preference: DensityPreference;
    choose: (next: DensityPreference) => void;
};

/** Sağlayıcı yoksa kontrol HİÇ çizilmez — tema kontrolüyle aynı kural. */
export const DensityControlContext = createContext<DensityControl | null>(null);

export function useDensityControl(): DensityControl | null {
    return useContext(DensityControlContext);
}
