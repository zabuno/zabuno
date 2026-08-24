export function OrderBadge({ position, label }: { position: number; label: string }) {
    return <span aria-label={label}>{position + 1}</span>;
}
