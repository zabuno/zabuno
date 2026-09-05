import { useEffect, useId, useRef, useState } from 'react';
import { ConfirmDialog } from '../../../catalog/overlays/compound/ConfirmDialog';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: string;
};

export type TeamMemberListStatus = 'loading' | 'error' | 'success';

/**
 * `forbidden` ve `missing`, `error`'dan AYRI durur (FF-138d).
 *
 * Üçü de "olmadı" demez: `error` geçici bir aksaklıktır ve tekrar denemek
 * anlamlıdır; diğer ikisi sunucunun kesin cevabıdır ve tekrar denemek aynı
 * cevabı getirir. Tek bir `error` altında toplansalardı ekran, sahibi sonu
 * olmayan bir "tekrar dene" döngüsüne çağırırdı.
 */
export type TeamMemberRemoveOutcome = 'success' | 'error' | 'retry' | 'forbidden' | 'missing';

export type TeamMemberTransferOutcome = 'success' | 'error' | 'retry';

export type TeamMemberRoleOutcome = 'success' | 'error';

type RowStage = 'idle' | 'confirming' | 'busy' | 'error' | 'forbidden' | 'missing';

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
    removeForbiddenText: string;
    removeMissingText: string;
    removeSuccessText: string;
    removeRetryText: string;
    /**
     * Sunucunun ÇIKARABİLDİĞİ roller — `MembershipRole::removable()`.
     *
     * Küme burada sabit yazılmaz, çağırandan gelir: bileşen kendi listesini
     * tutsaydı, sunucunun kaldırabildiği yeni bir rol doğduğunda ekran onu
     * çıkarılamaz göstermeye devam ederdi.
     *
     * "Davet edilebilir" ile karıştırmayın — ikisi bir zamanlar aynı listeydi
     * ve eski `member` rolündeki kişiler bu yüzden ekipte mahsur kaldı.
     */
    removableRoles: string[];
    /**
     * Oturumdaki kişi çalışma alanının SAHİBİ mi?
     *
     * `workspace.manage` iznini Yönetici de taşır ve bu ekran ona da açıktır;
     * ama ekipten çıkarmak ve sahipliği devretmek yalnız sahibin işidir ve uç
     * nokta yöneticiye 403 döner. Yapılamayan iş çizilmez (`docs/98` FF-74).
     */
    viewerIsOwner: boolean;
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

/*
    SAHİPLİK EDİTÖRE VE YÖNETİCİYE DEVREDİLİR.

    Bu küme uç noktanın kendi sözleşmesinin aynasıdır
    (`MembershipRole::ownershipTransferable()`) ve çıkarma kümesiyle bir
    ilgisi yoktur. İkisi bir zamanlar aynı bayrağı paylaşıyordu; çıkarma
    yönetici ve mutfağı da kapsayınca ayrıldılar, çünkü aynı bayrağı
    paylaşmaya devam etselerdi ekran bir aşçıya "sahipliği devret" diye düğme
    çizer, tıklandığında sunucu reddederdi.

    Liste bir süre yalnız `editor` idi ve bu ekranda ters yönde bir hata
    üretiyordu: sunucu kabul etseydi bile yönetici satırında düğme YOKTU —
    yani yapılabilen bir iş yok gibi görünüyordu. Sunucu kısıtı FF-144'te
    düzelince buranın da düzelmesi gerekti; iki taraf ayrı kalırsa ekran ya
    reddedilecek bir tıklama sunar ya da var olan bir yolu gizler.

    MUTFAK BURADA DA YOK: sunucu da reddediyor. Sınır sunucudadır, düğmeyi
    çizmemek onun ekrandaki karşılığıdır — koruma değil.
*/
const TRANSFERABLE_ROLES = ['editor', 'manager'];

/**
 * Presentational: renders whatever member rows/status text it is given.
 * No fetch, route, or workspace-id knowledge — TeamPage owns the real
 * GET/DELETE /api/workspaces/{workspaceId}/team/members calls and passes
 * the resulting status/members/labels plus a remove callback through. Rows
 * expose a Remove control only for the roles the server can actually remove
 * (`removableRoles`) and only to the workspace owner; the Owner row never
 * does — ownership is transferred, not deleted.
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
}: TeamMemberListProps) {
    /*
        BAŞLIK GÖRÜNÜR VE BÖLGENİN ADIDIR (`docs/109` §6.4, kaynağın
        "Üyeler" kart başlığı). Önce bölgenin adı yalnız görünmez bir
        `aria-label`'daydı: gözle bakan biri kartın neyin kartı olduğunu
        ancak içindeki satırlardan çıkarabiliyordu.
    */
    const headingId = useId();
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

        /*
            SUNUCUNUN KESİN CEVABI TEKRAR DENENMEZ. Onay düğmesini "Tekrar
            dene" olarak bırakmak, sahibi aynı cevabı tekrar tekrar almaya
            çağırırdı; satırda kalan tek yol vazgeçmektir.
        */
        if (outcome === 'forbidden' || outcome === 'missing') {
            setCommittedRows((current) => {
                const next = { ...current };
                delete next[memberId];

                return next;
            });
            setRowStages((current) => ({ ...current, [memberId]: outcome }));

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
            <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
                <h2 id={headingId} className="text-body font-bold text-fg">
                    {label}
                </h2>
                <p role="status" className="text-body text-fg-muted">
                    {loadingText}
                </p>
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
                <h2 id={headingId} className="text-body font-bold text-fg">
                    {label}
                </h2>
                <p role="status" className="text-body font-medium text-fg-danger">
                    {errorText}
                </p>
            </div>
        );
    }

    if (members.length === 0) {
        return (
            <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
                <h2 id={headingId} className="text-body font-bold text-fg">
                    {label}
                </h2>
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
        <div role="region" aria-labelledby={headingId} className="flex flex-col gap-3">
            <h2 id={headingId} className="text-body font-bold text-fg">
                {label}
            </h2>
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
                    /*
                        ÇIKARILABİLİR KÜME = SUNUCUNUN ÇIKARABİLDİĞİ ROLLER.

                        Burada bir zamanlar tek bir `=== 'editor'` vardı ve o
                        satır yazıldığında davet edilebilen tek rol Editör'dü.
                        Sonra Yönetici ve Mutfak doğdu; sunucu ikisini de
                        çıkarabilir hâle geldi ama ekran düğmeyi çizmedi.
                        Sahibin, işten ayrılan bir yöneticiyi ekipten
                        çıkarmasının hiçbir yolu yoktu.

                        Küme artık davet listesinden gelir — yani bu körlük
                        aynı yoldan bir daha doğamaz.
                    */
                    const removable =
                        viewerIsOwner && removableRoles.includes(member.role.toLowerCase());
                    const transferable =
                        viewerIsOwner && TRANSFERABLE_ROLES.includes(member.role.toLowerCase());
                    const rejected = stage === 'forbidden' || stage === 'missing';

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
                                className="inline-flex h-10 w-10 flex-none items-center justify-center rounded-pill bg-surface-subtle text-body font-bold text-fg-secondary"
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

                            {/*
                                REDDİN KENDİSİ YAZILIR, "bir şeyler ters
                                gitti" değil. Sahip iki farklı gerçekten
                                birini okur — ya bu iş onun değildir ve
                                sahibinden istemesi gerekir, ya da o üyelik
                                zaten listede yoktur. Yanında duran tek düğme
                                vazgeçmektir: tekrar denemek aynı cevabı
                                getirir.
                            */}
                            {removable && rejected && (
                                <div className="flex flex-wrap items-center gap-2">
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
                                        onClick={() => cancelRemove(member.id)}
                                    >
                                        {removeCancelText}
                                    </button>
                                </div>
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
