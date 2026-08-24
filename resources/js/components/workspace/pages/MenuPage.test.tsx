import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';

/**
 * Feature-local acceptance for MenuPage: the no-location honest loading
 * state (locationId === null) and the location-present delegation to
 * MenuCatalogWorkspace with the exact workspaceId/locationId contract.
 * MenuCatalogWorkspace itself is mocked here — its CRUD/price/allergen/
 * visibility contracts are frozen separately in
 * catalog/menu/macro/MenuCatalogWorkspace.test.tsx.
 */

vi.mock('../../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

vi.mock('../ai/AiAssistPanel', () => ({
    AiAssistPanel: ({ context }: { context: string }) => (
        <div data-testid="ai-assist-panel" data-context={context} />
    ),
}));

import { MenuPage } from './MenuPage';

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

describe('MenuPage', () => {
    it('shows an honest loading status and does not mount the catalog workspace when no location is selected yet', () => {
        render(<MenuPage workspaceId={WORKSPACE_ID} locationId={null} onTreeChange={vi.fn()} />);

        expect(screen.getByRole('status')).toBeInTheDocument();
        expect(screen.queryByTestId('menu-catalog-workspace')).not.toBeInTheDocument();
    });

    it('renders the menu catalog workspace with the exact workspaceId/locationId once a location is selected', () => {
        render(
            <MenuPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} onTreeChange={vi.fn()} />,
        );

        const menuRoot = document.querySelector('#menu') as HTMLElement;
        const workspace = within(menuRoot).getByTestId('menu-catalog-workspace');
        expect(workspace).toHaveAttribute('data-workspace-id', String(WORKSPACE_ID));
        expect(workspace).toHaveAttribute('data-location-id', String(LOCATION_ID));
    });

    it('does not swap in the loading status once a location and its catalog workspace are rendered', () => {
        render(
            <MenuPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} onTreeChange={vi.fn()} />,
        );

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
