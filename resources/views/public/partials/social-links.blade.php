{{-- SOSYAL ŞERİT — DOĞRULANMIŞ profil, ölü ikon yok (C3).

     ── HİÇ PROFİL YOKSA HİÇ ÇİZİLMEZ ──

     Boş bir başlık, olmayan bir bölümün sözünü vermektir (altbilgi içerik
     bandıyla aynı kural). Liste `SocialProfiles::forShell()`ten gelir ve
     yalnız yapılandırılmış, HTTPS bir adres oraya girer.

     ── ETİKET BAĞLANTIDA, İKONDA DEĞİL ──

     İkon tek başına duruyor (yanında sözcük yok) ama erişilebilir adı yine
     de ikonun kendisinde YAŞAMAZ: adı taşıyan şey tıklanan şeydir. Bağlantı
     `aria-label` alır, ikon `aria-hidden` kalır — böylece ekran okuyucu
     "GitHub'da Zabuno, bağlantı" der, "grafik" demez ve aynı şeyi iki kez
     okumaz (`ICON-03`).

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
                    aria-label="{{ $link['label'] }}"
                ><x-phosphor :name="$link['icon']" /></a>
            </li>
        @endforeach
    </ul>
@endif
