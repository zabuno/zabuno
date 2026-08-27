import { forwardRef, useId } from 'react';
import { HelperText } from 'flowbite-react';
import { Label } from '../micro/Label';
import { TextInput, type TextInputProps } from '../micro/TextInput';

export type TextFieldProps = TextInputProps & {
    label: string;
    helpText?: string;
    errorText?: string;
    required?: boolean;
};

/**
 * Compound: Label + TextInput + yardım/hata metni.
 *
 * `SelectField` vardı, karşılığı yoktu. Sonuç, her formun kendi etiket ve
 * hata düzenini elle kurmasıydı — ve elle kurulan on formdan onunda
 * `aria-describedby` bağı hiç yoktu, yani ekran okuyucu hatayı alana
 * bağlayamıyordu.
 *
 * Bu bileşen ne micro'nun işaretlemesini ne de erişilebilirlik davranışını
 * yeniden yazar; yalnız etrafına label/aria bağlarını kurar.
 *
 * `ref` iletilir: gönderim sonrası odağın İLK hatalı alana taşınması için
 * çağıran tarafın alana tutunabilmesi gerekir.
 */
export const TextField = forwardRef<HTMLInputElement, TextFieldProps>(function TextField(
    { label, helpText, errorText, required = false, id, ...inputProps },
    ref,
) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    const helpId = helpText ? `${fieldId}-help` : undefined;
    const errorId = errorText ? `${fieldId}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={fieldId} required={required}>
                {label}
            </Label>
            <TextInput
                id={fieldId}
                ref={ref}
                invalid={Boolean(errorText)}
                aria-required={required || undefined}
                aria-describedby={describedBy}
                {...inputProps}
            />
            {helpText ? (
                <HelperText id={helpId} color="gray">
                    {helpText}
                </HelperText>
            ) : null}
            {errorText ? (
                <HelperText id={errorId} color="failure" role="alert" aria-live="polite">
                    {errorText}
                </HelperText>
            ) : null}
        </div>
    );
});
