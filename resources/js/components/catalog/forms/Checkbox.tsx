import { forwardRef } from 'react';
import { Checkbox as FlowbiteCheckbox } from 'flowbite-react';
import type { CheckboxProps as FlowbiteCheckboxProps } from 'flowbite-react';

export type CheckboxProps = FlowbiteCheckboxProps & {
    invalid?: boolean;
};

/**
 * Micro building block: a bare checkbox. Knows nothing about labels, help
 * text, error messages, forms, routes, or fetches.
 */
export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(function Checkbox(
    { invalid = false, disabled = false, ...rest },
    ref,
) {
    return (
        <FlowbiteCheckbox
            ref={ref}
            disabled={disabled}
            aria-invalid={invalid || undefined}
            aria-disabled={disabled || undefined}
            {...rest}
        />
    );
});
