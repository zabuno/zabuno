import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { WorkspaceApp } from './components/workspace/WorkspaceApp';
import { ThemeRoot } from './components/theme/ThemeRoot';

const container = document.getElementById('app');

if (!container) {
    throw new Error('Root mount element #app not found.');
}

createRoot(container).render(
    <StrictMode>
        <ThemeRoot>
            <WorkspaceApp />
        </ThemeRoot>
    </StrictMode>,
);
