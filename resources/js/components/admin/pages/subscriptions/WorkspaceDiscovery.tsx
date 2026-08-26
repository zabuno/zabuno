import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../../i18n/platform';

export type Workspace = {
    id: number;
    name: string;
    slug: string;
    state: string;
};

type WorkspaceDiscoveryStatus = 'loading' | 'error' | 'success';

type WorkspaceDiscoveryProps = {
    selectedWorkspace: Workspace | null;
    onSelect: (workspace: Workspace) => void;
};

const WORKSPACES_ENDPOINT = '/api/admin/workspaces';

const FIELD_CLASSES =
    'w-full min-h-11 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white';

function isValidWorkspace(value: unknown): value is Workspace {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate.id === 'number' &&
        Number.isInteger(candidate.id) &&
        candidate.id > 0 &&
        typeof candidate.name === 'string' &&
        candidate.name.length > 0 &&
        typeof candidate.slug === 'string' &&
        candidate.slug.length > 0 &&
        typeof candidate.state === 'string' &&
        candidate.state.length > 0
    );
}

function isValidWorkspaceList(value: unknown): value is Workspace[] {
    return Array.isArray(value) && value.every(isValidWorkspace);
}

/**
 * Real workspace discovery: GET /api/admin/workspaces?query= on mount,
 * same-origin credentials, honest loading/empty/error/success states —
 * every rendered row is an exact server id/name/slug/state, never invented.
 * A custom accessible combobox/listbox is used (rather than a native
 * <select>) so each option can expose its id/name/slug/state together.
 */
export function WorkspaceDiscovery({ selectedWorkspace, onSelect }: WorkspaceDiscoveryProps) {
    const [status, setStatus] = useState<WorkspaceDiscoveryStatus>('loading');
    const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
    const requestRef = useRef(0);

    const fetchWorkspaces = useCallback(async () => {
        const requestId = ++requestRef.current;
        setStatus('loading');

        try {
            const response = await fetch(`${WORKSPACES_ENDPOINT}?query=`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (requestRef.current !== requestId) {
                return;
            }

            if (!response.ok) {
                setStatus('error');
                return;
            }

            const body: unknown = await response.json();

            if (!isValidWorkspaceList(body)) {
                setStatus('error');
                return;
            }

            setWorkspaces(body);
            setStatus('success');
        } catch {
            if (requestRef.current === requestId) {
                setStatus('error');
            }
        }
    }, []);

    useEffect(() => {
        void (async () => {
            await fetchWorkspaces();
        })();
    }, [fetchWorkspaces]);

    function handleSelect(workspace: Workspace) {
        onSelect(workspace);
    }

    return (
        <div className="flex flex-col gap-3">
            <label
                htmlFor="subscription-workspace-combobox"
                className="flex flex-col gap-1 text-sm text-fg-secondary"
            >
                {t('platform.subscriptions.workspace.label')}
            </label>
            <button
                id="subscription-workspace-combobox"
                type="button"
                role="combobox"
                aria-expanded="true"
                aria-haspopup="listbox"
                aria-controls="subscription-workspace-listbox"
                className={FIELD_CLASSES}
            >
                {selectedWorkspace
                    ? selectedWorkspace.name
                    : t('platform.subscriptions.workspace.placeholder')}
            </button>

            {workspaces.length > 0 && (
                <ul
                    id="subscription-workspace-listbox"
                    role="listbox"
                    aria-label={t('platform.subscriptions.workspace.label')}
                    className="flex flex-col gap-1 rounded-md border border-gray-200 p-1 dark:border-gray-700"
                >
                    {workspaces.map((workspace) => (
                        <li key={workspace.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={selectedWorkspace?.id === workspace.id}
                                onClick={() => handleSelect(workspace)}
                                className="flex w-full flex-wrap gap-2 rounded-md px-2 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                <span className="font-medium text-fg">{workspace.name}</span>
                                <span>{workspace.slug}</span>
                                <span>{workspace.state}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {status === 'loading' && (
                <p role="status" className="text-sm text-fg-muted">
                    {t('platform.subscriptions.workspace.loading')}
                </p>
            )}

            {status === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-sm font-medium text-fg-danger">
                        {t('platform.subscriptions.workspace.error')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-sm font-medium text-fg-danger"
                        onClick={() => void fetchWorkspaces()}
                    >
                        {t('platform.subscriptions.workspace.retry')}
                    </button>
                </div>
            )}

            {status === 'success' && workspaces.length === 0 && (
                <p role="status" className="text-sm text-fg-muted">
                    {t('platform.subscriptions.workspace.empty')}
                </p>
            )}
        </div>
    );
}

export default WorkspaceDiscovery;
