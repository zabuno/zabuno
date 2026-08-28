import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { WorkspaceApp } from './components/workspace/WorkspaceApp';
import { ThemeRoot } from './components/theme/ThemeRoot';
import { AppErrorBoundary } from './components/system/AppErrorBoundary';
import { BuildTruthBanner } from './components/system/BuildTruthBanner';
import { MobileNavigationDrawer } from './components/workspace/chrome/MobileChrome';

/**
 * MOBİL giriş noktası — 320 px genişlik esas alınarak.
 *
 * Bu dosyanın varlık sebebi, masaüstüne özgü kodu BU pakete hiç
 * koymamaktır. Medya sorgusuyla yapılan uyarlamada telefon, masaüstü
 * düzeninin kodunu da indirir, ayrıştırır ve sonra gizler; 320 pikselde
 * indirilen her fazladan kilobayt kullanıcının beklediği süredir.
 *
 * Hangi paketin yükleneceğine sunucu karar verir
 * (`App\Support\Device\DeviceClass`), tarayıcı değil.
 */
const container = document.getElementById('app');

if (!container) {
    throw new Error('Root mount element #app not found.');
}

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            <BuildTruthBanner />
            <AppErrorBoundary scope="app">
                <WorkspaceApp
                    renderNavigationDrawer={(context) => <MobileNavigationDrawer {...context} />}
                />
            </AppErrorBoundary>
        </ThemeRoot>
    </StrictMode>,
);
