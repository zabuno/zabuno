# 05 — Destekleyici Bileşen Varyantları (A–F)

Bu dosya, EA Platform'un destekleyici bileşenlerinin (Button, IconButton, Badge/Tag/Status, Tabs,
SegmentedControl, Toolbar, SidePanel, Menu/Dropdown, Toast/Alert, Modal/Drawer, Pagination,
CommandPalette) altı mikro-stil varyantı [A–F] altındaki spesifikasyonunu tanımlar. Davranış
katmanı headless ve TEK'tir; varyantlar yalnız [01-varyant-cercevesi.md](./01-varyant-cercevesi.md)'ndeki
12 mikro-eksende, `data-variant="a..f"` + CSS custom property overlay ile farklılaşır. Bu aile,
P2 fazının son halkasıdır (card → form → table → destekleyici, Hafta 3–7).

Bağlantılı dosyalar: [00-genel-plan.md](./00-genel-plan.md) ·
[01-varyant-cercevesi.md](./01-varyant-cercevesi.md) ·
[02-card-varyantlari.md](./02-card-varyantlari.md) ·
[03-form-varyantlari.md](./03-form-varyantlari.md) ·
[04-table-varyantlari.md](./04-table-varyantlari.md) ·
[06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ·
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)

## 1. Kapsam ve bileşen envanteri

| Bileşen | Rol | Varyanta duyarlı yüzeyler |
|---|---|---|
| Button | Primary / secondary / ghost / danger aksiyonlar | Kabuk ayrımı, radius, focus, hover, accent dozu |
| IconButton | Yalnız ikonlu aksiyon (erişilebilir ada zorunlu) | Kabuk biçimi, focus, hover |
| Badge / Tag / Status | Semantik kapsül: durum, etiket, sayaç | Kapsül ayrım grameri, accent dozu |
| Tabs | Görünüm/sekme geçişi (tek aktif) | Selected işareti, divider, focus, motion |
| SegmentedControl | Küçük küme içinde tekli seçim (2–5 seçenek) | Konteyner grameri, selected, radius |
| Toolbar | Tablo/sayfa üstü aksiyon-filtre çubuğu | Konteyner ayrımı, divider, density |
| SidePanel | Sol Option Gallery + sağ Option Information overlay'i | Konteyner, panel-içi divider, giriş motion'u, scrim |
| Menu / Dropdown | Popover aksiyon/seçenek listesi | Popover kabuğu, item selected/hover, divider |
| Toast / Alert | Geçici bildirim ve satır-içi uyarı | Konteyner, semantik accent taşıyıcısı, giriş motion'u |
| Modal / Drawer | Kesintili diyalog ve kenar paneli | Kabuk, radius, elevation, scrim, motion |
| Pagination | Sayfalama kontrolü (tablo ile birlikte) | Selected sayfa işareti, hover, hit area |
| CommandPalette | Global komut arama overlay'i (glass istisna alanı) | Kabuk, input biçimi, glass/opak fallback |

## 2. Varyanttan bağımsız değişmezler (brief §1)

- Sarı buton kuralı: primary buton zemini **#FFB900**, üstündeki metin/ikon HER ZAMAN **#080616** —
  asla beyaz (#FFB900/#FFFFFF ≈ 1.72:1 FAIL; #FFB900/#080616 ≈ 11.63:1 AAA). Tüm varyantlarda geçerlidir.
- **#003399** koyu zemin (#080616) üstünde metin/ince ikon olarak KULLANILAMAZ (1.85:1). Dark modda
  metin-seviyesi mavi accent yalnız **blue/300 = #93A8F4**; #003399 dark'ta yalnız geniş dolgu,
  border, yapısal yüzey.
- Minimum font **1rem (16px)** — badge, tag, tab etiketi ve pagination sayıları DAHİL. İzinli
  weight'ler 400/500/700. Sayısal içerikte (badge sayacı, pagination) `font-variant-numeric: tabular-nums`.
- Görünür ikon 20–24px (Phosphor, SVG; emoji yasak); hit area **min 44×44px** — IconButton,
  tab, pagination öğesi, menu item dahil.
- `focus-visible` her bileşende ZORUNLU; asla yalnız border rengi değişimiyle yetinilmez.
  Durum asla yalnız renkle iletilmez (ikon + metin şart).
- Glass/blur yalnız CommandPalette ve global chrome'da OPSİYONEL (opak fallback şart);
  menu, toast, modal gövdesi, side panel ve tüm scrim'lerde YASAK.
  Scrim = düz yarı saydam **%40 ink/950 (#080616)**, blursuz.
- Motion: yalnız işlevsel, 120–240ms, ease-out, `prefers-reduced-motion` desteklenir;
  hover'da scale YOK. Kontrol yükseklikleri density üçlüsünü izler: comfortable=52 /
  standard=44 / compact=36 (px); density padding ile sağlanır, font küçülmez.
- Pill istisnası: pill yalnız Variant F input'larında ve semantik kapsül bileşenlerde
  (badge, tag, avatar, switch) serbesttir. Buton, tab ve segment pill OLMAZ (tavan 8px).
- Hardcode hex yasak; bu dosyadaki hex'ler token değerlerinin dokümantasyonudur, kodda yalnız
  semantic token kullanılır. RTL: tüm start/end kararları CSS logical properties ile yazılır.

## 3. 12 mikro-eksenin bu dosyadaki taşıyıcıları

Eksen kararlarının A–F değerleri [01-varyant-cercevesi.md](./01-varyant-cercevesi.md)'nde tanımlıdır;
burada yalnız hangi destekleyici bileşene uygulandığı gösterilir. Sıra brief §2 sırasıdır.

| # | Eksen | Bu dosyadaki taşıyıcı bileşenler |
|---|---|---|
| 1 | Konteyner ayrım grameri | Secondary button kabuğu, Toolbar, SidePanel, Menu, Toast/Alert, Modal, SegmentedControl kasası |
| 2 | Radius eşlemesi | Button, IconButton, Menu, Toast, Modal, SegmentedControl (badge/tag pill muafiyetli) |
| 3 | Focus-visible stili | Tüm etkileşimli bileşenler |
| 4 | Hover geri bildirimi | Button, IconButton, Tabs, Menu item, Pagination |
| 5 | Selected işareti | Tabs, SegmentedControl, Menu item, Pagination aktif sayfa |
| 6 | Divider stratejisi | Toolbar grupları, SidePanel iç bölmeleri, Menu bölümleri, Modal header/footer |
| 7 | Density varsayılanı | Toolbar, Menu item, Pagination yükseklikleri |
| 8 | Input biçimi | CommandPalette arama girişi (muafiyet alanının bu dosyadaki tek taşıyıcısı) |
| 9 | Label ağırlığı | Buton etiketi, tab etiketi, menu item metni (tümü 500; D'de 400/500 hiyerarşi istisnası) |
| 10 | Tablo başlık işlenişi | Bu dosyada taşıyıcı yok (bkz. [04-table-varyantlari.md](./04-table-varyantlari.md)); Toolbar tabloyla bitişikse görsel uyum aranır |
| 11 | Accent dozu | Primary/danger buton dağılımı, badge/status renkleri, boş durum ikonları |
| 12 | Motion mikro-davranışı | Toast/SidePanel/Modal girişleri, Menu açılışı, state geçişleri |

## 4. Button

Roller varyanttan bağımsızdır; kabuk işlenişi varyanta bağlıdır.

| Rol | Ortak tanım (tüm varyantlar) |
|---|---|
| Primary | Zemin #FFB900, metin/ikon #080616 (sarı buton kuralı). Ekran başına tek primary hedeflenir |
| Secondary | Nötr kabuk; işleniş eksen 1'i izler (border'lı / tonal / şeritli / inset / minimal / elevated) |
| Ghost | Transparan zemin, metin token rengi; yalnız hover/focus'ta zemin geri bildirimi |
| Danger | error/600 #DC2626 dolgu + beyaz metin (dark'ta aydınlatılmış türev token); ikon + metin birlikte, asla yalnız renk |

Etiket: Roboto 500, min 1rem. Yükseklik density üçlüsüne bağlıdır (52/44/36); compact'ta hit
area 44px'e tamamlanır. Disabled durumda soluklaştırma token ile yapılır, kontrast koşulu
etkileşimsiz öğe için raporlanır ama etiket okunur kalır.

| V | Kabuk/ayrım (E1) | Radius (E2) | Focus (E3) | Hover (E4) | Accent dozu (E11) | Motion (E12) |
|---|---|---|---|---|---|---|
| A | Secondary: 1px semantik border, dolgu yok | sm 4px | 2px blue ring (light #003399, dark #93A8F4), offset 2px | Border koyulaşır + %4 zemin tintı | Minimum; sarı YALNIZ primary CTA | Hover animasyonu yok; state 120ms opacity/color |
| B | Secondary: filled-tonal (bir ton), border yok | lg 8px | 2px ring + zemin bir ton aydınlanır | Yüzey bir ton açılır (150ms) | Orta | 150ms zemin geçişi |
| C | Secondary: %50 opaklıkta 1px soluk border | md 6px | Start kenarında 2px mavi şerit + 2px ring | Şerit %40 opaklıkta belirir + %4 tint | Şeritle yüksek/alan küçük; sarı şerit = AI provenance | Şerit 120ms scaleY (reduced-motion'da anlık) |
| D | Tekil buton kendi dış konteyneri: 1px border + bir ton fark; toolbar grubu İÇİNDEKİ buton 0px köşeli, köşe gruptan | lg 8px (grup içinde 0px) | Inset ring 2px (taşma yok) | Dolgu bir ton | Düşük | Grup expand/collapse 200ms height+fade |
| E | Kutulu buton minimum; ghost tercih; secondary: 1px border | xs 2px | 2px ring + etkileşimli metinde alt çizgi kalınlaşması | Metin koyulaşır; zemin tintı YOK | Çok düşük; sarı yalnız primary CTA ve kritik KPI | Yalnız içerik giriş fade 150ms |
| F | Primary/secondary raised: y=2 blur=8 %10 siyah (dark: bir ton + 1px border) | lg 8px (buton pill OLMAZ) | 2px ring + gölge derinleşir | Z-lift: gölge y=4 blur=12; scale/translate YOK | Orta-yüksek; sarı CTA + sarı odak metrikleri | 200ms gölge/renk; reduced-motion'da gölge geçişi kaldırılır, yalnız fade/renk geçişi kalır (brief F kuralının buton uyarlaması) |

## 5. IconButton

Yalnız ikonlu aksiyonlar: görünür ikon 20–24px, hit area min 44×44px, `aria-label` zorunlu,
tooltip metni ile desteklenir. Kabuk, radius, focus, hover ve motion kararları Button
tablosuyla (bkz. §4) BİREBİR aynıdır; tek fark içeriğin metinsiz olmasıdır. Ghost işleniş
varsayılandır (toolbar ve tablo actions hücresi bağlamı); dolgu gerektiren tekil kullanımda
secondary kabuk uygulanır. Toggle'lı IconButton (ör. görünüm değiştirici) selected işaretini
eksen 5'ten alır ve `aria-pressed` bildirir; durum asla yalnız renkle verilmez (ikon değişimi
veya işaret şart).

## 6. Badge / Tag / Status

Semantik kapsül sınıfı: **pill biçimi tüm varyantlarda serbesttir** (brief §1.3 kapsül muafiyeti —
badge, tag, avatar, switch). Metin min 1rem/500, sayaçlarda tabular-nums. Status asla yalnız
renk taşımaz: Phosphor ikon + metin zorunlu.

| Tür | Semantik renk (light 600 seti) | Dark not |
|---|---|---|
| Success | success/600 #15803D | Aydınlatılmış türev token |
| Error | error/600 #DC2626 | Aydınlatılmış türev token |
| Warning | warning/600 #B45309 | Aydınlatılmış türev token |
| Info | info/600 #1D4ED8 | Aydınlatılmış türev token |
| Nötr tag | ink yüzey + border token | Dark border #26224A |

| V | Kapsül ayrımı (E1) | Accent notu (E11) |
|---|---|---|
| A | 1px semantik border, dolgu yok (outline kapsül) | Sarı badge kullanılmaz |
| B | Tonal dolgu, border yok | Boş durum sarı vurgu ikonu serbest |
| C | Soluk 1px border + start'ta 2px mini şerit; sarı şerit = AI-üretimi provenance işareti | Şerit sistemi statüye ayrılmıştır |
| D | Grup zemininde tonal dolgu | Grup başlığı yanında mavi (light #003399 / dark #93A8F4) küçük vurgu |
| E | Dolgu yok; metin + ikon, gerekirse 1px rule ayrımı | Sarı yalnız kritik KPI değerinde |
| F | Tonal dolgu + dark'ta 1px border | Sarı, odak metriği rozetinde kullanılabilir |

## 7. Tabs

Tek aktif sekme; klavye: ok tuşları + `aria-selected`. Etiket 1rem/500 (E'de aktif sekme 700
işareti eksen 5 gereği). Aktif işaret hiçbir varyantta yalnız renk değildir (kalınlık/dolgu/ikon eşlik eder).

Uyarlama notu (eksen 5): yatay sekme bağlamında seçim işareti alt kenara taşınır — bu,
[01](./01-varyant-cercevesi.md)'deki logical-start işaret gramerinin yatay-bağlam karşılığıdır
(C'nin tablo başlığındaki "sıralı kolonda 2px alt şerit" kuralıyla aynı mantık). A'da start
kenarı işareti sekmede alt border'a, C'de start şeridi alt şeride dönüşür; E'de brief'in iki
seçeneğinden "başında bold işaret" ucu **etiket 700 + 2px alt rule** olarak uygulanır.

| V | Selected işareti (E5) | Liste divider'ı (E6) | Hover (E4) | Motion (E12) |
|---|---|---|---|---|
| A | Aktif sekmede 2px mavi alt border (light #003399, dark #93A8F4) | Sekme listesi altında 1px tam genişlik | Border koyulaşır + %4 tint | İşaret geçişi 120ms color/opacity |
| B | Blue %8 dolgu (dark #93A8F4 %10) + ikon; 8px radius yüzey | Yok — boşluk ayırır | Yüzey bir ton açılır (150ms) | 150ms zemin geçişi |
| C | 2px mavi alt şerit + zemin %6 mavi dolgu | 1px inset (içerik hizasında) | Şerit %40 belirir + %4 tint | Şerit 120ms scaleY |
| D | Grup içinde dolgu + logical-end'de check ikonu | İç ayraç 1px tam genişlik (grup içinde) | Dolgu bir ton | 200ms height+fade (taşan sekme grubu) |
| E | 2px rule altta + etiket 700 (bold işaret) | Rule hiyerarşisi: bölüm 2px, öğe 1px | Metin koyulaşır, tint yok | Yalnız içerik fade 150ms |
| F | Aktif sekme kalıcı yükselti + 1px mavi border | Yok (gerekirse 1px) | Gölge derinleşir | 200ms gölge/renk |

## 8. SegmentedControl

2–5 seçenekli tekli seçim; Tabs'ten farkı içerik değil parametre değiştirmesidir (`radiogroup`
semantiği). Kasa eksen 1'i, aktif segment eksen 5'i izler.

| V | Kasa | Aktif segment |
|---|---|---|
| A | 1px border kasa, 4px; segmentler 1px iç ayraçla | 2px mavi border + start kenarında ince işaret |
| B | Tonal kasa, 8px, border yok | Blue %8 dolgu (dark #93A8F4 %10) + ikon |
| C | Soluk 1px border kasa, 6px | 2px mavi start şeridi + %6 dolgu |
| D | Doğal deseni: dış kasa 1px border + ton, 8px; İÇ segmentler 0px | Dolgu + end'de check ikonu |
| E | Kasa minimum: yalnız 1px rule'larla ayrılmış metin dizisi, 2px | 2px rule üst+alt |
| F | Raised kasa (y=2 blur=8 %10; dark: ton + 1px border), 8px | Kalıcı yükselti + 1px mavi border |

## 9. Toolbar

Tablo/sayfa üstü çubuk: start'ta arama/filtre (SearchField, bkz.
[03-form-varyantlari.md](./03-form-varyantlari.md)), end'de aksiyonlar (logical properties).
Yüksekliği density üçlüsünü izler; varsayılan density eksen 7'den gelir: A compact'a yakın
standard, B comfortable'a yakın standard, C standard, D comfortable, E standard, F standard.
Bulk-actions moduna geçiş davranışı [04-table-varyantlari.md](./04-table-varyantlari.md)'nde tanımlıdır.

| V | Konteyner ayrımı (E1) | Grup ayracı (E6) |
|---|---|---|
| A | Alt kenarda 1px border; zemin canvas ile aynı | Dikey 1px tam yükseklik |
| B | Bir ton farklı zemin bandı; border yok | Yok — boşluk (spacing ölçeği: 8/12/16) |
| C | Soluk 1px alt border | Dikey 1px inset (kısaltılmış) |
| D | Grup yüzeyinin parçası (tablo kasasıyla bütünleşik üst bant) | İç ayraç 1px |
| E | 2px alt rule (bölüm sınırı) | Öğe arası 1px rule |
| F | Kart-içi tablo deseninin üst bandı; kasa gölgesini paylaşır | Yok (gerekirse 1px) |

## 10. SidePanel (Option Gallery + Option Information)

İki bölmeli overlay panel: **sol bölme Option Gallery** (seçenek listesi/galerisi), **sağ bölme
Option Information** (seçili öğenin detayı). Scrim tüm varyantlarda aynıdır: düz yarı saydam
%40 ink/950, BLURSUZ; panel gövdesinde glass/blur yasak. Gallery'de seçim işareti eksen 5'i,
iki bölme arasındaki ayraç eksen 6'yı izler. Kapanış `Esc` + scrim tıklaması; focus trap zorunlu;
açılışta odak panel başlığına gider. 320px'te iki bölme tek sütuna yığılır: önce Gallery,
seçim sonrası Information ileri kaydırılır (geri aksiyonu görünür).

| V | Panel kabuğu (E1/E2) | Bölme ayracı (E6) | Giriş motion'u (E12) |
|---|---|---|---|
| A | 1px border, 4px radius, gölge yok | Dikey 1px tam yükseklik | 120ms fade (slide yok) |
| B | Bir ton açık yüzey (dark 950→900), 8px | Yok — boşluk + ton farkı | 200ms slide+fade |
| C | Soluk 1px border, 6px; seçili gallery öğesi 2px mavi start şeridi | Dikey 1px inset | Fade + şerit 120ms scaleY |
| D | Tek gruplanmış yüzey: dış 1px border + ton, 8px; iç öğeler 0px | İç ayraç 1px tam genişlik | 200ms height/slide+fade |
| E | Kutu minimum; başlık 700 + 2px rule ile yapı | Bölüm 2px, öğe 1px rule | Fade 150ms |
| F | Overlay yükseltisi: y=4 blur=16 %12 (dark: ton + 1px border), 8px | Yok (gerekirse 1px) | 240ms fade+4px rise (reduced-motion: yalnız fade) |

## 11. Menu / Dropdown

Popover listesi: item yüksekliği density üçlüsüne bağlı, min hit 44px; klavye ok/`Home`/`End`/
harf araması headless katmanda tektir. Menu bir "gerekli kutu"dur — E dahil tüm varyantlarda
kapalı yüzey olarak çizilir; glass yasak.

| V | Popover kabuğu (E1/E2) | Item hover (E4) | Item selected (E5) | Bölüm ayracı (E6) |
|---|---|---|---|---|
| A | 1px border, 4px, gölge yok | %4 tint | 2px mavi border + start'ta ince işaret; dolgu yok | 1px tam genişlik |
| B | Bir ton açık yüzey, 8px | Bir ton açılır (150ms) | Blue %8 dolgu (dark #93A8F4 %10) + ikon | Yok — spacing |
| C | Soluk 1px border, 6px | Şerit %40 + %4 tint | 2px mavi start şeridi + %6 dolgu | 1px inset |
| D | Grup yüzeyi: 1px border + ton, dış 8px, item 0px | Dolgu bir ton | Dolgu + end'de check | İç 1px tam genişlik |
| E | 1px border, 2px (zorunlu kutu) | Satır %3 tint | 2px rule veya başta bold işaret | Bölüm 2px, öğe 1px |
| F | Overlay gölge y=4 blur=16 %12 (dark: ton+1px border), 8px | Dolgu (gölge sabit) | 1px mavi border + bir ton dolgu ([01](./01-varyant-cercevesi.md) satır-düzeyi ikame kuralı) | Yok (gerekirse 1px) |

## 12. Toast / Alert

Toast = geçici overlay bildirimi; Alert = satır-içi kalıcı uyarı. Her ikisinde ikon + başlık +
metin zorunlu (asla yalnız renk); semantik renkler §6'daki 600 seti + dark türevleri. Toast'ta
kapatma aksiyonu ve `role="status"`/`role="alert"` ayrımı headless katmandadır. Giriş animasyonu
120–240ms bandında, eksen 12'yi izler; `prefers-reduced-motion`'da yalnız anlık/fade.

| V | Konteyner (E1) | Semantik accent taşıyıcısı (E11) | Giriş (E12) |
|---|---|---|---|
| A | 1px semantik border (durum rengi border'da), canvas zemin | Border + ikon | 120ms fade |
| B | Tonal zemin (durum renginin zayıf dolgusu token'la) | Dolgu + ikon | 150ms zemin/fade |
| C | Soluk 1px border + 2px durum start şeridi (error=kırmızı, success=yeşil, AI=sarı) | Start şeridi + ikon | Şerit 120ms scaleY + fade |
| D | Grup yüzeyi içinde satır (bildirim merkezi deseni), dış 8px | Satır başı ikon + metin | 200ms height+fade |
| E | 2px üst rule + metin; kutu minimum | Rule rengi değil ikon+metin taşır (rule nötr kalır) | Fade 150ms |
| F | Overlay gölge y=4 blur=16 %12 (dark: ton+1px border), 8px | Dolgu + ikon | 240ms fade+4px rise |

## 13. Modal / Drawer

Scrim tüm varyantlarda: %40 ink/950, blursuz, düz yarı saydam. Focus trap, `Esc`, odak iadesi
headless katmanda tektir. Başlık 1rem üstü ölçek 700; footer aksiyonları FormFooter deseniyle
uyumludur (bkz. [03-form-varyantlari.md](./03-form-varyantlari.md)). 320px'te modal tam
genişliğe yaklaşır, drawer tam ekran olur; radius üst sınırı her durumda 8px'tir (12px yalnız
kullanıcının mutlak üst limiti olarak anılır, operasyonel tavan 8px).

| V | Kabuk (E1/E2) | Header/footer ayracı (E6) | Motion (E12) |
|---|---|---|---|
| A | 1px border, 4px, gölge yok | 1px tam genişlik | 120ms fade |
| B | Bir ton açık yüzey, 8px | Yok — spacing | 200ms slide+fade |
| C | Soluk 1px border, 6px; hata diyaloglarında 2px start şeridi | 1px inset | Fade + şerit 120ms |
| D | Gruplanmış yüzey: 1px border + ton, 8px; iç bölümler 0px + iç ayraç | İç 1px tam genişlik | 200ms height+fade |
| E | Minimum kutu, 2px; yapı başlık 700 + rule'larla | Bölüm 2px, öğe 1px | Fade 150ms |
| F | Overlay: y=4 blur=16 %12 (dark: ton + 1px border), 8px | Yok (gerekirse 1px) | 240ms fade+4px rise |

## 14. Pagination

Tablonun alt bandında (bkz. [04-table-varyantlari.md](./04-table-varyantlari.md)); sayı öğeleri
tabular-nums, min 1rem; her öğenin hit area'sı 44×44px. Önce/sonra ok butonları IconButton
kurallarını izler. Aktif sayfa işareti eksen 5, hover eksen 4 kararlarıdır:

| V | Aktif sayfa (E5) | Hover (E4) |
|---|---|---|
| A | 2px mavi border çerçeve, dolgu yok | Border koyulaşır + %4 tint |
| B | Blue %8 dolgu (dark #93A8F4 %10) | Bir ton açılır |
| C | 2px mavi start şeridi + %6 dolgu | Şerit %40 + %4 tint |
| D | Dolgu (grup içi satır deseni) | Dolgu bir ton |
| E | Aktif sayı 700 + 2px alt rule | Metin koyulaşır, tint yok |
| F | Kalıcı yükselti + 1px mavi border | Gölge derinleşir |

## 15. CommandPalette

Glass'ın izinli olduğu TEK bileşen alanı (global chrome ile birlikte, her ikisinde de OPSİYONEL):
palette kabuğunda blur'lu yarı saydamlık kullanılabilir; **opak fallback zorunludur**
(`backdrop-filter` desteklenmiyorsa veya `prefers-reduced-transparency` ise düz yüzey token'ı).
Scrim yine blursuz %40 ink/950'dir — glass yalnız kabukta, asla scrim'de. Sonuç listesi Menu
kurallarını (§11), aktif satır eksen 5'i izler; arama girişi eksen 8 (input muafiyet alanı)
kararını taşır:

| V | Palette girişi (E8) | Kabuk notu |
|---|---|---|
| A | 0px keskin, 1px border; focus'ta 2px ring | 1px border, gölgesiz; glass kullanılsa da çizgisel gramer korunur |
| B | Filled-tonal, 4px, border yok; focus 2px ring + alt kenar 2px mavi | Ton basamaklı kabuk |
| C | 0px keskin; focus'ta start'ta 2px mavi şerit + ring | AI-üretimi sonuç satırında sarı start şeridi (provenance) |
| D | Borderless-inset, 4px; focus inset ring | Palette tek gruplanmış yüzey; sonuçlar 0px iç öğeler |
| E | 1px border, 0px keskin, alt kenar 2px | Bölüm başlıkları 700 + rule ayrımı |
| F | Pill-shape, 1px border; iç padding start/end 20px; focus 2px ring | Overlay gölge y=4 blur=16 %12 |

Storybook matrix story'leri ve axe kapıları [07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md)'nde,
Figma component set üretim prompt'ları [06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md)'nde tanımlıdır;
bu dosya onların bileşen-düzeyi girdisidir.

## 16. 320px ve RTL notları

- 320px: Toolbar aksiyonları overflow menüsüne (Menu, §11) katlanır; Tabs yatay kaydırmalı olur
  (kaydırma ipucu görünür); SidePanel ve Drawer tam genişliğe geçer; Pagination yalnız
  önce/sonra + sayfa göstergesine sadeleşir. CommandPalette tam ekran açılır.
- RTL: start şeritleri (C), selected işaretleri, check ikonları (D, logical-end), Toolbar
  hizalaması ve SidePanel bölme sırası CSS logical properties ile aynalanır; ok ikonları
  yön semantiğine göre çevrilir. Arapça RTL ve Almanca uzama testi her bileşen için zorunludur
  (bkz. [07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) i18n story'leri).

## Kabul kriterleri

- [ ] Primary buton tüm varyantlarda #FFB900 zemin + #080616 metin; hiçbir state'te beyaz metin yok.
- [ ] Dark modda hiçbir bileşende #003399 metin/ince ikon olarak kullanılmıyor; metin-seviyesi mavi yalnız #93A8F4.
- [ ] 12 bileşen grubunun tamamı 6 varyantta Figma component set (variant prop A–F) + kod + matrix story olarak üretildi (P2 kabulü).
- [ ] Tüm etkileşimli öğelerde hit area ≥44×44px; görünür ikonlar 20–24px Phosphor; emoji yok.
- [ ] Badge/Tag/Status dahil hiçbir metin 1rem (16px) altında değil; weight yalnız 400/500/700.
- [ ] Status, Toast, Alert ve Badge'lerde durum ikon + metin ile veriliyor; axe color-contrast tüm state'lerde ≥4.5:1.
- [ ] `focus-visible` her bileşende varyantın eksen 3 stiliyle mevcut; yalnız border rengi değişimiyle yetinilen hiçbir durum yok.
- [ ] Glass/blur yalnız CommandPalette ve global chrome'da, opak fallback ile; tüm scrim'ler blursuz %40 ink/950.
- [ ] Pill yalnız Variant F input'larında ve semantik kapsüllerde (badge, tag, avatar, switch); buton/tab/segment radius'u ≤8px.
- [ ] Tüm motion 120–240ms ease-out bandında; hover'da scale/translate yok; `prefers-reduced-motion` her animasyonlu bileşende doğrulandı.
- [ ] RTL aynalama (start şeritleri, check konumları, bölme sırası) ve 320px davranışları story'lerle kanıtlandı.
- [ ] Kodda hardcode hex yok; tüm renk/radius/spacing değerleri semantic + variant overlay token'larından geliyor (token drift CI yeşil).
