import { useCallback, useEffect, useLayoutEffect, useRef, useState, type FormEvent } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../lib/csrfHeader';
import { readValidationFailure } from '../../lib/validationErrors';
import { setAnalyticsContext, trackPageView } from '../../lib/analytics';
import { shouldInterceptNavigation } from '../../lib/navigation';
import { t } from '../../i18n/workspace';
import { BrandOnboardingForm } from './BrandOnboardingForm';
import type { BrandProfile } from './BrandEditForm';
import type { LocationProfile } from './LocationEditForm';
import { LocationOnboardingForm } from './LocationOnboardingForm';
import { AdminShell } from '../catalog/layout/macro/AdminShell';
import { AppErrorBoundary } from '../system/AppErrorBoundary';
import type { SidebarNavGroup } from '../catalog/layout/compound/SidebarNav';
import { AiCommandTrigger } from '../catalog/navigation/compound/AiCommandTrigger';
import { AiCommandCenter } from './ai/AiCommandCenter';
import { WorkspaceContextControls } from './shell/WorkspaceContextControls';
import { WorkspaceBreadcrumbs } from './shell/WorkspaceBreadcrumbs';
import type { AiAssistQuickAction } from '../../lib/aiAssistState';
import type { DashboardMenuTree } from './pages/DashboardPage.section';
import type { BrandProfile as SectionBrandProfile } from './BrandEditForm';
import type { LocationProfile as SectionLocationProfile } from './LocationEditForm';
import {
    sectionHref,
    SECTION_DESCRIPTORS,
    resolveSectionKeyFromPath,
    resolveSectionDescriptorForOnboardingPhase,
    renderActiveSection,
} from './shell/WorkspaceSectionRegistry';

export type CatalogPhase =
    'loading' | 'error' | 'brand-onboarding' | 'location-onboarding' | 'menu-catalog';

export type WorkspaceSectionRuntimeContext = {
    workspaceId: number;
    /**
     * Bölümün "henüz yükleniyor" ile "kurulacak bir şey yok" durumlarını
     * ayırt edebilmesi için gerekir. Bu ayrım olmadan bölüm, boş bir
     * çalışma alanında sonsuza kadar yükleniyor görünür.
     */
    catalogPhase: CatalogPhase;
    dashboardMenuTree: DashboardMenuTree | null;
    brand: SectionBrandProfile | null;
    location: SectionLocationProfile | null;
    locationProfiles: SectionLocationProfile[];
    catalogLocationId: number | null;
    onSelectLocation: (locationId: number) => void;
    onLocationSaved: (location: SectionLocationProfile) => void;
    onLocationCreated: (location: SectionLocationProfile) => void;
    onBrandSaved: (brand: SectionBrandProfile) => void;
    onMenuTreeChange: (tree: DashboardMenuTree) => void;
    /** Boş durumdan çıkış yolunu sunabilmek için. */
    onNavigateToSection: (section: string) => void;
};

type WorkspaceUser = { id: number; name: string; email: string };
type Workspace = { id: number; name: string; slug: string; state: string };

type Phase = 'loading' | 'error' | 'create' | 'choose' | 'current';

type WorkspaceSection = string;

function resolveSectionFromPath(pathname: string): WorkspaceSection {
    return resolveSectionKeyFromPath(pathname);
}

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
        resolveSectionFromPath(window.location.pathname),
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

    // Tenant bağlamı API'den gelir. Tek yerde bildiririz: `setCurrentWorkspace`
    // üç ayrı yolda çağrılıyor (yükleme, workspace yaratma, workspace değiştirme)
    // ve üçüne ayrı ayrı ölçüm koymak, dördüncüsü eklendiği gün unutulurdu.
    useEffect(() => {
        if (currentWorkspace === null) {
            return;
        }

        const section = resolveSectionFromPath(window.location.pathname);
        const canonical = sectionHref(currentWorkspace.slug, section);

        // Kullanıcı `/app` adresinden girmiş olabilir; orada hangi restoranın
        // hangi ekranı olduğu YAZMAZ. Adresi kanonik hâline çekeriz.
        //
        // `replaceState`, `pushState` DEĞİL: bu bir gezinti değil, aynı yerin
        // tam adının yazılmasıdır. `pushState` olsaydı geri tuşu kullanıcıyı
        // aynı ekrana geri götürürdü ve panelden çıkamazdı.
        if (window.location.pathname !== canonical) {
            window.history.replaceState({}, '', canonical);
        }

        setAnalyticsContext({
            tenantId: String(currentWorkspace.id),
            tenantSlug: currentWorkspace.slug,
        });

        // Girişin ölçümü burada yapılır, bileşen ilk bindiğinde değil: o an
        // ne tenant bilinir ne de adres kanoniktir. İkisi de bilinmeden
        // gönderilen bir olay, "hangi restoranın hangi ekranı" sorusuna
        // cevap vermez — yani hiç gönderilmemiş gibidir.
        trackPageView(canonical, section);
    }, [currentWorkspace]);

    // Geri/ileri tuşu. Fragment kullanılırken `hashchange` dinleniyordu;
    // gerçek adreslerde karşılığı `popstate`'tir. Tarayıcı geçmişi artık
    // gerçek ekranları taşıyor, dolayısıyla geri tuşu bir önceki EKRANA
    // döner — önceki fragment'e değil.
    useEffect(() => {
        function handlePopState() {
            const key = resolveSectionFromPath(window.location.pathname);
            setActiveSection(key);
            trackPageView(window.location.pathname, key);
        }

        window.addEventListener('popstate', handlePopState);

        return () => {
            window.removeEventListener('popstate', handlePopState);
        };
    }, []);

    /**
     * Bölüm değişiminde sayfayı başa al ve odağı ana bölgeye taşı.
     *
     * Gezinti bağlantıları `#media` gibi hash'ler ve her bölüm aynı id'yi
     * taşıyan bir kapsayıcı render ediyor. Tarayıcı bu durumda O ELEMANA
     * KAYDIRIYOR — yani bir gezinti öğesine tıklamak sayfayı rastgele bir
     * yere sıçratıyordu. Kullanıcının gördüğü şey buydu.
     *
     * Bölüm bir "sayfa"dır; yeni sayfa baştan başlar. Odağın ana bölgeye
     * taşınması da SPA gezinmesinde doğru davranıştır: ekran okuyucu aksi
     * hâlde gezinti listesinde kalır ve içeriğin değiştiğini duyurmaz.
     *
     * İlk render'da odak taşınmaz — sayfaya yeni gelen kullanıcının odağını
     * çalmak, çözdüğümüz sorundan daha rahatsız edicidir.
     */
    const hasNavigatedRef = useRef(false);

    /**
     * Bölüme geçiş. Kaydırma, bölüm DEĞİŞMEDEN ÖNCE sıfırlanır.
     *
     * Sıra burada kritiktir ve ölçümle bulunmuştur. İçerik değiştiğinde
     * belge kısalır (ör. Medya 1940px -> Analitik 842px) ve tarayıcı
     * kaydırmayı zorunlu olarak kırpar: 1000 -> 122. O kırpma BİZİM
     * kodumuzdan önce olur ve kullanıcının gördüğü sıçrama tam olarak
     * budur. Kaydırma zaten 0'dayken içerik değişirse kırpacak bir şey
     * kalmaz.
     */
    function goToSection(key: WorkspaceSection): void {
        window.scrollTo({ top: 0, behavior: 'auto' });

        // Adres bölümle birlikte değişir — tek gezinti girişi burasıdır.
        // Çağıranın ayrıca adres yazması gerekmez; bir yerde unutulursa
        // ekran ile adres ayrışır ve yenilemede başka bir sayfa açılır.
        if (currentWorkspace) {
            const href = sectionHref(currentWorkspace.slug, key);

            if (window.location.pathname !== href) {
                window.history.pushState({}, '', href);
            }

            // `pushState` tarayıcıya göre sayfa DEĞİŞTİRMEZ; GA4 ve Metrica
            // burada kendiliğinden hiçbir şey ölçmez. Bildirmezsek panelde
            // on ekran gezen bir kullanıcı tek sayfalık ziyaret görünür.
            trackPageView(href, key);
        }

        setActiveSection(key);
    }

    // `useLayoutEffect`: DOM değiştikten SONRA ama BOYAMADAN ÖNCE çalışır.
    // Bu yol geri/ileri tuşu ve doğrudan hash değişimi içindir; orada
    // tıklama anında araya girecek bir yer yoktur, dolayısıyla tek çare
    // kırpılmış konumu kullanıcı görmeden düzeltmektir.
    useLayoutEffect(() => {
        // `auto`: her gezinmede yumuşak kaydırma gürültüdür ve azaltılmış
        // hareket tercihini çiğner.
        window.scrollTo({ top: 0, behavior: 'auto' });

        if (!hasNavigatedRef.current) {
            hasNavigatedRef.current = true;

            return;
        }

        // `preventScroll` ZORUNLUDUR. `focus()` varsayılan olarak elemanı
        // görünür alana kaydırır; `main` üst çubuğun altında başladığı için
        // bu, bir satır önce yaptığımız "başa dön"ü geri alır ve sayfayı
        // aşağı fırlatır.
        //
        // Ölçüldü (gerçek tarayıcı, 720px viewport, 2400px içerik):
        //   scrollTo({top: 0})            -> scrollY = 0
        //   ardından focus()              -> scrollY = 1680   ← sıçrama
        //   focus({ preventScroll: true }) -> scrollY = 0
        //
        // jsdom'da `focus()` kaydırmaz, bu yüzden bu hatayı bir birim testi
        // GÖREMEZ; sözleşme "seçeneğin verildiği" üzerinden zorlanır.
        document.getElementById('main-content')?.focus({ preventScroll: true });
    }, [activeSection]);

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

            // Sunucu neyin yanlış olduğunu SÖYLEDİ. Çalışma alanı adı çakıştıysa
            // ya da geçersizse, kullanıcının bunu bilmesi gerekir — bu ekran
            // ürünle ilk temas noktası.
            const failure = await readValidationFailure(
                response,
                t('workspace.create.error.submit'),
            );

            setCreateError(
                failure.fields.name ?? failure.message ?? t('workspace.create.error.submit'),
            );
        } catch {
            // Buraya yalnız istek kurulamadığında düşülür.
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

    function handleBrandCreated(createdBrand: BrandProfile) {
        setBrand(createdBrand);

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
        goToSection(key);
        setAiCommandOpen(false);
    }

    const liveRegion = (
        <div aria-live="polite" className="sr-only">
            {liveMessage}
        </div>
    );

    if (phase === 'loading') {
        return (
            <div className="mx-auto max-w-content px-4 py-10">
                {liveRegion}
                <p role="status" aria-live="polite">
                    {t('workspace.loading')}
                </p>
            </div>
        );
    }

    if (phase === 'error') {
        return (
            <div className="mx-auto max-w-content px-4 py-10">
                {liveRegion}
                <p role="alert" className="text-body font-medium text-fg-danger">
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
            <div className="mx-auto max-w-content px-4 py-10">
                {liveRegion}
                <form onSubmit={handleCreate} noValidate className="flex flex-col gap-4">
                    <h1 className="text-xl font-semibold text-fg">
                        {t('workspace.create.heading')}
                    </h1>

                    {createError && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
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
            <div className="mx-auto max-w-content px-4 py-10">
                {liveRegion}
                <h1 className="text-xl font-semibold text-fg">{t('workspace.choose.heading')}</h1>
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

    const activeOnboardingDescriptor =
        catalogPhase === 'brand-onboarding' || catalogPhase === 'location-onboarding'
            ? resolveSectionDescriptorForOnboardingPhase(catalogPhase)
            : null;

    const showOnboardingForm =
        currentWorkspace !== null &&
        activeOnboardingDescriptor !== null &&
        activeSection === activeOnboardingDescriptor.key;

    const navGroups: SidebarNavGroup[] = [];

    if (currentWorkspace) {
        const catalogItems: SidebarNavGroup['items'] = SECTION_DESCRIPTORS.map((descriptor) => ({
            key: descriptor.key,
            label: t(descriptor.labelKey as Parameters<typeof t>[0]),
            // Gerçek bağlantı: klavye, yeni sekmede açma, orta tık ve
            // "bağlantıyı kopyala" kendiliğinden çalışır. Fragment ile
            // hiçbiri çalışmıyordu.
            href: sectionHref(currentWorkspace.slug, descriptor.key),
            onSelect: (event) => {
                if (!shouldInterceptNavigation(event)) {
                    return;
                }

                event.preventDefault();
                goToSection(descriptor.key);
                setMobileMenuOpen(false);
            },
        }));

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

    const aiQuickActions: AiAssistQuickAction[] = SECTION_DESCRIPTORS.filter(
        (descriptor) => descriptor.aiQuickAction,
    ).map((descriptor) => ({
        key: descriptor.key as AiAssistQuickAction['key'],
        label: t(descriptor.labelKey as Parameters<typeof t>[0]),
    }));

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
                    {/*
                        Oturum sahibinin kimliği. Daha önce her sayfanın
                        tepesine ham metin olarak basılıyordu — slug ve durum
                        koduyla birlikte, etiketsiz. Bilgi gereksiz değildi,
                        YERİ yanlıştı: çok kiracılı bir panelde "hangi
                        hesaptayım?" gerçek bir sorudur ve cevabı içerik
                        alanında değil kimlik alanında durur.
                    */}
                    {user?.email ? (
                        <span
                            /*
                                Kırılma noktası jetonu YOK (docs/06 §12,
                                FluidAdaptiveLayout): düzen 320 pikselden
                                itibaren akışkandır. Dar ekranda yer
                                kalmayınca üst çubuk zaten sarar; metin
                                `max-width` ile kısalır, `sm:` ile
                                gizlenmez.
                            */
                            className="max-w-[18ch] truncate text-meta text-fg-muted"
                            title={user.email}
                        >
                            {user.email}
                        </span>
                    ) : null}
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
                    sectionLabel={t(
                        (SECTION_DESCRIPTORS.find((descriptor) => descriptor.key === activeSection)
                            ?.labelKey ?? 'workspace.shell.nav.dashboard') as Parameters<
                            typeof t
                        >[0],
                    )}
                    locationsHref={sectionHref(currentWorkspace.slug, 'locations')}
                    onSwitchWorkspace={handleSwitch}
                    onSelectLocations={() => goToSection('locations')}
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

            {logoutError && (
                <p role="alert" className="mb-4 text-body font-medium text-fg-danger">
                    {logoutError}
                </p>
            )}

            {/*
                Rota düzeyinde sınır — kök sınırdan AYRI, ve ayrı olması esas.

                Kök sınır tek başına yeterli olsaydı, tek bir ekranın çökmesi
                kenar çubuğunu ve başlığı da götürürdü; kullanıcının elinde
                başka bir ekrana geçme imkânı kalmaz, tek çıkış yol sayfayı
                yenilemek olurdu — o da aynı bozuk ekrana dönmek demektir.

                `resetKey` bölüm anahtarıdır: kullanıcı başka bir bölüme
                geçtiğinde sınır kendini sıfırlar. Bu olmadan React bozuk
                ağacı kalıcı sayar ve hata ekranı sonraki bölümde de kalırdı.
            */}
            {currentWorkspace && !showOnboardingForm && (
                <AppErrorBoundary scope="route" resetKey={activeSection}>
                    {renderActiveSection(activeSection, {
                        workspaceId: currentWorkspace.id,
                        catalogPhase,
                        dashboardMenuTree,
                        brand,
                        location:
                            locationProfiles.find((profile) => profile.id === catalogLocationId) ??
                            null,
                        locationProfiles,
                        catalogLocationId,
                        onSelectLocation: setCatalogLocationId,
                        onLocationSaved: handleLocationSaved,
                        onLocationCreated: handleLocationAdded,
                        onBrandSaved: setBrand,
                        onMenuTreeChange: handleCatalogTreeChange,
                        onNavigateToSection: goToSection,
                    })}
                </AppErrorBoundary>
            )}

            {showOnboardingForm && currentWorkspace && catalogPhase === 'brand-onboarding' && (
                <BrandOnboardingForm
                    workspaceId={currentWorkspace.id}
                    onCreated={handleBrandCreated}
                />
            )}

            {showOnboardingForm && currentWorkspace && catalogPhase === 'location-onboarding' && (
                <LocationOnboardingForm
                    workspaceId={currentWorkspace.id}
                    onCreated={handleLocationCreated}
                />
            )}

            {catalogPhase === 'error' && (
                <div>
                    <p role="alert" className="text-body font-medium text-fg-danger">
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
