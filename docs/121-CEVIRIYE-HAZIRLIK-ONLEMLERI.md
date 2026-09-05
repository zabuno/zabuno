# 121 — Çeviri en sonda, hazırlık en başta: şimdi alınmazsa sonradan pahalı önlemler

**Sahibin kararı (2026-09-05):** *"Şu an frontpages'da tercüme işine
girmeyeceğiz. Önce sayfalar oluşacak, sonra SEO/ASEO/pSEO çerçevesi, sonra
tasarım, sonra içerikleri daha da geliştireceğiz, waterfall enterprise hedefi
bitecek, en son ben dediğimde tercümeye geçeceğiz. Ama masterpage, tercüme
için altyapısı hazır olacak. En başında tercüme için gereken tüm önlemleri
alacağız. En son tercüme yaparken sorun yaşamamalıyız."*

## 1. Sıra — bağlayıcı

```
1. Sayfalar oluşur                (iskelet + kütük + kabuk)
2. SEO / AEO / pSEO çerçevesi     (metadata, structured data, sitemap)
3. Tasarım                        (görsel dil, hareket, medya)
4. İçerik derinleşir              (sayfa ve metin geliştirme)
5. Waterfall enterprise hedefi biter
6. ⟵ SAHİBİN AÇIK KOMUTU
7. Çeviri
```

**Altıncı adım bir kapıdır, bir aşama değil.** `ÇEVİRİLERE BAŞLA` denmeden
yedinciye geçilmez; çeviri kilidi dört katmanda kapalı (`docs/119` §10.2).

## 2. Çeviri neden "sonra hallederiz" ile hallolmaz

Çeviri bir metin işi gibi görünür ama büyük kısmı **yapı işidir** ve o yapı
metin yazılırken kurulur. Sonradan kurulamayan şeyler şunlar:

Bir cümle iki bileşene bölünmüşse, o cümle hiçbir dile çevrilemez — çeviren
kişi yarım cümleyi görür. Bir metin kodda gömülüyse katalogda hiç görünmez ve
kilidi açmak işe yaramaz. Bir yer tutucu sırayla numaralanmışsa, kelime sırası
değişen bir dilde cümle bozulur. Bir görselin içine yazı gömülmüşse her dil
için yeni bir görsel gerekir.

Bunların hiçbiri çeviri gününde fark edilmez — **hepsi çeviri gününden aylar
önce yazılmış koddur.**

## 3. On üç önlem — hepsi bugün alınabilir, hiçbiri çeviri değildir

### Ö1 — Kullanıcının gördüğü hiçbir metin kodda gömülü olmaz

Bu deponun kapısı zaten var (`I18N-SSR-RATCHET`). Yeni yüzeylerde de geçerli.

### Ö2 — Cümle birleştirilerek kurulmaz

`"Toplam " . $n . " ürün"` çevrilemez: hangi parçanın nereye geleceği dile
göre değişir. Tek katalog anahtarı, içinde yer tutucu.

### Ö3 — Yer tutucular ADLI olur, sıralı değil

`{count} ürün {menu} menüsünde` — `%1$s`, `%2$s` değil. Kelime sırası
Türkçede, Almancada ve Arapçada farklıdır; sıralı yer tutucu çevirmene sırayı
değiştirme hakkı vermez.

### Ö4 — Çoğul, `if (n === 1)` ile yapılmaz

Arapçada **altı**, Rusçada **üç**, Farsçada **iki** çoğul biçimi vardır.
İngilizce ikili mantık (tekil/çoğul) bu dillerin hiçbirinde doğru değildir.
Katalog çoğul biçimlerini taşımalı.

### Ö5 — Cümle parçaları arayüzde birleştirilmez

"Sil" + " " + ürün adı gibi kurulan başlıklar, ismin hâli olan dillerde
bozulur. Her tam cümle tek anahtardır.

### Ö6 — Görselin içine yazı gömülmez

Gömülürse dokuz dil için dokuz görsel gerekir ve hiçbiri aranabilir olmaz.
Yazı görselin üstünde HTML katmanı olarak durur.

### Ö7 — Düzen metin UZAMASINA dayanıklı olur

Almanca İngilizceden ortalama **%35 uzundur**; kısa etiketlerde bu oran
%100'e çıkar. Sabit genişlikli bir düğme İngilizcede güzel, Almancada
kırpılmış görünür. **320 pikselde bu iki katı acıtır.**

### Ö8 — Tarih, sayı ve para birimi biçimlendiriciden gelir

Elle kurulan `₺{n},00` yen'de yanlıştır (JPY'de ondalık yok), Almancada
yanlıştır (ondalık ayırıcı virgül). Bu depoda `MoneyFormatter` var; elle
biçimlendirme yasak.

### Ö9 — Sıralama locale'e duyarlı olur

Türkçede `i` ile `ı`, Almancada `ä`, İsveççede `å` farklı yerlere gider.
Ham `sort()` bir dilde doğru, ötekinde yanlıştır.

### Ö10 — CSS **mantıksal** özellikler kullanılır

`margin-inline-start`, `padding-inline-end`, `text-align: start` — asla
`margin-left`. Dokuz dilin **ikisi sağdan sola** (`ar`, `fa`) ve fiziksel
özellik kullanan her kural o iki dilde ters çalışır. Sonradan taramak yüzlerce
dosya demektir.

### Ö11 — Geri düşen metin `lang` özniteliği taşır

İngilizce sayfada Türkçe kalmış bir alan varsa o öğe `lang="tr"` demelidir:
ekran okuyucu doğru telaffuz eder, arama motoru doğru anlar.

### Ö12 — Katalog anahtarları KARARLIDIR

Anahtarı yeniden adlandırmak, o metnin bütün çevirilerini öksüz bırakır.
Anahtar bir kimliktir; metin değişir, anahtar değişmez.

### Ö13 — Belirsiz metin için çevirmen notu bulunur

Tek başına `"Open"` çevrilemez: fiil mi ("aç"), sıfat mı ("açık")? Kısa ve
belirsiz her anahtar bağlam notu taşır.

## 4. Bunları ölçen tek araç: sahte-yerelleştirme

On üç önlemin **dördü** (Ö1, Ö2, Ö3, Ö7) çeviri yapılmadan, tek bir kelime
çevrilmeden ölçülebilir — ve ölçülmezse çeviri gününe kadar görünmez.

**Sahte-yerelleştirme (pseudo-localization):** katalogdaki her metin, gerçek
bir dile çevrilmeden mekanik olarak dönüştürülür:

```
"Save changes"  →  "⟦Şåvê çhàñgêš ····⟧"
```

Üç şey aynı anda görünür hâle gelir:

| Dönüşüm | Neyi açığa çıkarır |
| --- | --- |
| Aksanlı harfler | Katalogdan GEÇMEYEN metin — dönüşmemiş kalır, gözle bulunur (Ö1) |
| Sonuna dolgu (%35–%50) | Uzayan metnin kırdığı düzen (Ö7) |
| Baş/son köşeli ayraç | Ortasından kesilen ya da parça parça kurulan cümle (Ö2, Ö5) |

**Bu bir çeviri değildir.** Hiçbir dile ait değil, hiçbir çevirmen çalışmadı,
kilit açılmadı. Yalnız bir ölçüm dilidir ve yalnız geliştirmede açılır.

**320 pikselle birlikte kullanılır.** Sahte-yerelleştirilmiş katalogla
`scripts/mobile-ux-audit` koşturulduğunda, Almancanın dar ekranda ne kıracağı
bugünden görülür — Almanca tek kelime yazılmadan.

## 5. Kapı: ne zaman kırılır

| Kapı | Ne ölçer | Ne zaman kırılır |
| --- | --- | --- |
| `I18N-SSR-RATCHET` | Kodda gömülü metin | Yeni gömülü metin eklendiğinde |
| Sahte-yerelleştirme + mobil denetim | Ö1, Ö2, Ö7 | Uzayan metin düzeni kırdığında |
| Mantıksal CSS taraması | Ö10 | Fiziksel yön özelliği eklendiğinde |
| Yer tutucu eşliği | Ö3 | Kaynak ve çeviri yer tutucuları ayrıştığında (kapı zaten var) |
| Çeviri kilidi | — | Kilit kapalıyken sağlayıcı çağrıldığında |

## 6. Bu belge neyi VAAT ETMİYOR

Bu önlemler çeviriyi **ucuzlatır**, kusursuz yapmaz. Çeviri günü hâlâ insan
işi olacak: bağlam, ton, pazar bilgisi ve hukuki metinler makineyle
kapanmaz. Vaat edilen tek şey, o gün karşılaşılacak sorunların **yapısal**
olanlarının bugün çözülmüş olmasıdır.

Ayrıca: sahte-yerelleştirme sağdan sola yazımı ölçmez. Onu ölçen şey `ar` ve
`fa` ile gerçek bir sayfa açmaktır ve o ölçüm `docs/120` §7'de duruyor.

## 7. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6. Sahte-yerelleştirmenin dolgu oranı (%35–%50) Almancanın
ortalamasından geliyor; dokuz dilin gerçek katalogları geldiğinde ölçülüp
düzeltilebilir.
