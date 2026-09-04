import { useEffect, useState } from 'react';

import { t } from '../../../i18n/platform';
import { Badge } from '../../catalog/feedback/micro/Badge';
import { OpsCard } from '../../ops/OpsCard';

type AuditRow = {
    id: number;
    provider: string;
    connectionId: number | null;
    connectionLabel: string | null;
    action: string;
    actor: string | null;
    at: string;
};

type AssignmentRow = {
    workspaceId: number;
    workspaceName: string;
    provider: string;
    connectionId: number;
    connectionLabel: string;
    health: string;
    since: string;
};

type State =
    | { phase: 'loading' }
    | { phase: 'error' }
    | { phase: 'ready'; audits: AuditRow[]; assignments: AssignmentRow[] };

const ENDPOINT = '/api/admin/ai/audit';

const cellClass = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-top';
const headClass =
    'px-[var(--space-3)] py-[var(--space-2)] text-meta font-bold text-fg-subtle text-start';

/**
 * AI denetim izi — `docs/98` Tur 3'ün iki ekransız tablosu.
 *
 * İki tablo, iki soru: "kim hangi anahtarı ne zaman yazdı/kapattı, hangi
 * hesap düştü" ve "hangi restoran hangi hesaba yapışmış". İkincisi
 * `docs/14` §2a'nın görünür karşılığı — yapışkanlık bir kararsa, kararın
 * kendisi okunabilmeli.
 *
 * Salt okunur. Sır yok — bu tablolar zaten sır taşımaz.
 */
export function AiAuditPage() {
    const [state, setState] = useState<State>({ phase: 'loading' });

    useEffect(() => {
        let cancelled = false;
        void (async () => {
            try {
                const response = await fetch(ENDPOINT, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (cancelled) return;
                if (!response.ok) {
                    setState({ phase: 'error' });
                    return;
                }
                const body = (await response.json()) as {
                    audits?: AuditRow[];
                    assignments?: AssignmentRow[];
                };
                setState({
                    phase: 'ready',
                    audits: body.audits ?? [],
                    assignments: body.assignments ?? [],
                });
            } catch {
                if (!cancelled) setState({ phase: 'error' });
            }
        })();
        return () => {
            cancelled = true;
        };
    }, []);

    if (state.phase === 'loading') {
        return (
            <p role="status" className="text-body text-fg-muted">
                {t('engineering.aiAudit.loading')}
            </p>
        );
    }

    if (state.phase === 'error') {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('engineering.aiAudit.error')}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-[var(--space-5)]">
            <OpsCard title={t('engineering.aiAudit.assignments.title')} padded={false}>
                {state.assignments.length === 0 ? (
                    <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                        {t('engineering.aiAudit.assignments.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <caption className="sr-only">
                                {t('engineering.aiAudit.assignments.title')}
                            </caption>
                            <thead className="bg-[var(--color-surface-subtle)]">
                                <tr>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.workspace')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.provider')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.connection')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.health')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.since')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.assignments.map((row) => (
                                    <tr
                                        key={`${row.workspaceId}-${row.provider}`}
                                        className="border-t border-[var(--color-border)]"
                                    >
                                        <td className={cellClass}>{row.workspaceName}</td>
                                        <td className={cellClass}>{row.provider}</td>
                                        <td className={cellClass}>{row.connectionLabel}</td>
                                        <td className={cellClass}>
                                            <Badge
                                                status={
                                                    row.health === 'healthy'
                                                        ? 'success'
                                                        : row.health === 'unhealthy'
                                                          ? 'error'
                                                          : 'info'
                                                }
                                            >
                                                {t(
                                                    `platform.connections.health.${row.health}` as never,
                                                )}
                                            </Badge>
                                        </td>
                                        <td className={cellClass}>{row.since}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </OpsCard>

            <OpsCard title={t('engineering.aiAudit.audits.title')} padded={false}>
                {state.audits.length === 0 ? (
                    <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                        {t('engineering.aiAudit.audits.empty')}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <caption className="sr-only">
                                {t('engineering.aiAudit.audits.title')}
                            </caption>
                            <thead className="bg-[var(--color-surface-subtle)]">
                                <tr>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.at')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.provider')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.connection')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.action')}
                                    </th>
                                    <th scope="col" className={headClass}>
                                        {t('engineering.aiAudit.col.actor')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {state.audits.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t border-[var(--color-border)]"
                                    >
                                        <td className={cellClass}>{row.at}</td>
                                        <td className={cellClass}>{row.provider}</td>
                                        <td className={cellClass}>{row.connectionLabel ?? '—'}</td>
                                        <td className={cellClass}>
                                            <code className="text-meta">{row.action}</code>
                                        </td>
                                        {/* Aktör yoksa "sunucu": komuttan yazılan kayıt kimsenin değildir, isim uydurulmaz. */}
                                        <td className={cellClass}>
                                            {row.actor ?? t('engineering.aiAudit.actor.server')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </OpsCard>
        </div>
    );
}

export default AiAuditPage;
