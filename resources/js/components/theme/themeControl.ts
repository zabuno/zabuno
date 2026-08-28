import { createContext, useContext } from 'react';
import { t } from '../../i18n/theme';

export type ThemePreference = 'system' | 'light' | 'dark';

export const THEME_OPTION_ORDER: readonly ThemePreference[] = ['system', 'light', 'dark'];

export const themeOptionLabels: Record<ThemePreference, () => string> = {
    system: () => t('theme.system'),
    light: () => t('theme.light'),
    dark: () => t('theme.dark'),
};

export type ThemeControl = {
    preference: ThemePreference;
    choose: (next: ThemePreference) => void;
};

/**
 * Tema tercihi bir BAĞLAM — `docs/63`.
 *
 * Kontrol önceden uygulamanın dibinde, her sayfanın altında duruyordu. Tema
 * hiçbir sayfanın görevi değildir; kişisel bir tercihtir ve hesap menüsüne
 * aittir. Ama tercihi TUTAN yer değişmedi: hâlâ `ThemeRoot`, çünkü belgeye
 * sınıfı yazan ve `localStorage`'ı okuyan orasıdır.
 *
 * Bu dosya `ThemeRoot`'tan AYRI, çünkü bir bileşen dosyasından bileşen
 * olmayan şeyler dışa aktarmak Fast Refresh'i bozar: düzenlemede tüm modül
 * yeniden yüklenir ve durum sıfırlanır.
 */
export const ThemeControlContext = createContext<ThemeControl | null>(null);

/**
 * Tema kontrolü — sağlayıcı yoksa `null`.
 *
 * İlk hâli sağlayıcı yokken HATA FIRLATIYORDU ve gerekçesi şuydu: sessizce
 * çalışmayan bir kontrol göstermek yalandır. Gerekçe doğru, sonuç yanlıştı —
 * fırlatmak, temayı hiç yönetmeyen bir kabuğu da çökertiyordu.
 *
 * Doğru cevap üçüncüsü: sağlayıcı yoksa kontrol HİÇ ÇİZİLMEZ.
 */
export function useThemeControl(): ThemeControl | null {
    return useContext(ThemeControlContext);
}
