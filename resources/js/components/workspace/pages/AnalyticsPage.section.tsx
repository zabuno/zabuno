import { ChartBar } from '@phosphor-icons/react';
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
const AnalyticsPage = lazy(async () => ({
    default: (await import('./AnalyticsPage')).AnalyticsPage,
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
            <AnalyticsPage
                workspaceId={ctx.workspaceId}
                locationId={ctx.catalogLocationId ?? undefined}
                onNavigateToSection={ctx.onNavigateToSection}
                // Boşluğun sebebini ayırt edebilmesi için (docs/66).
                menuTree={ctx.dashboardMenuTree}
            />
        </Suspense>
    );
}

const analyticsSection: WorkspaceSectionDescriptor = {
    key: 'analytics',
    path: 'analytics',
    order: 3,
    labelKey: 'workspace.shell.nav.insights',
    icon: <ChartBar size={18} weight="regular" />,
    permission: 'analytics.view',
    group: 'primary',
    render,
};

export default analyticsSection;
