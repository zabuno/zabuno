import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { ThemeProvider } from 'flowbite-react/theme/provider';
import {
    DEFAULT_DENSITY,
    DENSITY_STORAGE_KEY,
    DensityControlContext,
    isDensityPreference,
    type DensityControl,
    type DensityPreference,
} from './densityControl';
import { ThemeControlContext, type ThemeControl, type ThemePreference } from './themeControl';
import { FLOWBITE_TOKEN_APPLY, flowbiteTokenTheme } from '../../design-system/flowbite-theme';

export const THEME_STORAGE_KEY = 'zabuno-theme';

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
 * Arayüz yoğunluğu — artık SABİT değil, tercih (FF-128).
 *
 * Külliyat üç mod tanımlıyor ve CSS'te üçü de vardı, ama burada
 * `INTERFACE_DENSITY = 'standard'` diye yazılıydı: mod tanımlıydı, ölçülüydü,
 * test ediliydi ve hiç kimse değiştiremiyordu. Sahibin isteği üzerine
 * (2026-09-04 teslim paketi) seçim kullanıcıya açıldı; tercih tema ile aynı
 * yerde, aynı biçimde saklanır.
 */
function readStoredDensity(): DensityPreference {
    try {
        const stored: unknown = window.localStorage.getItem(DENSITY_STORAGE_KEY);

        // Bilinmeyen bir değer VARSAYILANA düşer. Saklanan metne güvenip
        // özniteliğe yazsaydık, elle kurcalanmış bir tarayıcıda hiçbir
        // yoğunluk kuralıyla eşleşmeyen bir kök doğardı.
        return isDensityPreference(stored) ? stored : DEFAULT_DENSITY;
    } catch {
        return DEFAULT_DENSITY;
    }
}

function applyToDocument(isDark: boolean, density: DensityPreference) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark);
    root.setAttribute('data-theme', isDark ? 'dark' : 'light');
    root.setAttribute('data-density', density);
    root.style.colorScheme = isDark ? 'dark' : 'light';
}

type ThemeRootProps = {
    children: ReactNode;
};

export function ThemeRoot({ children }: ThemeRootProps) {
    const [preference, setPreference] = useState<ThemePreference>(() => readStoredPreference());
    const [density, setDensity] = useState<DensityPreference>(() => readStoredDensity());
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
        applyToDocument(resolveIsDark(preference, systemPrefersDark), density);
    }, [preference, systemPrefersDark, density]);

    const choose = useCallback((next: ThemePreference) => {
        setPreference(next);
        try {
            window.localStorage.setItem(THEME_STORAGE_KEY, next);
        } catch {
            // storage unavailable — in-memory preference still applies for this session
        }
    }, []);

    const chooseDensity = useCallback((next: DensityPreference) => {
        setDensity(next);
        try {
            window.localStorage.setItem(DENSITY_STORAGE_KEY, next);
        } catch {
            // storage unavailable — in-memory preference still applies for this session
        }
    }, []);

    const control = useMemo<ThemeControl>(() => ({ preference, choose }), [preference, choose]);
    const densityControl = useMemo<DensityControl>(
        () => ({ preference: density, choose: chooseDensity }),
        [density, chooseDensity],
    );

    return (
        <ThemeControlContext value={control}>
            <DensityControlContext value={densityControl}>
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
            </DensityControlContext>
        </ThemeControlContext>
    );
}
