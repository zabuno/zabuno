# 79 — Misafir ürün-içi cümle okumaz (FF-21)

## Ne görüldü

`docs/75` ile başlık gerçek adı taşımaya başladı. Localhost'ta 375 px'lik bir
telefonda bakıldığında şu çıktı:

```
Zeytin Restoranları
Kadıköy Şubesi
Bahariye Cd. No:1, İstanbul
+90 216 555 12 34
Yayınlanan menü — güncel yayınlanmış sürüm gösteriliyor.   ← ?
1 kategori, 1 ürün
```

Son iki satırdan biri **bizim kavramımız**. "Yayınlanmış sürüm" misafirin
sorduğu bir soru değil; sürümleme ürünün iç meselesi. Sayfa artık kendi
kimliğini söyleyebiliyorken bu cümle, gerçek bilginin hemen altında duran en
zayıf satırdı.

Cümle **her zaman** yanlış değildi. Adın bilinmediği bir sayfada (kimlik
alanı eklenmeden önce yayınlanmış menüler) misafire hiç değilse ne baktığını
anlatır. Bu yüzden silinmedi: **kimlik bilinmiyorsa** gösterilmeye devam
ediyor.

## Testin yakaladığı ikinci yer

Kuralı donduran test yazıldığında ikinci bir yer daha çıktı:

```html
<meta name="description" content="Yayınlanan menü — güncel yayınlanmış sürüm.">
```

Aynı cümle, **paylaşım önizlemesinde**. Bu sayfa çoğunlukla WhatsApp'ta
paylaşılıyor ve bağlantıyı gören kişi başlıktan sonra ilk bunu okuyor. Orada
"yayınlanmış sürüm" hiçbir şey anlatmaz.

Artık açıklama şubeyi ve adresi taşıyor:

> Zeytin Restoranları · Kadıköy Şubesi · Bahariye Cd. No:1, İstanbul

## Yol boyunca çıkan gerçek kusur

`@php($x = $fn($y))` — Blade'in **tek satırlık** `@php(...)` biçimi iç içe
parantezi doğru kapatmıyor. Derlenen çıktı `<?php($pageDescription = ...)`
oluyor: noktalı virgül yok, kapanış yok, ve şablonun geri kalanı PHP olarak
yutuluyor. Belirti alakasız görünüyordu — sayfanın çok aşağısında
"Undefined variable $categoryCount".

Blok biçimi (`@php ... @endphp`) kullanılıyor ve sebebi yorumda duruyor.

## Kanıt

`PUB-IDENTITY-NO-INTERNAL-COPY-01` — kimlik varsa cümle **yok**, kimlik
yoksa **var**. Tek test, iki yönü de donduruyor.

## Ürün iddiası

Çalışır: misafir sayfada yalnız kendisine ait bilgiyi okur; paylaşılan
bağlantının önizlemesi restoranı tarif eder.
