{{-- KISA DOĞRUDAN CEVAP — yönerge §13.3'ün ilk gereksinimi.

     H1'in hemen altında, tek paragraf, alıntılanabilir. Cevap sistemleri
     sayfanın başından okur; sonda duran bir cevap cevap değildir. Kendi
     başlığı YOKTUR: başlık koymak, ilk ekranı içerikten önce başlıkla
     doldurmak olurdu (`TOUCH-FIRST-INTERFACE` madde 3).

     Bir `section` de değildir ve bu bilinçli: cevabı bir bölüm kabuğuna
     sarmak, ekran okuyucuya başlığı olmayan bir bölüm ilan etmek olurdu.
     O yüzden burada tek bir paragraf var — sayfadaki en büyük düzyazı,
     başlangıç kenarında marka rayıyla (`site-content.css`). --}}
<p class="site-doc-lede" data-block="direct_answer">{{ $block->entries[0]->text }}</p>
