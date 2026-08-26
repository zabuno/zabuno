import { Button as FlowbiteButton, Spinner } from 'flowbite-react';
import type { ButtonProps as FlowbiteButtonProps } from 'flowbite-react';

export type ButtonProps = FlowbiteButtonProps<'button'> & {
    loading?: boolean;
    loadingText?: string;
};

/**
 * Micro building block: a bare button with an optional loading state. Knows
 * nothing about forms, routes, or fetches — the caller decides what onClick
 * does.
 */
export function Button({ loading = false, loadingText, disabled, children, ...rest }: ButtonProps) {
    return (
        <FlowbiteButton disabled={disabled || loading} aria-busy={loading || undefined} {...rest}>
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
