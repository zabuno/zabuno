import { ForkKnife } from '@phosphor-icons/react';
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
const MenuPage = lazy(async () => ({ default: (await import('./MenuPage')).MenuPage }));
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner. İki katmanlı
            "yükleniyor" yazısı, kullanıcıya bir şeyin takıldığını
            düşündürür.
        */
        <Suspense fallback={null}>
            <MenuPage
                workspaceId={ctx.workspaceId}
                catalogPhase={ctx.catalogPhase}
                locationId={ctx.catalogLocationId}
                onTreeChange={ctx.onMenuTreeChange}
                onNavigateToSection={ctx.onNavigateToSection}
                can={ctx.can}
            />
        </Suspense>
    );
}

const menuSection: WorkspaceSectionDescriptor = {
    key: 'menu',
    path: 'menu',
    order: 1,
    labelKey: 'workspace.shell.nav.menus',
    icon: <ForkKnife size={18} weight="regular" />,
    permission: 'menu.view',
    group: 'primary',
    aiQuickAction: true,
    render,
};

export default menuSection;
