import type { ReactNode } from 'react';
import clsx from 'clsx';

export type OpsCardProps = {
    title?: string;
    /** Başlık satırının sağı: filtre, küçük eylem, rozet. */
    toolbar?: ReactNode;
    children: ReactNode;
    className?: string;
    /** İçerik kendi dolgusunu yönetiyorsa (tablo gibi) kapatılır. */
    padded?: boolean;
};

/**
 * Operasyon kartı — `docs/99` (Metronic "card" karşılığı, token'lı).
 *
 * Metronic'in kart dili şudur: soluk uygulama zemini üstünde beyaz, hafif
 * kenarlıklı, yuvarlatılmış bir yüzey; başlık satırı ile gövde arasında
 * ince bir ayraç; sağ üstte kartın kendi araçları. Bu bileşen o düzeni
 * Zabuno token'larıyla kurar — `--color-surface`, `--color-border`,
 * `--radius-md`, `--space-*`. Gölge yok: Flat 2.0 (`docs/06` §10) derinliği
 * tonla kurar, gölgeyle değil.
 *
 * "Her bilgi grubunu karta sokmak yasak" (`docs/36` §5.2): kart, sınırının
 * anlam taşıdığı yerde kullanılır — bir kaynak, bir kayıt, bir tablo.
 */
export function OpsCard({ title, toolbar, children, className, padded = true }: OpsCardProps) {
    return (
        <section
            aria-label={title}
            className={clsx(
                // 2026 semi-flat: daha yumuşak köşe, tek piksel kenarlık, gölge yok.
                'flex min-w-0 flex-col rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)]',
                className,
            )}
        >
            {title || toolbar ? (
                <div className="flex flex-wrap items-center justify-between gap-[var(--space-2)] border-b border-[var(--color-border)] px-[var(--space-5)] py-[var(--space-4)]">
                    {/*
                        AĞIRLIK 700 (FF-131). AEP ölçeğinde izinli ağırlıklar
                        400 / 500 / 700'dür; 600 (`font-bold`) ölçeğin
                        DIŞINDADIR ve Roboto'da ayrı bir kesim olarak
                        yüklenmediği için tarayıcı onu 500 ile 700 arasında
                        SENTEZLİYORDU — yani kart başlıkları, teslim paketinin
                        beklediğinden hem daha ince hem de her tarayıcıda
                        biraz farklı çiziliyordu.
                    */}
                    {title ? (
                        <h2 className="text-body font-bold tracking-tight text-fg">{title}</h2>
                    ) : (
                        <span />
                    )}
                    {toolbar ? (
                        <div className="flex items-center gap-[var(--space-2)]">{toolbar}</div>
                    ) : null}
                </div>
            ) : null}
            <div className={clsx('min-w-0', padded && 'px-[var(--space-5)] py-[var(--space-5)]')}>
                {children}
            </div>
        </section>
    );
}

export default OpsCard;
