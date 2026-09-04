import { Gear } from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import { SettingsPage, type SettingsTab } from './SettingsPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    // Sekme ADRESTEN okunur, bileşen durumundan değil: `settings/billing`
    // paylaşılabilir, yer imine eklenebilir ve tarayıcı geçmişinde anlamlıdır.
    const activeTab: SettingsTab =
        ctx.subPath === 'billing' || ctx.subPath === 'account' || ctx.subPath === 'audit'
            ? (ctx.subPath as SettingsTab)
            : 'brand';

    return (
        <SettingsPage
            workspaceId={ctx.workspaceId}
            brand={ctx.brand}
            onSaved={ctx.onBrandSaved}
            activeTab={activeTab}
            onSelectTab={(tab) => ctx.onNavigateToSection(`settings/${tab}`)}
            userName={ctx.userName}
        />
    );
}

const settingsSection: WorkspaceSectionDescriptor = {
    key: 'settings',
    path: 'settings',
    order: 7,
    labelKey: 'workspace.shell.nav.settings',
    icon: <Gear size={18} weight="regular" />,
    /*
        Kenar çubuğunda LİSTELENMEZ (FF-84, sahibin kararı): ayarlar sistem
        (hesap) menüsünden açılır. Grubu olmayan bölümün adresi çalışmaya
        devam eder — kayıt bu üçüncü hâli zaten tanımlıyor.
    */
    render,
};

export default settingsSection;
