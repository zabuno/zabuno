@extends('public.layout')

{{-- İLETİŞİM — "tıkanırsam kime sorarım?" (`docs/88` P1-01, FF-216, FF-201).

     ── FF-240'ta ölçülen boşluk ─────────────────────────────────────────

     Sayfa DOĞRUYDU ama sessizdi. Ziyaretçi kime yazdığını okuyabiliyordu,
     ama telefona dokunamıyordu (numara düz metindi), cevabı zaten yazılmış
     bir soruyu sormaktan kaçınamıyordu, ne yazması gerektiğini bilmiyordu ve
     yazdığı şeye ne olacağını hiçbir yerde göremiyordu.

     ── Sayfanın sırası ve NEDEN bu sıra ─────────────────────────────────

       1. Kime yazıyorum?   — yanlış kapıysa daha ileri gitmesin.
       2. Yazmam gerekiyor mu? — cevabı yazılmışsa beklemesin.
       3. Ne yazayım?       — eksik bir mesaj yazışmayı bir tur uzatır.
       4. Formun kendisi.
       5. Yazdığıma ne olacak?

     ── Korunan davranışlar ──────────────────────────────────────────────

     Şirket bilgisi girilmemişken satır ATLANMAZ, "girilmedi" diye yazılır
     (FF-216) ve gönderim sonrası REFERANS ekranda kalır (FF-201). İkisi de
     bu pakette olduğu gibi duruyor, yalnız daha okunur bir kutuya girdi. --}}

@section('title', $st['contactHeading'])
@section('description', $st['contactLead'])

@section('content')
    {{-- `id` ATLAMA BAĞLANTISININ hedefidir (kabuktaki `skipToContent`).
         Bu sayfada yoktu: klavyeyle gezen biri bağlantıyı görüyor, basıyor
         ve hiçbir yere gitmiyordu. --}}
    <main id="main-content" class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-10">
        <div class="flex flex-col gap-2">
            <h1 class="site-page-title">{{ $st['contactHeading'] }}</h1>
            <p class="text-fg-secondary">{{ $st['contactLead'] }}</p>
        </div>

        @if (session('contact.sent'))
            {{-- Teyit EKRANDA. "Gönderildi" demeyen bir form, gönderilip
                 gönderilmediğini bilmeyen bir kullanıcı bırakır. Kutunun
                 kendi BAŞLIĞI var: rengi ve kenarı göremeyen bir okuyucu
                 için sonucu söyleyen tek şey odur. --}}
            <div role="status" class="site-panel" data-tone="confirmed">
                <strong class="site-panel-title">{{ $st['contactSentHeading'] }}</strong>
                <p>{{ $st['contactSent'] }}</p>
                @if ($sentReference !== null)
                    {{-- REFERANS EKRANDA (FF-201): e-posta çıkmasa bile
                         numara elde kalır. Bal küpü gönderiminde referans
                         yoktur ve bu satır hiç çizilmez. --}}
                    <p class="site-panel-fact">{{ $sentReference }}</p>
                @endif
            </div>
        @endif

        {{-- 1. KİME YAZIYORUM (FF-216).

             Form tek başına bir iletişim yolu değildir; adres, telefon ve
             e-posta `/about` ile AYNI kaynaktan gelir ve girilmemişse öyle
             yazar. E-posta ve telefon artık dokunulabilir — kararı veren
             yer `CompanyIdentity::actionFor()`, şablon değil. --}}
        <section aria-labelledby="contact-identity-heading" class="flex flex-col gap-3">
            <h2 id="contact-identity-heading" class="text-xl font-bold">{{ $st['contactIdentityHeading'] }}</h2>
            <p class="text-fg-secondary">{{ $st['contactIdentityBody'] }}</p>
            @include('public.partials.company-identity')
        </section>

        {{-- 2. YAZMAM GEREKİYOR MU?

             Yardım makaleleri oturum İSTEMEZ ve ilk günün üç sorusunu
             (menü aktarma, karekod basma, fiyat değiştirme) zaten
             cevaplıyor. Bir cevabı beklemek yerine okumak, en hızlı
             destektir — ve bu, tutulamayacak bir hız sözü vermeden
             söylenebilen tek şeydir. --}}
        <section aria-labelledby="contact-before-heading" class="site-panel">
            <h2 id="contact-before-heading" class="site-panel-title">{{ $st['contactBeforeHeading'] }}</h2>
            <p>{{ $st['contactBeforeBody'] }}</p>
            <a href="/help" class="site-cta">{{ $st['contactBeforeCta'] }}</a>
        </section>

        {{-- 3–4. NE YAZAYIM, ve FORM. --}}
        <section aria-labelledby="contact-form-heading" class="flex flex-col gap-4">
            <h2 id="contact-form-heading" class="text-xl font-bold">{{ $st['contactFormHeading'] }}</h2>

            @if ($commitment !== null)
                {{-- Yalnız yapılandırılmışsa (`docs/125` §3). Boşken bu satır
                     YOKTUR; yedek bir "en kısa sürede" cümlesi de yoktur. --}}
                <p class="text-fg-secondary">{{ $commitment }}</p>
            @endif

            {{-- Eksik bir mesaj yazışmayı bir tur uzatır: "hangi restoran?"
                 diye sormak, cevabı bir gün geciktirmektir. Bu üç madde bir
                 SÖZ değil, bir istektir. --}}
            <div class="site-panel">
                <h3 class="site-panel-title">{{ $st['contactTipsHeading'] }}</h3>
                <ul class="site-list">
                    <li>{{ $st['contactTipsRestaurant'] }}</li>
                    <li>{{ $st['contactTipsScreen'] }}</li>
                    <li>{{ $st['contactTipsWhen'] }}</li>
                </ul>
            </div>

            @if ($errors->any())
                {{-- Hata bandının BAŞLIĞI var: "bir şeyler ters gitti" değil,
                     ne OLMADIĞI. `role="alert"` ile ekran okuyucu onu anında
                     okur; kutu formun ÜSTÜNDEDİR, çünkü altında olsaydı dar
                     ekranda görünmeden kaydırılırdı. --}}
                <div role="alert" class="site-panel" data-tone="danger">
                    <strong class="site-panel-title">{{ $st['contactErrorsHeading'] }}</strong>
                    <ul class="site-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="/contact" class="flex flex-col gap-4">
                @csrf

                {{-- Zorunluluk BİR KEZ söylenir. Her etikete yıldız koymak,
                     hiçbiri isteğe bağlı olmayan bir formda üç kez aynı şeyi
                     söylemek olurdu. --}}
                <p class="site-field-hint">{{ $st['contactRequired'] }}</p>

                <div class="site-field">
                    {{-- Etiket ŞART: yer tutucu bir etiket değildir ve ekran
                         okuyucu onu alan adı olarak okumaz. --}}
                    <label for="contact-name" class="site-field-label">{{ $st['contactName'] }}</label>
                    <input id="contact-name" name="name" type="text" required autocomplete="name"
                           value="{{ old('name') }}" class="site-control">
                </div>

                <div class="site-field">
                    <label for="contact-email" class="site-field-label">{{ $st['contactEmail'] }}</label>
                    <input id="contact-email" name="email" type="email" required autocomplete="email"
                           value="{{ old('email') }}" aria-describedby="contact-email-hint"
                           class="site-control">
                    {{-- İpucu `aria-describedby` ile bağlı: ekran okuyucu onu
                         alanın parçası olarak okur, sayfada başıboş bir
                         cümle olarak değil. --}}
                    <p id="contact-email-hint" class="site-field-hint">{{ $st['contactEmailHint'] }}</p>
                </div>

                <div class="site-field">
                    <label for="contact-message" class="site-field-label">{{ $st['contactMessage'] }}</label>
                    <textarea id="contact-message" name="message" rows="6" required maxlength="4000"
                              aria-describedby="contact-message-hint"
                              class="site-control">{{ old('message') }}</textarea>
                    {{-- Sınır SAYIYLA yazılır ve sayı `maxlength` ile aynı
                         yerden okunur: "çok uzun" diye reddedilen bir mesaj,
                         sınırı hiç görmemiş birinin yazdığı mesajdır. --}}
                    <p id="contact-message-hint" class="site-field-hint">{{ $st['contactMessageHint'] }}</p>
                </div>

                {{-- BAL KÜPÜ: insan bunu görmez, dolayısıyla dolduramaz.
                     `aria-hidden` ve `tabindex="-1"`, ekran okuyucu ve klavye
                     kullanan bir insanın da yanlışlıkla doldurmasını engeller —
                     aksi hâlde tuzak, korumak istediği kişiyi yakalardı. --}}
                <div aria-hidden="true" class="hidden">
                    <label for="contact-website">{{ $st['contactHoneypot'] }}</label>
                    <input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit" class="site-cta" data-emphasis="true">{{ $st['contactSubmit'] }}</button>
            </form>

            {{-- 5. YAZDIĞIMA NE OLACAK — göndermeden ÖNCE.

                 Bağlantı yaşayan bir rotadır (`/privacy`) ve etiketi
                 altbilgininkiyle AYNI katalog anahtarından gelir: iki yerde
                 iki farklı adla anılan bir belge, iki belge sanılır.

                 Bağlantı CÜMLENİN İÇİNDE DEĞİL, kendi satırında durur ve
                 bu ölçülmüş bir karar: cümle içinde 53×18 pikselde
                 çiziliyordu (`scripts/mobile-ux-audit`, 320×568) ve WCAG
                 2.2'nin 2.5.8 muafiyetine sığınmak yerine, bağımsız bir
                 eylem olarak yazmak onu 44 piksele çıkardı. Metin akışını
                 bozmuyor, çünkü zaten bir cümlenin devamı değil — bir
                 belgeye gitme kararı. --}}
            <p class="text-fg-secondary">{{ $st['contactPrivacyBody'] }}</p>
            <a href="/privacy" class="site-cta">{{ $st['footerPrivacy'] }}</a>
        </section>
    </main>
@endsection
