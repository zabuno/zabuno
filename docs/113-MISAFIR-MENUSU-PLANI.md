# 113 — Misafir menüsü: kaynak envanteri, yetenek boşluğu ve plan

**Kanonik kaynak:** `docs/reference/guest-menu/lezzet-sarayi.dc.html`
(10.443 satır; gerçek şablon 9.620–10.440 arası, öncesi gömülü font
dosyalarıdır). Sahibin kuralı geçerlidir: **tasarımı sahip veriyorsa
kaynak kazanır**, eski belgeler kaynağa uydurulur.

**Bu belge bir ANALİZ ve PLANDIR.** Ürün kodu içermez, hiçbir ekranın
yapıldığını iddia etmez. Aşağıdaki her "VAR" satırı depodaki bir dosya ve
satır numarasıyla ölçülmüştür; hiçbiri bir belgenin iddiasından
türetilmemiştir (`docs/110` yöntemi).

**Ölçüm tabanı:** `main` üzerinden `ff-166-devam` @ `81606f0`, 2026-09-05.

---

## 0. Bir düzeltme: kaynak 320 için tasarlanmış

Görev tanımında *"kaynak en küçük `min-width:375px` kullanıyor — yani
320 px tasarlanmamış"* deniyordu. Kaynağı okuyunca bu doğru çıkmadı ve
düzeltilmesi gerekiyor, çünkü planın en pahalı bölümü buna dayanıyor.

Kaynakta medya sorgusu **olmayan** taban katman 320 katmanıdır ve
yazarı bunu bilerek yapmış. Kendi yorumu, satır 9.672–9.673:

> `320'de fiyat + kalp + Ekle bir satıra sığmıyor (174px izde 124px`
> `sabit). Fiyat kendi satırını alır; ≥375'te yeniden tek satır.`

Taban katmanda `.gm-hdrutil{display:none}` ile tema ve favori düğmeleri
başlıktan **çıkarılıp** filtre çubuğuna alınıyor (9.675–9.676), kart
görseli 96 px'e iniyor (9.667), fiyat kendi satırına düşüyor (9.674).
`min-width:375px` bunları **geri alan** katmandır, kuran katman değil.

Yani kaynak "mobile-first" değil, gerçekten "320-first" yazılmış. Bu iyi
haber: `docs/48`'in 1. maddesiyle çatışmıyor. Çatışma başka yerde ve
gerçek — §7'de.

---

## 1. Kaynağın tam envanteri

### 1.1 Bölümler

| # | Bölüm | Kaynak satır | Kabuk |
| --- | --- | --- | --- |
| 1 | Üst başlık (yapışkan) | 9.720–9.751 | `<header>`, `position:sticky` |
| 2 | Masaüstü arama satırı | 9.734–9.740 | `.gm-desk`, yalnız ≥1024 |
| 3 | Kategori şeridi | 9.743–9.750 | `<nav class="gm-hs">` |
| 4 | Filtre çubuğu (mobil) | 9.754–9.766 | `.gm-mobfilter`, yapışkan |
| 5 | Gövde kabuğu | 9.769 | `.gm-shell`, ≥1024'te iki sütun |
| 6 | Filtre paneli | 9.773–9.856 | `<aside>`; <1024 sayfa, ≥1024 yan panel |
| 7 | Liste | 9.859–9.928 | `<main>`, gruplu |
| 8 | Sepet çubuğu (mobil) | 9.932–9.938 | `position:fixed`, alt |
| 9 | Arama katmanı | 9.941–9.989 | tam ekran örtü, `z-index:80` |
| 10 | Ürün sayfası | 9.992–10.062 | alt sayfa; ≥1024'te ortalanmış kalıcı |
| 11 | Sepet | 10.064–10.118 | alt sayfa |
| 12 | Toast | 10.120–10.127 | `role="status"`, Geri al düğmeli |

### 1.2 Etkileşimler

`aria-label`'dan görünen 18 etiket ve arkalarındaki davranış:

| Etiket | Sayı | Davranış (kaynak) |
| --- | --- | --- |
| Ara | 1 | `openSearch` — tam ekran katman açar |
| Sesli arama | 2 | **hiçbir işleyicisi yok** — dekoratif |
| Tema | 2 | `toggleTheme` — `light`/`dark` |
| Favoriler | 2 | `openFavs` — listeyi favorilere daraltır |
| Favori | 2 | `it.fav` / `det.fav` — bellek içi kimlik listesi |
| Sepet | 1 | `openCart` |
| Sepetim | 1 | sepet sayfası başlığı |
| Filtreler | 1 | `openFilter` |
| Fotoğraflar | 1 | `role="switch"` — **galeri değil**, liste görsellerini kapatır |
| Kategoriler | 1 | yatay çip şeridi |
| Artır / Azalt | 2+2 | adet; 1'de `ph-minus` ikonu `ph-trash` olur |
| Temizle | 1 | arama kutusunu boşaltır |
| Kapat | 3 | sayfa/panel kapatır |
| Geri | 1 | arama katmanından döner |
| Ürün | 1 | ürün sayfası `role="dialog"` |

Etiketi olmayan, ama işleyicisi olan davranışlar: `togglePhotos`,
`clearFilters`, `clearAllergens`, `clearRecent`, `sendOrder`, `undo`,
`onPriceMin/Max`, `dragStart/Move/End` (sayfayı aşağı sürükleyerek
kapatma, eşik 90 px — 10.227–10.230).

**İki not, planı doğrudan etkiliyor:**

1. **"Fotoğraflar" bir galeri değil.** `role="switch"`, listedeki
   görselleri açıp kapatan bir anahtar (9.762–9.765). Ürün başına çoklu
   fotoğraf kaynakta hiç yok. Görev tanımındaki "fotoğraf galerisi"
   sorusunun kaynakta karşılığı budur.
2. **"Sesli arama" iki yerde de ölü düğmedir.** Ne `on-click` taşıyor ne
   de mantıkta karşılığı var. Kaynak onu çizmiş ama bağlamamış.

### 1.3 Veri sözleşmesi

Kaynağın ürün nesnesi (10.155–10.196) şu alanları taşıyor:

`id` · `cat` · `name` · `price` · `old` (indirimli eski fiyat) ·
`rating` · `time` (dakika) · `kcal` · `tags` (`pop`/`aci`/`ind`/`vegan`)
· `diets` (`vegan`/`vejetaryen`/`glutensiz`/`aci`) · `allergens`
(14'lük AB listesi) · `hue` (görsel yerine degrade) · `desc`.

Kategori nesnesi: `key` · `name` · `icon` · `desc`.

Filtre eksenleri (10.201–10.217): favori görünümü, kategori/etiket,
metin (ad **ve açıklama**), fiyat alt/üst, en az puan, diyet (hepsi),
alerjen (hariç tut), hazırlık süresi; sıralama `pop`/`cheap`/`exp`/`fast`.

Ürün sayfası seçenek grupları (10.269–10.274): `Porsiyon` (radio, üç
seçenek, fiyata oranlı ek ücret) ve `Yanına ne gelsin?` (checkbox, dört
sabit ek). Ayrıca serbest metin **Mutfağa not**.

---

## 2. Token envanteri ve AEP eşlemesi

Kaynak 34 token tanımlıyor (9.628–9.634). Depo tarafındaki kanonik
kaynak `resources/css/aep/tokens/*.css` (elle düzenlenmez, satıcı
kopyası) ve onun üstündeki takma ad katmanı `resources/css/app.css`.

### 2.1 Birebir tutan eksenler

| Kaynak | Değer | Depo karşılığı | Değer |
| --- | --- | --- | --- |
| `--ease` | `cubic-bezier(0,0,.2,1)` | `--aep-ease` | aynı |
| `--d1` | 120 ms | `--aep-duration-1` | 120 ms |
| `--d3` | 240 ms | `--aep-duration-4` | 240 ms |
| `--tap` | 44 px | `--aep-hit-area` | 44 px |
| `--scrim` | rgb(… / 52%) | `--aep-scrim` | var |

Dokunma hedefi ve hareket eğrisi **aynı** çıktı. Bu, kaynağın rastgele
değil, aynı aileden bir sistemle çizildiğini gösteriyor ve benimseme
maliyetini düşürüyor.

### 2.2 Rol eşlemesi (yeniden adlandırma yeter)

| Kaynak | Depo (semantik) |
| --- | --- |
| `--bg` | `--aep-surface-canvas` |
| `--surface` | `--aep-surface-raised` |
| `--surface2` | `--aep-surface-tint` |
| `--sunken` | `--aep-surface-sunken` |
| `--fg` / `--fg2` / `--fg3` | `--aep-text-primary` / `-secondary` / `-disabled` |
| `--line` / `--line2` | `--aep-border-subtle` / `--aep-border` |
| `--accent` / `--accent-fg` | `--aep-accent-primary` / `--aep-on-accent-primary` |
| `--accent-tint` | `--aep-accent-secondary-fill-soft` |
| `--ok` / `--ok-t` | `--aep-status-success` / `-success-fill` |
| `--warn` / `--warn-t` | `--aep-status-warning` / `-warning-fill` |
| `--danger` / `--danger-t` | `--aep-status-error` / `-error-fill` |

### 2.3 Çatışan eksenler — karar gerekiyor

| Eksen | Kaynak | Depo | Çatışmanın niteliği |
| --- | --- | --- | --- |
| Yarıçap | `--r:16px`, `--rs:12px` | `--aep-radius-lg:8px` ve dosyanın kendi cümlesi: *"operational ceiling is 8px"* | Kaynak deponun tavanının **iki katı** yuvarlak |
| Gölge | `--sh`, `--sh2`; kartta hover gölgesi | `app.css`: *"Flat 2.0 gölge yerine TON kullanır"* | Kaynak yükseklik, depo ton kullanıyor |
| Orta süre | `--d2:180ms` | `--aep-duration-2:150ms`, `-3:200ms` | Ara değer; birine yuvarlanır |
| Odak halkası | `outline:2px solid var(--accent)` | `--aep-focus-ring`; `DS-AEP-INK-11`: *odak kromasız kalır* | Kaynak odağı markaya bağlıyor, depo bilerek bağlamıyor |
| Başlık yazı ailesi | `Newsreader` (serif) | yalnız `--aep-font-sans` | Depoda görüntü/serif rolü **yok** |
| Yıldız rengi | `--star` | rol yok (`--aep-yellow-500` bir ilkeldir) | Puan yeteneği de yok — §4 |

**Karar (§5'te gerekçesi):** yarıçap ve gölge, kaynağın *kimliğini*
taşıyan iki eksendir; ikisi de AEP'in `variants.css` katmanında zaten
ayarlanabilir eksenlerdir (`--aep-surface-radius`, `--aep-container-shadow`).
Yani kaynağın yuvarlaklığı token tavanını **delmeden**, bir varyant
seçilerek elde edilir. Odak halkası ve serif başlık ayrı kararlardır.

### 2.4 Depoda olup kaynakta olmayan

`--aep-bp-*` (kırılma noktaları), `density` üçlüsü, `--aep-z-*` (dokuz
katmanlı z ölçeği), `--aep-container-max`, `--aep-numeric`,
`--aep-ai-provenance`, `--aep-elevation-*`, tipografi rolleri. Kaynak
z-index'i elle yazıyor (40/45/50/65/70/80/90); depoda bunun için
adlandırılmış bir ölçek var ve misafir sayfası onu tüketmiyor.

### 2.5 Bugünkü misafir sayfası hiçbirini tüketmiyor

`resources/views/public-menu.blade.php` kendi namespace'ini taşıyor:
`--qr-bg`, `--qr-fg`, `--qr-muted`, `--qr-border`, `--qr-accent`,
`--qr-chip-bg` (satır 136–154), üstüne kiracıdan gelen `--qr-brand` ve
`--qr-brand-secondary` (121–131). AEP token'larıyla **hiçbir bağı yok**;
sayfa `@vite` de çağırmıyor, yani `app.css` hiç yüklenmiyor.

Bu bir kusur değil, bir tercihtir (§8) — ama "token'lar aynı olsun"
isteği, misafir yüzeyinde token zincirinin bugün **hiç bulunmadığı**
gerçeğiyle başlıyor. `docs/37` §2.1'in merkezîlik testi — *bir tonu tek
yerde değiştirmek onu tüketen her yüzeyi değiştirmelidir* — misafir
sayfası için bugün **başarısızdır**.

---

## 3. Kaynağın kırılma noktaları

| Eşik | Ne değişiyor |
| --- | --- |
| taban (320) | tek sütun; kart görseli 96 px; fiyat kendi satırında; tema+favori filtre çubuğunda; arama düğme |
| 375 | kart görseli 108 px; fiyat satıra döner; tema+favori başlığa çıkar |
| 430 | kart görseli 120 px |
| 600 | iki sütun; kart dikeye döner, görsel 172 px sabit yükseklik |
| 1024 | iki sütunlu kabuk (288 px filtre + içerik); filtre yapışkan yan panel; ürün sayfası ortalanmış kalıcı; arama satır olur; sepet çubuğu gizlenir |
| 1280 / 1600 | üç / dört sütun |

Depodaki `--aep-bp-*` ölçeği: 320 / 430 / 768 / 1024 / 1440. **Yalnız
430 ve 1024 tutuyor.** 375, 600, 1280, 1600 deponun ölçeğinde yok.

---

## 4. Yetenek boşluğu

Ölçüt: **VAR** = kod var, kanıt gösterildi. **KISMEN** = bir parçası
var, eksik olan adıyla yazıldı. **YOK** = kodda karşılığı yok.

| # | Kaynaktaki bileşen | Durum | Kanıt / gerekçe |
| --- | --- | --- | --- |
| 1 | Marka başlığı (ad, şube, adres, telefon) | VAR | `docs/75`; `MenuIdentity.php:31-32`; yayına donar |
| 2 | Masa numarası alt satırı | YOK | Masa kavramı yok; `opt-14` bunu kendi açık sorusu sayıyor |
| 3 | Metin araması | VAR | `public-menu.blade.php:555-558` + `717-753`; **yalnız ürün adı**, açıklama aranmıyor |
| 4 | Sesli arama | YOK | `SecurityHeaders.php:68` → `microphone=()`. Kaynakta da bağlanmamış |
| 5 | Tema değiştirici | KISMEN | `partials/theme-bootstrap.blade.php` OKUR; misafir tarafında **yazan** hiçbir denetim yok |
| 6 | Favoriler (anonim) | YOK | Kodda hiç yok; misafir tarafında yazılan tek istemci durumu `zabuno-theme` |
| 7 | Sepet düğmesi ve rozeti | YOK | Order/Cart modeli, göçü, rotası yok |
| 8 | Kategori şeridi (çip, ikon, sayaç) | KISMEN | Kategoriler var, çapa olarak; çip/ikon/sayaç yok |
| 9 | Filtre çubuğu ve paneli | YOK | Misafir sayfasında `<select>` dahil hiçbir filtre denetimi yok |
| 10 | Fiyat aralığı filtresi | YOK | Veri VAR (`price_minor_amount`), denetim yok |
| 11 | En az puan filtresi | YOK | Puan verisi yok — 20 |
| 12 | Diyet filtresi | YOK | Sütun yok; ayrıca `ArtifactSchemaValidator.php:35-42` `is_vegan_certified` gibi alanları **yasaklıyor** |
| 13 | Alerjen hariç tutma | KISMEN | Alerjen verisi VAR; ama serbest metin (`UpdateMenuItemAllergensRequest.php:22-23`), kontrollü sözlük yok → facet kurulamaz |
| 14 | Hazırlık süresi filtresi | YOK | Süre sütunu yok |
| 15 | Sıralama | YOK | Fiyat/ad ile mümkün; puan/süre ile değil |
| 16 | Fotoğraf aç/kapa anahtarı | YOK | — |
| 17 | Ürün kartı görseli | VAR | Ürün başına **bir** görsel; `EloquentMenuMedia.php:63` bağlamadan önce siliyor |
| 18 | Ürün rozetleri (Popüler/İndirim/Vegan/Acılı) | YOK | Etiket kavramı yok |
| 19 | Ürün adı | VAR | `BuildPublicationSnapshot.php:76` |
| 20 | Ürün puanı / yıldız | YOK | `opt-25` tasarımda; kodda tablo, model, uç nokta yok |
| 21 | Ürün açıklaması | VAR | `BuildPublicationSnapshot.php:80` |
| 22 | Süre + kalori | YOK | Sütun yok |
| 23 | Fiyat | VAR | `:81-82` |
| 24 | Eski fiyat / indirim | YOK | Tek fiyat alanı var |
| 25 | Kart üstü favori | YOK | — 6 |
| 26 | Kart üstü "Ekle" | YOK | — 7 |
| 27 | "Sepette" şeridi | YOK | — 7 |
| 28 | Sonuç bulunamadı durumu | VAR | `GuestText.php` `searchNoMatch`, `menuEmpty`, `categoryEmpty` |
| 29 | Alt bilgi (n ürün · KDV dahil) | KISMEN | Sayı var; KDV cümlesi ürünün doğrulayamadığı bir iddia |
| 30 | Sepet çubuğu (mobil) | YOK | — 7 |
| 31 | Son aramalar | YOK | İstemci kalıcılığı yok |
| 32 | "Çok aranıyor" | YOK | Ama **veri hattı var**: `search_no_results` ölçülüyor (`:861-881`) |
| 33 | Ayrı arama sonuç listesi | KISMEN | Bugünkü arama listeyi yerinde daraltıyor, ayrı katman yok |
| 34 | Ürün sayfası kapak görseli | VAR | `public-menu-item.blade.php:157-164` |
| 35 | Ürün sayfası alerjen bölümü | VAR | `BuildPublicationSnapshot.php:83` |
| 36 | Seçenek grupları (porsiyon, yanına) | YOK | `opt-01`, `opt-02` — kodda yok |
| 37 | Mutfağa not | YOK | — 7 |
| 38 | Adet artır / azalt | YOK | — 7 |
| 39 | Sepete ekle | YOK | — 7 |
| 40 | Sepet sayfası ve "garsona bildir" | YOK | — 7 |
| 41 | Toast + Geri al | YOK | — |
| 42 | Sürükleyerek kapatma | YOK | — |

**Sayım: VAR 9 · KISMEN 5 · YOK 28.**

### 4.1 Ters yöndeki boşluk — depoda VAR, kaynakta YOK

Kaynağa körü körüne uyulursa **kaybedilecek** dört yetenek:

| Depoda | Kanıt | Kaynakta |
| --- | --- | --- |
| Misafirin dil seçimi | `GuestLocale`, `docs/85`; beş katalog | yok |
| Şube kapalı / servis dışı bildirimi | `partials/guest-closed-notice.blade.php`, `GuestOutOfService` | yok |
| "Bugün tükendi" | `out_of_stock_since`, `docs/82` | yok |
| PWA kurulumu ve çevrimdışı durumu | `public/public-diner-sw.js`, `:674-716` | yok |

Bunlar planın koruma listesidir. Yeni tasarım bunları **taşımak
zorundadır**; kaynakta olmamaları eksiklik değil, kaynağın kapsamının
dar olmasıdır.

### 4.2 İki yasak — boşluk değil, verilmiş karar

- **Mikrofon kapalı.** `SecurityHeaders.php:68` `microphone=()`
  gönderiyor. Sesli arama önce bu başlığın değişmesini ister; bu bir
  güvenlik yüzeyi kararıdır ve sahibinindir.
- **"Alerjensiz" iddiası yasak.** `ArtifactSchemaValidator.php:30-42`
  `allergen_free`, `no_allergens`, `cross_contamination`,
  `is_vegan_certified` gibi alan adlarını **ada göre** reddediyor;
  gerekçe kodda yazılı: yanlış bir alerjensizlik iddiası bir sağlık
  olayıdır. Kaynağın "Vegan" rozeti ve diyet filtresi bu yasağın
  üstünden geçemez; ancak "içerir" yönünde (hariç tutma) kurulabilir.

---

## 5. Skin / marka kimliği modeli

### 5.1 Bugün ne var

Kiracıdan gelen renk zaten misafir sayfasına ulaşıyor: `brands`
tablosunda `primary_color` ve `secondary_color`, `#rrggbb` olarak
doğrulanıyor (`MenuIdentity::normaliseColor`), yayın anında **donuyor**
ve sayfaya iki CSS değişkeni olarak yazılıyor.

Ama kullanımı bilerek daraltılmış. Blade'in kendi cümlesi (117–127):

> Renk yalnız **DEKORASYONDUR** (üst şerit ve kategori altı çizgisi);
> metin ya da metin arkası olarak kullanılmaz, çünkü restoranın seçtiği
> açık sarı bir renk beyaz üstünde okunmaz hâle gelirdi ve kontrastı
> biz garanti edemeyiz.

Renk bugün tam **iki** yerde görünüyor: 4 px'lik üst şerit (`:181`) ve
kategori başlığı altındaki 2 px çizgi (`:304`). Vurgu rengi
(`--qr-accent`) sabittir, kiracının eline geçmez.

Kaynak ise `--accent`'i tam tersine kullanıyor: birincil düğme zemini,
fiyat metni rengi, seçili çip zemini, odak halkası. Yani **sahibin
isteği, deponun kontrast gerekçesini doğrudan karşılıyor.**

### 5.2 Karar: kısıt kaldırılmaz, ölçüyle değiştirilir

Sahibin isteği "kiracı marka kimliğini kursun" — ama kontrast oranı
kiracıya bırakılamaz (`docs/37` §1: erişilebilirlik estetiği yener;
`docs/06` §8: WCAG 2.2 AA asgari). İkisini uzlaştıran tek yol, kısıtı
kaldırmak değil **ölçülebilir hâle getirmektir**:

1. **Kiracı bir ton verir, ürün bir rampa üretir.** Kiracı tek bir marka
   rengi girer. Ürün ondan `accent` ailesini (`primary`, `on-primary`,
   `tint`, `fill-soft`) türetir ve **her birini kart ve zemin üstünde
   ölçer**. Ölçüm AA'yı geçmiyorsa üretilen ton, geçene kadar
   açıklaştırılır/koyulaştırılır. Kiracının verdiği ton bir *girdi*dir,
   yayınlanan değer değil.
2. **Geçmezse yayın durur.** `modules/themes-brand.md` bu kapıyı zaten
   şart koşuyor: *"safe contrast (WCAG 2.2 AA otomatik kontrol) +
   draft → preview → publish → rollback her renk/asset değişikliği için
   zorunludur — doğrudan canlıya yazma yoktur."* Bugün bu döngü kodda
   **yok**; renk doğrudan canlıya yazılıyor. Kapı bu planla kurulur.
3. **Ölçüm yayın anında yapılır, istekte değil.** Renk yayına donduğu
   için (`docs/75`) kontrast kanıtı da yayınla birlikte donar. Böylece
   Ocak'ta AA geçen bir yayın, Mart'ta kural değişse bile kendi
   kanıtını taşımaya devam eder.

### 5.3 Skin üç eksene ayrılır — hepsi kiracıya açılmaz

Deponun token katmanında **zaten** kullanılmayan bir varyant ekseni
duruyor: `resources/css/aep/tokens/variants.css`, `data-variant="a".."f"`,
altı biçim varyantı. Dosyanın kendi ilk satırı: *"the ONLY place the 12
micro-axes resolve. Components read tokens, never the variant."*
Depoda bu öznitelik hiçbir üretim kodunda tüketilmiyor — yalnız
`docs/design-corpus/ui-variant-plan/` içinde planlanmış.

Bu, sahibin "farklı skin'ler seçilebilsin" isteğinin hazır yatağıdır.
Skin üçe ayrılır:

| Eksen | Kim seçer | Neden |
| --- | --- | --- |
| **Biçim** — `data-variant="a".."f"` (yarıçap, kenarlık, gölge, şerit, başlık ağırlığı) | Kiracı, **hazır listeden** | Altı varyantın altısı da platform tarafından bir kez ölçülür; kiracı ölçülmemiş bir değer üretemez |
| **Tema** — `light` / `dark` | Misafir (cihazı) + kiracı varsayılanı | Kontrast her iki temada ayrı ölçülür |
| **Marka rengi** — tek ton girdisi | Kiracı, **serbest** | Ama §5.2'deki rampa ve kapıdan geçerek |
| **Yoğunluk** — `data-density` | **Hiç kimse** | Misafir yüzeyi tek yoğunluktur; `--aep-hit-area` 44 px'in altına inemez |
| **Tipografi ailesi** | **Hiç kimse (şimdilik)** | Depoda yalnız `--aep-font-sans` var; serif/görüntü rolü yok. Kaynağın `Newsreader`'ı ayrı bir karardır — §12 |

**Kiracıya kapatılanların gerekçesi tek cümleyle:** kiracı bir *değer*
seçemez, bir *seçenek* seçer. Değer seçtirmek, ölçülmemiş bir kombinasyonu
misafirin ekranına koymaktır ve o ekran restoranın değil, ürünün
itibarıdır.

### 5.4 `themes-brand` bugün ne taşıyor, ne eksik

| Modülün iddiası | Kodda |
| --- | --- |
| Renk rolleri: primary, secondary, accent, neutral | Yalnız primary + secondary sütunu |
| Tipografi: heading/body/mono aileleri | Sütun yok |
| Asset yuvaları: logo, cover, avatar, favicon, app icon, OG | Yalnız `users.avatar_media_asset_id`; logo yuvası tanımlı ama işlemci yok (`docs/75` §Sınır) |
| `ThemePort::tokens(domain, tenant)`, `ThemePublished` | Kodda **yok** |
| `draft → preview → published → rolled_back` | Tema için **yok** |
| WCAG AA otomatik kontrol | **Yok** |
| İzin `theme.manage` | **Yok** — bugün `WorkspaceManage` ile korunuyor (`UpdateBrandController.php:28`) |

---

## 6. "zabuno" çerçevesi

Sahibin isteği: tasarım görünmez bir çerçeveye alınsın, adı `zabuno`
olsun, sonra ona özel başlık/altbilgi gelsin.

### 6.1 Görünmez çerçeve ne demektir

Görünmez çerçeve, **bugün hiçbir piksel üretmeyen ama yarın üretebilecek
olan bir sahiplik sınırıdır.** Teknik olarak üç şey yapar ve dördüncüyü
yapmaz:

1. **Bağlamı kurar.** Tema, varyant, yoğunluk, dil ve yön özniteliklerini
   tek bir kök öğeye yazar — kaynağın bugün `<div data-theme>` ile
   yaptığını, deponun `ThemeRoot.tsx:55-61` deseniyle aynı hizada.
2. **Yuva açar.** `header` ve `footer` için iki boş yuva tanımlar. Yuva
   boşken **hiçbir düğüm üretilmez** — `display:none` bile değil,
   çıktıda hiç bulunmaz. Boş bir `<div>` bırakmak, "görünmez" değil
   "görünmeyen" olurdu ve yapışkan başlığın `top` hesabını kirletirdi.
3. **Sınır çizer.** İçerideki hiçbir bileşen çerçevenin dışını bilmez;
   çerçeve de içeridekilerin ne olduğunu bilmez. `docs/37` §2.4'ün
   sınır kuralı: veri ve gezinme yüzey sınırından girer.
4. **Yapmadığı:** stil vermez, kutu üretmez, `z-index` tüketmez, ölçü
   dayatmaz. Çerçevenin kendisi tasarım kararı vermez — `docs/37`
   §2.4: *"Blade asla tasarım kararı vermez — token tüketir, üretmez."*

### 6.2 Neden ayrı bir çerçeve, neden şimdi

Çünkü bugün misafir sayfasının **iki** farklı şablonu (`public-menu`,
`public-menu-item`) ve iki hata yüzeyi (`out-of-service`, `not-found`)
token bloklarını, tema önyüklemesini ve analitik dahilini **kopyalayarak**
taşıyor. `--qr-bg` beş dosyada ayrı ayrı tanımlı. Çerçeve, bu dört
yüzeyi tek bir kabuğa alır; ileride gelecek zabuno başlığı/altbilgisi
dört yerde değil bir yerde açılır.

### 6.3 Hafifliği nasıl bozmaz

Çerçeve **Blade partial'ıdır**, bileşen değil. Ne JS ne ayrı CSS dosyası
getirir; bugünkü satır içi `<style nonce>` bloğu çerçeveye taşınır ve
tek yerde kalır. Ölçülebilir kabul ölçütü: çerçeve girdikten sonra
misafir sayfasının **istek sayısı ve JS baytı değişmemelidir** — bugün
ikisi de sıfır dış JS paketi, sıfır CSS isteği.

---

## 7. 320 px önce

`docs/48` bağlayıcıdır. Kabul ölçütleri (§6, birebir): 320×480'de yatay
kaydırma ve taşan öğe yok; üretim bileşenlerinde kırılma noktası öneki
yok; 320'den geniş sabit genişlik yok; düzen kararları **kapsayıcıyı**
dinler, ekranı değil; hiçbir denetim içeriğin üstüne kalıcı binmez.

Araç sırası da bağlayıcıdır: **1)** içsel düzen, **2)** kapsayıcı
sorgusu, **3)** kırılma noktası — *"yalnız gerçekten ekranın kendisine
ait bir karar için ve gerekçesi yazılarak"*.

**Kapı hakkında dürüstlük notu:** `docs/48` §4 bir kapıyı
(`resources/js/components/viewport.guard.test.ts`) adıyla anıyor; o
dosya bugün depoda **yok**. Kaybolmamış — `docs/54` §8 sahibin
talimatıyla kaldırıldığını kaydediyor, çünkü kuralın yarısını
(akışkanlık) diğer yarısını (uyarlama) yasaklayarak zorluyordu. İlke
yürürlükte, zorlayıcısı yok. Ayrıca bugünkü zorlayıcılar yalnız `.tsx`
tarar; misafir yüzeyi Blade olduğu için **hiçbir kapının kapsamında
değil**. Bu planın kapsamı bir kapı yazmak değildir; ama plan bunu
bilerek yazar ve §12'de bir madde olarak taşır.

### 7.1 Kaynağın altı eşiği ne olur

| Kaynak eşiği | Karar | Gerekçe |
| --- | --- | --- |
| 375 (kart görseli, fiyat satırı, başlık araçları) | **İçsel düzene çevrilir** | Bunlar ekran değil, *kartın* kararıdır. `flex-wrap` + `flex-basis` ile fiyat yer kalmayınca kendiliğinden düşer; 375 sihirli sayısına gerek kalmaz. `docs/48` §3'ün birebir örneği budur |
| 430 (kart görseli 120 px) | **Kapsayıcı sorgusu** | Kart kendi kapsayıcısını dinler; dar bir sütuna konsa da doğru davranır |
| 600 (iki sütun, kart dikeye döner) | **`repeat(auto-fit, minmax(…))`** | Sütun sayısı eşikten değil, taban genişlikten çıkar; 1280/1600 eşikleri de böylece kendiliğinden düşer |
| 1024 (iki sütunlu kabuk, filtre yan panele) | **Kırılma noktası KALIR** | Bu gerçekten ekranın kararıdır: yan panelin varlığı sayfa iskeletini değiştirir, kart içi bir tercih değildir. `--aep-bp-md` ile aynı değer |
| 1280 / 1600 | **Düşer** | `auto-fit` bunları üretir |

Sonuç: altı eşikten **bir** tanesi kalır ve deponun kendi ölçeğindeki
`--aep-bp-md:1024px` ile aynıdır. Diğerleri içsel düzene ve kapsayıcı
sorgusuna çevrilir.

### 7.2 320'de somut olarak ne kırılır

Kaynak 320'yi düşünmüş ama iki yerde hesabı tutmuyor. 320 px'te gövde
yatay dolgusu 12+12 → kullanılabilir **296 px**:

1. **Filtre çubuğu taşar.** İçindekiler (9.754–9.765): "Filtreler"
   düğmesi (ikon 19 + metin + dolgu ≈ 105 px) + favori 44 + tema 44 +
   fotoğraf anahtarı (19 + 7 + 42 = 68) + dört boşluk × 8 = 32.
   Sabit toplam ≈ **293 px**. Geriye `resultLabel` için ~3 px kalıyor.
   Etiket `min-width:0` taşıdığı için taşmaz ama **tamamen kaybolur** —
   yani "kaç ürün gösteriliyor" bilgisi 320'de görünmez.
   *Karar:* 320'de sonuç sayısı filtre çubuğundan çıkar, listenin
   başına kendi satırına alınır. Üç yardımcı düğme (favori, tema,
   fotoğraf) tek bir "Görünüm" düğmesinin arkasına toplanır.
2. **Ürün sayfası alt çubuğu sıkışır.** Adet denetimi (44 + 32 + 44 +
   kenarlık ≈ 125 px) + boşluk 10 → "Sepete ekle · ₺185,00" düğmesine
   **153 px** kalıyor; metin kesiliyor. *Karar:* 320'de adet denetimi
   ve eylem düğmesi **iki satır** olur (adet üstte, tam genişlik eylem
   altta). — Bu madde sepet yeteneğine bağlıdır ve §13 gereği bugün
   çizilmez; kararı ileride kullanılmak üzere kaydedilir.

Ayrıca `docs/48` §6.5 gereği denetlenecek: kaynağın yapışkan başlığı +
yapışkan filtre çubuğu + sabit sepet çubuğu 320×480'de dikey alanın
kayda değer bölümünü yiyor. Üçünün aynı anda yapışkan kalması *"hiçbir
denetim içeriğin üstüne kalıcı binmez"* ölçütünü zorlar. **Karar:**
320'de yalnız başlık yapışkandır; filtre çubuğu akışa girer.

### 7.3 Kaynağın 320'de zaten doğru yaptıkları — korunur

Dokunma hedefi her yerde `var(--tap)`=44 px; taban yazı boyutu `1rem`
ve küçük görünmesi gereken yerlerde `font-size` düşürmek yerine
`transform:scale()` kullanılmış (rozetler, sayaçlar) — bu, deponun
*"yoğunluk tipografiye dokunmaz"* kuralıyla (`DS-DENSITY-CONTRACT-05`)
aynı yöndedir; `overscroll-behavior:contain` ile sayfa alt sayfa
içinden kaydırılmıyor; `env(safe-area-inset-bottom)` her sabit çubukta
var; `@media (prefers-reduced-motion:reduce)` var.

---

## 8. Adaptive yükleme

Depoda ayrım gerçektir: `vite.config.ts:44-56` altı giriş tanımlar,
ikisi cihaza özgüdür (`workspace.mobile.tsx`, `workspace.desktop.tsx`);
seçim sunucuda yapılır (`DeviceClass::detect`), `Vary` ve `Accept-CH`
başlıkları `NegotiateDeviceClass` ile yazılır, sızıntı
`scripts/adaptive-bundle-gate` ile CI'da ölçülür.

**Misafir sayfası bu ayrımı bugün kullanmıyor** ve kullanmamalı.
Gerekçe ölçülmüş bir olgudur, tercih değil:

- `public-menu.blade.php` içinde `@vite` **hiç geçmiyor**. Sayfa ne JS
  paketi ne derlenmiş CSS yüklüyor; tüm stil ve davranış satır içi ve
  nonce'lu.
- `docs/48` §5 bunu zaten kaydediyor: *"o sayfa zaten sunucuda üretilen
  sade HTML'dir (React yüklemez)."*

Bir paket bölmenin maliyeti bu yüzden **negatiftir**: bugünkü misafir
paketi 0 bayt JS'tir; ikiye bölünmüş bir paket 0'dan küçük olamaz.
`docs/54` §5'in kendi ölçütüyle — ayrım *modül sınırında* olmalı, yoksa
kod yine indirilir — misafir yüzeyinde bölünecek bir modül sınırı da
yoktur.

**Ama cihaz bilgisi zaten elde.** `NegotiateDeviceClass` genel bir ara
katmandır (`bootstrap/app.php:65`), yani misafir isteğinde de
`DeviceClass` çözülmüş durumdadır ve `Vary` başlıkları zaten
yazılmaktadır. Sayfa yalnız onu **okumuyor**.

**Karar:** misafir yüzeyinde paket bölünmez. Cihaz farkı iki yerde,
sunucuda çözülür ve HTML'e **hiç girmeyerek** çözülür:

| Fark | Nasıl |
| --- | --- |
| Dokunma / işaretçi | `@media (hover:hover) and (pointer:fine)` — kaynağın 9.641–9.645'te zaten yaptığı. Hover kuralları yalnız işaretçi varsa uygulanır; telefonda `:active` kullanılır |
| Masaüstüne özgü ağır işaretleme (yan panel iskeleti) | Sunucuda `DeviceClass` okunarak **çıktıya hiç yazılmaz** — gizlenmez, üretilmez |

İkincisi `docs/54` §5'in ruhuna uyar: dal çalışmasa bile kodun pakette
bulunması sorundur; Blade'de dal çalışmazsa **işaretleme hiç
üretilmez**. Bunun bedeli `Vary` doğruluğudur ve o başlık zaten
yazılıyor.

---

## 9. Rol ve yetki

Yeni bir kavram uydurulmaz. Bugünkü kaynak: `Permission` enum'unda 17
izin, `MembershipRole`'da beş rol, `RolePermissions::for()` içinde açık
listeler (türetme yok, dosyanın kendi tercihi).

Bu planın getirdiği yeni yönetim yüzeyleri ve izinleri:

| Yeni yetenek | İzin | Gerekçe |
| --- | --- | --- |
| Skin seçimi (varyant + tema varsayılanı) | `WorkspaceManage` | Marka rengi bugün de bu izinle düzenleniyor (`UpdateBrandController.php:28`); ikinci bir izin icat etmek aynı işi iki kapıdan geçirmek olurdu |
| Marka rengi ve kontrast kapısı | `WorkspaceManage` | aynı |
| Skin'in **yayına** girmesi | `MenuPublish` | Skin misafirin gördüğü şeyi değiştirir. `RolePermissions.php` gerekçesi birebir geçerli: *"içerik düzenlemek geri alınabilir bir iştir, yayınlamak ise misafirin gördüğü menüyü değiştirir"* |

**Sonuç rol matrisi (değişen hücreler):**

| Rol | Skin düzenler | Skin yayınlar |
| --- | --- | --- |
| Owner | evet | evet |
| Manager | evet | evet |
| Editor | **hayır** (`WorkspaceManage` yok) | hayır |
| Kitchen | hayır | hayır |
| Member | hayır | hayır |

Editor'ın dışarıda kalması kasıtlıdır ve mevcut sınırın doğal sonucudur:
Editor içerik düzenler, marka/şube ayarlarına dokunmaz.

`themes-brand.md`'nin ilan ettiği `theme.manage` izni **eklenmez**.
Gerekçe: kodda karşılığı yok, işi `WorkspaceManage` zaten yapıyor ve
`Permission` enum'una eklenen her değer beş rolün beşinde de açıkça
karara bağlanmayı gerektirir. Modül dosyası bu gerçeğe göre
düzeltilmelidir — tersi değil.

Sepet, puan ve favori için izin **tanımlanmaz**; §13 gereği bu yetenekler
çizilmiyor, izinleri de erken doğmuş olurdu.

---

## 10. Superadmin fiyat tablosu ve entitlement

Bugünkü `Entitlement` enum'u üç değer taşıyor: `qr.bulk-generation`,
`team.invitations`, `analytics.reporting`. Enum'un kendi kapsam kuralı
bağlayıcıdır:

> Entitlement **EK YETKİ** verir; temel yolculuğu kapatmaz.
> Kayıt→menü→yayın→QR zinciri plansız bir hesapta çalışmaya devam eder.

Plan kataloğu üç kademedir (`PlanCatalogueSeeder.php:63-107`):
`starter` (0, boş), `restaurant` (499 ₺, QR toplu + analitik), `team`
(999 ₺, + ekip daveti). Tohumun kendi cümlesi: *"KADEMELER
UYDURULMADI, UYGULANANDAN TÜRETİLDİ… Merdiven yalnız BÜYÜR."*

### 10.1 Bu planın eklediği tek entitlement

| Yeni değer | Neye açar | Hangi plandan |
| --- | --- | --- |
| `branding.custom` | Marka rengi + skin varyantı seçimi ve yayını | `restaurant` ve üstü |

Yalnız bir tane, çünkü:

- **Temel yolculuk kapanamaz.** Skin *seçmemek* bir kusur değildir;
  seçmeyen restoran bugünkü nötr görünümü alır (bugünkü davranış:
  renk seçilmemişse şerit yüksekliği sıfır kalır — `:170-175`).
  Dolayısıyla `branding.custom` gerçekten *ek* yetkidir.
- `modules/opt-08-custom-branding.md` bunu zaten bir entitlement
  seviyesi olarak tanımlıyor (*"Bu modülün kendisi bir entitlement
  seviyesidir"*), yeni bir kavram doğurmuyoruz.
- Sepet/puan/favori çizilmediği için onların entitlement'ları da
  yazılmaz. `opt-14` ve `opt-25`'in ilan ettiği "M2 edition" bugün
  var olmayan bir kademedir; merdiven ancak uygulanan şeyden büyür.

### 10.2 Sıkı gereken bir yer

`StoreManagedPlanRequest.php:25-26` `entitlements` alanını
`array|min:1` + her öğe `string|min:1` olarak doğruluyor — yani
superadmin serbest metin girebiliyor ve `Entitlement::tryFromKey()`
tanımayan anahtarı sessizce yok sayıyor. Enum'un kendi kuralı
(*"bilinmeyen asla yetki vermez"*) doğru davranıyor, ama superadmin
yazım hatasını **ekranda göremiyor**. `branding.custom` eklenirken bu
doğrulama enum'a bağlanmalıdır; aksi hâlde yeni değer ilk yazım
hatasında sessizce hiçbir şey açmaz.

---

## 11. Modül kurgusu

**Yeni `modules/*.md` gerekmiyor.** Gerekçe: bu planın çizdiği her şey
mevcut üç modülün kapsamına düşüyor ve 62 modüllük katalog zaten geniş.

| Bu planın parçası | Karşılayan modül | Modülde düzeltilecek |
| --- | --- | --- |
| Skin eksenleri, kontrast kapısı, draft→publish | `modules/themes-brand.md` | `theme.manage` izni kodda yok — `WorkspaceManage`/`MenuPublish` yazılmalı; asset yuvalarının işlemcisiz olduğu not düşülmeli |
| `branding.custom` entitlement'ı | `modules/opt-08-custom-branding.md` | Entitlement anahtarının adı yazılmalı |
| Misafir yüzeyi kabuğu, 320, adaptive | `modules/qr-destination.md` | Çerçeve sahipliği ve "paket bölünmez" kararı |
| Ürün sayfası alan sözleşmesi | `modules/menu-catalog.md` | Değişiklik yok |

Çizilmeyenlerin modülleri **zaten var** ve dokunulmaz: `opt-14`
(sipariş), `opt-25` (geri bildirim/NPS), `opt-01` (varyant), `opt-02`
(ekstra/modifier). Bunlara bu planda hiçbir şey eklenmez; eklenirse
"yakında geliyor" izlenimi doğar ve `docs/107`'nin faz sırası bozulur.

---

## 12. Uygulama sırası

Sıra bağımlılığa göredir, tarihe göre değil (`docs/107` §0 yöntemi).

**1. Çerçeve ve token birleştirme.** `zabuno` Blade kabuğu kurulur; dört
misafir yüzeyi (`public-menu`, `public-menu-item`, `out-of-service`,
`not-found`) ona alınır; `--qr-*` ad alanı AEP semantik token'larına
bağlanır (§2.2 eşlemesi). *Neden önce:* skin de, 320 çalışması da tek
bir token kökü olmadan iki yerde iki kere yapılır. `docs/37` §2.1'in
merkezîlik testi ancak burada geçer.

**2. 320 kararları.** §7.1 eşik çevrimi ve §7.2'nin iki kırığı. *Neden
burada:* içsel düzene çevrim, token'lar tek yerdeyken ucuz; skin
eklendikten sonra her varyantta tekrar ölçmek gerekirdi.

**3. Kontrast kapısı ve renk rampası.** §5.2. Skin'in *önünde* gelir,
çünkü kapı olmadan açılan bir renk alanı geri alınamaz: kiracı okunmaz
bir menü yayınlar ve bunu ürün yapmış olur.

**4. Skin eksenleri.** `data-variant` altı varyantının misafir
yüzeyinde tüketilmesi, tema varsayılanı, ayarlar ekranı. Yayına donma
(`docs/75` deseni) ve `draft → preview → publish → rollback`.

**5. `branding.custom` entitlement'ı + superadmin doğrulaması.** §10.
*Neden en sonda:* kapatılacak bir yetenek ancak var olduktan sonra
plana bağlanır; tersi, boş bir kutu satmak olurdu.

**Sıranın dışında, bağımsız ve küçük:** misafir tarafında tema
değiştirici (§4 madde 5). Okuma altyapısı zaten var
(`theme-bootstrap.blade.php`), eksik olan yalnız yazan denetim. Hiçbir
şeye bağlı değil, 1. adımdan sonra herhangi bir noktada girebilir.

**Ayrıca kaydedilir, bu planın kapsamı değil:** misafir Blade yüzeyini
kapsayan bir 320 kapısı yok (§7 dürüstlük notu). `docs/48`'in ilkesi
zorlayıcısız duruyor. Bir kapı yazmak ayrı bir pakettir ve `docs/54`
§8'deki kaldırma gerekçesini tekrar etmemek zorundadır.

---

## 13. ÇİZİLMEYECEKLER

`docs/109` §8.2 geleneği: sol sütun kaynakta olan, sağ sütun **deponun
ölçülmüş gerçeği**. Gerekçeler beğeni değil, yokluk bildirir. Bir madde
yapıldığında satır silinmez, üstü çizilir ve sağ sütun ne olduğunu
paket numarasıyla söyler.

| Kaynakta | Çizilmedi çünkü |
| --- | --- |
| Sepet, "Ekle" düğmesi, sepet çubuğu, sepet sayfası, "Siparişi garsona bildir" | Depoda Order/OrderItem modeli, göçü, rotası ve olayı yok; sipariş `docs/107` Faz 7, ürün Faz 1'de |
| Ürün puanı, yıldızlar, "En az puan" filtresi | Puan/geri bildirim tablosu, modeli ve uç noktası yok; `opt-25` Faz 9 |
| Favoriler, favori rozeti, "Favorilerim" görünümü | Kodda hiç yok; misafir tarafında yazılan tek istemci durumu tema tercihidir, favoriyi tutacak bir yer yok |
| Sesli arama | `SecurityHeaders.php:68` mikrofonu kapatıyor. Kaynakta da bağlanmamış bir düğmedir |
| Diyet filtresi (Vegan / Vejetaryen / Glutensiz) | Diyet sütunu yok; ayrıca `ArtifactSchemaValidator.php:35-42` "vegan sertifikalı"/"alerjensiz" biçimindeki alanları ada göre reddediyor — yanlış iddia bir sağlık olayıdır |
| "Vegan" / "Acılı" ürün rozetleri | Aynı yasak; etiket kavramı da yok |
| Hazırlık süresi filtresi ve karttaki "25 dk" | `menu_items` ve `products` tablolarında süre sütunu yok |
| Karttaki "520 kcal" | Kalori sütunu yok. Beslenme değeri beyanı ayrıca hukuki bir iddiadır |
| Eski fiyat / indirim rozeti | Tek fiyat alanı var; kampanya kavramı yok |
| Alerjen **filtresi** (hariç tutma) | Alerjen verisi var ama serbest metin; kontrollü sözlük olmadan facet "Süt" ile "süt"ü ayrı sayardı. Veri var, **sözlük** yok |
| Porsiyon ve "Yanına ne gelsin?" seçenekleri | `opt-01`/`opt-02` kodda yok; ürünün tek fiyatı var |
| "Mutfağa not" alanı | Notu iletecek sipariş kanalı yok — yazılan not hiçbir yere gitmez |
| "Son aramalar" ve "Çok aranıyor" | İstemcide kalıcılık yok; "çok aranıyor" için de bugün yalnız *sonuçsuz* aramalar ölçülüyor, popülerlik ölçülmüyor |
| Fotoğraf galerisi / birden çok görsel | Ürün başına bir görsel; `EloquentMenuMedia.php:63` ikinciyi bağlarken birinciyi siliyor. (Kaynak da galeri çizmiyor — "Fotoğraflar" bir aç/kapa anahtarıdır) |
| "Masa 12" alt satırı | Masa kavramı yok; `opt-14` bunu kendi açık sorusu olarak kaydediyor |
| "Fiyatlar KDV dahildir" | Ürün fiyatın KDV dahil olup olmadığını bilmiyor; vergi alanı yok. Doğrulanamayan bir iddia yazılmaz |
| Kart sayaçları ("7 ürün") kategori çipinde | Sayılabilir, ama filtre çizilmediği için sayacın anlattığı seçim de yok. Filtre geldiğinde bu satırın gerekçesi düşer |

**Ayrıca çizilmeyen ama farklı sebeple:** kaynağın `Newsreader` serif
başlık ailesi. Depoda serif/görüntü tipografi rolü yok ve font eklemek
misafir yüzeyine bugün olmayan bir ağ isteği getirir — sayfanın
tamamı bugün tek istekte geliyor. Bu bir yokluk değil, **sahibin
kararını isteyen bir maliyet**tir: kimlik kazancı karşılığında ilk
boyama gecikmesi.

---

## 14. Özet

**önce:** Misafir menüsü sunucuda üretilen sade HTML; kendi `--qr-*`
token ada alanını taşıyor, tasarım sisteminden kopuk, kırılma noktası
hiç yok, kiracı rengi yalnız 4 px'lik bir şeritte görünüyor.

**şimdi:** Bu belge kaynağın 42 bileşenini ölçtü ve deponun karşılığını
kodla eşledi: 9 VAR, 5 KISMEN, 28 YOK. Kaynağın 320 tabanı doğrulandı,
iki gerçek kırığı sayıyla gösterildi.

**fark:** Artık "bu tasarımı uygulayalım" cümlesi bir dilek değil; hangi
parçanın hangi veriye dayandığı, hangisinin veri olmadığı için
çizilemeyeceği ve hangi sırayla yapılacağı yazılı.

**kullaniciYolculugu:** Kadıköy'deki kebapçı ayarlardan bordo bir marka
rengi ve "yumuşak köşeli" bir skin seçer, önizler, yayınlar. Masadaki
misafir karekodu okutur; menü kebapçının renginde ama yazılar okunur
kalır, çünkü ton yayın anında ölçülmüştür. 320 px'lik eski bir telefonda
her düğmeye ulaşır. **Bugün bu yolculuk skin seçme adımında durur** —
renk yalnız bir şerittir ve kontrast kapısı yoktur.

**kalanEngel:** Kontrast kapısı ve renk rampası yazılmadan skin alanı
açılamaz. Misafir Blade yüzeyini kapsayan bir 320 zorlayıcısı yok.

**capability_delta:** Bu paket **0** yeni ürün yeteneği ekler — bir
analiz ve plan belgesidir. Ürün bugün menüyü yayınlayıp masaya kart
basabiliyor; bu belge onu bir adım ileri götürmez, bir sonraki adımın
nereye basacağını belirler.
