import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../lib/csrfHeader';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';
import { TeamRoleGuide, type TeamRoleKey } from './team/TeamRoleGuide';
import {
    TeamMemberList,
    type TeamMember,
    type TeamMemberListStatus,
    type TeamMemberRemoveOutcome,
    type TeamMemberRoleOutcome,
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
    /**
     * Oturumdaki kişinin BU çalışma alanındaki rolü — `GET /workspace-context`
     * gövdesinin `role` alanı, kabuktan geçirilir.
     *
     * Neden izin listesi değil: `workspace.manage` iznini Yönetici de taşır
     * ve bu ekran ona da açıktır. Ama ekipten çıkarmak sahibin kararıdır ve
     * uç nokta yöneticiye 403 döner; yani "yapabilir mi?" sorusunun cevabı
     * izin listesinde YOK. Sunucu rolü zaten izin kümesinden geri okuyarak
     * üretiyor, karar yine oradan geliyor.
     *
     * TANIMSIZ = SÜZME YOK (`docs/98` FF-74 sözleşmesinin kendi kuralı, bkz.
     * `WorkspaceApp` içindeki `can`). Eski gövdeler ve bileşen testleri rol
     * bilgisi taşımaz; sessizce her şeyi gizlemek, yetkisiz göstermekten
     * kötü olurdu. `null` ise SÜZÜLÜR: sunucu "bu izin kümesi hiçbir role
     * uymuyor" demiştir ve tanınmayan bir role sahiplik yetkisi verilemez.
     */
    viewerRole?: string | null;
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
type InvitableRole = 'editor' | 'manager' | 'kitchen';

/**
 * Davet edilebilir roller — `App\Domain\Tenancy\MembershipRole::invitable()`.
 *
 * `owner` listede YOK: sahiplik davetle verilmez, devredilir. `member` de
 * yok: yeni kimse salt okunur bir role davet edilmemeli.
 *
 * Kaynağın dördüncü rolü "Mutfak" bir süre burada da yoktu ve olmaması
 * doğruydu: deponun izin matrisinde karşılığı olmayan bir hap, sahibe hiç
 * kimseye veremeyeceği bir yetkiyi vaat ederdi. Artık `MembershipRole` onu
 * tanıyor, `RolePermissions` dar listesini üretiyor ve menü uçları o dar
 * izne bakıyor — yani hap gerçek bir daveti temsil ediyor.
 *
 * SIRA KAYNAĞIN SIRASIDIR (Editör · Yönetici · Mutfak) ve ilk sıradaki
 * VARSAYILANDIR.
 */
const INVITABLE_ROLES: {
    value: InvitableRole;
    labelKey:
        | 'workspace.team.invite.role.editor'
        | 'workspace.team.invite.role.manager'
        | 'workspace.team.invite.role.kitchen';
    helpKey:
        | 'workspace.team.invite.role.editor.help'
        | 'workspace.team.invite.role.manager.help'
        | 'workspace.team.invite.role.kitchen.help';
}[] = [
    {
        value: 'editor',
        labelKey: 'workspace.team.invite.role.editor',
        helpKey: 'workspace.team.invite.role.editor.help',
    },
    {
        value: 'manager',
        labelKey: 'workspace.team.invite.role.manager',
        helpKey: 'workspace.team.invite.role.manager.help',
    },
    {
        value: 'kitchen',
        labelKey: 'workspace.team.invite.role.kitchen',
        helpKey: 'workspace.team.invite.role.kitchen.help',
    },
];

/*
    ROL HAPLARI (kaynak: `panel.dc.html`, "Takım" → davet kartı).

    Rol bir açılır listedeydi ve seçenekler ancak liste açılınca görünüyordu:
    iki rol arasında seçim yapan biri, ikisini aynı anda hiç görmüyordu. Hap
    olarak ikisi de ekranda durur ve seçili olan ŞEKİLLE de ayrışır — yalnız
    renkle değil (WCAG 2.2 §1.4.1): seçili hapın kenarlığı ve zemini birlikte
    değişir, ağırlığı da 500'den 700'e çıkar.
*/
const ROLE_PILL_BASE = [
    'inline-flex min-h-[var(--control-height)] items-center rounded-pill',
    'px-[var(--space-4)] py-[var(--space-1)] text-body',
    'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
].join(' ');

export function TeamPage({ workspaceId, viewerRole }: TeamPageProps) {
    const emailId = useId();
    const roleId = useId();
    /*
        Davet edilebilir roller. `owner` YOK: sahiplik davetle verilmez,
        devredilir (`docs/70`).
    */
    const [role, setRole] = useState<InvitableRole>('editor');
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

    /**
     * Yanlış verilmiş bir rolü düzeltir — `docs/83` (P1-07).
     *
     * Liste sunucudan YENİDEN OKUNUR: rol değişikliği anında etkilidir ve
     * ekrandaki değerin sunucudakiyle ayrışması, sahibi olmayan bir yetkiye
     * güvendirirdi.
     */
    const changeMemberRole = useCallback(
        async (memberId: number, role: string): Promise<TeamMemberRoleOutcome> => {
            if (workspaceId === undefined) {
                return 'error';
            }

            try {
                await bootstrapCsrfCookie();

                const response = await fetch(
                    `/api/workspaces/${workspaceId}/team/members/${memberId}/role`,
                    {
                        ...buildAuthRequestInit({ method: 'PUT' }),
                        credentials: 'same-origin',
                        body: JSON.stringify({ role }),
                    },
                );

                if (!response.ok) {
                    return 'error';
                }

                setMembers((current) =>
                    current.map((member) =>
                        member.id === memberId ? { ...member, role } : member,
                    ),
                );

                return 'success';
            } catch {
                return 'error';
            }
        },
        [workspaceId],
    );

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
                        /*
                            SUNUCUNUN NE DEDİĞİ TAŞINIR, NASIL SÖYLEDİĞİ
                            DEĞİL.

                            Her ret tek bir "olmadı"ya iniyordu ve ekran
                            hepsine "tekrar deneyin" diyordu. Oysa 403 ile
                            404 iki ayrı gerçektir: biri "bu iş sahibin"
                            (çıkış yolu: sahibinden istemek), diğeri "o
                            üyelik zaten orada değil" (çıkış yolu: yok).
                            Ayrımı BURADA yapmak zorunludur, çünkü yanıt
                            gövdesi bu satırdan sonra kayboluyor.

                            Gövdenin kendisi okunmaz ve ekrana taşınmaz:
                            "Forbidden." geliştiriciye yazılmış bir cümledir
                            ve sahibin ekranında ham JSON kadar yabancıdır.
                        */
                        if (response.status === 403) {
                            return 'forbidden';
                        }

                        if (response.status === 404) {
                            return 'missing';
                        }

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
                body: JSON.stringify({ email, role }),
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

    /*
        ROLLER KARTINA GİRECEK ROLLER.

        Dört kanonik rol her zaman anlatılır: sahiplik devredilebilir, diğer
        üçü davet edilebilir — yani sahibin hepsine bir yolu var. Eski salt
        okunur `member` rolü ise YALNIZ fiilen taşıyan biri varsa eklenir:
        satırda o kelimeyi gören sahip, kartta karşılığını bulmalı; kimse
        taşımıyorken anlatmak ise olmayan bir seçenek sunmak olurdu.
    */
    /*
        Rol BİLİNMİYORSA süzülmez, YOKSA süzülür. İkisi farklı şeydir:
        `undefined` "kabuk bu bilgiyi hiç geçirmedi" demektir, `null` ise
        sunucunun kendi cevabıdır — "bu izin kümesi hiçbir role uymuyor".
        İkincisinde sahiplik yetkisi vermek, tanımadığımız birine sahip
        muamelesi yapmak olurdu.
    */
    const viewerIsOwner = viewerRole === undefined || viewerRole?.toLowerCase() === 'owner';

    const guideRoles: TeamRoleKey[] = ['owner', 'manager', 'editor', 'kitchen'];

    if (members.some((member) => member.role.toLowerCase() === 'member')) {
        guideRoles.push('member');
    }

    return (
        <div id="section-team">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.team.heading')}
                description={t('workspace.team.operational.description')}
            >
                {/*
                    İKİ SÜTUN — kaynağın kendi düzeni (`panel.dc.html`,
                    "Takım"): solda KİMLER VAR (üyeler, bekleyen davetler),
                    sağda NE YAPABİLİRİM (davet et, roller).

                    Ekran tek sütundu ve sırası davet → davetler → üyeler'di:
                    sahip takımını görmek için iki formu geçmek zorundaydı.
                    Sütunlar `flex-wrap` ile kurulur, breakpoint'le değil —
                    320 pikselde kendiliğinden alt alta iner.
                */}
                <div className="flex flex-wrap items-start gap-[var(--space-fluid-md)]">
                    <div className="flex min-w-[min(100%,20rem)] flex-[2] flex-col gap-[var(--space-fluid-md)]">
                        <PanelCard>
                            <TeamMemberList
                                status={membersStatus}
                                members={members}
                                label={t('workspace.team.members.region')}
                                loadingText={t('workspace.team.members.loading')}
                                errorText={t('workspace.team.members.error')}
                                emptyText={t('workspace.team.members.empty')}
                                onRemoveMember={removeMember}
                                onChangeRole={changeMemberRole}
                                assignableRoles={INVITABLE_ROLES.map((option) => ({
                                    value: option.value,
                                    label: t(option.labelKey),
                                }))}
                                roleLabelFor={(name) =>
                                    t('workspace.team.members.role.label', { name })
                                }
                                roleErrorText={t('workspace.team.members.role.error')}
                                removeButtonText={t('workspace.team.members.remove.button')}
                                removeConfirmText={t('workspace.team.members.remove.confirm')}
                                removeCancelText={t('workspace.team.members.remove.cancel')}
                                removeBusyText={t('workspace.team.members.remove.busy')}
                                removeErrorText={t('workspace.team.members.remove.error')}
                                removeForbiddenText={t('workspace.team.members.remove.forbidden')}
                                removeMissingText={t('workspace.team.members.remove.missing')}
                                removeSuccessText={t('workspace.team.members.remove.success')}
                                removeRetryText={t('workspace.team.members.remove.retry')}
                                /*
                                    ÇIKARILABİLİR KÜME, DAVET EDİLEBİLİR
                                    KÜMEDİR — sunucunun kendi türetmesiyle
                                    aynı (`MembershipRole::invitable()`).
                                    İkinci bir liste yazılsaydı, davet
                                    edilebilen yeni bir rol doğduğunda biri
                                    güncellenir öbürü unutulurdu.
                                */
                                removableRoles={INVITABLE_ROLES.map((option) => option.value)}
                                viewerIsOwner={viewerIsOwner}
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
                        </PanelCard>
                        <PanelCard>
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
                        </PanelCard>
                    </div>

                    <div className="flex min-w-[min(100%,18rem)] flex-[1] flex-col gap-[var(--space-fluid-md)]">
                        <PanelCard>
                            <fieldset className="m-0 flex flex-col gap-4 border-0 p-0">
                                <legend className="mb-1 text-body font-bold text-fg">
                                    {t('workspace.team.invite.section')}
                                </legend>

                                <div>
                                    <div className="mb-2 block">
                                        <Label htmlFor={emailId}>
                                            {t('workspace.team.invite.email')}
                                        </Label>
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

                                {/*
                                    ROL SEÇİMİ — `docs/70`, kaynağın rol
                                    hapları.

                                    Davet önceden her zaman `editor`
                                    gönderiyordu ve o rol hiçbir şeyi
                                    düzenleyemiyordu. Sahibin, faturaya
                                    dokunamayan ama günlük operasyonu
                                    yürütebilen birini davet etmesinin yolu
                                    yoktu.

                                    `Owner` listede DEĞİL: sahiplik davetle
                                    verilmez, devredilir.
                                */}
                                <div className="flex flex-col gap-[var(--space-2)]">
                                    <span id={roleId} className="text-body font-medium text-fg">
                                        {t('workspace.team.invite.role.label')}
                                    </span>
                                    <div
                                        role="radiogroup"
                                        aria-labelledby={roleId}
                                        className="flex flex-wrap gap-[var(--space-2)]"
                                    >
                                        {INVITABLE_ROLES.map((option) => {
                                            const selected = option.value === role;

                                            return (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={selected}
                                                    className={`${ROLE_PILL_BASE} ${
                                                        selected
                                                            ? 'border border-action bg-action font-bold text-action-fg'
                                                            : 'border border-border bg-surface font-medium text-fg hover:bg-surface-hover'
                                                    }`}
                                                    onClick={() => {
                                                        setRole(option.value);
                                                        setSubmitError(false);
                                                        setSubmitSuccess(false);
                                                    }}
                                                >
                                                    {t(option.labelKey)}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {/*
                                        Rolün NE YAPABİLDİĞİ hapların altında
                                        yazar. "Editor" kelimesi tek başına
                                        yayınlayıp yayınlayamayacağını söylemez
                                        ve sahibi yanlış kişiye yanlış yetkiyi
                                        verebilir.
                                    */}
                                    {/*
                                        Flowbite'ın `HelperText`'i BİLEREK
                                        kullanılmadı: kendi ham gri palet
                                        basamağını ve taban altı bir yazı
                                        boyutunu basıyor — ikisi de jeton
                                        kökünü atlar, üstelik o boyut gövde
                                        tabanının (1rem) altındadır.
                                    */}
                                    {/*
                                        Yardım metni ROL KAYDINDAN okunur,
                                        elle yazılmış bir koşuldan değil.
                                        İkili bir `?:` üçüncü rol geldiğinde
                                        sessizce yanlış cümleyi gösterirdi —
                                        "Mutfak" seçili iken ekranda
                                        "Editör"ün cümlesi yazardı ve sahip
                                        yanlış yetkiyi verdiğini fark
                                        etmezdi.
                                    */}
                                    <p className="text-body text-fg-secondary">
                                        {t(
                                            (
                                                INVITABLE_ROLES.find(
                                                    (option) => option.value === role,
                                                ) ?? INVITABLE_ROLES[0]
                                            ).helpKey,
                                        )}
                                    </p>
                                </div>

                                <Button
                                    className="w-full"
                                    disabled={!emailIsValid || submitting}
                                    onClick={() => void handleInvite()}
                                >
                                    {t('workspace.team.invite.button')}
                                </Button>

                                {/*
                                    Kaynağın son satırı: sahiplik davetle
                                    verilmez. Bu cümle burada durur çünkü
                                    sahibin "Sahip" hapını aradığı an tam
                                    olarak burasıdır.
                                */}
                                <p className="text-body text-fg-muted">
                                    {t('workspace.team.invite.ownership.note')}
                                </p>

                                {submitting && (
                                    <p role="status" className="text-body text-fg-muted">
                                        {t('workspace.team.invite.submitting')}
                                    </p>
                                )}

                                {!submitting && submitError && (
                                    <p
                                        role="status"
                                        className="text-body font-medium text-fg-danger"
                                    >
                                        {t('workspace.team.invite.error')}
                                    </p>
                                )}

                                {!submitting && submitSuccess && (
                                    <p
                                        role="status"
                                        className="text-body font-medium text-fg-success"
                                    >
                                        {t('workspace.team.invite.success')}
                                    </p>
                                )}
                            </fieldset>
                        </PanelCard>

                        <PanelCard>
                            <TeamRoleGuide roles={guideRoles} />
                        </PanelCard>
                    </div>
                </div>
            </WorkspacePageFrame>
        </div>
    );
}

export default TeamPage;
