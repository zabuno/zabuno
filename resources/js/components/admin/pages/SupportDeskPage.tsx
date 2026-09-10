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
        ODAK KUYRUKTAN GELDİĞİNDE TAŞINIR, seçiciden geldiğinde değil.
        Seçiciyi kullanan zaten masaya bakıyordur; kuyruktan tıklayan ise
        sayfanın en üstünde kalır ve klavyeyle çalışıyorsa açtığı masayı
        bulmak için bütün kuyruğu yeniden geçmek zorunda kalırdı.
    */
    const deskRef = useRef<HTMLDivElement | null>(null);
    const [deskFocusPending, setDeskFocusPending] = useState(false);

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

    /*
        YÜKLEME OKUDUĞUNU GERİ VERİR. Kuyruktan gelen tek tık, kiracıyı
        SUNUCUNUN gövdesinden kurar — kuyruk satırında yalnız bir kimlik
        numarası vardır, ad/slug/durum yoktur. Başarısız bir okumada `null`
        döner ve çağıran hiçbir şey silahlamaz.
    */
    const load = useCallback(async (workspaceId: number): Promise<TenantSupportView | null> => {
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
            if (requestRef.current !== requestId) return null;

            if (!response.ok) {
                setState({ phase: 'error' });

                return null;
            }

            const body = (await response.json()) as TenantSupportView;

            if (requestRef.current !== requestId) return null;

            setState({ phase: 'ready', view: body });

            return body;
        } catch {
            if (requestRef.current === requestId) setState({ phase: 'error' });

            return null;
        }
    }, []);

    function handleSelect(workspace: Workspace) {
        setSelected(workspace);
        setNotice(null);
        void load(workspace.id);
    }

    /*
        KUYRUKTAN TEK TIK (`docs/125` §6). Önce OKUNUR, sonra seçilir — sıra
        bu paketin bütün argümanı.

        Ters sırada (önce seç, sonra oku) okuma başarısız olduğunda ekranda
        seçili ama hiç okunmamış bir kiracı kalırdı; "kiracı olarak bak"
        düğmesi o hesap için silahlanmış olurdu ve sahibinin denetim izine
        hiç görülmemiş bir hesap için bir bakış kaydı düşebilirdi.

        Ad/slug/durum SUNUCUNUN gövdesinden alınır: kuyruk satırı yalnız bir
        kimlik numarası taşır ve ekranın uydurduğu bir ad, seçicide yazan
        addan sapabilirdi.
    */
    async function handleQueueOpen(workspaceId: number) {
        setNotice(null);

        const view = await load(workspaceId);

        if (view === null) return;

        setSelected(view.workspace);
        setDeskFocusPending(true);
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

    useEffect(() => {
        if (!deskFocusPending || state.phase !== 'ready') return;

        deskRef.current?.focus();
        setDeskFocusPending(false);
    }, [deskFocusPending, state.phase]);

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
                onOpenWorkspace={(workspaceId) => void handleQueueOpen(workspaceId)}
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
                /*
                    KABIN ADI VARDIR. Odak buraya taşınıyor ve adsız bir kaba
                    düşen ekran okuyucu yalnız "grup" der; adıyla düşen ise
                    hangi restoranın masasının açıldığını söyler.
                */
                <div
                    ref={deskRef}
                    tabIndex={-1}
                    role="group"
                    aria-label={t('platform.support.desk.region', {
                        name: state.view.workspace.name,
                    })}
                    className="flex flex-col gap-[var(--space-4)] focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
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
                </div>
            )}
        </div>
    );
}

export default SupportDeskPage;
