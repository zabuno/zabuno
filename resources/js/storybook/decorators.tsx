import { useEffect } from 'react';
import type { Decorator } from '@storybook/react-vite';

export type ThemeMode = 'light' | 'dark' | 'high-contrast';
export type Direction = 'ltr' | 'rtl';
export type Density = 'comfortable' | 'standard' | 'compact';

function ThemeRootSync({ theme }: { theme: ThemeMode }) {
    useEffect(() => {
        const root = document.documentElement;
        const priorDark = root.classList.contains('dark');
        const priorHighContrast = root.classList.contains('high-contrast');
        const priorDataTheme = root.getAttribute('data-theme');
        const priorColorScheme = root.style.colorScheme;

        root.classList.toggle('dark', theme === 'dark' || theme === 'high-contrast');
        root.classList.toggle('high-contrast', theme === 'high-contrast');
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme === 'high-contrast' ? 'dark' : theme;

        return () => {
            root.classList.toggle('dark', priorDark);
            root.classList.toggle('high-contrast', priorHighContrast);
            if (priorDataTheme === null) {
                root.removeAttribute('data-theme');
            } else {
                root.setAttribute('data-theme', priorDataTheme);
            }
            root.style.colorScheme = priorColorScheme;
        };
    }, [theme]);

    return null;
}

export const withTheme: Decorator = (Story, context) => {
    const theme = (context.globals.theme as ThemeMode | undefined) ?? 'light';
    return (
        <div
            data-theme={theme}
            className={
                theme === 'dark'
                    ? 'dark'
                    : theme === 'high-contrast'
                      ? 'dark high-contrast'
                      : undefined
            }
        >
            <ThemeRootSync theme={theme} />
            <Story />
        </div>
    );
};

export const withDirection: Decorator = (Story, context) => {
    const direction = (context.globals.direction as Direction | undefined) ?? 'ltr';
    return (
        <div dir={direction}>
            <Story />
        </div>
    );
};

/**
 * Yoğunluk, token seviyesinde çözülür ve bileşene sızmaz (docs/37 §2.2, X4).
 * Bu decorator yalnız kapsayıcıya modun sınıfını koyar; bileşenler
 * `var(--density-*)` okur ve hangi modda olduklarını bilmezler.
 */
export const withDensity: Decorator = (Story, context) => {
    const density = (context.globals.density as Density | undefined) ?? 'standard';

    return (
        <div className={density === 'standard' ? undefined : `density-${density}`}>
            <Story />
        </div>
    );
};

export const themeAndDirectionGlobalTypes = {
    density: {
        name: 'Density',
        description: 'Bilgi yoğunluğu — satır yüksekliği ve iç boşluk değişir, tipografi değişmez',
        defaultValue: 'standard',
        toolbar: {
            icon: 'component',
            items: [
                { value: 'comfortable', title: 'Comfortable' },
                { value: 'standard', title: 'Standard' },
                { value: 'compact', title: 'Compact' },
            ],
        },
    },
    theme: {
        name: 'Theme',
        description: 'Zabuno theme mode',
        defaultValue: 'light',
        toolbar: {
            icon: 'paintbrush',
            items: [
                { value: 'light', title: 'Light' },
                { value: 'dark', title: 'Dark' },
                { value: 'high-contrast', title: 'High contrast' },
            ],
        },
    },
    direction: {
        name: 'Direction',
        description: 'Text direction',
        defaultValue: 'ltr',
        toolbar: {
            icon: 'transfer',
            items: [
                { value: 'ltr', title: 'LTR' },
                { value: 'rtl', title: 'RTL' },
            ],
        },
    },
};
