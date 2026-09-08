import type { ReactNode } from 'react';

/**
 * EKİP ÜYELERİ LİSTESİNİN CİHAZA ÖZGÜ YÜZEYİ — `docs/151` §B,
 * `docs/153` §5.
 *
 * `queueSurface.ts` ve `librarySurface.ts` ile aynı desen: paylaşılan sayfa
 * "bir üye listesi çizicisi alırım" der ama MASAÜSTÜ çiziciyi ADIYLA
 * ANMAZ. `undefined` telefonun NORMAL hâlidir — o pakette masaüstü
 * tablosunun kodu hiç yoktur ve ekran bugünkü dokunmatik listeyi çizer.
 *
 * ── Neden metinler de bağlamda ────────────────────────────────────────
 *
 * Bu yüzeyin bağlamı alışılmadık biçimde CÜMLE taşır. Sebep şu: ekipten
 * çıkarmanın beş ayrı sonucu var (oldu / geçici arıza / yetkin yok / o
 * üyelik yok / tekrar dene) ve hangi cümlenin hangi sonuca yazılacağı bir
 * ÜRÜN kararıdır, bir sunum kararı değil (FF-138d). İki yüzey kendi
 * cümlelerini kendisi seçseydi, aynı 403 telefonda "olmadı", masaüstünde
 * "yetkin yok" derdi ve hangisinin doğru olduğu ekrana bakarak
 * anlaşılmazdı.
 */

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

export type TeamMemberListSurfaceContext = {
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

export type TeamMemberListSurfaceRenderer = (context: TeamMemberListSurfaceContext) => ReactNode;
