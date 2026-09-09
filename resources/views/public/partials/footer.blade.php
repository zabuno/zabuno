{{-- KURUMSAL KABUĞUN ALT ÇUBUĞU — TEK tanım (`docs/100` §2, `docs/136` §6, `docs/141`).

     ── Sahibin isteği ───────────────────────────────────────────────────

     *"Çoook zengin bir footer olmalı ve çok katmanlı olmalı ve çok row olmalı
     ve çok menu grubu olmalı, pSEO için footer üzerinde content menus."*
     (2026-09-08)

     FF-232 bu isteğin YARISINI karşılamıştı: zenginlik elle yazılmıyor,
     kütükten türüyor — ve o karar KORUNUYOR. Karşılamadığı yarı yapıydı;
     sahip bugünkü hâli gördü ve haklı olarak *"iki sütunluk bir footer"*
     dedi. FF-237 yapıyı kurar.

     ── ALTI SATIR, HER BİRİNİN BİR İŞİ ──────────────────────────────────

       1. MARKA — kim olduğumuz ve ürünün ne olduğu, tek cümle.
       2. BAĞLANTI GRUPLARI — Ürün, Şirket, Hesap; hepsi bugün canlıda 200
          dönen adresler.
       3. pSEO İÇERİK MENÜLERİ — kütükten türer (`nav['content']`); bugün boş
          ve bu yüzden HİÇ çizilmiyor. Boş bir başlık, olmayan bir bölümün
          sözünü vermektir.
       4. YASAL — on üç belge, kendi satırında. Bir sözleşmeyi arayan kişi
          ürün gezintisinde gezinmez; "yasal" başlığını arar.
       5. BAĞLANTI — doğrulanmış dış profil. Şirket künyesi C3'te
          `/information-society-services` sayfasına taşındı; bağlantısı
          2. satırdaki şirket grubunda duruyor.
       6. ALT SATIR — telif ve gezintiye dönüş yolu.

     Süs olsun diye satır yok: her satır ya bir soruyu yanıtlıyor ya bir
     yükümlülüğü karşılıyor.

     ── 320 PİKSEL — pazarlığa kapalı ────────────────────────────────────

     Çok satırlı bir altbilgi dar ekranın en büyük riskidir: yasal satırın
     tek başına on üç bağlantısı var ve on üçü açık çizilseydi 572 piksel,
     yani bir telefon ekranından uzun olurdu.

     Çözüm GİZLEMEK DEĞİL, KATLAMAK. Dörtten çok maddesi olan her grup
     kapalı başlar (`SiteNavigation::OPEN_ITEM_CEILING`); katlama bir
     `<details>`tir, betiksiz çalışır ve içindeki her bağlantı sunucu
     HTML'inde ZATEN durur. Bu mobil görünüm korunur. 2026-09-08 sahip kararıyla 64rem üzerinde
     ayrı açık gezinti görünür; native details CSS ile zorla açılmaz. --}}
<footer class="site-footer"{!! $lang->chromeAttributes() !!}>
    {{-- The original mobile presentation remains native; desktop has its own open navigation. --}}
    <div class="site-mobile-footer" data-mobile-footer>
    {{-- 1. MARKA SATIRI. Ürünün ne olduğunu söyleyen tek cümle katalogdan
         gelir; kimin sattığını şirket grubundaki
         `/information-society-services` sayfası söyler. --}}
    <div class="site-shell-inner site-footer-row site-footer-brand">
        <span class="site-footer-brand-name">{{ $st['brand'] }}</span>
        <span class="site-footer-tagline">{{ $st['footerTagline'] }}</span>
    </div>

    {{-- 2. BAĞLANTI GRUPLARI SATIRI. Sütun sayısı yazılmaz: ızgara sığdığı
         kadar sütun açar (`auto-fit`), yani 320'de tek sütun, geniş ekranda
         üç. İkinci bir düzen yok. --}}
    <div class="site-shell-inner site-footer-row">
        <div class="dz-footer site-footer-grid">
            @foreach ($nav['footer'] as $group)
                @include('public.partials.footer-group', ['group' => $group])
            @endforeach
        </div>
    </div>

    {{-- 3. pSEO İÇERİK MENÜLERİ (FF-232, korunuyor).

         Bağlantıların hepsi ziyaretçinin alacağı HTTP kodunu üreten AYNI
         karardan süzülür (`ResolvePageDelivery`, `docs/129` §3): kapı tek
         cümledir — *altbilgideki her bağlantı 200 döner.* Sahip bir sayfayı
         yayına aldığı gün, tek bir Blade satırı değişmeden burada belirir.

         Bandın kendisi de bir `<details>`tir ve kapalı başlar: kütükteki
         sayfalar yayına alındıkça onlarca satıra çıkabilir. --}}
    @if ($nav['content'] !== [])
        <div class="site-shell-inner site-footer-row">
            <details class="site-footer-content">
                <summary class="site-footer-fold-toggle">
                    <h2 class="site-footer-heading">{{ $st['footerContentMenus'] }}</h2>
                    <x-phosphor name="caret-down" />
                </summary>

                <div class="dz-footer site-footer-grid">
                    @foreach ($nav['content'] as $group)
                        @include('public.partials.footer-group', ['group' => $group])
                    @endforeach
                </div>
            </details>
        </div>
    @endif

    {{-- 4. YASAL SATIR — on üç belge (FF-237).

         Kendi satırında, çünkü onu arayan kişi (alıcı, hukukçu, ödeme
         kuruluşunun üye iş yeri incelemesi) ürün gezintisine bakmaz. On üçü
         de `LegalLibraryPort::KEYS`ten gelen, rotası ve metni olan
         belgelerdir; beşi FF-232'de altbilgide hiç görünmüyordu. --}}
    <div class="site-shell-inner site-footer-row site-footer-legal">
        @foreach ($nav['legal'] as $group)
            @include('public.partials.footer-group', ['group' => $group])
        @endforeach
    </div>

    {{-- 5. BAĞLANTI SATIRI — kimlik ARTIK BURADA DEĞİL (C3).

         Bu satırda yedi satırlık şirket künyesi duruyordu, katlanmış bir
         `<details>` içinde. Künye `/information-society-services`
         sayfasına TAŞINDI ve altbilgide onun adı ve bağlantısı kaldı
         (şirket grubunda, 2. satırda) — sebebi ölçülebilir: kanunun
         istediği şey bir altbilgi satırı değil, ADIYLA aranabilen bir
         sayfa; bir denetim "Bilgi Toplumu Hizmetleri" diye arar ve
         katlanmış bir altbilgi listesi o aramanın hiçbir sonucunu vermez.
         Değerler kaybolmadı: aynı `CompanyIdentity::rows()` çağrısı,
         aynı "girilmedi" kuralı, artık kendi sayfasında.

         Yerinde duran şey doğrulanmış tek dış adres: GitHub profili.
         Doğrulanmamış hiçbir platform burada YOKTUR ve ikonu da yoktur —
         hiçbir yere gitmeyen bir ikon, ürünün geri kalanına da gölge
         düşürür (`SocialProfiles`). --}}
    <div class="site-shell-inner site-footer-row site-footer-connect">
        @include('public.partials.social-links', ['socialPresentation' => 'footer'])
    </div>

    {{-- 6. ALT SATIR.

         Telif ve gezintiye dönüş yolu. Dönüş yolu bir süs değil bir ölçü
         sonucu: altbilgi 320 pikselde bir ekran boyundan uzun ve sonuna
         varan kişinin üst çubuğa dönmek için parmağıyla geri kaydırması
         gerekirdi. Çıpa `#main-content` — atlama bağlantısının zaten
         kullandığı hedef; ikinci bir kimlik icat edilmedi ve önek
         gerekmiyor, çünkü hedef AYNI belgededir.

         SÜRÜM YOK: kayıt sayısı ziyaretçiye değil kayıt sözleşmesine hitap
         eder ve bir `<meta>` olarak kalır (`MP-04`).
         DİL SEÇİCİ YOK: `config/i18n.php` `shipped_locales` bugün tek dil
         taşıyor; tek seçenekli bir seçici, olmayan bir seçimin sözünü
         vermektir (`docs/120` §5.8).
         DURUM SAYFASI YOK: sahibin sorduğu durum sayfası alt alan adı
         henüz yayında değil (`docs/136` §9). Alan adı buraya YAZILMAZ:
         bu yazılım başka alan adlarında da çalışır (`SAAS-DOMAIN`). --}}
    <div class="site-shell-inner site-footer-row site-footer-bottom">
        <span>&copy; {{ now()->year }} {{ $st['brand'] }}</span>
        {{-- Görünüm tercihi altbilgide de var (C2): sayfanın sonuna kadar
             okuyan biri yukarı dönmeden kipini değiştirebilsin. Aynı parça,
             aynı durum — üst çubuktaki kopyayla anında eşleşir. --}}
        @include('public.partials.theme-control')
        <a href="#main-content" class="site-footer-top-link">{{ $st['footerBackToTop'] }}</a>
    </div>
    </div>
    @include('public.partials.desktop-footer')
</footer>
