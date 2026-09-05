export const team = {
    'workspace.team.heading': 'Team',
    'workspace.team.invite.email': 'Invite by email',
    'workspace.team.invite.role.owner': 'Owner',
    'workspace.team.invite.role.label': 'Role',
    // Rol DÜZELTME (`docs/83`, P1-07). Rol ADLARI yeniden kullanılır: aynı
    // rolün iki farklı etiketi olsaydı, davet ekranıyla üye listesi
    // birbirini yalanlardı.
    'workspace.team.members.role.label': 'Role for {name}',
    'workspace.team.members.role.error': 'The role could not be changed.',
    'workspace.team.invite.role.editor': 'Editor',
    // Rolün NE YAPABİLDİĞİ yazılır: "Editor" kelimesi tek başına yayınlayıp
    // yayınlayamayacağını söylemez (docs/70).
    'workspace.team.invite.role.editor.help':
        'Edits menu content. Cannot publish, change locations or see billing.',
    'workspace.team.invite.role.manager': 'Manager',
    'workspace.team.invite.role.manager.help':
        'Runs daily operations — menus, locations, QR codes and publishing. Cannot manage billing.',
    /*
        MUTFAK (`docs/109` §6.4, kaynak `panel.dc.html`: `<option
        value="kitchen">Mutfak</option>`).

        Yardım cümlesi rolün YAPABİLDİĞİ ile YAPAMADIĞINI birlikte söyler.
        Yalnız "alerjen ve bitti" yazsaydı, sahip fiyatların da açılıp
        açılmadığını bilmeden davet ederdi — ve bu rolün bütün varlık sebebi
        tam olarak fiyatların KAPALI kalmasıdır.
    */
    'workspace.team.invite.role.kitchen': 'Kitchen',
    'workspace.team.invite.role.kitchen.help':
        'Marks allergens and “sold out today”. Cannot change prices, publish or see anything else.',
    'workspace.team.invite.button': 'Invite',
    'workspace.team.invite.unavailable': 'Inviting teammates is not connected yet.',
    'workspace.team.invite.submitting': 'Submitting invitation…',
    'workspace.team.invite.error': 'Unable to create the invitation. Please try again.',
    'workspace.team.invite.success': 'Invitation created successfully.',
    'workspace.team.members.region': 'Team members',
    'workspace.team.members.loading': 'Loading team members…',
    'workspace.team.members.error': 'Team members failed to load. Please try again.',
    'workspace.team.members.empty': 'No members in this workspace yet.',
    'workspace.team.members.remove.button': 'Remove',
    'workspace.team.members.remove.confirm': 'Confirm remove',
    'workspace.team.members.remove.cancel': 'Cancel',
    'workspace.team.members.remove.busy': 'Removing…',
    'workspace.team.members.remove.error': 'Unable to remove this member. Please try again.',
    'workspace.team.members.remove.retry': 'Retry',
    'workspace.team.members.remove.success': 'Member removed.',
    'workspace.team.members.transfer.button': 'Transfer ownership',
    'workspace.team.members.transfer.title': 'Transfer workspace ownership',
    'workspace.team.members.transfer.body':
        'This member will become the workspace owner and you will become an editor. This action can be reversed later by the new owner.',
    'workspace.team.members.transfer.confirm': 'Confirm',
    'workspace.team.members.transfer.cancel': 'Cancel',
    'workspace.team.members.transfer.busy': 'Transferring…',
    'workspace.team.members.transfer.error': 'Unable to transfer ownership. Please try again.',
    'workspace.team.members.transfer.retry': 'Retry',
    'workspace.team.members.transfer.success': 'Ownership transferred.',
    'workspace.team.invitations.loading': 'Loading pending invitations…',
    'workspace.team.invitations.error': 'Pending invitations failed to load. Please try again.',
    'workspace.team.invitations.empty': 'No pending invitations.',
    'workspace.team.invitations.cancel.button': 'Cancel invitation',
    'workspace.team.invitations.cancel.confirm': 'Confirm cancel',
    'workspace.team.invitations.cancel.keep': 'Keep invitation',
    'workspace.team.invitations.cancel.busy': 'Cancelling…',
    'workspace.team.invitations.cancel.error':
        'Unable to cancel this invitation. Please try again.',
    'workspace.team.invitations.cancel.retry': 'Retry',
    'workspace.team.invitations.cancel.success': 'Invitation cancelled.',
    /*
        SAYFA AÇIKLAMASININ ÜÇ HÂLİ VE ROZETLERİ KALDIRILDI.

        Sayfa başlığının altındaki cümle yükleme durumuna göre değişiyordu ve
        üçü de kablolamayı anlatıyordu ("server-authoritative lists"). O
        cümleler mühendise aitti; restoran sahibi ekranda ne YAPABİLECEĞİNİ
        okur (`docs/53`). Üstelik yükleme ve hata hâlini listelerin kendisi
        zaten söylüyor — başlıkta ikinci bir kopyası, aynı şeyi iki kez
        söylemekti. Yerlerini kaynağın tek cümlesi aldı.
    */
    'workspace.team.invite.section': 'Invite',
    'workspace.team.pendingInvitations.region': 'Pending invitations',
    // Kaynağın kendi cümlesi (`panel.dc.html`, "Takım"). Eskiden buraya
    // listelerin nereden geldiğini anlatan bir kablolama notu yazılıyordu; o
    // cümle mühendise aitti, restoran sahibine değil.
    'workspace.team.operational.description': 'Everyone sees only what their job needs.',
    // Eski kayıtların taşıdığı salt okunur rol. Adı ekranda geçiyor (üye
    // satırında devre dışı bir seçenek olarak), o yüzden bir karşılığı olmalı.
    'workspace.team.invite.role.member': 'Member',
    // ROLLER NE YAPABİLİR? (`docs/109` §6.4). Cümleler deponun GERÇEK izin
    // matrisiyle (`RolePermissions`) uyumludur: yönetici faturayı görür ama
    // yönetemez, o yüzden "fatura yok" değil "faturaya dokunamaz" yazar.
    'workspace.team.roleGuide.heading': 'What can each role do?',
    'workspace.team.roleGuide.owner': 'Everything: billing, team, publishing.',
    'workspace.team.roleGuide.manager': 'Menu, QR codes, publishing. Cannot touch billing.',
    'workspace.team.roleGuide.editor': 'Products, prices and photos. Cannot publish.',
    // Kaynağın birebir cümlesi: "Mutfak — Alerjen ve 'bugün bitti'. Başka bir
    // şey görmez." İkinci cümle bir süs değil, rolün sözleşmesidir.
    'workspace.team.roleGuide.kitchen': 'Allergens and “sold out today”. Sees nothing else.',
    'workspace.team.roleGuide.member':
        'Read-only. Kept for older records; nobody is invited to it any more.',
    // Kaynağın davet kartındaki son satır. Sahiplik ayrı bir akıştır ve
    // sonucu geri alınamaz; davet listesinde aramak boşuna olurdu.
    'workspace.team.invite.ownership.note': 'Ownership is transferred, not given by invitation.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof team, string> {}
}
