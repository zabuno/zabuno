import { forwardRef } from 'react';
import { Textarea as FlowbiteTextarea } from 'flowbite-react';
import type { TextareaProps as FlowbiteTextareaProps } from 'flowbite-react';
import { textareaTokenTheme } from '../../../../design-system/flowbite-theme';

export type TextareaProps = FlowbiteTextareaProps & {
    invalid?: boolean;
};

/**
 * Micro building block: a bare textarea. Knows nothing about labels, help
 * text, error messages, forms, routes, or fetches.
 */
export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { invalid = false, color, disabled = false, ...rest },
    ref,
) {
    return (
        <FlowbiteTextarea
            theme={textareaTokenTheme}
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
