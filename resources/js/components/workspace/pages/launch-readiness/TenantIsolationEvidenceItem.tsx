import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { t } from '../../../../i18n/workspace';
import { Badge } from '../../../catalog/feedback/micro/Badge';
import { ReadinessItem } from './ReadinessItem';

type TenantIsolationEvidenceItemProps = {
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
          claim: string;
      };

function evidenceEndpoint(workspaceId: number): string {
    return `/api/workspaces/${workspaceId}/security/evidence/tenant-isolation`;
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
    const claim = record.claim;

    if (typeof ranAt !== 'string' || ranAt.length === 0) {
        return null;
    }
    if (typeof gitSha !== 'string' || gitSha.length === 0) {
        return null;
    }
    if (typeof durationMs !== 'number' || !Number.isFinite(durationMs)) {
        return null;
    }
    if (typeof claim !== 'string' || claim.length === 0) {
        return null;
    }

    return { phase: 'resolved', status, ranAt, gitSha, durationMs, claim };
}

/**
 * Compound: the tenant isolation checklist row. It alone owns the real,
 * read-only fetch of its evidence and renders an honest loading, passed,
 * failed, Unavailable (404, indistinguishable from denied/no-record) or
 * error state — never a fabricated status.
 */
export function TenantIsolationEvidenceItem({ workspaceId }: TenantIsolationEvidenceItemProps) {
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
                <Badge status="info">{t('workspace.launchReadiness.tenantIsolation.status.loading')}</Badge>
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
                <Badge status="error">{t('workspace.launchReadiness.tenantIsolation.status.error')}</Badge>
            </span>
        );
    } else {
        status = (
            <span role="status">
                <Badge status={state.status === 'passed' ? 'success' : 'error'}>
                    {state.status === 'passed'
                        ? t('workspace.launchReadiness.tenantIsolation.status.passed')
                        : t('workspace.launchReadiness.tenantIsolation.status.failed')}
                </Badge>
            </span>
        );
        details = (
            <dl className="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.tenantIsolation.metadata.ranAt')}:{' '}
                    </dt>
                    <dd className="inline">{state.ranAt}</dd>
                </div>
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.tenantIsolation.metadata.gitSha')}:{' '}
                    </dt>
                    <dd className="inline">{state.gitSha}</dd>
                </div>
                <div>
                    <dt className="inline font-medium">
                        {t('workspace.launchReadiness.tenantIsolation.metadata.durationMs')}:{' '}
                    </dt>
                    <dd className="inline">{state.durationMs} ms</dd>
                </div>
                <p>{state.claim}</p>
            </dl>
        );
    }

    return (
        <ReadinessItem
            title={t('workspace.launchReadiness.checklist.tenantIsolation.title')}
            description={t('workspace.launchReadiness.checklist.tenantIsolation.description')}
            status={status}
            details={details}
        />
    );
}

export default TenantIsolationEvidenceItem;
