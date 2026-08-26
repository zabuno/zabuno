import { useEffect, useState } from 'react';

import { t } from '../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../lib/csrfHeader';
import { AiAssistPanel } from '../ai/AiAssistPanel';
import type { DashboardMenuTree } from './DashboardPage';
import { DraftMenuPreviewRegion } from './publication/DraftMenuPreviewRegion';
import {
    PublicationStatusRegion,
    type CurrentPublication,
} from './publication/PublicationStatusRegion';
import { PublishActionConfigRegion } from './publication/PublishActionConfigRegion';
import {
    isDraftReady,
    PublishReadinessChecklistRegion,
} from './publication/PublishReadinessChecklistRegion';
import { PublishedSnapshotRegion } from './publication/PublishedSnapshotRegion';
import { QrDestinationRegion } from './publication/QrDestinationRegion';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

type PublicationPageProps = {
    workspaceId?: number;
    dashboardMenuTree?: DashboardMenuTree | null;
};

export function PublicationPage({ workspaceId, dashboardMenuTree = null }: PublicationPageProps) {
    const menuId = dashboardMenuTree?.id ?? null;
    const locationId = dashboardMenuTree?.locationId ?? null;

    const [current, setCurrent] = useState<CurrentPublication | null>(null);
    const [loading, setLoading] = useState(workspaceId !== undefined && menuId !== null);
    const [loadError, setLoadError] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [retryToken, setRetryToken] = useState(0);

    useEffect(() => {
        if (workspaceId === undefined || menuId === null) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/menu/${menuId}/publications/current`,
                    {
                        credentials: 'include',
                        headers: { Accept: 'application/json' },
                    },
                );

                if (cancelled) return;

                if (response.ok) {
                    setCurrent((await response.json()) as CurrentPublication);
                    setLoadError(false);
                } else if (response.status === 404) {
                    setCurrent(null);
                    setLoadError(false);
                } else {
                    setLoadError(true);
                }
            } catch {
                if (!cancelled) setLoadError(true);
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, menuId, retryToken]);

    function handleRetry() {
        setLoading(true);
        setLoadError(false);
        setRetryToken((token) => token + 1);
    }

    const checklistReady = isDraftReady(dashboardMenuTree);

    const badges: WorkspacePageStatusBadge[] = [
        checklistReady
            ? {
                  key: 'publication-readiness',
                  status: 'success',
                  label: t('workspace.publication.readiness.ready'),
              }
            : {
                  key: 'publication-readiness',
                  status: 'warning',
                  label: t('workspace.publication.readiness.needsAttention'),
              },
        current !== null
            ? { key: 'publication-status', status: 'success', label: `#${current.id}` }
            : {
                  key: 'publication-status',
                  status: 'info',
                  label: t('workspace.publication.status.notPublished'),
              },
    ];

    const [prevChecklistReady, setPrevChecklistReady] = useState(checklistReady);
    if (checklistReady !== prevChecklistReady) {
        setPrevChecklistReady(checklistReady);
        if (!checklistReady) {
            setConfirmed(false);
        }
    }

    async function handlePublish() {
        if (workspaceId === undefined || menuId === null) return;

        setPublishing(true);
        setErrorMessage(null);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/menu/${menuId}/publications`,
                buildAuthRequestInit({ method: 'POST' }),
            );

            if (response.ok) {
                setCurrent((await response.json()) as CurrentPublication);
                setLoadError(false);
                setConfirmed(false);
            } else {
                setErrorMessage(t('workspace.publication.status.publishError'));
            }
        } catch {
            setErrorMessage(t('workspace.publication.status.publishError'));
        } finally {
            setPublishing(false);
        }
    }

    return (
        <div id="section-publication">
            <WorkspacePageFrame
                title={t('workspace.shell.nav.publication')}
                description={t('workspace.publication.operational.description')}
                badges={badges}
            >
                <DraftMenuPreviewRegion dashboardMenuTree={dashboardMenuTree} />
                <PublishReadinessChecklistRegion dashboardMenuTree={dashboardMenuTree} />
                <PublicationStatusRegion
                    current={current}
                    loading={loading}
                    loadError={loadError}
                    onRetry={handleRetry}
                    checklistReady={checklistReady}
                    confirmed={confirmed}
                    onConfirmedChange={setConfirmed}
                    onPublish={handlePublish}
                    publishing={publishing}
                    errorMessage={errorMessage}
                />
                {current !== null ? <PublishedSnapshotRegion current={current} /> : null}
                <PublishActionConfigRegion />
                {workspaceId !== undefined && locationId !== null && menuId !== null ? (
                    <QrDestinationRegion
                        workspaceId={workspaceId}
                        locationId={locationId}
                        menuId={menuId}
                        hasCurrentPublication={current !== null}
                    />
                ) : null}

                <AiAssistPanel context={t('workspace.shell.nav.publication')} />
            </WorkspacePageFrame>
        </div>
    );
}

export default PublicationPage;
