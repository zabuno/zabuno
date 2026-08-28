import type { ReactNode } from 'react';
import { SettingsPage, type SettingsTab } from './SettingsPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    // Sekme ADRESTEN okunur, bileşen durumundan değil: `settings/billing`
    // paylaşılabilir, yer imine eklenebilir ve tarayıcı geçmişinde anlamlıdır.
    const activeTab: SettingsTab = ctx.subPath === 'billing' ? 'billing' : 'brand';

    return (
        <SettingsPage
            workspaceId={ctx.workspaceId}
            brand={ctx.brand}
            onSaved={ctx.onBrandSaved}
            activeTab={activeTab}
            onSelectTab={(tab) => ctx.onNavigateToSection(`settings/${tab}`)}
        />
    );
}

const settingsSection: WorkspaceSectionDescriptor = {
    key: 'settings',
    path: 'settings',
    order: 7,
    labelKey: 'workspace.shell.nav.settings',
    group: 'utility',
    render,
};

export default settingsSection;
