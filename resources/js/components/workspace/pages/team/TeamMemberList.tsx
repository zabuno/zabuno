import { useEffect, useRef, useState } from 'react';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: string;
};

export type TeamMemberListStatus = 'loading' | 'error' | 'success';

export type TeamMemberRemoveOutcome = 'success' | 'error' | 'retry';

type RowStage = 'idle' | 'confirming' | 'busy' | 'error';

type TeamMemberListProps = {
    status: TeamMemberListStatus;
    members: TeamMember[];
    loadingText: string;
    errorText: string;
    emptyText: string;
    onRemoveMember: (memberId: number) => Promise<TeamMemberRemoveOutcome>;
    removeButtonText: string;
    removeConfirmText: string;
    removeCancelText: string;
    removeBusyText: string;
    removeErrorText: string;
    removeSuccessText: string;
    removeRetryText: string;
};

const REMOVABLE_ROLE = 'editor';

/**
 * Presentational: renders whatever member rows/status text it is given.
 * No fetch, route, or workspace-id knowledge — TeamPage owns the real
 * GET/DELETE /api/workspaces/{workspaceId}/team/members calls and passes
 * the resulting status/members/labels plus a remove callback through. Only
 * an Editor row exposes a Remove control; Owner and any other role never do.
 */
export function TeamMemberList({
    status,
    members,
    loadingText,
    errorText,
    emptyText,
    onRemoveMember,
    removeButtonText,
    removeConfirmText,
    removeCancelText,
    removeBusyText,
    removeErrorText,
    removeSuccessText,
    removeRetryText,
}: TeamMemberListProps) {
    const [rowStages, setRowStages] = useState<Record<number, RowStage>>({});
    const [committedRows, setCommittedRows] = useState<Record<number, boolean>>({});
    const [announcement, setAnnouncement] = useState<string | null>(null);
    const skipNextMembersClearRef = useRef(false);

    useEffect(() => {
        if (skipNextMembersClearRef.current) {
            skipNextMembersClearRef.current = false;

            return;
        }

        setAnnouncement(null);
    }, [members]);

    function startRemove(memberId: number) {
        setRowStages((current) => ({ ...current, [memberId]: 'confirming' }));
    }

    function cancelRemove(memberId: number) {
        setRowStages((current) => ({ ...current, [memberId]: 'idle' }));
    }

    async function confirmRemove(memberId: number) {
        setRowStages((current) => ({ ...current, [memberId]: 'busy' }));

        const outcome = await onRemoveMember(memberId);

        if (outcome === 'success') {
            skipNextMembersClearRef.current = true;
            setAnnouncement(removeSuccessText);
            setRowStages((current) => {
                const next = { ...current };
                delete next[memberId];

                return next;
            });
            setCommittedRows((current) => {
                const next = { ...current };
                delete next[memberId];

                return next;
            });

            return;
        }

        setCommittedRows((current) => ({ ...current, [memberId]: outcome === 'retry' }));
        setRowStages((current) => ({ ...current, [memberId]: 'error' }));
    }

    if (status === 'loading') {
        return (
            <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                {loadingText}
            </p>
        );
    }

    if (status === 'error') {
        return (
            <p role="status" className="text-sm font-medium text-red-600 dark:text-red-400">
                {errorText}
            </p>
        );
    }

    if (members.length === 0) {
        return (
            <>
                {announcement && (
                    <p role="status" className="text-sm font-medium text-green-600 dark:text-green-400">
                        {announcement}
                    </p>
                )}
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {emptyText}
                </p>
            </>
        );
    }

    return (
        <>
            {announcement && (
                <p role="status" className="text-sm font-medium text-green-600 dark:text-green-400">
                    {announcement}
                </p>
            )}
            <ul className="flex flex-col gap-2">
                {members.map((member) => {
                    const stage = rowStages[member.id] ?? 'idle';
                    const removable = member.role.toLowerCase() === REMOVABLE_ROLE;

                    return (
                        <li
                            key={member.id}
                            className="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-lg border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300"
                        >
                            <span className="font-medium text-gray-900 dark:text-white">{member.name}</span>
                            <span className="text-gray-500 dark:text-gray-400">{member.email}</span>
                            <span className="text-gray-500 dark:text-gray-400">{member.role}</span>

                            {removable && stage === 'idle' && (
                                <button
                                    type="button"
                                    className="text-sm font-medium text-red-600 dark:text-red-400"
                                    onClick={() => startRemove(member.id)}
                                >
                                    {removeButtonText}
                                </button>
                            )}

                            {removable && stage !== 'idle' && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-red-600 dark:text-red-400"
                                        disabled={stage === 'busy'}
                                        onClick={() => void confirmRemove(member.id)}
                                    >
                                        {committedRows[member.id] ? removeRetryText : removeConfirmText}
                                    </button>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        disabled={stage === 'busy'}
                                        onClick={() => cancelRemove(member.id)}
                                    >
                                        {removeCancelText}
                                    </button>
                                </div>
                            )}

                            {removable && stage === 'busy' && (
                                <span role="status" className="text-sm text-gray-500 dark:text-gray-400">
                                    {removeBusyText}
                                </span>
                            )}

                            {removable && stage === 'error' && (
                                <span role="status" className="text-sm font-medium text-red-600 dark:text-red-400">
                                    {removeErrorText}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </>
    );
}

export default TeamMemberList;
