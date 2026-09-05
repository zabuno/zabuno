import { Users } from '@phosphor-icons/react';
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
const TeamPage = lazy(async () => ({ default: (await import('./TeamPage')).TeamPage }));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner. İki katmanlı
            "yükleniyor" yazısı, kullanıcıya bir şeyin takıldığını
            düşündürür.
        */
        <Suspense fallback={null}>
            {/*
                Rol GEÇİRİLİR: bu ekran `workspace.manage` taşıyan herkese
                açıktır (Yönetici dahil), ama ekipten çıkarmak ve sahipliği
                devretmek yalnız Sahibin işidir. Sayfa, yapılamayan işi
                çizmemek için bunu bilmek zorunda (`docs/98` FF-74).
            */}
            <TeamPage workspaceId={ctx.workspaceId} viewerRole={ctx.role} />
        </Suspense>
    );
}

const teamSection: WorkspaceSectionDescriptor = {
    key: 'team',
    path: 'team',
    order: 6,
    labelKey: 'workspace.shell.nav.team',
    icon: <Users size={18} weight="regular" />,
    permission: 'workspace.manage',
    group: 'management',
    render,
};

export default teamSection;
