import { QrCode } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-122; desen FF-97'den).

    Bu bölüm kaydı sayfayı doğrudan içeri alıyordu ve masa kartı sihirbazı
    eklendiğinde masaüstü paketinin JS kapanışı bütçeyi 0,16 KB aştı
    (`DS-BUNDLE-BUDGET-07`). Bütçeyi yükseltmek, her gün panosunu açan bir
    restorana hiç girmediği bir ekranın kodunu indirtmek olurdu; sınırın işi
    tam olarak bunu söylemek.

    Kaydın METADATASI (ad, ikon, sıra, izin) kenar çubuğunu çizmek için hâlâ
    eager; yalnız ÇİZİM ertelenir.
*/
const QrCodesPage = lazy(async () => ({
    default: (await import('./QrCodesPage')).QrCodesPage,
}));

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu anlatır ve
            parça milisaniyeler içinde iner. İki katmanlı "yükleniyor" yazısı,
            kullanıcıya bir şeyin takıldığını düşündürür.
        */
        <Suspense fallback={null}>
            <QrCodesPage
                workspaceId={ctx.workspaceId}
                dashboardMenuTree={ctx.dashboardMenuTree}
                onNavigateToSection={ctx.onNavigateToSection}
                /*
                    Markanın rengi QR ekranına ULAŞIR (FF-112): "markalı" tema
                    ve masa kartı bu rengi kullanıyor ve ekran, renk
                    taranamayacak kadar açıksa bunu indirme öncesinde söylüyor.
                */
                brandPrimaryColor={ctx.brand?.primary_color ?? null}
            />
        </Suspense>
    );
}

const qrCodesSection: WorkspaceSectionDescriptor = {
    key: 'qr-codes',
    path: 'qr-codes',
    order: 2,
    labelKey: 'workspace.shell.nav.qrCodes',
    icon: <QrCode size={18} weight="regular" />,
    permission: 'qr.view',
    group: 'primary',
    render,
};

export default qrCodesSection;
