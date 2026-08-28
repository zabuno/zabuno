import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { WorkspaceApp } from './components/workspace/WorkspaceApp';
import { ThemeRoot } from './components/theme/ThemeRoot';
import { AppErrorBoundary } from './components/system/AppErrorBoundary';
import { BuildTruthBanner } from './components/system/BuildTruthBanner';
import { DesktopSidebar } from './components/workspace/chrome/DesktopChrome';

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

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            <BuildTruthBanner />
            <AppErrorBoundary scope="app">
                <WorkspaceApp
                    renderPersistentSidebar={(context) => <DesktopSidebar {...context} />}
                />
            </AppErrorBoundary>
        </ThemeRoot>
    </StrictMode>,
);
