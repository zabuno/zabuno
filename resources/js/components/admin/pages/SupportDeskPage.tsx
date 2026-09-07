import { useCallback, useEffect, useRef, useState } from 'react';

import { t } from '../../../i18n/platform';
import { SupportDesk } from './support/SupportDesk';
import { SupportQueue, type SupportQueueRow } from './support/SupportQueue';
import type { SupportAccessSession, TenantSupportView } from './support/types';
import { WorkspaceDiscovery, type Workspace } from './subscriptions/WorkspaceDiscovery';

type AccessState = {
    session: SupportAccessSession | null;
    windowMinutes: number;
    reasonMinLength: number;
};

type State =
    | { phase: 'idle' }
    | { phase: 'loading' }
    | { phase: 'error' }
    | { phase: 'ready'; view: TenantSupportView };

/**
 * Destek masası — `docs/122` §3 boşluk 3 ve Y7, `docs/133`.
 *
 * Getirme kabuğu: sunucuyla konuşur, çizmeyi `SupportDesk`'e bırakır.
 *
 * OTURUM HER İSTEKTEN SONRA SUNUCUYA YENİDEN SORULUR ve ekranın kendi
 * belleğinde tutulmaz. Süre sunucuda dolar; tarayıcıda tutulan bir sayaç,
 * sekme uyuduğunda ya da makine kapandığında yalan söyler ve "hâlâ açık mı?"
 * sorusuna en son ne zaman baktığına göre cevap verir.
 *
 * REDDEDİLEN İSTEĞİN CÜMLESİ SUNUCUNUNDUR. Salt-okunur kilit bir yazmayı
 * reddettiğinde ekran kendi tahminini yazmaz; kullanıcıya sunucunun
 * söylediğini gösterir, çünkü ret sebebi ekranın bildiğinden fazlasını
 * taşır.
 */
export function SupportDeskPage() {
    const [selected, setSelected] = useState<Workspace | null>(null);
    const [state, setState] = useState<State>({ phase: 'idle' });
    const [access, setAccess] = useState<AccessState>({
        session: null,
        windowMinutes: 0,
        reasonMinLength: 0,
    });
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const requestRef = useRef(0);

    /*
        KUYRUK KİRACIDAN ÖNCE GELİR (`docs/125` §6). Destek günü bir
        restoran seçmekle değil, bekleyen bir talebi okumakla başlar; kuyruk
        bu yüzden kiracı seçicisinin ÜSTÜNDEDİR.

        Süzgeç SUNUCUDA uygulanır: tarayıcıda süzmek yalnız çekilmiş listeyi
        süzerdi ve "bekleyen yok" cevabı gerçekte "bu listede yok" anlamına
        gelirdi.
    */
    const [queue, setQueue] = useState<SupportQueueRow[]>([]);
    const [queueStatus, setQueueStatus] = useState('');
    const [queueAttempt, setQueueAttempt] = useState(0);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            const params = queueStatus === '' ? '' : `?status=${encodeURIComponent(queueStatus)}`;

            try {
                const response = await fetch(`/api/admin/support-requests${params}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled || !response.ok) return;

                const body = (await response.json()) as SupportQueueRow[];

                if (cancelled) return;

                setQueue(body);
            } catch {
                // Kuyruk okunamadıysa ekran BOŞ bir kuyruk uydurmaz; kart
                // "bekleyen yok" demez, önceki listeyi olduğu gibi bırakır.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [queueStatus, queueAttempt]);

    async function handleQueueStatus(id: number, next: string) {
        setBusy(true);
        setNotice(null);

        try {
            const response = await fetch(`/api/admin/support-requests/${String(id)}/status`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: next }),
            });

            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;

                setNotice(body?.message ?? t('platform.support.error'));
            }
        } catch {
            setNotice(t('platform.support.error'));
        }

        setBusy(false);
        setQueueAttempt((previous) => previous + 1);
    }

    /*
        OTURUM DURUMU BİR SAYAÇLA TAZELENİR (`AuditTrailRegion` ile aynı
        biçim). İş etkinin İÇİNDE yapılır: dışarıda tanımlanmış bir işlevi
        etkiden çağırmak, derleyici kapısının "etki içinde eşzamanlı
        setState" hatasını tetikliyor — `await` sınırını göremediği için
        durum güncellemesini çizim sırasında sanıyor.

        Sayaç ayrıca doğru olanı yapar: "ilk okuma" ile "işlemden sonra
        yeniden okuma" AYNI yoldan geçer ve ikisi ayrışamaz.
    */
    const [accessAttempt, setAccessAttempt] = useState(0);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch('/api/admin/support-access', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled || !response.ok) return;

                const body = (await response.json()) as {
                    session?: SupportAccessSession | null;
                    windowMinutes?: number;
                    reasonMinLength?: number;
                };

                if (cancelled) return;

                setAccess({
                    session: body.session ?? null,
                    windowMinutes: body.windowMinutes ?? 0,
                    reasonMinLength: body.reasonMinLength ?? 0,
                });
            } catch {
                // Oturum durumu okunamadıysa ekran bir şey UYDURMAZ: kart
                // "oturum yok" der ve açma denemesi sunucuda yine
                // reddedilir. Sunucu tek karar mercii kalır.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [accessAttempt]);

    const load = useCallback(async (workspaceId: number) => {
        const requestId = ++requestRef.current;
        setState({ phase: 'loading' });

        try {
            const response = await fetch(
                `/api/admin/workspaces/${String(workspaceId)}/support-view`,
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                },
            );

            // Yarışan istek: hızlı hızlı iki kiracı seçildiğinde geç gelen
            // ilk cevabın ikincinin ekranına yazılması, bir destek
            // ekranında en tehlikeli türden karışıklık olurdu.
            if (requestRef.current !== requestId) return;

            if (!response.ok) {
                setState({ phase: 'error' });

                return;
            }

            const body = (await response.json()) as TenantSupportView;

            if (requestRef.current !== requestId) return;

            setState({ phase: 'ready', view: body });
        } catch {
            if (requestRef.current === requestId) setState({ phase: 'error' });
        }
    }, []);

    function handleSelect(workspace: Workspace) {
        setSelected(workspace);
        setNotice(null);
        void load(workspace.id);
    }

    async function handleOpen(reason: string) {
        if (selected === null) {
            setNotice(t('platform.support.open.pickTenant'));

            return;
        }

        setBusy(true);
        setNotice(null);

        try {
            const response = await fetch(
                `/api/admin/workspaces/${String(selected.id)}/support-access`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason }),
                },
            );

            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;

                setNotice(body?.message ?? t('platform.support.open.failed'));
            }
        } catch {
            setNotice(t('platform.support.open.failed'));
        }

        setBusy(false);
        setAccessAttempt((previous) => previous + 1);
        await load(selected.id);
    }

    async function handleEnd() {
        setBusy(true);
        setNotice(null);

        try {
            await fetch('/api/admin/support-access/end', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
        } catch {
            setNotice(t('platform.support.session.endFailed'));
        }

        setBusy(false);
        setAccessAttempt((previous) => previous + 1);

        if (selected !== null) await load(selected.id);
    }

    return (
        <div className="flex flex-col gap-[var(--space-4)]">
            <p className="text-body text-fg-secondary">{t('platform.support.intro')}</p>

            <SupportQueue
                rows={queue}
                status={queueStatus}
                busy={busy}
                onStatusFilter={(next) => {
                    setQueueStatus(next);
                }}
                onChangeStatus={(id, next) => void handleQueueStatus(id, next)}
            />

            <WorkspaceDiscovery selectedWorkspace={selected} onSelect={handleSelect} />

            {state.phase === 'idle' && (
                <p className="text-body text-fg-muted">{t('platform.support.idle')}</p>
            )}

            {state.phase === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {t('platform.support.loading')}
                </p>
            )}

            {state.phase === 'error' && (
                <div className="flex flex-col gap-[var(--space-2)]">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('platform.support.error')}
                    </p>
                    <button
                        type="button"
                        className="min-h-[var(--density-hit-area-min)] self-start text-body font-medium text-fg-danger"
                        onClick={() => selected && void load(selected.id)}
                    >
                        {t('platform.support.retry')}
                    </button>
                </div>
            )}

            {state.phase === 'ready' && (
                <SupportDesk
                    view={state.view}
                    session={access.session}
                    windowMinutes={access.windowMinutes}
                    reasonMinLength={access.reasonMinLength}
                    busy={busy}
                    notice={notice}
                    onOpen={(reason) => void handleOpen(reason)}
                    onEnd={() => void handleEnd()}
                />
            )}
        </div>
    );
}

export default SupportDeskPage;
