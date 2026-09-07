import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Breadcrumbs, type BreadcrumbItem } from '../../navigation/compound/Breadcrumbs';

export type PageHeaderProps = {
    title: string;
    /**
     * Optional trail rendered above the title; omit to hide.
     *
     * İZ, ADIYLA BİRLİKTE GELİR — `docs/121` Ö1.
     *
     * Önce yalnız `BreadcrumbItem[]` idi ve `Breadcrumbs` bölge adını kodda
     * gömülü `'Breadcrumb'`den alıyordu. Adı ayrı bir İSTEĞE BAĞLI prop
     * yapmak aynı deliği açık bırakırdı: unutulduğu anda gömülü metin geri
     * gelirdi. Tek bir nesne, ikisini ayrılmaz kılar — iz varsa adı da var.
     */
    breadcrumbs?: {
        /** `<nav>` bölgesinin erişilebilir adı; çağıranın kataloğundan. */
        label: string;
        /** İz boşken ekran okuyucuya söylenen cümle. */
        emptyLabel: string;
        items: BreadcrumbItem[];
    };
    description?: ReactNode;
    /** Slot for primary/secondary page actions (buttons), rendered end-aligned. */
    actions?: ReactNode;
    className?: string;
};

/**
 * Macro: composes Compound/Navigation/Breadcrumbs above a title/
 * description/actions row. Does not reimplement Breadcrumbs' markup, and
 * takes no position on what `actions` renders — the caller supplies
 * whatever Button/IconButton nodes it needs.
 */
export function PageHeader({
    title,
    breadcrumbs,
    description,
    actions,
    className,
}: PageHeaderProps) {
    /*
        DİKEY RİTİM ÖLÜ ALAN ÖLÇEĞİNDEN — `docs/117` M9.

        Başlık bloğunun aralıkları sabit bir adımdı (`gap-3`, 12px) ve her
        genişlikte aynıydı: dar ekranda taban, kırpılmış masaüstüydü. Aralık
        artık ölü alan ölçeğini okur ve dar ekranda daralır; `min()` tavanı
        bugünkü değerde tutar, yani masaüstü görünümü değişmez. Yazı boyu
        ve hedefler değişmez — küçülen tek şey satırlar arasındaki boşluk.
    */
    const rhythm = 'gap-[min(var(--space-3),var(--space-fluid-sm))]';

    return (
        <div className={clsx('flex flex-col', rhythm, className)}>
            {breadcrumbs ? (
                <Breadcrumbs
                    items={breadcrumbs.items}
                    label={breadcrumbs.label}
                    emptyLabel={breadcrumbs.emptyLabel}
                />
            ) : null}
            <div className={clsx('flex flex-wrap items-start justify-between', rhythm)}>
                <div className="flex flex-col gap-1">
                    {/*
                        Sayfa başlığı 700 ve SIKI harf aralığı (FF-131):
                        teslim paketi başlığı `letter-spacing:-.02em` ile
                        çiziyor. 600 ağırlık AEP ölçeğinde yok ve Roboto'da
                        ayrı kesim olarak yüklenmediği için sentezleniyordu.
                    */}
                    <h1 className="text-title font-bold tracking-[-0.02em] text-fg">{title}</h1>
                    {description ? (
                        <p className="max-w-[60ch] text-body text-fg-secondary">{description}</p>
                    ) : null}
                </div>
                {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
            </div>
        </div>
    );
}
