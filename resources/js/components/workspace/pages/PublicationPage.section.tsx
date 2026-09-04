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
const PublicationPage = lazy(async () => ({
    default: (await import('./PublicationPage')).PublicationPage,
}));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner. İki katmanlı
            "yükleniyor" yazısı, kullanıcıya bir şeyin takıldığını
            düşündürür.
        */
        <Suspense fallback={null}>
            <PublicationPage
                workspaceId={ctx.workspaceId}
                dashboardMenuTree={ctx.dashboardMenuTree}
            />
        </Suspense>
    );
}

const publicationSection: WorkspaceSectionDescriptor = {
    key: 'publication',
    path: 'publication',
    order: 10,
    labelKey: 'workspace.shell.nav.publication',
    permission: 'menu.view',
    aiQuickAction: true,
    render,
};

export default publicationSection;
