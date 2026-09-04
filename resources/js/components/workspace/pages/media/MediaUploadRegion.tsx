import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { Button } from '../../../catalog/forms/micro/Button';
import { useCallback, useEffect, useId, useState, type FormEvent } from 'react';
import { canCropInto, parseAspect } from './cropGeometry';
import { ImageCropField } from './ImageCropField';
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

type UploadLimits = { maxBytes: number; maxMegapixels: number };

export type UploadOptions = {
    /** Yeniden denemede AYNI kalır — sunucu ikinci gönderimi ikinci görsel sanmaz (FF-68). */
    idempotencyKey: string;
    onProgress: (fraction: number) => void;
};

type MediaUploadRegionProps = {
    onSubmit: (formData: FormData, options: UploadOptions) => Promise<void> | void;
};

/** "IMG_8734.jpg" → "IMG 8734": boş alt metin bırakmaktansa dosya adı; sonra düzeltilir. */
function nameFromFile(fileName: string): string {
    return (
        fileName
            .replace(/\.[a-z0-9]+$/i, '')
            .replace(/[-_]+/g, ' ')
            .trim() || fileName
    );
}

function newIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

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
    const [limits, setLimits] = useState<UploadLimits | null>(null);
    const [progress, setProgress] = useState(0);
    /*
        Anahtar SEÇİMLE doğar, denemeyle değil: aynı dosyanın ikinci
        denemesi aynı anahtarı taşır. Yeni bir dosya seçilince yenilenir.
    */
    const [idempotencyKey, setIdempotencyKey] = useState(() => newIdempotencyKey());
    const [selected, setSelected] = useState<SelectedImage | null>(null);
    /*
        KIRPILMIŞ ÇIKTI (FF-129) — varsa yüklenen budur, seçilen dosya değil.

        Sunucudaki işleyici slotun oranına göre MERKEZDEN kırpıyor ve bunu
        kullanıcıya hiç sormuyordu; bir kapak görselinde tabak çoğu zaman
        merkezde durmaz ve sahibi yanlış çerçeveyi ancak yayımladıktan sonra
        görür. Artık çerçeveyi kullanıcı seçiyor.
    */
    const [cropped, setCropped] = useState<{ blob: Blob; width: number; height: number } | null>(
        null,
    );

    // Kimliği SABİT tutulur: `ImageCropField` bunu bir etkinin bağımlılığı
    // olarak kullanır ve her çizimde yeni bir işlev vermek, kırpmayı sonsuz
    // döngüye sokardı.
    const handleCropped = useCallback((blob: Blob, size: { width: number; height: number }) => {
        setCropped({ blob, width: size.width, height: size.height });
    }, []);
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
    /**
     * Çoklu yüklemenin kalan dosyaları (FF-76). Her birinin alt metni dosya
     * adından türer ("IMG_8734.jpg" → "IMG 8734") ve satırda düzeltilebilir;
     * sonradan da kütüphane çekmecesinden değişir. Slot hepsine ortaktır.
     */
    const [extra, setExtra] = useState<{ image: SelectedImage; altText: string }[]>([]);
    const [batchProgress, setBatchProgress] = useState<{ done: number; total: number } | null>(
        null,
    );
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

                const body = (await response.json()) as {
                    slots?: SlotPolicy[];
                    limits?: UploadLimits;
                };

                if (!cancelled) {
                    setPolicies(body.slots ?? []);
                    setLimits(body.limits ?? null);
                }
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
    /*
        Kontrol ORANDAN SONRA yapılır (FF-129).

        Öncesi yalnız kenarları en küçük ölçüyle karşılaştırıyordu ve sessiz
        bir delik bırakıyordu: 1250×1250 bir fotoğraf, 1200×500 isteyen 3:1
        bir slot için her iki kenarda da yeterli görünür — ama 3:1 çerçeve
        1250×417 olur ve yükseklik yetmez. Kullanıcı yüklerdi, sunucu
        kırpardı ve sonuç istenen ölçünün altında kalırdı.
    */
    const tooSmall =
        selected !== null &&
        activePolicy !== null &&
        selected.width > 0 &&
        !canCropInto(
            { width: selected.width, height: selected.height },
            { width: activePolicy.minWidth, height: activePolicy.minHeight },
            parseAspect(activePolicy.aspect),
        );

    /*
        SUNUCUNUN SINIRLARI YÜKLEMEDEN ÖNCE söylenir. 30 MB'lık bir dosyayı
        gönderip 422 almak, telefonda bir dakika ve bir kota harcamaktır;
        sınır zaten sunucudan (`limits`) geliyor, aynı sayı burada da geçerli.
        Sunucu yine son sözü söyler (`docs/47`: istemci yalnız hızlı yardım).
    */
    const tooLarge = selected !== null && limits !== null && selected.file.size > limits.maxBytes;
    const tooManyPixels =
        selected !== null &&
        limits !== null &&
        selected.width > 0 &&
        selected.width * selected.height > limits.maxMegapixels * 1_000_000;

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        // Öncesi burada sessizce `return` ediyordu: "Upload" düğmesine
        // basmak hiçbir şey yapmıyordu ve kullanıcı neyin eksik olduğunu
        // göremiyordu (`docs/47` Kural 5).
        const errors: Record<string, string> = {};

        if (!selected) {
            errors[fileId] = t('workspace.media.upload.error.file.required');
        } else if (tooLarge && limits) {
            errors[fileId] = t('workspace.media.upload.error.tooLarge', {
                size: String(Math.round(selected.file.size / 1_048_576)),
                max: String(Math.round(limits.maxBytes / 1_048_576)),
            });
        } else if (tooManyPixels && limits) {
            errors[fileId] = t('workspace.media.upload.error.tooManyPixels', {
                width: String(selected.width),
                height: String(selected.height),
                max: String(limits.maxMegapixels),
            });
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
        /*
            Kırpılmış çıktı VARSA yüklenen odur. Dosya adı korunur: sunucu
            uzantıdan biçim çıkarır ve kullanıcının kütüphanede tanıdığı ad
            değişmemeli.
        */
        formData.set(
            'file',
            cropped === null
                ? selected!.file
                : new File([cropped.blob], selected!.file.name, { type: cropped.blob.type }),
        );
        formData.set('altText', altText);
        formData.set('slot', slot);

        setStatus('pending');
        setProgress(0);
        setFailureMessage('');
        setFieldErrors({});

        try {
            await onSubmit(formData, { idempotencyKey, onProgress: setProgress });

            // Kalan dosyalar SIRAYLA: aynı slot, kendi alt metni, kendi anahtarı.
            // Biri düşerse kalanlar durur ve düşen dosya ilk sıraya alınır.
            if (extra.length > 0) {
                const total = extra.length + 1;
                for (let index = 0; index < extra.length; index++) {
                    setBatchProgress({ done: index + 1, total });
                    const row = extra[index];
                    const more = new FormData();
                    more.set('file', row.image.file);
                    more.set('altText', row.altText.trim() || nameFromFile(row.image.file.name));
                    more.set('slot', slot);
                    try {
                        await onSubmit(more, {
                            idempotencyKey: newIdempotencyKey(),
                            onProgress: setProgress,
                        });
                    } catch (error) {
                        setExtra(extra.slice(index));
                        setBatchProgress(null);
                        setFailureMessage(
                            error instanceof ServerRejectedError ? error.message : '',
                        );
                        setStatus('error');
                        return;
                    }
                }
                setExtra([]);
                setBatchProgress(null);
            }
            // Başarılı yükleme anahtarı TÜKETİR; bir sonraki dosya yenisini alır.
            setIdempotencyKey(newIdempotencyKey());
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
            <h3 className="text-body font-bold text-fg">{t('workspace.media.upload.heading')}</h3>

            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1">
                    <span className="text-body text-fg-secondary">
                        {t('workspace.media.upload.field.file')}
                    </span>
                    <MediaDropzone
                        selected={selected}
                        invalid={fieldErrors[fileId] !== undefined}
                        describedBy={fieldErrors[fileId] ? `${fileId}-error` : undefined}
                        onSelect={(image) => {
                            setSelected(image);
                            // Yeni dosya, yeni çerçeve: eski kırpma bir
                            // öncekinin karesini taşırdı.
                            setCropped(null);
                            // Yeni dosya, yeni anahtar; yeniden deneme eskisini korur.
                            setIdempotencyKey(newIdempotencyKey());
                        }}
                        onSelectMore={(images) =>
                            setExtra(
                                images.map((image) => ({
                                    image,
                                    altText: nameFromFile(image.file.name),
                                })),
                            )
                        }
                    />

                    {/*
                        KIRPMA, slot SEÇİLDİKTEN sonra görünür: hangi oranın
                        isteneceği slota bağlıdır ve slotsuz bir çerçeve
                        keyfî olurdu. `tooSmall` iken de çizilmez — kırpma
                        piksel eklemez, o durumda hata metni doğru olanı
                        zaten söylüyor.
                    */}
                    {selected !== null && activePolicy !== null && !tooSmall ? (
                        <ImageCropField
                            objectUrl={selected.previewUrl}
                            source={{ width: selected.width, height: selected.height }}
                            aspect={activePolicy.aspect}
                            minimum={{
                                width: activePolicy.minWidth,
                                height: activePolicy.minHeight,
                            }}
                            mimeType={selected.file.type}
                            onCropped={handleCropped}
                        />
                    ) : null}

                    {extra.length > 0 ? (
                        <ul
                            aria-label={t('workspace.media.upload.more.label')}
                            className="flex flex-col gap-2 rounded-lg border border-border p-2"
                        >
                            <li className="text-meta text-fg-muted">
                                {t('workspace.media.upload.more.lead', {
                                    count: String(extra.length),
                                })}
                            </li>
                            {extra.map((row, index) => (
                                <li
                                    key={`${row.image.file.name}-${index}`}
                                    className="flex flex-col gap-1"
                                >
                                    <label className="flex flex-col gap-1 text-meta text-fg-secondary">
                                        {row.image.file.name}
                                        <TextInput
                                            type="text"
                                            value={row.altText}
                                            aria-label={t('workspace.media.upload.more.altFor', {
                                                name: row.image.file.name,
                                            })}
                                            onChange={(event) =>
                                                setExtra((current) =>
                                                    current.map((item, i) =>
                                                        i === index
                                                            ? {
                                                                  ...item,
                                                                  altText: event.target.value,
                                                              }
                                                            : item,
                                                    ),
                                                )
                                            }
                                        />
                                    </label>
                                </li>
                            ))}
                        </ul>
                    ) : null}
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
                <div className="flex flex-col gap-1">
                    <p role="status" className="text-meta text-fg-muted">
                        {batchProgress
                            ? t('workspace.media.upload.more.progress', {
                                  done: String(batchProgress.done + 1),
                                  total: String(batchProgress.total),
                              })
                            : progress > 0 && progress < 1
                              ? t('workspace.media.upload.uploading.progress', {
                                    percent: String(Math.round(progress * 100)),
                                })
                              : t('workspace.media.upload.uploading')}
                    </p>
                    <progress
                        aria-label={t('workspace.media.upload.uploading')}
                        className="h-2 w-full"
                        max={100}
                        value={Math.round(progress * 100)}
                    />
                </div>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body text-fg-danger">
                        {failureMessage || t('workspace.media.upload.failed')}
                    </p>
                    {/* Seçim korunur; tekrar denemek AYNI anahtarla gider. */}
                    {selected ? (
                        <Button color="light" type="submit" className="self-start">
                            {t('workspace.media.upload.retry')}
                        </Button>
                    ) : null}
                </div>
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
