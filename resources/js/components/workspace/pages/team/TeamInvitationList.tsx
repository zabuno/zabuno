import { useEffect, useRef, useState } from 'react';

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
        <div role="region" aria-label={label} className="flex flex-col gap-3">
            <p className="text-sm font-semibold text-gray-900 dark:text-white">{label}</p>

            {status === 'loading' && (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {loadingText}
                </p>
            )}

            {status === 'error' && (
                <p role="status" className="text-sm font-medium text-red-600 dark:text-red-400">
                    {errorText}
                </p>
            )}

            {status === 'success' && announcement && (
                <p role="status" className="text-sm font-medium text-green-600 dark:text-green-400">
                    {announcement}
                </p>
            )}

            {status === 'success' && invitations.length === 0 && (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {emptyText}
                </p>
            )}

            {status === 'success' && invitations.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {invitations.map((invitation) => {
                        const stage = rowStages[invitation.id] ?? 'idle';

                        return (
                            <li
                                key={invitation.id}
                                className="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-lg border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300"
                            >
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {invitation.email}
                                </span>
                                <span className="text-gray-500 dark:text-gray-400">
                                    {invitation.role}
                                </span>
                                <span className="text-gray-500 dark:text-gray-400">
                                    {invitation.status}
                                </span>

                                {stage === 'idle' && (
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-red-600 dark:text-red-400"
                                        onClick={() => startCancel(invitation.id)}
                                    >
                                        {cancelButtonText}
                                    </button>
                                )}

                                {stage !== 'idle' && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            className="text-sm font-medium text-red-600 dark:text-red-400"
                                            disabled={stage === 'busy'}
                                            onClick={() => void confirmCancel(invitation.id)}
                                        >
                                            {committedRows[invitation.id]
                                                ? cancelRetryText
                                                : cancelConfirmText}
                                        </button>
                                        <button
                                            type="button"
                                            className="text-sm font-medium text-gray-700 dark:text-gray-300"
                                            disabled={stage === 'busy'}
                                            onClick={() => keepInvitation(invitation.id)}
                                        >
                                            {cancelKeepText}
                                        </button>
                                    </div>
                                )}

                                {stage === 'busy' && (
                                    <span
                                        role="status"
                                        className="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {cancelBusyText}
                                    </span>
                                )}

                                {stage === 'error' && (
                                    <span
                                        role="status"
                                        className="text-sm font-medium text-red-600 dark:text-red-400"
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
