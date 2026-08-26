import type { ReactNode } from 'react';
import { EvidenceStatusBadge } from './EvidenceStatusBadge';

type ReadinessItemProps = {
    title: string;
    description: string;
    status?: ReactNode;
    details?: ReactNode;
};

/**
 * Compound: one row of the readiness checklist — a real Stage 1 exit
 * requirement plus its honest evidence status. No control triggers a
 * check from here; evidence appears only after an independently run
 * verification is wired up. `status`/`details` let a dynamic item (e.g.
 * tenant isolation) inject its own real status and metadata while every
 * other item keeps the default Unavailable badge.
 */
export function ReadinessItem({ title, description, status, details }: ReadinessItemProps) {
    return (
        <li className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex flex-col gap-1">
                <p className="text-sm font-semibold text-fg">{title}</p>
                <p className="text-sm text-fg-muted">{description}</p>
                {details}
            </div>
            {status ?? <EvidenceStatusBadge />}
        </li>
    );
}

export default ReadinessItem;
