import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { readyForRender } from './i18n/mount';
import { PlatformApp } from './components/platform/PlatformApp';
import { ThemeRoot } from './components/theme/ThemeRoot';
import { AppErrorBoundary } from './components/system/AppErrorBoundary';
import { BuildTruthBanner } from './components/system/BuildTruthBanner';

const container = document.getElementById('platform-admin-app');

if (!container) {
    throw new Error('Root mount element #platform-admin-app not found.');
}

/*
    Çeviri tablosu İNDİRİLMEDEN çizilmez (FF-94): önce İngilizce, sonra
    Türkçe bir ekran göstermek, dili hiç bilmemekten daha kötü görünür.
*/
void readyForRender().then(() => {
    createRoot(container).render(
        <StrictMode>
            <ThemeRoot>
                {/*
                    Şerit hata sınırının DIŞINDA duruyor, ve bilerek: uygulama
                    çöktüğünde de görünmesi gerekir. Yanlış sürümün çalışıyor
                    olması, çökmenin sebebi olabilir — tam da o an susan bir
                    uyarı, en çok ihtiyaç duyulduğu anda kaybolurdu.
                */}
                <BuildTruthBanner />
                <AppErrorBoundary scope="app">
                    <PlatformApp />
                </AppErrorBoundary>
            </ThemeRoot>
        </StrictMode>,
    );
});
