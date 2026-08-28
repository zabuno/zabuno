# 82 — Bugün tükendi (P1-04)

## Önce ne oluyordu

Akşam servisinde balık bitti.

Sahibin tek seçeneği ürünü **gizlemekti**. O zaman ürün menüden tamamen
kayboluyor; misafir "bugün balık var mı?" diye soruyor, garson "vardı, bitti"
diyor — dijital menünün çözmesi gereken sürtünme **aynen kalıyor**. Ertesi
sabah sahip altı ürünü tek tek geri açmak zorunda.

Şemada "var ama tükendi" diye bir ara durum ifade edilemiyordu: yayın
snapshot'ı görünmez ürünü tamamen dışarıda bırakıyor.

## Şimdi ne oluyor

```
Levrek                                          420.00 TRY
[Bugün tükendi]
```

Ürün **menüde kalıyor**, fiyatı görünüyor, bugün alınamayacağı **metinle**
belli.

## Üç karar

### 1. Görünürlükten ayrı bir eksen

- **Gizli** ürün menüde **yoktur**.
- **Tükenmiş** ürün menüde **vardır**, ama bugün alınamaz.

İkisi birbirini silmez; gizli bir ürün tükendi işaretinden etkilenmez.

### 2. Yayın gerektirmez — açık karar

Gereksinim bunun **açıkça kararlaştırılmasını ve testle dondurulmasını**
istiyordu (kriter 3). Karar: **tükendi işareti yayın gerektirmeden misafire
yansır.**

Sebep: "balık bitti" servis sırasında geçerli, dakikalık bir gerçektir. Yayın
beklemek hem yavaştır hem **tehlikelidir** — sahibin taslağında yarım kalmış
bir fiyat düzenlemesi olabilir ve yayın onu da canlıya iterdi.

**Yayın snapshot'ı değişmez.** Tükendi, donmuş menünün üstüne konan bir
**tebeşir notudur**; menünün kendisi değil. Bir test snapshot'ın ve sürüm
numarasının aynı kaldığını donduruyor.

Bunun için snapshot satırları artık menü satırı kimliğini taşıyor — notun
hangi satıra ait olduğunu bilmesi gerekiyor.

### 3. Bayrak değil, zaman damgası

"Bugün tükendi" cümlesindeki **bugün**, şubenin kendi saat diliminde bir
gündür. İstanbul'da gece yarısı, sunucunun UTC'sinde henüz dündür.

Bayrak olsaydı sahip her sabah altı ürünü tek tek geri açardı — ya da bunu
yapan bir zamanlanmış görev yazardık ve **o görev çalışmadığı gün menü
sessizce yanlış kalırdı**.

Damga, hiçbir arka plan işi olmadan doğru cevabı verir: sorulduğu anda
hesaplanır. Dün tükenen balık bugün yeniden vardır.

## Erişilebilirlik

Tükendi **metinle** söylenir. Yalnız renk ya da soluklukla anlatmak, renk
göremeyen misafir için hiçbir şey anlatmaz (WCAG 1.4.1). Solukluk yardımcıdır,
tek başına anlatmaz.

## Yol boyunca iki muhafız haklı çıktı

### Domain katmanı çerçeve bilmez

İlk yazdığım `StockState`, `Illuminate\Support\Carbon` kullanıyordu. Mimari
kapısı yakaladı; sınıf PHP'nin kendi tarih tipleriyle yeniden yazıldı ve
"şimdi" dışarıdan verilir hâle geldi — bu, "yarın ne olur" sorusunu
sınanabilir de yaptı.

Ama aynı kapı, **kararın gerekçesini yazmayı cezalandırıyordu**: "burada
bilerek `Illuminate\Support\Carbon` kullanmıyoruz" diyen bir **yorum** ihlal
sayılıyordu. Bir yorum bağımlılık yaratmaz. Kapı artık yorumları önce
düşürüyor; gerçek bir ihlali hâlâ yakaladığı mutasyonla doğrulandı.

### Çevrilemez dize borcu

"Bugün tükendi" doğrudan Blade'e yazılınca borç cırcırı 69 → 70'e çıktı ve
haklıydı: sahip Blade'e yazılmış bir cümleyi hiçbir PO dosyasından çeviremez.

Bunun üzerine **misafir yüzeyi için gerçek bir katalog açıldı** (`guest`
alanı) — yani P1-06'nın temeli atıldı.

**Kaynak dili Türkçe.** Diğer kataloglar İngilizce kaynaklıdır ve bu ayrım
bilinçli: panel metinleri **ürünün** dilidir, misafir sayfası **restoranın**
dilidir ve o sayfadaki her metin bugün zaten Türkçedir. Kaynağı İngilizce
yapmak, çeviri dosyası doldurulana kadar Türk bir restoranın menüsünde
İngilizce bir cümle gösterirdi — var olmayan bir sorunu çözmek için gerçek
bir gerileme.

## Kanıt

`tests/Feature/MenuCatalog/OutOfStockTest.php` (6),
`MenuCatalogWorkspace.stock.test.tsx` (3)

| Requirement | Ne donduruluyor |
| --- | --- |
| `STOCK-GUEST-VISIBLE-01` | Ürün menüde kalır, fiyatı görünür, tükendiği metinle bellidir |
| `STOCK-NO-PUBLISH-01` | Yayın snapshot'ı ve sürüm numarası değişmez |
| `STOCK-INDEPENDENT-OF-VISIBILITY-01` | İki eksen birbirini silmez |
| `STOCK-RESETS-NEXT-DAY-01` | Dün tükenen balık bugün yeniden vardır |
| `STOCK-BULK-01` | Tek ekrandan çoklu işaretleme ve geri getirme |
| `STOCK-AUTHZ-01` | Salt-okunur üye işaretleyemez |

Başka bir restoranın satırı buradan işaretlenemez.

## Ürün iddiası

Çalışır: sahip servis sırasında bir ürünü "bugün tükendi" işaretler, misafir
onu menüde fiyatıyla birlikte ama alınamaz olarak görür, ve işaret ertesi
sabah kendiliğinden düşer.
