import { UserCircle } from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import { ProfilePage } from './ProfilePage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <ProfilePage
            workspaceId={ctx.workspaceId}
            email={ctx.email}
            userName={ctx.userName}
            avatarMediaAssetId={ctx.avatarMediaAssetId}
            avatarUrl={ctx.avatarUrl}
            brand={ctx.brand}
            onBrandSaved={ctx.onBrandSaved}
            canManageBrand={ctx.can('workspace.manage')}
        />
    );
}

const profileSection: WorkspaceSectionDescriptor = {
    key: 'profile',
    path: 'profile',
    order: 11,
    labelKey: 'workspace.profile.title',
    icon: <UserCircle size={18} weight="regular" />,
    /*
        Kenar çubuğunda LİSTELENMEZ: profil kişisel bir ekrandır ve günlük
        operasyonun hedefleri arasında yer tutmaz. Hesap menüsünden açılır —
        kullanıcının kendi adını ve fotoğrafını aradığı yer orasıdır.
    */
    render,
};

export default profileSection;
