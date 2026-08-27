import { useEffect, useId, useRef, useState, type DragEvent } from 'react';

import { t } from '../../../../i18n/workspace';

export type SelectedImage = {
    file: File;
    previewUrl: string;
    width: number;
    height: number;
};

type MediaDropzoneProps = {
    selected: SelectedImage | null;
    invalid: boolean;
    describedBy?: string;
    onSelect: (image: SelectedImage | null) => void;
};

/**
 * Sürükle-bırak, tıkla-seç ve önizleme.
 *
 * Öncesinde burada ham bir `<input type="file">` vardı. İki somut sonucu:
 *
 *   1. Tarayıcı onu İŞLETİM SİSTEMİNİN dilinde çiziyordu. Uygulamanın geri
 *      kalanı İngilizceyken düğmede "Dosya Seç · Dosya seçilmedi" yazıyordu.
 *      Kendi alanımızı çizince metin de katalogdan gelir.
 *   2. Seçilen dosyanın ÖNİZLEMESİ yoktu. Kullanıcı yanlış dosyayı seçtiğini
 *      ancak yükledikten sonra anlıyordu.
 *
 * Kütüphane KULLANILMADI. `react-dropzone` yalnız davranış verir ve iyi bir
 * adaydır (`docs/49` §7); ama gereken davranış üç olaydan ibaret ve külliyat
 * görünümün bizde kalmasını şart koşuyor. Kesintili yükleme gerçekten
 * gerektiğinde (video, Faz 2) karar veriyle yeniden verilir.
 */
export function MediaDropzone({ selected, invalid, describedBy, onSelect }: MediaDropzoneProps) {
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [dragging, setDragging] = useState(false);

    /*
        Seçim dışarıdan temizlendiğinde (başarılı yükleme sonrası) GERÇEK
        girdi de temizlenmeli. Aksi hâlde dosya seçici hâlâ eski dosyayı
        tutar: kullanıcı aynı dosyayı yeniden seçemez, çünkü `change`
        tetiklenmez — ve form boş görünürken aslında dolu olur.
    */
    useEffect(() => {
        if (selected === null && inputRef.current) {
            inputRef.current.value = '';
        }
    }, [selected]);

    /*
        Önizleme URL'i belgeye bağlıdır ve kendiliğinden serbest kalmaz.
        Menü doldururken arka arkaya yirmi görsel seçen bir kullanıcıda
        yirmi blob bellekte kalırdı.
    */
    useEffect(() => {
        const url = selected?.previewUrl;

        return () => {
            if (url && typeof URL.revokeObjectURL === 'function') {
                URL.revokeObjectURL(url);
            }
        };
    }, [selected?.previewUrl]);

    function read(file: File | undefined) {
        if (!file) {
            onSelect(null);

            return;
        }

        /*
            Seçim, görselin ÇÖZÜLMESİNİ BEKLEMEZ.

            İlk hâli önce önizlemeyi yükleyip sonra `onSelect` çağırıyordu.
            İki sorunu vardı: büyük bir dosyada kullanıcı saniyelerce hiçbir
            şey görmüyordu, ve önizleme üretilemeyen her ortamda seçim hiç
            olmuyordu — testlerde tam olarak bu oldu, çünkü jsdom
            `URL.createObjectURL` sağlamıyor.

            Doğrusu: dosya hemen seçilir, ölçüler geldiğinde eklenir.
        */
        const previewUrl =
            typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : '';

        onSelect({ file, previewUrl, width: 0, height: 0 });

        if (previewUrl === '') {
            return;
        }

        const probe = new Image();

        // Ölçüler slot gereksinimiyle YÜKLEMEDEN ÖNCE karşılaştırılabilsin
        // diye okunur.
        probe.onload = () => {
            onSelect({ file, previewUrl, width: probe.naturalWidth, height: probe.naturalHeight });
        };
        probe.src = previewUrl;
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setDragging(false);
        read(event.dataTransfer.files[0]);
    }

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            {/*
                `<div>` üzerinde sürükleme olayları, içinde GERÇEK bir dosya
                girdisi. Girdi gizlenir ama kaldırılmaz: klavye ve ekran
                okuyucu onun üzerinden çalışır ve `<label>` ona bağlıdır.
            */}
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={handleDrop}
                className={[
                    'flex flex-col items-center gap-[var(--space-2)] rounded-lg border border-dashed',
                    'p-[var(--space-4)] text-center',
                    dragging ? 'border-action bg-surface-hover' : 'border-border',
                    invalid ? 'border-border-danger' : '',
                ].join(' ')}
            >
                {selected ? (
                    <>
                        {/*
                            Önizleme kutuya SIĞDIRILIR, kırpılmaz: kullanıcı
                            neyi seçtiğini görmeli, kırpma kararı ayrı bir
                            iştir.
                        */}
                        <img
                            src={selected.previewUrl}
                            alt=""
                            className="max-h-[12rem] max-w-full rounded-md object-contain"
                        />
                        <p className="text-meta text-fg-muted">
                            {selected.file.name}
                            {selected.width > 0
                                ? ` — ${t('workspace.media.upload.selected.dimensions', {
                                      width: String(selected.width),
                                      height: String(selected.height),
                                  })}`
                                : ''}
                        </p>
                    </>
                ) : (
                    <>
                        <p className="text-body text-fg-secondary">
                            {dragging
                                ? t('workspace.media.upload.dropzone.active')
                                : t('workspace.media.upload.dropzone.label')}
                        </p>
                        <p className="text-meta text-fg-muted">
                            {t('workspace.media.upload.dropzone.hint')}
                        </p>
                    </>
                )}

                <label
                    htmlFor={inputId}
                    className={[
                        'inline-flex min-h-[var(--density-hit-area-min)] cursor-pointer items-center',
                        'rounded-md border border-border px-[var(--space-3)] py-[var(--space-1)]',
                        'text-meta font-medium text-fg-secondary hover:bg-surface-hover hover:text-fg',
                        'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus',
                    ].join(' ')}
                >
                    {selected
                        ? t('workspace.media.upload.selected.replace')
                        : t('workspace.media.upload.dropzone.choose')}
                    <input
                        id={inputId}
                        ref={inputRef}
                        name="media-file"
                        type="file"
                        accept="image/*"
                        aria-invalid={invalid ? true : undefined}
                        aria-describedby={describedBy}
                        className="sr-only"
                        onChange={(event) => read(event.target.files?.[0])}
                    />
                </label>
            </div>
        </div>
    );
}

export default MediaDropzone;
