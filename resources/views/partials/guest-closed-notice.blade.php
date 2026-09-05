{{-- ŞUBE ŞU ANDA KAPALI — misafirin gördüğü HER yüzeyde aynı şerit (FF-143).

     NEDEN AYRI BİR PARÇA. FF-141 bu şeridi yalnız menü sayfasına koydu ve
     işaretlemeyi doğrudan o şablonun içine yazdı. İkinci yüzey (tek ürünün
     sayfası) geldiğinde önümüzde iki yol vardı: işaretlemeyi kopyalamak ya da
     tek bir yere taşımak. Kopya, ilk gün AYNI görünür; ayrışması ancak biri
     düzeltilip diğeri unutulduğunda —yani en kötü anda— fark edilir. Menü
     sayfasında "yarın 09:00" derken ürün sayfasında hiçbir şey demeyen bir
     ürün, misafiri yola çıkarır.

     KARAR BURADA VERİLMEZ. Bu dosya yalnız ÇİZER; kapalı mıyız ve açılış ne
     zaman sorularının tek cevabı `ResolveGuestMenuView::closedNoticeForMenu`
     içindedir. İki hesap bir gün iki cevap verir.

     BOŞ KAP BIRAKILMAZ. Şube açıksa, saati hiç girilmemişse ya da hafta
     okunamıyorsa `$closedNotice` `null` gelir ve buradan HİÇBİR düğüm
     çıkmaz — sayfanın üstünde sebepsiz bir boşluk ve ekran okuyucuda boş bir
     duyuru bölgesi bırakmamak için.

     DURUM RENGE YASLANMAZ. Cümlenin kendisi "şu anda kapalıyız" der ve
     `role="status"` ile duyurulur; rengi göremeyen ya da ekranı görmeyen
     misafir için renk hiçbir şey anlatmaz (WCAG 1.4.1). Bu yüzden şeridin
     hiç rengi yok: yalnız metin, kalınlık ve bir ayraç çizgisi.

     `data-guest-state` MAKİNE içindir: testler ve ölçüm, hangi hâlin
     çizildiğini cümlenin çevirisine bakmadan ayırt edebilmeli — servis dışı
     sayfasında da aynı gerekçeyle var.

     ÜSLUP: biçim `style` özniteliğinde durur, sınıf adında değil. Bu şerit
     iki AYRI şablonda (menü ve ürün sayfası) yaşıyor ve o iki şablonun CSS
     blokları ortak değil; sınıfa yaslansaydı, birinde biçimli diğerinde
     biçimsiz görünürdü. --}}
@php
    /* Şerit BİLDİRİLMEDİYSE de sayfa çalışır: `@include` eden bir şablonun
       değişkeni geçirmeyi unutması, misafirin sayfasını bozmamalı. */
    $notice = $closedNotice ?? null;
@endphp
@if ($notice !== null)
    @php
        /* Metin ŞABLONDA değil KATALOGDA yaşar (`docs/85`): Blade'e yazılan
           bir cümleyi sahip hiçbir PO dosyasından çeviremez.

           Dilin çözüm sırası, sayfanın geri kalanının metinlerini çözen
           sırayla AYNIDIR (bkz. `public-menu.blade.php` içindeki `$gt`).
           Ayrı bir sıra kullansaydık, aynı sayfada şerit bir dilde, menü
           başka bir dilde konuşabilirdi.

           Saat ve gün UYDURULMAZ: `GuestText::closedNotice` yalnız veriden
           çıkan bir açılış için ikinci satırı kurar; çıkmıyorsa cümle tek
           satır kalır. */
        $closedText = app(\App\Support\Localization\GuestText::class)->closedNotice(
            $guestLocale ?? $contentLocale ?? 'tr',
            $notice->nextOpeningClock,
            $notice->nextOpeningIsoWeekday,
            $notice->nextOpeningIsToday,
        );
    @endphp
    <p role="status" class="qr-menu-closed-notice" data-guest-state="closed"
       @if ($notice->nextOpeningClock !== null) data-next-opening="{{ $notice->nextOpeningClock }}" @endif
       style="margin:0;padding:12px 16px;border-bottom:1px solid currentColor;font-weight:700">
        {{ $closedText['notice'] }}
        @isset($closedText['nextOpening'])
            {{-- Saat YALNIZ şubenin kendi haftasından çıkıyorsa basılır;
                 anahtar yoksa satır hiç çizilmez. --}}
            {{ $closedText['nextOpening'] }}
        @endisset
    </p>
@endif
