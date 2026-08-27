import { useCallback, useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

type SubscriptionPhase = 'loading' | 'noActive' | 'active' | 'error';

type SessionState = 'none' | 'initiated' | 'processing' | 'succeeded' | 'failed';

type SessionData = {
    state: SessionState;
    conversation_id?: string;
    amount_minor?: number;
    currency?: string;
    redirect_url?: string;
};

type SessionPhase =
    | 'idle'
    | 'loading'
    | 'error'
    | 'ready'
    | 'transaction'
    | 'failed'
    | 'invalidRedirect'
    | 'checkoutError';

type IyzicoSandboxCheckoutProps = {
    workspaceId: number;
};

type SubscriptionState = 'active' | 'none';

function isSubscriptionState(value: unknown): value is SubscriptionState {
    return value === 'active' || value === 'none';
}

function isValidSubscriptionData(value: unknown): value is { state: SubscriptionState } {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    return isSubscriptionState((value as Record<string, unknown>).state);
}

function isSessionState(value: unknown): value is SessionState {
    return (
        value === 'none' ||
        value === 'initiated' ||
        value === 'processing' ||
        value === 'succeeded' ||
        value === 'failed'
    );
}

function isValidSessionData(value: unknown): value is SessionData {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    if (!isSessionState(candidate.state)) {
        return false;
    }

    if (candidate.conversation_id !== undefined && typeof candidate.conversation_id !== 'string') {
        return false;
    }

    if (candidate.amount_minor !== undefined) {
        if (
            typeof candidate.amount_minor !== 'number' ||
            !Number.isSafeInteger(candidate.amount_minor) ||
            candidate.amount_minor < 0
        ) {
            return false;
        }
    }

    if (candidate.currency !== undefined) {
        if (typeof candidate.currency !== 'string' || !/^[A-Z]{3}$/.test(candidate.currency)) {
            return false;
        }
    }

    if (candidate.redirect_url !== undefined && typeof candidate.redirect_url !== 'string') {
        return false;
    }

    return true;
}

function isHttpsUrl(value: unknown): value is string {
    if (typeof value !== 'string') {
        return false;
    }

    try {
        return new URL(value).protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * Frontend-only Iyzico sandbox checkout region. Never collects card
 * number/expiry/CVV/amount/currency/plan input — every value shown comes
 * from the server-authoritative subscription + session endpoints. A
 * session POST fires only on an explicit human click.
 */
export function IyzicoSandboxCheckout({ workspaceId }: IyzicoSandboxCheckoutProps) {
    const [subscriptionPhase, setSubscriptionPhase] = useState<SubscriptionPhase>('loading');
    const [sessionPhase, setSessionPhase] = useState<SessionPhase>('idle');
    const [session, setSession] = useState<SessionData | null>(null);
    const [posting, setPosting] = useState(false);

    const sessionUrl = `/api/workspaces/${workspaceId}/iyzico-sandbox/session`;

    const fetchSession = useCallback(async () => {
        setSessionPhase('loading');

        try {
            const response = await fetch(sessionUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setSessionPhase('error');
                return;
            }

            const body: unknown = await response.json();

            if (!isValidSessionData(body)) {
                setSessionPhase('error');
                return;
            }

            setSession(body);
            setSessionPhase(body.state === 'none' ? 'ready' : 'transaction');
        } catch {
            setSessionPhase('error');
        }
    }, [sessionUrl]);

    const fetchSubscription = useCallback(async () => {
        setSubscriptionPhase('loading');

        let response: Response;

        try {
            response = await fetch(`/api/workspaces/${workspaceId}/subscription`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
        } catch {
            setSubscriptionPhase('error');
            return;
        }

        if (!response.ok) {
            setSubscriptionPhase('error');
            return;
        }

        let body: unknown;

        try {
            body = await response.json();
        } catch {
            setSubscriptionPhase('error');
            return;
        }

        if (!isValidSubscriptionData(body)) {
            setSubscriptionPhase('error');
            return;
        }

        if (body.state === 'active') {
            setSubscriptionPhase('active');
            await fetchSession();
            return;
        }

        setSubscriptionPhase('noActive');
    }, [workspaceId, fetchSession]);

    useEffect(() => {
        void (async () => {
            await fetchSubscription();
        })();
    }, [fetchSubscription]);

    const startCheckout = useCallback(async () => {
        if (posting) {
            return;
        }

        setPosting(true);

        try {
            await bootstrapCsrfCookie();

            const idempotencyKey = crypto.randomUUID();
            const requestInit = buildAuthRequestInit({ method: 'POST', body: '{}' });
            const headers = new Headers(requestInit.headers);
            headers.set('Content-Type', 'application/json');
            headers.set('Idempotency-Key', idempotencyKey);

            const response = await fetch(sessionUrl, {
                ...requestInit,
                credentials: 'same-origin',
                headers,
            });

            if (!response.ok) {
                setSessionPhase('checkoutError');
                return;
            }

            const body: unknown = await response.json();

            if (!isValidSessionData(body) || body.state === 'none') {
                setSessionPhase('checkoutError');
                return;
            }

            if (body.state === 'failed') {
                setSession(body);
                setSessionPhase('failed');
                return;
            }

            if (body.redirect_url !== undefined && !isHttpsUrl(body.redirect_url)) {
                setSession(body);
                setSessionPhase('invalidRedirect');
                return;
            }

            setSession(body);
            setSessionPhase('transaction');
        } catch {
            setSessionPhase('checkoutError');
        } finally {
            setPosting(false);
        }
    }, [posting, sessionUrl]);

    const startDisabled = subscriptionPhase !== 'active' || sessionPhase !== 'ready' || posting;
    const showStartButton =
        sessionPhase === 'ready' ||
        sessionPhase === 'idle' ||
        sessionPhase === 'loading' ||
        sessionPhase === 'error' ||
        posting;

    return (
        <div
            role="region"
            aria-label={t('workspace.billing.iyzicoSandbox.heading')}
            className="flex flex-col w-full gap-3"
        >
            <h2 className="text-body font-semibold text-fg">
                {t('workspace.billing.iyzicoSandbox.heading')}
            </h2>

            <p className="text-body text-fg-muted">
                {t('workspace.billing.iyzicoSandbox.disclaimer')}
            </p>

            {subscriptionPhase === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.iyzicoSandbox.loading')}
                </p>
            )}

            {subscriptionPhase === 'noActive' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.iyzicoSandbox.noActive')}
                </p>
            )}

            {subscriptionPhase === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.iyzicoSandbox.subscriptionError')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-body font-medium text-fg-danger"
                        onClick={() => void fetchSubscription()}
                    >
                        {t('workspace.billing.iyzicoSandbox.retry')}
                    </button>
                </div>
            )}

            {subscriptionPhase === 'active' && sessionPhase === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.iyzicoSandbox.loading')}
                </p>
            )}

            {subscriptionPhase === 'active' && sessionPhase === 'error' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.iyzicoSandbox.sessionError')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-body font-medium text-fg-danger"
                        onClick={() => void fetchSession()}
                    >
                        {t('workspace.billing.iyzicoSandbox.retry')}
                    </button>
                </div>
            )}

            {subscriptionPhase === 'active' && sessionPhase === 'checkoutError' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.iyzicoSandbox.checkoutError')}
                    </p>
                    <button
                        type="button"
                        className="self-start text-body font-medium text-fg-danger"
                        onClick={() => void startCheckout()}
                    >
                        {t('workspace.billing.iyzicoSandbox.retry')}
                    </button>
                </div>
            )}

            {subscriptionPhase === 'active' && sessionPhase === 'ready' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.iyzicoSandbox.ready')}
                </p>
            )}

            {posting && (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.billing.iyzicoSandbox.processing')}
                </p>
            )}

            {subscriptionPhase === 'active' && sessionPhase === 'invalidRedirect' && (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {t('workspace.billing.iyzicoSandbox.invalidRedirect')}
                </p>
            )}

            {subscriptionPhase === 'active' && sessionPhase === 'failed' && (
                <div className="flex flex-col gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.billing.iyzicoSandbox.state.failed')}
                    </p>
                    {session?.conversation_id && (
                        <p className="text-body text-fg-muted">
                            {t('workspace.billing.iyzicoSandbox.conversation', {
                                id: session.conversation_id,
                            })}
                        </p>
                    )}
                    <button
                        type="button"
                        className="self-start text-body font-medium text-fg-danger"
                        onClick={() => void startCheckout()}
                    >
                        {t('workspace.billing.iyzicoSandbox.retry')}
                    </button>
                </div>
            )}

            {subscriptionPhase === 'active' &&
                sessionPhase === 'transaction' &&
                session &&
                !posting && (
                    <div className="flex flex-col gap-1 text-body text-fg-secondary">
                        <span>
                            {t(
                                `workspace.billing.iyzicoSandbox.state.${session.state as 'initiated' | 'processing' | 'succeeded'}`,
                            )}
                        </span>
                        {session.conversation_id && (
                            <span>
                                {t('workspace.billing.iyzicoSandbox.conversation', {
                                    id: session.conversation_id,
                                })}
                            </span>
                        )}
                        {session.amount_minor !== undefined && session.currency && (
                            <span>
                                {t('workspace.billing.iyzicoSandbox.amount', {
                                    amount: String(session.amount_minor),
                                    currency: session.currency,
                                })}
                            </span>
                        )}
                        {isHttpsUrl(session.redirect_url) && (
                            <a href={session.redirect_url} className="font-medium text-fg-link">
                                {t('workspace.billing.iyzicoSandbox.continueLink')}
                            </a>
                        )}
                    </div>
                )}

            {showStartButton && (
                <button
                    type="button"
                    disabled={startDisabled}
                    onClick={() => void startCheckout()}
                    className="w-full rounded-lg bg-action px-4 py-2 text-body font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {t('workspace.billing.iyzicoSandbox.start')}
                </button>
            )}
        </div>
    );
}

export default IyzicoSandboxCheckout;
