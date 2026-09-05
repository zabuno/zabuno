import { Image } from '@phosphor-icons/react';
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
const MediaPage = lazy(async () => ({ default: (await import('./MediaPage')).MediaPage }));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner. İki katmanlı
            "yükleniyor" yazısı, kullanıcıya bir şeyin takıldığını
            düşündürür.
        */
        <Suspense fallback={null}>
            <MediaPage workspaceId={ctx.workspaceId} />
        </Suspense>
    );
}

const mediaSection: WorkspaceSectionDescriptor = {
    key: 'media',
    path: 'media',
    order: 5,
    labelKey: 'workspace.shell.nav.media',
    icon: <Image size={18} weight="regular" />,
    /*
        KÜTÜPHANE BİR ÇALIŞMA EKRANIDIR: yükle, dönüştür, taşı, sil, toplu
        işlem. Hepsi `media.manage` ister.

        İzin alanı boştu ve Mutfak rolü (`docs/109` §6.4) bunu görünür kıldı:
        fotoğraf yükleyemeyen bir aşçının kütüphaneyi hedef olarak görmesi,
        yapamayacağı bir işe davet olurdu. Eski salt okunur `member` rolü de
        bu izne sahip değil ve o da aynı sebeple bu hedefi görmez —
        listelemek hâlâ mümkün, YÖNETMEK değil.
    */
    permission: 'media.manage',
    group: 'management',
    render,
};

export default mediaSection;
