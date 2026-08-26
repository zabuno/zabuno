import { useEffect, useRef, useState } from 'react';
import { ConfirmDialog } from '../../../catalog/overlays/compound/ConfirmDialog';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: string;
};

export type TeamMemberListStatus = 'loading' | 'error' | 'success';

export type TeamMemberRemoveOutcome = 'success' | 'error' | 'retry';

export type TeamMemberTransferOutcome = 'success' | 'error' | 'retry';

type RowStage = 'idle' | 'confirming' | 'busy' | 'error';

type TransferStage = 'idle' | 'busy' | 'error';

type TeamMemberListProps = {
    status: TeamMemberListStatus;
    members: TeamMember[];
    label: string;
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
    onTransferOwnership: (memberId: number) => Promise<TeamMemberTransferOutcome>;
    transferButtonText: string;
    transferDialogTitle: string;
    transferDialogBody: string;
    transferConfirmText: string;
    transferCancelText: string;
    transferBusyText: string;
    transferErrorText: string;
    transferRetryText: string;
    transferSuccessText: string;
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
    label,
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
    onTransferOwnership,
    transferButtonText,
    transferDialogTitle,
    transferDialogBody,
    transferConfirmText,
    transferCancelText,
    transferBusyText,
    transferErrorText,
    transferRetryText,
    transferSuccessText,
}: TeamMemberListProps) {
    const [rowStages, setRowStages] = useState<Record<number, RowStage>>({});
    const [committedRows, setCommittedRows] = useState<Record<number, boolean>>({});
    const [announcement, setAnnouncement] = useState<string | null>(null);
    const skipNextMembersClearRef = useRef(false);

    const [transferDialogMemberId, setTransferDialogMemberId] = useState<number | null>(null);
    const [transferStage, setTransferStage] = useState<TransferStage>('idle');
    const [transferCommitted, setTransferCommitted] = useState<Record<number, boolean>>({});

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

    function startTransfer(memberId: number) {
        setTransferDialogMemberId(memberId);
        setTransferStage('idle');
        setTransferCommitted((current) => {
            const next = { ...current };
            delete next[memberId];

            return next;
        });
    }

    function closeTransferDialog() {
        setTransferDialogMemberId(null);
        setTransferStage('idle');
    }

    async function confirmTransfer() {
        const memberId = transferDialogMemberId;
        if (memberId === null) {
            return;
        }

        setTransferStage('busy');

        const outcome = await onTransferOwnership(memberId);

        if (outcome === 'success') {
            skipNextMembersClearRef.current = true;
            setAnnouncement(transferSuccessText);
            setTransferCommitted((current) => {
                const next = { ...current };
                delete next[memberId];

                return next;
            });
            closeTransferDialog();

            return;
        }

        setTransferCommitted((current) => ({ ...current, [memberId]: outcome === 'retry' }));
        setTransferStage('error');
    }

    const transferringMember =
        members.find((member) => member.id === transferDialogMemberId) ?? null;
    const transferIsCommitted =
        transferDialogMemberId !== null && (transferCommitted[transferDialogMemberId] ?? false);

    if (status === 'loading') {
        return (
            <div role="region" aria-label={label} className="flex flex-col gap-3">
                <p role="status" className="text-sm text-fg-muted">
                    {loadingText}
                </p>
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div role="region" aria-label={label} className="flex flex-col gap-3">
                <p role="status" className="text-sm font-medium text-fg-danger">
                    {errorText}
                </p>
            </div>
        );
    }

    if (members.length === 0) {
        return (
            <div role="region" aria-label={label} className="flex flex-col gap-3">
                {announcement && (
                    <p role="status" className="text-sm font-medium text-fg-success">
                        {announcement}
                    </p>
                )}
                <p role="status" className="text-sm text-fg-muted">
                    {emptyText}
                </p>
            </div>
        );
    }

    return (
        <div role="region" aria-label={label} className="flex flex-col gap-3">
            {announcement && (
                <p role="status" className="text-sm font-medium text-fg-success">
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
                            className="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-lg border border-border p-3 text-sm text-fg-secondary"
                        >
                            <span className="font-medium text-fg">{member.name}</span>
                            <span className="text-fg-muted">{member.email}</span>
                            <span className="text-fg-muted">{member.role}</span>

                            {removable && stage === 'idle' && (
                                <button
                                    type="button"
                                    className="text-sm font-medium text-fg-danger"
                                    onClick={() => startRemove(member.id)}
                                >
                                    {removeButtonText}
                                </button>
                            )}

                            {removable && stage === 'idle' && (
                                <button
                                    type="button"
                                    className="text-sm font-medium text-fg-link"
                                    onClick={() => startTransfer(member.id)}
                                >
                                    {transferButtonText}
                                </button>
                            )}

                            {removable && stage !== 'idle' && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-fg-danger"
                                        disabled={stage === 'busy'}
                                        onClick={() => void confirmRemove(member.id)}
                                    >
                                        {committedRows[member.id]
                                            ? removeRetryText
                                            : removeConfirmText}
                                    </button>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-fg-secondary"
                                        disabled={stage === 'busy'}
                                        onClick={() => cancelRemove(member.id)}
                                    >
                                        {removeCancelText}
                                    </button>
                                </div>
                            )}

                            {removable && stage === 'busy' && (
                                <span role="status" className="text-sm text-fg-muted">
                                    {removeBusyText}
                                </span>
                            )}

                            {removable && stage === 'error' && (
                                <span role="status" className="text-sm font-medium text-fg-danger">
                                    {removeErrorText}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>

            <ConfirmDialog
                open={transferringMember !== null}
                onClose={closeTransferDialog}
                onConfirm={() => void confirmTransfer()}
                title={transferDialogTitle}
                confirmLabel={transferIsCommitted ? transferRetryText : transferConfirmText}
                cancelLabel={transferCancelText}
                confirmLoading={transferStage === 'busy'}
                destructive={false}
            >
                <p>{transferDialogBody}</p>
                {transferStage === 'busy' && (
                    <p role="status" className="mt-2 text-sm text-fg-muted">
                        {transferBusyText}
                    </p>
                )}
                {transferStage === 'error' && (
                    <p role="status" className="mt-2 text-sm font-medium text-fg-danger">
                        {transferErrorText}
                    </p>
                )}
            </ConfirmDialog>
        </div>
    );
}

export default TeamMemberList;
