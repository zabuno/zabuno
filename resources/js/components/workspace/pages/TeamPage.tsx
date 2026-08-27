import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../lib/csrfHeader';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';
import {
    TeamMemberList,
    type TeamMember,
    type TeamMemberListStatus,
    type TeamMemberRemoveOutcome,
    type TeamMemberTransferOutcome,
} from './team/TeamMemberList';
import {
    TeamInvitationList,
    type TeamInvitation,
    type TeamInvitationListStatus,
    type TeamInvitationCancelOutcome,
} from './team/TeamInvitationList';

type TeamPageProps = {
    workspaceId?: number;
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Real server-authoritative member list and pending invitations: GET
 * /api/workspaces/{workspaceId}/team/members and
 * /api/workspaces/{workspaceId}/team/invitations on mount, same-origin
 * credentials, honest loading/error/empty/populated states — no fabricated
 * rows. Only an Editor invitation may be created from this UI; ownership
 * transfer is a separate flow.
 */
export function TeamPage({ workspaceId }: TeamPageProps) {
    const emailId = useId();
    const [email, setEmail] = useState('');
    const [membersStatus, setMembersStatus] = useState<TeamMemberListStatus>('loading');
    const [members, setMembers] = useState<TeamMember[]>([]);
    const [invitationsStatus, setInvitationsStatus] = useState<TeamInvitationListStatus>('loading');
    const [invitations, setInvitations] = useState<TeamInvitation[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(false);
    const [submitSuccess, setSubmitSuccess] = useState(false);

    const invitationsRequestRef = useRef(0);
    const membersRequestRef = useRef(0);
    const committedInvitationCancelsRef = useRef<Set<number>>(new Set());
    const committedMemberRemovalsRef = useRef<Set<number>>(new Set());
    const committedMemberTransfersRef = useRef<Set<number>>(new Set());

    useEffect(() => {
        committedInvitationCancelsRef.current = new Set();
        committedMemberRemovalsRef.current = new Set();
        committedMemberTransfersRef.current = new Set();
    }, [workspaceId]);

    const fetchInvitations = useCallback(async () => {
        if (workspaceId === undefined) {
            return;
        }

        const requestId = ++invitationsRequestRef.current;
        setInvitationsStatus('loading');

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/team/invitations`, {
                credentials: 'same-origin',
            });

            if (invitationsRequestRef.current !== requestId) {
                return;
            }

            if (!response.ok) {
                setInvitationsStatus('error');

                return;
            }

            const body = (await response.json()) as TeamInvitation[];

            setInvitations(body);
            setInvitationsStatus('success');
        } catch {
            if (invitationsRequestRef.current === requestId) {
                setInvitationsStatus('error');
            }
        }
    }, [workspaceId]);

    const fetchMembers = useCallback(async () => {
        if (workspaceId === undefined) {
            return;
        }

        const requestId = ++membersRequestRef.current;
        setMembersStatus('loading');

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/team/members`, {
                credentials: 'same-origin',
            });

            if (membersRequestRef.current !== requestId) {
                return;
            }

            if (!response.ok) {
                setMembersStatus('error');

                return;
            }

            const body = (await response.json()) as TeamMember[];

            setMembers(body);
            setMembersStatus('success');
        } catch {
            if (membersRequestRef.current === requestId) {
                setMembersStatus('error');
            }
        }
    }, [workspaceId]);

    useEffect(() => {
        void (async () => {
            await fetchMembers();
        })();
    }, [fetchMembers]);

    useEffect(() => {
        void (async () => {
            await fetchInvitations();
        })();
    }, [fetchInvitations]);

    const refetchMembersAuthoritative = useCallback(async (): Promise<TeamMember[] | null> => {
        if (workspaceId === undefined) {
            return null;
        }

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/team/members`, {
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return null;
            }

            const body = (await response.json()) as TeamMember[];

            setMembers(body);
            setMembersStatus('success');

            return body;
        } catch {
            return null;
        }
    }, [workspaceId]);

    const refetchInvitationsAuthoritative = useCallback(async (): Promise<
        TeamInvitation[] | null
    > => {
        if (workspaceId === undefined) {
            return null;
        }

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/team/invitations`, {
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return null;
            }

            const body = (await response.json()) as TeamInvitation[];

            setInvitations(body);
            setInvitationsStatus('success');

            return body;
        } catch {
            return null;
        }
    }, [workspaceId]);

    const removeMember = useCallback(
        async (memberId: number): Promise<TeamMemberRemoveOutcome> => {
            if (workspaceId === undefined) {
                return 'error';
            }

            const alreadyCommitted = committedMemberRemovalsRef.current.has(memberId);

            if (!alreadyCommitted) {
                try {
                    await bootstrapCsrfCookie();

                    const requestInit = buildAuthRequestInit({ method: 'DELETE' });
                    const response = await fetch(
                        `/api/workspaces/${workspaceId}/team/members/${memberId}`,
                        {
                            ...requestInit,
                            credentials: 'same-origin',
                        },
                    );

                    if (!response.ok) {
                        return 'error';
                    }

                    committedMemberRemovalsRef.current.add(memberId);
                } catch {
                    return 'error';
                }
            }

            const rows = await refetchMembersAuthoritative();

            if (rows === null || rows.some((member) => member.id === memberId)) {
                return 'retry';
            }

            committedMemberRemovalsRef.current.delete(memberId);

            return 'success';
        },
        [workspaceId, refetchMembersAuthoritative],
    );

    const transferOwnership = useCallback(
        async (memberId: number): Promise<TeamMemberTransferOutcome> => {
            if (workspaceId === undefined) {
                return 'error';
            }

            const alreadyCommitted = committedMemberTransfersRef.current.has(memberId);

            if (!alreadyCommitted) {
                try {
                    await bootstrapCsrfCookie();

                    const requestInit = buildAuthRequestInit({ method: 'POST' });
                    const response = await fetch(
                        `/api/workspaces/${workspaceId}/team/members/${memberId}/transfer-ownership`,
                        {
                            ...requestInit,
                            credentials: 'same-origin',
                        },
                    );

                    if (!response.ok) {
                        return 'error';
                    }

                    committedMemberTransfersRef.current.add(memberId);
                } catch {
                    return 'error';
                }
            }

            const rows = await refetchMembersAuthoritative();

            if (rows === null) {
                return 'retry';
            }

            const promotedMember = rows.find((member) => member.id === memberId);
            const ownerCount = rows.filter(
                (member) => member.role.toLowerCase() === 'owner',
            ).length;

            if (
                promotedMember === undefined ||
                promotedMember.role.toLowerCase() !== 'owner' ||
                ownerCount !== 1
            ) {
                return 'retry';
            }

            committedMemberTransfersRef.current.delete(memberId);

            return 'success';
        },
        [workspaceId, refetchMembersAuthoritative],
    );

    const cancelInvitation = useCallback(
        async (invitationId: number): Promise<TeamInvitationCancelOutcome> => {
            if (workspaceId === undefined) {
                return 'error';
            }

            const alreadyCommitted = committedInvitationCancelsRef.current.has(invitationId);

            if (!alreadyCommitted) {
                try {
                    await bootstrapCsrfCookie();

                    const requestInit = buildAuthRequestInit({ method: 'DELETE' });
                    const response = await fetch(
                        `/api/workspaces/${workspaceId}/team/invitations/${invitationId}`,
                        {
                            ...requestInit,
                            credentials: 'same-origin',
                        },
                    );

                    if (!response.ok) {
                        return 'error';
                    }

                    committedInvitationCancelsRef.current.add(invitationId);
                } catch {
                    return 'error';
                }
            }

            const rows = await refetchInvitationsAuthoritative();

            if (rows === null || rows.some((invitation) => invitation.id === invitationId)) {
                return 'retry';
            }

            committedInvitationCancelsRef.current.delete(invitationId);

            return 'success';
        },
        [workspaceId, refetchInvitationsAuthoritative],
    );

    const emailIsValid = EMAIL_PATTERN.test(email);

    async function handleInvite() {
        if (workspaceId === undefined || !emailIsValid) {
            return;
        }

        setSubmitting(true);
        setSubmitError(false);
        setSubmitSuccess(false);

        try {
            await bootstrapCsrfCookie();

            const requestInit = buildAuthRequestInit({
                method: 'POST',
                body: JSON.stringify({ email, role: 'editor' }),
            });
            const headers = new Headers(requestInit.headers);
            headers.set('Content-Type', 'application/json');

            const response = await fetch(`/api/workspaces/${workspaceId}/team/invitations`, {
                ...requestInit,
                credentials: 'same-origin',
                headers,
            });

            if (!response.ok) {
                setSubmitError(true);

                return;
            }

            await fetchInvitations();
            setEmail('');
            setSubmitSuccess(true);
        } catch {
            setSubmitError(true);
        } finally {
            setSubmitting(false);
        }
    }

    const teamStatusBadge: WorkspacePageStatusBadge | null =
        invitationsStatus === 'loading'
            ? { key: 'team-status', status: 'info', label: t('workspace.team.status.loading') }
            : invitationsStatus === 'error'
              ? { key: 'team-status', status: 'error', label: t('workspace.team.status.error') }
              : // Başarı hâlinde rozet YOK. Buraya önceden "Invitations
                // connected" basılıyordu: bu bir kullanıcı durumu değil,
                // kablolamanın çalıştığına dair bir MÜHENDİS notudur. Üye
                // listesinin ekranda olması bağlantının kurulduğunu zaten
                // kanıtlar.
                null;
    const badges: WorkspacePageStatusBadge[] = teamStatusBadge ? [teamStatusBadge] : [];

    const operationalDescription =
        invitationsStatus === 'loading'
            ? t('workspace.team.operational.description.loading')
            : invitationsStatus === 'error'
              ? t('workspace.team.operational.description.error')
              : t('workspace.team.operational.description.connected');

    return (
        <div id="section-team">
            <WorkspacePageFrame
                title={t('workspace.team.heading')}
                description={operationalDescription}
                badges={badges}
            >
                <fieldset className="flex flex-col gap-4 border-0 p-0 m-0">
                    <legend className="mb-1 text-body font-semibold text-fg">
                        {t('workspace.team.invite.section')}
                    </legend>

                    <div>
                        <div className="mb-2 block">
                            <Label htmlFor={emailId}>{t('workspace.team.invite.email')}</Label>
                        </div>
                        <TextInput
                            id={emailId}
                            name="invite-email"
                            type="email"
                            value={email}
                            onChange={(event) => {
                                setEmail(event.target.value);
                                setSubmitError(false);
                                setSubmitSuccess(false);
                            }}
                        />
                    </div>

                    <Button
                        className="w-full"
                        disabled={!emailIsValid || submitting}
                        onClick={() => void handleInvite()}
                    >
                        {t('workspace.team.invite.button')}
                    </Button>

                    {submitting && (
                        <p role="status" className="text-body text-fg-muted">
                            {t('workspace.team.invite.submitting')}
                        </p>
                    )}

                    {!submitting && submitError && (
                        <p role="status" className="text-body font-medium text-fg-danger">
                            {t('workspace.team.invite.error')}
                        </p>
                    )}

                    {!submitting && submitSuccess && (
                        <p role="status" className="text-body font-medium text-fg-success">
                            {t('workspace.team.invite.success')}
                        </p>
                    )}
                </fieldset>

                <TeamInvitationList
                    status={invitationsStatus}
                    invitations={invitations}
                    label={t('workspace.team.pendingInvitations.region')}
                    loadingText={t('workspace.team.invitations.loading')}
                    errorText={t('workspace.team.invitations.error')}
                    emptyText={t('workspace.team.invitations.empty')}
                    onCancelInvitation={cancelInvitation}
                    cancelButtonText={t('workspace.team.invitations.cancel.button')}
                    cancelConfirmText={t('workspace.team.invitations.cancel.confirm')}
                    cancelKeepText={t('workspace.team.invitations.cancel.keep')}
                    cancelBusyText={t('workspace.team.invitations.cancel.busy')}
                    cancelErrorText={t('workspace.team.invitations.cancel.error')}
                    cancelSuccessText={t('workspace.team.invitations.cancel.success')}
                    cancelRetryText={t('workspace.team.invitations.cancel.retry')}
                />

                <TeamMemberList
                    status={membersStatus}
                    members={members}
                    label={t('workspace.team.members.region')}
                    loadingText={t('workspace.team.members.loading')}
                    errorText={t('workspace.team.members.error')}
                    emptyText={t('workspace.team.members.empty')}
                    onRemoveMember={removeMember}
                    removeButtonText={t('workspace.team.members.remove.button')}
                    removeConfirmText={t('workspace.team.members.remove.confirm')}
                    removeCancelText={t('workspace.team.members.remove.cancel')}
                    removeBusyText={t('workspace.team.members.remove.busy')}
                    removeErrorText={t('workspace.team.members.remove.error')}
                    removeSuccessText={t('workspace.team.members.remove.success')}
                    removeRetryText={t('workspace.team.members.remove.retry')}
                    onTransferOwnership={transferOwnership}
                    transferButtonText={t('workspace.team.members.transfer.button')}
                    transferDialogTitle={t('workspace.team.members.transfer.title')}
                    transferDialogBody={t('workspace.team.members.transfer.body')}
                    transferConfirmText={t('workspace.team.members.transfer.confirm')}
                    transferCancelText={t('workspace.team.members.transfer.cancel')}
                    transferBusyText={t('workspace.team.members.transfer.busy')}
                    transferErrorText={t('workspace.team.members.transfer.error')}
                    transferRetryText={t('workspace.team.members.transfer.retry')}
                    transferSuccessText={t('workspace.team.members.transfer.success')}
                />
            </WorkspacePageFrame>
        </div>
    );
}

export default TeamPage;
