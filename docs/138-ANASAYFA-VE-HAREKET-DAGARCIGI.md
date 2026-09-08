<!--
    KARAR BELGESİ — FF-233, 2026-09-08.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan gelir; ölçülemeyenler §9'da AÇIKÇA "bilinmiyor"
    diye ayrılmıştır.
-->

# Ana sayfa ve hareket dağarcığı — 320×480 taban, sıfır kütüphane, iddia envanterden

## 1. Sahibin isteği ve bu belgenin kapsamı

2026-09-08, üç cümle:

> *"UX estetiği önemli. Yüksek kaliteli animasyonlar, 3D gibi görünen 2D
> efektler, parallax ve sağlı sollu hareket eden landing pages, particles vb.
> Bir uzay teknolojileri şirketi gibi, abartı dursun, görünsün, hissettirsin."*

> *"Frontpages gerçek mobile first olacak. 320px first, adaptive first,
> **iPhone 4 first**. Desktop uyumu sonradan. Coding ilk önce mobile için, UI
> ilk önce mobile için. Sadece media query değil, gerçek mobile first."*

> *"daisyUI kullan, baştan yarat. Bu kararım kesin."*

**Bu belge neyi bağlar:** kurumsal sayfa GÖVDELERİNİN hareket dağarcığını
(`resources/css/site-motion.css` + `resources/js/site-motion.ts`), ana
sayfanın bölümlerini ve içeriğinin nereden geldiğini, kurumsal sitenin
ağırlık bütçesini.

**Neyi BAĞLAMAZ:** kabuğu. Üst çubuk, altbilgi ve tema `docs/136`ün işidir ve
bu pakette onlara **tek satır** girmedi. Kabuk sakin kalır: her sayfada
aynıdır, her gün görülür ve üçüncü ziyarette kıpırdayan bir üst çubuk
gürültüdür.

---

## 2. Teknoloji kararı — GSAP DEĞİL, ve gerekçesi bir rakam

`docs/118` E5 *"GSAP birinci sıradadır"* diyor. O sıralama **React'e karşı**
yazılmıştı ve gerekçesi şuydu: *"sıfır-React bir yüzeye ikinci bir bileşen
sistemi sokmadan"*. Gerekçe doğru; sonucu bugün başka bir yere çıkıyor.

**Ölçüldü (gsap 3.15.0, temiz bir dizine `npm install gsap`, `gzip -9`):**

| Dosya | Ham | gzip |
|---|---|---|
| `gsap.min.js` | 72.927 B | **28.302 B** |
| `ScrollTrigger.min.js` | 44.575 B | **17.961 B** |
| **Toplam** | 117.502 B | **46.263 B** |

Karşılaştırma: kurumsal sitenin BÜTÜN CSS'i o gün 27.702 bayt gzip ve
JavaScript'i **sıfır**. Yani GSAP, sayfanın tamamından **1,67 kat** ağır bir
betik demekti — hedef kitle mutfaktan, zayıf şebekeyle bakan bir restoran
sahibiyken.

Karşılığında verdiği tek şey — kaydırmaya bağlı hareket — tarayıcıda **zaten
var**: `animation-timeline: view()` / `scroll()`. Üstelik daha iyisini
veriyor, çünkü CSS zaman çizelgesi tarayıcının **bileşim iş parçacığında**
koşar; bir `ScrollTrigger` ana iş parçacığında koşar ve zayıf bir telefonda
dokunmanın kendisini geciktirir.

**Verilen karar:**

> Hareketin tamamı CSS'tedir. JavaScript yalnız iki iş yapar: kök öğeye
> `data-motion="on"` yazmak, ve `animation-timeline` desteklemeyen
> tarayıcıda giriş animasyonunu bir IntersectionObserver ile tetiklemek.

**Ölçüldü:** `resources/js/site-motion.ts` derlenmiş hâli **669 bayt ham,
380 bayt gzip**. GSAP'ın **%0,8'i**.

Hiçbir `scroll` dinleyicisi, hiçbir `requestAnimationFrame` döngüsü, hiçbir
tuval (canvas), hiçbir WebGL, hiçbir CDN yok. CSP zaten reddederdi
(`default-src 'self'`, `docs/136` §2) ama karar bütçeyle alındı, politikayla
değil.

**React de gelmedi.** Kurumsal sayfalar hâlâ sıfır React yüklüyor; kapı
`HOME-NO-REACT-05` yerinde.

---

## 3. Hareket dağarcığı — her efektin bir İŞİ var

`resources/css/site-motion.css`. Dağarcık ana sayfaya özel değil: sonraki
kurumsal sayfalar aynı sınıfları giyecek.

| Sınıf | İŞİ (süs değil) | Mekanizma |
|---|---|---|
| `.site-drift[data-shift]` | Katmanlar farklı hızda akar → sahne bir düzlem değil bir YER gibi okunur | `animation-timeline: view()`, dikey `translate` |
| `.site-sweep[data-away]` | Yatay akış, iki katman zıt yöne → düzlemler birbirinden ayrılır ("sağlı sollu") | `view()`, yatay `translate` |
| `.site-field` | Parçacık alanı → sahnenin görüntü alanında BİTMEDİĞİNİ söyler | İki `radial-gradient` düzlemi, sonsuz `translate` |
| `.site-horizon` | Perspektif ızgara → 3D görünen 2D derinlik | `perspective` + `rotateX` + maske |
| `.site-halo` | Işıma → gözü BAŞLIĞA çeker | Tek `radial-gradient` |
| `.site-orb` | Halka → karekodun "sabit adres" fikri: merkez durur, çevre döner | İki `::` halkası, `scale`/`opacity` nefesi |
| `.site-reveal` | İçerik sırayla girer → 480 pikselde her şey aynı anda düşmez | `view()`, `animation-range: entry` |
| `.site-thread` | Zincirde NEREDE olduğunu söyler | Adlandırılmış `scroll-timeline` |
| `.site-rail` | Yatay şerit → dokunmada kaydırma UCUZDUR | `scroll-snap`, kendi kaydırma kabı |

**Parçacık başına DOM düğümü yok.** Yüz parçacık yüz düğüm demekti; burada
iki düğüm var ve tarayıcı ikisini de bir kez boyayıp bileşim iş parçacığında
taşıyor.

**Her kare `translate`/`scale`/`rotate`/`opacity`.** Düzeni yeniden
hesaplatan bir `top` ya da `height` animasyonu yok.

### 3.1 Katman sırası — dekor hiçbir şeyi örtmez

Sahne katmanları `--layer-scene-back/mid/front` (0/1/2) kullanır; içerik 10,
menü 20, çerez şeridi 30, atlama bağlantısı 50. Kapı `HOME-SCENE-03` bu
dosyadaki her `z-index` bildirimini okur ve `--layer-scene-*` dışında bir
değer bulursa kırılır.

Her katman ayrıca `aria-hidden="true"` (`HOME-SCENE-04`) ve
`pointer-events: none` (kabuktan gelir).

### 3.2 `prefers-reduced-motion` — mutlak, ve ÖLÇÜLÜYOR

Kural `no-preference` üzerinden yazıldı, `reduce` üzerinden değil. Kapı
`HOME-SCENE-02` kaba değil kesin: dosyadaki `@media (prefers-reduced-motion:
no-preference)` bloğunun sınırları süslü parantez sayılarak bulunur ve o
bloğun **dışında** kalan her `animation`/`transition` bildirimi testi kırar.
İkinci bir kapı da var: bloğun içindeki her kuralın `data-motion='on'`
kancasını taşıdığı ölçülüyor.

Sonuç: `reduce` diyen bir ziyaretçide betik özniteliği yazsa **bile** hiçbir
sahne kuralı doğmaz. Sayfa tam işlevlidir — her metin okunur, hiçbir şey
saydam değildir, hiçbir şey yerinden oynamaz.

### 3.3 Betiksiz de eksiksiz

`animation-timeline` desteklemeyen tarayıcıda (bugün Firefox) parallax ve
sahne hareketi hiç doğmaz; katmanlar yerinde durur. Giriş animasyonu için
küçük bir IntersectionObserver geri düşüşü var ve o da yoksa öznitelik geri
alınır — "yarım animasyon" diye bir durum yok.

Betik hiç inmezse sayfa **eksiksizdir**. Kapı `HOME-SCENE-05` gövdeyi betik
etiketleri atılmış hâlde tarar ve her başlığı, her hedefi ve kapalı bir
soru-cevap bölmesinin cevabını arar.

---

## 4. Mobil birinciliği — YAZIM DÜZEYİNDE, ölçüm düzeyinde değil yalnız

Sahibin cümlesi: *"Sadece media query değil, gerçek mobile first."*
Ölçülebilir karşılığı üç maddeye indirildi ve üçü de testle sabit.

### 4.1 Tek bir genişlik medya sorgusu YOK

`site-motion.css` içinde ne `min-width` var ne `max-width`. Düzen `clamp()`,
`min()` ve `repeat(auto-fit, minmax(min(100%, 15rem), 1fr))` ile yazıldı:
320'de doğru olan kural 1440'ta da **aynı** kuraldır. Kapı `HOME-SCENE-01`.

### 4.2 Tek bir `max-*` bastırması, tek bir "mobilde gizle" YOK

`home.blade.php` içindeki her sınıf listesi taranıyor: `max-sm:`…`max-2xl:`
ve çıplak `hidden` testi kırar. Var olan `HOME-FLUID-04` zaten `sm:`…`2xl:`
jetonlarını yasaklıyordu; bu ikisi birlikte, "önce masaüstü sonra mobil
uyumu" yolunu kapatıyor.

`max-inline-size` bir bastırma DEĞİLDİR ve kullanıldı (`.site-hero-actions >
.site-action`, `.site-scene-lead`): tek bir kural, her genişlikte aynı
davranıyor, hiçbir yerde geri alınmıyor.

### 4.3 Yükseklik de taban — 320×**480**

`100vh`/`100svh`/`dvh` yüksekliğinde bir sahne **yok** ve olamaz
(`HOME-SCENE-01`, düzenli ifadeyle ölçülüyor). Sahnenin boyu içeriğinden
gelir.

**Ölçüldü (gerçek Chrome, 320×480):** sahne bölümünün yüksekliği **348
piksel**. İlk ekranda görünenler:

1. marka + menü düğmesi (kabuk, 56 px),
2. `<h1>` — iki satır,
3. vaat cümlesi — üç satır,
4. **iki eylem düğmesi de** (44'er piksel),
5. "plan gerekmiyor" notu,
6. ve bir sonraki bölümün başlığının ilk satırı.

Yani 480 piksellik bir ekranda ziyaretçi ürünün ne olduğunu, ne yapacağını ve
sayfanın devam ettiğini kaydırmadan görüyor.

**Bunun için başlık kısaltıldı.** Eski başlık ("Run your restaurant's menu
and workspace from one place") 320'de 28 punto ile **dört satırdı**;
"workspace" da bir kebapçının bilmediği bir kelimeydi. Yeni başlık iki satır.

### 4.4 Dokunma ≠ imleç (`docs/118` E2)

- Dağarcıkta **`:hover` ile anlatılan hiçbir bilgi yok**. Dokunmada hover
  yoktur; hover yalnız imleçli bir cihazda bir vurgu katabilir.
- Şeridin kaydırılabilir olduğu **hover ile değil geometriyle** söyleniyor:
  bir sonraki kartın kenarı görünür kalıyor (`min(78%, 20rem)`) ve altındaki
  ilerleme çizgisi kaç adım kaldığını gösteriyor. İkisi de her giriş kipinde
  aynı çalışır.
- Yerleşik kaydırma çubuğu **kapatıldı** ve gerekçesi ölçülmüş: açıkken
  şeridin altında iki farklı uzunlukta iki gösterge duruyordu. Seçim taban
  cihaza göre yapıldı — dokunmada yerleşik çubuk hiç çizilmez, yani onu
  tutmak göstergeyi yalnız imleçli cihaza vermek olurdu. **Bedeli açıkça
  yazıldı:** imleç kullanan biri başparmağı sürükleyemez; tekerlek + üst
  karakter, dokunmatik yüzey ve ok tuşları (kap odaklanabilir) duruyor.

### 4.5 Yoğunluk: büyük hedef + SIKI boşluk (`docs/118` E3)

Kart dolgusu `--space-4` (16), ızgara boşluğu `--space-3` (12), soru satırı
dolgusu `--space-2`/`--space-3`. Dokunma hedefi hiçbir yerde
`--control-height` (44) altına inmiyor. Dolgu **bir kez** uygulanıyor: sahne
kendi `padding-inline`ini sıfırlar ve iç kolonu `.site-shell-inner`e
bırakır — iç içe iki dolgu 320 pikselde içeriği 264 piksele düşürürdü.

---

## 5. Ana sayfa — iddia UYDURULMAZ, envantere bağlanır

### 5.1 Bölümler ve her birinin anlattığı gerçek yetenek

| # | Bölüm | Anlattığı | Kaynağı |
|---|---|---|---|
| 1 | Sahne | Ürünün tek cümlelik vaadi + iki eylem | `ProductOverviewPage` DirectAnswer |
| 2 | `#how-it-works` | Hesaptan masadaki koda **altı adım** | `ProductOverviewPage` HowItWorks (altı denetleyici) |
| 3 | `#features` | Ürünün **on iki parçası** | `ProductOverviewPage` Capabilities |
| 4 | `#limits` | Ürünün **yedi sınırı** — ne DEĞİL | `ProductOverviewPage` Limitations |
| 5 | `#pricing` | Plan kataloğu | `ListPlanCatalog` (veri, kod değil) |
| 6 | `#faq` | Beş soru | Katalog + genel bakış sayfasının SSS'i |
| 7 | `#contact` | İletişim formu | Yaşayan `/contact` |

`#features` çıpası **korundu**: kabuğun gezintisi ona işaret ediyor
(`SiteNavigation`) ve adı değiştirmek çalışan bir bağlantıyı kırardı.

### 5.2 Kapı: iddia ile envanter ayrışamaz (`HOME-REAL-07`)

`App\Support\Site\HomeStory` yalnız **sırayı** ve katalog anahtarlarını
bildirir. Test, üç listenin başlıklarını `ProductOverviewPage`in kendi
terimleriyle **sıra sıra** karşılaştırır:

- 12 parça başlığı ≡ Capabilities terimleri,
- 7 sınır başlığı ≡ Limitations terimleri,
- 6 adım başlığı ≡ HowItWorks terimleri.

Ayrıca her iddianın dayandığı `source` dosyasının **var olduğu** ölçülüyor.

Bunun anlamı şu: bir yetenek üründen düşerse ya da adı değişirse, ana sayfa
eski iddiayı sessizce taşımaya devam **edemez**. Bir pazarlama sayfasında bir
satır silmeyi kimse hatırlamaz; kırmızı bir test hatırlatır.

### 5.3 REDDEDİLEN UYDURMALAR

Sayfada yok ve bilerek yok: **müşteri logosu, referans (testimonial), "1000+
restoran", ödül, canlı sayaç, uydurma grafik, "yakında" vaadi, "binlerce
kişiye katıl", para iade garantisi.** Kapı `HOME-HONEST-08` bunların
sözcüklerini ve `\d+\s*\+\s*(restaurant|customer|…)` desenini arıyor.

Ürünün bugün kaç müşterisi olduğu bu depoda **ölçülemez**; ölçülemeyen bir
sayıyı sayfaya yazmak, ilk soruda çöken bir iddiadır.

**Yatırımcı da bu yüzden ikna olur.** Sayfanın en uzun iki bölümü ürünün on
iki parçası ve **yedi sınırı**. "Ne değil" bölümü bir zayıflık ilanı değil,
ölçülmüş bir envanterin varlığının kanıtı: bunu yazabilmek için ürünün
sınırlarını bilmek gerekir.

### 5.4 GÖRSEL YOK — ve bu bir eksiklik değil, bir karar

Depoya tek bir fotoğraf konmadı. Üç gerekçe, üçü de ölçülmüş:

1. **`ICON-04` kurumsal şablonun kendi SVG'sini çizmesini yasaklıyor**
   (`ShellIconLanguageTest`): kurumsal görünümlerde `<svg>` yalnız
   `<x-phosphor>` bileşeninde yaşar. Sahne bu yüzden **tamamen CSS'te**
   çizildi — gradyan, maske, `perspective`. Ek istek yok, ek bayt neredeyse
   yok.
2. **320 pikselde en ağır şey fotoğraftır.** Tek bir "hero" fotoğrafı,
   sıkıştırılmış hâliyle bile bu paketin CSS+JS toplamından ağır olurdu;
   mutfaktaki adamın beklediği saniye oradan gelir.
3. **Başkasının restoranının fotoğrafı ödünç bir kanıttır.** Bir stok
   fotoğraf, sahip olmadığımız bir müşteriyi ima eder — §5.3'te reddedilen
   şeyin görsel biçimi.

Bir gün gerçek bir müşteri fotoğrafı gelirse kural `docs/118` E7'dir:
Unsplash değil; kaynak, adres, lisans ve indirme tarihi kaydedilir.

---

## 6. Performans bütçesi — kapıya BAĞLI

`resources/js/design-system/site-weight-budget.json` +
`site-weight-budget.test.ts` (`SITE-WEIGHT-BUDGET-01`, `npx vitest run
resources/js` ile koşar).

**Sorusu ayrı:** `bundle-budget.json` panelin sorusunu sorar ("en ağır
uygulama yüzeyi kaç KB JS indiriyor?"). Bu bütçe kurumsal sitenin sorusunu
sorar: **ürünü ilk kez gören biri, tek satır okumadan önce kaç bayt
bekliyor?**

### 6.1 Ölçüm — temiz görünüm önbelleğiyle

`php artisan view:clear && rm -rf public/build && npm run build`, sonra
`gzip -9`.

| Aşama | CSS ham | CSS gzip | JS ham | JS gzip |
|---|---|---|---|---|
| Bu paketten ÖNCE (`ff-232`) | 161.186 B | 27.702 B | **0** | **0** |
| Bu paketin teslimi | **176.503 B** | **30.049 B** | **669 B** | **380 B** |
| **Fark** | +15.317 B | **+2.347 B** | +669 B | **+380 B** |

**Ziyaretçinin toplam beklediği: 30.429 bayt gzip (29,7 KB).** Bütçe **32 KB**;
pay yaklaşık **%7**.

Karşılaştırma için: GSAP tek başına 46.263 bayt gzip — yani **bütçenin
tamamından büyük**. Bir animasyon kütüphanesi eklemek bu kapıyı derhal kırar
ve bu, bütçenin var olma sebebidir.

**Görünüm önbelleği uyarısı:** `app.css` derlenmiş Blade önbelleğini de bir
tarama kaynağı sayıyor (`@source '…/storage/framework/views/*.php'`). Test
suiti koştuktan sonra aynı kaynak kod ~6 KB daha büyük bir CSS üretir; kirli
bir önbellekle alınan sayı kodun değil önbelleğin ölçüsüdür. CI'da bu risk
yok: `npm run build` test adımlarından **önce** koşuyor.

### 6.2 Yazı tipi ayrı sayılır

`roboto-latin-wght-normal` woff2 **43.136 bayt** — kabuğun `preload`
ettiği tek yazı tipi dosyası (`partials/font-preload.blade.php`). Bütçeye
girmiyor çünkü ayrı bir kararın konusu (FF-195), zaten sıkıştırılmış ve
gzip'lenemez; ama ziyaretçinin beklediği baytın parçası olduğu için burada
yazılı. Toplam ilk yük: **≈73,6 KB**.

---

## 7. daisyUI kullanımı

Kullanılan sınıflar: `dz-btn`, `dz-btn-primary`. Kart, ızgara ve soru-cevap
için daisyUI bileşeni **kullanılmadı** ve gerekçesi ölçüldü: `dz-card`,
`dz-collapse` ve `dz-badge` kendi ölçülerini, ok geometrilerini ve yükseklik
geçişlerini getiriyor; üçü de burada gereksiz CSS. Aynı görünüm beş kuralla
veriliyor.

### 7.1 ÖLÇÜLMÜŞ TUZAK — katmansız kural, katmanlı kuralı yener

`docs/136` bunu bir **kazanç** olarak yazmıştı (`site-shell.css` katmansız
olduğu için daisyUI'yi `!important` olmadan eziyor). Bu pakette aynı olgu bir
**kusur** üretti:

`app.css` katmansız bir `a { color: inherit }` taşıyor (panelde gezinti
bağlantıları mavi ve altı çizili olmasın diye). daisyUI'nin
`.btn-primary { color: var(--color-primary-content) }` kuralı ise
`@layer components` içinde. Sonuç ölçüldü (320×480): `<a class="dz-btn
dz-btn-primary">` **marka sarısının üstünde %87 beyaz** metinle çiziliyordu —
yaklaşık **1,9:1** kontrast.

Aynı arıza ailesi depoda daha önce de görülmüştü (`SkipLink`, FF-125) ve aynı
cevabı aldı: marka sarısının tek doğru mürekkebi jetonda yazılıdır. Düzeltme
tek kural: `a.dz-btn-primary { color: var(--aep-on-accent-primary) }`.

**Kayda geçirilen genel ders:** `<a class="dz-btn …">` bu depodaki ilk
"bağlantı biçimindeki daisyUI düğmesi"ydi. Sonraki her sayfa aynı tuzağa
girecek.

---

## 8. Koyu adacık — tek bir renk elle seçilmeden

Sahne koyu bir zemin ister (parçacık, ışıma ve perspektif ızgara açık zeminde
ya görünmez ya kirli görünür). Bunun için **yeni bir renk seçilmedi**: sahne
`data-theme="dark"` taşıyor — kök öğeye yazılan aynı öznitelik.
`aep/tokens/colors.css` içindeki `[data-theme="dark"]` seçicisi ELEMENT
düzeyindedir, `:root`a bağlı değil; sahne temanın kendi koyu değerlerini
yerel olarak yeniden çözüyor ve içindeki metin, kenar ve daisyUI düğmesi
kendiliğinden dönüyor.

**Bu da ölçülmüş bir kusurun düzeltmesi.** İlk yazımda mürekkep
`var(--aep-text-inverse)` idi; o jeton koyu temada `--aep-ink-950`'e döner,
yani koyu zemin üstünde koyu metin — ekran görüntüsünde başlık neredeyse
görünmüyordu. "Ters mürekkep" bir **yön** bilgisidir, sabit bir renk değil.

Sahnenin bütün alfa değerleri `color-mix(in srgb, var(--…) N%, transparent)`
ile jetonlardan türetildi: dosyada tek bir `#hex` ve tek bir çıplak `rgb()`
yok.

---

## 9. Bu paketin ölçemedikleri — açıkça

1. **iOS Safari doğrulanmadı.** Ölçüm Chrome'da. `animation-timeline` Safari
   26'da var, ama gerçek cihazda koşturulmadı. Geri düşüş yolu tanımlı
   (hareket doğmaz, sayfa eksiksiz kalır), yani riski "bozuk sayfa" değil
   "hareketsiz sayfa".
2. **Gerçek cihazda kare hızı ölçülmedi.** Efektlerin tamamı bileşim iş
   parçacığı özellikleri kullanıyor ve ana iş parçacığında hiçbir kaydırma
   dinleyicisi yok — ama bu bir GEREKÇEDİR, bir ölçüm değil. Zayıf bir
   Android telefonda kare sayacı okunmadı.
3. **Şebeke süresi ölçülmedi.** 29.412 bayt gzip ölçülmüş bir olgudur;
   "0,65 saniye" ise bir varsayımdan (≈50 KB/s) türetilmiş bir tahmindir ve
   öyle okunmalıdır.
4. **Estetik ölçülmedi.** `scripts/mobile-ux-audit` taşma, kırpılma, dokunma
   hedefi ve yoğunluk ölçer. "Uzay teknolojileri şirketi gibi duruyor mu"
   sorusunun cevabı sahibindir.
5. **Firefox'ta görünüm doğrulanmadı.** `animation-timeline` orada yok;
   IntersectionObserver geri düşüşü kodda var ama gerçek Firefox'ta
   koşturulmadı.
6. **Türkçe metin yok ve olmayacak.** `shipped_locales` yalnız `['en']`;
   yeni dizeler PO kataloglarına boş `msgstr` ile girdi ve çeviri sahibin
   kararıdır (`docs/118` E4).

---

## 10. Ölçüm sonuçları — `scripts/mobile-ux-audit`

Araca **`--height`** bayrağı eklendi. Sıra değişmedi: 320×568 hâlâ türetilen
taban ve CI kapısı hâlâ onu koşuyor. Bayrak, sahibin tabanının
(*"iPhone 4 first"* — 320×**480**) ölçülebilmesi için gerekliydi; 568 ile 480
arasındaki 88 piksel, "ilk ekranda ne görünüyor" sorusunu değiştiriyor.

14 kurumsal sayfa, gerçek Chrome, `php artisan site:export-static` çıktısı:

| Genişlik × Yükseklik | ÖNCE (`ff-232`) | SONRA (bu paket) |
|---|---|---|
| **320×480** (iPhone 4) | 3/14 sayfa · `small-target` **7** | 3/14 · `small-target` **5** |
| 320×568 (iPhone SE) | 3/14 · `small-target` 7 | 3/14 · `small-target` 5 |
| 1280×800 (dizüstü) | 3/14 · `small-target` 7 | 3/14 · `small-target` 5 |

**Ana sayfanın kendi bulguları 5 → 2'ye düştü.** Kalan ikisi
(`Contact us`, `Ask us`) **paylaşılan fiyat parçasından** geliyor
(`public/partials/pricing.blade.php`) ve aynısı `/pricing` sayfasında da
duruyor — yani bu paketin ürettiği bir şey değil, paylaşılan bir parçanın
borcu. İkisi de metin AKIŞI içindeki bağlantı ve WCAG 2.2'nin 2.5.8 ölçütü
onları 44 pikselden muaf tutuyor.

Ana sayfanın **yeni** bağlantıları (`Pricing`, `Write to us`) `.site-action`
ile 44 piksele çıkarıldı ve ölçümden düştü.

Hiçbir genişlik/yükseklikte `overflow-x`, `clipped`, `text-clip`,
`tight-gap` ya da `wasted-width` bulgusu yok. Yatay şerit kendi kaydırma
kabında olduğu için belgeyi kaydırmıyor (ölçüldü: 320 pikselde
`documentElement.scrollWidth === 320`, şeridin kendi `scrollWidth`i 1469).

### 10.1 Azaltılmış hareket — ÖLÇÜLDÜ, iddia edilmedi

Aynı sayfa, aynı Chrome, `Emulation.setEmulatedMedia` ile iki kez açıldı
(320×480):

| | `no-preference` | `reduce` |
|---|---|---|
| `<html data-motion>` | `on` | **yok** |
| Koşan animasyon (`document.getAnimations().length`) | 41 | **0** |
| `.site-reveal` öğelerinin en düşük `opacity`si | 0 | **1** |
| `<h1>`/`<h2>` sayısı | 9 | 9 |
| Gövde metni uzunluğu | 4.967 karakter | **4.967 karakter** |

Yani azaltılmış hareket isteyen ziyaretçi **aynı sayfayı harfi harfine**
okuyor; eksilen tek şey hareket.

---

## 11. Rapor alanları

- **once:** Ana sayfa dört genel "feature" başlığı, dört genel adım ve üç
  soru taşıyan düz bir metin listesiydi. Ürünün on iki parçası ve yedi sınırı
  yalnız yayınlanmamış bir içerik sayfasında yazılıydı — ziyaretçi onları
  hiç görmüyordu. Hareket yoktu; `docs/136` §7 bir sözleşme bırakmıştı ama
  onu kullanan bir dosya yoktu. Kurumsal sitenin ağırlığı hiç ölçülmüyordu.
- **simdi:** Sayfa ürünün kendi envanterinden besleniyor ve bir test iki
  listenin ayrışmasını imkânsız kılıyor. Sahne katmanı CSS'te yazıldı: 380
  bayt gzip betikle parallax, derinlik, parçacık, ışıma, yatay şerit,
  kaydırmaya bağlı giriş ve ilerleme çizgisi. `--height` bayrağıyla 320×480
  ölçülebilir hâle geldi ve ölçüldü. Ağırlık bir kapıya bağlandı.
- **fark:** Ziyaretçinin indirdiği: 27.702 → 29.412 bayt gzip (**+%6,2**),
  içinde ilk kez 380 bayt JavaScript var. Dokunma hedefi bulgusu ana sayfada
  5 → 2. İlk ekranda (320×480) görünen içerik: başlık + vaat + iki eylem +
  bir sonraki başlık.
- **kullaniciYolculugu:** Bir kebapçı, mutfağın arkasından, zayıf şebekeyle
  ve dört yıllık bir telefonla `zabuno.com`u açıyor. 29 KB iniyor. İlk
  ekranda ürünün ne yaptığını bir cümlede okuyor ve "Create an account"
  düğmesini görüyor — kaydırmasına gerek yok. Kaydırdıkça zincir yana kayıyor
  ve altındaki çizgi altı adımın kaçında olduğunu söylüyor. Sonra ürünün on
  iki parçasını, ardından **yedi sınırını** okuyor: "kasa değil, ödeme almaz,
  rezervasyon yok". Servis sırasında öğreneceği şeyi tanıtım sayfasında
  öğreniyor. Telefonunda "hareketi azalt" açıksa hiçbir şey kıpırdamıyor ve
  sayfa harfi harfine aynı bilgiyi taşıyor.
- **kalanEngel:** iOS Safari ve Firefox gerçek cihazda/tarayıcıda
  doğrulanmadı. Gerçek bir telefonda kare hızı ölçülmedi. Fiyat parçasındaki
  iki satır içi bağlantı hâlâ 18 piksel (paylaşılan parça, ayrı bir paketin
  işi). Sayfa hâlâ İngilizce — çeviri sahibin kararı.
- **capability_delta:** Kurumsal sayfa gövdeleri artık bir hareket
  dağarcığına sahip ve o dağarcık ölçülmüş bir bütçeye bağlı; ana sayfanın
  her iddiası ürünün kendi envanteriyle testle bağlı. Bir yetenek eklendiğinde
  ya da düştüğünde pazarlama sayfası artık sessizce eskiyemez.
- **Çalışabilen:** Ana sayfa 320×480'de ilk ekranda ürünü anlatıyor; sahne
  kaydırmayla yaşıyor; azaltılmış hareket isteyende hiç doğmuyor; betik hiç
  inmese sayfa eksiksiz; ağırlık kapısı yeşil; on iki parça ve yedi sınır
  ürünün kendi envanterinden geliyor.
- **Çalışamayan:** Diğer kurumsal sayfa gövdeleri (fiyat, yardım, iletişim,
  yasal) hâlâ eski düz düzenlerinde — dağarcık hazır ama giydirilmedi.
  Gerçek cihaz doğrulaması yapılmadı.
