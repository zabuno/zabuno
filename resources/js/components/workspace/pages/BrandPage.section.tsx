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
const BrandPage = lazy(async () => ({ default: (await import('./BrandPage')).BrandPage }));
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
            <BrandPage
                workspaceId={ctx.workspaceId}
                brand={ctx.brand}
                onSaved={ctx.onBrandSaved}
                onNavigateToMedia={() => ctx.onNavigateToSection('media')}
            />
        </Suspense>
    );
}

const brandSection: WorkspaceSectionDescriptor = {
    key: 'brand',
    path: 'brand',
    order: 8,
    labelKey: 'workspace.shell.nav.brand',
    permission: 'workspace.manage',
    catalogOnboardingPhase: 'brand-onboarding',
    render,
};

export default brandSection;
