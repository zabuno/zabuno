import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Select } from '../../../catalog/forms/micro/Select';
import { Button } from '../../../catalog/forms/micro/Button';
import { useCallback, useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { canCropInto, parseAspect } from './cropGeometry';
import { DEFAULT_MAX_EDGE } from './clientDownscale';
import { downscaleImageFile, type DownscaleOutcome } from './downscaleImageFile';
import { ImageCropField } from './ImageCropField';
import { MediaDropzone, type SelectedImage } from './MediaDropzone';
import { MediaOptimizeStep } from './MediaOptimizeStep';
import { MediaUploadSteps, type UploadStep, type UploadStepKey } from './MediaUploadSteps';
import { SupportedTypesTable } from './SupportedTypesTable';
import { FieldError } from '../../../catalog/menu/micro/FieldError';
import { focusFirstInvalidField, ServerRejectedError } from '../../../../lib/validationErrors';
import { t } from '../../../../i18n/workspace';
import { wizardText } from './uploadWizardCopy';

type UploadStatus = 'idle' | 'pending' | 'success' | 'error';

/**
 * Sihirbazın adım sırası — kanonik kaynağın "Yükle" ekranıyla aynı.
 *
 * Sıra keyfî değil, her adım bir öncekinin çıktısını kullanır: küçültme
 * seçilmiş bir dosyaya, çerçeve küçültülmüş bir kareye, gönderme ise ikisine
 * birden bağlıdır.
 */
const STEP_ORDER: readonly UploadStepKey[] = ['pick', 'optimize', 'frame', 'send'];

/** Yükleyicinin kabul ettiği türler — desteklenen türler tablosunu da bu besler. */
const ACCEPT = 'image/*';

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

/**
 * Yüklemenin ARDINDAN dosyanın gerçekte nerede olduğu (FF-150).
 *
 * Sunucu 201 ile "aldım" der, ama "aldım" ile "kullanabilirsin" aynı şey
 * değildir: dosya güvenlik kapısını geçemediyse karantinada bekler. Bu
 * ekran o farkı BİLMEDEN "tamamlandı" yazıyordu ve sahip fotoğrafının
 * menüde çıkmamasını kendi hatası sanıyordu.
 *
 * Alanlar isteğe bağlıdır: `onSubmit` bugün bazı çağıranlarda (ve
 * testlerde) hiçbir şey döndürmüyor ve döndürmemesi bir hata değil —
 * o durumda ekran eskisi gibi davranır.
 */
export type UploadOutcome = {
    /** `MediaAssetStatus` — `ready` dışındaki her değer "henüz kullanılamaz" demektir. */
    status?: string;
    /** Sunucunun KAYDETTİĞİ sebep; sorunsuz dosyada boştur. */
    statusReason?: string | null;
};

type MediaUploadRegionProps = {
    onSubmit: (
        formData: FormData,
        options: UploadOptions,
    ) => Promise<UploadOutcome | void> | UploadOutcome | void;
};

/**
 * Dosya, İŞLENEMEDİĞİ için değil, TARANAMADIĞI için mi bekliyor?
 *
 * Ayrım önemli: tarama kapısında bekleyen bir dosyada kusur ORTAMDADIR
 * (sunucuda tarayıcı yok) ve sahibin yapabileceği bir şey yoktur. İşleme
 * aşamasında takılan bir dosyada ise sebep dosyanın kendisi olabilir —
 * oraya "yanlış bir şey yapmadınız" yazmak, yanlış bir teselli olurdu.
 */
function heldAtScanGate(status: string | undefined): boolean {
    return status === 'scanning' || status === 'quarantined';
}

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
     * Yükleme BAŞARILI oldu ama dosya kullanılamıyor (FF-150).
     *
     * Hata değildir — `failureMessage` ile aynı kutuya konamaz: sunucu
     * dosyayı reddetmedi, aldı ve tuttu. Kırmızı bir "başarısız" uyarısı
     * göstermek sahibi dosyayı yeniden yüklemeye iterdi ve ikinci deneme de
     * aynı yerde beklerdi.
     */
    const [held, setHeld] = useState<{ reason: string; atScanGate: boolean } | null>(null);
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

    /*
        SİHİRBAZ DURUMU.

        Öncesinde bu ekran TEK bir uzun formdu: bırakma alanı, kırpma aracı,
        alt metin, yer seçimi ve yükle düğmesi alt alta. Telefonda bu, sahibin
        kaydırarak aradığı beş ayrı karar demekti ve hangi sırayla yapılacağı
        hiçbir yerde yazmıyordu. Kaynak aynı işi dört adıma bölüyor ve her
        adımda TEK bir soru soruyor.
    */
    const [step, setStep] = useState<UploadStepKey>('pick');
    /** Kullanıcının seçtiği uzun kenar hedefi (2. adım). */
    const [maxEdge, setMaxEdge] = useState(DEFAULT_MAX_EDGE);
    /** İstemcide küçültülmüş çıktı; `null` ise küçültülecek bir şey yoktu. */
    const [optimized, setOptimized] = useState<DownscaleOutcome | null>(null);
    const [optimizing, setOptimizing] = useState(false);
    /**
     * Odak isteği — hatalı alan BAŞKA bir adımda olabilir.
     *
     * Sihirbazın en sinsi arızası budur: "Upload" son adımda, eksik alan
     * başka adımda. Odak, alan ekrana geldikten SONRA taşınmalı; bu yüzden
     * istek bir sonraki çizime bırakılıyor.
     */
    const [focusToken, setFocusToken] = useState(0);
    const focusFieldsRef = useRef<Record<string, string>>({});

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

    /*
        Slot kısıtları PRİMİTİF olarak okunur: etki bağımlılıklarında nesne
        kimliği kullanılsaydı her çizimde yeni bir nesne doğar ve küçültme
        sonsuz döngüye girerdi.
    */
    const minWidth = activePolicy?.minWidth ?? 0;
    const minHeight = activePolicy?.minHeight ?? 0;
    const policyAspect = activePolicy?.aspect ?? null;

    /*
        İSTEMCİDE KÜÇÜLTME (kanonik kaynak, "Yükle" 2. adımı).

        Telefonla çekilen 8 MB'lık fotoğraf bugün olduğu gibi ağa gidiyor;
        sunucu onu zaten küçülteceği hâlde. Burada dosya kullanıcının KENDİ
        makinesinde küçülür — bu aynı zamanda bir güvenlik kararıdır
        (`docs/108` §4): taranmamış dosya sunucuya gidip oradan geri servis
        edilmez.

        Taban KIRPILMIŞ karedir (varsa): oran zaten uygulanmıştır, ikinci kez
        uygulamak aynı kısıtı iki kere saymak olurdu. Kırpma yoksa taban
        seçilen dosyanın kendisidir.
    */
    useEffect(() => {
        /*
            Küçültme bir DIŞ SİSTEM işidir (tuval, çözücü) ve etkinin gövdesi
            durum yazmaz: her `setState` bir geri çağrının içinde durur. Etki
            gövdesinde eşzamanlı durum yazmak zincirleme çizim üretir ve CI
            kapısı (`react-hooks/set-state-in-effect`) bunu hata sayar.
        */
        const base =
            selected !== null && selected.width > 0
                ? cropped
                    ? {
                          blob: cropped.blob,
                          name: selected.file.name,
                          width: cropped.width,
                          height: cropped.height,
                      }
                    : {
                          blob: selected.file,
                          name: selected.file.name,
                          width: selected.width,
                          height: selected.height,
                      }
                : null;

        let cancelled = false;

        void (async () => {
            if (base === null) {
                if (!cancelled) setOptimized(null);

                return;
            }

            if (!cancelled) setOptimizing(true);

            const outcome = await downscaleImageFile(base, {
                minimum: { width: minWidth, height: minHeight },
                aspect: cropped ? null : policyAspect,
                maxEdge,
            });

            if (cancelled) return;

            setOptimized(outcome);
            setOptimizing(false);
        })();

        return () => {
            cancelled = true;
        };
    }, [selected, cropped, minWidth, minHeight, policyAspect, maxEdge]);

    /*
        Odak, hatalı alanın ADIMI çizildikten sonra taşınır.

        İstek bir sayaçla taşınıyor, durumla değil: etkinin içinde durum
        sıfırlamak zincirleme çizim üretirdi. Sayaç her gönderimde artar, yani
        aynı hata iki kez alındığında odak yine taşınır — durum kullanılsaydı
        ikinci gönderim hiçbir şey yapmazdı.
    */
    useEffect(() => {
        if (focusToken === 0) return;

        focusFirstInvalidField(focusFieldsRef.current, [fileId, slotId, altId]);
    }, [focusToken, fileId, slotId, altId]);

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

    /** Hangi alan hangi adımda yaşıyor — ekran sırasıyla. */
    const FIELD_STEPS: readonly (readonly [string, UploadStepKey])[] = [
        [fileId, 'pick'],
        [slotId, 'frame'],
        [altId, 'send'],
    ];

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
            /*
                Hata BAŞKA bir adımda olabilir. Sihirbaz oraya döner; yoksa
                kullanıcı düğmeye basar, hiçbir şey olmaz ve neyin eksik
                olduğunu göremez — çünkü eksik alan ekranda DEĞİLDİR.

                Sıra EKRAN sırasıdır: dosya 1., yer 3., alt metin 4. adımda.
            */
            const firstStep = FIELD_STEPS.map(([id, key]) => (errors[id] ? key : null)).find(
                (key): key is UploadStepKey => key !== null,
            );

            if (firstStep) {
                setStep(firstStep);
            }

            focusFieldsRef.current = errors;
            setFocusToken((token) => token + 1);

            return;
        }

        const formData = new FormData();
        /*
            YÜKLENEN DOSYA — üç aday, tek sıra.

            1. İstemcide küçültülmüş çıktı (varsa). Kırpılmış kareden
               üretildiyse hem çerçeveyi hem küçültmeyi birlikte taşır.
            2. Kırpılmış kare (küçültme yapılamadıysa).
            3. Seçilen dosyanın kendisi.

            Dosya adı korunur: sunucu uzantıdan biçim çıkarır ve kullanıcının
            kütüphanede tanıdığı ad değişmemeli. Tek istisna, HEIC'in JPEG'e
            çevrildiği durumdur — orada uzantı DEĞİŞMEK ZORUNDADIR, yoksa
            sunucu içerikle uzantının çeliştiğini görür.
        */
        formData.set(
            'file',
            optimized !== null
                ? optimized.file
                : cropped === null
                  ? selected!.file
                  : new File([cropped.blob], selected!.file.name, { type: cropped.blob.type }),
        );
        formData.set('altText', altText);
        formData.set('slot', slot);

        setStatus('pending');
        setProgress(0);
        setFailureMessage('');
        setHeld(null);
        setFieldErrors({});

        /*
            BEKLEYENLERİN SONUNCUSU gösterilir, ilki değil.

            Toplu yüklemede dosyaların hepsi aynı kapıdan geçer; biri
            beklemede kaldıysa büyük olasılıkla hepsi kaldı. Sebebi her
            dosya için ayrı ayrı listelemek aynı cümleyi kırk kez yazmak
            olurdu — ve sahip kırk satır okumaz.
        */
        let lastHeld: { reason: string; atScanGate: boolean } | null = null;

        function noteOutcome(outcome: UploadOutcome | void): void {
            const reason = outcome?.statusReason;

            if (typeof reason === 'string' && reason.trim() !== '') {
                lastHeld = { reason, atScanGate: heldAtScanGate(outcome?.status) };
            }
        }

        try {
            noteOutcome(await onSubmit(formData, { idempotencyKey, onProgress: setProgress }));

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
                        noteOutcome(
                            await onSubmit(more, {
                                idempotencyKey: newIdempotencyKey(),
                                onProgress: setProgress,
                            }),
                        );
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
            setCropped(null);
            setOptimized(null);
            setAltText('');
            setSlot('');
            setHeld(lastHeld);
            setStatus('success');
            // Sihirbaz başa döner: sonraki fotoğraf yine "hangi dosya?" ile
            // başlar. Son adımda bırakmak, kullanıcıya bitmemiş bir iş
            // gösterirdi.
            setStep('pick');
        } catch (error) {
            // YALNIZ sunucunun reddi ekrana çıkar. Ağ kopmasında `error`
            // ham bir JavaScript hatasıdır ("Network failure") ve onu
            // göstermek kullanıcıya iç detay sızdırmaktır.
            setFailureMessage(error instanceof ServerRejectedError ? error.message : '');
            setStatus('error');
        }
    }

    const stepIndex = STEP_ORDER.indexOf(step);
    /*
        Dosya seçilmeden sonraki adımlar ULAŞILAMAZ. Kısıt görünür tutuluyor:
        devre dışı bir adım sırayı öğretir, gizlenen bir adım ise "kaç adım
        kaldı" sorusunu geri getirir.
    */
    const steps: readonly UploadStep[] = STEP_ORDER.map((key) => ({
        key,
        label: wizardText(`workspace.media.upload.step.${key}`),
        reachable: key === 'pick' || selected !== null,
    }));

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

            {/*
                ADIM GÖSTERGESİ — bir süs değil.

                Kullanıcı nerede olduğunu ve kaç adım kaldığını görür.
                Ulaşılamayan adım gizlenmez, devre dışı gösterilir: gizlemek
                "kaç adım kaldı" sorusunu geri getirirdi.
            */}
            <MediaUploadSteps steps={steps} activeKey={step} onGo={setStep} />

            {step === 'pick' ? (
                <div
                    role="group"
                    aria-label={wizardText('workspace.media.upload.step.pick')}
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-col gap-1">
                        <span className="text-body text-fg-secondary">
                            {t('workspace.media.upload.field.file')}
                        </span>
                        <p className="text-body text-fg-muted">
                            {wizardText('workspace.media.upload.pick.lead')}
                        </p>
                        <MediaDropzone
                            selected={selected}
                            invalid={fieldErrors[fileId] !== undefined}
                            describedBy={fieldErrors[fileId] ? `${fileId}-error` : undefined}
                            onSelect={(image) => {
                                /*
                                    YENİ dosya mı? `MediaDropzone` aynı dosya
                                    için iki kez haber verir (önce dosya, sonra
                                    ölçüler). İkisini ayırmadan sihirbaz,
                                    kullanıcı 1. adıma geri döndüğünde ölçü
                                    geldiği anda tekrar ileri sıçrardı.
                                */
                                const isNew =
                                    image !== null &&
                                    (selected === null || selected.file !== image.file);

                                setSelected(image);
                                // Yeni dosya, yeni çerçeve: eski kırpma bir
                                // öncekinin karesini taşırdı.
                                setCropped(null);
                                // Yeni dosya, yeni anahtar; yeniden deneme eskisini korur.
                                setIdempotencyKey(newIdempotencyKey());

                                if (isNew) {
                                    setStep('optimize');
                                }
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
                        {fieldErrors[fileId] ? (
                            <span id={`${fileId}-error`}>
                                <FieldError message={fieldErrors[fileId]} />
                            </span>
                        ) : null}
                    </div>

                    {/*
                        Desteklenen türler tablosu 1. adımdadır çünkü kararı
                        DEĞİŞTİREN bilgi burada işe yarar: sahibi dosyayı
                        seçmeden önce hangisinin kabul edileceğini bilmeli.
                        Yükleme reddedildikten sonra okunan bir tablo,
                        okunmamış bir tablodur.
                    */}
                    <SupportedTypesTable accept={ACCEPT} maxBytes={limits?.maxBytes ?? null} />
                </div>
            ) : null}

            {step === 'optimize' && selected !== null ? (
                <div role="group" aria-label={wizardText('workspace.media.upload.step.optimize')}>
                    <MediaOptimizeStep
                        source={{ width: selected.width, height: selected.height }}
                        minimum={{ width: minWidth, height: minHeight }}
                        aspect={policyAspect}
                        maxEdge={maxEdge}
                        onMaxEdge={setMaxEdge}
                        beforeBytes={selected.file.size}
                        afterBytes={optimized?.file.size ?? null}
                        busy={optimizing}
                    />
                </div>
            ) : null}

            {step === 'frame' ? (
                <div
                    role="group"
                    aria-label={wizardText('workspace.media.upload.step.frame')}
                    className="flex flex-col gap-3"
                >
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

                            Öncesinde kullanıcı 17 opak ad arasından seçim
                            yapıyor ve hangi ölçüde görsel yükleyeceğini
                            hiçbir yerden öğrenemiyordu; bulanık görseli
                            ancak yayınladıktan sonra fark ediyordu.
                        */}
                        {activePolicy ? (
                            /*
                                Gereksinimler bir DİPNOT değil, kullanıcının
                                kararını değiştiren cümlelerdir: "yeter mi,
                                yetmez mi?" Rakamları slot değiştikçe değişir,
                                bu yüzden `tabular-nums`.
                            */
                            <ul className="flex flex-col gap-0.5 text-body text-fg-muted tabular-nums">
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
                </div>
            ) : null}

            {step === 'send' ? (
                <div
                    role="group"
                    aria-label={wizardText('workspace.media.upload.step.send')}
                    className="flex flex-col gap-3"
                >
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
                        <p id={`${altId}-hint`} className="text-body text-fg-muted">
                            {t('workspace.media.upload.field.altText.hint')}
                        </p>
                        {fieldErrors[altId] ? <FieldError message={fieldErrors[altId]} /> : null}
                    </div>

                    {extra.length > 0 ? (
                        <ul
                            aria-label={t('workspace.media.upload.more.label')}
                            className="flex flex-col gap-2 rounded-[var(--radius-lg)] border border-border p-[var(--space-2)]"
                        >
                            <li className="text-body text-fg-muted">
                                {t('workspace.media.upload.more.lead', {
                                    count: String(extra.length),
                                })}
                            </li>
                            {extra.map((row, index) => (
                                <li
                                    key={`${row.image.file.name}-${index}`}
                                    className="flex flex-col gap-1"
                                >
                                    {/* Dosya adı bir ETİKETTİR, sayaç değil. */}
                                    <label className="flex flex-col gap-1 text-body text-fg-secondary">
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

                    <Button
                        color="light"
                        type="submit"
                        disabled={status === 'pending'}
                        className="self-start"
                    >
                        {t('workspace.media.upload.button')}
                    </Button>
                </div>
            ) : null}

            {/*
                GEZİNTİ. "Devam" dosya seçilmeden çalışmaz: sonraki üç adımın
                hepsi seçilmiş bir dosyaya bağlıdır ve boş bir adım kullanıcıya
                "bir şeyi kaçırdım" dedirtir.
            */}
            <div className="flex flex-wrap gap-[var(--space-2)]">
                {step !== 'pick' ? (
                    <button
                        type="button"
                        onClick={() => setStep(STEP_ORDER[Math.max(0, stepIndex - 1)])}
                        className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-2)] text-body font-medium text-fg-secondary hover:bg-surface-hover"
                    >
                        {wizardText('workspace.media.upload.back')}
                    </button>
                ) : null}
                {step !== 'send' ? (
                    <button
                        type="button"
                        disabled={selected === null}
                        onClick={() =>
                            setStep(STEP_ORDER[Math.min(STEP_ORDER.length - 1, stepIndex + 1)])
                        }
                        className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border-strong bg-surface-active px-[var(--space-3)] py-[var(--space-2)] text-body font-bold text-fg disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {wizardText('workspace.media.upload.next')}
                    </button>
                ) : null}
            </div>

            {status === 'pending' && (
                <div className="flex flex-col gap-1">
                    {/* Yüzde her karede değişir: sabit genişlikli rakam. */}
                    <p role="status" className="text-meta text-fg-muted tabular-nums">
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

            {/*
                BAŞARILI GÖNDERİM, İKİ AYRI GERÇEK (FF-150).

                Dosya ilerlediyse tek cümle yeter. Kapıda kaldıysa sahibin
                üç şeyi bilmesi gerekir: dosya ULAŞTI, henüz KULLANILAMIYOR
                ve SEBEBİ şu. Üçü tek bir canlı bölgede durur; ekran
                okuyucuda üç ayrı duyuru, aynı olayı üç kez anlatırdı.

                Sebep sunucunun KAYDETTİĞİ cümledir, burada üretilmez —
                "birazdan biter" ya da "%80" gibi bir şey yazmak, bilmediğimiz
                bir şeyi bilir gibi davranmak olurdu.
            */}
            {status === 'success' &&
                (held ? (
                    <div role="status" className="flex flex-col gap-1">
                        <p className="text-body text-fg-muted">
                            {t('workspace.media.upload.held')}
                        </p>
                        <p className="text-body text-fg-warning">{held.reason}</p>
                        {held.atScanGate ? (
                            <p className="text-body text-fg-muted">
                                {t('workspace.media.upload.held.notYours')}
                            </p>
                        ) : null}
                    </div>
                ) : (
                    /*
                        İlerleyen dosyanın cevabı TEK cümledir ve tek bir
                        paragraf olarak kalır: söylenecek başka bir şey yokken
                        etrafına kutu çizmek, ekran okuyucuya da fazladan bir
                        katman okutur.
                    */
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.media.upload.complete')}
                    </p>
                ))}

            {/*
                Güvenlik açıklaması dipnot DEĞİLDİR: yüklediği fotoğrafın
                neden hemen menüde görünmediğini soran kullanıcının cevabı
                budur.

                Ama BEKLEYEN bir dosyanın yanında çizilmez: "Her görsel
                taranır" cümlesi ile "tarama bu ortamda çalışmıyor" cümlesi
                aynı ekranda durursa biri mutlaka yalandır ve sahip hangisine
                inanacağını bilemez. Vaat, çürütüldüğü anda susar.
            */}
            {held ? null : (
                <p className="text-body text-fg-muted">
                    {t('workspace.media.security.explanation')}
                </p>
            )}
        </form>
    );
}

export default MediaUploadRegion;
