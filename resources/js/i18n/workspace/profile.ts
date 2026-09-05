export const profile = {
    /*
        Profil ekranı (FF-88) — sahibin isteği (2026-09-04). Kişiye ait olan
        her şey burada: ad, fotoğraf, tema. Markanın rengi de sahibin
        istediği için burada durur, ama yalnız yetkiliye çizilir.
    */
    'workspace.profile.title': 'Profile',
    'workspace.profile.description':
        'Your name, photo and appearance. Changes here follow you into every workspace you belong to.',

    'workspace.profile.details.heading': 'Your details',

    /*
        HESAP BAKIMI (`docs/83`, P1-07) — anahtarlar `workspace.settings.*`
        altındaydı ve form iki ekranda birden çiziliyordu. Kanonik kaynak
        (`panel.dc.html` > "Profil") ad, e-posta ve şifreyi Profil'e koyuyor;
        anahtarlar da oraya taşındı. Bir ayarın tek bir evi olur.
    */
    'workspace.profile.account.region': 'Your account',
    'workspace.profile.account.name.label': 'Your name',
    'workspace.profile.account.name.submit': 'Save name',
    'workspace.profile.account.name.saved': 'Your name was saved.',
    'workspace.profile.account.name.error': 'Your name could not be saved.',
    /*
        E-POSTA GÖRÜNÜR AMA DÜZENLENMEZ: değişimi doğrulama akışı ister ve o
        akış üründe yok. Düzenlenebilir bir alan, kaydeder gibi yapıp hiçbir
        şey yapmazdı.
    */
    'workspace.profile.account.email.label': 'Email',
    'workspace.profile.account.password.title': 'Change password',
    /*
        BU CÜMLE GERÇEKTİR, süs değil: `UpdatePasswordController` şifre
        değişince kullanıcının diğer oturumlarını `sessions` tablosundan
        siler (`ACCOUNT-PASSWORD-OTHER-SESSIONS-01`). Sürpriz bir çıkış,
        kullanıcıya ürünün bozulduğunu düşündürür.
    */
    'workspace.profile.account.password.help':
        'Changing your password signs you out on your other devices. This one stays signed in.',
    'workspace.profile.account.password.current': 'Current password',
    'workspace.profile.account.password.next': 'New password',
    'workspace.profile.account.password.confirm': 'Repeat new password',
    // Açılır bölümün başlığı "Change password"; düğme ayrı adlandırılır ki
    // ekran okuyucuda iki farklı şey iki farklı adla duyulsun.
    'workspace.profile.account.password.submit': 'Save new password',
    'workspace.profile.account.password.saved': 'Your password was changed.',
    'workspace.profile.account.password.error':
        'Your password could not be changed. Check your current password and try again.',

    'workspace.profile.avatar.heading': 'Profile photo',
    'workspace.profile.avatar.help':
        'Choose a photo of yourself. It is stored in your media library, scanned like every other image, and shown next to your name.',
    'workspace.profile.avatar.alt_default': 'Profile photo',
    'workspace.profile.avatar.current_alt': 'Your current profile photo',
    'workspace.profile.avatar.formats': 'JPEG, PNG or WebP.',
    'workspace.profile.avatar.save': 'Use this photo',
    'workspace.profile.avatar.saving': 'Saving…',
    'workspace.profile.avatar.saved': 'Profile photo updated.',
    'workspace.profile.avatar.remove': 'Remove photo',
    'workspace.profile.avatar.processing':
        'Your photo is still being processed. It may take a moment to appear everywhere.',
    'workspace.profile.avatar.error': 'The photo could not be saved. Try again.',

    'workspace.profile.appearance.heading': 'Appearance',
    'workspace.profile.appearance.help':
        'Light or dark is your own choice — it changes nothing for your guests. “System” follows your device setting.',

    /*
        YOĞUNLUK, kullanıcıya PİKSEL değil İŞ anlatır (FF-128). "Rahat"
        parmakla dokunulacak bir tablette, "sıkışık" gün boyu tabloya bakan
        bir masaüstünde işe yarar. Dokunma hedefi hiçbir modda küçülmez;
        değişen yalnız dolgudur.

        Cümle kanonik kaynağınkidir (docs/109): "Satır yüksekliği değişir;
        yazı boyutu ve dokunma hedefi değişmez." Eski cümle neyin
        DEĞİŞMEDİĞİNİ sayıyor ama yazı boyutundan hiç söz etmiyordu —
        tabletle servis yapan garsonun sorduğu ikinci soru tam olarak oydu.
    */
    'workspace.profile.density.heading': 'Row spacing',
    'workspace.profile.density.help':
        'Row height changes. Text size and touch targets stay the same.',
    'workspace.profile.preview.heading': 'Preview',
    'workspace.profile.preview.help': 'How your choices look right now.',
    'workspace.profile.preview.sampleLabel': 'Table',
    'workspace.profile.preview.sampleValue': 'Terrace 4',
    'workspace.profile.preview.sampleAction': 'Open',

    'workspace.profile.colors.heading': 'Brand colours',
    'workspace.profile.colors.help':
        'These two colours are used on your published guest menu. Pick them here, or type the exact code from your brand guide.',
    'workspace.profile.colors.primary': 'Primary colour',
    'workspace.profile.colors.secondary': 'Secondary colour',
    'workspace.profile.colors.clear': 'Clear',
    'workspace.profile.colors.save': 'Save colours',
    'workspace.profile.colors.saving': 'Saving…',
    'workspace.profile.colors.saved': 'Brand colours saved. They go live with your next publish.',
    'workspace.profile.colors.error':
        'The colours could not be saved. Use a six-digit code such as #C8102E and try again.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof profile, string> {}
}
