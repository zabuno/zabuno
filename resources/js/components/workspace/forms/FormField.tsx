import { HelperText, Label, TextInput } from 'flowbite-react';

type FormFieldProps = {
    id: string;
    label: string;
    name: string;
    value: string;
    onChange?: (value: string) => void;
    disabled?: boolean;
    readOnly?: boolean;
    type?: string;
    /** Sunucunun bu alan için söylediği. */
    errorText?: string;
};

/**
 * Micro: one labeled TextInput. Owns no fetch/route/business logic — value
 * and onChange come from the calling form, so it stays a pure presentation
 * building block reusable across Brand/Location edit and onboarding forms.
 *
 * `errorText` 2026-08-27'de eklendi. Öncesinde alan hatası gösterecek yeri
 * yoktu; formlar da zaten sunucunun 422 gövdesini okumuyordu, yani hata
 * mesajı ne üretiliyor ne gösteriliyordu. Şimdi mesaj alana `aria-describedby`
 * ile BAĞLI: ekran okuyucu alanı ve hatasını ilgili iki şey olarak okur,
 * ayrı ayrı değil.
 */
export function FormField({
    id,
    label,
    name,
    value,
    onChange,
    disabled = false,
    readOnly = false,
    type = 'text',
    errorText,
}: FormFieldProps) {
    const errorId = errorText ? `${id}-error` : undefined;

    return (
        <div>
            <div className="mb-2 block">
                <Label htmlFor={id}>{label}</Label>
            </div>
            <TextInput
                id={id}
                name={name}
                type={type}
                className="w-full"
                color={errorText ? 'failure' : undefined}
                aria-invalid={errorText ? true : undefined}
                aria-describedby={errorId}
                value={value}
                disabled={disabled}
                readOnly={readOnly}
                onChange={onChange ? (event) => onChange(event.target.value) : undefined}
            />
            {errorText ? (
                <HelperText id={errorId} color="failure" role="alert" aria-live="polite">
                    {errorText}
                </HelperText>
            ) : null}
        </div>
    );
}

export default FormField;
