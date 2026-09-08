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
    /*
        SUNUCUNUN İKİ AYRI "HAYIR"I, İKİ AYRI CÜMLE (FF-138d).

        Her ret için tek bir "tekrar deneyin" cümlesi vardı. Ama bu iki
        cevabın ortak yanı yok: biri "bu iş senin değil" der ve tekrar
        denemek hiçbir şeyi değiştirmez, diğeri "o üyelik zaten orada değil"
        der ve denenecek bir şey kalmamıştır. İkisine de "tekrar deneyin"
        demek, sahibi sonu olmayan bir döngüye çağırmaktı.

        Cümleler sunucunun SÖYLEDİĞİNE dayanır ama onun kelimeleriyle
        yazılmaz: "Forbidden." bir geliştirici cümlesidir ve sahibe çıkış
        yolunu göstermez.
    */
    'workspace.team.members.remove.forbidden':
        'Only the workspace owner can remove people from the team. Ask the owner to do it.',
    'workspace.team.members.remove.missing': 'This person is no longer in the team list.',
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
        "BEKLİYOR"UN İKİ AYRI SEBEBİ VARDI (`docs/110` P0-06).

        Bir davet satırı "pending" diyordu; ama bu ya "e-posta ulaştı, kişi
        henüz tıklamadı" ya da "e-posta hiç çıkmadı" demekti. Sahip ikisini
        de aynı görüyor, ikisinde de beklemekten başka bir şey
        yapamıyordu — oysa çözümleri tamamen farklıdır.

        Cümleler SÖZ VERMEZ: "yakında gelir", tahmini süre ya da "e-posta
        yolda" yok. Taşıyıcının ne zaman döneceğini bilmiyoruz.
    */
    'workspace.team.invitations.delivery.failed':
        'The email did not go out. The invitation is still valid — send it again.',
    // "Denenmedi" ile "denendi ve düştü" ayrı şeylerdir. Bu satır, teslimat
    // geçmişi hiç tutulmamış eski davetler içindir; onlara "gönderildi"
    // demek, yapmadığımız bir işi yaptığımızı söylemek olurdu.
    'workspace.team.invitations.delivery.unknown':
        'We cannot tell whether this email was ever sent.',
    'workspace.team.invitations.resend.button': 'Send again',
    'workspace.team.invitations.resend.busy': 'Sending…',
    /*
        "TESLİM EDİLDİ" DEMİYORUZ, ÇÜNKÜ BİLMİYORUZ.

        Bildiğimiz tek şey taşıyıcının mesajı hatasız devraldığı. Gelen
        kutusuna düştüğünü, spam'e gidip gitmediğini buradan göremeyiz ve
        göremediğimiz bir şeyi söylemeyiz.
    */
    'workspace.team.invitations.resend.sent':
        'The email provider accepted it. We cannot see the inbox from here.',
    'workspace.team.invitations.resend.undelivered':
        'The invitation was refreshed, but the email did not go out.',
    'workspace.team.invitations.resend.error': 'Could not send it again. Please try again.',
    // Yeniden gönderme yeni bir bağlantı üretir ve eskisini öldürür. Bunu
    // yazmasak, sahip "iki e-posta gitti, hangisi çalışıyor?" sorusunun
    // cevabını ancak alıcı şikâyet edince öğrenirdi.
    'workspace.team.invitations.resend.linkNote':
        'Sending again replaces the link: only the newest email works.',
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

    /*
        ROLÜN AYRINTISI (`docs/139` §5).

        Yukarıdaki beş cümle rolün TARİFİDİR ve elle yazılmıştır — bir tarif
        gerekir, liste onun yerine geçmez. Ama tarif tek başına bırakıldığında
        bir role izin eklendiği gün sessizce eskiyordu ve hiçbir kapı bunu
        görmüyordu. Aşağıdaki etiketler o boşluğu kapatan listelerin
        kelimeleridir: hangi rolün hangisini taşıdığı BURADA YAZMAZ, koddan
        üretilen matristen gelir (`role-permission-matrix.json`).

        Yani bu blokta bir yetki iddiası yoktur; yalnız yeteneklerin ADLARI
        vardır. Bir yetenek adsız kalırsa ya da olmayan bir yeteneğin adı
        burada unutulursa ön uç kapısı kırılır
        (`TeamRoleGuide.matrix.test.tsx`).
    */
    'workspace.team.roleGuide.detail.show': 'See exactly what this role can and cannot do',
    'workspace.team.roleGuide.detail.can': 'Can',
    'workspace.team.roleGuide.detail.cannot': 'Cannot',
    // Listenin nereden geldiğini söylemek, ona güvenilip güvenilmeyeceğini
    // söyler. Elle yazılmış bir özet olsaydı bu cümle kurulamazdı.
    'workspace.team.roleGuide.detail.source':
        'These two lists come from the same rules the product enforces when someone taps a button.',

    'workspace.team.roleGuide.ability.workspace.view': 'Open this workspace',
    'workspace.team.roleGuide.ability.workspace.manage': 'Change brand and branch settings',
    'workspace.team.roleGuide.ability.menu.view': 'See the menus',
    'workspace.team.roleGuide.ability.menu.manage': 'Add and edit dishes, prices and categories',
    'workspace.team.roleGuide.ability.menu.publish': 'Publish the menu guests see',
    'workspace.team.roleGuide.ability.menu.allergens.manage': 'Mark allergens on a dish',
    'workspace.team.roleGuide.ability.menu.stock.manage': 'Mark a dish sold out for today',
    'workspace.team.roleGuide.ability.qr.view': 'See the QR codes',
    'workspace.team.roleGuide.ability.qr.create': 'Create QR codes for tables',
    'workspace.team.roleGuide.ability.qr.disable': 'Turn a QR code off',
    'workspace.team.roleGuide.ability.qr.design.manage': 'Change how printed QR codes look',
    'workspace.team.roleGuide.ability.analytics.view': 'See visit and scan numbers',
    'workspace.team.roleGuide.ability.billing.view': 'See the plan and the invoices',
    'workspace.team.roleGuide.ability.billing.manage': 'Change the plan and pay for it',
    'workspace.team.roleGuide.ability.security.evidence.view':
        'See the backup and data separation records',
    'workspace.team.roleGuide.ability.media.manage': 'Upload, replace and delete photos',
    'workspace.team.roleGuide.ability.media.download_original': 'Download the original photo file',
    'workspace.team.roleGuide.ability.order.view': 'See the orders coming from tables',
    'workspace.team.roleGuide.ability.order.confirm': 'Confirm an order from a table',
    'workspace.team.roleGuide.ability.order.kitchen': 'Move an order through the kitchen',
    'workspace.team.roleGuide.ability.order.settings': 'Turn table ordering on and off',
    /*
        VERİ HAKLARI İKİ AYRI YETENEKTİR (FF-226, `docs/138`). Kopya almak
        ile silmek tek satırda anlatılsaydı, rol rehberini okuyan sahip
        "arşiv indirmek" ile "her şeyi silmek" arasındaki farkı ekranda
        göremezdi; ikisi de yalnız sahibindir ve ikisi de adıyla yazılıdır.
    */
    'workspace.team.roleGuide.ability.workspace.data.export':
        'Take a copy of everything this workspace holds',
    'workspace.team.roleGuide.ability.workspace.data.erase':
        'Ask for this workspace data to be erased',
    'workspace.team.roleGuide.ability.rating.view': 'See what guests rated',
    'workspace.team.roleGuide.ability.rating.reply': 'Reply to a guest in the menu',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof team, string> {}
}
