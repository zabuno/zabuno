export type AdminFooterProps = {
    productName: string;
    currentYear: number;
};

/**
 * Compound: persona-neutral footer landmark showing only the real product
 * name and current copyright year (docs/35). No links, version, or fabricated
 * data — a surface supplies the real name and year via props.
 */
export function AdminFooter({ productName, currentYear }: AdminFooterProps) {
    return (
        <footer className="admin-footer flex flex-wrap items-center gap-[var(--space-fluid-sm)] border-t border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-sm)] text-[var(--color-text-secondary)]">
            <span>
                © {currentYear} {productName}
            </span>
        </footer>
    );
}
