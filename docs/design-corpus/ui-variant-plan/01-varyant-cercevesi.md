# 01 — Varyant Çerçevesi: Değişmezler, 12 Mikro-Eksen ve A–F Tanımları

Bu dosya, EA Platform çok-varyant UI planının çekirdek referansıdır: tüm varyantlarda asla
değişmeyen kuralları (tipografi, renk/kontrast, geometri, davranış/kalite), varyantların
yalnızca üzerinde farklılaşabildiği 12 mikro-detay eksenini ve A–F varyantlarının tam
tanımlarını tek yerde toplar. Bileşen ailesi dosyaları (02–05) her kararı buradaki eksen
matrisinden türetir; MCP prompt katalogları (06–07) ve değerlendirme protokolü (08) bu
çerçeveye referans verir. Buradaki hex, px, rem ve süre değerleri bağlayıcıdır.

Bağlantılı dosyalar: [00-genel-plan.md](./00-genel-plan.md) ·
[02-card-varyantlari.md](./02-card-varyantlari.md) ·
[03-form-varyantlari.md](./03-form-varyantlari.md) ·
[04-table-varyantlari.md](./04-table-varyantlari.md) ·
[05-bilesen-varyantlari.md](./05-bilesen-varyantlari.md) ·
[06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ·
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)

## 1. Değişmezler (tüm varyantlarda ortak felsefe)

Ana tasarım dili sabittir: **Flat 2.0 temeli + bağlamsal kartlar (contextual cards)**.
Glass yalnız chrome/overlay katmanında; neumorphism yasaktır. Data table'da zebra yerine
1px ayraç + hover dolgusu tercih edilir; A–F varyantları bu kararların ince-taneli
yorumlarıdır, alternatifleri değil. Aşağıdaki dört alt bölüm hiçbir varyant tarafından
geçersiz kılınamaz.

### 1.1 Tipografi

| Kural | Değer |
|---|---|
| Font ailesi | **Roboto** (Latin); script-bazlı fallback: Noto Sans aileleri |
| Dağıtım | Self-host; CDN yok |
| İzinli weight'ler | **400 / 500 / 700** — 400 altı YASAK |
| Minimum font boyutu | **1rem (16px)** — tablo başlıkları ve caption'lar DAHİL; 16px altı YASAK |
| Sayısal hücreler | `font-variant-numeric: tabular-nums` |

Varyantlar font ailesi, boyut tabanı veya weight seti ekleyemez; yalnız izinli weight'lerin
hangi rolde kullanılacağına (eksen 9 ve 10) karar verir.

### 1.2 Renk ve kontrast matrisi

Palet sabittir; hex değiştirmek ve yeni renk icat etmek yasaktır. Kod ve Figma'da hardcode
hex kullanılmaz — yalnız semantic token + mode.

| Token / Rol | Hex | Not |
|---|---|---|
| Primary (limon sarısı) | **#FFB900** | Sarı zemin üstü metin HER ZAMAN #080616; asla beyaz |
| Sarı üstü metin | **#080616** | Buton/CTA dahil tüm sarı yüzeylerde |
| Secondary accent (Parlement Mavisi) | **#003399** | Dark zeminde metin/ince ikon olarak KULLANILAMAZ |
| blue/300 (dark metin-mavisi türevi) | **#93A8F4** | Dark modda metin-seviyesi mavi accent |
| Dark canvas / ink/950 | **#080616** | Dark yüzey merdiveninin tabanı |
| ink/900 | **#0D0A24** | Dark yüzey basamağı |
| ink/800 | **#16123A** | Dark yüzey basamağı |
| ink/50 (light canvas) | **#F7F7FB** | Light zeminde beyaz #FFFFFF yüzeylerle birlikte |
| Border (light / dark) | **#E4E4EE** / **#26224A** | Semantik border token çifti |
| success/600 | **#15803D** | Dark modda aydınlatılmış türev üretilecek |
| error/600 | **#DC2626** | Dark modda aydınlatılmış türev üretilecek |
| warning/600 | **#B45309** | Dark modda aydınlatılmış türev üretilecek |
| info/600 | **#1D4ED8** | Dark modda aydınlatılmış türev üretilecek |
| Dark'ta beyaz metin | #FFFFFF %87 (primary), %60 (secondary) | Opaklık token'ları |

Kontrast matrisi (WCAG 2.2 AA hedefi: metin ≥4.5:1, her state'te):

| Ön plan | Zemin | Oran | Sonuç | Bağlayıcı kural |
|---|---|---|---|---|
| #FFFFFF | #FFB900 | ≈1.72:1 | FAIL | Sarı üstünde beyaz metin YASAK |
| #080616 | #FFB900 | ≈11.63:1 | AAA | Sarı üstü metin her zaman #080616 |
| #003399 | #080616 | ≈1.85:1 | FAIL | Dark'ta #003399 metin/ince ikon YASAK; yalnız geniş dolgu, border, yapısal yüzey |
| #93A8F4 | #080616 | ≈8.73:1 * | AA/AAA | Dark'ta metin-seviyesi mavi accent = blue/300 |
| #003399 | #FFFFFF | ≈10.86:1 * | AAA | Light'ta mavi metin/ikon serbest |
| #080616 | #F7F7FB | ≈18.75:1 * | AAA | Light canvas üzerinde koyu metin |
| #FFFFFF %87 | #080616 | ≥4.5:1 hedefi | AA | Dark primary metin; CI'da doğrulanır |

İlk üç oran brief'te sabitlenmiştir; `*` işaretli oranlar aynı WCAG bağıl parlaklık
formülüyle hesaplanmış doğrulama değerleridir ve P0 kontrast doğrulamasında CI tarafından
yeniden ölçülür. Durum bilgisi hiçbir zaman yalnız renkle iletilmez (ikon + metin şart).

### 1.3 Geometri

| Kural | Değer |
|---|---|
| Radius ölçeği | xs=2 / sm=4 / md=6 / lg=8 (px) |
| Yüzey/kontrol radius üst sınırı | **8px (0.5rem)** — operasyonel tavan; 12px yalnız "mutlak üst limit" bağlamında anılır, kullanılmaz |
| Input muafiyeti | Input alanları radius kuralından muaftır: varyanta göre 0px (keskin), 4px veya pill-shape |
| Pill kapsamı | Pill yalnız Variant F input'larında ve semantik kapsül bileşenlerde (badge, tag, avatar, switch) |
| Spacing ölçeği | 4 / 8 / 12 / 16 / 24 / 32 / 48 (space/1..7) |
| Dokunma hedefi | Görünür ikon 20–24px; hit area **min 44×44px** |

### 1.4 Davranış ve kalite

| Alan | Değişmez kural |
|---|---|
| Tema | Dark/Light birinci sınıf; semantic token + mode ile; hardcode hex yasak |
| Density | comfortable(52) / standard(44) / compact(36) satır yüksekliği; font küçülterek DEĞİL, padding/metadata/kolon görünürlüğü ile |
| Erişilebilirlik | WCAG 2.2 AA; metin kontrastı her state'te ≥4.5:1; focus-visible her varyantta ZORUNLU (stili varyant belirler; asla yalnız border rengi değişimiyle yetinilmez) |
| Durum iletişimi | Asla yalnız renk; ikon + metin şart |
| Motion | Yalnız işlevsel (state/süreklilik/nedensellik); 120–240ms, ease-out; `prefers-reduced-motion` desteklenir; hover'da scale YOK |
| Responsive | Mobile-native first: önce 320px; bantlar 320 / 480 / 768 / 1024 / 1440; container-query öncelikli |
| i18n | RTL mirror; CSS logical properties (start/end); CLDR/Intl formatlama; Almanca uzama ve Arapça RTL testi zorunlu; label asla placeholder'a gömülmez — her zaman görünür ve üstte |
| İkon | Phosphor (öncelik), SVG; emoji yasak |
| Glass/blur | Form, tablo, veri yüzeyi ve scrim'de YASAK; yalnız global header, command palette, geçici overlay'de opsiyonel (opak fallback şart); scrim = düz yarı saydam %40 ink/950, blursuz |
| Tablo hizalama | Metin → logical start; sayı/para → logical end; actions → logical end |

## 2. Varyant mantığı ve 12 mikro-detay ekseni

[A,B,C,D,E,F] bir A/B testi gibi 2 büyük alternatif DEĞİLDİR; aynı felsefenin (Flat 2.0 +
contextual cards) 6 ince-taneli mikro-stil yorumudur. Her harf, TÜM bileşen ailelerinde
(card, form, table, destekleyici bileşenler) aynı mikro-kararlarla uygulanır: "Variant C
card" ile "Variant C table" aynı gramerin parçasıdır ve tam ekran kompozisyonlar
varyant-içi tutarlıdır. Varyantlar YALNIZ aşağıdaki 12 eksende farklılaşabilir; eksen
dışına çıkan her fark bir çerçeve ihlalidir.

| # | Eksen | Karar alanı |
|---|---|---|
| 1 | Konteyner ayrım grameri | Yüzeyler birbirinden nasıl ayrılır: border / ton / gölge / çizgi / inset / şerit |
| 2 | Radius eşlemesi | Sabit ölçek (xs/sm/md/lg) içinden hangi değerin hangi yüzeye atandığı |
| 3 | Focus-visible stili | Zorunlu focus göstergesinin biçimi (ring, inset ring, şerit, çizgi kalınlaşması) |
| 4 | Hover geri bildirimi | İşaretçi üzerindeyken verilen görsel yanıt |
| 5 | Seçim (selected) işareti | Seçili öğenin kalıcı işaretlenme biçimi |
| 6 | Ayraç (divider) stratejisi | Liste/tablo/grup içi ayırma: çizgi, boşluk, rule hiyerarşisi, inset |
| 7 | Density varsayılanı | Üçlü density ölçeğinde varyantın varsayılan konumu |
| 8 | Input biçimi (muafiyet alanı) | Radius muafiyetinin kullanımı: 0px / 4px / pill + dolgu ve kenar stratejisi |
| 9 | Label ağırlığı/işlenişi | İzinli weight'lerin label/değer hiyerarşisine dağılımı |
| 10 | Tablo başlık hücresi işlenişi | Header hücresinin zemin, çizgi ve tipografi kararı |
| 11 | Accent (sarı/mavi) konuşlandırma dozu | Sarı ve mavinin nerede, ne yoğunlukta kullanıldığı |
| 12 | Motion mikro-davranışı | 120–240ms bandı içinde varyanta özgü izinli geçişler |

## 3. Varyant tanımları [A–F]

Her varyant bölümü 12 ekseni aynı sırayla işler; "Karakter" satırı eksen değil,
değerlendirme (08) için doğal aday notudur.

### 3.1 VARIANT A — "Hairline" (çizgisel hassasiyet)

| # | Eksen | Karar |
|---|---|---|
| 1 | Ayrım | Her konteynerde 1px semantik border; yüzey tonu canvas ile AYNI (ton farkı yok); gölge yok |
| 2 | Radius | sm (4px) tüm yüzey/kontroller |
| 3 | Focus | 2px blue ring (light: #003399, dark: #93A8F4), offset 2px |
| 4 | Hover | Border rengi koyulaşır + %4 zemin tintı |
| 5 | Selected | Border 2px mavi + başlangıç kenarında ince işaret; satırda blue %8 dolgu YOK, yalnız border |
| 6 | Divider | 1px tam genişlik |
| 7 | Density | Compact'a yakın standard |
| 8 | Input | 0px keskin, 1px border; focus'ta 2px ring |
| 9 | Label | Roboto 500, input üstü |
| 10 | Tablo başlığı | Transparan zemin; 1px alt çizgi sticky'de 2px'e kalınlaşır; metin 1rem/500 |
| 11 | Accent dozu | Minimum; sarı YALNIZ primary CTA |
| 12 | Motion | Hover'da animasyon yok; yalnız state geçişi 120ms opacity/color |

Karakter: en yoğun, en "mühendis" his; Analytical Console (EA/EOP/ERX grid) için doğal aday.

### 3.2 VARIANT B — "Tonal" (ton katmanlı)

| # | Eksen | Karar |
|---|---|---|
| 1 | Ayrım | Border'sız; ayrım yüzey tonu basamaklarıyla (dark: 950→900→800; light: 50→beyaz→gölgesiz beyaz+ton) |
| 2 | Radius | lg (8px) |
| 3 | Focus | 2px ring + zemin bir ton aydınlanır |
| 4 | Hover | Yüzey bir ton açılır (150ms background-color) |
| 5 | Selected | Blue %8 dolgu (light: #003399 %8, dark: #93A8F4 %10) + ikon |
| 6 | Divider | Yok — boşluk (spacing) ayırır |
| 7 | Density | Comfortable'a yakın standard |
| 8 | Input | Filled-tonal (zemin bir ton koyu/açık), 4px radius, border yok; focus'ta 2px ring + alt kenar 2px mavi |
| 9 | Label | Roboto 500; dolgu alanının dışında üstte |
| 10 | Tablo başlığı | Bir ton farklı zemin bandı; radius üst köşelerde 8px; metin 1rem/500 |
| 11 | Accent dozu | Orta; boş durumlarda (empty state) sarı vurgu ikonu serbest |
| 12 | Motion | 150ms zemin geçişleri; panel girişleri 200ms slide+fade |

Karakter: en yumuşak, en "ürünleşmiş SaaS" his; dashboard/EBP shell için doğal aday.

### 3.3 VARIANT C — "Stripe" (kenar-şerit grameri)

| # | Eksen | Karar |
|---|---|---|
| 1 | Ayrım | Çok soluk 1px border (%50 opaklıkta border token) + DURUM bilgisini taşıyan 2px logical-start kenar şeridi (selected=mavi, error=kırmızı, success=yeşil, AI-üretimi=sarı) |
| 2 | Radius | md (6px) |
| 3 | Focus | Start şeridi 2px mavi + 2px ring birlikte |
| 4 | Hover | Şerit %40 opaklıkta belirir + %4 zemin tintı |
| 5 | Selected | 2px mavi start şeridi + zemin %6 mavi dolgu |
| 6 | Divider | 1px, yalnız içerik bölgesinde (inset — start hizasından içeride başlar) |
| 7 | Density | Standard |
| 8 | Input | 0px keskin; default'ta 1px border, focus'ta start kenarında 2px mavi şerit + ring |
| 9 | Label | Roboto 500; zorunlu alan işareti metinle ("(zorunlu)"), yalnız yıldız değil |
| 10 | Tablo başlığı | Transparan; sıralı kolonda başlık hücresine 2px alt şerit; satır durumları start şeridiyle |
| 11 | Accent dozu | Şerit sistemiyle yüksek ama alan olarak küçük; sarı şerit = AI provenance işareti |
| 12 | Motion | Şerit 120ms genişleyerek belirir (transform scaleY; reduced-motion'da anlık) |

Karakter: durum-yoğun operasyon ekranları (EOP exception, workflow) için doğal aday.

### 3.4 VARIANT D — "Inset" (gruplanmış yüzey)

| # | Eksen | Karar |
|---|---|---|
| 1 | Ayrım | Dış konteyner tek büyük gruplanmış yüzey (settings-group deseni); iç öğeler border'sız, iç ayraçlarla bölünür; dış konteyner 1px border + bir ton fark |
| 2 | Radius | Dış konteyner lg (8px); İÇ öğeler 0px (grup köşeleri konteynerden gelir) |
| 3 | Focus | İç öğe zemininde ring İÇERİDE (inset ring 2px), taşma yok |
| 4 | Hover | İç satır dolgusu bir ton |
| 5 | Selected | İç satır dolgusu + logical-end'de check ikonu |
| 6 | Divider | İç ayraç 1px, tam genişlik (grubun içinde) |
| 7 | Density | Comfortable |
| 8 | Input | Borderless-inset — zemin bir ton koyu/açık, 4px radius; focus'ta inset ring |
| 9 | Label | Roboto 400 + 500 değer hiyerarşisi tersine de kurulabilir (label 400, değer 500) — ayar/özellik düzenleyici (property editor) semantiği |
| 10 | Tablo başlığı | Grup zemininin bir ton koyusu bant; tablo dış kasası 8px, hücreler 0px |
| 11 | Accent dozu | Düşük; grup başlıklarında mavi (light) / #93A8F4 (dark) küçük vurgu |
| 12 | Motion | Grup expand/collapse 200ms height+fade |

Karakter: konfigürasyon/ayar formları, ERX property editor için doğal aday.

### 3.5 VARIANT E — "Rule" (editoryal çizgi)

| # | Eksen | Karar |
|---|---|---|
| 1 | Ayrım | Konteyner kutuları minimum; yapıyı tipografik hiyerarşi + yatay çizgiler (rule) kurar; kart yalnız gerçekten gerekli yerde |
| 2 | Radius | xs (2px) — nadiren görünür çünkü kutu az |
| 3 | Focus | 2px ring + metin altı çizgi kalınlaşması (link/etkileşimli metinde) |
| 4 | Hover | Metin/etiket rengi koyulaşır + zemin tintı YOK (yalnız satır bazlı öğelerde %3 tint) |
| 5 | Selected | Kalın (2px) rule üstte+altta veya başında bold işaret; dolgu minimum |
| 6 | Divider | Rule hiyerarşisi — bölüm arası 2px, öğe arası 1px |
| 7 | Density | Standard |
| 8 | Input | 1px border, 0px keskin; alt kenar 2px (görsel ağırlık altta — editoryal his); focus'ta ring |
| 9 | Label | Roboto 700 küçük ölçek KULLANILMAZ (min 1rem korunur); label 500, bölüm başlığı 700 |
| 10 | Tablo başlığı | Çift rule (üst 2px + alt 1px) arasında; zemin transparan; metin 1rem/700 |
| 11 | Accent dozu | Çok düşük; sarı yalnız primary CTA ve kritik KPI değeri |
| 12 | Motion | Neredeyse yok; yalnız içerik giriş fade 150ms |

Karakter: EBM raporlama, governance, mimari dokümantasyon ekranları için doğal aday.

### 3.6 VARIANT F — "Elevated" (ölçülü yükselti)

| # | Eksen | Karar |
|---|---|---|
| 1 | Ayrım | Flat 2.0 + tek yönlü ölçülü gölge; raised kart: y=2 blur=8 %10 siyah (dark modda gölge yerine bir ton + 1px border kombinasyonu); overlay: y=4 blur=16 %12 |
| 2 | Radius | lg (8px) |
| 3 | Focus | 2px ring + hafif yükselti (gölge derinleşir) |
| 4 | Hover | Z-lift — gölge derinleşir (y=4 blur=12); SCALE YOK, translate YOK |
| 5 | Selected | Kalıcı yükselti + 1px mavi border |
| 6 | Divider | Yok (kart içi gerekiyorsa 1px) |
| 7 | Density | Standard |
| 8 | Input | **Pill-shape** (tam yuvarlak) — muafiyetin pill ucu bu varyantta; 1px border; focus'ta 2px ring; iç padding start/end 20px |
| 9 | Label | Roboto 500, pill'in DIŞINDA üstte (asla floating/inside değil) |
| 10 | Tablo başlığı | Kart-içi tablo deseni; başlık bandı bir ton + tablo kasası kartın parçası (8px) |
| 11 | Accent dozu | Orta-yüksek; sarı CTA + sarı odak metrikleri |
| 12 | Motion | 200ms gölge/renk; kart giriş 240ms fade+4px rise; reduced-motion'da yalnız fade |

Karakter: commerce yüzeyleri (ürün kartı, hero yakını), müşteri-yüzlü paneller için doğal aday.

Bağlayıcı ikame kuralı (satır düzeyi): F'te yükselti yalnız **kart/kontrol düzeyindedir**
(kart, buton, segmented kasa, sekme, sayfalama öğesi gibi ayrık kontroller yükselti
taşıyabilir). **Satır-düzeyi öğelerde** (tablo satırı, menu/combobox seçeneği, liste
satırı) gölge yasaktır; selected ikamesi = **1px mavi border + bir ton zemin dolgusu**
(hover dolgusuyla aynı ton). 02–05 dosyalarındaki F tanımları bu kurala tabidir.

## 4. Karşılaştırma matrisi (12 eksen × A–F)

Hücreler kısaltılmış özet değerlerdir; bağlayıcı tam metin Bölüm 3'tedir.

| # | Eksen | A "Hairline" | B "Tonal" | C "Stripe" | D "Inset" | E "Rule" | F "Elevated" |
|---|---|---|---|---|---|---|---|
| 1 | Ayrım | 1px border; ton farkı yok; gölge yok | Border'sız; ton basamakları | Soluk 1px border (%50) + 2px start durum şeridi | Dış 1px border + bir ton; iç öğeler border'sız | Kutu minimum; tipografi + rule | Gölge y=2 blur=8 %10 (dark: ton + 1px border) |
| 2 | Radius | sm (4px) | lg (8px) | md (6px) | Dış lg (8px), iç 0px | xs (2px) | lg (8px) |
| 3 | Focus | 2px ring, offset 2px | 2px ring + zemin bir ton | 2px start şeridi + 2px ring | Inset ring 2px | 2px ring + alt çizgi kalınlaşması | 2px ring + gölge derinleşir |
| 4 | Hover | Border koyulaşır + %4 tint | Yüzey bir ton açılır (150ms) | Şerit %40 opaklık + %4 tint | İç satır dolgusu bir ton | Metin koyulaşır; tint yok (satırda %3) | Z-lift: gölge y=4 blur=12 |
| 5 | Selected | 2px mavi border + ince start işareti; dolgu yok | Blue %8 dolgu (dark %10) + ikon | 2px mavi start şeridi + %6 mavi dolgu | Dolgu + end'de check ikonu | 2px rule üst+alt veya bold işaret | Kalıcı yükselti + 1px mavi border |
| 6 | Divider | 1px tam genişlik | Yok — spacing | 1px inset (içerik bölgesi) | İç ayraç 1px tam genişlik | Bölüm 2px, öğe 1px | Yok (gerekirse 1px) |
| 7 | Density | Compact'a yakın standard | Comfortable'a yakın standard | Standard | Comfortable | Standard | Standard |
| 8 | Input | 0px, 1px border | Filled-tonal, 4px, border yok | 0px; focus'ta start şeridi | Borderless-inset, 4px | 0px, 1px border, alt kenar 2px | Pill; padding start/end 20px |
| 9 | Label | 500, üstte | 500, dolgu dışında üstte | 500 + "(zorunlu)" metni | 400/500 ters hiyerarşi mümkün | Label 500, bölüm 700 | 500, pill dışında üstte |
| 10 | Tablo başlığı | Transparan; 1px→2px alt çizgi; 1rem/500 | Ton bandı; üst köşe 8px; 1rem/500 | Transparan; sıralı kolonda 2px alt şerit | Bir ton koyu bant; kasa 8px, hücre 0px | Çift rule (2px+1px); 1rem/700 | Kart-içi bant bir ton; kasa 8px |
| 11 | Accent | Minimum; sarı yalnız CTA | Orta; empty state sarı ikon | Şeritle yüksek/küçük alan; sarı = AI | Düşük; grup başlığında mavi | Çok düşük; CTA + kritik KPI | Orta-yüksek; CTA + odak metrikleri |
| 12 | Motion | Yalnız state 120ms | 150ms zemin; panel 200ms slide+fade | Şerit 120ms scaleY | Expand/collapse 200ms | Yalnız giriş fade 150ms | 200ms gölge; giriş 240ms fade+rise |

## 5. Mühendislik modeli: headless + data-variant + token overlay

İlke: 6 ayrı kod tabanı DEĞİL. Davranış katmanı (klavye, focus yönetimi, seçim, validation,
sanallaştırma) headless ve TEK'tir; varyantlar yalnız görünüm katmanında yaşar. Uygulama üç
mekanizmayla yapılır:

1. **`data-variant="a..f"` attribute'u** — kök veya bölge seviyesinde uygulanır; altındaki
   tüm bileşenler varyant gramerini miras alır.
2. **CSS custom property overlay (variant token seti)** — 12 eksenin her kararı bir token'a
   çözülür; bileşen CSS'i yalnız token tüketir, varyant seçicisi yalnız token değeri atar.
3. **Minimum yapısal prop** — token ile ifade edilemeyen az sayıda yapısal fark (örn. C'nin
   durum şeridi elemanı, D'nin grup kabı) prop/slot ile açılır; davranışa dokunmaz.

Sıralı katman modeli:

| Katman | Sorumluluk | Varyanta duyarlı mı? |
|---|---|---|
| Headless davranış (hook/state machine) | Klavye, focus, seçim, validation, aria | HAYIR — tek implementasyon |
| Semantic token (mode: light/dark) | Renk rolleri, border, metin opaklıkları | HAYIR — tema ile çözülür |
| Density token (comfortable/standard/compact) | 52/44/36 satır yüksekliği, padding | HAYIR — density ile çözülür |
| Variant overlay token (`data-variant`) | 12 eksenin görsel kararları | EVET — tek fark noktası |

Örnek CSS (değerler brief ile birebir; kısaltılmış kesit):

```css
/* Semantic taban — mode ile çözülür; bileşenler yalnız token tüketir. */
:root {
  --ea-color-primary: #FFB900;
  --ea-color-on-primary: #080616;
  --ea-color-focus-ring: #003399;
  --ea-color-border: #E4E4EE;
}
[data-theme="dark"] {
  --ea-color-focus-ring: #93A8F4; /* #003399 dark'ta metin/ince ikon olarak yasak */
  --ea-color-border: #26224A;
}

/* Variant overlay — yalnız eksen kararlarının token karşılıkları. */
[data-variant="a"] {
  --ea-surface-radius: 4px;        /* eksen 2: sm */
  --ea-container-border-w: 1px;    /* eksen 1: hairline */
  --ea-container-shadow: none;
  --ea-input-radius: 0;            /* eksen 8: keskin */
  --ea-divider-inset: 0;           /* eksen 6: tam genişlik */
}
[data-variant="b"] {
  --ea-surface-radius: 8px;        /* eksen 2: lg */
  --ea-container-border-w: 0;      /* eksen 1: ton basamakları */
  --ea-input-radius: 4px;          /* eksen 8: filled-tonal */
}
[data-variant="f"] {
  --ea-surface-radius: 8px;
  --ea-container-shadow: 0 2px 8px rgb(0 0 0 / 10%); /* raised: y=2 blur=8 %10 */
  --ea-input-radius: 9999px;       /* eksen 8: pill muafiyeti yalnız F */
}

/* Bileşen: varyantı bilmez, yalnız token okur. */
.ea-card {
  border-radius: var(--ea-surface-radius);
  border: var(--ea-container-border-w) solid var(--ea-color-border);
  box-shadow: var(--ea-container-shadow, none);
}
.ea-input:focus-visible {
  outline: 2px solid var(--ea-color-focus-ring);
  outline-offset: var(--ea-focus-offset, 2px);
}
```

Storybook tarafında `globalTypes` (theme × density × variant a–f) decorator'ı bu üç boyutu
aynı mekanizmayla sürer; kurulum prompt'ları [07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md)
dosyasındadır. Style Dictionary, aynı token setini Figma Variables ve CSS custom
properties'e tek kaynaktan üretir (P0 kapsamı, bkz. [00-genel-plan.md](./00-genel-plan.md)).

## 6. Figma modeli: tek component set + variant property

Figma'da 6 ayrı kütüphane/kopya YOKTUR. Her bileşen ailesi için **tek component set**
kurulur ve `variant` property'si **A–F** değerlerini alır; state/density gibi ek
property'ler minimumda tutulur. Böylece Figma yapısı, koddaki `data-variant` modelinin
birebir aynasıdır ve Code Connect eşlemesi tek sette kalır (mevcut Code Connect bileşeni
varken yeniden yaratma yasaktır; ayrıntılı kurallar ve prompt'lar
[06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) dosyasındadır).

Variables koleksiyon yapısı:

| Koleksiyon | İçerik | Mode'lar |
|---|---|---|
| primitive | Ham değerler: hex paleti, radius ölçeği (2/4/6/8), spacing (4..48) | Tek mode |
| semantic | Rol token'ları: surface, border, text, accent, state renkleri | light / dark |
| density | Satır yüksekliği ve padding token'ları | comfortable(52) / standard(44) / compact(36) |
| variant-overlay | 12 eksen kararlarının token karşılıkları | a / b / c / d / e / f |

Adlandırma sözleşmesi: Figma component property'si varyantları **büyük harfle (A–F)**
yazar; variant-overlay koleksiyonunun mode adları ve koddaki `data-variant` değeri ise
**küçük harftir (a–f)** — export/senkron araçları bu eşlemeyi birebir korur.

Kural seti: primitive'ler yalnız semantic/variant-overlay tarafından referans alınır;
bileşen stilleri asla primitive'e doğrudan bağlanmaz (kod tarafındaki "hardcode hex yasak"
kuralının Figma karşılığı). Variant-overlay koleksiyonunun mode değiştirilmesi, aynı
frame'in A'dan F'ye dönüşümünü token seviyesinde üretir; 6-lı karşılaştırma canvas'ları
(P3) bu mekanizmayla kurulur.

## 7. Varyant × Domain eşleşme hipotezi

Bağlayıcı DEĞİL; [08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)
protokolünde sınanacak değerlendirme hipotezidir. Kazanan tek varyant olmayabilir; domain
başına varyant ataması meşru sonuçtur.

| Varyant | Doğal aday domain | Gerekçe (eksen imzası) |
|---|---|---|
| A "Hairline" | Analytical Console (EA/EOP grid) | Ton farksız 1px ayrım + compact'a yakın density: maksimum veri yoğunluğu |
| B "Tonal" | EBP shell / dashboard | Border'sız ton katmanları + yumuşak geçişler: ürünleşmiş SaaS hissi |
| C "Stripe" | EOP exception / workflow | Start şeridi durum gramerini taşır; sarı şerit AI provenance işareti |
| D "Inset" | ERX konfigürasyon | Gruplanmış yüzey + property editor label semantiği |
| E "Rule" | EBM raporlama / governance | Editoryal rule hiyerarşisi + minimum accent: okuma odaklı |
| F "Elevated" | Commerce / müşteri-yüzlü paneller | Ölçülü yükselti + pill input + sarı odak metrikleri |

## Kabul kriterleri

- [ ] Değişmezler (Bölüm 1) brief §1 ile birebir aynı: tüm hex, px, rem, weight ve süre değerleri eşleşiyor; yeni renk/radius/varyant icat edilmedi.
- [ ] Kontrast matrisindeki FAIL çiftleri (#FFB900/#FFFFFF, #003399/#080616) hiçbir varyant spesifikasyonunda metin kombinasyonu olarak geçmiyor.
- [ ] A–F tanımlarının her biri 12 mikro-ekseni aynı sırayla, atlamadan işliyor; eksen dışı fark tanımlanmadı.
- [ ] Karşılaştırma matrisi (Bölüm 4) ile tam tanımlar (Bölüm 3) arasında çelişki yok; matris hücreleri kısaltmadır, bağlayıcı metin Bölüm 3'tür.
- [ ] Pill radius yalnız Variant F input'larında ve semantik kapsül bileşenlerde tanımlı; diğer varyantların input biçimi 0px veya 4px.
- [ ] Yüzey/kontrol radius'u hiçbir varyantta 8px operasyonel tavanını aşmıyor; 12px yalnız "mutlak üst limit" bağlamında anıldı.
- [ ] Mühendislik modeli tek headless davranış katmanı + `data-variant` + CSS custom property overlay olarak tanımlı; örnek CSS yalnız token tüketiyor, bileşen seçicilerinde hardcode hex yok.
- [ ] Figma modeli tek component set + `variant` property (A–F) ve dört koleksiyon (primitive/semantic/density/variant-overlay) olarak tanımlı.
- [ ] Varyant × Domain tablosu "bağlayıcı değil, hipotez" ifadesiyle işaretli ve 08 protokolüne bağlanmış.
- [ ] 02–05 dosyalarındaki bileşen spesifikasyonları bu dosyanın eksen kararlarına referans veriyor; çelişki tespit edilirse bu dosya (ve brief) kazanır.
