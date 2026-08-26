import { forwardRef } from 'react';
import { TextInput as FlowbiteTextInput } from 'flowbite-react';
import type { TextInputProps as FlowbiteTextInputProps } from 'flowbite-react';
import { textInputTokenTheme } from '../../../../design-system/flowbite-theme';

export type TextInputProps = FlowbiteTextInputProps & {
    invalid?: boolean;
};

/**
 * Micro: tek satırlık metin alanı.
 *
 * Uygulama boyunca elle yazılmış ham palet sınıflarıyla kurulmuş alanlar
 * vardı; her biri kendi kararını veriyordu ve tema değiştiğinde birinin
 * unutulması kaçınılmazdı. Bu bileşen o kararı tek yere taşır ve Select/Textarea ile
 * aynı Flowbite tabanını paylaşır — böylece bir formdaki üç alan birbirine
 * benzemek zorunda kalmaz, birbirinin AYNISI olur.
 *
 * Etiket, yardım metni, hata mesajı, form veya fetch bilmez.
 */
export const TextInput = forwardRef<HTMLInputElement, TextInputProps>(function TextInput(
    { invalid = false, color, disabled = false, ...rest },
    ref,
) {
    return (
        <FlowbiteTextInput
            theme={textInputTokenTheme}
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
