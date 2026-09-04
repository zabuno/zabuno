import { UserCircle } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-97).

    Bölüm kayıtları eskiden sayfayı doğrudan içeri alıyordu; yani her gün
    panosunu açan bir restoran, hiç girmediği bu ekranın kodunu da
    indiriyordu. Kaydın METADATASI (ad, ikon, sıra, izin) kenar çubuğunu
    çizmek için hâlâ eager; yalnız ÇİZİM ertelenir.
*/
const ProfilePage = lazy(async () => ({ default: (await import('./ProfilePage')).ProfilePage }));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner. İki katmanlı
            "yükleniyor" yazısı, kullanıcıya bir şeyin takıldığını
            düşündürür.
        */
        <Suspense fallback={null}>
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
        </Suspense>
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
