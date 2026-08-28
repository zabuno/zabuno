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
    /**
     * Alanın altında duran açıklama: ne beklendiği, sonradan
     * değiştirilebilir mi, neyi etkiler.
     *
     * Bir alan adı çoğu zaman soruyu tam sormaz. "Para birimi" yazan bir
     * kutu, kullanıcının bilmediği iki şeyi cevaplamaz: bu seçim neyi
     * etkiler ve sonradan değiştirilebilir mi. O bilgi olmadan kullanıcı ya
     * yanlış seçer ya da hiç seçemez.
     */
    helpText?: string;
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
    helpText,
}: FormFieldProps) {
    const errorId = errorText ? `${id}-error` : undefined;
    const helpId = helpText ? `${id}-help` : undefined;
    // Hem açıklama hem hata varsa İKİSİ de bağlanır: ekran okuyucu önce ne
    // istendiğini, sonra neyin yanlış olduğunu okur.
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

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
                aria-describedby={describedBy}
                value={value}
                disabled={disabled}
                readOnly={readOnly}
                onChange={onChange ? (event) => onChange(event.target.value) : undefined}
            />
            {helpText ? <HelperText id={helpId}>{helpText}</HelperText> : null}
            {errorText ? (
                <HelperText id={errorId} color="failure" role="alert" aria-live="polite">
                    {errorText}
                </HelperText>
            ) : null}
        </div>
    );
}

export default FormField;
