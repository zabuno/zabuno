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

## 5.1 İlk koşu (2026-09-07): ne bulundu, ne onarıldı, ne kaldı

Araç ilk kez koşturuldu ve **ölçüm iki kez yapıldı**: bir kez düz katalogla
(taban), bir kez sahte-yerelleştirilmiş katalogla — ikisi de `scripts/mobile-
ux-audit` ile, gerçek Chrome'da, 320×568'de, 323 hikâyenin hepsinde. Aradaki
FARK, uzayan metnin kırdığı yerdir; tabanda da kırık olan şey uzamanın işi
değildir ve öyle sayılmadı.

Sonuç, `docs/123` §5'in işaretlediği beş bileşende **on sekiz kırık**:
on üçü Ö7 (mobil denetimin ölçtüğü), dördü Ö1, biri Ö2/Ö5.
**On altısı onarıldı, ikisi onarılmadı ve sebebi aşağıda yazılı.**

### Ö7 — uzayan metnin kırdığı düzen (13 bulgu, ölçüldü)

| # | Bileşen · hikâye | Ölçüm | Durum |
| --- | --- | --- | --- |
| K1–K5 | `TrendChart` · beş hikâye | belge 326–335 px > 320 px | onarıldı |
| K6–K11 | `MenuScreenActions` · üç hikâye (taşma + kırpılma) | düğme 340–376 px | onarıldı |
| K12 | `PageHeader` · `with-breadcrumbs-and-actions` | kullanılabilir 320 → 219 px | **onarılmadı** |
| K13 | `PageHeader` · `default` | kullanılabilir 70 → 115 px | **onarılmadı** |

**K1–K5 · `TrendChart`.** Ekran okuyucu tablosu `sr-only` sınıfını DOĞRUDAN
taşıyordu ve **bir tablo 1 piksele sığmaz**: CSS genişliği tabloda bir tavan
değil bir TABANDIR, kutu en dar içeriğinin genişliğini alır. Başlık uzayınca
tablo 326–335 piksele çıktı ve mutlak konumlu olmasına rağmen belgeyi o kadar
genişletti — 320 pikselde sayfa yana kayıyordu ve kaydıran şey görünmez bir
tabloydu. Isı ızgarasında aynı kusur bulunmuş ve `docs/117` M8'de çözülmüştü;
`TrendChart` o taramada gözden kaçmıştı. Çözüm aynı: tablo bir `div.sr-only`
içine alındı. Ekran okuyucu için hiçbir şey değişmedi.

**K6–K11 · `MenuScreenActions`.** Menü hapı üç parça taşıyor (ad, saat ipucu,
"şimdi açık") ve `shrink-0` ile küçülmesi yasaklanmış, sarma izni de
verilmemişti. Metin uzayınca hap 340–376 piksele çıktı: kendisi kenardan
kırpıldı VE belgeyi kaydırdı. Kutu artık daralabiliyor, görüntü alanını
aşamıyor ve parçalar sığmadığında alt satıra iniyor. **Dokunma hedefi
küçülmedi**: asgari boy yerinde, sarma boyu yalnız artırır ve sarılan satırlar
arası boşluk ölçeğin en dar adımı — büyük hedef, sıkı boşluk.

**K12–K13 · `PageHeader` — ONARILMADI, ve sebebi bir karardır.**
İkisi de `wasted-width`: "en içteki içerik sütunu görüntü alanının %72'sini
kullanmıyor". Ölçülen şey, **hiçbir metin satırının 230 piksele ULAŞMAMASI**.
`default` hikâyesinde ekranda tek bir başlık var ("Orders" → 115 px);
`with-breadcrumbs-and-actions` hikâyesinde kırıntı izi sarıyor ve satırlar
kısalıyor. İkisinde de dolgu israfı yok — **içerik kısa**.

Bunu "onarmanın" tek yolu metni ya da kutuları o eşiğe kadar GENİŞLETMEK
olurdu; yani ekranı düzeltmek değil, ölçüyü oynamak. Sahte bir yeşil, bulduğu
gerçek kusurları bir daha hiç göstermeyen bir kapıdır. İkisi de açık kaldı ve
`default` zaten `scripts/mobile-ux-audit.baseline.json` içinde dondurulmuş
borç olarak duruyor. Sahte-yerelleştirmeyle ikisinin de sayısı YÜKSELDİ
(70→115, 320→219), yani uzayan metin burada durumu kötüleştirmiyor —
oranın kendisi kısa içerikli bir başlık için yanlış soru.

### Ö1 — kullanıcının gördüğü, katalogdan geçmeyen metin (4 bulgu)

| # | Nerede | Gömülü metin | Durum |
| --- | --- | --- | --- |
| K14 | `Breadcrumbs` (`PageHeader`) | `label = 'Breadcrumb'` | onarıldı |
| K15 | `Breadcrumbs` (`PageHeader`) | `'Empty breadcrumb trail'` | onarıldı |
| K16 | `ResponsiveDataTable` (`DashboardOverview`) | `emptyMessage = 'No data to display.'` | onarıldı |
| K17 | `SidebarNav` (`DesktopSidebar`) | `label = 'Primary'` | onarıldı |

Dördü de **prop varsayılanıydı**. `docs/35` katalog bileşenlerine metin bilmeyi
zaten yasaklıyor — metin prop olarak gelir — ama bir varsayılan o yasağın
sessiz istisnasıydı: çağıran unuttuğu gün bileşen kendi kelimesini
konuşuyordu. Ve unutuluyordu: panelin ANA ekranındaki tablo
(`DashboardPage`) tam olarak `'No data to display.'` cümlesine düşüyordu.

`I18N-SSR-RATCHET` bunları göremez, çünkü o kapı SUNUCUDA üretilen metni
ölçer; bunlar React tarafında yaşıyordu. Boşluk artık kapalı:
`resources/js/i18n/embedded-text.contract.test.ts` (DS-I18N-EMBEDDED-01…03).

Çözüm varsayılanı BAŞKA bir metinle değiştirmek değil, varsayılanı
KALDIRMAKTIR: prop zorunlu oldu, çağıran ya kataloğundan bir metin verir ya da
derlenmez. Aynı zincirin öteki ucunda duran `MobileChrome`'un
`title={navLabel ?? 'Menu'}` geri düşüşü de bu yüzden birlikte kapandı.

### Ö2/Ö5 — arayüzde birleştirilen cümle (1 bulgu)

| # | Nerede | Durum |
| --- | --- | --- |
| K18 | `WorkspaceSwitcherTrigger` (`DesktopSidebar`) | onarıldı |

Çalışma alanı seçicisinin erişilebilir adı
`${workspaceName} — ${t('workspace.current.switch')}` diye kuruluyordu.
Sahte-yerelleştirilmiş katalogda o düğme tek bir cümle değil **iki ayrı
parça** olarak göründü (`⟦…⟧⟦…⟧`) — bu, §4 tablosundaki "baş/son köşeli
ayraç" satırının tam olarak söylemek için var olduğu şey. Çevirmen o hâlde
tireyi kaldıramaz, adı cümlenin sonuna alamaz, ismin hâlini uygulayamaz.
Artık tek anahtar ve **adlı** yer tutucu: `{workspace} — Switch workspace`.

### Ö3 — sıralı yer tutucu: aranmış, bulunamamış

Çalışma alanı kataloğunun 1579 metninin hiçbirinde `%1$s`/`%s` yok. Bu bir
tahmin değil, bir kapı: DS-I18N-EMBEDDED-03 her koşuda katalogu tarar.

### Dokunulmayanlar ve neden

- **`StatValue`** ve içindeki `▲`/`▼` glifleri: bu bileşen üzerinde AYNI ANDA
  başka bir oturum çalışıyordu; ikinci bir yazar çakışma üretirdi. Bileşenin
  kendi Ö1 kusuru (`TREND_LABEL` kodda gömülü İngilizce) o oturuma aittir.
  `TrendChart` içinde `StatValue` ya da bu glifler geçmiyor; oradaki onarım
  bu sınırı hiç yoklamadı.
- **Beş bileşenin dışındaki dondurulmuş borç**: onay kutusu 16×16, metin
  bağlantısı 19 px, dört `wasted-width` hikâyesi. Bunlar tabanda da var, yani
  uzamanın işi değil; `docs/117` M5–M9'un konusu.

### Aracın kendi sınırı — ölçüldü, kaydedildi

§4 "aksanlı harfler katalogdan GEÇMEYEN metni açığa çıkarır (Ö1)" diyor.
Bu **sunucu tarafında** doğru (`PseudoLocalizingTranslator` yalnız katalog
metnini dönüştürür), **Storybook'ta değil**: oradaki decorator katalog
girdilerini değil, çizilmiş ağaçtaki METİN DÜĞÜMLERİNİ dönüştürür — ürün
adını, saati ve rakamı da. Yani hikâyelerde her şey dönüşür ve gömülü metin
dönüşmemiş olarak GÖRÜNMEZ; ayraç sayısı da veriyi cümle parçası sanabilir
(bir deneme taraması 72 hikâyede 184 bulgunun çoğunu böyle üretti).

Sonuç: Storybook koşusu Ö7'yi ölçer ve Ö2/Ö5 için bir İŞARET verir; Ö1'i
ölçmez. Bu paketteki Ö1 kırıkları o işaretin gösterdiği dosyalar okunarak
bulundu ve bulunduktan sonra bir kapıya bağlandı. Aracı Ö1'i de ölçer hâle
getirmek ayrı bir iştir; bugün yapılmadı ve yapılmış gibi gösterilmiyor.

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
