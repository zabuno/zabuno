import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { ThemeProvider } from 'flowbite-react/theme/provider';
import type { Decorator } from '@storybook/react-vite';

import { FLOWBITE_TOKEN_APPLY, flowbiteTokenTheme } from '../design-system/flowbite-theme';
import { pseudoLocalize, pseudoLocalizationEnabled } from '../i18n/pseudo';

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

/**
 * Storybook, ürünün gördüğü Flowbite temasını görmelidir.
 *
 * Bu decorator olmadan story'ler `ThemeProvider`sız render edilirdi ve
 * katalog dışından gelen her Flowbite bileşeni (Modal, Dropdown, doğrudan
 * import edilmiş `Button`) Storybook'ta ham palet, üründe token gösterirdi
 * — yani geliştirme yüzeyi ürünle ilgili YANLIŞ bir şey söylerdi.
 * `ThemeRoot`'un ürün tarafında yaptığının story karşılığı.
 */
export const withFlowbiteTokenTheme: Decorator = (Story) => (
    <ThemeProvider theme={flowbiteTokenTheme} applyTheme={FLOWBITE_TOKEN_APPLY}>
        <Story />
    </ThemeProvider>
);

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

/**
 * SAHTE-YERELLEŞTİRME DECORATOR'Ü — `docs/121` §4.
 *
 * Story'ler metinlerini prop olarak alır; katalog çeviricisinden geçmezler.
 * Yani sunucu tarafındaki ölçüm kipi Storybook'ta hiçbir şeyi dönüştürmez ve
 * "Almanca dar ekranda neyi kırar" sorusu ölçülemez kalırdı.
 *
 * Bu decorator boşluğu kapatır: render edilmiş ağaçtaki METİN DÜĞÜMLERİNİ
 * dönüştürür. Ölçtüğü şey tam olarak `mobile-ux-audit`'in ölçtüğü şeydir —
 * uzayan metnin gerçek bir düzen motorunda ne kırdığı.
 *
 * VARSAYILAN KAPALI ve derleme zamanı bayrağıyla açılır:
 *
 *     VITE_I18N_PSEUDO=1 npm run build-storybook -- -o /tmp/sb-pseudo
 *
 * Çalışma anında açılabilen bir anahtar olsaydı bir gün üretimde açık
 * kalırdı; ürün derlemesinde bu bayrak hiç yoktur.
 */
export const withPseudoLocale: Decorator = (Story) => {
    if (!pseudoLocalizationEnabled()) {
        return <Story />;
    }

    return (
        <PseudoLocaleRoot>
            <Story />
        </PseudoLocaleRoot>
    );
};

function PseudoLocaleRoot({ children }: { children: ReactNode }) {
    const host = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const root = host.current;

        if (root === null) {
            return;
        }

        /*
            Metin düğümleri tek tek dönüştürülür, `innerHTML` ile değil:
            işaretlemeyi yeniden yazmak, ölçülen düzenin kendisini
            değiştirirdi ve ölçüm kendi kurduğu bir dünyayı ölçerdi.

            `<script>`/`<style>` atlanır — orada "metin" kod demektir.
        */
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                const parent = node.parentElement?.tagName;

                if (parent === 'SCRIPT' || parent === 'STYLE') {
                    return NodeFilter.FILTER_REJECT;
                }

                return node.nodeValue?.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            },
        });

        const nodes: Text[] = [];

        for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
            nodes.push(node as Text);
        }

        for (const node of nodes) {
            node.nodeValue = pseudoLocalize(node.nodeValue ?? '');
        }
    });

    return <div ref={host}>{children}</div>;
}
