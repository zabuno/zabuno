import { Button as FlowbiteButton, Spinner } from 'flowbite-react';
import type { ButtonProps as FlowbiteButtonProps } from 'flowbite-react';
import { buttonTokenTheme } from '../../../../design-system/flowbite-theme';

export type ButtonProps = FlowbiteButtonProps<'button'> & {
    loading?: boolean;
    loadingText?: string;
};

/**
 * Micro building block: a bare button with an optional loading state. Knows
 * nothing about forms, routes, or fetches — the caller decides what onClick
 * does.
 *
 * Temayı burada AYRICA uygular. `ThemeRoot` zaten bir `ThemeProvider`
 * kuruyor, ama bir provider yalnız kendi ağacını kapsar: bu bileşen bir
 * testte veya story'de tek başına render edildiğinde sağlayıcısız kalır ve
 * o durumda sessizce Flowbite'ın ham paletine düşerdi. Tanım tek yerde
 * (`design-system/flowbite-theme`), uygulama iki yerde.
 */
export function Button({ loading = false, loadingText, disabled, children, ...rest }: ButtonProps) {
    return (
        <FlowbiteButton
            theme={buttonTokenTheme}
            applyTheme="replace"
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...rest}
        >
            {loading ? (
                <span className="flex items-center gap-2">
                    <Spinner size="sm" aria-hidden="true" />
                    <span>{loadingText ?? children}</span>
                </span>
            ) : (
                children
            )}
        </FlowbiteButton>
    );
}
