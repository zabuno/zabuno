import type { ReactNode } from 'react';
import { PageHeader } from '../../../catalog/layout/macro/PageHeader';
import { Badge, type BadgeStatus } from '../../../catalog/feedback/micro/Badge';

export type WorkspacePageStatusBadge = {
    key: string;
    status: BadgeStatus;
    label: string;
};

/**
 * Sayfanın genişlik sınıfı — `docs/58`.
 *
 * Her sayfa aynı genişlikte olmamalı: tek amaçlı bir form ile bir ürün
 * tablosu aynı ölçüyü paylaşamaz. Sınır konmadığında sayfa tam genişliğe
 * yayılır ve 1920 piksellik bir ekranda form 1900 piksel olur — alanlar
 * birbirinden metrelerce uzaklaşır, göz satır başına dönmeyi bırakır ve
 * birincil eylem içeriğin görüş alanı dışında kalır.
 */
export type WorkspacePageMeasure = 'form' | 'settings' | 'standard' | 'wide' | 'full';

const MEASURE_CLASS: Record<WorkspacePageMeasure, string> = {
    form: 'max-w-page-form',
    settings: 'max-w-page-settings',
    standard: 'max-w-page-standard',
    wide: 'max-w-page-wide',
    // Editör gibi gerçekten tüm genişliğe ihtiyaç duyan yüzeyler için.
    full: '',
};

type WorkspacePageFrameProps = {
    /** Page title rendered as the section heading; omit to skip PageHeader (e.g. when a child region already owns the heading). */
    title?: string;
    description?: ReactNode;
    /** Honest status badges derived only from props/loaded data — no invented values. */
    badges?: WorkspacePageStatusBadge[];
    /** Real quick actions wired to routes/handlers that already exist. */
    actions?: ReactNode;
    /** Sayfanın genişlik sınıfı; varsayılan `standard`. */
    measure?: WorkspacePageMeasure;
    children: ReactNode;
};

/**
 * Shared fluid frame reused across the six workspace pages: a PageHeader
 * (when a title is given), an honest status-badge row, and a gap-based
 * column for page content. Sizing uses clamp()/flex-wrap only — no
 * responsive breakpoint classes — so it starts adapting from a 320px
 * viewport.
 */
export function WorkspacePageFrame({
    title,
    description,
    badges = [],
    actions,
    measure = 'standard',
    children,
}: WorkspacePageFrameProps) {
    return (
        <div
            className={`flex flex-col ${MEASURE_CLASS[measure]}`}
            style={{ gap: 'var(--space-fluid-md)' }}
        >
            {title ? (
                <PageHeader title={title} description={description} actions={actions} />
            ) : (
                <>
                    {description ? (
                        <p className="text-body text-fg-secondary">{description}</p>
                    ) : null}
                    {actions ? (
                        <div className="flex flex-wrap items-center gap-2">{actions}</div>
                    ) : null}
                </>
            )}

            {badges.length > 0 ? (
                <div className="flex flex-wrap items-center gap-2">
                    {badges.map((badge) => (
                        <Badge key={badge.key} status={badge.status}>
                            {badge.label}
                        </Badge>
                    ))}
                </div>
            ) : null}

            <div className="flex flex-col" style={{ gap: 'var(--space-fluid-md)' }}>
                {children}
            </div>
        </div>
    );
}

export default WorkspacePageFrame;
