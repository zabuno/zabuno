{{-- İLK BOYAMANIN YAZI TİPİ — YALNIZ O (FF-195).

     Yazı tipi bir CSS dosyasının İÇİNDEN keşfedilir: tarayıcı önce HTML'i,
     sonra CSS'i, ancak ondan sonra `@font-face`'i görür ve isteği o anda
     açar. Bu zincir, LCP metnini taşıyan yazı tipini gereksiz yere iki tur
     geciktirir; `swap` altında sonucu görünür bir yeniden akıştır.

     `preload` o iki turu atlar: istek HTML ayrıştırılırken açılır.

     YALNIZ TEK DOSYA. Latin alt kümesi her sayfanın ilk boyamasında
     kesinlikle gerekir (43 KB). `latin-ext` yalnız Türkçe/Almanca/Fransızca
     karakter geçen sayfalarda, `cyrillic` yalnız Rusça sayfalarda gerekir;
     onları da preload etmek, İngilizce bir paneli açan herkese hiç
     kullanmayacağı 53 KB indirtirdi. Her şeyi preload etmek tarayıcıya
     "hepsi en önemli" demektir; o da "hiçbiri" demekle aynı kapıya çıkar.

     `crossorigin` ZORUNLUDUR — aynı kaynaktan gelse bile. Yazı tipi
     istekleri her zaman anonimdir; `crossorigin` taşımayan bir preload ayrı
     bir önbellek girdisine düşer ve dosya İKİ KEZ indirilir. Yani eksik bir
     öznitelik, preload'u hızlandırmadan yavaşlatmaya çevirir.

     `Vite::asset` derleme manifest'inden okur: dosya adı içerikten türeyen
     bir parmak izi taşır, dolayısıyla uzun önbellek güvenlidir ve yazı tipi
     güncellendiği gün adres kendiliğinden değişir. --}}
<link rel="preload" as="font" type="font/woff2"
      href="{{ Vite::asset('resources/fonts/roboto-latin-wght-normal.woff2') }}" crossorigin>
