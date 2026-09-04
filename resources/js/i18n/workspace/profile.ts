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
    */
    'workspace.profile.density.heading': 'Row spacing',
    'workspace.profile.density.help':
        'How much breathing room lists and tables get. Buttons stay the same size to tap in every mode.',
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
