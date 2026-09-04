import { useEffect, useId, useRef, useState, type DragEvent, type ReactNode } from 'react';
import clsx from 'clsx';

export type FileDropzoneProps = {
    /** Bırakma alanının ana satırı — "Bir dosya bırakın ya da seçin". */
    label: string;
    /** İkinci satır: kabul edilen biçimler gibi. */
    hint?: string;
    /** Sürükleme sırasında görünen satır. */
    activeLabel?: string;
    /** Düğme metni; dosya seçiliyken `replaceLabel` kullanılır. */
    chooseLabel: string;
    replaceLabel?: string;
    /** `<input accept>` değeri: `image/*`, `.csv,text/csv` … */
    accept?: string;
    multiple?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    /** Girdinin adı; formda gönderilmese de otomatik doldurma için anlamlıdır. */
    name?: string;
    /** Seçili dosya varken alanın içinde çizilen şey (önizleme, dosya adı…). */
    preview?: ReactNode;
    /** Seçim yapıldığında — hiç dosya yoksa boş liste gelir. */
    onSelect: (files: File[]) => void;
    /** Dışarıdan seçim temizlendiğinde gerçek girdiyi de temizlemek için. */
    resetSignal?: unknown;
};

/**
 * Dosya seçme YÜZEYİ — sürükle-bırak, tıkla-seç, kendi metniyle.
 *
 * Ham bir `<input type="file">` iki şeyi birden yapar ve ikisini de kötü
 * yapar: tarayıcı onu İŞLETİM SİSTEMİNİN dilinde çizer ("Dosya Seç · Dosya
 * seçilmedi"), ve sürükleyip bırakmayı hiç desteklemez. Uygulamanın geri
 * kalanı Türkçe iken düğmenin sistem diline kaçması, sahibin 2026-09-04'te
 * menü içe aktarma ekranında gördüğü şeydi.
 *
 * Bu bileşen o yüzeyi TEK YERDE tutar: görsel yükleme (`MediaDropzone`) ve
 * menü CSV içe aktarma aynı modülü kullanır — sahibin isteği buydu. Önizleme
 * dışarıdan verilir, çünkü bir görselin önizlemesi ile bir CSV'nin adı aynı
 * şey değildir; ortak olan şey SEÇME davranışıdır.
 *
 * Kütüphane kullanılmadı: gereken davranış üç olaydan ibaret ve görünümün
 * bizde kalması şart (`docs/49` §7).
 */
export function FileDropzone({
    label,
    hint,
    activeLabel,
    chooseLabel,
    replaceLabel,
    accept,
    multiple = false,
    disabled = false,
    invalid = false,
    describedBy,
    name,
    preview,
    onSelect,
    resetSignal,
}: FileDropzoneProps) {
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [dragging, setDragging] = useState(false);

    /*
        Seçim dışarıdan temizlendiğinde GERÇEK girdi de temizlenir. Aksi
        hâlde dosya seçici hâlâ eski dosyayı tutar: kullanıcı aynı dosyayı
        yeniden seçemez, çünkü `change` tetiklenmez — ve alan boş görünürken
        aslında doludur.
    */
    useEffect(() => {
        if (preview === undefined && inputRef.current) {
            inputRef.current.value = '';
        }
    }, [preview, resetSignal]);

    function take(list: FileList | null | undefined): void {
        onSelect(Array.from(list ?? []));
    }

    function handleDrop(event: DragEvent<HTMLDivElement>): void {
        event.preventDefault();
        setDragging(false);

        if (!disabled) {
            take(event.dataTransfer.files);
        }
    }

    return (
        <div
            onDragOver={(event) => {
                event.preventDefault();

                if (!disabled) {
                    setDragging(true);
                }
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={handleDrop}
            className={clsx(
                'flex flex-col items-center gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-dashed',
                'p-[var(--space-4)] text-center',
                'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-inout)]',
                dragging ? 'border-action bg-surface-hover' : 'border-border',
                invalid && 'border-border-danger',
                disabled && 'opacity-60',
            )}
        >
            {preview ?? (
                <>
                    <p className="text-body text-fg-secondary">
                        {dragging ? (activeLabel ?? label) : label}
                    </p>
                    {hint ? <p className="text-meta text-fg-muted">{hint}</p> : null}
                </>
            )}

            {/*
                Girdi GİZLENİR ama kaldırılmaz: klavye ve ekran okuyucu onun
                üzerinden çalışır ve etiket ona bağlıdır.
            */}
            <label
                htmlFor={inputId}
                className={clsx(
                    'inline-flex min-h-[var(--density-hit-area-min)] items-center',
                    'rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-1)]',
                    'text-meta font-medium text-fg-secondary',
                    'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus',
                    disabled
                        ? 'cursor-not-allowed'
                        : 'cursor-pointer hover:bg-surface-hover hover:text-fg',
                )}
            >
                {preview !== undefined ? (replaceLabel ?? chooseLabel) : chooseLabel}
                <input
                    id={inputId}
                    ref={inputRef}
                    name={name}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    disabled={disabled}
                    aria-invalid={invalid ? true : undefined}
                    aria-describedby={describedBy}
                    className="sr-only"
                    onChange={(event) => take(event.target.files)}
                />
            </label>
        </div>
    );
}

export default FileDropzone;
