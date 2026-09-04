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

export type TeamMemberRoleOutcome = 'success' | 'error';

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
    /*
        Yanlış verilmiş bir rolü DÜZELTMEK (`docs/83`, P1-07).

        Önceden tek çare üyeyi silip yeniden davet etmekti: kişi erişimini
        kaybediyor, yeni bir davet bekliyor ve bu sırada iş duruyordu.
    */
    onChangeRole: (memberId: number, role: string) => Promise<TeamMemberRoleOutcome>;
    assignableRoles: { value: string; label: string }[];
    roleLabelFor: (name: string) => string;
    roleErrorText: string;
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
    onChangeRole,
    assignableRoles,
    roleLabelFor,
    roleErrorText,
}: TeamMemberListProps) {
    const [rowStages, setRowStages] = useState<Record<number, RowStage>>({});
    const [roleBusyId, setRoleBusyId] = useState<number | null>(null);
    const [roleErrorId, setRoleErrorId] = useState<number | null>(null);

    async function changeRole(memberId: number, role: string): Promise<void> {
        setRoleBusyId(memberId);
        setRoleErrorId(null);

        const outcome = await onChangeRole(memberId, role);

        setRoleBusyId(null);

        // Başarısızlık SESSİZ kalamaz: seçim kutusu eski değerine döner ve
        // kullanıcı değişikliğin olduğunu sanır.
        if (outcome === 'error') {
            setRoleErrorId(memberId);
        }
    }
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
                <p role="status" className="text-body text-fg-muted">
                    {loadingText}
                </p>
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div role="region" aria-label={label} className="flex flex-col gap-3">
                <p role="status" className="text-body font-medium text-fg-danger">
                    {errorText}
                </p>
            </div>
        );
    }

    if (members.length === 0) {
        return (
            <div role="region" aria-label={label} className="flex flex-col gap-3">
                {announcement && (
                    <p role="status" className="text-body font-medium text-fg-success">
                        {announcement}
                    </p>
                )}
                <p role="status" className="text-body text-fg-muted">
                    {emptyText}
                </p>
            </div>
        );
    }

    return (
        <div role="region" aria-label={label} className="flex flex-col gap-3">
            {announcement && (
                <p role="status" className="text-body font-medium text-fg-success">
                    {announcement}
                </p>
            )}
            {/*
                SATIR KART DEĞİLDİR (FF-131, teslim paketinin düzeni).

                Her üye kendi kenarlıklı, yuvarlatılmış kutusundaydı ve
                aralarında boşluk vardı: dört kişilik bir takım dört ayrı
                kart gibi okunuyor, göz her satırda yeniden "bu nedir?" diye
                başlıyordu. Kutuların sınırı bilgi taşımıyordu; kişiler zaten
                bir aradaydı.

                Paketin düzeni bir LİSTEDİR: ince ayraçlar, baş harf dairesi,
                tek ritim.
            */}
            <ul className="flex flex-col">
                {members.map((member) => {
                    const stage = rowStages[member.id] ?? 'idle';
                    // Sahibin rolü buradan değişmez: sahiplik DEVREDİLİR ve
                    // sahipsiz kalan bir çalışma alanını kimse onaramaz.
                    const roleEditable = member.role.toLowerCase() !== 'owner';
                    const removable = member.role.toLowerCase() === REMOVABLE_ROLE;

                    return (
                        <li
                            key={member.id}
                            className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-3 gap-y-1 border-t border-border px-[var(--density-padding-inline)] py-[var(--space-2)] text-body text-fg-secondary first:border-t-0"
                        >
                            {/*
                                BAŞ HARF DEKORATİFTİR: ad zaten satırda
                                yazıyor ve ekran okuyucunun aynı bilgiyi iki
                                kez okuması, listeyi iki kat uzatır.
                            */}
                            <span
                                aria-hidden="true"
                                data-avatar-initial="true"
                                /*
                                    40 piksel: AEP ölçeğinde 32 (space-6) ile
                                    48 (space-7) arasında bir ara basamak yok
                                    ve daire ikisinde de yanlış duruyor —
                                    32 satırda kaybolur, 48 satırı şişirir.
                                    Teslim paketinin kendi paneli de tam 40
                                    kullanıyor, bu yüzden ölçü ondan alındı.
                                */
                                className="inline-flex h-10 w-10 flex-none items-center justify-center rounded-full bg-surface-subtle text-body font-bold text-fg-secondary"
                            >
                                {member.name.slice(0, 1).toLocaleUpperCase('tr-TR')}
                            </span>
                            <span className="font-medium text-fg">{member.name}</span>
                            <span className="text-fg-muted">{member.email}</span>
                            {roleEditable ? (
                                <label className="flex items-center gap-1">
                                    <span className="sr-only">{roleLabelFor(member.name)}</span>
                                    <select
                                        className="rounded-md border border-border bg-surface px-2 py-1 text-body text-fg"
                                        value={member.role}
                                        disabled={roleBusyId === member.id}
                                        onChange={(event) =>
                                            void changeRole(member.id, event.target.value)
                                        }
                                    >
                                        {/*
                                            MEVCUT rol dağıtılabilir listede
                                            olmayabilir: `member`, yalnız eski
                                            kayıtların taşıdığı salt okunur bir
                                            roldür ve yeni kimseye verilmez.

                                            Onu listeden çıkarmak, satırın
                                            kişiyi "Editor" gibi göstermesine
                                            yol açardı — yani ekran yalan
                                            söylerdi. Devre dışı bir seçenek
                                            olarak gösterilir: gerçek okunur,
                                            ama geri seçilemez.
                                        */}
                                        {assignableRoles.some(
                                            (option) => option.value === member.role,
                                        ) ? null : (
                                            <option value={member.role} disabled>
                                                {member.role}
                                            </option>
                                        )}
                                        {assignableRoles.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ) : (
                                <span className="text-fg-muted">{member.role}</span>
                            )}

                            {roleErrorId === member.id ? (
                                <span role="alert" className="text-body text-fg-danger">
                                    {roleErrorText}
                                </span>
                            ) : null}

                            {removable && stage === 'idle' && (
                                <button
                                    type="button"
                                    className="text-body font-medium text-fg-danger"
                                    onClick={() => startRemove(member.id)}
                                >
                                    {removeButtonText}
                                </button>
                            )}

                            {removable && stage === 'idle' && (
                                <button
                                    type="button"
                                    className="text-body font-medium text-fg-link"
                                    onClick={() => startTransfer(member.id)}
                                >
                                    {transferButtonText}
                                </button>
                            )}

                            {removable && stage !== 'idle' && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        className="text-body font-medium text-fg-danger"
                                        disabled={stage === 'busy'}
                                        onClick={() => void confirmRemove(member.id)}
                                    >
                                        {committedRows[member.id]
                                            ? removeRetryText
                                            : removeConfirmText}
                                    </button>
                                    <button
                                        type="button"
                                        className="text-body font-medium text-fg-secondary"
                                        disabled={stage === 'busy'}
                                        onClick={() => cancelRemove(member.id)}
                                    >
                                        {removeCancelText}
                                    </button>
                                </div>
                            )}

                            {removable && stage === 'busy' && (
                                <span role="status" className="text-body text-fg-muted">
                                    {removeBusyText}
                                </span>
                            )}

                            {removable && stage === 'error' && (
                                <span
                                    role="status"
                                    className="text-body font-medium text-fg-danger"
                                >
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
                    <p role="status" className="mt-2 text-body text-fg-muted">
                        {transferBusyText}
                    </p>
                )}
                {transferStage === 'error' && (
                    <p role="status" className="mt-2 text-body font-medium text-fg-danger">
                        {transferErrorText}
                    </p>
                )}
            </ConfirmDialog>
        </div>
    );
}

export default TeamMemberList;
