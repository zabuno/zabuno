import { forwardRef } from 'react';
import type { InputHTMLAttributes } from 'react';
import clsx from 'clsx';

export type InputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> & {
    invalid?: boolean;
};

/**
 * Micro building block: a bare text input. Knows nothing about labels,
 * help text, error messages, forms, routes, or fetches.
 */
export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { invalid = false, disabled = false, className, ...rest },
    ref,
) {
    return (
        <input
            ref={ref}
            disabled={disabled}
            aria-invalid={invalid || undefined}
            aria-disabled={disabled || undefined}
            className={clsx(
                'block w-full rounded-md border px-3 py-2 text-body outline-none transition-colors',
                'focus-visible:ring-2 focus-visible:ring-offset-1',
                invalid
                    ? 'border-border-danger focus-visible:ring-fg-danger'
                    : 'border-border focus-visible:ring-focus',
                disabled && 'cursor-not-allowed opacity-60',
                className,
            )}
            {...rest}
        />
    );
});
