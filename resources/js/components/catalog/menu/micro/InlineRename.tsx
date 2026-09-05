import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '../../../../lib/utils';

export type InlineRenameProps = {
    /** Görünen ad. */
    value: string;
    /** Erişilebilir ad: "Kaymakam adını değiştir" gibi. */
    label: string;
    /** Kaydet. Sunucu reddederse hata mesajı döndürülür, `null` başarıdır. */
    onSubmit: (next: string) => Promise<string | null> | string | null;
    /** Boş ad reddedilir; mesaj çağırandan gelir (katalogda yaşar). */
    emptyMessage: string;
    saveLabel: string;
    cancelLabel: string;
    /** Görünüm sınıfı: kategori başlığı ile ürün satırı aynı bileşeni kullanır. */
    textClassName?: string;
    inputClassName?: string;
    /**
     * Ad DEĞİŞTİRİLEMEZ: yalnız metin çizilir, tetikleyici düğme hiç doğmaz.
     *
     * Menü ekranını adı değiştiremeyen bir rolle (Mutfak) açtığında gerekir.
     * Düğmeyi `disabled` çizmek yerine hiç çizmemek, bu deponun kuralıdır
     * (`docs/98` FF-74): yapılamayan iş için bir hedef bırakmak, kullanıcıya
     * olmayan bir yol göstermektir.
     */
    readOnly?: boolean;
};

/**
 * Adı YERİNDE düzeltir — sahibin isteği (2026-09-04).
 *
 * Önceki hâl `window.prompt` idi. Bir tarayıcı dialogu şu sebeplerle üründen
 * kopuktur ve burada somut zarar veriyordu:
 *
 *   1. **Ürünün dışında çizilir.** Chrome onu "zabuno.com web sitesinin
 *      mesajı" başlığıyla, kendi yazı tipi ve kendi düğmeleriyle ("İptal /
 *      Tamam") gösterir. Kullanıcı ürünün içindeyken bir anda tarayıcının
 *      içine düşer; bu, dolandırıcılık uyarılarıyla aynı görsel dildir.
 *   2. **Bağlamı yok eder.** Diyalog açıkken düzenlenen satır görünmez:
 *      "hangi ürünün adını yazıyorum?" sorusu ekranda cevapsızdır.
 *   3. **Sessizce ölür.** Tarayıcı "bu sayfanın başka diyalog oluşturmasını
 *      engelle" kutusunu sunar; kullanıcı bir kez işaretlerse düzenle düğmesi
 *      o oturum boyunca HİÇBİR ŞEY yapmaz ve hata da vermez.
 *   4. **Doğrulama gösteremez.** Boş ad girildiğinde diyalog çoktan kapanmış
 *      olur; hata satırdan uzakta, sayfanın başka bir yerinde belirir.
 *   5. **Sayfayı dondurur.** `prompt` eşzamanlıdır: açık olduğu sürece
 *      uygulamanın hiçbir yeri çizilmez, hiçbir istek işlenmez.
 *
 * Yerine geçen desen SATIR İÇİ DÜZENLEMEDİR: araştırmanın da işaret ettiği
 * gibi en az sürtünmeli yol, bağlamı korumaktır — komşu satırlar ekranda
 * kalır ve düzeltme birkaç tuşla biter.
 *
 * Klavye sözleşmesi: `Enter` kaydeder, `Escape` vazgeçer, odak kaybı
 * KAYDETMEZ. Odakla kaydetmek, sekme değiştiren kullanıcının farkında
 * olmadan yazdığını göndermesi demek olurdu.
 */
export function InlineRename({
    value,
    label,
    onSubmit,
    emptyMessage,
    saveLabel,
    cancelLabel,
    textClassName,
    inputClassName,
    readOnly = false,
}: InlineRenameProps) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const inputRef = useRef<HTMLInputElement | null>(null);
    const triggerRef = useRef<HTMLButtonElement | null>(null);

    useEffect(() => {
        if (editing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editing]);

    /*
        Taslak, düzenleme AÇILIRKEN kurulur — bir efektle değil.

        İlk hâlde `useEffect` her `value` değişiminde `setDraft` çağırıyordu;
        bu, efektin içinde senkron durum güncellemesidir ve React'i ardışık
        render'lara sokar (lint kuralı da tam olarak bunu yakaladı). Oysa
        taslağın dış dünyayla eşitlenmesi gereken tek an, kullanıcının
        düzenlemeye başladığı andır.
    */
    function startEditing(): void {
        setDraft(value);
        setError(null);
        setEditing(true);
    }

    function cancel(): void {
        setEditing(false);
        setError(null);
        setDraft(value);
        // Odak GERİ DÖNER: klavye kullanıcısı vazgeçtiğinde sayfanın başına
        // fırlamamalı.
        triggerRef.current?.focus();
    }

    async function save(): Promise<void> {
        const next = draft.trim();

        if (next === '') {
            setError(emptyMessage);

            return;
        }

        if (next === value) {
            cancel();

            return;
        }

        setSaving(true);
        const failure = await onSubmit(next);
        setSaving(false);

        if (failure !== null) {
            setError(failure);

            return;
        }

        setEditing(false);
        setError(null);
        triggerRef.current?.focus();
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>): void {
        if (event.key === 'Enter') {
            event.preventDefault();
            void save();
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            // Menü/diyalog gibi dış katmanlar da Escape dinliyor olabilir;
            // buradaki iptal onlara ulaşmamalı.
            event.stopPropagation();
            cancel();
        }
    }

    if (readOnly) {
        /*
            Metin, düzenlenebilir hâldeki metinle AYNI sınıfı taşır: satırın
            hizası role göre kaymaz — iki farklı kullanıcı aynı listeye
            baktığında aynı ızgarayı görür.
        */
        return <span className={cn('min-w-0 truncate', textClassName)}>{value}</span>;
    }

    if (!editing) {
        return (
            <button
                ref={triggerRef}
                type="button"
                aria-label={label}
                onClick={startEditing}
                className={cn(
                    'flex min-w-0 items-center rounded-[var(--radius-sm)] text-start',
                    /*
                        DOKUNMA HEDEFİ 44 px (`--density-hit-area-min`).

                        İlk hâlde tetikleyici yalnız metin yüksekliğindeydi:
                        telefonda ölçüldüğünde 24 px çıktı. Parmakla
                        vurulamayan bir hedef, klavyeyle ve fareyle
                        çalışıyor olsa bile çalışmıyor demektir.
                    */
                    'min-h-[var(--density-hit-area-min)] px-[var(--space-1)]',
                    '[&>span]:truncate',
                    // Tıklanabilirlik ÜZERİNE GELİNCE söylenir: her satırda
                    // kalıcı bir çerçeve, listeyi kutular ormanına çevirirdi.
                    'hover:bg-surface-hover',
                    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                    textClassName,
                )}
            >
                <span className="truncate">{value}</span>
            </button>
        );
    }

    return (
        <span className="flex min-w-0 flex-1 flex-col gap-[var(--space-1)]">
            <span className="flex min-w-0 items-center gap-[var(--space-2)]">
                <input
                    ref={inputRef}
                    type="text"
                    value={draft}
                    aria-label={label}
                    aria-invalid={error !== null ? true : undefined}
                    disabled={saving}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={onKeyDown}
                    className={cn(
                        'min-w-0 flex-1 rounded-[var(--radius-md)] border bg-surface',
                        'px-[var(--space-2)] py-[var(--space-1)] text-body text-fg',
                        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                        error === null ? 'border-border' : 'border-border-danger',
                        inputClassName,
                    )}
                />
                <button
                    type="button"
                    disabled={saving}
                    onClick={() => void save()}
                    className="min-h-[var(--density-hit-area-min)] shrink-0 rounded-[var(--radius-md)] border border-action bg-action px-[var(--space-3)] text-meta font-bold text-action-fg disabled:opacity-60"
                >
                    {saveLabel}
                </button>
                <button
                    type="button"
                    disabled={saving}
                    onClick={cancel}
                    className="min-h-[var(--density-hit-area-min)] shrink-0 rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-meta font-medium text-fg-secondary hover:bg-surface-hover"
                >
                    {cancelLabel}
                </button>
            </span>
            {/*
                Hata DÜZENLENEN ALANIN ALTINDA durur. `prompt` ile hata
                sayfanın başka bir yerinde beliriyordu: kullanıcı yazdığı yere
                bakarken uyarı gözünün dışında kalıyordu.
            */}
            {error !== null ? (
                <span role="alert" className="text-meta text-fg-danger">
                    {error}
                </span>
            ) : null}
        </span>
    );
}

export default InlineRename;
