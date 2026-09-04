import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import {
    canCropInto,
    cropRectFor,
    largestRectFor,
    maxZoomFor,
    parseAspect,
    type CropRect,
    type Size,
} from './cropGeometry';

/**
 * Yükleme ÖNCESİ kırpma (FF-129).
 *
 * Sunucudaki işleyici slotun oranına göre merkezden kırpıyor ve bunu
 * kullanıcıya hiç sormuyordu. Bir kapak görselinde tabak çoğu zaman merkezde
 * durmaz; restoran sahibi yanlış çerçeveyi ancak yayımladıktan sonra görür.
 *
 * Kırpma İSTEMCİDE yapılır ve bu bir güvenlik kararıdır: dosya kullanıcının
 * kendi makinesindedir, sunucudan servis edilmez. Taranmamış bir dosyayı
 * önizleme diye sunucudan geri vermek, tam olarak virüs taramasının
 * engellemeye çalıştığı şeydir.
 *
 * Araç, kırpmanın İMKÂNSIZ olduğu durumda hiç açılmaz: kırpma piksel eklemez,
 * bu yüzden küçük bir kaynağa çerçeve seçtirip sonunda "olmadı" demek emeği
 * boşa harcatmak olurdu.
 */
export type ImageCropFieldProps = {
    /** Kullanıcının seçtiği dosyanın nesne adresi. */
    objectUrl: string;
    source: Size;
    /** Slotun oranı (`'3:1'`); `null` ise kırpma gerekmez. */
    aspect: string | null;
    minimum: Size;
    /** Kırpılmış görüntü hazır olduğunda — yüklenecek dosya budur. */
    onCropped: (blob: Blob, size: Size) => void;
    /** Kaynak dosyanın MIME türü; kırpılmış çıktı aynı türde kalır. */
    mimeType: string;
};

export function ImageCropField({
    objectUrl,
    source,
    aspect,
    minimum,
    onCropped,
    mimeType,
}: ImageCropFieldProps) {
    const zoomId = useId();
    const ratio = parseAspect(aspect);

    const [zoom, setZoom] = useState(1);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const frameRef = useRef<HTMLDivElement | null>(null);
    const dragRef = useRef<{ pointerX: number; pointerY: number } | null>(null);

    const possible = canCropInto(source, minimum, ratio);
    const maxZoom = useMemo(() => maxZoomFor(source, minimum, ratio), [source, minimum, ratio]);
    const rect: CropRect = useMemo(
        () => cropRectFor(source, ratio, zoom, offset),
        [source, ratio, zoom, offset],
    );

    /*
        Kırpılmış görüntü, kullanıcı çerçeveyi bıraktığında değil HER
        değişiklikte üretilir. Sebebi: "Yükle" düğmesine basıldığı anda
        gönderilecek şeyin hazır olması gerekir; üretimi o ana bırakmak,
        düğmeye basınca hiçbir şey olmayan bir bekleme doğururdu.
    */
    useEffect(() => {
        if (!possible) return;

        let cancelled = false;
        const image = new Image();

        image.onload = () => {
            if (cancelled) return;

            const canvas = document.createElement('canvas');
            canvas.width = rect.width;
            canvas.height = rect.height;

            const context = canvas.getContext('2d');

            if (context === null) return;

            context.drawImage(
                image,
                rect.x,
                rect.y,
                rect.width,
                rect.height,
                0,
                0,
                rect.width,
                rect.height,
            );

            canvas.toBlob(
                (blob) => {
                    if (!cancelled && blob !== null) {
                        onCropped(blob, { width: rect.width, height: rect.height });
                    }
                },
                // Çıktı türü kaynağınkiyle AYNI kalır: PNG bir logoyu JPEG'e
                // çevirmek saydamlığı düz beyaza gömerdi (INV-07).
                mimeType === 'image/png' ? 'image/png' : 'image/jpeg',
                0.92,
            );
        };

        image.src = objectUrl;

        return () => {
            cancelled = true;
        };
    }, [objectUrl, rect, possible, onCropped, mimeType]);

    const onPointerDown = useCallback((event: React.PointerEvent<HTMLDivElement>) => {
        dragRef.current = { pointerX: event.clientX, pointerY: event.clientY };
        event.currentTarget.setPointerCapture(event.pointerId);
    }, []);

    const onPointerMove = useCallback((event: React.PointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        const frame = frameRef.current;

        if (drag === null || frame === null) return;

        const box = frame.getBoundingClientRect();

        // Kayma NORMALİZE edilir (-1…1): yakınlaştırma değişince
        // kullanıcının seçtiği yer orantılı kalır. Piksel tutulsaydı
        // her yakınlaştırmada çerçeve sıçrardı.
        setOffset((previous) => ({
            x: clamp(previous.x - ((event.clientX - drag.pointerX) * 2) / Math.max(1, box.width)),
            y: clamp(previous.y - ((event.clientY - drag.pointerY) * 2) / Math.max(1, box.height)),
        }));

        dragRef.current = { pointerX: event.clientX, pointerY: event.clientY };
    }, []);

    const onPointerUp = useCallback((event: React.PointerEvent<HTMLDivElement>) => {
        dragRef.current = null;
        event.currentTarget.releasePointerCapture(event.pointerId);
    }, []);

    if (!possible || ratio === null) {
        /*
            Oransız slotta kırpma yoktur ve imkânsız durumda araç hiç
            açılmaz — ikisinde de yükleme formunun kendi hata metni doğru
            olanı zaten söylüyor (kaç piksel var, kaç gerekiyor, neden
            büyütülmüyor).
        */
        return null;
    }

    const largest = largestRectFor(source, ratio);
    const scale = rect.width / largest.width;

    return (
        <section
            aria-labelledby="media-crop-heading"
            className="flex flex-col gap-[var(--space-2)]"
        >
            <h4 id="media-crop-heading" className="text-body font-semibold text-fg">
                {t('workspace.media.crop.heading')}
            </h4>
            <p className="text-body text-fg-secondary">{t('workspace.media.crop.help')}</p>

            <div
                ref={frameRef}
                role="group"
                aria-label={t('workspace.media.crop.frame')}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={onPointerUp}
                className="relative w-full cursor-grab touch-none overflow-hidden rounded-[var(--radius-lg)] border border-border bg-surface-subtle"
                style={{ aspectRatio: String(ratio) }}
            >
                {/*
                    Görüntü çerçeveyi DOLDURUR ve içinde kayar. `object-fit`
                    ile yapılamazdı: tarayıcının kendi kırpması kullanıcının
                    seçtiği kareyi bilmez ve önizleme, yüklenecek şeyden
                    farklı olurdu — yani önizleme yalan söylerdi.
                */}
                <img
                    src={objectUrl}
                    alt=""
                    draggable={false}
                    /*
                        `max-w-none` ŞART: taban stil sayfası her görüntüye
                        `max-width: 100%` koyuyor ve yakınlaştırılmış çerçeve
                        sessizce eziliyordu — kutu doğru, sayı doğru,
                        gösterilen kare YANLIŞTI. Ekrana bakmadan
                        görülemezdi, çünkü hiçbir test bunu ölçmüyordu.
                    */
                    className="pointer-events-none absolute max-w-none origin-top-left select-none"
                    style={{
                        width: `${String(100 / scale)}%`,
                        insetInlineStart: `${String((-rect.x / rect.width) * 100)}%`,
                        top: `${String((-rect.y / rect.height) * 100)}%`,
                    }}
                />
            </div>

            <label htmlFor={zoomId} className="text-body text-fg-secondary">
                {t('workspace.media.crop.zoom')}
            </label>
            <input
                id={zoomId}
                type="range"
                min={1}
                max={maxZoom}
                step={0.01}
                value={zoom}
                onChange={(event) => setZoom(Number(event.target.value))}
                className="min-h-[var(--control-height)] w-full"
            />

            <div className="flex flex-wrap items-center gap-[var(--space-3)]">
                <button
                    type="button"
                    onClick={() => {
                        setZoom(1);
                        setOffset({ x: 0, y: 0 });
                    }}
                    className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-2)] text-body font-medium whitespace-nowrap text-fg-secondary hover:bg-surface-hover"
                >
                    {t('workspace.media.crop.reset')}
                </button>

                {/*
                    Sonuç ölçüsü SÜREKLİ görünür: kullanıcı yakınlaştırdıkça
                    piksel kaybettiğini bilmeli, çünkü slotun en küçük
                    ölçüsüne yaklaşan bir çerçeve yayında bulanık görünür.
                */}
                <p aria-live="polite" className="text-meta text-fg-muted">
                    {t('workspace.media.crop.result', {
                        width: String(rect.width),
                        height: String(rect.height),
                    })}
                </p>
            </div>
        </section>
    );
}

function clamp(value: number): number {
    return Math.min(1, Math.max(-1, value));
}

export default ImageCropField;
