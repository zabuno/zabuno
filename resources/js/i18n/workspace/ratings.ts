export const ratings = {
    // Kenar çubuğu etiketi. "Puan" değil "Puanlar": sahip bir listeye bakar,
    // tek bir ortalamaya değil.
    'workspace.shell.nav.ratings': 'Ratings',

    'workspace.ratings.heading': 'Ratings',
    'workspace.ratings.description': 'What guests said about each dish — and what you said back.',

    /*
        SAHİP PUANI KALDIRAMAZ VE BUNU EKRANDA OKUR (`docs/116` §4).

        Kural sunucuda zaten korunuyor: yanıt denetleyicilerinin sinyal ya da
        puan deposuna uzanan bir eli yok. Ama sahip o kodu okumaz; kaldırma
        düğmesini arar, bulamaz ve "acaba nerede?" diye dolaşır. Cümle o
        aramayı bitirir ve sebebini söyler.
    */
    'workspace.ratings.noRemoval':
        'You can reply to a rating. You cannot take one down: an average the restaurant could delete would be an advertisement, not a measurement.',

    'workspace.ratings.loading': 'Loading ratings…',
    'workspace.ratings.error.title': 'Ratings could not be loaded',
    'workspace.ratings.error.description':
        'Nothing was lost: every vote a guest left is still counted. Try again.',
    'workspace.ratings.retry': 'Try again',

    'workspace.ratings.empty.title': 'No dishes to show yet',
    'workspace.ratings.empty.description':
        'A rating belongs to a dish. Add dishes to this menu and guests at the table can start rating them.',
    'workspace.ratings.empty.action': 'Open the menu',

    'workspace.ratings.prerequisite.title': 'Pick a menu first',
    'workspace.ratings.prerequisite.description':
        'A rating belongs to a dish, and a dish lives in a menu. Choose a branch and a menu to see its ratings.',

    'workspace.ratings.permission.title': 'Ratings are not part of your role',
    'workspace.ratings.permission.whyNoAction':
        'Ask the workspace owner if you need to see what guests rated.',

    // --- BİR SATIRIN SAYISI --------------------------------------------------
    'workspace.ratings.score': '{score} out of {max}',

    /*
        EŞİK ALTINDA SIFIR YILDIZ ÇİZİLMEZ (`docs/116` §3).

        Sıfır bir ÖLÇÜMDÜR — "misafirler bu tabağa sıfır verdi" der — ve
        bilinmeyenin yerine konamaz. Konsaydı hiç oy almamış her yeni ürün
        menünün en kötüsü gibi görünürdü.

        Eşiğin SAYISI bu ekrana hiç inmiyor: kararı sunucu veriyor, ekran
        yalnız sonucu okuyor. İki yüzeyde iki farklı eşik olsaydı sahip
        "misafir 4,2 görüyor, ben neden görmüyorum?" sorusunun cevabını
        hiçbir yerde bulamazdı.
    */
    'workspace.ratings.notEnough': 'Not enough ratings yet',
    'workspace.ratings.notEnough.help':
        'A score appears once enough guests have rated this dish. Until then there is no number to show — and zero would say something the guests never said.',

    /*
        SAYIM SAHİPTEN GİZLENMEZ. Gizlenen şey PUAN, yani henüz güvenilmeyen
        türetilmiş değerdir; kaç oy geldiği bilinen bir ölçümdür ve sahibin
        "eşiğe ne kadar kaldı?" sorusunun tek cevabıdır.
    */
    'workspace.ratings.votes': 'Votes so far: {count}',

    /*
        SAYININ YAŞI — türetilmiş puan bir işin çıktısıdır ve o iş
        çalışmadıysa ekrandaki sayı dünkü sayıdır. Garson kuyruğunda da aynı
        desen var ve aynı sebeple: donmuş bir ekranla dolu bir ekran aynı
        görünür.
    */
    'workspace.ratings.computedAt': 'Worked out {time}',
    'workspace.ratings.computedAt.never': 'Not worked out yet',

    /*
        ALGORİTMA SÜRÜMÜ (`docs/116` Ö3).

        "Bu ürünün puanı neden düştü?" sorusunun iki ayrı cevabı var: yeni
        oylar geldi, ya da hesaplama kuralı değişti. Sürüm yazılmazsa ikisi
        ayırt edilemez ve sahip yanlış olanı düzeltmeye çalışır.
    */
    'workspace.ratings.method': 'Scoring method {version}',
    'workspace.ratings.method.help':
        'Scores are worked out from guest votes by a method that can change over time. The version above says which one produced the numbers on this screen — so a score that moved because of new votes can be told apart from one that moved because the method did.',

    // --- SAHİBİN YANITI ------------------------------------------------------
    'workspace.ratings.reply.label': 'Your reply',
    'workspace.ratings.reply.help': 'Guests read this under the dish, on their own screen.',
    'workspace.ratings.reply.who': 'Reply from the restaurant',
    'workspace.ratings.reply.published': 'published {time}',
    'workspace.ratings.reply.none': 'You have not replied about this dish.',
    'workspace.ratings.reply.publish': 'Publish reply',
    'workspace.ratings.reply.update': 'Update reply',
    'workspace.ratings.reply.withdraw': 'Withdraw reply',
    'workspace.ratings.reply.saving': 'Saving…',
    'workspace.ratings.reply.empty':
        'Write something. An empty reply shows the guest an empty box.',
    // Sessiz kırpma yasak: sahibin cümlesini kısaltıp yayınlamak, ona
    // yazmadığı bir cümleyi söyletmektir.
    'workspace.ratings.reply.tooLong':
        'That is longer than {max} characters. Shorten it yourself — we will not cut your sentence for you.',
    'workspace.ratings.reply.failed':
        'That did not go through. Your words are still here; try again.',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof ratings, string> {}
}
