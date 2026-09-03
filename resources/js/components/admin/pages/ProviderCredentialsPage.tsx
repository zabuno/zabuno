import { useCallback, useEffect, useMemo, useState } from 'react';

import { t } from '../../../i18n/platform';

/**
 * Superadmin sağlayıcı kasası paneli — `docs/94` (Faz 4) + `docs/95` Faz 3.
 *
 * Sır ASLA geri gelmez: API her sır alan için yalnız `••••son4` maskesi
 * verir. Bir sır alanı boş bırakmak "değiştirme" demektir — mevcut değeri
 * korur; kullanıcı sırrı yeniden girmek zorunda değildir çünkü panel onu
 * zaten okuyamaz. Düz alanlar (domain, endpoint) tam değeriyle görünür.
 *
 * FAZ 3'TE GÖRÜNÜM DEĞİŞTİ: artık "her sağlayıcı bir kart" değil,
 * **sağlayıcı → altında N bağlantı kartı**. Sebebi kozmetik değil — aynı
 * sağlayıcının iki hesabı olabilir (`docs/96` Faz 3: toplu içe aktarma
 * paylaşılan kotayı korumak için AYRI bir OpenAI hesabında çalışır) ve düz
 * liste bunu gösteremezdi.
 */

const ENDPOINT = '/api/admin/connections';
const CSRF_ENDPOINT = '/sanctum/csrf-cookie';

type FieldSchema = {
    name: string;
    secret: boolean;
    required: boolean;
    default: string | null;
};

type ProviderSchema = {
    provider: string;
    fields: FieldSchema[];
};

type FieldStatus = {
    name: string;
    secret: boolean;
    isSet: boolean;
    preview: string | null;
};

type Connection = {
    id: number;
    provider: string;
    label: string;
    scope: string;
    workspaceId: number | null;
    configured: boolean;
    state: string;
    health: string;
    lastRotatedAt: string | null;
    lastHealthCheckAt: string | null;
    fields: FieldStatus[];
};

type Payload = {
    providers: ProviderSchema[];
    connections: Connection[];
};

function isFieldStatus(value: unknown): value is FieldStatus {
    if (typeof value !== 'object' || value === null) return false;
    const c = value as Record<string, unknown>;

    return (
        typeof c.name === 'string' &&
        typeof c.secret === 'boolean' &&
        typeof c.isSet === 'boolean' &&
        (c.preview === null || typeof c.preview === 'string')
    );
}

function isConnection(value: unknown): value is Connection {
    if (typeof value !== 'object' || value === null) return false;
    const c = value as Record<string, unknown>;

    return (
        typeof c.id === 'number' &&
        typeof c.provider === 'string' &&
        typeof c.label === 'string' &&
        typeof c.scope === 'string' &&
        typeof c.configured === 'boolean' &&
        typeof c.state === 'string' &&
        typeof c.health === 'string' &&
        Array.isArray(c.fields) &&
        c.fields.every(isFieldStatus)
    );
}

function isPayload(value: unknown): value is Payload {
    if (typeof value !== 'object' || value === null) return false;
    const c = value as Record<string, unknown>;

    return (
        Array.isArray(c.providers) &&
        c.providers.every(
            (entry) =>
                typeof entry === 'object' &&
                entry !== null &&
                typeof (entry as Record<string, unknown>).provider === 'string' &&
                Array.isArray((entry as Record<string, unknown>).fields),
        ) &&
        Array.isArray(c.connections) &&
        c.connections.every(isConnection)
    );
}

function readXsrfCookie(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function bootstrapCsrfCookie(): Promise<void> {
    const response = await fetch(CSRF_ENDPOINT, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('CSRF bootstrap failed');
    }
}

function mutationInit(method: string, body: unknown): RequestInit {
    const headers = new Headers({ Accept: 'application/json', 'Content-Type': 'application/json' });
    const xsrfToken = readXsrfCookie();
    if (xsrfToken) {
        headers.set('X-XSRF-TOKEN', xsrfToken);
    }

    return { method, credentials: 'same-origin', headers, body: JSON.stringify(body) };
}

function providerLabel(provider: string): string {
    return t(`platform.credentials.provider.${provider}` as never);
}

function fieldLabel(name: string): string {
    return t(`platform.credentials.field.${name}` as never);
}

/** Kart başına düzenleme taslağı: bağlantı kimliği → alan → değer. */
type DraftMap = Record<number, Record<string, string>>;

function draftFor(connection: Connection): Record<string, string> {
    const draft: Record<string, string> = {};
    for (const field of connection.fields) {
        // Sır alan BOŞ başlar (maske bir değer değil, bir izdir); düz alan
        // mevcut değeriyle dolu gelir.
        draft[field.name] = field.secret ? '' : (field.preview ?? '');
    }

    return draft;
}

const inputClass =
    'min-h-[44px] w-full rounded-[var(--radius-sm)] border border-[var(--color-border)] px-3';
const primaryButtonClass =
    'min-h-[44px] rounded-[var(--radius-sm)] bg-[var(--color-accent)] px-4 text-[var(--color-on-accent)]';
const secondaryButtonClass =
    'min-h-[44px] rounded-[var(--radius-sm)] border border-[var(--color-border)] px-4';

export function ProviderCredentialsPage() {
    const [payload, setPayload] = useState<Payload | null>(null);
    const [drafts, setDrafts] = useState<DraftMap>({});
    const [loadError, setLoadError] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [savedId, setSavedId] = useState<number | null>(null);
    const [saveError, setSaveError] = useState<number | null>(null);
    /** Bağlantı kimliği → son yoklamanın sonucu (`reachable`/`rejected`/`unsupported`). */
    const [probeOutcome, setProbeOutcome] = useState<Record<number, string | null>>({});

    /*
        EKLEME FORMU — `docs/95` Faz 3 UX sözleşmesi.

        Sağlayıcı SEÇİLENE KADAR ortak alanlar (etiket, kapsam) gerçek
        `disabled` özniteliğiyle kapalı durur; yalnız görsel bir soluklaştırma
        değil, çünkü ekran okuyucu da "devre dışı" demeli. Gerekçe: hangi
        sağlayıcıya ait olduğu belirsiz bir etiket anlamsızdır.
    */
    const [addOpen, setAddOpen] = useState(false);
    const [newProvider, setNewProvider] = useState('');
    const [newLabel, setNewLabel] = useState('');
    const [newScope, setNewScope] = useState('platform_owned');
    const [newWorkspaceId, setNewWorkspaceId] = useState('');
    const [newFields, setNewFields] = useState<Record<string, string>>({});
    const [creating, setCreating] = useState(false);
    const [createError, setCreateError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoadError(false);
        try {
            const response = await fetch(ENDPOINT, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error('load failed');
            }
            const data: unknown = await response.json();
            if (!isPayload(data)) {
                throw new Error('shape');
            }

            setPayload(data);

            const next: DraftMap = {};
            for (const connection of data.connections) {
                next[connection.id] = draftFor(connection);
            }
            setDrafts(next);
        } catch {
            setLoadError(true);
        }
    }, []);

    useEffect(() => {
        void (async () => {
            await load();
        })();
    }, [load]);

    const setField = useCallback((id: number, field: string, value: string) => {
        setDrafts((prev) => ({ ...prev, [id]: { ...prev[id], [field]: value } }));
    }, []);

    const save = useCallback(
        async (connection: Connection) => {
            setBusyId(connection.id);
            setSaveError(null);
            setSavedId(null);
            try {
                await bootstrapCsrfCookie();

                const draft = drafts[connection.id] ?? {};
                const fields: Record<string, string> = {};
                for (const field of connection.fields) {
                    const value = draft[field.name] ?? '';
                    // Boş sır GÖNDERİLMEZ: öncekini korumak için.
                    if (field.secret && value === '') continue;
                    fields[field.name] = value;
                }

                const response = await fetch(
                    `${ENDPOINT}/${connection.id}`,
                    mutationInit('PUT', { fields }),
                );
                if (!response.ok) {
                    throw new Error('save failed');
                }
                setSavedId(connection.id);
                await load();
            } catch {
                setSaveError(connection.id);
            } finally {
                setBusyId(null);
            }
        },
        [drafts, load],
    );

    /*
        UYUMLULUK YOKLAMASI — `docs/95` Faz 3.

        Superadmin bugüne kadar anahtarı kaydedip "kaydedildi" görüyor, ama
        yanlış olduğunu ancak ilk MÜŞTERİ isteğinde öğreniyordu. Bu düğme o
        soruyu şimdi, tek ve ücretsiz bir çağrıyla yanıtlar.
    */
    const probe = useCallback(
        async (id: number) => {
            setBusyId(id);
            setProbeOutcome((prev) => ({ ...prev, [id]: null }));
            try {
                await bootstrapCsrfCookie();
                const response = await fetch(`${ENDPOINT}/${id}/probe`, mutationInit('POST', {}));
                if (!response.ok) {
                    throw new Error('probe failed');
                }
                const body: unknown = await response.json();
                const outcome =
                    typeof body === 'object' &&
                    body !== null &&
                    typeof (body as { probe?: { outcome?: unknown } }).probe?.outcome === 'string'
                        ? String((body as { probe: { outcome: string } }).probe.outcome)
                        : 'rejected';

                setProbeOutcome((prev) => ({ ...prev, [id]: outcome }));
                await load();
            } catch {
                setSaveError(id);
            } finally {
                setBusyId(null);
            }
        },
        [load],
    );

    const setState = useCallback(
        async (id: number, state: 'disable' | 'enable') => {
            setBusyId(id);
            setSaveError(null);
            try {
                await bootstrapCsrfCookie();
                const response = await fetch(
                    `${ENDPOINT}/${id}/${state}`,
                    mutationInit('POST', {}),
                );
                if (!response.ok) {
                    throw new Error('state failed');
                }
                await load();
            } catch {
                setSaveError(id);
            } finally {
                setBusyId(null);
            }
        },
        [load],
    );

    const selectedSchema = useMemo(
        () => payload?.providers.find((entry) => entry.provider === newProvider) ?? null,
        [payload, newProvider],
    );

    const resetAddForm = useCallback(() => {
        setNewProvider('');
        setNewLabel('');
        setNewScope('platform_owned');
        setNewWorkspaceId('');
        setNewFields({});
        setCreateError(null);
    }, []);

    const create = useCallback(async () => {
        setCreating(true);
        setCreateError(null);
        try {
            await bootstrapCsrfCookie();

            const body: Record<string, unknown> = {
                provider: newProvider,
                label: newLabel,
                scope: newScope,
                fields: newFields,
            };
            if (newScope === 'tenant_byok' && newWorkspaceId !== '') {
                body.workspaceId = Number(newWorkspaceId);
            }

            const response = await fetch(ENDPOINT, mutationInit('POST', body));
            if (!response.ok) {
                const problem: unknown = await response.json().catch(() => null);
                const message =
                    typeof problem === 'object' && problem !== null && 'message' in problem
                        ? String((problem as { message: unknown }).message)
                        : t('platform.connections.create.error');
                throw new Error(message);
            }

            resetAddForm();
            setAddOpen(false);
            await load();
        } catch (error) {
            setCreateError(
                error instanceof Error ? error.message : t('platform.connections.create.error'),
            );
        } finally {
            setCreating(false);
        }
    }, [newProvider, newLabel, newScope, newWorkspaceId, newFields, load, resetAddForm]);

    const heading = useMemo(() => t('platform.credentials.region.label'), []);

    if (loadError) {
        return (
            <section aria-labelledby="credentials-heading">
                <h1 id="credentials-heading">{heading}</h1>
                <p role="alert">{t('platform.credentials.error')}</p>
                <button type="button" onClick={() => void load()}>
                    {t('platform.credentials.retry')}
                </button>
            </section>
        );
    }

    if (payload === null) {
        return (
            <section aria-labelledby="credentials-heading">
                <h1 id="credentials-heading">{heading}</h1>
                <p>{t('platform.credentials.loading')}</p>
            </section>
        );
    }

    // Sağlayıcı → bağlantıları. Sıra `providers` dizisinden gelir ki panel
    // her açılışta aynı düzende görünsün.
    const grouped = payload.providers.map((schema) => ({
        schema,
        connections: payload.connections.filter((c) => c.provider === schema.provider),
    }));

    return (
        <section aria-labelledby="credentials-heading">
            <h1 id="credentials-heading">{heading}</h1>
            <p>{t('platform.credentials.intro')}</p>

            {/* Panel genelinde TEK ekleme düğmesi — sağlayıcı başına değil. */}
            <div className="mt-[var(--space-fluid-md)]">
                <button
                    type="button"
                    className={primaryButtonClass}
                    onClick={() => {
                        if (addOpen) resetAddForm();
                        setAddOpen(!addOpen);
                    }}
                >
                    {addOpen ? t('platform.connections.add.cancel') : t('platform.connections.add')}
                </button>
            </div>

            {addOpen ? (
                <section
                    aria-labelledby="new-connection-heading"
                    className="mt-[var(--space-fluid-md)] rounded-[var(--radius-md)] border border-[var(--color-border)] p-[var(--space-fluid-md)]"
                >
                    <h2 id="new-connection-heading">{t('platform.connections.add.heading')}</h2>

                    <div className="mt-[var(--space-fluid-sm)]">
                        <label htmlFor="new-connection-provider" className="block">
                            {t('platform.connections.provider.label')}
                        </label>
                        <select
                            id="new-connection-provider"
                            className={inputClass}
                            value={newProvider}
                            onChange={(event) => {
                                setNewProvider(event.target.value);
                                setNewFields({});
                            }}
                        >
                            <option value="">{t('platform.connections.provider.choose')}</option>
                            {payload.providers.map((entry) => (
                                <option key={entry.provider} value={entry.provider}>
                                    {providerLabel(entry.provider)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="mt-[var(--space-fluid-sm)]">
                        <label htmlFor="new-connection-label" className="block">
                            {t('platform.connections.label.label')}
                        </label>
                        <input
                            id="new-connection-label"
                            type="text"
                            className={inputClass}
                            /*
                                Sağlayıcı seçilmeden bu alan anlamsızdır:
                                hangi sağlayıcıya ait bir etiket olduğu
                                belirsiz olurdu. GERÇEK `disabled` —
                                ekran okuyucu da "devre dışı" desin.
                            */
                            disabled={selectedSchema === null}
                            value={newLabel}
                            onChange={(event) => setNewLabel(event.target.value)}
                        />
                        <p className="text-[var(--color-text-muted)]">
                            {t('platform.connections.label.help')}
                        </p>
                    </div>

                    <div className="mt-[var(--space-fluid-sm)]">
                        <label htmlFor="new-connection-scope" className="block">
                            {t('platform.connections.scope.label')}
                        </label>
                        <select
                            id="new-connection-scope"
                            className={inputClass}
                            disabled={selectedSchema === null}
                            value={newScope}
                            onChange={(event) => setNewScope(event.target.value)}
                        >
                            <option value="platform_owned">
                                {t('platform.connections.scope.platform')}
                            </option>
                            <option value="tenant_byok">
                                {t('platform.connections.scope.byok')}
                            </option>
                        </select>
                    </div>

                    {newScope === 'tenant_byok' ? (
                        <div className="mt-[var(--space-fluid-sm)]">
                            <label htmlFor="new-connection-workspace" className="block">
                                {t('platform.connections.workspace.label')}
                            </label>
                            <input
                                id="new-connection-workspace"
                                type="number"
                                inputMode="numeric"
                                className={inputClass}
                                disabled={selectedSchema === null}
                                value={newWorkspaceId}
                                onChange={(event) => setNewWorkspaceId(event.target.value)}
                            />
                            <p className="text-[var(--color-text-muted)]">
                                {t('platform.connections.workspace.help')}
                            </p>
                        </div>
                    ) : null}

                    {/* Sağlayıcıya ÖZEL alanlar yalnız seçim yapıldıktan sonra. */}
                    {selectedSchema?.fields.map((field) => {
                        const inputId = `new-connection-${field.name}`;

                        return (
                            <div key={field.name} className="mt-[var(--space-fluid-sm)]">
                                <label htmlFor={inputId} className="block">
                                    {fieldLabel(field.name)}
                                </label>
                                <input
                                    id={inputId}
                                    type={field.secret ? 'password' : 'text'}
                                    autoComplete="off"
                                    className={inputClass}
                                    placeholder={field.default ?? ''}
                                    value={newFields[field.name] ?? ''}
                                    onChange={(event) =>
                                        setNewFields((prev) => ({
                                            ...prev,
                                            [field.name]: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        );
                    })}

                    <div className="mt-[var(--space-fluid-md)] flex items-center gap-[var(--space-fluid-sm)]">
                        <button
                            type="button"
                            className={primaryButtonClass}
                            disabled={creating || selectedSchema === null}
                            onClick={() => void create()}
                        >
                            {t('platform.connections.create')}
                        </button>
                        {createError !== null ? <span role="alert">{createError}</span> : null}
                    </div>
                </section>
            ) : null}

            {grouped.map(({ schema, connections }) => (
                <section
                    key={schema.provider}
                    className="mt-[var(--space-fluid-lg)]"
                    aria-labelledby={`provider-${schema.provider}`}
                >
                    <h2 id={`provider-${schema.provider}`}>{providerLabel(schema.provider)}</h2>

                    {connections.length === 0 ? (
                        <p className="text-[var(--color-text-muted)]">
                            {t('platform.connections.empty')}
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-[var(--space-fluid-md)]">
                            {connections.map((connection) => (
                                <li
                                    key={connection.id}
                                    className="rounded-[var(--radius-md)] border border-[var(--color-border)] p-[var(--space-fluid-md)]"
                                >
                                    <div className="flex items-center justify-between gap-[var(--space-fluid-sm)]">
                                        <h3>{connection.label}</h3>
                                        <span data-testid={`state-${connection.id}`}>
                                            {t(
                                                `platform.credentials.state.${connection.state}` as never,
                                            )}
                                        </span>
                                    </div>

                                    <p className="text-[var(--color-text-muted)]">
                                        {t(
                                            `platform.connections.scope.${connection.scope}` as never,
                                        )}
                                        {' · '}
                                        {t(
                                            `platform.connections.health.${connection.health}` as never,
                                        )}
                                    </p>

                                    {connection.fields.map((field) => {
                                        const inputId = `cred-${connection.id}-${field.name}`;

                                        return (
                                            <div
                                                key={field.name}
                                                className="mt-[var(--space-fluid-sm)]"
                                            >
                                                <label htmlFor={inputId} className="block">
                                                    {fieldLabel(field.name)}
                                                    {field.secret && field.isSet ? (
                                                        <span className="ms-2 text-[var(--color-text-muted)]">
                                                            {t('platform.credentials.secretSet', {
                                                                mask: field.preview ?? '',
                                                            })}
                                                        </span>
                                                    ) : null}
                                                </label>
                                                <input
                                                    id={inputId}
                                                    name={field.name}
                                                    type={field.secret ? 'password' : 'text'}
                                                    autoComplete="off"
                                                    className={inputClass}
                                                    placeholder={
                                                        field.secret && field.isSet
                                                            ? t(
                                                                  'platform.credentials.keepPlaceholder',
                                                              )
                                                            : ''
                                                    }
                                                    value={
                                                        drafts[connection.id]?.[field.name] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setField(
                                                            connection.id,
                                                            field.name,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        );
                                    })}

                                    <div className="mt-[var(--space-fluid-md)] flex items-center gap-[var(--space-fluid-sm)]">
                                        <button
                                            type="button"
                                            className={primaryButtonClass}
                                            disabled={busyId === connection.id}
                                            onClick={() => void save(connection)}
                                        >
                                            {t('platform.credentials.save')}
                                        </button>

                                        <button
                                            type="button"
                                            className={secondaryButtonClass}
                                            disabled={busyId === connection.id}
                                            onClick={() =>
                                                void setState(
                                                    connection.id,
                                                    connection.state === 'active'
                                                        ? 'disable'
                                                        : 'enable',
                                                )
                                            }
                                        >
                                            {connection.state === 'active'
                                                ? t('platform.credentials.disable')
                                                : t('platform.connections.enable')}
                                        </button>

                                        <button
                                            type="button"
                                            className={secondaryButtonClass}
                                            disabled={busyId === connection.id}
                                            onClick={() => void probe(connection.id)}
                                        >
                                            {t('platform.connections.probe')}
                                        </button>

                                        {probeOutcome[connection.id] ? (
                                            <span role="status">
                                                {t(
                                                    `platform.connections.probe.${probeOutcome[connection.id]}` as never,
                                                )}
                                            </span>
                                        ) : null}

                                        {savedId === connection.id ? (
                                            <span role="status">
                                                {t('platform.credentials.saved')}
                                            </span>
                                        ) : null}
                                        {saveError === connection.id ? (
                                            <span role="alert">
                                                {t('platform.credentials.saveError')}
                                            </span>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ))}
        </section>
    );
}
