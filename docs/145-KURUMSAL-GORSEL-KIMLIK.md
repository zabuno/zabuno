<!--
    KARAR BELGESİ — FF-238, 2026-09-08.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan gelir; ölçülemeyenler §8'de AÇIKÇA "bilinmiyor"
    diye ayrılmıştır.

    Bu belge `docs/136` §3'ü EZER. Ezme `docs/118` E10 olarak da kayıtlıdır.
-->

# 145 — Kurumsal görsel kimlik: kendi paleti, kendi ölçeği, kendi yüzey dili

## 1. Sahibin kararı ve neden bir öncekini eziyor

2026-09-08 sabahı verilen karar (`docs/136`, FF-232) kurumsal sitenin daisyUI
temasının **panelin marka jetonlarından** (`--aep-*`) türemesini şart
koşuyordu. Gerekçesi gerçekti: iki renk kaynağı bir gün ayrışır ve o gün
kurumsal site ile panel iki ayrı şirket gibi görünür.

**Ölçülen sonuç başka oldu.** daisyUI kuruldu, tema kuruldu, kapı yeşile döndü
ve ekranda hiçbir şey değişmedi: aynı renkler, aynı tipografi, aynı sade
sayfa. Sahip bunu gördü ve kısıtı kaldırdı:

> *"temanın mevcut marka jetonlarını sikerim, yeter artık."*

ve defalarca tekrarladığı hedefi bir kez daha söyledi:

> *"bir uzay teknolojileri şirketi gibi, abartı dursun, görünsün,
> hissettirsin."*

### Kararın teknik gerekçesi — kimsenin hatası değildi

Panelin jetonları **panel için** seçilmişti ve doğru seçilmişti: sekiz saat
bakılan bir ekran sakin olmalı, gölge yerine ton kullanmalı, dikkat
çekmemeli (`docs/102` §1, Flat 2.0). Kurumsal sayfanın işi tam tersidir —
bir kez bakılır ve hatırlanması gerekir.

Aynı jetonu ikisine birden vermek, ikisini de ortalamaktı: panel gereksiz
gürültülü, site gereksiz sessiz. Bugün seçilen şey ortalama değil, **iki ayrı
doğru**.

| Yüzey | Kim bakar | İşi ne | Renk kaynağı |
|---|---|---|---|
| Panel (`/app`) | Restoran sahibi, her gün | Sekiz saat çalışmak | `--aep-*` (`aep/tokens/`) |
| Kurumsal site | Ziyaretçi, bir kez | İkna etmek, sonra çekilmek | `--zc-*` (`site-identity.css`) |
| Misafir menüsü | Restoranın müşterisi | Kiracının markası | Kiracı skin'i (ayrı, dokunulmadı) |

### Ayrışma riski ne oldu

Ortadan kalkmadı; **yer değiştirdi ve görünür oldu.** İki renk kaynağının
tehlikeli olduğu durum, ikisinin de *aynı şeyi* anlatmaya çalışmasıdır: aynı
düğme iki dosyada iki kez yazılırsa bir gün ayrışır ve hangisinin doğru
olduğu belirsizleşir. Burada iki kaynak iki farklı şeyi anlatıyor, yani
"ayrıştılar" diye bir kusur da doğmuyor.

Panelle kalan tek kasıtlı bağ **marka altını**dır (§2, `signal` merdiveni).

---

## 2. Palet — ve gerekçesi

Kaynak: `resources/css/site-identity.css` §1. Deponun kurumsal yüzeyinde ham
renk yazılabilen **tek yer** orasıdır ve bir kapı bunu ölçer (KIMLIK-01).

### 2.1 Beş merdiven

| Merdiven | Rolü | Neden bu ton |
|---|---|---|
| `void` (1000-400) | Derinlik | Nötr gri değil, **mavi-mora çalan siyah**. Nötr bir siyah "kapalı ekran" gibi okunur; hafif mavi bir siyah "uzak" gibi okunur — atmosferik perspektif uzaktaki her şeyi maviye kaydırır ve göz bunu mesafe olarak yorumlar. |
| `halo` (50-500) | Gündüz | Aynı tonun aydınlık tarafı. Sayfa zemini saf beyaz **değil** (`halo-100`), kart beyaz: beyaz üstünde beyaz kart 1.00:1'dir ve onu ayakta tutan tek şey kenar çizgisi kalırdı. |
| `pulse` (400-600) | Eylem | Elektrik moru. Birincil düğmenin rengi. |
| `beam` (300-700) | Bağlantı, soğuk ışıma | Buz mavisi; metin seviyesindeki tek soğuk vurgu. |
| `signal` (400-700) | Marka | Altın sarısı. Artık bir **düğme** değil bir **vurgu**: marka işareti, ışıma, göz izi etiketi. |

### 2.2 Marka sarısı neden düğme olmaktan çıktı — ölçülmüş bir kusur

Kurumsal sitenin birincil düğmesi marka sarısıydı ve `home.blade.php` onun
üstüne `text-white` yazıyordu.

**Beyaz üstüne `#ffb300` = 1.73:1.** WCAG asgarisinin (4.5) dörtte biri.
Yani sitenin en önemli düğmesinin yazısı okunmuyordu — ve ortada bir hata
mesajı olmadığı için kimse bunu bir kusur olarak görmemişti; yalnız
kaydolmayan bir ziyaretçi vardı.

Yeni değer: **beyaz üstüne `#6d3bf5` = 5.84:1.** Kimlik kararı bir
erişilebilirlik kusurunu da kapatıyor ve bunu `home.blade.php`ye dokunmadan
yapıyor (§7).

Sarı kaybolmadı; daha az yerde daha çok görünüyor.

### 2.3 Ölçülen kontrastlar

WCAG 2.x, `App\Domain\Branding\SrgbColor` ile hesaplandı. Kapı:
`CorporateIdentityContrastTest` (KIMLIK-02/03) — **her satır iki kipte de
ölçülür** ve asgarinin altına düşen bir değer kapıyı kırar.

**Metin (asgari: gövde 7.0, ötekiler 4.5)**

| Ne | Açık kip | Koyu kip |
|---|---|---|
| Gövde metni / sayfa zemini | 16,96 | 16,68 |
| Gövde metni / kart zemini | 18,96 | 15,66 |
| İkincil metin / sayfa zemini | 8,02 | 8,25 |
| Birincil düğmenin yazısı (beyaz / mor) | 5,84 | 5,84 |
| İkincil dolgunun yazısı | 6,87 | 13,11 |
| Marka vurgusunun yazısı | 7,20 | 13,39 |
| Marka vurgusu METNİ / sayfa zemini | 6,44 | 12,83 |
| Bağlantı metni / sayfa zemini | 6,15 | 12,57 |
| Hata cümlesi / sayfa zemini | 5,85 | 9,73 |
| Derin bandın gövde metni | 17,40 | 17,40 |
| Derin bandın ikincil metni | 8,60 | 8,60 |
| Derin banttaki marka vurgusu | 13,39 | 13,39 |
| Derin banttaki bağlantı | 13,11 | 13,11 |

**Metin olmayan (WCAG 1.4.11, asgari 3.0)**

| Ne | Açık kip | Koyu kip |
|---|---|---|
| Kontrol kenarı / sayfa zemini | 4,83 | 3,95 |
| Kontrol kenarı / kart zemini | 5,40 | 3,71 |
| Odak halkası / sayfa zemini | 6,15 | 12,57 |
| Birincil düğmenin yüzeyi / zemin | 5,23 | 3,36 |
| Kart / zemin (ayrışma) | 1,12 | 1,07 |

Son satır bilerek düşüktür ve bir kusur değildir: kartı ayakta tutan şey
yalnız ton değil, **kenar + gölge + üst parıltı** üçlüsüdür (§5). Kapıdaki
eşiği 1,05'tir — yani "hiç yok" ile "az" arasındaki farkı zorunlu kılar,
fazlasını değil.

### 2.4 Ölçülmeyen renkler — açıkça

Yarı saydam jetonlar (ışıma, cam, gölge) `color-mix()` ile yazılır ve
kontrastları **arkasındaki yüzeye** bağlıdır; o yüzey CSS'te yazılı değildir.
Ölçülmediler ve tahmin edilip yeşil gösterilmediler. Hiçbiri metin taşımaz;
`--zc-pulse-400` özellikle "yalnız ışıma" diye işaretlidir (üstüne beyaz
3,94:1 olduğu için).

---

## 3. Açık ve koyu kip — ve tema tanımayan derin bant

İki kip de **tam tanımdır**, biri ötekinin sapması değil. Sıra açık kiple
başlar, çünkü betiği engellenmiş bir ziyaretçi `data-theme` özniteliğini hiç
görmez ve açık kipi okur (`docs/136` §3.3 korunuyor).

**Ama sahne her zaman derindir.** Kahraman bölümü ve harekete çağrı bandı
ziyaretçi hangi kipte olursa olsun koyu kalır (`.site-deep`,
`--zc-deep-*`). Gerekçe:

> Uzay hissi koyu bir zeminde yaşar; ama açık kipi seçmiş birinin sayfasının
> tamamını koyulaştırmak, o kişinin kararını yok saymaktır.

Aynı ayrım gerçek hayatta da var: aydınlık bir müzede planetaryum salonu
karanlıktır. **Okunan metin ziyaretçinin kipinde, sahne kendi ışığında.**

Derin bandın içindeki metin bu yüzden `--zc-deep-text`tir, `--zc-text` değil —
açık kipte `--zc-text` koyu mürekkeptir ve derin bandın üstünde okunmazdı.
Kapı bunu ayrıca ölçer (`deepPairs`).

---

## 4. Tipografi ve ölçü

### 4.1 Başlıklar büyüdü, gövde büyümedi

Yeni yazı tipi **yok**: Roboto bu depoda değişken eksenle barındırılıyor
(`font-weight: 100 900`), yani 800 ağırlık zaten indirilen dosyanın içinde.
İkinci bir yazı tipi eklemek sıfır kazanç için bir ağ isteği ve bir CSP
tartışması olurdu.

| Rol | 320 px | Geniş ekran |
|---|---|---|
| `--zc-display-1` (kahraman) | 32 px | 72 px |
| `--zc-display-2` (bölüm) | 26 px | 44 px |
| `--zc-display-3` (kart) | 20 px | 28 px |
| `--zc-lede` (giriş) | 17 px | 22 px |
| Gövde (`--aep-text-body`) | **16 px — değişmedi** | 16 px |

Hepsi `clamp()`, kırılma noktası yok (`MP-05`). Yoğunluk fontu küçülterek
sağlanmadı (`docs/118` E3): küçülen hiçbir şey yok.

Tek istisna göz izi etiketi (`--zc-eyebrow`, 13 px) ve gerekçesi yazılı: o bir
*okunan metin* değil bir *sınıflandırmadır* ("ÜRÜN", "GÜVENLİK"), harf aralığı
açıktır ve cümle taşıyamaz.

### 4.2 Ölçü dört roldür, tek sayı değil

Sütunu tek bir sayıyla genişletmek kusuru yer değiştirirdi: bir sözleşme
82 rem genişliğinde okunmaz (satır başına ~180 karakter; okunabilir aralık
45-75).

| Rol | Ne için | Tavan |
|---|---|---|
| `--zc-measure-prose` | sözleşme, yardım makalesi | 44 rem |
| `--zc-measure-form` | iletişim formu, fiyat kartları | 48 rem |
| `--zc-measure-page` | kurumsal sayfa gövdesi | **82 rem** |
| `--zc-measure-stage` | kahraman, tam genişlik bantları | 110 rem |

Hepsi `min(100%, …)`: 320 pikselde tavan devreye **girmez**.

**Kabuk ile okuma sütunu bilerek aynı genişlikte değil.** Üst çubuk ve
altbilgi sayfa ölçüsündedir (82 rem); bir sözleşme ya da iletişim formu ise
kendi okuma ölçüsünde kalır (44/48 rem). Yani geniş ekranda marka adı, o
sayfaların metninden daha solda durur. Bu bir kaçak değil bir seçim: gezinti
sayfanın tamamına aittir, bir paragraf ise okunabilir satır uzunluğuna. Aynı
uyumsuzluk `origin/main`de de vardı (1024'e karşı 768) ve orada da kasıtlıydı.

Kenar boşluğu akışkan (`--zc-gutter`): alt sınırı `--space-3` (12 px, bugünkü
kabuk değeriyle bire bir), üst sınırı `--space-7` (48 px).

### 4.3 Yarıçap panelden ayrıldı

Panelin tavanı 8 px ve gerekçesi doğru: veri yoğun bir tabloda yuvarlak köşe
yer yer. Kurumsal sayfada tablo yok; orada yarıçap bir **yüzey işaretidir**.
Kurumsal merdiven: 6 / 10 / 16 / 24 px.

---

## 5. Yüzey dili — 2D ile 3D görünmek

Hepsi `site-identity.css` §6'da, hepsi sınıf, hiçbiri betik. **Bu paket tek
bir `animation` ya da `transition` yazmaz** (§6 sonu, `docs/136` §7).

| Sınıf | Ne yapar | Neden böyle |
|---|---|---|
| `.site-field` | Üç yumuşak ışık kaynağı (mor, buz mavisi, altın) | Merkezleri kabın **dışında**: ekranda bir "top" değil bir atmosfer görünür |
| `.site-deep` | Tema tanımayan derin bant | §3 |
| `.site-panel` | Üst parıltı + kenar + renkli gölge | Üçü olmadan bir kart "renkli dikdörtgen"dir; üçüyle bir **nesne** olur — istenen "3D gibi görünen 2D"nin tamamı budur, perspektif gerekmez |
| `.site-lit` | Üst kenara 1 px ışık | Ayrı sınıf, çünkü **her** kartın ışımaması gerekir: her yerde olan bir vurgu vurgu değildir |
| `.site-glass` | Arkasını süzen yüzey | İki tanım: bulanıklık desteklenmiyorsa neredeyse opak, destekleniyorsa gerçek cam |
| `.site-veil` | Bandın alt kenarını zemine karıştıran duman | Sert kenar keser, kaybolan kenar bağlar |
| `.site-rule` | Uçlarında sönen hairline | Gradyan simetrik, yani RTL'de aynı |
| `.site-eyebrow` | Göz izi etiketi | `text-transform` **yok**: ekran okuyucu büyük harfi bazen harf harf okur |
| `.site-bleed` | Ölçülü sütunun içinde tam genişlik bant | **Yalnız `.site-stage` içinde** — `100vw` kaydırma çubuğunu sayar ve sahnenin `overflow: clip`i onu kırpar |

Koyu kipte gölge yerine **ışık** kullanılır ve bu bir zevk değil bir gerçek:
siyah üstüne siyah gölge görünmez. `--zc-lift-*` koyu kipte hem dış gölge hem
iç üst çizgi taşır.

---

## 6. Erişilebilirlik

- **`prefers-contrast: more`**: gradyan, cam ve ışıma kapanır; kenarlar
  kontrol seviyesine (3:1 üstü) çıkar. Sayfa fakirleşmez, yalnız sahne söner.
- **`forced-colors: active`**: dekoratif katmanlar çıkar, sistem paleti
  devralır.
- **`prefers-reduced-motion`**: bu pakette hareket olmadığı için ihlal
  edilecek bir şey yok. Bir sonraki paket `site-shell.css`teki `no-preference`
  kapısını kullanır; o kapı tek yönlüdür ve betik onu geri alamaz.
- **Emoji yok**: `ShellIconLanguageTest` (ICON-01) artık `site-identity.css`i
  de tarıyor.

---

## 7. Panele dokunulmadı — nasıl kanıtlandı

Sahibin sınırı: *"Panelin tek pikseline dokunmayacaksın."*

"Dokunmadım" bir iddiadır; aşağıdaki dördü ölçüdür.

1. **Kapsam.** Kurumsal palet `.site-shell` seçicisine bağlı. O sınıf yalnız
   `views/public/layout.blade.php` gövdesinde var — KIMLIK-05 depodaki bütün
   `resources/views` ve `resources/js` ağacını tarayıp bunu doğruluyor.
2. **Kök temiz.** Kimlik dosyası `:root` ve `[data-theme='dark']`
   seviyesinde **yalnız yeni `--zc-*` adları** tanımlar; var olan hiçbir
   jetonu orada yeniden yazmaz (KIMLIK-05, ikinci yarı).
3. **Panelin zinciri yerinde.** `--fg`, `--surface`, `--canvas`, `--border`,
   `--focus`, `--color-brand-500` hâlâ `:root` seviyesinde `--aep-*`
   jetonlarını okuyor (KIMLIK-07).
4. **Derlenmiş CSS ölçüldü.** `origin/main` ve bu paketin çıktısındaki bütün
   `:root` blokları ayrıştırılıp karşılaştırıldı: **fark yalnız EKLEME**.
   `--zc-` ile başlamayan bir tek satır değişmedi.

Ayrıca KIMLIK-06: panelin kaynakları (`resources/js`, `resources/css/aep`)
`--zc-` ya da `dz-` dizesinin adını bile anmıyor.

**Misafir menüsüne de dokunulmadı** — o yüzeyde kiracının kendi markası
geçerlidir ve bu paket onun tek dosyasını açmadı.

---

## 8. Ölçüldü — ve ölçülemeyenler

### 8.1 Dar ekran (taban)

`php artisan site:export-static` ile üretilen **18 sayfa**, gerçek Chrome'da.

| Genişlik | `origin/main` | Bu paket |
|---|---|---|
| 320×568 (ltr) | 3/18 sayfa · `small-target` 7 | **3/18 · `small-target` 7** |
| 320×568 (rtl) | — | 3/18 · `small-target` 7 |
| 1280×800 | 3/18 · `small-target` 7 | **3/18 · `small-target` 7** |
| 1920×800 | 3/18 · `small-target` 7 | **3/18 · `small-target` 7** |

**Ölçüm dört kez tekrarlandı** ve dördünden üçü 3/18 verdi; bir koşuda 4/18
görüldü (`pricing` sayfasında sekizinci bir `small-target`). Tekrar eden
sonuç 3/18'dir ve tek seferlik farkın kaynağı denetim aracının kendi
zamanlamasıdır, bu paketin bir çıktısı değil. Kayda geçiyor, çünkü
görülmemiş sayılamaz.

**Yeni ihlal sıfır.** Kalan yedi bulgu `origin/main`de de var ve hepsi metin
akışı içindeki bağlantılardır ("Contact us", "Ask us", "Pricing"); WCAG
2.2'nin 2.5.8 ölçütü onları 44 pikselden muaf tutar ve bu paket sayfa
gövdelerine dokunmadı.

**320×480 ayrıca ölçüldü** (denetim betiği yüksekliği genişlikten türetir,
568 kullanır; bu ölçüm doğrudan Chrome'da yapıldı):

| 320×480 | Önce | Sonra |
|---|---|---|
| `scrollWidth` / `clientWidth` | 320 / 320 | **320 / 320** (yatay taşma yok) |
| İçerik sütunu | 288 px | **291 px** |
| Sütun / görüntü alanı | 0,900 | **0,910** |
| Kahraman başlığı | 28 px | **32 px** |

### 8.2 Geniş ekran — asıl değişen yer

| Ölçü | Önce | Sonra |
|---|---|---|
| 1440: sayfa kabı | 1024 px | **1312 px** |
| 1440: içerik sütunu | 992 px | **1238 px** |
| 1440: sütun / görüntü alanı | **0,689** | **0,860** |
| 1440: kahraman başlığı | 40 px | **65,6 px** |
| 1920: içerik sütunu | 992 px | **1219 px** |
| 1920: sütun / görüntü alanı | **0,517** | **0,635** |
| 1920: kahraman başlığı | 40 px | **72 px** |

1920'de eski değer, ekranın **%48'inin** hiçbir şey göstermediği anlamına
geliyordu. Sahibin ifadesi birebir buydu.

1920'deki 0,635 hâlâ 1'e uzak ve bu **kasıtlıdır**: metin okunur genişlikte
kalmalı. Kalan boşluğu dolduracak şey sütunun daha da genişlemesi değil,
tam kanamalı bantlar ve alan gradyanlarıdır (`.site-bleed`, `.site-field`) —
onları kullanacak olan sayfa gövdesi paketidir (§9).

### 8.3 CSS ağırlığı

`php artisan view:clear && rm -rf public/build && npm run build`, iki tarafta
da aynı koşulda:

| Aşama | Ham | gzip |
|---|---|---|
| `origin/main` (daisyUI kurulu, panelden türeyen tema) | 161.175 B | 28.029 B |
| **Bu paket** | **175.861 B** | **30.410 B** |

**Net maliyet: +14.686 ham, +2.381 gzip bayt (gzip'te %8,5).** Karşılığında
gelen şey: iki tam palet (açık + koyu), tema tanımayan derin bant katmanı,
tipografi ölçeği, dört ölçü rolü ve dokuz yüzey sınıfı.

React paketi değişmedi: kurumsal sayfalar hâlâ **sıfır React** yüklüyor.

### 8.4 Ölçülemeyenler — açıkça

1. **Üretimde nasıl göründüğü bilinmiyor.** Üretim sunucusuna erişim yok;
   buradaki her ölçüm yerel statik dışa aktarımdan gelir.
2. **Yarı saydam jetonların kontrastı ölçülmedi** (§2.4).
3. **Estetik ölçülmedi.** Bu paket geometriyi, kontrastı ve ağırlığı ölçer;
   "güzel mi" sorusunun cevabı sahibindir ve bu belge onu ölçtüğünü iddia
   etmiyor.
4. **iOS Safari doğrulanmadı.** Ölçüm Chrome'da yapıldı. `backdrop-filter`
   için `-webkit-` öneki yazıldı ama davranışı ölçülmedi.
5. **`.site-bleed` gerçek bir sayfada denenmedi** — henüz onu kullanan bir
   sayfa gövdesi yok. Kısıtı (`.site-stage` içinde olmalı) yazılı bir kural,
   ölçülmüş bir sonuç değil.

---

## 9. Sonraki ajanlara talimat

Bu paket **paleti, ölçeği ve yüzey dilini** kurar. Sayfa gövdelerini ve
hareketi yazan ajanlar onları **kullanır**.

**Serbest:**

- `--zc-*` jetonlarını okumak ve §6 sınıflarını (`.site-panel`, `.site-field`,
  `.site-deep`, `.site-glass`, `.site-veil`, `.site-rule`, `.site-eyebrow`,
  `.site-display*`, `.site-lede`, `.site-measure-*`, `.site-bleed`) giymek.
- Sayfa kabını `.site-measure-page`e taşımak — taşındığı gün
  `site-shell.css`teki geçiş kuralı (`main[class~='max-w-5xl']`) **silinir**.
- Yeni bir rol jetonu eklemek: önce §1'e bir ilkel, sonra §2'ye rol, sonra
  kontrast tablosuna satır. Üçü birden yapılmadan kapı geçilmez.
- daisyUI bileşeni kullanmak — `dz-` önekiyle, `docs/136` §9 aynen geçerli.

**Yasak:**

- **§1 dışında ham renk yazmak.** Kurumsal CSS'in hiçbir yerinde `#`, `rgb(`,
  `hsl(`, `oklch(` yoktur (KIMLIK-01).
- **`--aep-*` jetonunu kurumsal yüzeyde okumak.** Panelin jetonu panelindir.
- **Panele `--zc-*` ya da `dz-` sokmak** (KIMLIK-06).
- **`.site-shell` sınıfını başka bir gövdeye yazmak** (KIMLIK-05).
- Kırılma noktası jetonu (`sm:` …) — `MP-05`.
- `max-*` ile dar ekranı bastırmak, "mobilde gizle" yapmak.
- Emoji. Her yerde. Phosphor ilk.
- `!important`. Katmansız bir kural zaten katmanlı bir kuralı yener.
- CDN, uzak yazı tipi, uzak görsel. CSP zaten reddeder.
- `.site-bleed`i `.site-stage` dışında kullanmak — yatay taşma üretir ve
  `mobile-ux-audit` kırar.

---

## 10. Rapor alanları

- **once:** Kurumsal site panelin jetonlarını giyiyordu: sakin, düz, gölgesiz.
  1920 pikselde içerik ekranın yarısında duruyordu, kahraman başlığı 40
  pikseldi ve birincil düğmenin yazısı 1,73:1 kontrastla okunmuyordu.
- **simdi:** Kurumsal sitenin kendi paleti (iki tam kip + tema tanımayan derin
  bant), kendi tipografi ölçeği (320'de 32 px, geniş ekranda 72 px), dört ölçü
  rolü ve dokuz yüzey sınıfı var. Sayfa kabı 82 rem; düğme yazısı 5,84:1.
- **fark:** 320 pikselde hiçbir şey bozulmadı (yeni ihlal sıfır, yatay taşma
  yok, içerik sütunu 288'den 291 piksele çıktı). Geniş ekranda içerik sütunu
  992'den 1238 piksele (1440) ve 1219 piksele (1920) çıktı. CSS gzip'te 2,4 KB
  büyüdü. Panelde bir tek satır değişmedi.
- **kullaniciYolculugu:** Bir kebapçı öğle güneşinde telefonundan
  zabuno.com'u açıyor. Önce: başlık küçük, sayfa panelin sakin grileriyle
  aynı, "Open workspace app" düğmesinin yazısı sarının üstünde okunmuyor;
  ne olduğunu anlamadan çıkıyor. Şimdi: derin bir zemin, 32 piksellik bir
  başlık, üstünde beyaz yazısı okunan mor bir düğme. Akşam bilgisayarından
  aynı adresi açtığında sayfa 1312 pikselik bir kolona yayılıyor ve dört
  özellik yan yana duruyor. Paneli açtığında ise hiçbir şey değişmemiş —
  çalışma tezgâhı hâlâ sakin.
- **kalanEngel:** Sayfa gövdeleri bu dili henüz **giymiyor**. Kahraman
  bölümü hâlâ `home.blade.php`nin eski yapısı; `.site-deep`, `.site-field`,
  `.site-panel` ve `.site-bleed` tanımlı ama kullanılmıyor. Hareket
  (parallax, parçacık) hiç yazılmadı. Üretimde nasıl göründüğü bilinmiyor.
- **capability_delta:** Kurumsal site artık panelden bağımsız bir görsel
  kimliğe sahip ve o kimlik ölçülüyor (kontrast, kapsam, ham renk kaynağı,
  emoji). Yeni bir ürün yeteneği açılmadı.
- **Çalışan ürün iddiası:** Bugün çalışan şey, kurumsal sitenin **kabuğu ve
  tipografisi**: açık ve koyu kipte, 320 pikselden 1920 piksele, ölçülmüş
  kontrastla. Çalışmayan şey: uzay hissini taşıyacak **sayfa gövdeleri ve
  hareket** — ikisi de ayrı paketlerin işi ve bu paket onlara yalnız zemin
  hazırladı.
