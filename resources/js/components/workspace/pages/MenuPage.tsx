import { t } from '../../../i18n/workspace';
import { MenuCatalogWorkspace } from '../../catalog/menu/macro/MenuCatalogWorkspace';
import type { DashboardMenuTree } from './DashboardPage';
import { AiAssistPanel } from '../ai/AiAssistPanel';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

type MenuPageProps = {
    workspaceId: number;
    locationId: number | null;
    onTreeChange: (tree: DashboardMenuTree) => void;
};

export function MenuPage({ workspaceId, locationId, onTreeChange }: MenuPageProps) {
    const badges: WorkspacePageStatusBadge[] =
        locationId !== null
            ? [{ key: 'menu-location', status: 'success', label: `#${locationId}` }]
            : [];

    return (
        <div id="menu">
            <WorkspacePageFrame
                title={t('workspace.shell.nav.menu')}
                description={t('workspace.menu.operational.description')}
                badges={badges}
            >
                {locationId !== null ? (
                    <MenuCatalogWorkspace
                        workspaceId={workspaceId}
                        locationId={locationId}
                        onTreeChange={onTreeChange}
                    />
                ) : (
                    <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                        {t('workspace.menu.loading')}
                    </p>
                )}

                <AiAssistPanel context={t('workspace.shell.nav.menu')} />
            </WorkspacePageFrame>
        </div>
    );
}

export default MenuPage;
