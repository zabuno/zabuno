import { createTheme } from 'flowbite-react';
import type { ThemeProviderProps } from 'flowbite-react';

/**
 * Flowbite'ın token köküne bağlanması — tasarım sisteminin son halkası.
 *
 * Flowbite bu deponun primitif kaynağıdır, ama varsayılan teması kendi ham
 * paletini (`bg-gray-50`, `text-gray-900`, `focus:ring-primary-500`) ve sabit
 * yüksekliğini (`h-10`) getirir. İkisi de bu deponun kurallarını çiğner:
 *
 * - Ham palet, tema değiştiğinde sessizce yanlış görünür ve
 *   `DS-RAW-PALETTE-BANNED-01`'in yasakladığı şeyin ta kendisidir.
 * - Sabit piksel yükseklik, yoğunluk token'ını (`--density-hit-area-min`)
 *   yok sayar; "compact" moda geçen bir kullanıcı için buton küçülmez,
 *   satır küçülür ve hizalama bozulur.
 *
 * Bu dosya o varsayılanları semantic token'larla değiştirir. Sonuç: Storybook'ta
 * bir token değiştiğinde ürün de değişir — ikisi aynı kaynağı okur. Aksi hâlde
 * "Storybook'ta güzel görünüyordu" ile "üründe farklı" arasındaki boşluk kalıcı
 * olur.
 *
 * Burada YALNIZ görünüm değişir. Flowbite'ın erişilebilirlik davranışı
 * (odak yönetimi, ARIA, klavye) olduğu gibi kalır — zaten onun için
 * kullanılıyor.
 */
export const zabunoFlowbiteTheme = createTheme({
    button: {
        base:
            'group relative flex items-center justify-center rounded-lg text-center font-medium ' +
            'min-h-[var(--density-hit-area-min)] ' +
            'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)] ' +
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ' +
            'disabled:cursor-not-allowed disabled:opacity-60',
        color: {
            default: 'border border-action bg-action text-action-fg hover:brightness-95',
            primary: 'border border-action bg-action text-action-fg hover:brightness-95',
            light: 'border border-border bg-surface text-fg hover:bg-surface-hover',
            gray: 'border border-border bg-surface-subtle text-fg-secondary hover:bg-surface-hover',
            failure:
                'border border-border-danger bg-surface-danger text-fg-danger hover:brightness-95',
            success:
                'border border-border-success bg-surface-success text-fg-success hover:brightness-95',
            warning:
                'border border-border-warning bg-surface-warning text-fg-warning hover:brightness-95',
        },
        size: {
            xs: 'px-2 py-1 text-xs',
            sm: 'px-3 py-1.5 text-sm',
            md: 'px-4 py-2 text-sm',
            lg: 'px-5 py-2.5 text-base',
            xl: 'px-6 py-3 text-base',
        },
    },
    textInput: {
        field: {
            input: {
                base:
                    'block w-full rounded-lg border bg-surface text-fg ' +
                    'min-h-[var(--density-hit-area-min)] ' +
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ' +
                    'disabled:cursor-not-allowed disabled:text-fg-muted disabled:opacity-60',
                colors: {
                    gray: 'border-border placeholder-fg-muted',
                    failure: 'border-border-danger text-fg-danger placeholder-fg-muted',
                },
            },
        },
    },
    select: {
        field: {
            select: {
                base:
                    'block w-full rounded-lg border bg-surface text-fg ' +
                    'min-h-[var(--density-hit-area-min)] ' +
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ' +
                    'disabled:cursor-not-allowed disabled:text-fg-muted disabled:opacity-60',
                colors: {
                    gray: 'border-border',
                    failure: 'border-border-danger text-fg-danger',
                },
            },
        },
    },
    textarea: {
        base:
            'block w-full rounded-lg border bg-surface p-2.5 text-sm text-fg ' +
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ' +
            'disabled:cursor-not-allowed disabled:text-fg-muted disabled:opacity-60',
        colors: {
            gray: 'border-border placeholder-fg-muted',
            failure: 'border-border-danger text-fg-danger placeholder-fg-muted',
        },
    },
    checkbox: {
        base:
            'h-4 w-4 appearance-none rounded border border-border bg-surface ' +
            'bg-[length:0.55em_0.55em] bg-center bg-no-repeat ' +
            'checked:border-transparent checked:bg-current checked:bg-check-icon ' +
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        color: {
            default: 'text-action',
            failure: 'text-fg-danger',
        },
    },
    label: {
        root: {
            base: 'text-sm font-medium',
            colors: {
                default: 'text-fg',
                gray: 'text-fg-secondary',
                failure: 'text-fg-danger',
                success: 'text-fg-success',
                warning: 'text-fg-warning',
                info: 'text-fg-link',
            },
        },
    },
});

/**
 * `createTheme` varsayılan olarak MERGE eder: override'lar Flowbite'ın kendi
 * sınıflarının yanına eklenir, onların yerine geçmez. Sonuç, aynı öğede hem
 * `bg-action` hem `bg-gray-50` bulunmasıdır — hangisinin kazandığı sınıf
 * sırasına kalır ve bu, bilinçli bir tasarım kararı değildir.
 *
 * Bu yüzden override ettiğimiz her anahtar açıkça `replace` olarak
 * işaretlenir. Değiştirmediğimiz her şey Flowbite'ta kalır.
 */
export const zabunoFlowbiteApplyTheme: ThemeProviderProps['applyTheme'] = {
    button: {
        base: 'replace',
        color: 'replace',
        size: 'replace',
    },
    textInput: {
        field: {
            input: {
                base: 'replace',
                colors: 'replace',
            },
        },
    },
    select: {
        field: {
            select: {
                base: 'replace',
                colors: 'replace',
            },
        },
    },
    textarea: {
        base: 'replace',
        colors: 'replace',
    },
    checkbox: {
        base: 'replace',
        color: 'replace',
    },
    label: {
        root: {
            base: 'replace',
            colors: 'replace',
        },
    },
};
