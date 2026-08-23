import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { t } from '../../../../i18n/workspace';
import { Badge } from '../../../catalog/feedback/micro/Badge';
import { ReadinessItem } from './ReadinessItem';

type BackupRestoreEvidenceItemProps = {
    workspaceId: number;
};

type EvidenceState =
    | { phase: 'loading' }
    | { phase: 'unavailable' }
    | { phase: 'error' }
    | {
          phase: 'resolved';
          status: 'passed' | 'failed';
          ranAt: string;
          gitSha: string;
          durationMs: number;
          restoredRowCount: number;
          claim: string;
      };

function evidenceEndpoint(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/security/evidence/backup-restore`;
}

function parseEvidence(payload: unknown): EvidenceState | null {
    if (typeof payload !== 'object' || payload === null) {
        return null;
    }

    const data = (payload as { data?: unknown }).data;
    if (typeof data !== 'object' || data === null) {
        return null;
    }

    const record = data as Record<string, unknown>;
    const status = record.status;
    if (status !== 'passed' && status !== 'failed') {
        return null;
    }

    const ranAt = record.ran_at;
    const gitSha = record.git_sha;
    const durationMs = record.duration_ms;
    const restoredRowCount = record.restored_row_count;
    const claim = record.claim;

    if (typeof ranAt !== 'string' || ranAt.length === 0) {
        return null;
    }
    if (typeof gitSha !== 'string' || gitSha.length === 0) {
        return null;
    }
    if (typeof durationMs !== 'number' || !Number.isFinite(durationMs) || durationMs < 0) {
        return null;
    }
    if (typeof restoredRowCount !== 'number' || !Number.isFinite(restoredRowCount) || restoredRowCount < 0) {
        return null;
    }
    if (typeof claim !== 'string' || claim.length === 0) {
        return null;
    }

    return { phase: 'resolved', status, ranAt, gitSha, durationMs, restoredRowCount, claim };
}

/**
 * Compound: the backup & restore drill checklist row. It alone owns the
 * real, read-only fetch of its evidence and renders an honest loading,
 * passed, failed, Unavailable (404, indistinguishable from denied/no-record)
 * or error state — never a fabricated status.
 */
export function BackupRestoreEvidenceItem({ workspaceId }: BackupRestoreEvidenceItemProps) {
    const [state, setState] = useState<EvidenceState>({ phase: 'loading' });

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(evidenceEndpoint(workspaceId));
                if (cancelled) {
                    return;
                }

                if (response.status === 404) {
                    setState({ phase: 'unavailable' });
                    return;
                }
                if (!response.ok) {
                    setState({ phase: 'error' });
                    return;
                }

                const payload = await response.json();
                if (cancelled) {
                    return;
                }

                const parsed = parseEvidence(payload);
                setState(parsed ?? { phase: 'error' });
            } catch {
                if (!cancelled) {
                    setState({ phase: 'error' });
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    let status: ReactNode;
    let details: ReactNode;

    if (state.phase === 'loading') {
        status = (
            <span role="status">
                <Badge status="info">{t('workspace.launchReadiness.backupRestore.status.loading')}</Badge>
            </span>
        );
    } else if (state.phase === 'unavailable') {
        status = (
            <span role="status">
                <Badge status="warning">{t('workspace.launchReadiness.item.status.unavailable')}</Badge>
            </span>
        );
    } else if (state.phase === 'error') {
        status = (
            <span role="alert">
                <Badge status="error">{t('workspace.launchReadiness.backupRestore.status.error')}</Badge>
            </span>
        );
    } else {
        status = (
            <span role="status">
                <Badge status={state.status === 'passed' ? 'success' : 'error'}>
                    {state.status === 'passed'
                        ? t('workspace.launchReadiness.backupRestore.status.passed')
                        : t('workspace.launchReadiness.backupRestore.status.failed')}
                </Badge>
            </span>
        );
        details = (
            <dl className="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.backupRestore.metadata.ranAt')}:{' '}
                    </dt>
                    <dd className="inline">{state.ranAt}</dd>
                </div>
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.backupRestore.metadata.gitSha')}:{' '}
                    </dt>
                    <dd className="inline">{state.gitSha}</dd>
                </div>
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.backupRestore.metadata.durationMs')}:{' '}
                    </dt>
                    <dd className="inline">{state.durationMs} ms</dd>
                </div>
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.backupRestore.metadata.restoredRowCount')}:{' '}
                    </dt>
                    <dd className="inline">{state.restoredRowCount}</dd>
                </div>
                <p>{state.claim}</p>
            </dl>
        );
    }

    return (
        <ReadinessItem
            title={t('workspace.launchReadiness.checklist.backupRestore.title')}
            description={t('workspace.launchReadiness.checklist.backupRestore.description')}
            status={status}
            details={details}
        />
    );
}

export default BackupRestoreEvidenceItem;
