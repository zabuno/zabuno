# 102 — Restoran paneli estetik olgunluğu (kabuk + sayfalar)

**Durum:** Faz 1 ✅ (FF-77), Faz 2 ✅ (FF-78), Faz 3 kısmen ✅ (FF-79/FF-80,
2026-09-04). Sayaç: **2/4 tamamlandı, 3/4 aktif.**
**Sahibin tespiti (canlı ekran görüntüsü, Home):** "maturity level bir UX
estetiği istedim, yapmadın." Doğru: FF-63…FF-76 yapı ve davranış getirdi;
`docs/99` yalnız superadmin/mühendislik kabuğunu Metronic'ten esinle kurdu.
Restoran panelinde sayfa 2000 px'e yayılmış çıplak bir yüzeydi: kart yok,
ikon yok, tonal derinlik yok, tablo başlığı gövdeyle aynı ton, "Setup" ve
"Home" başlıkları yan yana iki ayrı dünya gibi.
**Kanonik komşular:** görsel formül `docs/06` §10 (Precision Flat 2.0 + Tonal
SaaS Shell + Contextual Cards), dış külliyat `docs/36`, superadmin estetiği
`docs/99`, shell planı `docs/50`, acemi kuralları `docs/101`, 320px `docs/48`.

---

## 1. İlke — aynı dil, iki kabuk

Restoran paneli superadmin kabuğuyla **aynı görsel dili** konuşur
(`docs/99` §2 tablosu); farkı yoğunluk ve ton sıcaklığıdır: operasyon
paneli sıkı ve karşılaştırmalı, restoran paneli ferah ve tek-odaklı
(`docs/101` A1 tek "şimdi").

| Metronic kalıbı | Restoran paneli karşılığı | Token |
| --- | --- | --- |
| Soluk uygulama zemini, üstünde beyaz kartlar | `<main>` zemini `surface-subtle`, kartlar `surface` | `--color-surface-subtle` / `--color-surface` |
| İkonlu, gruplu sol aside | `SidebarNav` gruplar + Phosphor ikon (kayıt: `icon`) | `--space-*`, `--density-*` |
| Kart: başlık satırı + ince ayraç + sağ üst araç | `OpsCard` (ops ile ortak bileşen) | `--radius-md`, `--color-border` |
| Tablo: soluk başlık satırı | `ResponsiveDataTable` thead `surface-subtle`, meta büyük harf | `--color-surface-subtle` |
| Vurgu şeridi | "Şimdi" kartında marka rengi sol şerit (`border-s-brand`) | `--color-brand` |
| Gölge | **Yok** — derinlik tonla (Flat 2.0) | — |

Alınmayanlar `docs/99` §3 ile aynıdır (koyu aside, gölge, piksel sabiti,
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
- Tablo başlığı: `ResponsiveDataTable` thead `surface-subtle` + meta büyük harf.

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
   `text-meta` büyük harf/harf aralıklı, değer büyük ve tabular; gezinti
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
  bölüm başlığı `text-caption` büyük harf ve altında ayraç.

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
kart, hover, aktif, kenarlık) sahibin işaret ettiği lacivert bandına taşır:
koyu temada zemin `oklch(0.21 0.042 262)`, kart `oklch(0.275 0.041 260)` —
yani `#111827`/`#1f2937` tonu. İlk deneme chroma 0.022 ile yapılmıştı ve
ekranda hâlâ "düz siyah" okunuyordu; kroma gözle görülür bir laciverde
çıkacak kadar yükseltildi. Kiracı tarafı ölçümle kromasız kalır
(zemin `oklch(0.15 0 0)`, kart `oklch(0.2 0 0)`, kenarlık `oklch(0.32 0 0)`);
marka, odak, durum ve metin jetonları ortak kalır — ikinci bir tasarım
sistemi doğmaz. Öznitelik `AdminShell`'in `persona` prop'uyla verilir ve
yalnız `OpsShell` verir. Ayrıca `OpsShell` aynı özniteliği **kök öğeye** de
yazar ve ayrılırken siler: çekmece ve diyalog PORTALLA `document.body`
altına çizilir, kabuk `div`ine yazılan öznitelik onlara miras kalmazdı.

**Kalıcılık.** `persona.guard.test` üç şeyi dondurur: persona yalnız platform
kabuğunda; kiracı kabuğu persona vermez; persona blokları yüzey dışında bir
jetona dokunmaz. Ayrıca üç katman ailesinin bağlı kalması ve tema
üstyazımlarında Flowbite gri paletinin bulunmaması da test edilir.

## 6. Kullanıcı yolculuğu

Mehmet Usta Home'u açar: solda ikonlu kısa bir menü, ortada tek büyük
"Şimdi: menünü yayınla" kartı, altında beş adımlık kurulum kartı ve dört
sayı kartı; sayfa çıplak bir tabloya değil bir panele benzer. Neyi
yapacağını okumadan görür.
