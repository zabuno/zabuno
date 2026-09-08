{{-- ADIM LİSTESİ — yönerge §13.3.

     Gerçekten SIRALI bir liste: adımların sırası bilginin kendisidir ve
     `ol` bunu ekran okuyucuya da söyler. Numara İŞARETLEMEDE YOK, CSS
     sayacından geliyor — elle yazılmış bir "3.", listeyi yeniden sıralayan
     kişinin güncellemeyi unutmasına açıktır ve o gün numara ile sıra
     birbirini yalanlar.

     `role="list"` bir süs değil: `list-style: none` verilen bir `ol`u
     Safari listelikten çıkarır ve "beş öğeli liste" duyurusu kaybolur.
     Kural tarayıcının değil belgenin kararı olduğu için burada yazılı.

     Adım başlığı ile açıklaması AYRI satırlarda: 320 pikselde ikisini aynı
     satıra koymak, kalın başlığın açıklamanın ilk cümlesine yapışması
     demekti. --}}
<section class="site-doc-block" data-block="how_it_works">
    <h2 class="site-doc-heading">{{ $block->heading }}</h2>
    <ol class="site-doc-steps" role="list">
        @foreach ($block->entries as $entry)
            <li class="site-doc-step">
                <strong class="site-doc-step-term">{{ $entry->term }}</strong>
                <span class="site-doc-step-text">{{ $entry->text }}</span>
            </li>
        @endforeach
    </ol>
</section>
