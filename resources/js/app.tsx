import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AppShell } from './components/AppShell';
import { ThemeRoot } from './components/theme/ThemeRoot';

const container = document.getElementById('app');

if (!container) {
    throw new Error('Root mount element #app not found.');
}

const rawCoreModuleCount = container.dataset.coreModuleCount;

if (rawCoreModuleCount === undefined || !/^\d+$/.test(rawCoreModuleCount)) {
    throw new Error(
        `Root mount element #app data-core-module-count is missing or not a non-negative integer (got: ${String(rawCoreModuleCount)}).`,
    );
}

const coreModuleCount = Number.parseInt(rawCoreModuleCount, 10);

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            <AppShell coreModuleCount={coreModuleCount} />
        </ThemeRoot>
    </StrictMode>,
);
