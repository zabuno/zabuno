export const support = {
    /*
        DESTEK EKRANI (FF-201, `docs/125`, `docs/107` Faz 1.6).

        Sahibin panelden destek istemesinin yolu yoktu; kamu formuna gidip
        adını ve e-postasını yeniden yazıyordu ve talebi hangi restorana
        aitti, bilinmiyordu. Bu ekran: kendi talepleri (referans, konu,
        durum, tarih), yeni talep formu (konu + mesaj; ad ve e-posta
        hesaptan), yardım makalelerine bağlantı.

        YANIT TAAHHÜDÜ BU KATALOGDA YOKTUR ve olmayacak: cümle sunucudan
        gelir (`GET .../support-requests` → `commitment.sentence`), çünkü
        iletişim sayfası, alındı e-postası ve panel tek anahtarı okumak
        zorunda (`site.support.commitment`). Buraya ikinci bir cümle yazmak,
        iki cümlenin ayrıştığı günü hazırlamak olurdu.
    */
    'workspace.support.title': 'Support',
    'workspace.support.description':
        'Ask for help from inside your workspace. Every request gets a reference number and stays listed here with its status.',

    // Yardım makaleleri OTURUM İSTEMEZ (`/help`); bağlantı yeni sekmede
    // değil aynı sekmede açılır — sahip geri tuşuyla döner.
    'workspace.support.help.lead': 'Many questions are already answered in the help articles.',
    'workspace.support.help.link': 'Open help articles',

    'workspace.support.form.heading': 'New request',
    'workspace.support.form.subject': 'Subject',
    'workspace.support.form.subject.help': 'One line: what is not working, or what you need.',
    'workspace.support.form.message': 'What is happening?',
    /*
        E-POSTA HESAPTAN: form sormaz, cümle söyler. Sahip cevabın nereye
        geleceğini görmeli; adres yanlışsa Profil'den düzeltir, buradan
        değil.
    */
    'workspace.support.form.message.help':
        'Tell us what you expected and what you saw instead. We reply to {email}.',
    'workspace.support.form.submit': 'Send request',
    'workspace.support.form.submitting': 'Sending…',
    /*
        ÜÇ SONUÇ CÜMLESİ, ÜÇ GERÇEK. Alındı e-postasının hâli sunucudan
        gelir (`acknowledgement`): çıktı / çıkmadı / hiç denenmedi. Üçünü
        "gönderildi" altında toplamak, sahibi gelmeyen bir e-postayı
        beklemeye çağırmak olurdu (`docs/110` P0-06 dersi).
    */
    'workspace.support.form.sent':
        'Your request {reference} was received. We sent a confirmation to {email}.',
    'workspace.support.form.sentNoCopy':
        'Your request {reference} was received. The confirmation email could not be sent, but the request is recorded and listed below.',
    'workspace.support.form.error': 'Your request could not be sent. Try again.',

    'workspace.support.list.heading': 'Your requests',
    'workspace.support.list.loading': 'Loading your requests…',
    'workspace.support.list.error': 'Your requests could not be loaded.',
    'workspace.support.list.empty':
        'No requests yet. When you send one, it appears here with its reference and status.',
    'workspace.support.list.received': 'Received {date}',
    'workspace.support.list.answered': 'Answered {date}',

    // Durum KELİMEYLE anlatılır, yalnız renkle değil (WCAG 2.2 §1.4.1).
    'workspace.support.status.received': 'Received',
    'workspace.support.status.answered': 'Answered',
    'workspace.support.status.closed': 'Closed',
} as const;

declare module '../workspace' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- intentional declaration-merging augmentation
    interface WorkspaceTranslationCatalog extends Record<keyof typeof support, string> {}
}
