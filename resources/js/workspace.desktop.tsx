import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { readyForRender } from './i18n/mount';
import { WorkspaceApp } from './components/workspace/WorkspaceApp';
import { desktopInspectors } from './components/workspace/inspectors/desktopInspectors';
import { ThemeRoot } from './components/theme/ThemeRoot';
import { AppErrorBoundary } from './components/system/AppErrorBoundary';
import { BuildTruthBanner } from './components/system/BuildTruthBanner';
import { DesktopSidebar } from './components/workspace/chrome/DesktopChrome';
import { desktopKitchenSurface } from './components/workspace/kitchen/desktopKitchen';

/**
 * MASAÜSTÜ giriş noktası.
 *
 * Mobil paketten farkı, masaüstüne özgü kabuk parçalarını (bağlam paneli ve
 * onunla gelen her şey) İÇERMESİDİR. Telefon bu kodu hiç indirmez — adaptive
 * yükleme ile responsive uyarlamanın farkı tam olarak budur.
 *
 * Hangi paketin yükleneceğine sunucu karar verir
 * (`App\Support\Device\DeviceClass`), tarayıcı değil.
 */
const container = document.getElementById('app');

if (!container) {
    throw new Error('Root mount element #app not found.');
}

/*
    Çeviri tablosu İNDİRİLMEDEN çizilmez (FF-94): önce İngilizce, sonra
    Türkçe bir ekran göstermek, dili hiç bilmemekten daha kötü görünür.
*/
void readyForRender().then(() => {
    createRoot(container).render(
        <StrictMode>
            <ThemeRoot>
                <BuildTruthBanner />
                <AppErrorBoundary scope="app">
                    <WorkspaceApp
                        renderPersistentSidebar={(context) => <DesktopSidebar {...context} />}
                        /*
                            Bağlam paneli YALNIZ masaüstünde. 336 piksellik kalıcı
                            bir ray, 320 piksel genişliğinde bir ekranda zaten yer
                            bulamaz — ve temel görev ona bağımlı değildir
                            (docs/50 §3.4, docs/60).
                        */
                        inspectors={desktopInspectors}
                        /*
                            MUTFAK MONİTÖRÜ YALNIZ BURADA (`docs/115` S5,
                            `docs/54`). Duvara asılan bir ekran için yazıldı:
                            uzaktan okunur tipografi ve tam ekran kipi.
                            Telefon paketi bu kodu hiç indirmez ve orada
                            ekran nedenini söyleyen bir cümle gösterir.
                        */
                        renderKitchenMonitor={desktopKitchenSurface}
                    />
                </AppErrorBoundary>
            </ThemeRoot>
        </StrictMode>,
    );
});
