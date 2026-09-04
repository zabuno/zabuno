import { useEffect } from 'react';

import { t } from '../../../../i18n/workspace';
import { FileDropzone } from '../../../catalog/forms/compound/FileDropzone';

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
    /** Birden çok dosya bırakıldığında İLKİ `onSelect`, kalanlar buraya (FF-76 çoklu yükleme). */
    onSelectMore?: (images: SelectedImage[]) => void;
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
export function MediaDropzone({
    selected,
    invalid,
    describedBy,
    onSelect,
    onSelectMore,
}: MediaDropzoneProps) {
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

    /*
        ÇOKLU SEÇİM (FF-76): 40 fotoğrafı tek tek seçtirmek, kebapçıya 40 kez
        aynı işi yaptırmaktı. İlk dosya bugünkü tek-dosya yolundan geçer
        (önizleme, ölçü); kalanlar liste olarak yukarı verilir ve sırayla
        yüklenir.
    */
    function readAll(files: File[]) {
        read(files[0]);

        if (files.length > 1 && onSelectMore) {
            onSelectMore(
                files.slice(1).map((file) => ({ file, previewUrl: '', width: 0, height: 0 })),
            );
        }
    }

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <FileDropzone
                name="media-file"
                accept="image/*"
                multiple={onSelectMore !== undefined}
                invalid={invalid}
                describedBy={describedBy}
                label={t('workspace.media.upload.dropzone.label')}
                activeLabel={t('workspace.media.upload.dropzone.active')}
                hint={t('workspace.media.upload.dropzone.hint')}
                chooseLabel={t('workspace.media.upload.dropzone.choose')}
                replaceLabel={t('workspace.media.upload.selected.replace')}
                onSelect={readAll}
                preview={
                    selected ? (
                        <>
                            {/*
                                Önizleme kutuya SIĞDIRILIR, kırpılmaz: kullanıcı
                                neyi seçtiğini görmeli, kırpma kararı ayrı bir
                                iştir.
                            */}
                            <img
                                src={selected.previewUrl}
                                alt=""
                                className="max-h-[12rem] max-w-full rounded-[var(--radius-md)] object-contain"
                            />
                            {/* Dosya adı + ölçü: ölçü değişir, rakam hizalanır. */}
                            <p className="text-meta text-fg-muted tabular-nums">
                                {selected.file.name}
                                {selected.width > 0
                                    ? ` — ${t('workspace.media.upload.selected.dimensions', {
                                          width: String(selected.width),
                                          height: String(selected.height),
                                      })}`
                                    : ''}
                            </p>
                        </>
                    ) : undefined
                }
            />
        </div>
    );
}

export default MediaDropzone;
