{{-- ÖNE ÇIKAN YETENEKLER — okunmaz, TARANIR.

     Sekiz yetenek düzyazı değildir: okuyan kişi aradığını arar. Bu yüzden
     tek uzun sütun değil, akışkan bir ızgara — 320 pikselde tek sütun, ekran
     genişledikçe kendiliğinden çoğalır. Kırılma noktası yok (`MP-05`).

     Ad ve açıklama bir TANIM listesidir: ikisi arasındaki ilişki görsel değil
     anlamsaldır. İkon yok (`docs/136` §4, ICON-04): ayrım tipografi, ray ve
     boşlukla kuruluyor. --}}
<section class="site-doc-block" data-block="capabilities">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    <dl class="site-doc-cards">
        @foreach ($block->entries as $entry)
            <div class="site-doc-card">
                <dt class="site-doc-card-term">{{ $entry->term }}</dt>
                <dd class="site-doc-card-text">{{ $entry->text }}</dd>
            </div>
        @endforeach
    </dl>
</section>
