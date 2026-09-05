import { Star } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-97): kaydın metadatası (ad, ikon, sıra, izin)
    kenar çubuğunu çizmek için eager kalır, yalnız ÇİZİM ertelenir.
*/
const RatingsPage = lazy(async () => ({
    default: (await import('./RatingsPage')).RatingsPage,
}));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <Suspense fallback={null}>
            <RatingsPage
                workspaceId={ctx.workspaceId}
                /*
                    Puanlar bir MENÜNÜN satırlarına dayanır (`docs/116` P5:
                    okuma adresi menüye dayanır, yanıt adresi ürüne). Panom ile
                    aynı ağacı okumak, iki ekranın aynı menüden bahsetmesini
                    garanti eder.
                */
                menuTree={ctx.dashboardMenuTree}
                can={ctx.can}
                onNavigateToSection={ctx.onNavigateToSection}
            />
        </Suspense>
    );
}

const ratingsSection: WorkspaceSectionDescriptor = {
    key: 'ratings',
    /*
        GERÇEK BİR ADRES, FRAGMENT DEĞİL (`docs/38` §4): `/app/{w}/ratings`
        paylaşılabilir, ölçülebilir ve tarayıcı geçmişinde anlamlıdır.
    */
    path: 'ratings',
    order: 13,
    labelKey: 'workspace.shell.nav.ratings',
    icon: <Star size={18} weight="regular" />,
    permission: 'rating.view',
    group: 'primary',
    render,
};

export default ratingsSection;
