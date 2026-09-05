import { useEffect, useId, useRef, useState } from 'react';

export type TeamInvitation = {
    id: number;
    email: string;
    role: string;
    status: string;
};

export type TeamInvitationListStatus = 'loading' | 'error' | 'success';

export type TeamInvitationCancelOutcome = 'success' | 'error' | 'retry';

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
}: TeamInvitationListProps) {
    const headingId = useId();
    const [rowStages, setRowStages] = useState<Record<number, RowStage>>({});
    const [committedRows, setCommittedRows] = useState<Record<number, boolean>>({});
    const [announcement, setAnnouncement] = useState<string | null>(null);
    const skipNextInvitationsClearRef = useRef(false);

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

                                {stage === 'idle' && (
                                    <button
                                        type="button"
                                        className="text-body font-medium text-fg-danger"
                                        onClick={() => startCancel(invitation.id)}
                                    >
                                        {cancelButtonText}
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
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

export default TeamInvitationList;
