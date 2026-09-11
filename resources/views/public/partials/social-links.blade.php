{{-- SOSYAL ŞERİT — DOĞRULANMIŞ profil, ölü ikon yok (C3).

     ── HİÇ PROFİL YOKSA HİÇ ÇİZİLMEZ ──

     Boş bir başlık, olmayan bir bölümün sözünü vermektir (altbilgi içerik
     bandıyla aynı kural). Liste `SocialProfiles::forShell()`ten gelir ve
     yalnız yapılandırılmış, HTTPS bir adres oraya girer.

     ── ETİKET BAĞLANTIDA, İKONDA DEĞİL ──

     İkon tek başına duruyor ama erişilebilir adı ikonun kendisinde YAŞAMAZ:
     adı taşıyan şey tıklanan şeydir. İkon `aria-hidden` kalır — ekran
     okuyucu "grafik" demez ve aynı şeyi iki kez okumaz (`ICON-03`).

     ── AD GÖRÜNÜR BİR DÜĞÜMDÜR, `aria-label` DEĞİL ──

     Yapımcının adı artık bağlantının İÇİNDE gerçek bir `<span>` olarak
     duruyor ve bağlantının erişilebilir adı odur. `aria-label` kaldırıldı:
     iki kaynak olsaydı (görünen metin ve gizli etiket) ikisi bir gün
     ayrışırdı ve ayrışan taraf ekran okuyucununki olurdu — kimsenin
     bakmadığı taraf.

     ── VURGU BİR ZENGİNLEŞTİRMEDİR, BİLGİNİN TEK EVİ DEĞİL ──

     Ad vurguda (`hover`) açılır; ama DOKUNMALI BİR CİHAZDA VURGU YOKTUR
     (`TOUCH-FIRST-INTERFACE` md. 2). Bu yüzden ad, açılıp açılmamasından
     bağımsız olarak erişilebilirlik ağacında ve odak sırasında hep vardır:
     görsel olarak daraltılır, ağaçtan çıkarılmaz. Dokunan kullanıcı adı
     ekranda görmez ama ekran okuyucusu ve klavyesi onu bulur; kaybolan tek
     şey bir SÜS olur, bir BİLGİ değil.

     ── DIŞ ADRES, DIŞ SEKME DEĞİL ──

     `target="_blank"` YOK: bir bağlantının nereye açılacağı ziyaretçinin
     kararıdır ve dokunmalı bir cihazda yeni sekme, geri düğmesini işe
     yaramaz hâle getirir. `rel="me noopener"`: `me` bu profilin bize ait
     olduğunu makine tarafında söyler (doğrulanmış bir olgu), `noopener`
     ise açılan sayfanın bu belgeye erişmesini keser. --}}
@php
    $socialLinks = \App\Support\Site\SocialProfiles::forShell($st);
@endphp

@if ($socialLinks !== [])
    <ul class="site-social" data-social-presentation="{{ $socialPresentation ?? 'menu' }}" aria-label="{{ $st['socialLabel'] }}">
        @foreach ($socialLinks as $link)
            <li>
                <a
                    href="{{ $link['url'] }}"
                    class="site-social-link"
                    data-social="{{ $link['id'] }}"
                    rel="me noopener"
                ><x-phosphor :name="$link['icon']" /><span class="site-social-name">{{ $link['name'] }}</span></a>
            </li>
        @endforeach
    </ul>
@endif
