import type { ReactNode } from 'react';

/**
 * NUMARALI ADIM — panel v3.1 kanonik kaynağının QR ekranı.
 *
 * Kaynak üç bölümü 1-2-3 diye numaralandırıyor ve bu bir süs değil: ekranda
 * dokuz kontrol var ve numarasız hâlde bunlar bir form değil bir kokpittir.
 * Numara sahibe "bu bir sıra" der; sıra da onun kafasındaki sıradır (*ne
 * basacaksın → hangi masalar → nasıl görünsün*).
 *
 * ADIM SIRALI AMA KİLİTLİ DEĞİL. Sihirbaz gibi "önce 1'i bitir" demiyor:
 * üçünün de makul bir varsayılanı var ve sahip yalnız değiştirmek istediğine
 * dokunur. Kilitli bir sihirbaz, kırkıncı kez kart basan sahibe her seferinde
 * üç ekran gezdirirdi.
 *
 * Numara `aria-hidden` DEĞİL: bölümün erişilebilir adı numarayı da taşır
 * ("1. Ne basacaksın?"), böylece ekran okuyucu kullanan biri de kaçıncı
 * bölümde olduğunu bilir.
 */
export function QrStepSection({
    step,
    title,
    aside,
    children,
}: {
    step: number;
    title: string;
    /** Başlığın sağındaki tek satırlık not; yoksa çizilmez. */
    aside?: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-label={`${String(step)}. ${title}`}
            className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]"
        >
            <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                <span
                    aria-hidden="true"
                    className="flex h-7 min-w-7 items-center justify-center rounded-pill bg-action text-body font-bold text-action-fg"
                >
                    {step}
                </span>
                <h2 className="flex-1 text-body font-bold text-fg">{title}</h2>
                {aside ? <span className="text-body text-fg-secondary">{aside}</span> : null}
            </div>

            {children}
        </section>
    );
}
