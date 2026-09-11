# 151 — Masaüstünün kendi stili, ve üç ekran daha

**Paket:** `FF-259` · **Öncesi:** `docs/54` (adaptive cihaz yükleme), `docs/60`
(bağlam paneli), `docs/153` (masaüstünün kendi bileşenleri — bu programın 1.
paketi) · **Global kural:** `TOUCH-FIRST-INTERFACE`

Sahibin cümlesi (2026-09-08):

> *"ama artık desktop adaptive components, desktop adaptive styles, yap"*

`docs/153` masaüstüne kendi BİLEŞENLERİNİ verdi. Bu paket iki şeyi birden
yapar: o deseni yayar (üç ekran daha) ve eksik kalan yarısını kurar —
masaüstünün kendi STİL KATMANINI.

---

> **BU BELGE PLANDIR, TESLİM KAYDI DEĞİL — ve plan bölündü (2026-09-11).**
>
> Belge 2026-09-08'de PR #345 ile yazıldı. O PR **birleşmedi**: 56 dosya,
> `+5701/−351`, ve main dört gün içinde 61 commit ilerledi. Bugün ölçüldüğünde
> çakışan 25 dosya vardı ve aşağıdaki üç ekranın **ikisi main'e başka
> yollardan çoktan girmişti** — masaüstü medya ekranı (#358) ve masaüstü ekip
> listesi (#370). Yani bu belgenin anlattığı işin bir kısmı yapıldı, ama bu
> paketle değil.
>
> | Bu belgenin parçası | Bugün main'de | Not |
> | --- | --- | --- |
> | Masaüstü medya ekranı | **Var** (#358) | Ayrı, paralel uygulama |
> | Masaüstü ekip listesi | **Var** (#370) | Ayrı, paralel uygulama |
> | **Masaüstü puanlama ekranı** | **YOK** | Üç ekranın teslim edilmeyeni |
> | **Masaüstü stil katmanı** (`app-desktop.css`) | **YOK** | Paketin asıl konusu |
> | **Masaüstü i18n kataloğu** (`workspace-desktop/*`) | **YOK** | Altı dosya |
> | Bu belge | Var (bu paket) | — |
>
> **Neden belge önce geldi:** #345'in Media/Team yarısı artık main'deki
> uygulamayla 2.400 satır ayrışmış durumda; onu elle uzlaştırmak, çalışan bir
> işi yeniden tartışmaktır. Ama belgenin kendisi hiçbir şeyle çakışmıyor ve
> geri kalan üç paketin (stil katmanı, i18n kataloğu, puanlama ekranı)
> **gerekçesini taşıyan tek kayıt** o. Çürüyen bir PR'ın içinde kalırsa,
> ekranlar yazıldığında nedenleri kimse bilmeyecek.
>
> **`lang/po/workspace.*` katalogları elle birleştirilmeyecek.** Onlar
> üretilmiş dosyalar; doğru yol main'inkini almak ve kaynak değişiklikleri
> indikten sonra çıkarımı yeniden koşturmaktır.

---

## 1. Bugün ne vardı — ölçüm

`docs/153` §1 masaüstü kapanışının %97,9'unun mobille ortak olduğunu ölçmüş,
ilk ekranı (sipariş kuyruğu) ayırmıştı. Bu paket başlarken ölçülen:

| | dosya | kaynak bayt |
|---|---:|---:|
| masaüstü kapanışı | 267 | 2.333.581 |
| mobil kapanışı | 257 | 2.255.140 |
| **ortak** | **255** | **2.245.211** |

Yani masaüstü kapanışının hâlâ **%96,2'si** ortaktı. Cihaza özgü modül sayısı
**11 masaüstü + 1 mobil**di.

**Stil tarafında ayrım SIFIRDI.** Tek bir giriş vardı
(`resources/css/app.css`, derlenmiş 231.406 B / 38.219 gzip) ve her cihaza
aynı taban iniyordu. Masaüstünün kendine ait bir tek CSS kuralı yoktu:
yoğunluk, `hover` durumları, çok bölmeli düzen ve geniş ekran ölçü sınırı
diye bir katman hiç açılmamıştı.

Derlenmiş paket ölçüsü (paket öncesi):

| | ilk yükleme | tüm parçalar (lazy dâhil) |
|---|---:|---:|
| masaüstü | 665.938 B (194.718 gzip) | 1.615.129 B / 116 parça (436.029 gzip) |
| mobil | 642.153 B (187.357 gzip) | 1.576.595 B / 115 parça (425.586 gzip) |

> Bu sayılar `origin/main` üzerinde, temiz bir derlemeyle alındı. `npm ci`'nin
> HEMEN ARDINDAN yapılan ilk derleme daha küçük bir `app.css` üretiyor
> (211.900 B): `flowbite-react` eklentisi sınıf listesini derleme sırasında
> üretiyor ve ilk koşuda henüz yok. Ölçüm bu yüzden ikinci derlemeden alındı
> — aksi hâlde bu paket, kendi eklemediği 19,5 kB'ı kendine yazardı.

---

## 2. Masaüstü stil katmanı — `resources/css/app-desktop.css`

### 2.1 Nasıl yüklenir

Ayrı bir **Vite girişidir** ve yalnız `workspace-app.blade.php` içindeki
MASAÜSTÜ dalından istenir:

```php
@vite(array_filter([
    'resources/css/app.css',
    $zabunoDevice === DeviceClass::Desktop ? 'resources/css/app-desktop.css' : null,
    $zabunoDevice->entryFor('workspace'),
]))
```

Telefona giden belge bu dosyanın adını hiç anmaz, dolayısıyla tek baytını
indirmez. `app.css` içine `@import` edilmesi de yasak ve **kapıyla kırılıyor**
— edilseydi katman mobil pakete girerdi ve "yalnız masaüstünde" cümlesi
yazıldığı anda yanlış olurdu.

Derlenmiş ağırlık: **5.747 B (1.284 gzip)**. `app.css` BAYT BAYT DEĞİŞMEDİ:
paket öncesi ve sonrası derlenmiş dosyalar `cmp` ile karşılaştırıldı, aynı
(231.406 B / 38.219 gzip). Yani masaüstü katmanı paylaşılan stile tek bayt
eklemedi ve bu bir tahmin değil, bir karşılaştırma.

### 2.2 Neden ayrıca KAPSAMLI

Katmanın her kuralı `[data-device='desktop']` kapsamındadır ve niteliği
SUNUCU yazar (`App\Support\Device\DeviceClass`, `<body data-device="…">`).

Ayrı dosya "indirilmez"i garanti eder; kapsam ise "yanlışlıkla uygulanmaz"ı.
İkisi farklı sorulara cevap verir: Storybook, statik dışa aktarım ya da yarın
açılacak bir önizleme yüzeyi bütün CSS'i tek belgeye toplayabilir ve kapsam
olmasaydı orada 320 tabanı sessizce masaüstü yoğunluğuna dönerdi.

### 2.3 Genişlik sorgusu yok — GİRİŞ KİPİ sorgusu var

Katmanda tek bir `min-width`/`max-width` cihaz seçimi YOKTUR ve kapı bunu
kırar. Cihaz kararı sunucudadır.

Buna karşılık `@media (hover: hover) and (pointer: fine)` katmanın
belkemiğidir. Önemi somut ve ölçülebilir bir kusuru önler: **`DeviceClass`
iPad'i masaüstü sayar** (bağlam paneli için yeterli genişliği var) ama iPad'de
parmak vardır. Yoğunluk genişliğe bağlansaydı iPad'de dokunma hedefleri 44
pikselin altına inerdi — yani "masaüstü stili" bir erişilebilirlik kusuru
üretirdi.

Yoğunluk bu yüzden `pointer: fine` ardındadır:

| | satır | kontrol | dolgu |
|---|---:|---:|---:|
| taban (ve iPad) | 44 px | 44 px | 10/12 px |
| imleçli makine | 32 px | 32 px | 4/8 px |

32 piksel, WCAG 2.2 AA'nın işaretleyici için ölçtüğü asgari 24×24'ün
üstünde ve dokunma için gereken 44'ün altındadır. Farkı meşru kılan tek şey,
buranın parmakla kullanılmadığının SORULMUŞ olmasıdır.

**Yazı tipi küçülmez.** `TOUCH-FIRST-INTERFACE` §3 ve AEP yoğunluk
jetonlarının kendi kuralı aynı şeyi söylüyor: yoğunluk yalnız dolguyla
sağlanır.

### 2.4 Katmanın içinde ne var

- **Yoğunluk** — yukarıdaki tablo; AEP'nin kendi `compact` değerleri
  kullanıldı, yeni bir ölçek uydurulmadı.
- **Çok bölmeli düzen** (`.dk-split`, `.dk-pane`, `.dk-frame`) — bölme
  KAPSAYICI sorgusuyla açılır (`@container`), medya sorgusuyla değil: kenar
  çubuğu açılıp kapandığında ekran genişliği değişmez ama sütun genişliği
  değişir.
- **`hover` ve odak** (`.dk-rowactions`) — satır eylemleri imleçli makinede
  `hover`/`focus-within` ile açığa çıkar. TABAN "her zaman görünür"dür;
  gizleme yalnız `pointer: fine` ardında EKLENİR. Ters sırada yazılsaydı
  dokunmalı bir masaüstü makinesinde tek bir CSS hatası eylemleri tamamen
  erişilemez yapardı.
- **Odak halkası içeri çizilir** — dışa çizilen bir halka, kendi kaydırma
  kutusu olan bir bölmenin ilk ve son satırında kırpılır.
- **Bağlam menüsü, toplu şerit, kısayol satırı, ızgara ve kutu.**
- **Geniş ekranda okunabilir ölçü** (`.dk-measure`, 150ch tavan).

### 2.5 `hover` bir kısa yoldur, tek yol değil

`hover` ile açığa çıkan hiçbir BİLGİ yoktur; açığa çıkan yalnız eylem
düğmelerinin GÖRÜNÜRLÜĞÜDÜR. Aynı eylemlerin üç yolu daha var: bağlam menüsü,
klavye kısayolu ve toplu şerit. `focus-within` şarttır — yalnız `hover`
yazılsaydı klavyeyle gezen kullanıcı düğmeleri hiç göremezdi, ve bu
ekranların asıl kullanıcısı klavyeyle çalışıyor.

`prefers-reduced-motion` MUTLAK: katmanın ürettiği tek geçiş (görünürlük) o
altında kapanır ve görünürlük yine değişir — bilgi kaybı olmaz.

---

## 3. Üç ekran daha

`docs/153` §9 kalan 14 ekranı değere göre sıralamıştı. Üç "yüksek" vardı:
`menu`, `media`, `team`.

### 3.1 Menü neden yine yok — sayılarla

`MenuCatalogWorkspace.tsx` **4.613 satır / 231.575 bayt** tek bir dosyadır: 79
`useState`, ~40 işleyici, beş yerde kopyalanmış yeniden okuma. `docs/153` §9
bunun ön koşulunu M2 olarak yazmıştı: `useMenuCatalog` çıkarımı.

O çıkarım bu pakette YAPILMADI ve yapılmamalıydı: tek başına bir pakettir ve
bu paketin allowed-files kümesini üç katına çıkarırdı. Çıkarım yapılmadan
yazılacak bir masaüstü menüsü ya iş mantığını ikinci kez yazardı (`docs/153`
§4 gereği yasak) ya da mobil menüyü riske atardı.

**Menü hâlâ 3. paketin işidir ve ön koşulu değişmedi.**

### 3.2 Seçilen üç ekran

| ekran | masaüstünde ne kazandı | dosya |
|---|---|---|
| `media` | ızgara + kalıcı ayrıntı bölmesi, çoklu seçim, toplu silme, sağ tık, klavye | `MediaLibraryDesktop.tsx` |
| `team` | çok sütunlu tablo, toplu rol değişimi, toplu çıkarma, sağ tık, klavye | `TeamMemberTableDesktop.tsx` |
| `ratings` | sıralama (en düşük puan), kalıcı yanıt bölmesi, klavye | `RatingListDesktop.tsx` |

Üçüncüsü "orta" banttan seçildi ve gerekçesi ucuzluk değil: kırk ürünlük bir
menüde sahibin sorusu "hangisi en düşük?"tür ve telefonda o soru ancak
kaydırarak cevaplanır. Kalan "orta" adaylar ya yeni bir çizim yükü getiriyordu
(`analytics` — yan yana grafik) ya da yeni bir hat (`qr-codes` — toplu baskı
önizlemesi).

### 3.3 Ortak kalan: iş mantığı

Üç ekranın hiçbirinde tek bir `fetch` yoktur ve tek bir ürün kararı verilmez.
Veri, yazma yolları ve cümleler paylaşılan sayfadan bir BAĞLAM olarak geçer:

| ekran | bağlam | çizici |
|---|---|---|
| `media` | `media/librarySurface.ts` | `renderLibrary` |
| `team` | `team/memberListSurface.ts` | `renderMemberList` |
| `ratings` | `ratings/ratingListSurface.ts` | `renderRatingList` |

Çiziciyi çağıran tek yer `shared/DeviceSurface.tsx`. Doğrudan JSX'in içinde
çağrıldığında `react-hooks/refs` haklı bir hata veriyordu: bağlam nesnesi
içinde `useRef` okuyan geri çağrılar taşıyor ve "çizim sırasında bir işleve
nesne geçirmek" ref okuma anlamına gelebiliyor. Uyarı susturulmadı; çağrı bir
bileşenin çizimine taşındı. Bileşen CİHAZ TANIMAZ — adında da türünde de
"desktop" geçmez.

Bu paket ayrıca **bir süzgeci başsız hâle getirdi**:
`media/mediaLibraryQuery.ts`. Kod yazılmadı, TAŞINDI —
`MediaLibraryRegion` içindeki karşılaştırıcı ve süzgeç oraya alındı ve iki
yüzey de oradan okuyor. İki kopya yazılsaydı arıza sessiz olurdu: aynı arama,
aynı akşam, telefonda dört, masaüstünde beş sonuç verirdi. Bir ekranın
"yanlış çizmesi" görülür; **"farklı SÜZMESİ" görülmez.**

### 3.4 Ortak masaüstü dokusu

Klavye ve seçim davranışı üç kez yazılmadı: `desktop/useDesktopSelection.ts`
ve `desktop/DesktopContextMenu.tsx` (ikisi de masaüstü paketine kilitli). Sebep bakım kolaylığından öte:
kopyalansaydı zamanla ÜÇ FARKLI kısayol sözlüğü doğardı ve sahip aynı panelde
bir ekranda Boşluk'la, ötekinde Ctrl+tık ile seçmeyi öğrenmek zorunda kalırdı.

### 3.5 Erişilebilirlik

- **Sağ tıkın klavye karşılığı ŞARTTIR** — `Shift+F10` ve `ContextMenu` tuşu
  AYNI menüyü açar; ikinci bir "klavye menüsü" yazılmadı, çünkü iki menü
  zamanla ayrışır.
- **Sürükle-bırak eklenmedi**, dolayısıyla sürüklemesiz alternatif sorusu
  doğmadı (2.5.7).
- **Roving tabindex** her üç listede de aynı: bir kez `Tab`, içeride oklar.
- Medya ızgarasında yukarı/aşağı SATIR değiştirir ve satır genişliği
  **DOM'dan ölçülür** — sabit bir sütun sayısı kenar çubuğu açıldığında yanlış
  olurdu. Düzen motoru olmayan bir ortamda tek adıma düşer; ölçülemeyen bir
  geometriye göre atlamak, ölçtüğünü sanmaktır.
- **Erişilebilir ad katalogdan gelir, arayüzde birleştirilmez** (FF-213):
  ekip satırının adı tek bir yer tutuculu anahtardır, `ad + ' · ' + eposta`
  değil.

### 3.6 Yetki sınırı GENİŞLEMEDİ

Toplu işlem, tek tek yapılamayan hiçbir işi yapamaz — ve bu bir test tarafından
tutuluyor:

- **Sahip satırı hiçbir toplu işleme girmez.** Sahiplik silinmez, DEVREDİLİR.
- Yalnız çalışma alanının sahibi çıkarabilir; yönetici düğmeyi hiç görmez.
- Yalnız sunucunun çıkarabildiği roller çıkarılabilir.
- Kullanımdaki bir medya dosyası toplu silmeyle GİTMEZ.

Ve dördü de **atladığını SÖYLER**. Sessizce atlanan bir satır, sahibe
yapılmamış bir işi yapılmış gösterir.

---

## 4. M1 borcu kapandı: kataloglar artık cihaz tanıyor

`docs/153` §8 mobil paketin 1.155 bayt (355 gzip) büyüdüğünü ve bunun 833
baytının **yalnız masaüstünde çizilen 11 dize** olduğunu yazmıştı. Sebebi de
yazılıydı: `i18n/workspace/*.ts` katalogları eager glob ile toplanıyordu, yani
**kataloglar cihaz tanımıyordu**.

Bu paket o borcu kapattı:

- Yeni klasör `i18n/workspace-desktop/` ve toplayıcısı
  `i18n/workspace-desktop.ts` — AYRI bir glob, AYRI bir tablo.
- Masaüstü bileşenleri `t`'yi bu modülden alır; arama önce masaüstü, sonra
  ortak tabloya bakar. Yol tek kelime farklıdır ve yanlış yoldan çağıran bir
  bileşen DERLENMEZ (anahtar ötekinin tür birliğinde yoktur).
- On bir dize taşındı: ortak katalog **1789 → 1778**. Üç yeni ekranın
  **29 masaüstü dizesi** de oraya değil, cihazın kendi kataloğuna yazıldı —
  yani telefon paketi bu paketten sonra 40 dize AZ taşıyor.
- **Çeviri alan adı BÖLÜNMEDİ.** `i18n/domains.ts` iki tabloyu `workspace`
  alan adında birleştirir; PO/POT zinciri hiçbir dizeyi kaybetmez ve çevirmen
  aynı ekranın cümlelerini iki dosyada aramaz. Cihaz ayrımı bir PAKETLEME
  kararıdır, bir çeviri kararı değil.

**Ölçülen etki (yalnız M1, ekranlar eklenmeden):** mobil ilk yükleme
642.153 → 641.395 B (**−758 B ham, −185 B gzip**).

Kaynaktaki 833 bayt ile paketteki 758 bayt arasındaki fark küçültme ve
tekilleştirmedir; ikisi farklı şeyleri ölçüyor ve ikisi de doğru.

### Kapının kör noktası da kapandı

M1'i bağlarken `scripts/adaptive-bundle-gate`'in bir kör noktası çıktı: glob
örüntüsü `import.meta.glob(` arıyordu, oysa katalog toplayıcıları çağrıyı
`import.meta.glob<{ … }>(` biçiminde yazıyor. Yani **depodaki hiçbir katalog
glob'u gezilmiyordu** ve kapı bunu söylemiyordu. Örüntü düzeltildi; kapının
gördüğü masaüstü kapanışı 267 dosyadan 322'ye çıktı — kod büyümediği hâlde.

---

## 5. Kapı ne yasaklıyor

`scripts/adaptive-bundle-gate` dört yeni kural aldı ve öz-testi (`.test.sh`)
**on üç senaryo** koşuyor (dördü bu paketle eklendi):

| kural | ne kırılır |
|---|---|
| `i18n/workspace-desktop{,.ts,/*.ts}` masaüstüne kilitli | masaüstü dize kataloğu mobil pakete sızarsa |
| `app.css` masaüstü katmanını `@import` edemez | stil katmanı paylaşılan girişe sızarsa |
| masaüstü katmanı Vite girdisi olmak ZORUNDA | katman ölü kalırsa (hiç derlenmezse) |
| katmanda `@media` içinde genişlik ölçütü yasak | genişlikle cihaz seçilirse |

Kapı ayrıca iki paketin CSS ağırlığını da ayrı raporluyor.

Sunucu tarafında `tests/Feature/Device/AdaptiveServingTest` üç test kazandı:
telefonun belgesinde masaüstü katmanının adı GEÇEMEZ, masaüstünün belgesinde
GEÇMEK ZORUNDA, ve iki belge de cihaz kararını `data-device` ile taşır.

---

## 6. İki paketin ağırlığı — önce ve sonra

### Kaynak kapanışı (kapının raporu)

| | önce | sonra |
|---|---|---|
| masaüstü kapanışı | 267 dosya / 2.333.581 B | 338 dosya / 2.816.319 B |
| mobil kapanışı | 257 dosya / 2.255.140 B | 315 dosya / 2.647.624 B |
| cihaza özgü modül | 11 + 1 | 24 + 1 |

> **Bu iki satırın büyümesinin BÜYÜK KISMI kod değil.** Kapı bu paketten önce
> tür argümanlı glob'ları göremiyordu (§4); görmeye başladığı anda mobil
> kapanışa 53, masaüstüne 55 dosya "eklendi" — hiçbiri yeni yazılmadı. Kod
> olarak eklenen tarafı derlenmiş ölçüde okunur.

### Derlenmiş paket (asıl soru: kaç bayt İNİYOR)

| | önce | sonra | fark |
|---|---:|---:|---:|
| **mobil ilk yükleme** | 642.153 B (187.357 gzip) | 641.612 B (187.271 gzip) | **−541 B (−86 gzip)** |
| mobil tüm parçalar | 1.576.595 B / 115 parça (425.586 gzip) | 1.577.056 B / 117 parça (426.471 gzip) | +461 B (+885 gzip) |
| masaüstü ilk yükleme | 665.938 B (194.718 gzip) | 667.558 B (195.282 gzip) | +1.620 B (+564 gzip) |
| masaüstü tüm parçalar | 1.615.129 B / 116 parça (436.029 gzip) | 1.648.095 B / 124 parça (447.928 gzip) | +32.966 B (+11.899 gzip) |
| paylaşılan CSS | 231.406 B (38.219 gzip) | aynı dosya (`cmp`) | **0** |
| masaüstü CSS | — | 5.747 B (1.284 gzip) | mobil 0 B ister |

**Her mobil kullanıcının her oturumda indirdiği şey KÜÇÜLDÜ**: ilk yükleme
86 bayt gzip azaldı ve bunun sebebi M1 (§4).

**Bütün ekranları gezen bir mobil kullanıcının toplamı 885 bayt gzip
BÜYÜDÜ** ve sebebi ölçüldü: parça sayısı 115'ten 117'ye çıktı. Ham fark
yalnız 461 bayt; gzip farkının daha büyük olması, iki yeni parçanın AYRI
sıkıştırılmasındandır (parçalar arası tekrar sıkıştırılamıyor). Yeni
parçalar, süzgecin başsız hâle getirilmesiyle doğdu
(`media/mediaLibraryQuery.ts`) ve iki yüzeyin ortak modülü olduğu için Vite
onları ayrı bir parçaya çıkardı.

Bu, `docs/153` §8'in "ortak kod ayrıştırmasının kendisi (322 B)" satırının
aynısıdır ve **sıfır değildir**. Alternatifi süzgeci iki kez yazmaktı; o
zaman mobil paket büyümezdi ama aynı arama iki yüzeyde farklı sonuç
verebilirdi (§3.3). Bu paket sayıyı gizlemiyor: 885 bayt gzip, ödenmiş bir
bedeldir.

## 7. Ölçülemeyen — dürüstçe

**Masaüstü stil katmanı gerçek bir düzen motorunda ÖLÇÜLMEDİ.**

`scripts/mobile-ux-audit` Storybook'un statik çıktısını ölçüyor ve Storybook
`app.css`'i yüklüyor; masaüstü katmanı orada yok. Ayrıca cihaza özgü
bileşenlerin Storybook hikâyesi YOK (`docs/153` bu deseni kurdu: 320 denetim
külliyatına masaüstü hikâyesi eklemek, denetimi kendi tabanına karşı
kırardı).

Ölçülen ve ölçülmeyen, tek tek:

| koşu | sonuç |
|---|---|
| `--width 320 --height 480` | **yeni ihlal 0**, düzelen 0 (16 hikâyelik dondurulmuş borç aynen) |
| `--width 320 --direction rtl` | **yeni ihlal 0**, düzelen 0 (17 hikâyelik borç aynen) |
| `--width 1280` | 72 `wasted-width` + 6 `small-target` |
| `--width 1920` | aynı aile |

1280 ve 1920'deki `wasted-width` bulguları **bu paketin ürettiği bir kusur
değildir ve öyle raporlanmamalı**: eşik 320 için kalibre edilmiş bir
taban dosyasından geliyor ve geniş ekranda ORTALANMIŞ her bileşen ona göre
"genişlik israf ediyor". Bulguların hiçbiri bu paketin dokunduğu bir dosyaya
ait değil (`SupportPage`, `DashboardGreeting`, `LocationCard`, QR ekranları,
`TeamRoleGuide`). Altı `small-target` bulgusu ise 320'de zaten dondurulmuş
olan aynı altı bulgudur.

Yani bu paketten sonra bilinen şey şudur: **mobil bozulmadı** (320×480 ve
RTL koşularında yeni ihlal sıfır) ve masaüstü katmanının YAPISI kapıyla
doğrulandı (ayrı iniyor, `app.css`'e tek bayt eklemiyor, genişlikle cihaz
seçmiyor, ölü değil). Katmanın 1280 pikselde ekranda NASIL göründüğü
ölçülmedi ve "muhtemelen doğru" denmiyor: **bilinmiyor.**

Bunu kapatmanın yolu ayrı bir pakettir: masaüstü hikâyeleri ve onlara özgü bir
denetim koşusu (kendi taban genişliği ve kendi eşikleriyle). Bugünkü tek
denetim külliyatına eklemek doğru cevap değildir.

**İkinci ölçülemeyen:** klavye davranışı jsdom'da ölçüldü. Odak sırası ve tuş
işleyicileri gerçek; ama satır/sütun geometrisine bağlı hareket (medya
ızgarasında yukarı/aşağı) orada tek adıma düşüyor ve GERÇEK bir ızgarada
ölçülmedi.

---

## 8. Kalan iş

### Mekanizma borcu

| # | iş | durum |
|---|---|---|
| M1 | Cihaza göre bölünmüş dize katalogları | **KAPANDI** (§4) |
| M2 | `useMenuCatalog` çıkarımı | açık — masaüstü menüsünün ön koşulu |
| M3 | Derlenmiş paket ağırlığı için kapı eşiği | açık — bugün raporlanıyor, dondurulmuyor |
| M4 | Masaüstü katmanının gerçek düzen motorunda ölçümü | **YENİ** (§7) |

### Ekranlar

`orders`, `media`, `team`, `ratings` dönüştürüldü. Kalan **11** bölüm:

| bölüm | masaüstünde ne kazanır | tahmin |
|---|---|---|
| `menu` | kategori ağacı + liste + canlı önizleme aynı anda | **yüksek** (M2 gerekli) |
| `analytics` | yan yana grafik, karşılaştırma | orta |
| `qr-codes` | toplu üretim ve baskı önizlemesi | orta |
| `publication` | sürüm karşılaştırma yan yana | orta |
| `orders` (geçmiş) | sıralanabilir/filtrelenebilir tablo | orta |
| `locations` | liste + harita/ayrıntı yan yana | düşük |
| `dashboard` | daha yoğun kart ızgarası | düşük |
| `settings` | kalıcı alt gezinti | düşük |
| `billing` | plan karşılaştırma tablosu | düşük |
| `brand` | form + canlı önizleme | düşük |
| `profile`, `support` | ayrışma gerekmeyebilir | **yok** |

**"Yok" gerçek bir cevaptır** ve bu pakette bir kez daha uygulandı: ekip
ekranında bekleyen davetler listesi bilerek dokunmalı sürümde bırakıldı —
birkaç satırlık bir bekleme listesinde toplu işlemin kazandıracağı bir şey
yok.

---

## 9. İhlal edilemez kalan

- **320×480 TABAN OLMAYA DEVAM EDER.** Masaüstü stili tabanın YERİNE değil
  ÜSTÜNE gelir; mobil belge o dosyayı hiç görmez, dolayısıyla bastırılacak
  bir şey de yoktur.
- Cihaz kararı **sunucudadır** (`DeviceClass`), tarayıcıda değil.
- **Çeviri yapılmadı**: yalnız İngilizce kaynak satırları yazıldı, `tr`
  kataloğuna dokunulmadı, `shipped_locales` hâlâ `['en']`.
- Emoji yok; ikon `@phosphor-icons/react`. CDN yok. Yeni kütüphane eklenmedi.
- Panel Flowbite, kurumsal site daisyUI (`dz-`). Masaüstü katmanı üçüncü bir
  kütüphane değil, panelin kendi jetonlarını tüketen bir kural kümesidir
  (`dk-` öneki).
