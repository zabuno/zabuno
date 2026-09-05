# 102 — Restoran paneli estetik olgunluğu (kabuk + sayfalar)

**Durum:** Faz 1 ✅ (FF-77), Faz 2 ✅ (FF-78), Faz 3 kısmen ✅ (FF-79/FF-80,
2026-09-04). Sayaç: **2/4 tamamlandı, 3/4 aktif.**
**Sahibin tespiti (canlı ekran görüntüsü, Home):** "maturity level bir UX
estetiği istedim, yapmadın." Doğru: FF-63…FF-76 yapı ve davranış getirdi;
Superadmin/mühendislik kabuğunun estetiği ayrı kuruldu (`docs/50` kabuk
ailesi, `docs/36` §5 külliyat kararları).
Restoran panelinde sayfa 2000 px'e yayılmış çıplak bir yüzeydi: kart yok,
ikon yok, tonal derinlik yok, tablo başlığı gövdeyle aynı ton, "Setup" ve
"Home" başlıkları yan yana iki ayrı dünya gibi.
**Kanonik komşular:** görsel formül `docs/06` §10 (Precision Flat 2.0 + Tonal
SaaS Shell + Contextual Cards), dış külliyat `docs/36`, superadmin estetiği
Shell planı `docs/50`, külliyat kararları `docs/36`, acemi kuralları
`docs/101`, 320px `docs/48`.

---

## 1. İlke — aynı dil, iki kabuk

Restoran paneli superadmin kabuğuyla **aynı görsel dili** konuşur
(`docs/50` §4 ve `docs/36` §5); farkı yoğunluk ve ton sıcaklığıdır: operasyon
paneli sıkı ve karşılaştırmalı, restoran paneli ferah ve tek-odaklı
(`docs/101` A1 tek "şimdi").

| Metronic kalıbı | Restoran paneli karşılığı | Token |
| --- | --- | --- |
| Soluk uygulama zemini, üstünde beyaz kartlar | `<main>` zemini `surface-subtle`, kartlar `surface` | `--color-surface-subtle` / `--color-surface` |
| İkonlu, gruplu sol aside | `SidebarNav` gruplar + Phosphor ikon (kayıt: `icon`) | `--space-*`, `--density-*` |
| Kart: başlık satırı + ince ayraç + sağ üst araç | `OpsCard` (ops ile ortak bileşen) | `--radius-md`, `--color-border` |
| Tablo: soluk başlık satırı | `ResponsiveDataTable` thead `surface-subtle`, meta ağırlıklı (büyük harf YOK — `DS-NO-UPPERCASE-12`) | `--color-surface-subtle` |
| Vurgu şeridi | "Şimdi" kartında marka rengi sol şerit (`border-s-brand`) | `--color-brand` |
| Gölge | **Yok** — derinlik tonla (Flat 2.0) | — |

Alınmayanlar `docs/36` §5.4/§5.8 ile aynıdır (koyu aside, gölge, piksel sabiti,
Metronic ikon/renk seti, suite rail, Bootstrap).

---

## 2. Olgunluk cetveli — yüzey başına

| Seviye | Tanım | Ölçü |
| --- | --- | --- |
| **L0 Çıplak** | Sayfa = form/liste; kart yok, ikon yok, zemin tek ton | ekran görüntüsü |
| **L1 Yapısal** | Tek `h1`, page header, bölümler, akışkan ızgara, 320 px kapısı | `PublicPageIdentity`, `HOME-FLUID-04`, DS kapıları |
| **L2 Tonal + kart** | Zemin/kart tonu, kart grameri, ikonlu gezinti, tablo başlığı tonlu, tek birincil eylem | `docs/102` §4 kabul |
| **L3 Yoğunluk ve ritim** | 8pt ritmi tutarlı, yoğunluk token'ları (`--density-*`) her kontrolde, boş/yükleniyor/hata durumları tasarlı | template kataloğu (`docs/50` Faz 4) |
| **L4 Kişilik** | Marka ifadesi (sıcak vurgular, illüstrasyon, mikro-hareket), tema uyumu, gerçek kullanıcı testi | `docs/101` Faz 4 ölçümü |

### Bugün (FF-77 sonrası)

| Yüzey | Önce | Şimdi | Sonra |
| --- | --- | --- | --- |
| Kabuk (header/sidebar/main) | L1 | **L3** — tonal zemin, ikonlu gezinti, SABİT ray genişliği, başlıkta tek kimlik, telefonda alt gezinti (FF-83) | L4: marka ifadesi |
| Home | L0 | **L2** — tek `h1`, "Şimdi" vurgu kartı, Setup kartı, istatistik kartları, tablo kartı | L3: boş/yükleniyor durumları kartta |
| Media | L1 (FF-76) | **L2** — yükleme ve kütüphane iki kart | L3 |
| Menus | L1 | **L2→L3** — katalog kartta, kategori kutuları kart yüzeyinde (FF-81) | L3: satır yoğunluğu |
| QR codes / Insights / Locations / Team / Settings / Publication | L1 | **L2** — bölgeler `PanelCard`; Publication `cardChildren` | L3 |

---

## 3. Faz 1 — kabuk + Home (FF-77) ✅

- `AdminShell` main zemini `surface-subtle`; `DesktopChrome` aside `surface`.
- `WorkspaceSectionDescriptor.icon`; sekiz bölümün Phosphor ikonu
  (House, ForkKnife, QrCode, ChartBar, MapPin, Image, Users, Gear); mobil
  çekmece ve omnibox aynı kayıttan okur.
- Home: `WorkspacePageFrame` başlığı "Home" (tek `h1`); "Şimdi" kartı marka
  şeritli; Setup görev listesi kartta; istatistikler `StatCard`; ürün tablosu
  kartta, başlık satırı tonlu.
- Tablo başlığı: `ResponsiveDataTable` thead `surface-subtle` + meta ağırlık ve renk (büyük harf kaldırıldı, FF-126).

## 4. Kabul (Faz 1)

- Home'da tek `h1`; Setup ve "Şimdi" `section`/`aria-label` ile adlandırılmış.
- Kabukta breakpoint sınıfı yok; 320 px'te tek sütun.
- Ham piksel/ham palet yok (DS kapıları); gölge sınıfı yok.
- Gezinti öğelerinin her birinde ikon (`aria-hidden`), etiket katalogdan.

## 5. Faz 2-4

- **Faz 2 (FF-78) ✅:** `PanelCard` (= `OpsCard`, kopya değil) ve
  `WorkspacePageFrame.cardChildren`; Menus/Media/QR/Insights/Locations/Team/
  Settings/Publication gövdeleri kartta. Satır yoğunluğu ve durumların kart
  içi tasarımı Faz 3'e.
- **Faz 3:** header yoğunluk token'ları, omnibox görünümü, account popover.
- **Faz 4:** marka ifadesi (sıcak vurgu, illüstrasyon), tema uyumu, gerçek
  kebapçı testi (`docs/101` Faz 4 ile aynı oturum).

## 5b. FF-79 — görsel dil pası (2026-09-04, Storybook'ta görülerek)

Sahip: "tema çöp, 1999 model; 2026'ya göre tasarla." Kök neden kayda geçti:
FF-63…FF-78 boyunca **ekrana hiç bakılmadı**; token değiştirildi, sonuç
görülmedi. Bu turda Storybook statik olarak derlendi, kabuk ve pano
tarayıcıda açıldı ve şu iki şey görüldü:

1. **Gerçek hata:** `--color-canvas` takma adı yalnız `@theme` bloğunda
   tanımlıydı. Tailwind v4'te `@theme` takma adı kök değere DONAR; `.dark`
   bloğunda tekrar edilmezse koyu temada zemin açık kalır. Ekranda kenar
   çubuğu siyah, ana alan açık gri çıkıyordu — kart grameri görünmüyordu.
   Düzeltildi (`.dark` ve forced-colors bloklarında yeniden tanımlandı).
2. **Görsel dil:** kartlar `radius-lg` + `space-5` dolgu; kart başlığı
   `text-body` (sayfa başlığıyla yarışmaz); sayı kartında etiket
   `text-meta` ağırlıklı ve soluk (büyük harf yok), değer büyük ve tabular; gezinti
   öğeleri `radius-md` + 8pt ritim, grup başlıkları `text-caption`
   0.08em; üst çubuk dikey dolgusu `space-3` (cam yüzey sözleşmesi
   `docs/06` §11 korundu); tablo başlığı `space-5` hizalı, satırda hover.

## 5c. FF-80 — Faz 3 ritim ve durumlar (2026-09-04)

- **Boş/hata/kısıt durumları:** `PageState` ortalanmış, `space-7` dikey
  dolgu, başlık `text-section`, açıklama 48ch. Sola yaslı ve dar dolgulu
  hâli "yarım kalmış liste" gibi duruyordu.
- **Sayfa ritmi:** başlık bloğu ile gövde arası `space-fluid-lg`; kabuk ana
  alanı dolgusu `space-fluid-lg` — içerik kenardan ve başlıktan nefes alır.

`docs/102` yüzey tablosunda kabuk ve Home **L3**'e yaklaşır; kalan L3 işi
form alan ritmi ve liste satır yoğunluğudur.

## 5d. FF-81 — menü kataloğu yoğunluğu (2026-09-04)

Kategori/bölüm kutuları panel kart grameriyle aynı yüzeye taşındı
(`radius-lg`, `surface`, `space-5` dolgu, `space-4` iç boşluk); kategori
başlığı `text-section`. Menus yüzeyi tabloda **L3**'e yaklaşır.

## 5e. FF-83 — kabuk tutarlılığı, başlık tekrarı, telefon alt gezintisi (2026-09-04)

Sahibin canlı ekranlarından üç kusur:

1. **Kenar çubuğu sayfadan sayfaya daralıyordu.** Ana alan, kenar çubuğu ve
   bağlam paneli üçü de esnek BÜYÜME oranı taşıyordu (`4_1_32rem` /
   `1_1_17rem` / `1_1_21rem`); bağlam paneli açılan sayfada oranlar yeniden
   dağılıyordu. Şimdi raylar sabit (`basis` + `grow-0 shrink-0`), yalnız ana
   alan büyür. Kabuk her sayfada aynı.
2. **Başlıkta "Zabuno Zabuno Zabuno".** Ürün adı (marka işareti), çalışma
   alanı adı ve şube seçici üst üste geliyordu. `docs/50` §5 kapsam tablosu:
   çalışma alanı adı kenar çubuğunun üstündeki değiştiriciye aittir ve orada
   zaten var. Başlıktaki kopya kaldırıldı; şube seçici yalnız birden çok şube
   varken çizilir (tek şubede tek seçenekli bir kontrol, yer kaplayıp hiçbir
   şey yapmıyordu).
3. **Telefonda gezinti üst köşedeki hamburgerdeydi.** Alt sticky çubuk
   eklendi: dört günlük hedef (Home, Menus, QR codes, Insights) tek dokunuş,
   beşinci düğme "More" çekmeceyi açar. Hedefler bölüm kaydından okunur;
   alt çubuk varken hamburger üst çubuktan KALKAR.
## 5f. FF-84 — sistem (hesap) menüsü ve Ayarlar'ın yeri (2026-09-04)

**Sahibin kararı:** Ayarlar kenar çubuğundan **sistem menüsüne** taşındı.
`docs/50` §8 onu "utility" grubunda tutuyordu; tek maddelik bir grup başlığı
her ekranda dikey alan harcıyordu. Kayıttaki adres değişmedi
(`/app/{ws}/settings`), yalnız oraya giden kontrolün yeri değişti; bölümün
`group` alanı kaldırıldı (kayıt bu üçüncü hâli — "listelenmez ama adresi
çalışır" — zaten tanımlıyordu).

**Menünün estetiği** (öncesi: düz beyaz kutu, hizasız bir nokta, ikonsuz
satırlar):

- Panel `radius-lg`, 4 px iç dolgu, en az 16 rem genişlik; satırlar içeriden
  yuvarlanan vurguyla (kenara yapışmaz).
- Başlık artık bir KİMLİK bloğu: baş harf dairesi + e-posta + "Account".
- Satırlar dokunma yüksekliğinde, 12 px ikon boşluğuyla; Ayarlar (Gear),
  Çalışma alanı değiştir (ArrowsLeftRight), Çıkış (SignOut).
- Tema seçimi: hizasız `•` yerine sabit genişlikte sütunda onay imi (Check);
  bölüm başlığı `text-meta` ağırlıklı ve soluk, altında ayraç (`text-caption` diye bir jeton hiç var olmamıştı, FF-126).

## 5g. FF-86 — platform kabuğunda da sabit ray (2026-09-04)

FF-83 kiracı kabuğunun raylarını sabitledi; **platform/mühendislik kabuğu
(`OpsShell`) atlandı** ve orada ray `flex-[1_1_16rem]` ile büyümeye devam
etti: geniş ekranda kenar çubuğu ana içerikle alanı paylaşıp ekranın yarısını
kaplıyordu (sahibin `/platform/credentials` ekranı).

Artık kural bir TESTLE korunuyor (`OpsShell.layout.test`): kabuk ailesinin
hiçbir dosyasında büyüyen ray yazımı (`flex-[N_1_Xrem]`) bulunamaz; ray
`basis` + `grow-0 shrink-0` ile ölçülür, büyüyen tek bölge ana içeriktir.
Hikâye dosyasındaki örnek de düzeltildi — yanlış deseni öğretiyordu.

## 5h. FF-87 — persona rengi ve mavi kaçağı (2026-09-04, sahibin kararı)

**Sahibin kararı:** superadmin/mühendislik paneli **lacivere çalan** bir
zeminde çalışır; restoran paneli **kromasız** kalır ve orada mavi görünmez.
Amaç estetik değil oryantasyon: iki panel aynı tarayıcıda açıkken hangi
tarafta olunduğunu renk söyler.

**Bulunan kaçak.** Restoran panelinde sayfa nötr siyahken hesap menüsü ve
gezinti çekmecesi lacivert-gri bir yüzeyde açılıyordu. Sebep: açılır menü,
çekmece ve diyalog aileleri Flowbite'ın VARSAYILAN temasıyla çiziliyordu;
o palet Tailwind'in varsayılan grisidir ve maviye çalar (`gray-700` =
`rgb(55 65 81)`, hue ~260). Zabuno'nun yüzey token'ları kromasızdır.

Çağrı noktasında `className` ile geçmek **işe yaramadı** — tarayıcıda
ölçüldü, panel `rgb(55,65,81)` kaldı: Flowbite tema sınıfını aynı öğeye
basar ve kazananı sınıf sırası değil CSS kaynak sırası belirler. Bu yüzden
üç aile de **bağlandı**: kütüphanenin kendi teması taban alınıp yalnız renk
taşıyan yapraklar üstüne yazıldı, böylece `replace` eksiksiz oldu
(DS-FLOWBITE-TOKEN-BIND-10 korundu). Ölçüm sonrası: panel `oklch(0.2 0 0)`,
kenarlık `oklch(0.32 0 0)` — kroma sıfır.

**Persona.** `[data-persona='platform']` yalnız YÜZEY jetonlarını (zemin,
kart, hover, aktif, kenarlık) değiştirir. Koyu temanın ÇIPASI sahibin verdiği
renktir: **`#001133`** (oklch 0.1886 0.0728 258.9). Zemin birebir o renktir;
kart, hover, aktif ve kenarlık aynı kroma ve tonda, yalnız açıklık artırılarak
türetilir (0.244 / 0.284 / 0.334 / 0.364) — merdiven tek bir karardan çıkar.
Tarayıcıda ölçüldü: zemin `rgb(0, 17, 51)`. İlk iki deneme (chroma 0.022 ve
0.042) ekranda hâlâ "düz siyah" okunuyordu. Kiracı tarafı ölçümle kromasız kalır
(zemin `oklch(0.15 0 0)`, kart `oklch(0.2 0 0)`, kenarlık `oklch(0.32 0 0)`);
marka, odak, durum ve metin jetonları ortak kalır — ikinci bir tasarım
sistemi doğmaz. Öznitelik `AdminShell`'in `persona` prop'uyla verilir ve
yalnız `OpsShell` verir. Ayrıca `OpsShell` aynı özniteliği **kök öğeye** de
yazar ve ayrılırken siler: çekmece ve diyalog PORTALLA `document.body`
altına çizilir, kabuk `div`ine yazılan öznitelik onlara miras kalmazdı.

**Tüm sayfalarda.** Öznitelik üç yerde bulunur: platform ve mühendislik
Blade şablonlarının **`<body>`** etiketinde (ilk boyama doğru olsun diye —
React yüklenene kadar sayfa kiracı tonunda başlayıp sonra renk
değiştiremez; `<html>` kullanılmaz, çünkü RTL kapısı o etiketi birebir
donduruyor), `AdminShell`'in kök `div`inde ve `OpsShell`'in `<body>`ye
yazdığı çalışma zamanı özniteliğinde (portala çıkan katmanlar `body`
altına çizilir).
`PersonaSurfaceTest` (PHP) hem superadmin şablonlarının persona bildirmesini
hem de kiracı/kamu/misafir şablonlarının bildirmemesini dondurur.

**Kalıcılık.** `persona.guard.test` üç şeyi dondurur: persona yalnız platform
kabuğunda; kiracı kabuğu persona vermez; persona blokları yüzey dışında bir
jetona dokunmaz. Ayrıca üç katman ailesinin bağlı kalması ve tema
üstyazımlarında Flowbite gri paletinin bulunmaması da test edilir.

## 5i. FF-125 — AEP mürekkep merdiveni ve ölçümün nereye karşı yapıldığı (2026-09-04)

**Ne değişti.** `:root` ve `.dark` içindeki ham renk değerleri AEP teslim
paketinin merdiveniyle değiştirildi (`--canvas` `#f7f7fb`, `--surface`
`#ffffff`, `--surface-subtle` `#ededf4`, `--border` `#e4e4ee`; koyu tarafta
`#080616` / `#0d0a24` / `#16123a` / `#26224a`). Mürekkep artık boyutla değil
RENKLE ayrışıyor: `--text-meta` `0.875rem`'den **`1rem`**'e çıktı, taban her
yerde 16px. Takma adlar ve `@theme` bloğu değişmedi — zincir zaten doğruydu.

**Ölçüm aracındaki kendi hatam.** Yeni mürekkep alfalı (`rgb(8 6 22 / 66%)`).
Kapı testinin okuyucusu alfayı hiç görmüyordu, bu yüzden `compositeOver`
eklendi — ve ilk hâli LİNEER uzayda harmanlıyordu: `--fg-secondary` 6.60
yerine 2.68:1 ölçüldü. CSS alfa harmanı **gama (sRGB) uzayında** olur.
Düzeltildi ve bir kalibrasyon iddiası eklendi (%50 siyah, beyaz üstünde
3.98:1); yanlış uzay bir daha sessizce geri dönemez.

**Ekranda ölçülen (Storybook, `macro-layout-adminshell--restaurant-admin`).**
Açık tema en düşük 4.52:1, koyu tema en düşük 6.86:1, gövde metni 7.89:1,
kelime işareti 14.76:1 — hepsi 16px.

**Ölçüm neye karşı yapılır.** Kapı testi mürekkebi yalnız `--surface`
üstünde ölçüyordu; orada `--fg-muted` 6.60:1. Aynı metnin ekrandaki gerçek
zemini `--canvas`'tı ve ölçüm **4.52:1**'e düştü — hâlâ AA, ama pay 0.02.
Artık her metin jetonu hem kartta hem zeminde ölçülüyor; zemin bir ton
koyulaşırsa test kırılır, kullanıcı değil.

**Ekranı görünce çıkan gerçek hata.** Atlama bağlantısı (`SkipLink` ve
`public/layout.blade.php`) koyu temada marka sarısı üstüne **beyaz** metin
yazıyordu: ~1.75:1. Klavye kullanan birinin her sayfada ilk karşılaştığı
kontrol okunmuyordu. Sarının üstündeki tek doğru mürekkep
`--color-action-fg`'dir (#1c1500, 11.63:1); iki dosya da jetona bağlandı ve
kural teste yazıldı — bu bağlantı ham renk sınıfı kullanamaz.

**Uygulanmayan bir jeton, sebebiyle birlikte.** AEP paketi odak halkasını
parlamento mavisine (#003399) çeviriyor ve "halka metin değildir" diyor.
Uygulanmadı: `docs/71` sahibin şikâyetini ve alınan kararı kaydediyor —
karar "şu an mavi değil" değil, **"mavi OLAMAZ"** idi. Bir jetonu tasarım
paketi istedi diye geri çevirmek o kararı sessizce iptal etmek olurdu.
Sahip isterse tek satır; ama o satır bilerek atılacak.

**Değeri iki yerde tutmamak.** `ThemeCssContract.test` `--border`'ın iki ham
değerini donduruyordu ve merdiven değişince ürün doğru çalışırken kırmızıya
döndü. O testin ölçtüğü şey değer değil KONUM'dur (açık değer medya
sorgusunun dışında, koyu değer açık `.dark` altında); değer dondurma işi
`tokens.aep.guard.test`'e bırakıldı.

## 5j. FF-126 — büyük harf, hayalet sınıflar ve bağlanmamış tablo (2026-09-04)

**Büyük harf kalktı.** Bu bir zevk kararı değil, bir Türkçe kararıdır. CSS'in
büyük harfe çevirmesi küçük i'yi Türkçede İ'ye çevirmek zorundadır ve bunu
yalnız öğenin dili doğru bildirilmişse yapar; panelin dili kullanıcıya göre
değiştiği için aynı etiket bir tarayıcıda "İŞLETME", diğerinde "ISLETME"
okunuyordu. Hiyerarşi artık ağırlık ve renkle kurulur; harf aralığı da büyük
harfin telafisiydi, onunla birlikte gitti. `DS-NO-UPPERCASE-12` dondurur.

**Hayalet sınıflar.** `text-caption` yirmi dört yerde yazılıydı ve `app.css`
içinde `--text-caption` diye bir jeton YOKTU: derlenmiş CSS'te tek bir kural
bile üretilmemişti. Yirmi dört yer boyut seçtiğini sanıyor, aslında
ebeveyninin boyutunu miras alıyordu. Aynı sessiz hatanın ikinci biçimi
değişken adında çıktı: sağlayıcı kasası sayfası tanımsız üç değişkene
başvuruyordu, yani kasanın birincil düğmesi zeminsiz ve renksiz çiziliyordu.
`DS-TEXT-ROLE-EXISTS-13` ikisini birlikte kapatır.

**Sayacın kendisi hatalıydı.** Tipografi cırcırı `text-meta`yı SINIF ADINA
bakarak taban altı sayıyordu; AEP tabanı onu 1rem'e çıkarınca sayaç tam
olarak istediği düzeltmeyi cezalandırdı (2 → 149). Eşik artık `app.css`'ten
okunur: bir rol adı, gerçekten 1rem altında bir değere bağlıysa borçtur.

**Tablo hiç bağlanmamıştı.** Depoda tek bir ham gri sınıfı yazılı olmadığı
hâlde her tablo başlığı kütüphanenin gri paletiyle, 12 piksel ve büyük harf
çiziliyordu; kök yaprak metni fiziksel SOLA hizalıyordu, yani Arapçada her
tablo yanlış taraftaydı. Üç sözleşme (ham palet, 16px taban, fiziksel yön)
kaynakta GREEN, üründe geçersizdi — çünkü ihlal `node_modules` içindeydi.
Tablo ailesi `replace` ile bağlandı ve kural, render edilen sınıf listesine
bakan fixture'a eklendi.

**Ekranı görünce çıkan ikinci hata.** Fiziksel hizalamayı kaldırınca
başlıklar ORTALANDI: tarayıcının kendi `th` kuralı ortalar ve miras alınan
mantıksal hizalama onu yenmez. Hizalama artık her başlık hücresinde açıkça
yazılır — ortalanmış bir başlık, kimsenin seçmediği üçüncü bir hizalamadır.

**Ekranda ölçülen.** Açık tema: başlık 16px, büyük harf yok, `surface-subtle`
zemin. Koyu tema: başlık `#16123a` üstünde beyaz %60 (6.5:1), gövde satırları
zemin üstünde 7.30:1.

## 5k. FF-127 — rayın dibindeki sabit blok (2026-09-04)

**Sorun.** Profil ve Ayarlar kayıtta grupsuzdur, yani gruplu listede
çizilmezler; tek yolları hesap menüsünün İÇİYDİ. İkisi de günlük olmayan ama
sık aranan hedeflerdir ve bir açılır menünün ardında durunca kullanıcı
"nerede?" sorusunu her seferinde yeniden sorar.

**Çözüm.** Rayın dibindeki yapışkan bölge artık iki satır taşır. Blok kendi
listesini TUTMAZ: aynı bölüm kaydından, izin süzgecinden geçmiş
tanımlayıcılardan türetilir. İkinci bir liste tutulsaydı bir bölümün izni
değiştiğinde ray onu göstermeye devam eder ve kullanıcı 403 görürdü.

**Kendi gezinti adı var.** Adsız ikinci bir gezinti bölgesi, ekran okuyucuda
iki kez "gezinti" diye okunur ve hangisinde olunduğu anlaşılmaz; blok
`Account`/`Hesap` adını taşır.

**Sıra kayıttaki `order`dan bağımsız ve sabittir:** iki maddelik bir blokta
kimlik üstte, ayarlar altta durur — bu bir kas hafızasıdır.

**Ekranda ölçüldü** (yeni `Macro/Layout/DesktopSidebar` hikâyesi): satır
yüksekliği 44px (`--control-height`, `min-height` olarak), etkin satır
`aria-current="page"` ve yüzey tonuyla işaretli, sönük satır 6.60:1.

**Neden hikâye eklendi.** Bu bölüm ürüne girmeden görülemeyen tek kabuk
parçasıydı; "değişikliği ekranda gör" kuralı tam burada uygulanamıyordu.

## 5l. FF-128 — satır aralığı: ölü bir sabitten gerçek bir tercihe (2026-09-04)

**Sorun.** Üç yoğunluk modu CSS'te tanımlıydı, ölçülüydü, test ediliydi — ve
hiç kimse değiştiremiyordu: `ThemeRoot` içinde `INTERFACE_DENSITY = 'standard'`
diye yazılıydı. Bir ayarın var olması, ona erişilebilmesi demek değildir.

**Çözüm.** Yoğunluk artık tema ile aynı ailede: kişisel, tarayıcıda saklanan,
kök öğeye `[data-density]` olarak yazılan bir tercih. Bozuk bir saklanan değer
varsayılana düşer — elle kurcalanmış bir tarayıcıda hiçbir yoğunluk kuralıyla
eşleşmeyen bir kök doğmaz.

**Görünüm'ün içinde, ayrı bir bölüm değil.** Kullanıcı "bu ekran bana nasıl
görünsün" sorusunu bir kez sorar; temayı bir yerde, satır aralığını başka bir
yerde aramak zorunda kalmamalı.

**Canlı önizleme şeridi ve ilk hâlindeki yalan.** Seçiciler kendi
görünümlerini değiştirmez; kullanıcı "sıkışık"a bastığında sonucu göremiyordu.
Şerit bunun için eklendi — ama ilk hâlinde her satırda bir düğme vardı ve
şerit yoğunluğa HİÇ tepki vermedi: satırın yüksekliğini dolgu değil, içindeki
44 piksellik dokunma hedefi belirliyordu. Yani önizleme, göstermediğini
gizliyordu. Satırlar metne indirildi, kontrol ayrı durdu.

**Ekranda ölçüldü** (yeni `Surface/Workspace/AppearanceRegion` hikâyesi):
sıkışık 36px satır / 44px kontrol, standart 44/44, rahat 52/52. Yani dokunma
hedefi hiçbir modda 44'ün altına inmiyor — yardım metninin sözü artık ekranda
kanıtlı.

## 5m. FF-129 — yükleme öncesi kırpma (2026-09-04)

**Sorun.** Sunucudaki işleyici (`GdMediaAssetProcessor`) slotun oranına göre
MERKEZDEN kırpıyor ve bunu kullanıcıya hiç sormuyordu. Bir yemek
fotoğrafında bu masum bir varsayım değil: 3:1 bir kapak görselinde tabak çoğu
zaman merkezde durmaz ve restoran sahibi yanlış çerçeveyi ancak yayımladıktan
sonra görür.

**Kırpma İSTEMCİDE yapılır ve bu bir güvenlik kararıdır.** Dosya kullanıcının
kendi makinesindedir, sunucudan servis edilmez. Taranmamış bir dosyayı
"önizleme" diye sunucudan geri vermek, virüs taramasının engellemeye
çalıştığı şeyin ta kendisidir.

**Araç imkânsız durumda hiç açılmaz.** Kırpma piksel EKLEMEZ; 800×600 bir
fotoğraf 1200×400 isteyen bir slota hiçbir çerçeveyle sığmaz. Küçük bir
kaynağa çerçeve seçtirip sonunda "olmadı" demek emeği boşa harcatmaktır.

**Sessiz delik kapandı.** İstemci kontrolü yalnız kenarları en küçük ölçüyle
karşılaştırıyordu: 1250×1250 bir fotoğraf, 1200×500 isteyen 3:1 bir slot için
her iki kenarda da yeterli görünür — ama 3:1 çerçeve 1250×417 olur ve
yükseklik yetmez. Kontrol artık ORANDAN SONRA yapılıyor.

**Ekranı görünce çıkan hata.** Çerçeve kutusu doğru, sayı doğru, gösterilen
kare YANLIŞTI: taban stil sayfası her görüntüye `max-width: 100%` koyuyor ve
yakınlaştırılmış görüntü sessizce eziliyordu — yani önizleme, yüklenecek
şeyden başka bir kare gösteriyordu. Hiçbir test bunu ölçmüyordu; yalnız
ekrana bakınca görüldü.

**Ekranda ölçüldü** (yeni `Surface/Workspace/ImageCropField` hikâyesi):
2400×1200 kaynak, 3:1 slot → en geniş çerçeve 2400×800, en fazla 2 kat
yakınlaştırma ve tam en küçük ölçüde 1200×400. Görüntü çerçevenin iki katı
genişlikte ve seçilen kare önizlemede birebir görünüyor.

**Hâlâ eksik olan (ürün değil, dağıtım).** Yüklenen dosya `accepted` olmadan
işlenmez; `accepted` olması için virüs taramasının temiz dönmesi gerekir ve
`MEDIA_SCANNER_DRIVER` varsayılanı `unavailable`'dır. Sunucuda ClamAV kurulu
ve sürücü `clamav` değilse hiçbir görsel türev üretilmez — kütüphanede
önizleme çıkmaz ve fotoğraf menüde kullanılamaz. Ürün doğru davranıyor
(taranmamış dosyayı yayına almıyor); eksik olan dağıtımdır.

## 5n. FF-130 — teslim paketi kanonik ilan edildi (2026-09-04)

**Sahibin kararı:** *"herşeyin üstünde zip dosyaları UI var… zip dosyaları bu
işin tanrısıdır."* Depodaki iki karar teslim paketiyle çelişiyordu; ikisi de
pakete çevrildi.

**1. Odak halkası artık parlamento mavisi.** `--focus` `#003399` (koyu temada
`#93a8f4`). `docs/71` bunun tersini —"mavi OLAMAZ"— kaydediyordu ve o belge
SİLİNMEDİ, başına geri alma notu eklendi: kaydettiği şikâyet gerçekti ve
ayrımı hâlâ doğru. Yasaklanan şey METİN mavisiydi ve TARAYICININ KENDİ
halkasıydı; şimdi çizilen halka ürünün kendi seçtiği bir kenarlıktır. İki
temada da metin dışı 3:1 eşiği ölçülüyor (`DS-AEP-INK-11`), çünkü aynı mavi
koyu zeminde 1.85:1 verirdi.

**Ekranda ölçüldü — ve ölçüm aracı yanılttı.** `getComputedStyle` odaklanmış
düğmede halka rengini metnin rengi diye bildiriyordu; ekran görüntüsünde
halka net biçimde maviydi. Bu yüzden yanlış teşhise dayanan bir "düzeltme"
eklenip geri alındı: doğrulama ekran görüntüsüyle yapıldı, hesaplanan stille
değil.

**2. Görünüm Profil ekranına taşındı.** FF-119'da Ayarlar > Hesap'a
konmuştu ve gerekçesi doğruydu (tema kişiye aittir). Teslim paketi aynı
gerekçeyi bir adım öteye götürüyor: kişiye ait olan HER ŞEY Profil
ekranındadır; Ayarlar çalışma alanına aittir ve çalışma alanı değişince
içeriği değişir. Tema orada dururken, kişisel bir tercih restoran değişince
değişecekmiş gibi görünüyordu. Tek ev kuralı korundu.

**3. Hesap menüsü sadeleşti** — yalnız çalışma alanı değiştirme ve çıkış.
Profil ve Ayarlar rayın dibindeki blokta (FF-127). AMA telefonda ray yoktur
ve iki bölüm kayıtta grupsuz olduğu için çekmecede de çizilmez; menüden
tamamen kaldırmak ikisini de ULAŞILAMAZ yapardı. Kural bu yüzden "menüden
kaldır" değil, **"menüde yalnız ray yokken dursun"**.

## 5o. FF-131 — teslim paketi bir brief değil, çalışan bir sistemmiş (2026-09-04)

**Kök hata bendeydi.** Paketi bir tarif sanıp jetonları ELLE yeniden
türetiyordum. Paketin içinde `_ds/` altında çalışan bir tasarım sistemi
vardı: ilkel palet, semantic jetonlar, yoğunluk, tipografi ve bileşen CSS'i.
Aynı sayılar iki yerde yazılıyordu ve ilk ayrışmada hangisinin doğru olduğu
belirsizdi.

**Artık kaynak paket.** `resources/css/aep/` paketin kopyasıdır; depodaki
semantic jetonlar onun üstüne geçiş takma adı olarak oturur
(`--canvas: var(--aep-surface-canvas)`). Tailwind ve Flowbite zinciri hiç
değişmeden AEP değerlerini okur.

**Geçişin ortaya çıkardığı iki gerçek hata:**

1. **Roboto hiç yüklenmiyordu.** Depo yazı yığınında `'Roboto'` yazıyordu
   ama hiçbir yerden indirmiyordu; panel sistem yazı tipiyle çiziliyordu.
   Yani "yazı tipi kararı" yalnız bir dize olarak vardı.
2. **600 ağırlık AEP ölçeğinde yok.** Bütün başlıklar `font-semibold` (600)
   idi; izinli ağırlıklar 400/500/700 ve Roboto'da 600 ayrı kesim olarak
   yüklenmediği için tarayıcı onu SENTEZLİYORDU — başlıklar hem daha ince
   hem her tarayıcıda biraz farklı çiziliyordu.

**Paketten iki sapma, ikisi de yazılı:**

- Paketin `fonts.css`'i Phosphor'u ikon WEB YAZI TİPİ olarak yüklüyor; depo
  Phosphor'u zaten React bileşeni olarak kullanıyor, üç dış istek indirilip
  hiç kullanılmıyordu.
- Paketin `components/*.css` dosyaları zincire alınmadı. Belirleyici gözlem:
  PAKETİN KENDİ PANELİ de o sınıfları kullanmıyor — yalnız jetonları tüketip
  görünümü kendi katmanıyla kuruyor. Sözleşme jetonlardır.

**Persona geçişin dışında.** Platform panelinin lacivert kimliği AEP
jetonlarına bağlansaydı kiracı paneliyle birebir aynı renge düşer ve
öznitelik hiçbir şey yapmaz olurdu.

**Kapı testleri iki katmanı da okuyor.** Tek katman okuyan ölçüm
`var(--aep-*)` metnini renk sanıp çözemiyor ve "ölçülemedi" sessizce
"geçti"ye dönüşüyordu. Ayrıca eski okuyucu bir seçicinin yalnız İLK bloğunu
alıyordu; AEP aynı seçiciyi birden çok kez açtığı için jetonların yarısı
"tanımsız" görünüyordu.

## 6. Kullanıcı yolculuğu

Mehmet Usta Home'u açar: solda ikonlu kısa bir menü, ortada tek büyük
"Şimdi: menünü yayınla" kartı, altında beş adımlık kurulum kartı ve dört
sayı kartı; sayfa çıplak bir tabloya değil bir panele benzer. Neyi
yapacağını okumadan görür.
