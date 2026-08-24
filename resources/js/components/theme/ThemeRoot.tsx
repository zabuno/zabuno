import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { t } from '../../i18n/theme';

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

function applyToDocument(isDark: boolean) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark);
    root.setAttribute('data-theme', isDark ? 'dark' : 'light');
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
            {children}
            <div
                aria-hidden="true"
                data-theme-focus-clearance=""
                style={{ minHeight: 'calc(44px + max(0.75rem, env(safe-area-inset-bottom)))' }}
            />
            <div
                role="radiogroup"
                aria-label={t('theme.group_label')}
                className="fixed inset-x-0 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-50 flex justify-center motion-reduce:transition-none"
            >
                <div className="flex gap-1 rounded-full border border-gray-200 bg-white/95 p-1 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/95">
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
                                className="flex min-h-11 min-w-11 items-center justify-center rounded-full px-3 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 motion-reduce:transition-none dark:text-gray-300 dark:hover:bg-gray-700 aria-checked:bg-blue-700 aria-checked:text-white dark:aria-checked:bg-blue-600 aria-checked:forced-colors:outline aria-checked:forced-colors:outline-2 aria-checked:forced-colors:outline-offset-2"
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
