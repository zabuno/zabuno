import type { ReactNode } from 'react';
import clsx from 'clsx';

export type KeyValueEntry = {
    key: string;
    label: string;
    value: ReactNode;
};

export type KeyValueListProps = {
    entries: readonly KeyValueEntry[];
    className?: string;
};

/**
 * Compound: `<dl>` sabit bir çocuk grameri dayatır — doğrudan çocuk yalnız
 * `dt`/`dd` veya bunları DOĞRUDAN saran bir `div` olabilir ve rol taşıyan
 * hiçbir eleman (ayraç dahil, `role="none"` bile) araya giremez. Bu yüzden
 * satır ayrımı bir Divider bileşeniyle değil, grup `div`'inin kenarlığıyla
 * yapılır; burada bileşen tekrarı değil, erişilebilir yapı kazanır.
 */
export function KeyValueList({ entries, className }: KeyValueListProps) {
    return (
        <dl className={clsx('flex flex-col', className)}>
            {entries.map((entry, index) => (
                <div
                    key={entry.key}
                    className={clsx(
                        'flex items-baseline justify-between gap-4 py-2',
                        index > 0 && 'border-t border-border',
                    )}
                >
                    <dt className="text-sm text-fg-muted">{entry.label}</dt>
                    <dd className="text-sm font-medium text-fg">{entry.value}</dd>
                </div>
            ))}
        </dl>
    );
}
