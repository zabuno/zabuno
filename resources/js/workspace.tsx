import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { WorkspaceApp } from './components/workspace/WorkspaceApp';
import { ThemeRoot } from './components/theme/ThemeRoot';
import { AppErrorBoundary } from './components/system/AppErrorBoundary';
import { BuildTruthBanner } from './components/system/BuildTruthBanner';

const container = document.getElementById('app');

if (!container) {
    throw new Error('Root mount element #app not found.');
}

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
                <WorkspaceApp />
            </AppErrorBoundary>
        </ThemeRoot>
    </StrictMode>,
);
