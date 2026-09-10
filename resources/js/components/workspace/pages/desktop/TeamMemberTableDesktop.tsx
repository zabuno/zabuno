import { useEffect, useId, useRef, useState, type ReactNode } from 'react';

import { t } from '../../../../i18n/workspace';
import { ConfirmDialog } from '../../../catalog/overlays/compound/ConfirmDialog';
import type { TeamMemberSurface } from '../TeamPage';

/**
 * EKİP TABLOSU — İŞARETLEYİCİ SÜRÜMÜ (`docs/153` §6).
 *
 * ## Bu neden telefon listesinin geniş hâli DEĞİL
 *
 * Telefonda ekibe bakan kişi kişileri TEK TEK okur: dört satır, her satır
 * kendi ritminde, gözü yukarıdan aşağı iner. Masasında oturan kişinin
 * sorusu başkadır ve neredeyse her zaman bir SÜTUN sorusudur: "kim
 * yönetici?", "bu adres kimin?", "burada kimi çıkarabilirim?". O soru
 * hizalanmış bir sütuna bakarak cevaplanır.
 *
 * Aynı satırları 1280 piksele yaymak bu kazancı vermez: alanlar satır
 * içinde akmaya devam eder, göz her satırda yeniden "bu yazan e-posta
 * mıydı, rol müydü?" diye başlar. Bu yüzden burada GERÇEK bir tablo var ve
 * başlıkları GÖRÜNÜR — görünmez bir `aria-label` ekran okuyucuya yeter ama
 * gözle bakan kişiye hiçbir şey söylemez.
 *
 * ## Ortak kalan
 *
 * Veri, yetki kararı, mutasyon yolu ve bütün cümleler paylaşılan sayfadan
 * GELİR (`TeamMemberSurface`). Bu dosyada tek bir `fetch`, tek bir rota ve
 * tek bir `workspaceId` yoktur (`docs/153` §4): sahibin "çıkar" dediğinde
 * giden istek, telefonda giden istekle aynı koddan çıkar.
 *
 * ## Burada OLMAYAN şeyler, bilerek
 *
 * Toplu seçim yok, sağ tık menüsü yok, `hover` ile açılan eylem yok. Eylem
 * satırda HER ZAMAN görünür; gizlenseydi klavyeyle gezen kişi için ayrıca
 * bir odak borcu doğardı ve bu paket onu ödemek için açılmadı.
 */

/*
    SAHİPLİK EDİTÖRE VE YÖNETİCİYE DEVREDİLİR — uç noktanın kendi
    sözleşmesinin aynası (`MembershipRole::ownershipTransferable()`).

    Küme dokunmatik listede de aynen yazılıdır ve İKİ YERDE OLMASI bu
    paketin bilinçli borcudur: kümeyi ortak bir dosyaya taşımak paylaşılan
    `team/TeamMemberList.tsx`'i değiştirmeyi gerektirir ve o dosya bu
    değişiklik paketinin izin verilen kümesinde değildir. Küme yarın
    değişirse İKİSİ birden değişmelidir; ayrışırlarsa bir yüzey reddedilecek
    bir düğme çizer, öteki var olan bir yolu gizler.

    MUTFAK BURADA DA YOK: sunucu da reddediyor.
*/
const TRANSFERABLE_ROLES = ['editor', 'manager'];

type RowStage = 'idle' | 'confirming' | 'busy' | 'error' | 'forbidden' | 'missing';

type TransferStage = 'idle' | 'busy' | 'error';

const CELL = 'px-[var(--space-3)] py-[var(--space-2)] text-body align-middle';

export function TeamMemberTableDesktop({
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
    removeForbiddenText,
    removeMissingText,
    removeSuccessText,
    removeRetryText,
    removableRoles,
    viewerIsOwner,
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
}: TeamMemberSurface) {
    const headingId = useId();
    const [rowStages, setRowStages] = useState<Record<number, RowStage>>({});
    const [committedRows, setCommittedRows] = useState<Record<number, boolean>>({});
    const [roleBusyId, setRoleBusyId] = useState<number | null>(null);
    const [roleErrorId, setRoleErrorId] = useState<number | null>(null);
    const [announcement, setAnnouncement] = useState<string | null>(null);
    const skipNextMembersClearRef = useRef(false);

    const [transferDialogMemberId, setTransferDialogMemberId] = useState<number | null>(null);
    const [transferStage, setTransferStage] = useState<TransferStage>('idle');
    const [transferCommitted, setTransferCommitted] = useState<Record<number, boolean>>({});

    /*
        Liste TAZELENDİĞİNDE duyuru düşer: "Üye çıkarıldı" cümlesi, kendisini
        doğuran listenin yerini yenisi aldıktan sonra da ekranda kalsaydı,
        sahip bir sonraki işleminin de olduğunu sanırdı. Kendi yaptığımız
        tazeleme bunun istisnasıdır ve bir kez atlanır.
    */
    useEffect(() => {
        if (skipNextMembersClearRef.current) {
            skipNextMembersClearRef.current = false;

            return;
        }

        setAnnouncement(null);
    }, [members]);

    async function changeRole(memberId: number, role: string): Promise<void> {
        setRoleBusyId(memberId);
        setRoleErrorId(null);

        const outcome = await onChangeRole(memberId, role);

        setRoleBusyId(null);

        // Başarısızlık SESSİZ kalamaz: kutu eski değerine döner ve kullanıcı
        // değişikliğin olduğunu sanır.
        if (outcome === 'error') {
            setRoleErrorId(memberId);
        }
    }

    function setStage(memberId: number, stage: RowStage) {
        setRowStages((current) => ({ ...current, [memberId]: stage }));
    }

    function forgetRow(memberId: number, map: Record<number, boolean>) {
        const next = { ...map };
        delete next[memberId];

        return next;
    }

    async function confirmRemove(memberId: number): Promise<void> {
        setStage(memberId, 'busy');

        const outcome = await onRemoveMember(memberId);

        if (outcome === 'success') {
            skipNextMembersClearRef.current = true;
            setAnnouncement(removeSuccessText);
            setRowStages((current) => {
                const next = { ...current };
                delete next[memberId];

                return next;
            });
            setCommittedRows((current) => forgetRow(memberId, current));

            return;
        }

        /*
            SUNUCUNUN KESİN CEVABI TEKRAR DENENMEZ (FF-138d). Onay düğmesini
            "Tekrar dene" olarak bırakmak, sahibi aynı cevabı tekrar tekrar
            almaya çağırırdı; satırda kalan tek yol vazgeçmektir.
        */
        if (outcome === 'forbidden' || outcome === 'missing') {
            setCommittedRows((current) => forgetRow(memberId, current));
            setStage(memberId, outcome);

            return;
        }

        setCommittedRows((current) => ({ ...current, [memberId]: outcome === 'retry' }));
        setStage(memberId, 'error');
    }

    function startTransfer(memberId: number) {
        setTransferDialogMemberId(memberId);
        setTransferStage('idle');
        setTransferCommitted((current) => forgetRow(memberId, current));
    }

    function closeTransferDialog() {
        setTransferDialogMemberId(null);
        setTransferStage('idle');
    }

    async function confirmTransfer(): Promise<void> {
        const memberId = transferDialogMemberId;

        if (memberId === null) {
            return;
        }

        setTransferStage('busy');

        const outcome = await onTransferOwnership(memberId);

        if (outcome === 'success') {
            skipNextMembersClearRef.current = true;
            setAnnouncement(transferSuccessText);
            setTransferCommitted((current) => forgetRow(memberId, current));
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

    function frame(children: ReactNode) {
        return (
            <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
                <h2 id={headingId} className="text-body font-bold text-fg">
                    {label}
                </h2>
                {children}
            </div>
        );
    }

    if (status === 'loading') {
        return frame(
            <p role="status" className="text-body text-fg-muted">
                {loadingText}
            </p>,
        );
    }

    if (status === 'error') {
        return frame(
            <p role="status" className="text-body font-medium text-fg-danger">
                {errorText}
            </p>,
        );
    }

    if (members.length === 0) {
        return frame(
            <>
                {announcement && (
                    <p role="status" className="text-body font-medium text-fg-success">
                        {announcement}
                    </p>
                )}
                <p role="status" className="text-body text-fg-muted">
                    {emptyText}
                </p>
            </>,
        );
    }

    return frame(
        <>
            {announcement && (
                <p role="status" className="text-body font-medium text-fg-success">
                    {announcement}
                </p>
            )}
            <div className="overflow-x-auto rounded-[var(--radius-lg)] border border-border">
                <table aria-labelledby={headingId} className="w-full border-collapse text-start">
                    <thead>
                        {/*
                            BAŞLIKLAR GÖRÜNÜRDÜR (`docs/109` §6.4 ile aynı
                            gerekçe). Masada oturan kişi sütun arasında tam
                            olarak bu dört kelimeye bakarak gezinir; onlar
                            olmadan tablo yalnız hizalanmış bir listedir.
                        */}
                        <tr className="bg-surface-subtle text-start">
                            <th scope="col" className={`${CELL} font-bold text-fg`}>
                                {t('workspace.team.members.column.name')}
                            </th>
                            <th scope="col" className={`${CELL} font-bold text-fg`}>
                                {t('workspace.team.members.column.email')}
                            </th>
                            <th scope="col" className={`${CELL} font-bold text-fg`}>
                                {t('workspace.team.members.column.role')}
                            </th>
                            <th scope="col" className={`${CELL} font-bold text-fg`}>
                                {t('workspace.team.members.column.actions')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => {
                            const stage = rowStages[member.id] ?? 'idle';
                            /*
                                Sahibin rolü buradan değişmez ve sahip
                                çıkarılmaz: sahiplik DEVREDİLİR, silinmez —
                                sahipsiz kalan bir çalışma alanını kimse
                                onaramaz. Geniş ekran bu sınırı gevşetmez.
                            */
                            const roleEditable = member.role.toLowerCase() !== 'owner';
                            const removable =
                                viewerIsOwner && removableRoles.includes(member.role.toLowerCase());
                            const transferable =
                                viewerIsOwner &&
                                TRANSFERABLE_ROLES.includes(member.role.toLowerCase());
                            const rejected = stage === 'forbidden' || stage === 'missing';

                            return (
                                <tr
                                    key={member.id}
                                    className="border-t border-border text-fg-secondary"
                                >
                                    <th
                                        scope="row"
                                        className={`${CELL} text-start font-medium text-fg`}
                                    >
                                        {member.name}
                                    </th>
                                    <td className={`${CELL} text-fg-muted`}>{member.email}</td>
                                    <td className={CELL}>
                                        {roleEditable ? (
                                            <label className="flex items-center gap-[var(--space-1)]">
                                                <span className="sr-only">
                                                    {roleLabelFor(member.name)}
                                                </span>
                                                <select
                                                    className="rounded-md border border-border bg-surface px-[var(--space-2)] py-[var(--space-1)] text-body text-fg"
                                                    value={member.role}
                                                    disabled={roleBusyId === member.id}
                                                    onChange={(event) =>
                                                        void changeRole(
                                                            member.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    {/*
                                                        MEVCUT rol dağıtılabilir
                                                        listede olmayabilir:
                                                        `member` yalnız eski
                                                        kayıtların taşıdığı salt
                                                        okunur bir roldür. Onu
                                                        listeden çıkarmak satırın
                                                        kişiyi başka bir rolde
                                                        göstermesine yol açardı —
                                                        yani ekran yalan söylerdi.
                                                    */}
                                                    {assignableRoles.some(
                                                        (option) => option.value === member.role,
                                                    ) ? null : (
                                                        <option value={member.role} disabled>
                                                            {member.role}
                                                        </option>
                                                    )}
                                                    {assignableRoles.map((option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                        ) : (
                                            <span className="text-fg-muted">{member.role}</span>
                                        )}
                                        {roleErrorId === member.id ? (
                                            <span
                                                role="alert"
                                                className="block text-body text-fg-danger"
                                            >
                                                {roleErrorText}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className={CELL}>
                                        <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                                            {removable && stage === 'idle' && (
                                                <button
                                                    type="button"
                                                    className="text-body font-medium text-fg-danger"
                                                    onClick={() =>
                                                        setStage(member.id, 'confirming')
                                                    }
                                                >
                                                    {removeButtonText}
                                                </button>
                                            )}

                                            {transferable && stage === 'idle' && (
                                                <button
                                                    type="button"
                                                    className="text-body font-medium text-fg-link"
                                                    onClick={() => startTransfer(member.id)}
                                                >
                                                    {transferButtonText}
                                                </button>
                                            )}

                                            {removable && !rejected && stage !== 'idle' && (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="text-body font-medium text-fg-danger"
                                                        disabled={stage === 'busy'}
                                                        onClick={() =>
                                                            void confirmRemove(member.id)
                                                        }
                                                    >
                                                        {committedRows[member.id]
                                                            ? removeRetryText
                                                            : removeConfirmText}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="text-body font-medium text-fg-secondary"
                                                        disabled={stage === 'busy'}
                                                        onClick={() => setStage(member.id, 'idle')}
                                                    >
                                                        {removeCancelText}
                                                    </button>
                                                </>
                                            )}

                                            {removable && stage === 'busy' && (
                                                <span
                                                    role="status"
                                                    className="text-body text-fg-muted"
                                                >
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

                                            {/*
                                                REDDİN KENDİSİ YAZILIR, "bir
                                                şeyler ters gitti" değil: ya bu
                                                iş sahibinindir ve ondan
                                                istenir, ya da o üyelik zaten
                                                listede yoktur. Yanında duran
                                                tek düğme vazgeçmektir.
                                            */}
                                            {removable && rejected && (
                                                <>
                                                    <span
                                                        role="status"
                                                        className="text-body font-medium text-fg-danger"
                                                    >
                                                        {stage === 'forbidden'
                                                            ? removeForbiddenText
                                                            : removeMissingText}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className="text-body font-medium text-fg-secondary"
                                                        onClick={() => setStage(member.id, 'idle')}
                                                    >
                                                        {removeCancelText}
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

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
        </>,
    );
}

export default TeamMemberTableDesktop;
