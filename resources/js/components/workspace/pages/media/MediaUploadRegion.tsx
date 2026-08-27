import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { Button } from '../../../catalog/forms/micro/Button';
import { useId, useRef, useState, type FormEvent } from 'react';
import { FieldError } from '../../../catalog/menu/micro/FieldError';
import { focusFirstInvalidField, ServerRejectedError } from '../../../../lib/validationErrors';
import { t } from '../../../../i18n/workspace';

type UploadStatus = 'idle' | 'pending' | 'success' | 'error';

const SLOT_OPTIONS = [
    ['hero', 'workspace.media.upload.field.assetSlot.hero'],
    ['cards', 'workspace.media.upload.field.assetSlot.cards'],
    ['pricing', 'workspace.media.upload.field.assetSlot.pricing'],
    ['features', 'workspace.media.upload.field.assetSlot.features'],
    ['testimonial', 'workspace.media.upload.field.assetSlot.testimonial'],
    ['avatar', 'workspace.media.upload.field.assetSlot.avatar'],
    ['logo', 'workspace.media.upload.field.assetSlot.logo'],
    ['cover', 'workspace.media.upload.field.assetSlot.cover'],
    ['favicon', 'workspace.media.upload.field.assetSlot.favicon'],
    ['ogImage', 'workspace.media.upload.field.assetSlot.ogImage'],
    ['appIcon', 'workspace.media.upload.field.assetSlot.appIcon'],
    ['profileAvatar', 'workspace.media.upload.field.assetSlot.profileAvatar'],
    ['categoryHero', 'workspace.media.upload.field.assetSlot.categoryHero'],
    ['itemImage', 'workspace.media.upload.field.assetSlot.itemImage'],
    ['gallery', 'workspace.media.upload.field.assetSlot.gallery'],
    ['printLogo', 'workspace.media.upload.field.assetSlot.printLogo'],
    ['emailHeader', 'workspace.media.upload.field.assetSlot.emailHeader'],
] as const;

type MediaUploadRegionProps = {
    onSubmit: (formData: FormData) => Promise<void> | void;
};

/**
 * Görsel yükleme formu.
 *
 * İki kalıcı devre dışı alan (haklar/lisans, son kullanma) BURADAN
 * KALDIRILDI. Bir kontrol, yalnız ileride yapılacak diye devre dışı
 * gösterilmez (`docs/44` devre dışı standardı, `docs/47` Kural 4):
 * kullanıcı onu nasıl etkinleştireceğini bilemez, çünkü etkinleştirmenin
 * bir yolu yoktur. O alanlar geri geldiklerinde çalışır hâlde gelirler.
 */
export function MediaUploadRegion({ onSubmit }: MediaUploadRegionProps) {
    const fileId = useId();
    const altId = useId();
    const slotId = useId();

    const [file, setFile] = useState<File | null>(null);
    const [altText, setAltText] = useState('');
    const [slot, setSlot] = useState('');
    const [status, setStatus] = useState<UploadStatus>('idle');
    /**
     * Yüklemenin neden başarısız olduğu. Öncesinde yalnız bir durum vardı
     * ve ekranda sabit bir "yükleme başarısız" cümlesi görünüyordu; sunucu
     * dosyanın çok büyük olduğunu ya da biçiminin desteklenmediğini
     * söylüyor olsa bile.
     */
    const [failureMessage, setFailureMessage] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        // Öncesi burada sessizce `return` ediyordu: "Upload" düğmesine
        // basmak hiçbir şey yapmıyordu ve kullanıcı neyin eksik olduğunu
        // göremiyordu (`docs/47` Kural 5).
        const errors: Record<string, string> = {};

        if (!file) {
            errors[fileId] = t('workspace.media.upload.error.file.required');
        }

        if (altText.trim() === '') {
            errors[altId] = t('workspace.media.upload.error.altText.required');
        }

        if (slot === '') {
            errors[slotId] = t('workspace.media.upload.error.assetSlot.required');
        }

        setFieldErrors(errors);

        if (Object.keys(errors).length > 0) {
            focusFirstInvalidField(errors, [fileId, altId, slotId]);

            return;
        }

        const formData = new FormData();
        formData.set('file', file as File);
        formData.set('altText', altText);
        formData.set('slot', slot);

        setStatus('pending');
        setFailureMessage('');
        setFieldErrors({});

        try {
            await onSubmit(formData);
            setFile(null);
            setAltText('');
            setSlot('');
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            setStatus('success');
        } catch (error) {
            // YALNIZ sunucunun reddi ekrana çıkar. Ağ kopmasında `error`
            // ham bir JavaScript hatasıdır ("Network failure") ve onu
            // göstermek kullanıcıya iç detay sızdırmaktır.
            setFailureMessage(error instanceof ServerRejectedError ? error.message : '');
            setStatus('error');
        }
    }

    return (
        <form
            role="region"
            aria-label={t('workspace.media.upload.region')}
            onSubmit={(event) => void handleSubmit(event)}
            className="flex max-w-form flex-col gap-3"
        >
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.media.upload.heading')}
            </h3>

            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1">
                    {/* Hata `<label>`in DIŞINDA: içinde olsaydı alanın
                        erişilebilir adına karışırdı. */}
                    <label
                        htmlFor={fileId}
                        className="flex flex-col gap-1 text-body text-fg-secondary"
                    >
                        {t('workspace.media.upload.field.file')}
                        <TextInput
                            id={fileId}
                            name={fileId}
                            type="file"
                            ref={fileInputRef}
                            className="text-body text-fg-secondary"
                            aria-invalid={fieldErrors[fileId] === undefined ? undefined : true}
                            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                        />
                    </label>
                    {fieldErrors[fileId] ? <FieldError message={fieldErrors[fileId]} /> : null}
                </div>

                <div className="flex flex-col gap-1">
                    <label
                        htmlFor={altId}
                        className="flex flex-col gap-1 text-body text-fg-secondary"
                    >
                        {t('workspace.media.upload.field.altText')}
                        <TextInput
                            id={altId}
                            name={altId}
                            type="text"
                            required
                            value={altText}
                            aria-describedby={`${altId}-hint`}
                            aria-invalid={fieldErrors[altId] === undefined ? undefined : true}
                            onChange={(event) => setAltText(event.target.value)}
                        />
                    </label>
                    {/* Alternatif metin bir yasal/erişilebilirlik
                        yükümlülüğüdür; ne yazılacağını bilmeyen kullanıcı
                        dosya adını yazar ve alan işlevini kaybeder. */}
                    <p id={`${altId}-hint`} className="text-meta text-fg-muted">
                        {t('workspace.media.upload.field.altText.hint')}
                    </p>
                    {fieldErrors[altId] ? <FieldError message={fieldErrors[altId]} /> : null}
                </div>

                <div className="flex flex-col gap-1">
                    <label
                        htmlFor={slotId}
                        className="flex flex-col gap-1 text-body text-fg-secondary"
                    >
                        {t('workspace.media.upload.field.assetSlot')}
                        <Select
                            id={slotId}
                            name={slotId}
                            value={slot}
                            aria-invalid={fieldErrors[slotId] === undefined ? undefined : true}
                            onChange={(event) => setSlot(event.target.value)}
                        >
                            <option value="">
                                {t('workspace.media.upload.field.assetSlot.placeholder')}
                            </option>
                            {SLOT_OPTIONS.map(([value, labelKey]) => (
                                <option key={value} value={value}>
                                    {t(labelKey)}
                                </option>
                            ))}
                        </Select>
                    </label>
                    {fieldErrors[slotId] ? <FieldError message={fieldErrors[slotId]} /> : null}
                </div>
            </div>

            <Button
                color="light"
                type="submit"
                disabled={status === 'pending'}
                className="self-start"
            >
                {t('workspace.media.upload.button')}
            </Button>

            {status === 'pending' && (
                <p role="status" className="text-meta text-fg-muted">
                    {t('workspace.media.upload.uploading')}
                </p>
            )}

            {status === 'error' && (
                <p role="alert" className="text-body text-fg-danger">
                    {failureMessage || t('workspace.media.upload.failed')}
                </p>
            )}

            {status === 'success' && (
                <p role="status" className="text-meta text-fg-muted">
                    {t('workspace.media.upload.complete')}
                </p>
            )}

            <p className="text-meta text-fg-muted">{t('workspace.media.security.explanation')}</p>
        </form>
    );
}

export default MediaUploadRegion;
