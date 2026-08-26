import { forwardRef } from 'react';
import { Select as FlowbiteSelect } from 'flowbite-react';
import type { SelectProps as FlowbiteSelectProps } from 'flowbite-react';
import { selectTokenTheme } from '../../../../design-system/flowbite-theme';

export type SelectProps = FlowbiteSelectProps & {
    invalid?: boolean;
};

/**
 * Micro building block: a bare select field. Knows nothing about labels,
 * help text, error messages, forms, routes, or fetches.
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { invalid = false, color, disabled = false, ...rest },
    ref,
) {
    return (
        <FlowbiteSelect
            theme={selectTokenTheme}
            applyTheme="replace"
            ref={ref}
            disabled={disabled}
            aria-invalid={invalid || undefined}
            aria-disabled={disabled || undefined}
            color={color ?? (invalid ? 'failure' : undefined)}
            {...rest}
        />
    );
});
