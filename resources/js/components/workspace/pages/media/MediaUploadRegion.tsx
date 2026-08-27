import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { Button } from '../../../catalog/forms/micro/Button';
import { useEffect, useId, useState, type FormEvent } from 'react';
import { MediaDropzone, type SelectedImage } from './MediaDropzone';
import { FieldError } from '../../../catalog/menu/micro/FieldError';
import { focusFirstInvalidField, ServerRejectedError } from '../../../../lib/validationErrors';
import { t } from '../../../../i18n/workspace';

type UploadStatus = 'idle' | 'pending' | 'success' | 'error';

/**
 * Slot politikası — nerede kullanılacağı ve o yerin ne gerektirdiği.
 *
 * Liste artık burada SABİT DEĞİL; sunucudan gelir
 * (`GET /api/media/slot-policies`). İki sebebi var:
 *
 *   1. Minimum ölçü, format ve oran tek yerde yaşamalı; iki liste bir gün
 *      ayrışır ve kullanıcı reddedilene kadar bunu bilmez.
 *   2. Sunucu yalnız RESTORAN yüzeyinin slotlarını döndürür. Burada sabit
 *      dururken "Pricing", "Features" ve "Testimonial" de listedeydi —
 *      onlar Zabuno'nun kendi tanıtım sitesine ait (`docs/50`).
 */
type SlotPolicy = {
    key: string;
    minWidth: number;
    minHeight: number;
    aspect: string | null;
    formats: string[];
    altRequired: boolean;
};

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

    const [policies, setPolicies] = useState<SlotPolicy[]>([]);
    const [selected, setSelected] = useState<SelectedImage | null>(null);
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

    // Politikalar workspace'e bağlı değil; bir kez okunur.
    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch('/api/media/slot-policies', {
                    credentials: 'same-origin',
                });

                if (!response.ok || cancelled) return;

                const body = (await response.json()) as { slots?: SlotPolicy[] };

                if (!cancelled) setPolicies(body.slots ?? []);
            } catch {
                // Politika okunamazsa slot listesi boş kalır ve form
                // "önce bir yer seçin" der. Sessizce yanlış bir liste
                // göstermekten iyidir.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, []);

    const activePolicy = policies.find((candidate) => candidate.key === slot) ?? null;

    /**
     * Seçilen görsel bu slot için yeterince büyük mü?
     *
     * Kontrol İSTEMCİDE yapılır ama sunucunun yerine geçmez: amaç,
     * kullanıcının yükleme bitene kadar beklemeden öğrenmesi. Reddin
     * otoritesi sunucudadır (`docs/49` Faz 2).
     */
    const tooSmall =
        selected !== null &&
        activePolicy !== null &&
        selected.width > 0 &&
        (selected.width < activePolicy.minWidth || selected.height < activePolicy.minHeight);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        // Öncesi burada sessizce `return` ediyordu: "Upload" düğmesine
        // basmak hiçbir şey yapmıyordu ve kullanıcı neyin eksik olduğunu
        // göremiyordu (`docs/47` Kural 5).
        const errors: Record<string, string> = {};

        if (!selected) {
            errors[fileId] = t('workspace.media.upload.error.file.required');
        } else if (tooSmall && activePolicy) {
            // Reddin sebebi somut söylenir: "yeterince büyük değil" değil,
            // KAÇ olduğu ve KAÇ olması gerektiği — ve neden büyütülmediği.
            errors[fileId] = t('workspace.media.upload.error.tooSmall', {
                width: String(selected.width),
                height: String(selected.height),
                slot: activePolicy.key,
                min: `${activePolicy.minWidth} × ${activePolicy.minHeight}`,
            });
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
        formData.set('file', selected!.file);
        formData.set('altText', altText);
        formData.set('slot', slot);

        setStatus('pending');
        setFailureMessage('');
        setFieldErrors({});

        try {
            await onSubmit(formData);
            // Girdiyi temizlemek artık damlatma alanının işi: gerçek
            // `<input type=file>` orada yaşıyor.
            setSelected(null);
            setAltText('');
            setSlot('');
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
            // Tarayıcının kendi doğrulaması KAPALI. Açık kaldığında `required`
            // taşıyan alan yüzünden tarayıcı kendi baloncuğunu gösteriyor ve
            // BİZİM işleyicimiz hiç çalışmıyordu: ekranda ne bizim mesajımız
            // vardı ne de odak doğru alana gidiyordu. Bu, yerelde gerçek
            // tarayıcıyla denenerek bulundu; jsdom yerel doğrulamayı
            // çalıştırmadığı için testler yeşildi.
            noValidate
        >
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.media.upload.heading')}
            </h3>

            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1">
                    <span className="text-body text-fg-secondary">
                        {t('workspace.media.upload.field.file')}
                    </span>
                    <MediaDropzone
                        selected={selected}
                        invalid={fieldErrors[fileId] !== undefined}
                        describedBy={fieldErrors[fileId] ? `${fileId}-error` : undefined}
                        onSelect={setSelected}
                    />
                    {fieldErrors[fileId] ? (
                        <span id={`${fileId}-error`}>
                            <FieldError message={fieldErrors[fileId]} />
                        </span>
                    ) : null}
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
                            {policies.map((policy) => (
                                <option key={policy.key} value={policy.key}>
                                    {t(
                                        `workspace.media.upload.field.assetSlot.${policy.key}` as Parameters<
                                            typeof t
                                        >[0],
                                    )}
                                </option>
                            ))}
                        </Select>
                    </label>

                    {/*
                        Slot seçilince GEREKSİNİMLERİ görünür.

                        Öncesinde kullanıcı 17 opak ad arasından seçim yapıyor
                        ve hangi ölçüde görsel yükleyeceğini hiçbir yerden
                        öğrenemiyordu; bulanık görseli ancak yayınladıktan
                        sonra fark ediyordu.
                    */}
                    {activePolicy ? (
                        <ul className="flex flex-col gap-0.5 text-meta text-fg-muted">
                            <li>
                                {t('workspace.media.upload.requirement.minimum', {
                                    width: String(activePolicy.minWidth),
                                    height: String(activePolicy.minHeight),
                                })}
                            </li>
                            {activePolicy.aspect ? (
                                <li>
                                    {t('workspace.media.upload.requirement.aspect', {
                                        aspect: activePolicy.aspect,
                                    })}
                                </li>
                            ) : null}
                            <li>
                                {t('workspace.media.upload.requirement.formats', {
                                    formats: activePolicy.formats.join(', '),
                                })}
                            </li>
                        </ul>
                    ) : null}

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
