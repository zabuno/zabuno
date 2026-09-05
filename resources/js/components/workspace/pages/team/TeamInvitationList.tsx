import { useEffect, useId, useRef, useState } from 'react';

/**
 * Davetin E-POSTASININ bilinen hâli (`docs/110` P0-06).
 *
 * `status` daveti anlatır ("bekliyor"), bu alan ise POSTAYI. İkisi ayrı
 * sorulardır: bekleyen bir davetin e-postası hiç çıkmamış olabilir ve o
 * satır ekranda başarılı bir davetle birebir aynı görünüyordu.
 *
 * `sent` bir söz DEĞİLDİR: taşıyıcı mesajı hatasız devraldı demektir,
 * "gelen kutusuna düştü" demek değil.
 */
export type TeamInvitationDelivery = 'sent' | 'failed' | 'unknown';

export type TeamInvitation = {
    id: number;
    email: string;
    role: string;
    status: string;
    delivery: TeamInvitationDelivery;
};

export type TeamInvitationListStatus = 'loading' | 'error' | 'success';

export type TeamInvitationCancelOutcome = 'success' | 'error' | 'retry';

/**
 * Yeniden gönderme SONUCU — üç hâl, iki değil.
 *
 * `undelivered` hâli olmasaydı, davet tazelenip e-posta çıkmadığında ekran
 * ya "gönderildi" (yalan) ya da "gönderilemedi" (eksik: bağlantı gerçekten
 * yenilendi) derdi.
 */
export type TeamInvitationResendOutcome = 'sent' | 'undelivered' | 'error';

type RowStage = 'idle' | 'confirming' | 'busy' | 'error';

type TeamInvitationListProps = {
    status: TeamInvitationListStatus;
    invitations: TeamInvitation[];
    label: string;
    loadingText: string;
    errorText: string;
    emptyText: string;
    onCancelInvitation: (invitationId: number) => Promise<TeamInvitationCancelOutcome>;
    cancelButtonText: string;
    cancelConfirmText: string;
    cancelKeepText: string;
    cancelBusyText: string;
    cancelErrorText: string;
    cancelSuccessText: string;
    cancelRetryText: string;
    onResendInvitation: (invitationId: number) => Promise<TeamInvitationResendOutcome>;
    resendButtonText: string;
    resendBusyText: string;
    resendSentText: string;
    resendUndeliveredText: string;
    resendErrorText: string;
    resendLinkNoteText: string;
    deliveryFailedText: string;
    deliveryUnknownText: string;
};

/**
 * Presentational: renders whatever pending invitation rows/status text it is
 * given. No fetch, route, or workspace-id knowledge — TeamPage owns the real
 * GET/DELETE /api/workspaces/{workspaceId}/team/invitations calls and passes
 * the resulting status/invitations/labels plus a cancel callback through.
 */
export function TeamInvitationList({
    status,
    invitations,
    label,
    loadingText,
    errorText,
    emptyText,
    onCancelInvitation,
    cancelButtonText,
    cancelConfirmText,
    cancelKeepText,
    cancelBusyText,
    cancelErrorText,
    cancelSuccessText,
    cancelRetryText,
    onResendInvitation,
    resendButtonText,
    resendBusyText,
    resendSentText,
    resendUndeliveredText,
    resendErrorText,
    resendLinkNoteText,
    deliveryFailedText,
    deliveryUnknownText,
}: TeamInvitationListProps) {
    const headingId = useId();
    const [rowStages, setRowStages] = useState<Record<number, RowStage>>({});
    const [committedRows, setCommittedRows] = useState<Record<number, boolean>>({});
    const [announcement, setAnnouncement] = useState<string | null>(null);
    const skipNextInvitationsClearRef = useRef(false);
    /*
        Yeniden gönderme hâli SATIR BAŞINA tutulur.

        Tek bir "meşgul" bayrağı, iki daveti olan bir sahibin ikinci satırını
        da kilitlerdi ve hangisinin gönderildiği belirsiz kalırdı.
    */
    const [resendBusyRows, setResendBusyRows] = useState<Record<number, boolean>>({});
    const [resendNotices, setResendNotices] = useState<Record<number, string>>({});

    useEffect(() => {
        if (skipNextInvitationsClearRef.current) {
            skipNextInvitationsClearRef.current = false;

            return;
        }

        setAnnouncement(null);
    }, [invitations]);

    function startCancel(invitationId: number) {
        setRowStages((current) => ({ ...current, [invitationId]: 'confirming' }));
    }

    function keepInvitation(invitationId: number) {
        setRowStages((current) => ({ ...current, [invitationId]: 'idle' }));
    }

    async function confirmCancel(invitationId: number) {
        setRowStages((current) => ({ ...current, [invitationId]: 'busy' }));

        const outcome = await onCancelInvitation(invitationId);

        if (outcome === 'success') {
            skipNextInvitationsClearRef.current = true;
            setAnnouncement(cancelSuccessText);
            setRowStages((current) => {
                const next = { ...current };
                delete next[invitationId];

                return next;
            });
            setCommittedRows((current) => {
                const next = { ...current };
                delete next[invitationId];

                return next;
            });

            return;
        }

        setCommittedRows((current) => ({ ...current, [invitationId]: outcome === 'retry' }));
        setRowStages((current) => ({ ...current, [invitationId]: 'error' }));
    }

    async function resendInvitation(invitationId: number) {
        setResendBusyRows((current) => ({ ...current, [invitationId]: true }));
        setResendNotices((current) => {
            const next = { ...current };
            delete next[invitationId];

            return next;
        });

        const outcome = await onResendInvitation(invitationId);

        setResendBusyRows((current) => {
            const next = { ...current };
            delete next[invitationId];

            return next;
        });

        /*
            EKRANA SUNUCUNUN SÖYLEDİĞİ YAZILIR.

            Üç ayrı cevap, üç ayrı cümle: taşıyıcı devraldı / davet
            tazelendi ama e-posta çıkmadı / istek hiç tamamlanamadı. Üçünü
            tek bir "gönderildi" altında toplamak, sahibi gelmeyen bir
            e-postayı beklemeye çağırmak olurdu.
        */
        setResendNotices((current) => ({
            ...current,
            [invitationId]:
                outcome === 'sent'
                    ? resendSentText
                    : outcome === 'undelivered'
                      ? resendUndeliveredText
                      : resendErrorText,
        }));
    }

    return (
        <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
            {/*
                BAŞLIK GÖRÜNÜR VE BÖLGENİN ADIDIR (`docs/109` §6.4, kaynağın
                "Bekleyen davetler" kart başlığı).

                Önce iki ayrı ad vardı: görünmez bir `aria-label` ve onunla
                aynı metni yazan bir paragraf. Aynı şeyi iki kez söylemekten
                başka, paragraf `font-semibold` taşıyordu — AEP ölçeğinde
                izinli ağırlıklar 400/500/700'dür ve 600, Roboto'da ayrı bir
                kesim olmadığı için tarayıcı tarafından SENTEZLENİYORDU.
            */}
            <h2 id={headingId} className="text-body font-bold text-fg">
                {label}
            </h2>

            {status === 'loading' && (
                <p role="status" className="text-body text-fg-muted">
                    {loadingText}
                </p>
            )}

            {status === 'error' && (
                <p role="status" className="text-body font-medium text-fg-danger">
                    {errorText}
                </p>
            )}

            {status === 'success' && announcement && (
                <p role="status" className="text-body font-medium text-fg-success">
                    {announcement}
                </p>
            )}

            {status === 'success' && invitations.length === 0 && (
                <p role="status" className="text-body text-fg-muted">
                    {emptyText}
                </p>
            )}

            {status === 'success' && invitations.length > 0 && (
                <ul className="flex flex-col">
                    {invitations.map((invitation) => {
                        const stage = rowStages[invitation.id] ?? 'idle';
                        const resendBusy = resendBusyRows[invitation.id] === true;
                        const resendNotice = resendNotices[invitation.id];
                        /*
                            "GÖNDERİLDİ" İÇİN SATIR YAZILMAZ.

                            Yolunda giden bir davet, listede yalnız kendisi
                            olarak durur. Her satıra bir de "gönderildi"
                            rozeti koymak, gerçekten dikkat isteyen iki hâli
                            (çıkmadı / bilinmiyor) gürültünün içinde
                            kaybederdi.
                        */
                        const deliveryNotice =
                            invitation.delivery === 'failed'
                                ? deliveryFailedText
                                : invitation.delivery === 'unknown'
                                  ? deliveryUnknownText
                                  : null;

                        return (
                            <li
                                key={invitation.id}
                                // Üye listesiyle AYNI gramer (FF-131): satır kart değil,
                                // ayraçla bölünmüş liste öğesi. İki liste yan yana
                                // durduğu için ritimleri de aynı olmak zorunda —
                                // farklı olsalar biri "daha önemli" gibi okunurdu.
                                className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-3 gap-y-1 border-t border-border px-[var(--density-padding-inline)] py-[var(--space-2)] text-body text-fg-secondary first:border-t-0"
                            >
                                <span className="font-medium text-fg">{invitation.email}</span>
                                <span className="text-fg-muted">{invitation.role}</span>
                                <span className="text-fg-muted">{invitation.status}</span>

                                {deliveryNotice !== null && (
                                    <span className="text-body font-medium text-fg-danger">
                                        {deliveryNotice}
                                    </span>
                                )}

                                {stage === 'idle' && (
                                    <button
                                        type="button"
                                        className="text-body font-medium text-fg-danger"
                                        onClick={() => startCancel(invitation.id)}
                                    >
                                        {cancelButtonText}
                                    </button>
                                )}

                                {/*
                                    YENİDEN GÖNDERME HER BEKLEYEN SATIRDA
                                    DURUR (`docs/110` P0-06).

                                    Yalnız "çıkmadı" diyen satırlarda
                                    gösterseydik, spam'e düşmüş ya da yanlış
                                    okunmuş bir e-posta için sahibin elinde
                                    yine tek bir hamle kalırdı: daveti iptal
                                    edip yeniden kurmak. Yani ekibini
                                    kurabilmek için önce onu bozması
                                    gerekirdi.
                                */}
                                {stage === 'idle' && (
                                    <button
                                        type="button"
                                        className="text-body font-medium text-fg-secondary"
                                        disabled={resendBusy}
                                        onClick={() => void resendInvitation(invitation.id)}
                                    >
                                        {resendButtonText}
                                    </button>
                                )}

                                {stage !== 'idle' && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            className="text-body font-medium text-fg-danger"
                                            disabled={stage === 'busy'}
                                            onClick={() => void confirmCancel(invitation.id)}
                                        >
                                            {committedRows[invitation.id]
                                                ? cancelRetryText
                                                : cancelConfirmText}
                                        </button>
                                        <button
                                            type="button"
                                            className="text-body font-medium text-fg-secondary"
                                            disabled={stage === 'busy'}
                                            onClick={() => keepInvitation(invitation.id)}
                                        >
                                            {cancelKeepText}
                                        </button>
                                    </div>
                                )}

                                {stage === 'busy' && (
                                    <span role="status" className="text-body text-fg-muted">
                                        {cancelBusyText}
                                    </span>
                                )}

                                {stage === 'error' && (
                                    <span
                                        role="status"
                                        className="text-body font-medium text-fg-danger"
                                    >
                                        {cancelErrorText}
                                    </span>
                                )}

                                {resendBusy && (
                                    <span role="status" className="text-body text-fg-muted">
                                        {resendBusyText}
                                    </span>
                                )}

                                {!resendBusy && resendNotice !== undefined && (
                                    <span role="status" className="text-body text-fg-secondary">
                                        {resendNotice}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {/*
                BAĞLANTI KURALI SATIR BAŞINA DEĞİL, LİSTE ALTINA YAZILIR.

                Kural her davet için aynıdır; her satırda tekrarlamak aynı
                cümleyi beş kez okutmak olurdu. Ama hiç yazılmasaydı, iki
                e-posta göndermiş bir sahip "hangisi çalışıyor?" sorusunun
                cevabını ancak alıcı şikâyet edince öğrenirdi.
            */}
            {status === 'success' && invitations.length > 0 && (
                <p className="text-body text-fg-muted">{resendLinkNoteText}</p>
            )}
        </div>
    );
}

export default TeamInvitationList;
