import { MapPin } from '@phosphor-icons/react';
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
const LocationsPage = lazy(async () => ({
    default: (await import('./LocationsPage')).LocationsPage,
}));
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
            <LocationsPage
                workspaceId={ctx.workspaceId}
                locations={ctx.locationProfiles}
                onLocationSaved={ctx.onLocationSaved}
                onLocationCreated={ctx.onLocationCreated}
                /*
                    KAYNAĞIN `goQr` BAĞLAMASI (`panel.dc.html`, "Şubeler").

                    Kartın "Masalar" düğmesi tek bir cümledir: "bu şubenin
                    masaları". Önce bağlam o şubeye geçer — karekod ekranı
                    seçili şubenin masalarını okuyor — sonra ekran değişir.
                    Yalnız gitmek, sahibi başka bir şubenin masalarının önüne
                    bırakırdı.
                */
                onOpenTables={(locationId) => {
                    ctx.onSelectLocation(locationId);
                    ctx.onNavigateToSection('qr-codes');
                }}
                /*
                    Form açık/kapalı durumu ADRESTE durur: `locations/new`.
                    Bileşen içinde tutulsaydı, global "Oluştur" menüsü kullanıcıyı
                    listeye götürür ve tıkladığı şeyi ekranda ayrıca aratırdı.
                */
                addingLocation={ctx.subPath === 'new'}
                onToggleAddLocation={(adding) =>
                    ctx.onNavigateToSection(adding ? 'locations/new' : 'locations')
                }
            />
        </Suspense>
    );
}

const locationsSection: WorkspaceSectionDescriptor = {
    key: 'locations',
    path: 'locations',
    order: 4,
    labelKey: 'workspace.shell.nav.locations',
    icon: <MapPin size={18} weight="regular" />,
    /*
        ŞUBE KAYITLARI YÖNETİLEN KAYITLARDIR. Ekrandaki her yazma —
        şube eklemek, düzenlemek, saatleri değiştirmek — sunucuda
        `workspace.manage` istiyor (`App\Http\Controllers\Tenancy`).

        İzin alanı boştu; yani bu izni olmayan bir kullanıcı ekranı açıyor ve
        bastığı her düğmeden 403 alıyordu — `docs/98` FF-74'ün ("Editor 403
        görmez") tam olarak yasakladığı şey. Alanın doldurulması Mutfak
        rolüyle zorunlu hâle geldi, ama düzelttiği şey ondan eskidir.
    */
    permission: 'workspace.manage',
    group: 'management',
    aiQuickAction: true,
    catalogOnboardingPhase: 'location-onboarding',
    render,
};

export default locationsSection;
