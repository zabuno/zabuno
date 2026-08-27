import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { ThemeProvider } from 'flowbite-react/theme/provider';
import { t } from '../../i18n/theme';
import { FLOWBITE_TOKEN_APPLY, flowbiteTokenTheme } from '../../design-system/flowbite-theme';

export const THEME_STORAGE_KEY = 'zabuno-theme';

type ThemePreference = 'system' | 'light' | 'dark';

const THEME_OPTIONS: ThemePreference[] = ['system', 'light', 'dark'];

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

const optionLabels: Record<ThemePreference, () => string> = {
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

    const optionRefs = useRef<Record<ThemePreference, HTMLButtonElement | null>>({
        system: null,
        light: null,
        dark: null,
    });

    const choose = useCallback((next: ThemePreference, focus = false) => {
        setPreference(next);
        try {
            window.localStorage.setItem(THEME_STORAGE_KEY, next);
        } catch {
            // storage unavailable — in-memory preference still applies for this session
        }
        if (focus) {
            optionRefs.current[next]?.focus();
        }
    }, []);

    return (
        <>
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
            {/*
                Seçici artık SABİT DEĞİL.

                320×480'de (iPhone 4) sabit bir alt çubuk ekranın kalıcı
                olarak %12'sini kaplıyor ve içeriğin üstüne biniyordu — küçük
                ekranda en pahalı şey dikey alandır. Ayrıca "yapıştırılmış"
                bir kontrol, sayfanın hiçbir görevine ait değildir.
                
                Yerine akış içinde, sayfanın sonunda durur. Kabuklar isterse
                kendi üst çubuklarına yerleştirir (`ThemeSwitcher`).
            */}
            <div
                role="radiogroup"
                aria-label={t('theme.group_label')}
                className="flex justify-center py-[var(--space-3)] motion-reduce:transition-none"
            >
                <div className="flex gap-1 rounded-pill border border-border bg-surface/95 p-1 shadow-sm backdrop-blur-sm">
                    {THEME_OPTIONS.map((option) => {
                        const checked = preference === option;
                        return (
                            <button
                                key={option}
                                ref={(node) => {
                                    optionRefs.current[option] = node;
                                }}
                                type="button"
                                role="radio"
                                aria-checked={checked}
                                aria-label={optionLabels[option]()}
                                tabIndex={checked ? 0 : -1}
                                onClick={() => choose(option)}
                                onKeyDown={(event) => {
                                    const currentIndex = THEME_OPTIONS.indexOf(option);
                                    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                                        event.preventDefault();
                                        const next =
                                            THEME_OPTIONS[
                                                (currentIndex + 1) % THEME_OPTIONS.length
                                            ];
                                        choose(next, true);
                                    } else if (
                                        event.key === 'ArrowLeft' ||
                                        event.key === 'ArrowUp'
                                    ) {
                                        event.preventDefault();
                                        const prev =
                                            THEME_OPTIONS[
                                                (currentIndex - 1 + THEME_OPTIONS.length) %
                                                    THEME_OPTIONS.length
                                            ];
                                        choose(prev, true);
                                    } else if (event.key === 'Home') {
                                        event.preventDefault();
                                        choose(THEME_OPTIONS[0], true);
                                    } else if (event.key === 'End') {
                                        event.preventDefault();
                                        choose(THEME_OPTIONS[THEME_OPTIONS.length - 1], true);
                                    }
                                }}
                                className="flex min-h-11 min-w-11 items-center justify-center rounded-pill px-3 text-body font-medium text-fg-secondary transition-colors hover:bg-surface-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 motion-reduce:transition-none aria-checked:bg-action aria-checked:text-action-fg aria-checked:forced-colors:outline aria-checked:forced-colors:outline-2 aria-checked:forced-colors:outline-offset-2"
                            >
                                {optionLabels[option]()}
                            </button>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
