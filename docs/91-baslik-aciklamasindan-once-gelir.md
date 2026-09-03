# 91 — Başlık, açıklamasından önce gelir (FF-33)

## Ne görüldü

`docs/89` fiyat sayfasının çift başlığını düzeltti: sayfanın kendi `<h1>`'i
kaldırıldı ve başlık bölüme devredildi.

Canlıda görülen sonuç:

```
What a restaurant pays to publish its menu behind a QR code.
Pricing
Starter · Free
```

Giriş cümlesi yukarıda kalmıştı. Okuyucu **neyin açıklamasını okuduğunu
ancak sonraki satırda** öğreniyordu.

Cümle artık başlığın hemen altında; bölüm onu isteğe bağlı bir değer olarak
alıyor, böylece ana sayfada (bölüm bir alt başlıkken) tekrarlanmıyor.

## Testin beşinci kez öğrettiği şey

İlk iddia ham metin konumuna bakıyordu:

```php
$heading = strpos($html, '<h1');
$lead = strpos($html, 'What a restaurant pays');
```

Kırmızı kaldı — çünkü **aynı cümle `<head>` içindeki `meta` açıklamasında da
geçiyor** ve arama onu önce buluyor.

Bu, bu oturumda beşinci kez aynı ders: **ham metin araması "kullanıcı bunu
görüyor mu" sorusunu cevaplamaz.** Sınıf adı stil bloğunda geçer, cümle
`meta` etiketinde geçer, anahtar JSON haritasında geçer. İddia elemente ya da
bölgeye bakmalı — burada `<main>` gövdesine.

## Kanıt

`PUBLIC-PAGE-LEAD-AFTER-TITLE-01` — gövdede `<h1>`, açıklamasından önce
gelir.

## Ürün iddiası

Çalışır: fiyat sayfası önce ne olduğunu söyler, sonra kendini açıklar.
