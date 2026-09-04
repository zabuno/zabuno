import type { ReactNode } from 'react';

export type OpsCrumb = { label: string; href?: string };

export type OpsPageHeaderProps = {
    title: string;
    description?: string;
    /** Yalnız gerçek bir hiyerarşi varsa — tek seviye için boş bırakılır. */
    crumbs?: OpsCrumb[];
    /** Sayfanın birincil eylemi ve yanındakiler — Metronic "toolbar" karşılığı. */
    actions?: ReactNode;
    /** Başlığın erişilebilir kimliği; sayfa bunu `aria-labelledby` ile kullanır. */
    headingId?: string;
};

/**
 * Operasyon sayfası başlığı — `docs/99` (Metronic-esinli, token'lı).
 *
 * Metronic'in "toolbar" satırının karşılığı: sol tarafta başlık + breadcrumb,
 * sağ tarafta sayfanın eylemleri. Metronic'ten alınan şey DÜZEN; renk, radius
 * ve boşluk Zabuno semantic token'larından gelir — bileşen tek bir ham piksel
 * bilmez (`docs/36` §5.4).
 *
 * Breadcrumb yalnız gerçek hiyerarşi varsa çizilir: tek seviyeli bir sayfaya
 * "Platform / Plans" yazmak, olmayan bir üst sayfa vaat eder (`docs/50` §9.2).
 */
export function OpsPageHeader({
    title,
    description,
    crumbs = [],
    actions,
    headingId = 'ops-page-heading',
}: OpsPageHeaderProps) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-[var(--space-3)] border-b border-[var(--color-border)] pb-[var(--space-4)]">
            <div className="flex min-w-0 flex-col gap-[var(--space-1)]">
                {crumbs.length > 0 ? (
                    <nav aria-label="Breadcrumb">
                        <ol className="flex flex-wrap items-center gap-[var(--space-1)] text-meta text-fg-subtle">
                            {crumbs.map((crumb, index) => (
                                <li
                                    key={`${crumb.label}-${index}`}
                                    className="flex items-center gap-[var(--space-1)]"
                                >
                                    {crumb.href ? (
                                        <a
                                            href={crumb.href}
                                            className="underline underline-offset-2"
                                        >
                                            {crumb.label}
                                        </a>
                                    ) : (
                                        <span>{crumb.label}</span>
                                    )}
                                    {index < crumbs.length - 1 ? (
                                        <span aria-hidden="true">/</span>
                                    ) : null}
                                </li>
                            ))}
                        </ol>
                    </nav>
                ) : null}
                <h1 id={headingId} className="text-title font-bold text-fg">
                    {title}
                </h1>
                {description ? <p className="text-body text-fg-secondary">{description}</p> : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-[var(--space-2)]">{actions}</div>
            ) : null}
        </header>
    );
}

export default OpsPageHeader;
