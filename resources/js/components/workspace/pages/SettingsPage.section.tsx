import { Gear } from '@phosphor-icons/react';
import { lazy, Suspense, type ReactNode } from 'react';
import { type SettingsTab } from './SettingsPage';

/*
    EKRAN İSTENDİĞİNDE İNER (FF-137).

    Bu beş bölüm `lazy` desenine (FF-97) hiç geçmemişti ve masaüstü girişine
    doğrudan biniyordu. Panel v3 ile ekranlar büyüyünce kapanış 220 KB'a
    çıktı — bütçe 200. Bütçeyi yükseltmek, kullanıcının ekranı açarken
    beklediği süreyi uzatmaktır; büyüyen şey ertelendi.

    Kaydın METADATASI (ad, ikon, sıra, izin) kenar çubuğunu çizmek için hâlâ
    eager; yalnız ÇİZİM ertelenir.
*/
const SettingsPage = lazy(async () => ({ default: (await import('./SettingsPage')).SettingsPage }));
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    // Sekme ADRESTEN okunur, bileşen durumundan değil: `settings/billing`
    // paylaşılabilir, yer imine eklenebilir ve tarayıcı geçmişinde anlamlıdır.
    /*
        `account` ARTIK BİR SEKME DEĞİL (docs/109): kişisel ad/şifre Profil
        ekranına taşındı, sekmenin yerini `workspace` aldı. Eski adres
        (`settings/account`) tanınmadığı için Marka'ya düşer — kırık bir
        sayfa yerine ekranın ilk sekmesi açılır.
    */
    const activeTab: SettingsTab =
        ctx.subPath === 'billing' || ctx.subPath === 'workspace' || ctx.subPath === 'audit'
            ? (ctx.subPath as SettingsTab)
            : 'brand';

    return (
        /*
            Bekleme metni YOK: ekran zaten kendi yükleme durumunu
            anlatır ve parça milisaniyeler içinde iner.
        */
        <Suspense fallback={null}>
            <SettingsPage
                workspaceId={ctx.workspaceId}
                brand={ctx.brand}
                onSaved={ctx.onBrandSaved}
                activeTab={activeTab}
                onSelectTab={(tab) => ctx.onNavigateToSection(`settings/${tab}`)}
                onNavigateToMedia={() => ctx.onNavigateToSection('media')}
            />
        </Suspense>
    );
}

const settingsSection: WorkspaceSectionDescriptor = {
    key: 'settings',
    path: 'settings',
    order: 7,
    labelKey: 'workspace.shell.nav.settings',
    icon: <Gear size={18} weight="regular" />,
    /*
        Kenar çubuğunda LİSTELENMEZ (FF-84, sahibin kararı): ayarlar sistem
        (hesap) menüsünden açılır. Grubu olmayan bölümün adresi çalışmaya
        devam eder — kayıt bu üçüncü hâli zaten tanımlıyor.
    */
    render,
};

export default settingsSection;
