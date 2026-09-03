import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { EngineeringApp } from './components/engineering/EngineeringApp';
import { ThemeRoot } from './components/theme/ThemeRoot';
import { AppErrorBoundary } from './components/system/AppErrorBoundary';
import { BuildTruthBanner } from './components/system/BuildTruthBanner';

const container = document.getElementById('engineering-app');

if (!container) {
    throw new Error('Root mount element #engineering-app not found.');
}

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            {/* Şerit hata sınırının DIŞINDA — çökünce de görünmeli (`docs/52`). */}
            <BuildTruthBanner />
            <AppErrorBoundary scope="app">
                <EngineeringApp />
            </AppErrorBoundary>
        </ThemeRoot>
    </StrictMode>,
);
