import { useCallback, useEffect, useMemo, useState } from 'react';

import { t } from '../../../i18n/platform';

/**
 * Superadmin sağlayıcı kasası paneli — `docs/94` (Faz 4).
 *
 * Sır ASLA geri gelmez: API her sır alan için yalnız `••••son4` maskesi
 * verir. Bir sır alanı boş bırakmak "değiştirme" demektir — mevcut değeri
 * korur; kullanıcı sırrı yeniden girmek zorunda değildir çünkü panel onu
 * zaten okuyamaz. Düz alanlar (domain, endpoint) tam değeriyle görünür.
 */

const ENDPOINT = '/api/admin/credentials';
const CSRF_ENDPOINT = '/sanctum/csrf-cookie';

type FieldStatus = {
    name: string;
    secret: boolean;
    isSet: boolean;
    preview: string | null;
};

type CredentialStatus = {
    provider: string;
    configured: boolean;
    state: string;
    lastRotatedAt: string | null;
    fields: FieldStatus[];
};

function isFieldStatus(value: unknown): value is FieldStatus {
    if (typeof value !== 'object' || value === null) {
        return false;
    }
    const c = value as Record<string, unknown>;

    return (
        typeof c.name === 'string' &&
        typeof c.secret === 'boolean' &&
        typeof c.isSet === 'boolean' &&
        (c.preview === null || typeof c.preview === 'string')
    );
}

function isCredentialStatus(value: unknown): value is CredentialStatus {
    if (typeof value !== 'object' || value === null) {
        return false;
    }
    const c = value as Record<string, unknown>;

    return (
        typeof c.provider === 'string' &&
        typeof c.configured === 'boolean' &&
        typeof c.state === 'string' &&
        (c.lastRotatedAt === null || typeof c.lastRotatedAt === 'string') &&
        Array.isArray(c.fields) &&
        c.fields.every(isFieldStatus)
    );
}

function isCredentialList(value: unknown): value is CredentialStatus[] {
    return Array.isArray(value) && value.every(isCredentialStatus);
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

type DraftMap = Record<string, Record<string, string>>;

function draftFromStatuses(list: CredentialStatus[]): DraftMap {
    const draft: DraftMap = {};
    for (const status of list) {
        draft[status.provider] = {};
        for (const field of status.fields) {
            // Sır alan boş başlar (maske değerdir, değer değil); düz alan
            // mevcut değeriyle dolu gelir.
            draft[status.provider][field.name] = field.secret ? '' : (field.preview ?? '');
        }
    }

    return draft;
}

function providerLabel(provider: string): string {
    return t(`platform.credentials.provider.${provider}` as never);
}

export function ProviderCredentialsPage() {
    const [statuses, setStatuses] = useState<CredentialStatus[] | null>(null);
    const [drafts, setDrafts] = useState<DraftMap>({});
    const [loadError, setLoadError] = useState(false);
    const [busyProvider, setBusyProvider] = useState<string | null>(null);
    const [savedProvider, setSavedProvider] = useState<string | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);

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
            if (!isCredentialList(data)) {
                throw new Error('shape');
            }
            setStatuses(data);
            setDrafts(draftFromStatuses(data));
        } catch {
            setLoadError(true);
        }
    }, []);

    useEffect(() => {
        void (async () => {
            await load();
        })();
    }, [load]);

    const setField = useCallback((provider: string, field: string, value: string) => {
        setDrafts((prev) => ({
            ...prev,
            [provider]: { ...prev[provider], [field]: value },
        }));
    }, []);

    const save = useCallback(
        async (status: CredentialStatus) => {
            setBusyProvider(status.provider);
            setSaveError(null);
            setSavedProvider(null);
            try {
                await bootstrapCsrfCookie();

                const draft = drafts[status.provider] ?? {};
                const body: Record<string, string> = {};
                for (const field of status.fields) {
                    const value = draft[field.name] ?? '';
                    // Boş sır GÖNDERİLMEZ: öncekini korumak için.
                    if (field.secret && value === '') {
                        continue;
                    }
                    body[field.name] = value;
                }

                const response = await fetch(
                    `${ENDPOINT}/${status.provider}`,
                    mutationInit('PUT', body),
                );
                if (!response.ok) {
                    throw new Error('save failed');
                }
                setSavedProvider(status.provider);
                await load();
            } catch {
                setSaveError(status.provider);
            } finally {
                setBusyProvider(null);
            }
        },
        [drafts, load],
    );

    const disable = useCallback(
        async (provider: string) => {
            setBusyProvider(provider);
            setSaveError(null);
            try {
                await bootstrapCsrfCookie();
                const response = await fetch(
                    `${ENDPOINT}/${provider}/disable`,
                    mutationInit('POST', {}),
                );
                if (!response.ok) {
                    throw new Error('disable failed');
                }
                await load();
            } catch {
                setSaveError(provider);
            } finally {
                setBusyProvider(null);
            }
        },
        [load],
    );

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

    if (statuses === null) {
        return (
            <section aria-labelledby="credentials-heading">
                <h1 id="credentials-heading">{heading}</h1>
                <p>{t('platform.credentials.loading')}</p>
            </section>
        );
    }

    return (
        <section aria-labelledby="credentials-heading">
            <h1 id="credentials-heading">{heading}</h1>
            <p>{t('platform.credentials.intro')}</p>

            <ul className="flex flex-col gap-[var(--space-fluid-md)]">
                {statuses.map((status) => {
                    const stateLabel = t(`platform.credentials.state.${status.state}` as never);

                    return (
                        <li
                            key={status.provider}
                            className="rounded-[var(--radius-md)] border border-[var(--color-border)] p-[var(--space-fluid-md)]"
                        >
                            <div className="flex items-center justify-between gap-[var(--space-fluid-sm)]">
                                <h2>{providerLabel(status.provider)}</h2>
                                <span data-testid={`state-${status.provider}`}>{stateLabel}</span>
                            </div>

                            {status.fields.map((field) => {
                                const inputId = `cred-${status.provider}-${field.name}`;
                                const fieldLabel = t(
                                    `platform.credentials.field.${field.name}` as never,
                                );

                                return (
                                    <div key={field.name} className="mt-[var(--space-fluid-sm)]">
                                        <label htmlFor={inputId} className="block">
                                            {fieldLabel}
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
                                            className="min-h-[44px] w-full rounded-[var(--radius-sm)] border border-[var(--color-border)] px-3"
                                            placeholder={
                                                field.secret && field.isSet
                                                    ? t('platform.credentials.keepPlaceholder')
                                                    : ''
                                            }
                                            value={drafts[status.provider]?.[field.name] ?? ''}
                                            onChange={(event) =>
                                                setField(
                                                    status.provider,
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
                                    className="min-h-[44px] rounded-[var(--radius-sm)] bg-[var(--color-accent)] px-4 text-[var(--color-on-accent)]"
                                    disabled={busyProvider === status.provider}
                                    onClick={() => void save(status)}
                                >
                                    {t('platform.credentials.save')}
                                </button>

                                {status.configured ? (
                                    <button
                                        type="button"
                                        className="min-h-[44px] rounded-[var(--radius-sm)] border border-[var(--color-border)] px-4"
                                        disabled={busyProvider === status.provider}
                                        onClick={() => void disable(status.provider)}
                                    >
                                        {t('platform.credentials.disable')}
                                    </button>
                                ) : null}

                                {savedProvider === status.provider ? (
                                    <span role="status">{t('platform.credentials.saved')}</span>
                                ) : null}
                                {saveError === status.provider ? (
                                    <span role="alert">{t('platform.credentials.saveError')}</span>
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
