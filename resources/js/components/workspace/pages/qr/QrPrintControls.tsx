import type { ReactNode } from 'react';
import { CheckCircle } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';

/**
 * BASKI EKRANININ SEÇİM ÖĞELERİ — panel v3.1 kanonik kaynağı.
 *
 * Kaynak burada `SegmentedControl` kullanmıyor ve bu doğru: bölümlü kontrol
 * kısa, eş uzunlukta etiketler içindir ("Dikey / Yatay"). Kaynağın hazır
 * çıktıları ise iki satırlı ("Masa kartı" + "Pleksiglas içine") ve bir de
 * rozet taşıyor; bölümlü bir kontrolün içinde bunlar 320 pikselde okunmaz
 * bir şeride dönerdi.
 *
 * SEÇİLİ DURUM RENKLE ANLATILMAZ. Kaynak seçiliyi marka renginde bir kenarlık
 * ve bir onay simgesiyle işaretliyor; renk tek başına bir işaret değildir
 * (WCAG 2.2 §1.4.1). Burada üç işaret birlikte durur: `aria-pressed` (ekran
 * okuyucu), onay simgesi (biçim) ve alt çubuğun kelimeyle yazdığı özet
 * cümlesi. Kırmızı-yeşil ayırt edemeyen bir sahip de hangisini seçtiğini
 * okuyabilir.
 *
 * DOKUNMA HEDEFİ `--density-hit-area-min` ile taşınır: bu ekran mutfakta,
 * tabletle, ayakta kullanılır.
 */

const FOCUS_RING =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';

const TRANSITION =
    'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]';

type QrOptionCardProps = {
    selected: boolean;
    onSelect: () => void;
    icon: ReactNode;
    label: string;
    description: string;
    /** "En çok kullanılan" gibi tek bir vurgu; yoksa hiç çizilmez. */
    tag?: string;
};

/** İki satırlı, simgeli seçim kartı — kaynağın "Ne basacaksın?" ızgarası. */
export function QrOptionCard({
    selected,
    onSelect,
    icon,
    label,
    description,
    tag,
}: QrOptionCardProps) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onSelect}
            className={[
                'flex min-h-[4rem] items-center gap-[var(--space-3)] rounded-[var(--radius-lg)]',
                'border-2 p-[var(--space-3)] text-start',
                TRANSITION,
                FOCUS_RING,
                selected
                    ? 'border-action bg-surface'
                    : 'border-border bg-surface hover:bg-surface-hover',
            ].join(' ')}
        >
            <span
                aria-hidden="true"
                className={[
                    'flex h-10 w-8 shrink-0 items-center justify-center rounded-[var(--radius-sm)] border-2',
                    selected ? 'border-fg text-fg' : 'border-border-strong text-fg-secondary',
                ].join(' ')}
            >
                {icon}
            </span>

            <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-body font-bold text-fg">{label}</span>
                <span className="text-body text-fg-secondary">{description}</span>
                {tag ? <span className="text-meta font-bold text-fg-warning">{tag}</span> : null}
            </span>

            <QrSelectedMark selected={selected} />
        </button>
    );
}

type QrOptionChipProps = {
    selected: boolean;
    onSelect: () => void;
    label: string;
    /** İkinci satır: milimetre, kullanım ya da sayı. Yoksa çizilmez. */
    detail?: string;
    icon?: ReactNode;
    /** Ekran okuyucunun duyacağı tam ad; kısa etiketler için (masa numarası). */
    ariaLabel?: string;
    className?: string;
};

/** Tek satırlık seçim jetonu — kapsam, kâğıt, oran, yön, biçim ve masa. */
export function QrOptionChip({
    selected,
    onSelect,
    label,
    detail,
    icon,
    ariaLabel,
    className,
}: QrOptionChipProps) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            aria-label={ariaLabel}
            onClick={onSelect}
            className={[
                'flex min-h-[var(--density-hit-area-min)] flex-col items-center justify-center',
                'rounded-[var(--radius-md)] border px-[var(--space-3)] py-[var(--space-1)]',
                'tabular-nums',
                TRANSITION,
                FOCUS_RING,
                selected
                    ? 'border-action bg-surface font-bold text-fg'
                    : 'border-border bg-surface font-medium text-fg hover:bg-surface-hover',
                className ?? '',
            ].join(' ')}
        >
            <span className="flex items-center gap-[var(--space-1)] text-body">
                {icon}
                {label}
            </span>
            {detail ? <span className="text-meta text-fg-secondary">{detail}</span> : null}
        </button>
    );
}

/**
 * Seçili işareti: biçim (onay simgesi) + kelime (yalnız ekran okuyucuya).
 *
 * Kelimeyi görünür yazmak beş tasarımın altına beş kez "Seçili" koyardı ve
 * yalnız biri doğru olurdu; görünen ayrım biçimdir, kelimeyle anlatan yer ise
 * alt çubuğun özet cümlesidir.
 */
export function QrSelectedMark({ selected }: { selected: boolean }) {
    if (!selected) return null;

    return (
        <span className="flex shrink-0 items-center gap-[var(--space-1)] text-fg-link">
            <CheckCircle size={20} weight="fill" aria-hidden="true" />
            <span className="sr-only">{t('workspace.publication.qrScreen.optionSelected')}</span>
        </span>
    );
}
