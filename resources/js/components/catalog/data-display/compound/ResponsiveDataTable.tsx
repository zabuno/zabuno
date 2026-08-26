import type { ReactNode } from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeadCell, TableRow } from 'flowbite-react';
import clsx from 'clsx';
import { Skeleton } from '../micro/Skeleton';

export type DataTableColumn<Row> = {
    key: string;
    header: ReactNode;
    align?: 'start' | 'end';
    render: (row: Row) => ReactNode;
};

export type ResponsiveDataTableProps<Row> = {
    caption: string;
    columns: readonly DataTableColumn<Row>[];
    rows: readonly Row[];
    getRowKey: (row: Row, index: number) => string;
    loading?: boolean;
    emptyMessage?: string;
    className?: string;
};

/**
 * Compound: composes Micro/Data Display/Skeleton for loading-row
 * placeholders. Presentational/headless-data only — it renders whatever
 * typed columns/rows it is given and never fetches, paginates, sorts, or
 * decides a business rule itself (docs/35 §2a macro/compound boundary).
 */
export function ResponsiveDataTable<Row>({
    caption,
    columns,
    rows,
    getRowKey,
    loading = false,
    emptyMessage = 'No data to display.',
    className,
}: ResponsiveDataTableProps<Row>) {
    // Yoğunluk token seviyesinde çözülür: bu bileşen hangi modda olduğunu
    // BİLMEZ, yalnız `--density-*` okur. Mod, kapsayıcıdaki sınıfla belirlenir
    // (docs/37 §2.2, kesen eksen X4). Satır yüksekliği ve iç boşluk değişir;
    // tipografi değişmez.
    const isEmpty = !loading && rows.length === 0;

    return (
        <div className={clsx('overflow-x-auto', className)}>
            <Table>
                <caption className="sr-only">{caption}</caption>
                <TableHead>
                    <TableRow>
                        {columns.map((column) => (
                            <TableHeadCell
                                key={column.key}
                                className={clsx(
                                    'px-[var(--density-padding-inline)]',
                                    column.align === 'end' && 'text-end',
                                )}
                            >
                                {column.header}
                            </TableHeadCell>
                        ))}
                    </TableRow>
                </TableHead>
                <TableBody className="divide-y">
                    {loading
                        ? Array.from({ length: 3 }, (_, rowIndex) => (
                              <TableRow key={`skeleton-${rowIndex}`}>
                                  {columns.map((column) => (
                                      <TableCell
                                          key={column.key}
                                          className="h-[var(--density-row-height)] px-[var(--density-padding-inline)]"
                                      >
                                          <Skeleton shape="text" />
                                      </TableCell>
                                  ))}
                              </TableRow>
                          ))
                        : rows.map((row, rowIndex) => (
                              <TableRow key={getRowKey(row, rowIndex)}>
                                  {columns.map((column) => (
                                      <TableCell
                                          key={column.key}
                                          className={clsx(
                                              'h-[var(--density-row-height)] px-[var(--density-padding-inline)]',
                                              column.align === 'end' && 'text-end',
                                          )}
                                      >
                                          {column.render(row)}
                                      </TableCell>
                                  ))}
                              </TableRow>
                          ))}
                    {isEmpty ? (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                className="h-[var(--density-row-height)] text-center text-fg-muted"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    ) : null}
                </TableBody>
            </Table>
        </div>
    );
}
