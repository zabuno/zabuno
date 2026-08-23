import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { t } from '../../i18n/workspace';
import { BrandOnboardingForm } from './BrandOnboardingForm';
import type { BrandProfile } from './BrandEditForm';
import type { LocationProfile } from './LocationEditForm';
import { LocationOnboardingForm } from './LocationOnboardingForm';
import { AdminShell } from '../catalog/layout/macro/AdminShell';
import type { SidebarNavGroup } from '../catalog/layout/compound/SidebarNav';
import { AiCommandTrigger } from '../catalog/navigation/compound/AiCommandTrigger';
import { AiCommandCenter } from './ai/AiCommandCenter';
import { WorkspaceContextControls } from './shell/WorkspaceContextControls';
import { WorkspaceBreadcrumbs } from './shell/WorkspaceBreadcrumbs';
import { GlobalUnavailableActions } from './shell/GlobalUnavailableActions';
import type { AiAssistQuickAction } from '../../lib/aiAssistState';
import { DashboardPage, type DashboardMenuTree } from './pages/DashboardPage';
import { BrandPage } from './pages/BrandPage';
import { LocationsPage } from './pages/LocationsPage';
import { MenuPage } from './pages/MenuPage';
import { MediaPage } from './pages/MediaPage';
import { PublicationPage } from './pages/PublicationPage';
import { AnalyticsPage } from './pages/AnalyticsPage';
import { TeamPage } from './pages/TeamPage';
import { BillingPage } from './pages/BillingPage';
import { LaunchReadinessPage } from './pages/LaunchReadinessPage';

type WorkspaceUser = { id: number; name: string; email: string };
type Workspace = { id: number; name: string; slug: string; state: string };

type Phase = 'loading' | 'error' | 'create' | 'choose' | 'current';

type CatalogPhase =
    'loading' | 'error' | 'brand-onboarding' | 'location-onboarding' | 'menu-catalog';

type WorkspaceSection =
    | 'dashboard'
    | 'brand'
    | 'locations'
    | 'menu'
    | 'media'
    | 'publication'
    | 'analytics'
    | 'team'
    | 'billing'
    | 'security';

const SECTION_BY_HASH: Record<string, WorkspaceSection> = {
    '#dashboard': 'dashboard',
    '#brand': 'brand',
    '#locations': 'locations',
    '#menu': 'menu',
    '#media': 'media',
    '#publication': 'publication',
    '#analytics': 'analytics',
    '#team': 'team',
    '#billing': 'billing',
    '#security': 'security',
};

function resolveSectionFromHash(hash: string): WorkspaceSection {
    return SECTION_BY_HASH[hash] ?? 'dashboard';
}

const SECTION_LABEL_KEY: Record<WorkspaceSection, Parameters<typeof t>[0]> = {
    dashboard: 'workspace.shell.nav.dashboard',
    brand: 'workspace.shell.nav.brand',
    locations: 'workspace.shell.nav.locations',
    menu: 'workspace.shell.nav.menu',
    media: 'workspace.shell.nav.media',
    publication: 'workspace.shell.nav.publication',
    analytics: 'workspace.shell.nav.analytics',
    team: 'workspace.shell.nav.team',
    billing: 'workspace.shell.nav.billing',
    security: 'workspace.shell.nav.launchReadiness',
};

export function WorkspaceApp() {
    const [phase, setPhase] = useState<Phase>('loading');
    const [user, setUser] = useState<WorkspaceUser | null>(null);
    const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
    const [currentWorkspace, setCurrentWorkspace] = useState<Workspace | null>(null);
    const [liveMessage, setLiveMessage] = useState('');

    const [createName, setCreateName] = useState('');
    const [createError, setCreateError] = useState('');
    const [creating, setCreating] = useState(false);

    const [loggingOut, setLoggingOut] = useState(false);
    const [logoutError, setLogoutError] = useState('');

    const [catalogPhase, setCatalogPhase] = useState<CatalogPhase>('loading');
    const [catalogLocationId, setCatalogLocationId] = useState<number | null>(null);

    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [aiCommandOpen, setAiCommandOpen] = useState(false);
    const [activeSection, setActiveSection] = useState<WorkspaceSection>(() =>
        resolveSectionFromHash(window.location.hash),
    );

    const [dashboardMenuTree, setDashboardMenuTree] = useState<DashboardMenuTree | null>(null);

    const [brand, setBrand] = useState<BrandProfile | null>(null);
    const [locationProfiles, setLocationProfiles] = useState<LocationProfile[]>([]);

    const primedMenuTreeKeyRef = useRef<string | null>(null);

    const fetchMenuTree = useCallback(async (workspaceId: number, locationId: number) => {
        try {
            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand/locations/${locationId}/menu`,
            );

            if (!response.ok) {
                return null;
            }

            return (await response.json()) as DashboardMenuTree;
        } catch {
            return null;
        }
    }, []);

    const fetchCatalogSnapshot = useCallback(
        async (workspaceId: number) => {
            try {
                const response = await fetch(`/api/workspaces/${workspaceId}/brand`);

                if (response.status === 404) {
                    return {
                        catalogPhase: 'brand-onboarding' as const,
                        brand: null,
                        locationProfiles: [] as LocationProfile[],
                        catalogLocationId: null,
                        dashboardMenuTree: null,
                    };
                }

                if (!response.ok) {
                    return {
                        catalogPhase: 'error' as const,
                        brand: null,
                        locationProfiles: [] as LocationProfile[],
                        catalogLocationId: null,
                        dashboardMenuTree: null,
                    };
                }

                const nextBrand = (await response.json()) as BrandProfile;

                const locationsResponse = await fetch(
                    `/api/workspaces/${workspaceId}/brand/locations`,
                );

                if (!locationsResponse.ok) {
                    return {
                        catalogPhase: 'error' as const,
                        brand: nextBrand,
                        locationProfiles: [] as LocationProfile[],
                        catalogLocationId: null,
                        dashboardMenuTree: null,
                    };
                }

                const locations = (await locationsResponse.json()) as LocationProfile[];

                if (locations.length === 0) {
                    return {
                        catalogPhase: 'location-onboarding' as const,
                        brand: nextBrand,
                        locationProfiles: [] as LocationProfile[],
                        catalogLocationId: null,
                        dashboardMenuTree: null,
                    };
                }

                const firstLocationId = locations[0].id;
                const dashboardMenuTree = await fetchMenuTree(workspaceId, firstLocationId);

                return {
                    catalogPhase: 'menu-catalog' as const,
                    brand: nextBrand,
                    locationProfiles: locations,
                    catalogLocationId: firstLocationId,
                    dashboardMenuTree,
                };
            } catch {
                return {
                    catalogPhase: 'error' as const,
                    brand: null,
                    locationProfiles: [] as LocationProfile[],
                    catalogLocationId: null,
                    dashboardMenuTree: null,
                };
            }
        },
        [fetchMenuTree],
    );

    const applyCatalogSnapshot = useCallback(
        (workspaceId: number, snapshot: Awaited<ReturnType<typeof fetchCatalogSnapshot>>) => {
            setCatalogPhase(snapshot.catalogPhase);
            setBrand(snapshot.brand);
            setLocationProfiles(snapshot.locationProfiles);
            setCatalogLocationId(snapshot.catalogLocationId);
            setDashboardMenuTree(snapshot.dashboardMenuTree);

            primedMenuTreeKeyRef.current =
                snapshot.catalogPhase === 'menu-catalog' && snapshot.catalogLocationId !== null
                    ? `${workspaceId}:${snapshot.catalogLocationId}`
                    : null;
        },
        [],
    );

    const load = useCallback(async () => {
        try {
            const [userResponse, workspacesResponse, contextResponse] = await Promise.all([
                fetch('/api/user'),
                fetch('/api/workspaces'),
                fetch('/api/workspace-context'),
            ]);

            const nextUser = (await userResponse.json()) as WorkspaceUser;
            const nextWorkspaces = (await workspacesResponse.json()) as Workspace[];

            if (contextResponse.ok) {
                const context = (await contextResponse.json()) as Workspace;
                const snapshot = await fetchCatalogSnapshot(context.id);

                setUser(nextUser);
                setWorkspaces(nextWorkspaces);
                setCurrentWorkspace(context);
                applyCatalogSnapshot(context.id, snapshot);
                setPhase('current');
                setLiveMessage(`${context.name} is now the current workspace.`);

                return;
            }

            setUser(nextUser);
            setWorkspaces(nextWorkspaces);

            if (nextWorkspaces.length === 0) {
                setPhase('create');

                return;
            }

            setPhase('choose');
        } catch {
            setPhase('error');
        }
    }, [applyCatalogSnapshot, fetchCatalogSnapshot]);

    useEffect(() => {
        let cancelled = false;

        queueMicrotask(() => {
            if (cancelled) {
                return;
            }

            void load();
        });

        return () => {
            cancelled = true;
        };
    }, [load]);

    useEffect(() => {
        function handleHashChange() {
            setActiveSection(resolveSectionFromHash(window.location.hash));
        }

        window.addEventListener('hashchange', handleHashChange);

        return () => {
            window.removeEventListener('hashchange', handleHashChange);
        };
    }, []);

    async function handleCreate(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const name = createName.trim();

        if (name === '') {
            setCreateError(t('workspace.create.error.name'));

            return;
        }

        setCreateError('');
        setCreating(true);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                '/api/workspaces',
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name }),
                }),
            );

            if (response.ok) {
                const created = (await response.json()) as Workspace;
                const snapshot = await fetchCatalogSnapshot(created.id);

                setCurrentWorkspace(created);
                setWorkspaces((current) => [...current, created]);
                applyCatalogSnapshot(created.id, snapshot);
                setPhase('current');
                setLiveMessage(`${created.name} is now the current workspace.`);
                setCreating(false);

                return;
            }

            setCreateError(t('workspace.create.error.submit'));
        } catch {
            setCreateError(t('workspace.create.error.submit'));
        }

        setCreating(false);
    }

    async function handleChoose(workspace: Workspace) {
        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                '/api/workspace-context',
                buildAuthRequestInit({
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ workspace_id: workspace.id }),
                }),
            );

            if (response.ok) {
                const context = (await response.json()) as Workspace;
                const snapshot = await fetchCatalogSnapshot(context.id);

                setCurrentWorkspace(context);
                applyCatalogSnapshot(context.id, snapshot);
                setPhase('current');
                setLiveMessage(`${context.name} is now the current workspace.`);
            }
        } catch {
            // Chooser stays on screen; the user can retry the selection.
        }
    }

    const loadLocations = useCallback(async (workspaceId: number, isCancelled: () => boolean) => {
        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/brand/locations`);

            if (isCancelled()) {
                return;
            }

            if (!response.ok) {
                setCatalogPhase('error');

                return;
            }

            const locations = (await response.json()) as LocationProfile[];

            setLocationProfiles(locations);

            if (locations.length === 0) {
                setCatalogPhase('location-onboarding');

                return;
            }

            setCatalogLocationId(locations[0].id);
            setCatalogPhase('menu-catalog');
        } catch {
            if (!isCancelled()) {
                setCatalogPhase('error');
            }
        }
    }, []);

    const loadCatalog = useCallback(
        async (workspaceId: number, isCancelled: () => boolean) => {
            setCatalogPhase('loading');
            setCatalogLocationId(null);
            setBrand(null);
            setLocationProfiles([]);

            const snapshot = await fetchCatalogSnapshot(workspaceId);

            if (isCancelled()) {
                return;
            }

            applyCatalogSnapshot(workspaceId, snapshot);
        },
        [applyCatalogSnapshot, fetchCatalogSnapshot],
    );

    useEffect(() => {
        if (!currentWorkspace || catalogPhase !== 'menu-catalog' || catalogLocationId === null) {
            return;
        }

        const key = `${currentWorkspace.id}:${catalogLocationId}`;

        if (primedMenuTreeKeyRef.current === key) {
            primedMenuTreeKeyRef.current = null;

            return;
        }

        let cancelled = false;

        void (async () => {
            setDashboardMenuTree(null);

            const tree = await fetchMenuTree(currentWorkspace.id, catalogLocationId);

            if (!cancelled) {
                setDashboardMenuTree(tree);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [currentWorkspace, catalogPhase, catalogLocationId, fetchMenuTree]);

    const handleCatalogTreeChange = useCallback(
        (tree: DashboardMenuTree) => {
            if (tree.locationId !== catalogLocationId) {
                return;
            }
            setDashboardMenuTree(tree);
        },
        [catalogLocationId],
    );

    function handleBrandCreated() {
        if (currentWorkspace) {
            void loadLocations(currentWorkspace.id, () => false);
        }
    }

    function handleLocationSaved(updated: LocationProfile) {
        setLocationProfiles((current) =>
            current.map((location) => (location.id === updated.id ? updated : location)),
        );
    }

    function handleLocationCreated(location: LocationProfile) {
        setLocationProfiles([location]);
        setCatalogLocationId(location.id);
        setCatalogPhase('menu-catalog');
    }

    function handleLocationAdded(location: LocationProfile) {
        setLocationProfiles((current) => [...current, location]);
        setCatalogLocationId(location.id);
    }

    async function handleLogout() {
        setMobileMenuOpen(false);
        setLoggingOut(true);
        setLogoutError('');

        try {
            await bootstrapCsrfCookie();

            const response = await fetch('/logout', buildAuthRequestInit({ method: 'POST' }));

            if (response.ok) {
                window.location.assign('/login');

                return;
            }

            setLogoutError(t('workspace.current.logout.error'));
        } catch {
            setLogoutError(t('workspace.current.logout.error'));
        }

        setLoggingOut(false);
    }

    function handleSwitch() {
        setMobileMenuOpen(false);
        setPhase('choose');
    }

    function handleAiQuickAction(key: AiAssistQuickAction['key']) {
        setActiveSection(key);
        window.location.hash = `#${key}`;
        setAiCommandOpen(false);
    }

    const liveRegion = (
        <div aria-live="polite" className="sr-only">
            {liveMessage}
        </div>
    );

    if (phase === 'loading') {
        return (
            <div className="mx-auto max-w-2xl px-4 py-10">
                {liveRegion}
                <p role="status" aria-live="polite">
                    {t('workspace.loading')}
                </p>
            </div>
        );
    }

    if (phase === 'error') {
        return (
            <div className="mx-auto max-w-2xl px-4 py-10">
                {liveRegion}
                <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                    {t('workspace.error.heading')}
                </p>
                <Button
                    onClick={() => {
                        setPhase('loading');
                        void load();
                    }}
                    className="mt-4"
                >
                    {t('workspace.error.retry')}
                </Button>
            </div>
        );
    }

    if (phase === 'create') {
        return (
            <div className="mx-auto max-w-2xl px-4 py-10">
                {liveRegion}
                <form onSubmit={handleCreate} noValidate className="flex flex-col gap-4">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                        {t('workspace.create.heading')}
                    </h1>

                    {createError && (
                        <p
                            role="alert"
                            className="text-sm font-medium text-red-600 dark:text-red-400"
                        >
                            {createError}
                        </p>
                    )}

                    <div>
                        <div className="mb-2 block">
                            <Label htmlFor="workspace-name">{t('workspace.create.name')}</Label>
                        </div>
                        <TextInput
                            id="workspace-name"
                            name="name"
                            type="text"
                            className="w-full"
                            value={createName}
                            onChange={(event) => setCreateName(event.target.value)}
                        />
                    </div>

                    <Button type="submit" disabled={creating} className="w-full">
                        {t('workspace.create.submit')}
                    </Button>
                </form>
            </div>
        );
    }

    if (phase === 'choose') {
        return (
            <div className="mx-auto max-w-2xl px-4 py-10">
                {liveRegion}
                <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                    {t('workspace.choose.heading')}
                </h1>
                <ul className="mt-4 flex flex-col gap-2">
                    {workspaces.map((workspace) => (
                        <li key={workspace.id}>
                            <Button onClick={() => void handleChoose(workspace)} className="w-full">
                                {workspace.name}
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        );
    }

    const navGroups: SidebarNavGroup[] = [];

    if (currentWorkspace) {
        const catalogItems: SidebarNavGroup['items'] = [
            {
                key: 'dashboard',
                label: t('workspace.shell.nav.dashboard'),
                href: '#dashboard',
                onSelect: () => {
                    setActiveSection('dashboard');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'brand',
                label: t('workspace.shell.nav.brand'),
                href: '#brand',
                onSelect: () => {
                    setActiveSection('brand');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'locations',
                label: t('workspace.shell.nav.locations'),
                href: '#locations',
                onSelect: () => {
                    setActiveSection('locations');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'menu',
                label: t('workspace.shell.nav.menu'),
                href: '#menu',
                onSelect: () => {
                    setActiveSection('menu');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'media',
                label: t('workspace.shell.nav.media'),
                href: '#media',
                onSelect: () => {
                    setActiveSection('media');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'publication',
                label: t('workspace.shell.nav.publication'),
                href: '#publication',
                onSelect: () => {
                    setActiveSection('publication');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'analytics',
                label: t('workspace.shell.nav.analytics'),
                href: '#analytics',
                onSelect: () => {
                    setActiveSection('analytics');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'team',
                label: t('workspace.shell.nav.team'),
                href: '#team',
                onSelect: () => {
                    setActiveSection('team');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'billing',
                label: t('workspace.shell.nav.billing'),
                href: '#billing',
                onSelect: () => {
                    setActiveSection('billing');
                    setMobileMenuOpen(false);
                },
            },
            {
                key: 'security',
                label: t('workspace.shell.nav.launchReadiness'),
                href: '#security',
                onSelect: () => {
                    setActiveSection('security');
                    setMobileMenuOpen(false);
                },
            },
        ];

        navGroups.push({ key: 'catalog', items: catalogItems });
    }

    navGroups.push({
        key: 'account',
        label: t('workspace.shell.nav.group'),
        items: [
            {
                key: 'switch-workspace',
                label: t('workspace.current.switch'),
                onSelect: handleSwitch,
            },
            {
                key: 'logout',
                label: t('workspace.current.logout'),
                onSelect: () => void handleLogout(),
                disabled: loggingOut,
            },
        ],
    });

    const aiQuickActions: AiAssistQuickAction[] = [
        { key: 'dashboard', label: t('workspace.shell.nav.dashboard') },
        { key: 'locations', label: t('workspace.shell.nav.locations') },
        { key: 'menu', label: t('workspace.shell.nav.menu') },
        { key: 'publication', label: t('workspace.shell.nav.publication') },
    ];

    return (
        <AdminShell
            brand={{ name: t('workspace.shell.brand') }}
            navGroups={navGroups}
            activeNavKey={currentWorkspace ? activeSection : undefined}
            navLabel={t('workspace.shell.nav.label')}
            mobileMenuOpen={mobileMenuOpen}
            onToggleMobileMenu={() => setMobileMenuOpen((open) => !open)}
            onCloseMobileMenu={() => setMobileMenuOpen(false)}
            topBarCenter={
                currentWorkspace && (
                    <WorkspaceContextControls
                        workspaceName={currentWorkspace.name}
                        locationProfiles={locationProfiles}
                        catalogLocationId={catalogLocationId}
                        onSwitchWorkspace={handleSwitch}
                        onSelectLocation={setCatalogLocationId}
                    />
                )
            }
            topBarEnd={
                <div className="flex items-center gap-1">
                    <GlobalUnavailableActions />
                    <AiCommandTrigger
                        label={t('workspace.aiCommand.trigger.label')}
                        onClick={() => setAiCommandOpen(true)}
                    />
                </div>
            }
        >
            {liveRegion}

            {currentWorkspace && (
                <WorkspaceBreadcrumbs
                    workspaceName={currentWorkspace.name}
                    locationDisplayName={
                        locationProfiles.find((profile) => profile.id === catalogLocationId)
                            ?.display_name ?? null
                    }
                    sectionLabel={t(SECTION_LABEL_KEY[activeSection])}
                    onSwitchWorkspace={handleSwitch}
                    onSelectLocations={() => setActiveSection('locations')}
                />
            )}

            {currentWorkspace && (
                <AiCommandCenter
                    open={aiCommandOpen}
                    onClose={() => setAiCommandOpen(false)}
                    workspaceName={currentWorkspace.name}
                    locationDisplayName={
                        locationProfiles.find((profile) => profile.id === catalogLocationId)
                            ?.display_name ?? null
                    }
                    quickActions={aiQuickActions}
                    onSelectQuickAction={handleAiQuickAction}
                />
            )}

            <div className="mb-6 flex flex-col gap-2">
                <p className="text-sm text-gray-700 dark:text-gray-300">{currentWorkspace?.slug}</p>
                <p className="text-sm text-gray-700 dark:text-gray-300">
                    {currentWorkspace?.state}
                </p>
                <p className="text-sm text-gray-700 dark:text-gray-300">{user?.email}</p>
            </div>

            {logoutError && (
                <p role="alert" className="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
                    {logoutError}
                </p>
            )}

            {currentWorkspace && activeSection === 'dashboard' && (
                <DashboardPage
                    dashboardMenuTree={dashboardMenuTree}
                    brand={brand}
                    location={
                        locationProfiles.find((profile) => profile.id === catalogLocationId) ?? null
                    }
                />
            )}

            {currentWorkspace && activeSection === 'brand' && (
                <BrandPage workspaceId={currentWorkspace.id} brand={brand} onSaved={setBrand} />
            )}

            {currentWorkspace && activeSection === 'locations' && (
                <LocationsPage
                    workspaceId={currentWorkspace.id}
                    locations={locationProfiles}
                    selectedLocationId={catalogLocationId}
                    onSelectLocation={setCatalogLocationId}
                    onLocationSaved={handleLocationSaved}
                    onLocationCreated={handleLocationAdded}
                />
            )}

            {currentWorkspace && activeSection === 'menu' && (
                <MenuPage
                    workspaceId={currentWorkspace.id}
                    locationId={catalogLocationId}
                    onTreeChange={handleCatalogTreeChange}
                />
            )}

            {currentWorkspace && activeSection === 'media' && (
                <MediaPage workspaceId={currentWorkspace.id} />
            )}

            {currentWorkspace && activeSection === 'publication' && (
                <PublicationPage
                    workspaceId={currentWorkspace.id}
                    dashboardMenuTree={dashboardMenuTree}
                />
            )}

            {currentWorkspace && activeSection === 'analytics' && (
                <AnalyticsPage
                    workspaceId={currentWorkspace.id}
                    locationId={catalogLocationId ?? undefined}
                />
            )}

            {currentWorkspace && activeSection === 'team' && <TeamPage workspaceId={currentWorkspace.id} />}

            {currentWorkspace && activeSection === 'billing' && <BillingPage workspaceId={currentWorkspace.id} />}

            {currentWorkspace && activeSection === 'security' && (
                <LaunchReadinessPage workspaceId={currentWorkspace.id} />
            )}

            {currentWorkspace && catalogPhase === 'brand-onboarding' && (
                <BrandOnboardingForm
                    workspaceId={currentWorkspace.id}
                    onCreated={handleBrandCreated}
                />
            )}

            {currentWorkspace && catalogPhase === 'location-onboarding' && (
                <LocationOnboardingForm
                    workspaceId={currentWorkspace.id}
                    onCreated={handleLocationCreated}
                />
            )}

            {catalogPhase === 'error' && (
                <div>
                    <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                        {t('workspace.catalog.error.heading')}
                    </p>
                    <Button
                        onClick={() =>
                            currentWorkspace && void loadCatalog(currentWorkspace.id, () => false)
                        }
                        className="mt-4"
                    >
                        {t('workspace.catalog.error.retry')}
                    </Button>
                </div>
            )}
        </AdminShell>
    );
}

export default WorkspaceApp;
