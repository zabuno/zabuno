# 132 — Sağdan sola hazırlık: altyapı, çeviri değil

**Bu belge bir çeviri işi anlatmıyor.** Tek kelime çevrilmedi, `shipped_locales`
genişletilmedi, çeviri kilidine dokunulmadı (`docs/119` §10.2). Anlatılan şey
şu: dokuz dilin ikisi sağdan sola yazılıyor (`ar`, `fa` — `docs/120` §2) ve o
gün geldiğinde arayüzün ters yönde ayakta kalması, bugün yazılan CSS'e bağlı.

`docs/121` Ö10'un cümlesi bu paketin gerekçesidir:

> `margin-inline-start`, `padding-inline-end`, `text-align: start` — asla
> `margin-left`. Fiziksel özellik kullanan her kural o iki dilde ters çalışır.
> Sonradan taramak yüzlerce dosya demektir.

---

## 1. Somut yolculuk — Arap bir restoran sahibi paneli kendi dilinde açtı

Kahire'de bir restoran sahibi paneli Arapça açıyor. Yazı sağdan sola akıyor:
menü listesi sağdan başlıyor, kenar çubuğu sağa geçiyor, "Kaydet" düğmesi
cümlenin bittiği yerde duruyor. Bu **paketten önce de** kısmen çalışıyordu —
belgenin yönü zaten locale'den türüyordu (`DocumentLocale::direction()`,
`tests/Feature/Rtl/CriticalFlowDirectionTest.php`).

**Bu paketten önce o ekranda üç şey kırıktı ve hiçbiri görünmüyordu:**

1. **Fiyat sayfasındaki madde işaretli liste ters taraftan giriyordu.**
   `pl-5` "soldan 20 piksel boşluk" demek. Arapçada metin sağdan başlar,
   yani madde imleri sağda, boşluk solda kalıyordu: imler satırın içine
   giriyor, metin kenara yapışıyordu. Aynı kusur yardım sayfasındaki "İlk
   15 dakika" adımlarında da vardı (altı yerde).

2. **Tablo yamuk çiziliyordu.** Tablonun ilk hücresine "üst-sol köşeyi
   yuvarla" deniyordu. Arapçada ilk hücre ekranın SAĞ ucundadır; yani sağ
   uçtaki hücrenin sol köşesi yuvarlanıyor, sağ köşesi keskin kalıyordu.
   Sahip bunu "tablo bozuk görünüyor" diye tarif ederdi ve kimse sebebini
   bulamazdı.

3. **Arapça yazı her cihazda başka boyda çiziliyordu.** Depo Arap yazısı
   barındırmıyordu; harfler işletim sisteminden geliyordu. Ölçüldü: aynı
   Arapça cümle bir yüzle 166 piksel, başka bir yüzle 222 piksel genişlikte
   çiziliyor. 320 piksellik bir telefonda bu fark, aynı cümlenin bir cihazda
   tek satır, ötekinde iki satır olması demek. Sahibin telefonunda düzgün
   görünen ekran, garsonun telefonunda taşıyordu.

**Bu paketten sonra:** üçü de kapandı ve üçünün de geri gelmesi bir kapıyla
engellendi.

**Hâlâ kırık olan (ve kayıtlı):** yan panel (çekmece) Arapçada da fiziksel
soldan giriyor. Sebebi §3'te; bu depoda düzeltilebilecek bir şey değil,
kütüphanenin kendi sınıfı.

---

## 2. Ne tarandı, ne bulundu, ne düzeltildi

### 2.1 Tarama

`app/`, `config/`, `resources/`, `routes/` altındaki **1.678 kaynak dosya**
(`.tsx`, `.ts`, `.css`, `.php`, `.blade.php` …). Aranan üç aile:

| Aile | Örnek |
| --- | --- |
| Fiziksel Tailwind sınıfı | `ml-4`, `pl-5`, `left-0`, `text-right`, `border-l-2`, `rounded-tl-lg`, `origin-top-left` |
| Ham CSS fiziksel özelliği | `margin-left:`, `padding-right:`, `left:`, `text-align: left`, `float: right` |
| JSX iç stil anahtarı | `marginLeft`, `paddingRight`, `textAlign: 'left'` |

### 2.2 Sayılar

| | Adet |
| --- | --- |
| Bulunan fiziksel yön kullanımı | **26** |
| Düzeltilen | **13** |
| Gerekçeli istisna olarak bırakılan | **13** (12 kayıt satırı) |
| Ham CSS'te bulunan fiziksel özellik | **0** — depo bu tarafta zaten temizdi |
| JSX iç stilinde bulunan fiziksel anahtar | **0** |

### 2.3 Düzeltilen 13

| Yer | Neydi | Ne oldu |
| --- | --- | --- |
| `resources/views/public/partials/pricing.blade.php` | `pl-5` | `ps-5` |
| `resources/help/en/first-15-minutes.blade.php` (3 yer) | `pl-5` | `ps-5` |
| `resources/help/tr/first-15-minutes.blade.php` (3 yer) | `pl-5` | `ps-5` |
| `resources/js/design-system/flowbite-theme.ts` — tablo gövde hücresi (4 köşe) | `rounded-tl/tr/bl/br-lg` | `rounded-ss/se/es/ee-lg` |
| `resources/js/design-system/flowbite-theme.ts` — tablo başlık hücresi (2 köşe) | `rounded-tl/tr-lg` | `rounded-ss/se-lg` |

Derlenen CSS'te doğrulandı: `border-start-start-radius` kuralı `group-first/
body:group-first/row:first:rounded-ss-lg` seçicisiyle gerçekten üretiliyor.

### 2.4 Gerekçeli 13 istisna

Hepsi `scripts/logical-direction-gate.baseline.json` içinde, **her biri kendi
gerekçesiyle**. Gerekçesi olmayan bir satır kapıyı kırar (§3).

| Yer | Jeton | Gerekçe özeti |
| --- | --- | --- |
| `DrawerPanel.side.test.tsx` (3) | `left-0`, `right-0` | Bir sınıf ADI, bir stil değil: test Flowbite'ın ürettiği sınıfı okuyor. Sınıfın sahibi kütüphane. |
| `MobileChrome.side.test.tsx` (1) | `left-0` | Aynı gerekçe. |
| `OpeningHoursFields.test.tsx` (2) | `text-left`, `text-right` | Düzenli ifade içinde: test fiziksel sınıfların YOKLUĞUNU ölçüyor. |
| `DashboardHomeV3.test.tsx` (2) | `text-left`, `text-right` | Aynı gerekçe. |
| `LocationCard.test.tsx` (2) | `text-left`, `text-right` | Aynı gerekçe. |
| `PublishStepper.test.tsx` (2) | `text-left`, `text-right` | Aynı gerekçe. |
| `ImageCropField.tsx` (1) | `origin-top-left` | Gerçekten fiziksel: bir FOTOĞRAFIN kırpma karesi okuma yönüyle aynalanmaz. `transform-origin`'in mantıksal karşılığı da yok. |

### 2.5 Kural yeni değildi — kapsamı eksikti

Bu depoda **zaten iki kapı** vardı ve ikisi de doğru şeyi ölçüyordu. Yeni
olan, aralarındaki boşluk:

| Var olan kapı | Neyi tarıyordu | Neyi kaçırıyordu |
| --- | --- | --- |
| `DS-LOGICAL-DIRECTION-06` (`design-system.guard.test.ts`) | `resources/js/**/*.ts(x)` | Yalnız `m[lr]-`, `p[lr]-`, `text-left/right` desenine bakıyor. `rounded-tl-`, `border-l-`, `left-0`, `float-left`, `origin-top-left` deseninde YOK — **altı fiziksel köşe sınıfı `flowbite-theme.ts` içinde tam bu yüzden görünmüyordu.** |
| `I18N-LOGICAL-BLADE-20/CSS-21` (`LogicalDirectionScanTest.php`) | `resources/views` + `resources/css` | `resources/help` hiç taranmıyordu — **aynı `pl-5` orada altı kez daha duruyordu.** |

Ayrıca dört bileşen testi (`OpeningHoursFields`, `DashboardHomeV3`,
`LocationCard`, `PublishStepper`) kendi işaretlemesinde aynı kuralı
yasaklıyordu.

`scripts/logical-direction-gate` iki boşluğu da kapatır: **bütün izlenen
kaynak ağacını**, geniş bir desenle tarar. Var olan kapılar kaldırılmadı —
`php artisan test` koşan bir geliştirici Node kapısını çalıştırmayabilir;
kapılar birbirinin yerine değil üstüne durur.

`LogicalDirectionScanTest`'in borç listesi bu pakette **boşaldı** (tek satırı
`pricing.blade.php`'ydi ve düzeltildi). Dosyanın kendi notu ne yapılacağını
söylüyordu: *"Sıfıra indiği gün bu liste boşalır ve kural mutlaklaşır."*

---

## 3. Kapı — yeni fiziksel yön eklendiğinde kırılır

`scripts/logical-direction-gate` · `scripts/logical-direction-gate.baseline.json`
· `scripts/logical-direction-gate.test.sh` · CI'da iki adım.

**Fikir bu depoda zaten var:** `scripts/mobile-ux-audit.baseline.json` bugünkü
borcu dondurur ve yalnız listede olmayan bir ihlalde kırılır. Bu kapı aynı
mekanizmayı kullanır, **tek farkla**: oradaki liste bir BORÇTUR (kapanacak),
buradaki bir GEREKÇEDİR (kapanmayacak). Bu yüzden her satır bir `neden`
taşımak zorunda ve **gerekçesi 20 karakterden kısa olan bir satır kapıyı
kırar** — gerekçesiz bir istisna, kapının kendisini sessizce kaldırır.

Kapının kendi testi 11 senaryo koşuyor ve hepsi geçiyor:

```
ok   mantıksal özellikler geçer
ok   fiziksel Tailwind sınıfı kırar
ok   değersiz fiziksel utility kırar
ok   ham CSS fiziksel özelliği kırar
ok   yönsüz text-align yanlış alarm vermez
ok   JSX iç stil fiziksel anahtarı kırar
ok   rtl: varyantı ihlal sayılmaz
ok   gerekçeli istisna susturur
ok   gerekçesiz istisna kapıyı kırar
ok   istisna yalnız yazıldığı dosyayı kapsar
ok   bayat istisna raporlanır ama kırmaz
```

### Kapının ÖLÇMEDİĞİ şeyler — açıkça

- **`node_modules`.** Flowbite'ın `Drawer`'ı `left-0` / `right-0` yazıyor ve
  bu kapı onu değiştiremez. **Sonuç: yan panel Arapçada da fiziksel soldan
  girer.** Bu bir borçtur, sessiz bir geçiş değil; `DrawerPanel.tsx`
  içindeki not da aynı şeyi söylüyor. Kapatma yolu: konumu kütüphaneden
  devralmak yerine mantıksal olarak kendimiz yazmak — bu paketin kapsamı
  dışında.
- **JSX'te düz `left:` / `right:` anahtarları.** `style={{ left: … }}`
  taranmıyor, çünkü `left` bu depoda yüzlerce kez bir DEĞİŞKEN adı olarak
  geçiyor (`const [left, right] = aspect.split(':')`) ve hepsini ihlal
  saymak listeyi okunamaz yapardı. `marginLeft`, `paddingRight` gibi
  bileşik anahtarlar taranıyor. **Bu bir boşluktur ve ölçülmedi.**
- **Ekranın gerçekten kırılıp kırılmadığı.** Onu §4 ölçüyor. Bu kapı
  KAYNAĞI okur; ikisi ayrı sorulardır.

---

## 4. Ölçülebilir hâle getirmek: gerçek bir RTL sayfası

`docs/121` §4 açıkça yazıyor: *"sahte-yerelleştirme sağdan sola yazımı
ölçmez."* Aksanlı harf ve dolgu, UZAYAN metnin kırdığını gösterir; TERS AKAN
bir düzenin kırdığını değil.

### 4.1 Önce bulunan şey: RTL hikâyeleri RTL değildi

Katalogda 33 hikâye `RightToLeft` adını taşıyor, Arapça metin gösteriyor ve
yönünü `parameters: { direction: 'rtl' }` ile bildiriyordu. `withDirection`
decorator'ü ise yönü `context.globals`'tan okuyor.

**Gerçek Chrome'da ölçüldü (320 px):**

```
macro-layout-pageheader--right-to-left → sarmalayıcının dir özniteliği: "ltr"
```

Yani 33 hikâyenin **32'si**, adında "sağdan sola" yazan ve gerçekte soldan
sağa çizilen hikâyelerdi. Bu bir yazım hatası değil, bir **ölçüm
yokluğuydu**: `scripts/mobile-ux-audit` o hikâyeleri 320 pikselde ölçüyor ve
"RTL 320'de temiz" diye okunabilecek her sonuç, aslında LTR'nin ikinci kez
ölçülmesiydi. Bu deponun en sık tekrar eden kusur ailesi (`docs/109` §8.7).

Düzeltildi: 32 hikâye `globals: { direction: 'rtl' }` bildiriyor ve
`resources/js/storybook/decorators.direction.test.ts` tuzağın geri gelmesini
engelliyor (4 iddia).

### 4.2 Sonra kurulan ölçüm

`node scripts/mobile-ux-audit <dizin> --direction rtl`

- **Hikâye kipinde** yön Storybook'un kendi global'i üzerinden çevrilir
  (`?globals=direction:rtl`), yani ürünün gerçekten kullandığı
  `withDirection` decorator'ünden geçer. DOM'a elle `dir` yazmak, ölçümü
  ürünün yolundan koparırdı.
- **Sayfa kipinde** `<html dir>` çevrilir; orada yönü yazan taraf zaten
  sunucudur.
- **Çeviri yok.** Tek kelime çevrilmez; yalnız yön çevrilir.
- Kendi dondurulmuş borç listesi var:
  `scripts/mobile-ux-audit.rtl.baseline.json`. Ayrı liste, çünkü yön
  değişince kusur ailesi de değişir ve tek listede tutmak hangi bulgunun
  hangi yönden geldiğini gizlerdi.

### 4.3 320 pikselde ne çıktı

| Koşu | Etkilenen hikâye |
| --- | --- |
| `--direction ltr` (varsayılan) | **16 / 331** |
| `--direction rtl` | **17 / 331** |

Tek fark: `micro-feedback-spinner--large` → `overflow-x` (322 px > 320 px).
Sebebi ölçüldü ve öğreticidir: göstergenin SVG kutusu görüntü alanının
BAŞLANGIÇ kenarına dayanıyor ve iki piksel taşıyor. **LTR'de bu taşma sola
düşer ve sola taşma kaydırılamaz** — belgenin `scrollWidth` değerine hiç
girmez, yani hiç raporlanmaz. **RTL'de aynı taşma sağa düşer ve orada
kaydırılabilir hâle gelir.** Aynı geometri, iki farklı sonuç.

Bu, RTL ölçümünün neden ayrıca gerektiğinin en temiz kanıtı: LTR koşusu bu
bulguyu göremez, göremediği için de "temiz" der.

### 4.4 Ölçüm belirlenimci hâle getirildi

İlk RTL koşuları **kararsızdı**: art arda üç koşuda dört, beş, sonra yine
beş spinner hikâyesi `overflow-x` bildirdi ve piksel değerleri 322, 323, 324
diye oynadı. Sebep: `animate-spin` 24 piksellik kareyi döndürüyor ve DÖNEN
bir karenin eksen hizalı sınır kutusu açıya göre 24 ile 34 piksel arasında
değişiyor. Ölçüm hangi milisaniyede bakıldığına bağlıydı.

Kırılgan bir kapı, olmayan bir kapıdan kötüdür (FF-195'in kendi cümlesi).
`scripts/mobile-ux-audit` artık ölçümden önce her animasyonu Web Animations
API üzerinden sıfır anına kurup durduruyor. CSS ile (`animation-play-state:
paused`) denendi ve YETMEDİ — stilin uygulandığı an ile dönüşümün yeniden
hesaplandığı çerçeve aynı görev içinde değil.

Sonuç: iki ardışık RTL koşusu **birebir aynı** çıktı; LTR koşusunun
dondurulmuş listesi de değişmedi (0 yeni ihlal, 0 düzelen).

**Ölçülmeyen:** dönüşün ARA açılarında oluşan geçici taşma. Tek bir değeri
yok, dolayısıyla bir eşiği de olamaz. Araç bunu ölçtüğünü iddia etmiyor.

---

## 5. Arap yazısı barındırma — ölçüldü, sonra karar verildi

`docs/123` §5 bunu açık bir kalem olarak taşıyordu: *"Arap yazısı
barındırılmıyor."* FF-195 boşluğu kayda geçirmiş ve kapatma yolunu
*"Noto Sans Arabic, yaklaşık +30 KB"* diye yazmıştı.

**İki şey de ölçülünce değişti.**

### 5.1 Neden gerekli — ölçüm

Barındırılmadığı sürece Arapça metin işletim sisteminin yüzüne düşer. Aynı
Chrome'da, 16 piksel gövde boyunda, aynı Arapça cümle:

| Yazı yüzü | Genişlik |
| --- | --- |
| Al Bayan | 166,19 px |
| Al Nile | 170,95 px |
| Baghdad | 175,67 px |
| Nadeem | 183,70 px |
| Damascus | 188,31 px |
| Geeza Pro | 222,48 px |

**Yayılım 56,29 piksel** — 320 piksellik bir ekranın %17,6'sı. Aynı cümle
bir cihazda tek satır, ötekinde iki satır. FF-195'in latin metin için
kaldırdığı oynaklık, `ar`/`fa` için aynen duruyordu — ve §4'te kurulan RTL
kapısı tam da o metinleri ölçüyor. Kapıyı zemin oynarken kurmak, kırılgan bir
kapı kurmaktır.

### 5.2 Dört aday — hepsi ölçüldü

Hepsinin lisansı **dosyadan okundu**, hafızadan yazılmadı. (Bu depoda bir kez
yanlış lisans iddia edilmiş ve ölçümle düzeltilmişti; aynı hata tekrar
edilmesin diye yöntem burada yazılı: `npm pack`, sonra `LICENSE` ve
`metadata.json` okunur.)

| Aday | `arabic` alt kümesi | Lisans (dosyadan) | Farsça پ چ ژ گ | Fars-Hint rakamı ۰-۹ | ۀ (U+06C0) | ﷼ (U+FDFC) |
| --- | --- | --- | --- | --- | --- | --- |
| Noto Sans Arabic v33 | 165.960 bayt | OFL-1.1 | TAM | TAM | TAM | TAM |
| Noto Kufi Arabic | 123.688 bayt | OFL-1.1 | — | — | — | — |
| **Vazirmatn v16** | **46.308 bayt** | **OFL-1.1** | **TAM** | **TAM** | **TAM** | **TAM** |
| Cairo | 30.896 bayt | OFL-1.1 | TAM | TAM | **EKSİK** | **EKSİK** |

FF-195'in yazdığı "+30 KB" tahmini, seçilen aday için değil hiçbiri için
doğru değildi: Noto Sans Arabic beş katından fazla. Cairo o büyüklüğe
yakındı ama ölçümde iki eksik verdi.

**Seçilen: Vazirmatn.** Noto Sans Arabic'in **dörtte birinden küçük** ve
ölçülen her ölçütte onunla aynı sonucu veriyor.

### 5.3 Vazirmatn'ın ölçülen kapsamı

| Ölçüt | Sonuç |
| --- | --- |
| Arapça 28 temel harf | TAM |
| Harekeler (fetha, kesra, damma, şedde, sukun, hançerli elif) | TAM |
| Farsçanın ek harfleri پ چ ژ گ ی ک ۀ | TAM |
| Urducanın ek harfleri ٹ ڈ ڑ ں ھ ے | TAM |
| Arap-Hint rakamları ٠-٩ | TAM |
| Fars-Hint rakamları ۰-۹ | TAM |
| Noktalama ، ؛ ؟ ٪ ٫ ٬ ٭ ـ | TAM |
| Riyal işareti ﷼ | TAM |
| Lam-elif bitişik biçimleri, ﷲ | TAM |
| Şekillendirme tabloları (GSUB/GPOS) | VAR — bitişme yazı tipinin kendisinden gelir |
| Latin harf / ASCII rakam / Kiril | **YOK — bilerek.** Latin metin Roboto'dan gelir; ikinci bir latin yüzü, aynı ekranda iki farklı latin ölçüsü demektir. |

**Kapsanmayan ve açıkça yazılan:**

- Arap bloğunun (U+0600–06FF) 256 kod noktasından **142'si var**. Eksikler
  Kur'an tilavet işaretleri (U+06D6–06ED) ve Afrika/Orta Asya ortografileri
  için genişletilmiş harfler. Noto Sans Arabic'te 256/256 var — fark bu.
- Arabic Extended-A (U+08A0–08FF): **0/96**.
- Presentation Forms-A (U+FB50–FDFF): 72/688. Bunlar eski, önceden
  birleştirilmiş biçimler; modern metin taban harf + şekillendirme kullanır.
- U+200F (RLM): ne Vazirmatn'da ne Noto Sans Arabic'te var — **görünmez** bir
  kontrol karakteri olduğu için çizilecek şekli de yok. Kapsam eksiği değil,
  kapsam dışı.

Kapsanmayan bir karakter **boş kutu üretmez**: tarayıcı yığındaki bir sonraki
aileye düşer, yani işletim sisteminin yüzü çıkar — bugünkü davranışın aynısı.

**"Hepsi tamam" DENMİYOR.** Kur'an metni ya da Afrika ortografileri taşıyan
bir kiracı içeriği bu yazı tipiyle kısmen işletim sistemine düşer.

### 5.4 Maliyeti kim ödüyor

**Kimse.** `unicode-range` alt kümeyi Arap yazısıyla sınırlar: Arapça karakter
geçmeyen bir sayfa bu dosyayı HİÇ istemez. Dosya `preload` de EDİLMEZ —
preload `unicode-range` filtresini atlar ve dokuz dilin yedisine hiç
kullanmayacakları 45 KB indirtirdi.

| | Bayt | KB |
| --- | --- | --- |
| Roboto latin (preload edilen tek dosya) | 43.136 | 42,1 |
| Roboto latin-ext | 29.392 | 28,7 |
| Roboto cyrillic | 23.664 | 23,1 |
| **Vazirmatn arabic (yeni)** | **46.308** | **45,2** |
| **Toplam barındırılan** | **142.500** | **139,2** |

`font-budget.json` içindeki `maxHostedKb` 100'den 145'e çıktı. **Bu bir
gevşetme değil, kapsam değişikliğidir**: sayının koruduğu şey deponun toplam
ikili yükü ve o yük ölçülerek arttı. HER ziyaretçinin ödediği bedeli koruyan
sayı `maxFirstPaintKb`'dir ve o **değişmedi** (48 KB).

### 5.5 Ölçmeseydik kaçıracağımız şey

`--font-sans` (`app.css`) güncellendi ama **yetmedi**. Gövdenin yazı tipi
`aep/tokens/base.css` üzerinden `var(--aep-font-sans)`'tan geliyor ve o yığın
ayrı bir dosyada tanımlı. Gerçek Chrome'da ölçüldü:

| Durum | `document.fonts` |
| --- | --- |
| Yalnız `--font-sans` güncel | `Vazirmatn: unloaded` — Arapça metin hâlâ işletim sisteminden |
| İki yığın da güncel | `Vazirmatn: loaded` |

İkinci durumda Arapça metnin 32 glifi, Farsça metnin 20 glifi Vazirmatn'dan
çiziliyor; latin metnin 25 glifi Roboto'dan. Kiril ve latin-ext dosyaları
`unloaded` kalıyor — `unicode-range` çalışıyor.

Kaynak, sürüm, sha256, lisans ve kapsam kaydı:
`resources/fonts/PROVENANCE.json`. Lisans metinleri:
`resources/fonts/LICENSE-Roboto.txt`, `resources/fonts/LICENSE-Vazirmatn.txt`.

---

## 6. Neyin hâlâ ölçülmediği

Bu bölüm bu belgenin en önemli bölümüdür. Ölçülmemiş bir şey "geçti" değil,
**"bilinmiyor"**dur.

1. **Yan panel (çekmece) Arapçada hangi kenardan giriyor.** Konumu Flowbite
   yazıyor (`left-0` / `right-0`) ve bu depo onu mantıksallaştıramıyor.
   Bilinen sonuç: fiziksel soldan girer. Ölçülmedi, çıkarımla biliniyor.
2. **Fotoğraf kırpma çerçevesi.** `ImageCropField` görüntüyü
   `insetInlineStart` ile konumlandırıyor; sağdan sola bir belgede bu
   `right`'a çözülür ve kırpma karesi görüntünün fiziksel geometrisidir.
   Kırpma önizlemesinin Arapça arayüzde doğru kareyi gösterip göstermediği
   **ölçülmedi**. Bir ekran görüntüsüyle karşılaştırma gerektirir; bu paket
   onu yapmadı.
3. **Sunucuda üretilen kurumsal sayfalar sağdan sola ölçülmedi.**
   `--direction rtl` sayfa kipini destekliyor ama `php artisan
   site:export-static` çıktısı bu pakette RTL koşulmadı; ölçülen yüzey
   Storybook kataloğudur (331 hikâye).
4. **iOS Safari.** Bütün ölçüm Chrome'da yapıldı. Safari'nin kendi RTL ve
   yazı tipi davranışı doğrulanmadı ve doğrulandığı iddia edilmiyor.
5. **Gerçek Arapça içerikle taşma.** Ölçüm, hikâyelerdeki kısa Arapça
   dizgilerle yapıldı. Gerçek bir Arapça katalog geldiğinde uzunluk
   dağılımı değişir; `docs/121` §7'nin sahte-yerelleştirme dolgu oranı için
   söylediği aynen geçerli.
6. **JSX'te düz `left:` / `right:` iç stil anahtarları** kapının kapsamı
   dışında (§3).
7. **`docs/120` §7'de bir sayı tutmuyor:** *"RTL'nin dokuz dilin üçünde
   gerçekten çalıştığının ölçülmesi"* diyor. Aynı belgenin §2 tablosunda
   sağdan sola yazılan dil **iki** tanedir (`ar`, `fa`); Kürtçe §8'de
   açıkça Kurmancî/Latin/LTR olarak alınmış. Bu belge iki sayısını
   kullanıyor. `docs/120`'nin sahibi o satırı düzeltmeli — burada
   düzeltilmedi, çünkü o belgenin kanonik sahibi bu paket değil.

---

## 7. Kapılar

| Kapı | Ne ölçer | Nerede |
| --- | --- | --- |
| `scripts/logical-direction-gate` | Yeni fiziksel yön özelliği eklenmesi | CI |
| `scripts/logical-direction-gate.test.sh` | Kapının kendisi gerçekten kırılıyor mu (11 senaryo) | CI |
| `scripts/mobile-ux-audit … --direction rtl` | 320 pikselde sağdan sola düzen | CI |
| `resources/js/storybook/decorators.direction.test.ts` | RTL hikâyeleri gerçekten RTL mi | `npx vitest run resources/js` |
| `resources/js/design-system/font-hosting.test.ts` | Yazı tipi barındırma, bütçe, lisans, preload | `npx vitest run resources/js` |
| `tests/Feature/Rtl/CriticalFlowDirectionTest.php` | `<html dir>` locale'den türüyor mu (önceden vardı) | `php artisan test` |
| `tests/Feature/Localization/LogicalDirectionScanTest.php` | Blade + CSS fiziksel yön (önceden vardı; borcu bu pakette boşaldı) | `php artisan test` |
| `DS-LOGICAL-DIRECTION-06` (`design-system.guard.test.ts`) | `resources/js` fiziksel yön, dar desen (önceden vardı) | `npx vitest run resources/js` |

---

## 8. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6.

- Vazirmatn seçimi **ölçüme** dayanıyor, marka tercihine değil. Gerçek
  Arapça/Farsça katalog geldiğinde okunabilirlik insan kararıyla yeniden
  değerlendirilebilir; o gün Noto Sans Arabic'e geçmenin bedeli ölçülü ve
  belli (+119.652 bayt).
- `maxHostedKb` üçüncü bir yazı sistemi eklenirse yeniden ölçülür. Sayı bir
  hedef değil, bir alarm.
- Çekmece borcu (§6.1) kapandığı gün `DrawerPanel.side.test.tsx` istisnaları
  listeden düşer ve kapı bunu "artık bulunmayan istisna" diye raporlar.
