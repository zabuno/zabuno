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
    /*
        SIRA 14 — Yönetim bloğunun sonu, ve kaydın sonu.

        main'deki sıra okundu: 0-3 her gün gidilen yerler (Pano, Menü,
        Karekod, Analitik), 4-6 ara sıra düzenlenen kayıtlar (Şubeler,
        Medya, Ekip), 7-11 kenar çubuğunda LİSTELENMEYEN ama adresi çalışan
        bölümler (Ayarlar, Marka, Faturalandırma, Yayın, Profil), 12-13
        sonradan eklenen günlük ekranlar (Siparişler, Puanlama).

        13 doluydu (Puanlama) — kayıt defteri çakışmayı gürültüyle kesti.
        Aradaki bir sayıyı almak Puanlama'yı ya da Siparişler'i kaydırmak
        demekti; Siparişler'in sırası bu pakette DEĞİŞMEZ (`docs/122` onu
        ayrı bir eksik olarak ölçüyor: "günlük operasyon, en dipte" — o
        kusur destek paketinde çözülmez).

        Sona koymak `docs/122`'nin şikâyeti DEĞİL, tam tersi: orada kusur,
        GÜNLÜK bir ekranın sona atılmasıydı. Destek günlük değil — sahip
        buraya tıkandığında gelir. Yönetim bloğunun içinde Şubeler, Medya
        ve Ekip'ten sonra çizilir; ileride eklenecek bölümler de var olan
        hiçbir numarayı kaydırmadan yerleşebilir.
    */
    order: 14,
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
        `management` GRUBU — `utility` DEĞİL.

        Önce `utility` yazılmıştı, gerekçesi "Ayarlar'ın yanı"ydı. main'de
        o gerekçe artık YOK: FF-84 (sahibin kararı) Ayarlar'ı kenar
        çubuğundan hesap menüsüne taşıdı ve `utility` grubunu boşalttı —
        `SettingsPage.section.tsx`'in grubu bu yüzden tanımsız. Grup boş
        kaldığı için başlığının İngilizce karşılığı hâlâ "Settings"
        (`workspace.shell.nav.group.utility`). Destek oraya konunca kenar
        çubuğu, sahibin kaldırdığı "Settings" başlığını geri getiriyor ve
        altında tek bir madde gösteriyordu: ekranda yalan bir başlık
        (`WorkspaceApp.shell.test.tsx` bunu ölçüyor).

        Doğru yer Yönetim: `docs/125` §4'ün kendi cümlesi "destek bir
        YÖNETİM kanalıdır (plan, fatura, hesap)" ve izni Şubeler ile
        Ekip'inkiyle aynı (`workspace.manage`). Ayrıca dar ekranda tek
        maddelik bir grup başlığı, hiçbir bilgi vermeden bir satır yüksekliği
        yiyor — 320 px'de en kıt kaynak ekran alanı.
    */
    group: 'management',
    render,
};

export default supportSection;
