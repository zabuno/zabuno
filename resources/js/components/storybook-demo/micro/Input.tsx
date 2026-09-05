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
                // `outline-none` YOK: taban biçimi `none`a çekiyor ve
                // `focus-visible:outline-2` genişlik verse de çizgi
                // çizilmiyordu (docs/71).
                /*
                    YÜKSEKLİK BİR TOPLAM DEĞİL, BİR KARARDIR (`docs/117` M2).

                    Ölçüldü (320×568): alan 320×42 çiziliyordu — `py-2` artı
                    satır yüksekliği artı kenarlık. Hiç kimse 42 demedi; 42
                    ortaya çıktı. `--control-height` o kararı geri alır ve
                    hedef tabanının (44) altına inmeyi imkânsız kılar.
                */
                'block min-h-[var(--control-height)] w-full rounded-md border px-3 py-2 text-body transition-colors',
                // Halka değil ana hat (`docs/71`).
                'focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2',
                invalid
                    ? 'border-border-danger focus-visible:outline-fg-danger'
                    : 'border-border focus-visible:outline-focus',
                disabled && 'cursor-not-allowed opacity-60',
                className,
            )}
            {...rest}
        />
    );
});
