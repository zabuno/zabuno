import { Lifebuoy } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-97 deseni): kayıt eager, çizim tembel.
    Destek nadiren açılır; her gün panosunu açan bir restoran bu ekranın
    kodunu indirmemeli.
*/
const SupportPage = lazy(async () => ({ default: (await import('./SupportPage')).SupportPage }));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <Suspense fallback={null}>
            <SupportPage workspaceId={ctx.workspaceId} email={ctx.email} />
        </Suspense>
    );
}

/**
 * Destek — FF-201 (`docs/125`). Kayıt bu dosyayla olur; `WorkspaceApp.tsx`
 * bilmez (`WorkspaceSectionRegistry` `*.section.tsx` dosyalarını toplar).
 */
const supportSection: WorkspaceSectionDescriptor = {
    key: 'support',
    path: 'support',
    order: 13,
    labelKey: 'workspace.support.title',
    icon: <Lifebuoy size={18} weight="regular" />,
    /*
        YETKİ `workspace.manage` (`docs/125` §4): destek bir yönetim
        kanalıdır (plan, fatura, hesap). Editör ve Mutfak bu bölümü kenar
        çubuğunda GÖRMEZ; uç nokta da 404 döner — burası güvenlik sınırı
        değil, boş ekran göstermeme kuralıdır.
    */
    permission: 'workspace.manage',
    /*
        `utility` GRUBU: Ayarlar'ın yanı — günlük operasyon değil, ara sıra
        açılan bir yer. Ana menüde listelenir çünkü "tıkanırsam kime
        sorarım" sorusunun cevabı aranmadan bulunmalı.
    */
    group: 'utility',
    render,
};

export default supportSection;
