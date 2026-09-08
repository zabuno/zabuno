import clsx from 'clsx';
import type { MouseEvent, ReactNode } from 'react';

/**
 * SEÇİLECEK BİR GÖRSEL YOKKEN KONUŞAN KUTU — FF-224.
 *
 * ═══ NEDEN VAR ═══
 *
 * Sahip bir ürünün sunum panelini açtı, "Photo" başlığı altında tek bir
 * seçenek gördü ("No photo") ve sordu: *"Ürünlere, menülere resim yükleme
 * alanı yok?"* Vardı — Medya ekranında. Bu panel yalnız İŞLENMESİ BİTMİŞ
 * medyadan seçtiriyor, ve hiç medya yokken listenin boş olmasından başka
 * bir şey söylemiyordu. Ne "önce yükleyin", ne yükleme ekranına bir yol,
 * ne hangi yuvaya yükleneceği.
 *
 * İKİNCİ TUZAK: medya YUVAYA göre yüklenir (`config/media-slots.php`). Medya
 * ekranını kendi başına bulan sahip yanlış yuvayı seçerse fotoğrafı yine bu
 * listede görünmez ve sebebi yine yazmaz. Bu yüzden yuvanın ADI ekranda
 * geçer ve gereksinimi (`en-boy oranı`, asgari ölçü) sunucudan — yani
 * yapılandırmadan — okunur, elle yazılmaz.
 *
 * ═══ NEDEN BURAYA YÜKLEME KOYMUYORUZ ═══
 *
 * Medya ekranı kırpma, biçim dönüştürme, güvenlik taraması ve yuva
 * politikası taşıyor. İkinci bir yükleme yolu o zincirin yarısını atlar.
 * Bu kutunun işi YOLU GÖSTERMEK; ikinci bir kapı açmak değil.
 *
 * ═══ METİN BİLMEZ ═══
 *
 * `docs/35`: kataloğun bileşenleri kendi kelimesini konuşmaz, metin PROP
 * olarak gelir. Varsayılan bir dize yoktur (`DS-I18N-EMBEDDED`).
 */
export type MediaSlotHintProps = {
    /** Ne olduğu ve nereye yükleneceği — yuvanın adını TAŞIYAN cümle. */
    message: string;
    /**
     * Yuvanın ölçü beklentisi. `null` = sunucu politikayı henüz vermedi ya
     * da veremedi; o zaman satır HİÇ çizilmez. Uydurma bir ölçü, hiç ölçü
     * olmamasından pahalıdır.
     */
    requirement?: string | null;
    /**
     * Medya ekranının GERÇEK adresi. `null` ise bağlantı çizilmez — bu
     * kullanıcı o ekranı açamıyor demektir ve açamayacağı bir yere
     * bağlantı, yeni bir çıkmaz sokaktır.
     */
    href?: string | null;
    /** Bağlantının adı. `href` doluysa zorunludur. */
    linkLabel?: string;
    /**
     * Süslenmemiş sol tıklamayı uygulama karşılasın diye. Verilmezse
     * tarayıcı adresi kendi izler — bağlantı yine ÇALIŞIR.
     */
    onNavigate?: (event: MouseEvent<HTMLAnchorElement>) => void;
    /** Yanındaki ikon; `aria-hidden` çizilmesi çağırana aittir. */
    icon?: ReactNode;
};

/*
    DAR EKRAN TABAN. Kutu 320 pikselde tek sütuna düşer; bağlantı tam
    dokunma hedefi taşır (`--density-hit-area-min`) çünkü düz bir metin
    bağlantısı parmakla ıskalanır. Boşluk SIKI: büyük hedef + sıkı boşluk.
*/
const boxClass = clsx(
    'flex flex-col gap-[var(--space-2)] rounded-md border border-border',
    'bg-surface-subtle px-[var(--space-3)] py-[var(--space-2)]',
);

const linkClass = clsx(
    'inline-flex min-h-[var(--density-hit-area-min)] items-center self-start',
    'rounded-md border border-border px-[var(--space-3)] py-[var(--space-2)]',
    'text-body font-medium text-fg underline hover:bg-surface-hover',
);

export function MediaSlotHint({
    message,
    requirement = null,
    href = null,
    linkLabel,
    onNavigate,
    icon,
}: MediaSlotHintProps) {
    return (
        <div className={boxClass}>
            <p className="flex items-start gap-[var(--space-2)] text-meta text-fg-secondary">
                {icon ?? null}
                <span>{message}</span>
            </p>
            {requirement === null || requirement === '' ? null : (
                <p className="text-meta text-fg-muted tabular-nums">{requirement}</p>
            )}
            {href === null || href === '' || linkLabel === undefined ? null : (
                <a className={linkClass} href={href} onClick={onNavigate}>
                    {linkLabel}
                </a>
            )}
        </div>
    );
}

export default MediaSlotHint;
