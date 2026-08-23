import { t } from '../../../i18n/workspace';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { ReadinessChecklist } from './launch-readiness/ReadinessChecklist';

type LaunchReadinessPageProps = {
    workspaceId: number;
};

/**
 * Real owner-facing Stage 1 exit evidence surface. Tenant isolation
 * resolves from a real, workspace-scoped check; the remaining five
 * canonical items stay an honest, static "Unavailable" until their own
 * checks exist.
 */
export function LaunchReadinessPage({ workspaceId }: LaunchReadinessPageProps) {
    return (
        <div id="security">
            <WorkspacePageFrame
                title={t('workspace.launchReadiness.heading')}
                description={t('workspace.launchReadiness.operational.description')}
            >
                <ReadinessChecklist workspaceId={workspaceId} />
            </WorkspacePageFrame>
        </div>
    );
}

export default LaunchReadinessPage;
