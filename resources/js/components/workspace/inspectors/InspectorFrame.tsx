import type { ReactNode } from 'react';

/**
 * Bağlam panellerinin ortak iskeleti — `docs/60`.
 *
 * Üç panel de aynı üç parçadan oluşur: başlık, ad-değer satırları ve en fazla
 * bir kısayol. İskeleti paylaşmak bir tasarım tercihi değil, panelin
 * SÖZLEŞMESİNİ tek yerde tutmaktır: satırlar `dl` olur, kısayol tek kalır ve
 * hiçbir panel kendi başına bir alet çantasına dönüşemez.
 */
export type InspectorRow = {
    key: string;
    label: string;
    value: string;
};

export type InspectorFrameProps = {
    title: string;
    rows: InspectorRow[];
    /**
     * Panelin TEK eylemi ve yalnız ana alanda ZATEN var olan bir yola kısa
     * yol. Panel yeni bir yol açsaydı bir kolaylık değil, gizli bir ön koşul
     * olurdu (`docs/60` §3).
     */
    shortcut?: { label: string; onSelect: () => void };
    children?: ReactNode;
};

export function InspectorFrame({ title, rows, shortcut, children }: InspectorFrameProps) {
    return (
        <div className="flex flex-col gap-[var(--space-fluid-md)]">
            <h2 className="text-body font-semibold text-fg">{title}</h2>

            <dl className="flex flex-col gap-3">
                {rows.map((row) => (
                    <div key={row.key} className="flex flex-col gap-0.5">
                        <dt className="text-meta text-fg-muted">{row.label}</dt>
                        <dd className="text-body text-fg">{row.value}</dd>
                    </div>
                ))}
            </dl>

            {children}

            {shortcut !== undefined ? (
                <button
                    type="button"
                    onClick={shortcut.onSelect}
                    className="min-h-[var(--density-hit-area-min)] rounded-md border border-border px-3 py-2 text-body font-medium text-fg-secondary hover:bg-surface-hover"
                >
                    {shortcut.label}
                </button>
            ) : null}
        </div>
    );
}

export default InspectorFrame;
