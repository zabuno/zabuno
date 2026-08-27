import type { ReactNode } from 'react';

type UnavailableRegionProps = {
    label: string;
    statusText: string;
    children?: ReactNode;
};

/**
 * Micro: a labeled region with an honest "not connected yet" status line.
 * Reused by Team and Billing sub-regions that have no backing API — it
 * never fabricates data, only states its own unavailable status plus
 * whatever disabled controls the caller passes as children.
 */
export function UnavailableRegion({ label, statusText, children }: UnavailableRegionProps) {
    return (
        <div role="region" aria-label={label} className="flex flex-col gap-3">
            <p className="text-body font-semibold text-fg">{label}</p>
            <p role="status" className="text-body text-fg-muted">
                {statusText}
            </p>
            {children}
        </div>
    );
}

export default UnavailableRegion;
