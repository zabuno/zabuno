import { useCallback, useRef, useState } from 'react';

import { t } from '../../../i18n/platform';
import { OpsCard } from '../../ops/OpsCard';
import { WorkspaceDiscovery, type Workspace } from './subscriptions/WorkspaceDiscovery';

type TenantLocation = {
    id: number;
    displayName: string;
    city: string;
    countryCode: string;
    timezone: string | null;
};

type TenantMenu = {
    id: number;
    name: string;
    state: string;
    locationId: number | null;
    locationName: string | null;
};

type TenantMember = {
    userId: number;
    name: string;
    email: string;
    role: string;
    since: string | null;
};

type TenantEvent = {
    source: string;
    action: string;
    subject: string | null;
    actor: string | null;
    at: string | null;
};

type TenantDetail = {
    workspace: { id: number; name: string; slug: string; state: string; createdAt: string | null };
    brand: { name: string; slug: string; locale: string; currency: string } | null;
    subscription: {
        state: string;
        plan_code?: string;
        plan_name?: string;
        plan_version?: number;
        ends_at?: string;
    };
    usage: {
        locations: number;
        menus: number;
        products: number;
        mediaAssets: number;
        members: number;
    };
    locations: TenantLocation[];
    menus: TenantMenu[];
    members: TenantMember[];
    listsTruncated: { locations: boolean; menus: boolean; members: boolean };
    recentEvents: TenantEvent[];
};

type State =
    | { phase: 'idle' }
    | { phase: 'loading' }
    | { phase: 'error' }
    | { phase: 'ready'; detail: TenantDetail };

const cellClass = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-top';
const headClass =
    'px-[var(--space-3)] py-[var(--space-2)] text-meta font-bold text-fg-subtle text-start';
const noteClass = 'px-[var(--space-4)] py-[var(--space-3)] text-meta text-fg-muted';

/**
 * Kiracı ayrıntısı — `docs/122` §3 boşluk 1, dalga Y2.
 *
 * Ölçülen durum şuydu: `/platform` bir çalışma alanı listesi çiziyordu ve
 * satıra tıklayınca hiçbir şey olmuyordu. Süperadminin ilk günkü sorusu ise
 * tek satırlık değil — *"kaç şubesi var, hangi menüleri var, aboneliği ne
 * durumda, dün orada ne oldu?"* — ve o gün bu dört soru dört ayrı tabloya
 * elle SQL atmakla cevaplanıyordu.
 *
 * ÇİZİLMEYENLER, ÇİZİLENLER KADAR KASITLIDIR:
 *  - **Kiracı olarak bakma düğmesi yok.** `docs/122` §5 impersonation'ı en
 *    tehlikeli süperadmin yeteneği sayar, Y7'ye bırakır ve *zor* olmasını
 *    şart koşar. Buraya konacak kolay bir düğme o kararı sessizce iptal
 *    ederdi.
 *  - **Hiçbir yazma fiili yok:** askıya alma, silme, plan değiştirme. Bu
 *    ekran bir DESTEK aracıdır; müdahale kendi ekranında, kendi onayıyla
 *    yapılır.
 *  - **Okunamayan veri boş tabloyla değil, hatayla anlatılır.** Boş kartlar
 *    "bu restoranda hiç şube yok" derdi; oysa bilinen tek şey okuyamadığımız.
 *
 * SAYI LİSTEDEN AYRI OKUNUR: `usage` gerçek sayımdır, listeler kırpılabilir
 * ve kırpıldığında bunu kendileri söyler (`docs/109` §8.3).
 */
export function TenantDetailPage() {
    const [selected, setSelected] = useState<Workspace | null>(null);
    const [state, setState] = useState<State>({ phase: 'idle' });
    const requestRef = useRef(0);

    const load = useCallback(async (workspaceId: number) => {
        const requestId = ++requestRef.current;
        setState({ phase: 'loading' });

        try {
            const response = await fetch(`/api/admin/workspaces/${workspaceId}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            // Yarışan istek: hızlı hızlı iki kiracı seçildiğinde geç gelen
            // ilk cevabın ikinci kiracının ekranına yazılması, en tehlikeli
            // türden bir karışıklık olurdu.
            if (requestRef.current !== requestId) return;

            if (!response.ok) {
                setState({ phase: 'error' });

                return;
            }

            const body = (await response.json()) as TenantDetail;

            if (requestRef.current !== requestId) return;

            setState({ phase: 'ready', detail: body });
        } catch {
            if (requestRef.current === requestId) setState({ phase: 'error' });
        }
    }, []);

    function handleSelect(workspace: Workspace) {
        setSelected(workspace);
        void load(workspace.id);
    }

    return (
        <div className="flex flex-col gap-[var(--space-5)]">
            <p className="text-body text-fg-secondary">{t('platform.tenants.intro')}</p>

            <WorkspaceDiscovery selectedWorkspace={selected} onSelect={handleSelect} />

            {state.phase === 'idle' && (
                <p className="text-body text-fg-muted">{t('platform.tenants.idle')}</p>
            )}

            {state.phase === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('platform.tenants.loading')}
                </p>
            )}

            {state.phase === 'error' && (
                <div className="flex flex-col gap-[var(--space-2)]">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('platform.tenants.error')}
                    </p>
                    <button
                        type="button"
                        className="min-h-[var(--density-hit-area-min)] self-start text-body font-medium text-fg-danger"
                        onClick={() => selected && void load(selected.id)}
                    >
                        {t('platform.tenants.retry')}
                    </button>
                </div>
            )}

            {state.phase === 'ready' && <TenantCards detail={state.detail} />}
        </div>
    );
}

function TenantCards({ detail }: { detail: TenantDetail }) {
    const { workspace, brand, subscription, usage, listsTruncated } = detail;

    return (
        <>
            <OpsCard title={t('platform.tenants.identity.title')}>
                <dl className="flex flex-col gap-[var(--space-2)]">
                    <Pair label={t('platform.tenants.identity.slug')} value={workspace.slug} />
                    <Pair label={t('platform.tenants.identity.state')} value={workspace.state} />
                    <Pair
                        label={t('platform.tenants.identity.created')}
                        value={workspace.createdAt}
                    />
                    {/*
                        Markası olmayan bir çalışma alanı vardır (kurulum yarım
                        kalmıştır). Boş bir marka kartı çizmek yerine bunu
                        cümleyle söylemek, yarım kurulumu görünür kılar.
                    */}
                    {brand === null ? (
                        <p className="text-body text-fg-muted">
                            {t('platform.tenants.identity.noBrand')}
                        </p>
                    ) : (
                        <>
                            <Pair label={t('platform.tenants.identity.brand')} value={brand.name} />
                            <Pair
                                label={t('platform.tenants.identity.currency')}
                                value={brand.currency}
                            />
                            <Pair
                                label={t('platform.tenants.identity.locale')}
                                value={brand.locale}
                            />
                        </>
                    )}
                </dl>
            </OpsCard>

            <OpsCard title={t('platform.tenants.subscription.title')}>
                {subscription.state === 'active' ? (
                    <dl className="flex flex-col gap-[var(--space-2)]">
                        <Pair
                            label={t('platform.tenants.subscription.plan')}
                            value={`${subscription.plan_name ?? ''} (${subscription.plan_code ?? ''})`}
                        />
                        <Pair
                            label={t('platform.tenants.subscription.version')}
                            value={
                                subscription.plan_version === undefined
                                    ? null
                                    : String(subscription.plan_version)
                            }
                        />
                        <Pair
                            label={t('platform.tenants.subscription.endsAt')}
                            value={subscription.ends_at ?? null}
                        />
                    </dl>
                ) : (
                    <p className="text-body text-fg-muted">
                        {t('platform.tenants.subscription.none')}
                    </p>
                )}
            </OpsCard>

            <OpsCard title={t('platform.tenants.usage.title')}>
                <dl className="flex flex-col gap-[var(--space-2)]">
                    <Pair
                        label={t('platform.tenants.usage.locations')}
                        value={String(usage.locations)}
                    />
                    <Pair label={t('platform.tenants.usage.menus')} value={String(usage.menus)} />
                    <Pair
                        label={t('platform.tenants.usage.products')}
                        value={String(usage.products)}
                    />
                    <Pair
                        label={t('platform.tenants.usage.mediaAssets')}
                        value={String(usage.mediaAssets)}
                    />
                    <Pair
                        label={t('platform.tenants.usage.members')}
                        value={String(usage.members)}
                    />
                </dl>
            </OpsCard>

            <OpsCard title={t('platform.tenants.locations.title')} padded={false}>
                <Table
                    columns={[
                        t('platform.tenants.col.name'),
                        t('platform.tenants.col.city'),
                        t('platform.tenants.col.country'),
                        t('platform.tenants.col.timezone'),
                    ]}
                    caption={t('platform.tenants.locations.title')}
                    empty={t('platform.tenants.locations.empty')}
                    rows={detail.locations.map((location) => ({
                        key: String(location.id),
                        cells: [
                            location.displayName,
                            location.city,
                            location.countryCode,
                            location.timezone,
                        ],
                    }))}
                    truncated={listsTruncated.locations}
                />
            </OpsCard>

            <OpsCard title={t('platform.tenants.menus.title')} padded={false}>
                <Table
                    columns={[
                        t('platform.tenants.col.name'),
                        t('platform.tenants.col.state'),
                        t('platform.tenants.col.branch'),
                    ]}
                    caption={t('platform.tenants.menus.title')}
                    empty={t('platform.tenants.menus.empty')}
                    rows={detail.menus.map((menu) => ({
                        key: String(menu.id),
                        cells: [menu.name, menu.state, menu.locationName],
                    }))}
                    truncated={listsTruncated.menus}
                />
            </OpsCard>

            <OpsCard title={t('platform.tenants.members.title')} padded={false}>
                <Table
                    columns={[
                        t('platform.tenants.col.name'),
                        t('platform.tenants.col.email'),
                        t('platform.tenants.col.role'),
                        t('platform.tenants.col.since'),
                    ]}
                    caption={t('platform.tenants.members.title')}
                    empty={t('platform.tenants.members.empty')}
                    rows={detail.members.map((member) => ({
                        key: String(member.userId),
                        cells: [member.name, member.email, member.role, member.since],
                    }))}
                    truncated={listsTruncated.members}
                />
            </OpsCard>

            <OpsCard title={t('platform.tenants.events.title')} padded={false}>
                <Table
                    columns={[
                        t('platform.tenants.col.when'),
                        t('platform.tenants.col.source'),
                        t('platform.tenants.col.action'),
                        t('platform.tenants.col.subject'),
                        t('platform.tenants.col.actor'),
                    ]}
                    caption={t('platform.tenants.events.title')}
                    empty={t('platform.tenants.events.empty')}
                    rows={detail.recentEvents.map((event, index) => ({
                        key: `${event.source}-${index}`,
                        cells: [event.at, event.source, event.action, event.subject, event.actor],
                    }))}
                    truncated={false}
                />
            </OpsCard>
        </>
    );
}

function Pair({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex flex-wrap gap-[var(--space-2)]">
            <dt className="text-meta font-bold text-fg-subtle">{label}</dt>
            {/*
                Değeri olmayan alan BOŞ kalır: "—" ya da "bilinmiyor" yazmak,
                ölçülmemiş olanı bir cevap gibi gösterirdi (`docs/109` §8.3).
            */}
            <dd className="text-body text-fg">{value ?? ''}</dd>
        </div>
    );
}

type TableRow = { key: string; cells: (string | null)[] };

function Table({
    columns,
    caption,
    empty,
    rows,
    truncated,
}: {
    columns: string[];
    caption: string;
    empty: string;
    rows: TableRow[];
    truncated: boolean;
}) {
    return (
        <>
            {rows.length === 0 ? (
                <p className="px-[var(--space-4)] py-[var(--space-4)] text-body text-fg-muted">
                    {empty}
                </p>
            ) : (
                // 320 px tabanda geniş tablo SAYFAYI değil KENDİNİ kaydırır.
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse">
                        <caption className="sr-only">{caption}</caption>
                        <thead className="bg-[var(--color-surface-subtle)]">
                            <tr>
                                {columns.map((column) => (
                                    <th key={column} scope="col" className={headClass}>
                                        {column}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.key} className="border-t border-[var(--color-border)]">
                                    {row.cells.map((cell, index) => (
                                        <td key={columns[index]} className={cellClass}>
                                            {cell ?? ''}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {truncated ? <p className={noteClass}>{t('platform.tenants.truncated')}</p> : null}
        </>
    );
}

export default TenantDetailPage;
