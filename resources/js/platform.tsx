import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { PlatformApp } from './components/platform/PlatformApp';
import { ThemeRoot } from './components/theme/ThemeRoot';

const container = document.getElementById('platform-admin-app');

if (!container) {
    throw new Error('Root mount element #platform-admin-app not found.');
}

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            <PlatformApp />
        </ThemeRoot>
    </StrictMode>,
);
