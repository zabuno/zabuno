import { House } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-137).

    Bu beş bölüm `lazy` desenine (FF-97) hiç geçmemişti ve masaüstü girişine
    doğrudan biniyordu. Panel v3 ile ekranlar büyüyünce kapanış 220 KB'a
    çıktı — bütçe 200. Bütçeyi yükseltmek, kullanıcının ekranı açarken
    beklediği süreyi uzatmaktır; büyüyen şey ertelendi.

    Kaydın METADATASI (ad, ikon, sıra, izin) kenar çubuğunu çizmek için hâlâ
    eager; yalnız ÇİZİM ertelenir.
*/
const DashboardPage = lazy(async () => ({
    default: (await import('./DashboardPage')).DashboardPage,
}));
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

export type { DashboardMenuTree } from './DashboardPage';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner. İki katmanlı
            "yükleniyor" yazısı, kullanıcıya bir şeyin takıldığını
            düşündürür.
        */
        <Suspense fallback={null}>
            <DashboardPage
                noviceHome={ctx.features['novice-home'] !== false}
                dashboardMenuTree={ctx.dashboardMenuTree}
                brand={ctx.brand}
                location={ctx.location}
                workspaceId={ctx.workspaceId}
                onNavigateToSection={ctx.onNavigateToSection}
            />
        </Suspense>
    );
}

const dashboardSection: WorkspaceSectionDescriptor = {
    key: 'dashboard',
    path: 'dashboard',
    order: 0,
    labelKey: 'workspace.shell.nav.home',
    icon: <House size={18} weight="regular" />,
    /*
        ANA EKRAN BİR ÖLÇÜM ÖZETİDİR (`docs/109` §6.1-6.2): bugün en çok
        bakılanlar, sayaçlar ve aramadan/görüntülemeden doğan öneriler.
        Hepsi `analytics.view` ile okunur.

        İzin alanı boştu, yani izin listesi ne olursa olsun çiziliyordu.
        Mutfak rolü (`docs/109` §6.4 — "başka bir şey görmez") o boşluğu
        görünür kıldı: ölçüm göremeyen bir aşçı, açtığı ekranda yalnız boş
        kartlar bulurdu. Bugünkü dört rolün (sahip/yönetici/editör ve eski
        `member`) hepsinde `analytics.view` var, yani hiçbiri bu ekranı
        kaybetmez.
    */
    permission: 'analytics.view',
    group: 'primary',
    aiQuickAction: true,
    render,
};

export default dashboardSection;
