import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { ThemeProvider } from 'flowbite-react/theme/provider';
import { t } from '../../i18n/theme';
import { FLOWBITE_TOKEN_APPLY, flowbiteTokenTheme } from '../../design-system/flowbite-theme';

export const THEME_STORAGE_KEY = 'zabuno-theme';

export type ThemePreference = 'system' | 'light' | 'dark';

export const THEME_OPTION_ORDER: readonly ThemePreference[] = ['system', 'light', 'dark'];

type ThemeControl = {
    preference: ThemePreference;
    choose: (next: ThemePreference) => void;
};

/**
 * Tema tercihi bir BAĞLAM — `docs/63`.
 *
 * Kontrol önceden uygulamanın dibinde, her sayfanın altında duruyordu. Tema
 * hiçbir sayfanın görevi değildir; kişisel bir tercihtir ve hesap menüsüne
 * aittir. Ama tercihi TUTAN yer değişmedi: hâlâ `ThemeRoot`, çünkü belgeye
 * sınıfı yazan ve `localStorage`'ı okuyan orasıdır. Bağlam yalnız kontrolü
 * çizecek yerin değişmesini sağlar.
 */
const ThemeControlContext = createContext<ThemeControl | null>(null);

/**
 * Tema kontrolü — sağlayıcı yoksa `null`.
 *
 * İlk hâli sağlayıcı yokken HATA FIRLATIYORDU ve gerekçesi şuydu: sessizce
 * çalışmayan bir kontrol göstermek yalandır. Gerekçe doğru, sonuç yanlıştı —
 * fırlatmak, temayı hiç yönetmeyen bir kabuğu da çökertiyordu.
 *
 * Doğru cevap üçüncüsü: sağlayıcı yoksa kontrol HİÇ ÇİZİLMEZ. Çağıran
 * `null` görür ve görünüm bölümünü atlar. Böylece ne çalışmayan bir düğme
 * kalır, ne de temasız bir kabuk çöker.
 */
export function useThemeControl(): ThemeControl | null {
    return useContext(ThemeControlContext);
}

function isThemePreference(value: string | null): value is ThemePreference {
    return value === 'system' || value === 'light' || value === 'dark';
}

function readStoredPreference(): ThemePreference {
    try {
        const stored = window.localStorage.getItem(THEME_STORAGE_KEY);
        return isThemePreference(stored) ? stored : 'system';
    } catch {
        return 'system';
    }
}

function resolveIsDark(preference: ThemePreference, systemPrefersDark: boolean): boolean {
    return preference === 'dark' || (preference === 'system' && systemPrefersDark);
}

/**
 * Arayüz yoğunluğu.
 *
 * Külliyat üç mod tanımlıyor (comfortable / standard / compact) ve CSS'te
 * üçü de vardı — ama hiçbir bileşen uygulamıyordu, yani mod ÖLÜYDÜ.
 *
 * Şimdi kök öğede bir öznitelik olarak yaşıyor. Bugün tek bir değer
 * kullanılıyor; kullanıcıya seçim sunmak ayrı bir ÜRÜN kararıdır ve
 * sahibinindir. Bu sabit, o karar verildiğinde bağlanacak tek yerdir.
 */
const INTERFACE_DENSITY = 'standard' as const;

function applyToDocument(isDark: boolean) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark);
    root.setAttribute('data-theme', isDark ? 'dark' : 'light');
    root.setAttribute('data-density', INTERFACE_DENSITY);
    root.style.colorScheme = isDark ? 'dark' : 'light';
}

export const themeOptionLabels: Record<ThemePreference, () => string> = {
    system: () => t('theme.system'),
    light: () => t('theme.light'),
    dark: () => t('theme.dark'),
};

type ThemeRootProps = {
    children: ReactNode;
};

export function ThemeRoot({ children }: ThemeRootProps) {
    const [preference, setPreference] = useState<ThemePreference>(() => readStoredPreference());
    const [systemPrefersDark, setSystemPrefersDark] = useState<boolean>(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return false;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    });

    useEffect(() => {
        if (typeof window.matchMedia !== 'function') {
            return;
        }

        const mql = window.matchMedia('(prefers-color-scheme: dark)');

        const handleChange = (event: MediaQueryListEvent) => {
            setSystemPrefersDark(event.matches);
        };

        mql.addEventListener('change', handleChange);
        return () => mql.removeEventListener('change', handleChange);
    }, []);

    useEffect(() => {
        applyToDocument(resolveIsDark(preference, systemPrefersDark));
    }, [preference, systemPrefersDark]);

    const choose = useCallback((next: ThemePreference) => {
        setPreference(next);
        try {
            window.localStorage.setItem(THEME_STORAGE_KEY, next);
        } catch {
            // storage unavailable — in-memory preference still applies for this session
        }
    }, []);

    const control = useMemo<ThemeControl>(() => ({ preference, choose }), [preference, choose]);

    return (
        <ThemeControlContext value={control}>
            {/*
              Flowbite'ı token köküne bağlar. `auth/`, `workspace/` ve
              `admin/` altında Flowbite `Button`/`TextInput`/`Select`
              DOĞRUDAN import eden dosyalar var; onlar katalog primitifinden
              geçmediği için tek tek sarmalanamaz. Tema burada, uygulamanın
              tepesinde bağlanınca hepsi birden `--color-*` ve `--density-*`
              okur. Katalog primitifleri aynı tanımı ayrıca prop olarak da
              uygular; bkz. `design-system/flowbite-theme`.
            */}
            <ThemeProvider theme={flowbiteTokenTheme} applyTheme={FLOWBITE_TOKEN_APPLY}>
                {children}
            </ThemeProvider>
        </ThemeControlContext>
    );
}
