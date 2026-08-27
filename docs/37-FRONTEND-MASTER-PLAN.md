# 37 — Frontend Master Plan: Gap Raporu ve Geliştirme Planı

> **Kapsam.** Bu belge frontend'in **tek** planıdır: felsefe, kural setleri,
> semantik ilişkiler, merkezî token kökü, teknoloji sınırları, Laravel/React
> ayrımı, admin tema ve frontpages sınırları, ve her birinin **zorlayıcı**
> karşılığı. Ölçümler 2026-08-26 tarihli kod denetimine dayanır; hiçbiri
> tahmin değildir.
>
> **Kanonik kaynaklar.** Felsefe: [`design-corpus/`](design-corpus/README.md).
> Sözleşmeler: `docs/06` (kimlik/tema), `docs/35` (component factory),
> `docs/03` ADR-L10 (renderer bağımsızlığı), `docs/14`/`docs/32` (AI duruşu).
> Depo dışında kalan referans implementasyonu: `docs/36`.
> Çeliştiklerinde `docs/` kökündeki numaralı belge kazanır.

## 0. Owner özeti

Frontend'in **yaklaşımı** olgun; **uygulaması** yaklaşımın gerisinde. Bunun tek
bir yapısal sebebi var: sözleşmeler yazılıydı, fakat hiçbiri **ölçülmüyordu.**
Ölçülmeyen kural, kural değildir — yalnız iyi niyettir.

Bu tur üç şey yapıldı: semantic token yüzeyi utility olarak yayınlandı, beş
zorlayıcı kural build'e bağlandı, ve gözle seçildiği için AA'da kalan bir metin
token'ı ölçülüp düzeltildi. Kalan boşluklar §3'te sayılıdır ve §5'teki dalgalara
bağlanmıştır.

**Ürün karşılığı:** bir restoran sahibi menüsünü kurarken karşılaştığı her
ekran — form, tablo, kart, boş durum, hata — bugün birbirinden bağımsız
kararlarla boyanıyor. Merkezî kök kurulduğunda tek bir tonu değiştirmek bütün
ürünü değiştirir; bugün 90 dosyayı elle gezmek gerekir.

## 1. Felsefe — bağlayıcı öncelik sırası

Külliyatın dondurduğu sıra ([`design-corpus/saas-panel-tasarim-sistemi.md`](design-corpus/saas-panel-tasarim-sistemi.md)):

> **görev tamamlama → içerik → tipografi → data semantics → erişilebilirlik →
> affordance → responsiveness → i18n → performans → dönüşüm → motion →
> estetik**

Operasyonel/yönetimsel modüllerde doğruluk, hata önleme, karşılaştırılabilirlik
ve auditability öne geçer. Bu sıra bir çatışma çıktığında **karar kuralıdır**:
estetik bir tercih erişilebilirliği düşürüyorsa, erişilebilirlik kazanır ve
tartışma orada biter.

**Kimlik:** Flat 2.0 tabanı + *contextual* cards. Her bilgi grubunu karta
sokmak yasaktır — spacing ve proximity zaten gruplama üretir; gereksiz kart
yuvalaması özellikle tablo, property editor ve master-detail ekranlarında
zarar verir.

**AI duruşu:** AI düzenin **katılımcısıdır, otoritesi değildir**. Kritik
yolculuk AI kapalıyken deterministik yürür (`docs/14`). AI slot'lar üzerinden
çalışır; öneri üretir, karar vermez, insan onayından geçer.

## 2. Olması gereken — hedef mimari

### 2.1 Merkezî kök: token zinciri

Külliyatın en çok vurguladığı madde
([`design-corpus/olcu-birimleri.md`](design-corpus/olcu-birimleri.md)):

> Bileşen hiçbir zaman doğrudan `8px`, `16px`, `12px radius` **bilmez**;
> yalnız semantic token bilir.

Zincir:

```
primitive        ham değer        (tek kaynak, bileşene KAPALI)
    ↓
semantic         rol              (fg, surface, border, focus, danger …)
    ↓
component        bileşen-özgü     (button-primary-bg …)
    ↓
context resolver tema × density × varyant
    ↓
platform adapter web / Figma / chart teması
```

Birim kuralları: font `rem`; UI geometrisi logical token; web çıktısı CSS `px`;
responsive `fr`/`%`/container unit; `mm/cm/in` yalnız print. Atomik grid 4,
ana ritim 8.

**Merkezî yönetilebilirlik testi:** bir tonu tek yerde değiştirmek onu tüketen
her yüzeyi değiştirmelidir. Bu sağlanmıyorsa merkez yoktur.

### 2.2 Katman modeli

Külliyatın R1–R8 haritası ([`docs/36`](36-EXTERNAL-DESIGN-CORPUS.md) §4.1) ile
bu deponun micro/compound/macro modeli arasındaki eşleme:

| Külliyat | Bu depo | Sorumluluk |
|---|---|---|
| R1 token | `resources/css/app.css` + `design-system/` | Tema, density, varyant burada çözülür; bileşene **sızmaz** |
| R2 CSS temeli | `app.css` reset/tema/logical props | RTL logical property tabanı |
| R3 grid/layout | `catalog/layout/**` | Container-query öncelikli |
| R4 görsel primitive | `catalog/**/micro/**` | Box/Text/Icon/VisuallyHidden; **ürün anlamı taşımaz** |
| R5 davranış | (ayrışmamış) | Aile başına **tek sahip** |
| R6 bileşen | `catalog/**/compound/**` | Davranış + token; **varyant-kör** |
| R7 durum bileşenleri | (dağınık) | Skeleton/Empty/Error/Offline/Permission |
| R8 pattern | `catalog/**/macro/**` | DataGrid, arama+filtre, form bölümü |
| D/E ürün | `workspace/`, `admin/`, `public/` | Surface: macro'yu use-case'e bağlar |

**Bağımlılık yasası:** her katman yalnız **kendinden alttakine** bağlanır;
yukarı bağımlılık yasaktır ve ölçülür.

Külliyat yatay bağı da yasaklar. O yasak R1–R8 gibi ince bir modelde doğrudur:
orada `VisuallyHidden` R4, `IconButton` R6'dır, yani aralarındaki bağ zaten
yatay sayılmaz. Bu deponun üç katmanlı modeli ikisini aynı kutuya koyduğu için
düz bir yatay yasak, paylaşılan davranışı her bileşene **kopyalamaya**
zorlardı — korumak isterken bozardı.

Yatay yasağın gerçekte koruduğu şey **döngü**dür. Bu yüzden burada yukarı bağ
ve döngü ayrı ayrı yasaklanır (`DS-NO-CYCLE-03`), döngüsüz yatay kompozisyon
serbesttir. Model R4/R6 ayrımını kazandığında düz yasak geri getirilebilir.
Bu, bilinçli ve kayıtlı bir sapmadır.

### 2.3 Teknoloji sınırları

| Katman | Karar | Gerekçe |
|---|---|---|
| Primitive kaynağı | **Flowbite React** varsayılan | Kurulu ve yaygın; `docs/35` §2 |
| Erişilebilir boşluk | **Radix/headless sarmalanır** | Yalnız Flowbite'ın karşılamadığı yerde; duplicate primitive yasak |
| Sınıf birleştirme | `clsx` + `tailwind-merge` | Kurulu |
| Stil | Tailwind v4 `@theme` | Utility **yalnız** `@theme`'den üretilir |
| Tip | TypeScript strict | Props sözleşmesi bileşenin kontratıdır |
| Build | Vite + `laravel-vite-plugin` | Tek deployment |
| Test | Vitest + Testing Library | Story + component test |
| Görsel geliştirme | Storybook | Ana geliştirme yüzeyi |

**Duplicate yasağı:** aynı primitive iki kütüphaneden alınmaz. Bir aile için
ikinci davranış sahibi eklemek karar kapısına tabidir.

### 2.4 Laravel / React sınırı

| Yüzey | Sahip | Kural |
|---|---|---|
| Public frontpages (`/`, legal) | Blade + React ada | SEO ve ilk boyama Blade'in; etkileşim adası React |
| Public menü (`/menu/{token}`) | **Blade** | Misafir yüzeyi; JS'siz okunabilir kalmalı, PWA katmanı üstüne biner |
| Workspace app (`/app`) | **React SPA** | Hash-route'lu shell |
| Platform admin (`/platform`) | **React SPA** | Ayrı bundle |
| Auth ekranları | Blade + React | Fortify sözleşmesi Blade'de |

**Sınır kuralı:** React bileşeni asla route/fetch bilmez (macro'ya kadar);
veri ve navigasyon surface sınırından prop/context olarak girer. Blade asla
tasarım kararı vermez — token tüketir, üretmez.

### 2.5 Admin tema ve frontpages ayrımı

İkisi **aynı token kökünü** paylaşır, farklı yoğunluk ve ritim kullanır.
Frontpages editorial (geniş ritim, büyük tipografi); admin data-dense (kompakt
satır, yüksek bilgi yoğunluğu). Ayrım **density** ve **spacing** token'ında
çözülür, ayrı bir renk paleti veya ayrı bileşen ailesi üretilerek **değil**.

## 3. Gap raporu — ölçülmüş boşluklar

Durum sütunu: ✅ var ve zorlanıyor · ⚠️ var ama zorlanmıyor · ❌ yok.

| # | Alan | Olması gereken | Ölçülen durum | D |
|---|---|---|---|---|
| G1 | Semantic utility yüzeyi | Token'lar utility üretir | ✅ `@theme` yayınlıyor | — |
| G2 | Ham palet borcu | Sıfır | ⚠️ 895 ihlal / 89 dosya; cırcır ile **artamaz** | 1 |
| G3 | Kontrast | Her token AA | ✅ ölçülüyor, build'i kırıyor | — |
| G4 | Katman kapsamı | Her bileşen katmanlı | ✅ **kapandı** — `catalog/` altında katmansız dosya kalmadı | — |
| G5 | Yatay bağ / döngü | Döngü yasak, yukarı yasak | ✅ **kapandı** — `DS-NO-CYCLE-03`; sapma §2.2'de gerekçeli | — |
| G6 | **Density** | comfortable/standard/compact | ✅ **kapandı** — üç mod + `DS-DENSITY-CONTRACT-05` | — |
| G7 | **a11y kapısı** | axe "bitti" tanımının parçası | ✅ **kapandı** — 126 story taranıyor, ihlal sıfırda kilitli | — |
| G8 | Motion | Motion token + reduced-motion | ✅ **kapandı** — `DS-MOTION-CONTRACT-08` | — |
| G9 | Akışkan değerler | Token | ✅ akışkan ölçek + display tipografisi token'da; kalan üç yerel `clamp()` izole | — |
| G10 | RTL/logical | Logical öncelikli | ✅ **cırcır kuruldu** — `DS-LOGICAL-DIRECTION-06`, borç yükselemez | — |
| G11 | i18n | Altı katalog + PO pipeline | ⚠️ **scaffold + wiring kapandı** (`DS-I18N-*`, altı locale, fallback, RTL, `<html lang/dir>` türetiliyor, menü Türkçesi tam). **PO→MO→JSON pipeline hâlâ yok** | 5 |
| G12 | X5 durum grameri | Tek R7 ailesi | ⚠️ dağınık (Error 48, Loading 33, Empty 20, Skeleton 3, Permission 1, **Offline 0**) | 3 |
| G13 | Storybook IA | Yalnız 4 kök | ✅ **kapandı** — `DS-STORY-TAXONOMY-04`; `Workspace/` → `Surface/` | — |
| G14 | Varyant yönetimi | A–F overlay | ❌ varyant kütüphanesi yok | 5 |
| G15 | Performans bütçesi | Bütçe içinde ve **ölçülü** | ✅ **kapandı** — `DS-BUNDLE-BUDGET-07`, CI'da ölçülüyor | — |
| G16 | Referans implementasyon | Erişilebilir | ⚠️ depo dışında (`docs/36`) | — |
| G17 | **Primitive kaynağının teması** | Flowbite token kökünü okur | ✅ **kapandı** — beş kontrol bağlandı, `PlainButton` kaldırıldı, `DS-FLOWBITE-TOKEN-BIND-10` | — |

### 3.1 En pahalı üç boşluk

**G7 — a11y hiç ölçülmüyor.** Külliyat axe'i "bitti" tanımının parçası sayar;
Storybook eklentisi kurulu ama testte tek kontrol yok. Erişilebilirlik öncelik
sırasında estetiğin **çok** üstündedir ve şu an tamamen kanıtsızdır.

**G6 — density yok.** Admin panelin varlık sebebi bilgi yoğunluğudur. Üç mod
olmadan aynı tablo hem kasiyerin hem muhasebecinin ekranında aynı görünür.

**G11 — i18n tek dil.** Altı katalog Stage 1 kapsamındadır; bugün `en` dışında
hiçbiri yok ve bir pipeline kurulmamış. RTL altyapısı ise kısmen mevcut
(story'lerde Arapça içerik var).

## 4. Kural setleri — zorlayıcı karşılıklarıyla

Bir kural, testi yoksa kural değildir. Mevcut ve gereken zorlayıcılar:

| Kural | Zorlayıcı | Durum |
|---|---|---|
| Bileşen semantic token tüketir | `DS-RATCHET-01` | ✅ |
| Kompozisyon aşağı doğru akar | `DS-LAYER-DIRECTION-01` | ✅ |
| Yayınlanan token ham değere + karanlık temaya bağlı | `DS-TOKEN-INTEGRITY-01` | ✅ |
| Katmanlı bileşende ham hex yok | `DS-NO-RAW-HEX-01` | ✅ |
| Her katmanlı bileşenin story'si var | `DS-STORY-COVERAGE-01` | ✅ |
| Metin token'ı AA karşılar | `DS-CONTRAST-AA-01` | ✅ |
| Her story axe'ten geçer | `DS-A11Y-AXE-01` | ✅ |
| Her story render edilebilir | `DS-A11Y-RENDERABLE-02` | ✅ |
| Yoğunluk tipografiye dokunmaz, dokunma hedefi küçülmez | `DS-DENSITY-CONTRACT-05` | ✅ |
| Yoğunluk token'ı gerçekten tüketilir | `DS-DENSITY-CONSUMED-09` | ✅ |
| Fiziksel yön sınıfı artmaz | `DS-LOGICAL-DIRECTION-06` | ✅ |
| Story kökü icat edilmez | `DS-STORY-TAXONOMY-04` | ✅ |
| Bileşenler arası döngü yok | `DS-NO-CYCLE-03` | ✅ |
| Bundle bütçeyi aşmaz | `DS-BUNDLE-BUDGET-07` | ✅ |
| Bileşen ham süre bilmez, azaltılmış hareket yanıtlanır | `DS-MOTION-CONTRACT-08` | ✅ |
| Flowbite primitifi token kökünü okur (ham palet/sabit piksel üretmez) | `DS-FLOWBITE-TOKEN-BIND-10` | ✅ |

## 5. Geliştirme planı — dalgalar ve kapılar

Her dalga bir öncekinin zorlayıcısı GREEN olmadan açılmaz (külliyatın kapı
kuralı). Dalga sayısı sabittir; kapsam değişirse yeni sürüm adı verilir.

### Dalga 1 — Erişilebilirlik kapısı (G7) — ✅ KAPANDI
axe test hattına bağlandı: 126 story gerçek bağlamıyla (CSF `render` +
decorator) render edilip taranıyor. Tarama yedi ihlal buldu ve **üçü de
düzeltildi**, cırcıra gerek kalmadı:

- `KeyValueList` — `<dl>` sabit bir çocuk grameri dayatır; rol taşıyan hiçbir
  doğrudan çocuk (`role="none"` dahil) kabul edilmez. Satır ayrımı Divider
  bileşeninden grup `div`'inin kenarlığına taşındı.
- `AdminShell` — kalıcı kenar çubuğu ile mobil çekmece aynı adı taşıyan iki
  `<nav>` landmark'ı üretiyordu. Çekmece zaten adlandırılmış bir diyalog
  olduğu için içindeki gezinti `asLandmark={false}` ile landmark olmaktan
  çıkarıldı.
- `MenuItem` — bileşen doğruydu, story bağlamı eksikti: `role="menuitem"` bir
  `role="menu"` ebeveyni ister. Story'ye decorator eklendi ve tarama artık
  decorator'ları uyguluyor, yoksa üründe hiç var olmayan bir DOM ölçülürdü.

**Kapı:** ihlal sıfırda kilitli; `color-contrast` jsdom'da ölçülemediği için
o eksen token seviyesinde `DS-CONTRAST-AA-01` ile ayrıca kapalı.

### Dalga 2 — Katman gerçeği (G4, G5, G13) — ✅ KAPANDI
`catalog/forms/` altındaki yedi sınıflandırılmamış bileşen micro/compound
klasörlerine taşındı; `catalog/` altında katmansız dosya kalmadı.

`PageHeader` compound'dan **macro**'ya alındı: bir compound'u (Breadcrumbs)
compose edip slot sunan şey tanımı gereği bir pattern'dir. Bu bir kaçamak
değil, sınıflandırma düzeltmesidir.

Yatay bağ yasağı düz biçimde geri getirilmedi; yerine döngü yasağı kondu ve
gerekçesi §2.2'ye yazıldı.

`Workspace/` story kökü `Surface/` altına alındı ve kök icadı teste bağlandı.

**Yol boyunca çıkan kusur:** a11y kapısı `forwardRef` ile yazılan bileşenleri
`typeof === 'function'` kontrolü yüzünden sessizce atlıyordu — yani en çok
sarmalanan form primitive'lerine kördü. Düzeltilince anında üç gerçek ihlal
buldu (`Select`'in erişilebilir adı yoktu).

**Kapı:** her bileşen bir katmana ait, yukarı bağ ve döngü ölçülüyor.

### Dalga 3 — Yoğunluk, geometri, yön (G6, G9, G10) — ✅ KAPANDI
Üç yoğunluk modu kuruldu (`.density-comfortable` / varsayılan / `.density-compact`)
ve referans implementasyonun değerleri birebir alındı. İki madde teste bağlandı:

- **Satır yüksekliği height + padding ile değişir, font-size ile asla.** Aksi
  hâlde kompakt mod okunabilirliği düşürür; yoğunluk bir okunaklılık takası
  değildir.
- **Dokunma hedefi hiçbir modda küçülmez.** Kompakt mod satırı görsel olarak
  daraltır, parmakla dokunulabilirliği değil.

Atomik ızgara (4) ve ritim (8) `--space-1…8` olarak, akışkan aralık ölçeği ve
display tipografisi token olarak tanımlandı; bileşenlere gömülü `clamp()`
değerleri bu token'lara taşındı.

Fiziksel yön sınıfları için cırcır kuruldu: RTL'de fiziksel yön hata vermez,
yalnız **sessizce yanlış tarafa hizalar** — bu yüzden borç ölçülür ve
yükselemez.

**Kapı:** yoğunluk sözleşmesi ve yön borcu ölçülüyor.

**Sonradan eklenen ders.** İlk hâlinde yoğunluk modları CSS'te tanımlanmıştı
fakat **hiçbir bileşen onları okumuyordu** — yani Storybook'ta anahtarı
çevirmek hiçbir şeyi değiştirmiyordu. Tanımlanmış ama tüketilmeyen token
sistem değil süstür. `ResponsiveDataTable` yoğunluğa bağlandı ve
`DS-DENSITY-CONSUMED-09` bağlantının sessizce kopmasını engelliyor.
Storybook araç çubuğuna yoğunluk anahtarı eklendi (tema ve yön zaten vardı).

### Dalga 4 — Motion ve bütçe (G8, G15) — ✅ KAPANDI
Motion ölçeği (süre + easing) token oldu. Azaltılmış hareket süreleri
**sıfırlamaz, kısaltır**: ani sıçrama yumuşak geçişten daha rahatsız edicidir
ve durum değişimini okunmaz kılar — referans implementasyon da aynı tercihi
yapar. Vestibüler rahatsızlığı olan kullanıcı için bu bir tercih değil,
kullanılabilirlik şartıdır.

Bileşenlerde gömülü süre **sıfır** ölçüldü, bu yüzden cırcır yerine doğrudan
yasak kondu.

Bundle bütçesi CI'da ölçülüyor. Ölçümün gerçekten çalıştığı, bütçe geçici
olarak düşürülerek kanıtlandı: **156 KB gzip / 200 KB bütçe**, 44 KB pay.

**Kapı:** motion sözleşmesi ve bütçe ölçülüyor.

### Dalga 5 — i18n ve varyant (G11, G14) — kısmen
**i18n scaffold + wiring kapandı.** Altı locale kayıtlı (`en` complete,
diğerleri scaffold — `docs/26` S1-WP03 bunu böyle kapsıyor), eksik çeviri
`en`'e düşüyor, yön locale özelliği olarak çözülüyor ve `<html lang/dir>`
artık dört Blade'de elle yazılmak yerine uygulama locale'inden türüyor.

Bu sonuncusu göründüğünden önemliydi: istemci tarafı çevirici locale'i
`<html lang>`'den okur, yani sabit kodlanmış bir etiket altı katalog kurulsa
bile dil seçimini **sessizce dondururdu**.

Menü kataloğu Türkçe olarak tamamlandı — ürünün asıl kitlesi Türk
restoranları olduğu için ilk tamamlanan yüzey burası.

**Hâlâ eksik:** PO→MO→JSON pipeline'ı ve kalan beş dilin içeriği (Stage 2,
`docs/26` S1-WP03). A–F varyant overlay'i de bu dalgada bekliyor.

**Kapı:** ikinci bir dil uçtan uca çalışıyor; pipeline ve varyant açık.

### Dalga dışı — Primitive kaynağının teması (G17) — ✅ KAPANDI

> Bu bir dalga DEĞİLDİR ve numara almaz: yukarıdaki dalga sayısı bu belgede
> dondurulmuştur ve onu değiştirmek yeni bir plan sürümü gerektirir. Burada
> anlatılan iş, Dalga 2'nin (katman gerçeği) ve Dalga 3'ün (yoğunluk)
> zorlayıcıları GREEN olduktan sonra açığa çıkan tek bir boşluğu kapatır.

`docs/37 §2.3` primitive kaynağı olarak Flowbite'ı seçmişti ama onun
VARSAYILAN temasını hiç bağlamamıştı. Sonuç: sistem kurallıydı, ürün değildi.
`<Button color="light">` ham palet ve sabit `h-10` üretiyordu; yani token
kökünde bir tonu değiştirmek o butonu değiştirmiyor, yoğunluk anahtarı onu
hiç etkilemiyordu.

Beş kontrol (Button, TextInput, Select, Textarea, Checkbox) semantic ve
yoğunluk token'larına bağlandı. Bağlama iki noktadan uygulanır, TEK yerde
tanımlanır (`design-system/flowbite-theme.ts`): katalog primitifi kendi
dilimini prop olarak taşır (sağlayıcısız test/story'de de bağlı kalsın diye),
`ThemeRoot`taki `ThemeProvider` ise Flowbite'ı doğrudan import eden ~20
dosyayı kapsar.

`PlainButton` kaldırıldı ve **on beş dosyadaki on altı kullanımı** `Button`'a
taşındı: yedi kimlik doğrulama yüzeyi (giriş, kayıt, parola sıfırlama,
doğrulama, davet, çıkış), dört platform yönetim yüzeyi ve dört workspace
yüzeyi. `variant="primary"` → varsayılan `Button` (aynı `--color-action`
tonu), varyantsız kullanım → `color="light"`. Görünüm birebir korundu.

O bileşen zaten geçici ilan edilmişti ve `§2.3` "duplicate yasağı"nın tam
olarak yasakladığı şeydi: aynı aile için ikinci bir primitive. Kimlik
doğrulama eylemlerine markayı taşıyan paket onu geçici bir kaçış yolu olarak
kullanmıştı; artık kaçış yoluna gerek yok, çünkü kaçılan şey düzeltildi.

`catalog/forms/micro/nativeFieldStyles.ts` aynı gerekçeyle doğmuştu ve aynı
notu taşıyordu. **Sekiz yüzeyin tamamı taşındı ve dosya kaldırıldı
(2026-08-26).** Çıplak `<input>`/`<select>` öğeleri artık `TextInput`/`Select`
bileşenleridir; yani bir formdaki alan ile katalogtaki alan aynı şeydir, ve
tema kökünde bir ton değiştiğinde ikisi birden değişir.

**Yol boyunca çıkan üç kusur** — üçü de yalnız tarayıcıda görülebilirdi,
tipler ve testler üçünde de sessizdi:

- **Yoğunluk zinciri kopuktu.** `:root`ta `var()` ile türetilen bir custom
  property, tanımlandığı yerde BİR KEZ ikame edilir ve alt elemanlara
  çözülmüş hâlde miras kalır. `--control-height` `:root`ta 44px'e donuyor,
  `.density-comfortable` içinde yeniden hesaplanmıyordu — yani yoğunluk
  anahtarı hiçbir kontrolü değiştirmiyordu. Türetilmiş token'lar artık her
  modda yeniden tanımlanır ve eksik bir mod build'i kırar.
- **Hover, `base`'ten renk varyantlarına sızıyordu.** `twMerge` yalnız AYNI
  CSS özelliğini çakıştırabildiği için `base`'e konan bir `hover:bg-*`, onu
  yeniden tanımlamayan her varyanta geçiyordu: dolu sarı butonun üzerine
  gelindiğinde buton nötr griye dönüyordu. Hover artık rengin kendi kararı.
- **`ThemeProvider`'ı barrel'dan import etmek bundle'ı 226 KB'ye çıkarıyordu**
  (bütçe 200). `ThemeRoot` paylaşılan chunk'ta olduğu için barrel bütün
  kütüphaneyi oraya çekiyordu. Alt-yol import'uyla 160 KB'ye döndü.

**Kapı:** ham palet ve sabit geometri artık KAYNAKTA değil, RENDER EDİLEN
sınıf listesinde ölçülüyor.

Bu ayrım kritik: `DS-RAW-PALETTE-BANNED-01` mutlak bir yasaktır ve bu depodaki
kaynağı sıfır ihlalle tarar — ama Flowbite'ın paleti `node_modules` içindedir
ve hiçbir kaynak tarayıcısı onu göremez. Yani "kaynakta sıfır" ile "üründe
sıfır" AYNI ŞEY DEĞİLDİ: depoda `h-10` yazan tek satır yokken üretilen HTML'de
`h-10` vardı. İki kural birbirinin yerine geçmez, birbirini tamamlar.

Yasağın KAPSAMI da genişletildi: tarama artık `.tsx` yanında `.ts` dosyalarını
da okur. Sınıf listesini en yoğun taşıyan dosya (`design-system/flowbite-theme.ts`)
JSX içermez, yani mutlak yasak tam olarak sınıfların en yoğun toplandığı yerde
geçersizdi. Katman kuralları
(`DS-STORY-COVERAGE-01` ve kardeşleri) `.tsx` ile sınırlı kaldı: bir katman
kuralı BİLEŞEN hakkında konuşur, stil sabiti dışa aktaran bir modül hakkında
değil.

### Borç düşürme — dalgalara paralel
G2'nin 895 ihlali her dalgada azaltılır. Cırcır artışı zaten engelliyor;
hedef, her dalgada dokunulan dosyaların semantic token'a geçirilmesidir.

## 6. Non-goals

- Bu plan MVP Exit Gate'i **genişletmez**. Dalga 1–2 MVP'yi destekler; 3–5
  Post-MVP'ye taşabilir ve bu bilinçlidir.
- Referans implementasyonunu depoya taşımak bu planın konusu değildir
  (`docs/36`, owner IP kararı).
- Yeni bir UI kütüphanesi seçmek bu planın konusu değildir; Flowbite+Radix
  sınırı korunur.

## 7. Kabul kriterleri

- [ ] Her dalga kapısı, kendi zorlayıcı testi GREEN olmadan kapanmaz.
- [ ] `docs/06`, `docs/35`, `docs/03` ile çelişki yok; çelişki çıkarsa numaralı
      belge kazanır ve bu plan güncellenir.
- [ ] Her yeni kural bir testle gelir; testsiz kural bu belgeye yazılmaz.
- [ ] Ham palet borcu hiçbir dalgada yükselmez.

## Fazlanmış tasarım geçişi

Bu planın külliyata olan borcu `docs/41-DESIGN-SYSTEM-ROADMAP.md`
(`DESIGN-2030-v1`) içinde altı faza bölünmüştür. Faz 1 token kökünü
bağlar; ondan önce yapılan her görsel düzeltme, kök bağlanınca yeniden
yapılır.
