# 02 — Card Varyantları: Anatomi, Kart Türleri ve A–F Spesifikasyonu

Bu dosya, EA Platform çok-varyant planının Card ailesi spesifikasyonudur: kart anatomisi
(container/header/media/body/meta/actions), beş kart türü (metric/KPI, entity, list-item,
form-section, commerce ürün kartı) ve 12 mikro-detay ekseninin her varyantta (A–F) karta
uygulanışı burada tanımlanır. Değişmezler ve eksen tanımları [01-varyant-cercevesi.md](./01-varyant-cercevesi.md)
dosyasından devralınır; buradaki hiçbir karar oradaki hex, px, rem, weight ve süre değerlerini
değiştiremez. Card ailesi P2 fazının ilk ailesidir (bkz. [00-genel-plan.md](./00-genel-plan.md)).

Bağlantılı dosyalar: [00-genel-plan.md](./00-genel-plan.md) ·
[01-varyant-cercevesi.md](./01-varyant-cercevesi.md) ·
[03-form-varyantlari.md](./03-form-varyantlari.md) ·
[04-table-varyantlari.md](./04-table-varyantlari.md) ·
[05-bilesen-varyantlari.md](./05-bilesen-varyantlari.md) ·
[06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ·
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)

## 1. Kart anatomisi

Kart, "bağlamsal kart" felsefesinin taşıyıcısıdır: yalnız gerçekten gruplanması gereken
içerik kart olur; dekoratif kutulama yapılmaz. Tüm bölgeler CSS logical properties ile
kurulur (RTL mirror otomatik), bölge iç boşlukları spacing ölçeğinden (4/8/12/16/24/32/48)
seçilir. Davranış katmanı headless ve tektir; varyant farkları yalnız `data-variant="a..f"`
üzerinden CSS custom property overlay ile gelir.

| Bölge | İçerik | Bağlayıcı kurallar |
|---|---|---|
| container | Kartın dış yüzeyi | Ayrım grameri ve radius varyanttan (eksen 1–2); radius tavanı 8px; glass/blur YASAK |
| header | Başlık, opsiyonel ikon, opsiyonel overflow menü | Başlık min 1rem; weight varyantın label/başlık kararına göre 500 veya 700; ikon Phosphor 20–24px |
| media | Görsel/kapak (yalnız entity ve commerce) | `max-inline-size: 100%`; sabit en-boy oranı; metin bindirme yok |
| body | Ana içerik: değer, açıklama, alanlar, kart-içi tablo | Sayısal değerlerde `font-variant-numeric: tabular-nums`; metin hizası logical start |
| meta | İkincil bilgi: durum, zaman, sahip, etiketler | Durum asla yalnız renkle verilmez (ikon + metin); badge/tag kuralları [05](./05-bilesen-varyantlari.md) |
| actions | Buton/ikon eylemleri | Hit area min 44×44px; hiza logical end; sarı yalnız primary CTA (zemin #FFB900 + metin #080616) |

Bölgelerin tamamı opsiyoneldir; her kart türü bir alt kümeyi kullanır. Kart başlığı her
zaman görünür metindir — yalnız ikonla başlık YASAK.

## 2. Kart türleri

| Tür | Kullandığı bölgeler | Birincil kullanım | Doğal varyant adayı |
|---|---|---|---|
| Metric/KPI kartı | container, header, body, meta | Dashboard sayısal özet; trend + durum | B (EBP shell), E (kritik KPI) |
| Entity kartı | container, header, media?, body, meta, actions | EA/ERX nesne özeti (capability, application, risk) | A, C |
| List-item kartı | container, body, meta, actions | Kart formunda satır; mobilde tablo satırı ikamesi | C (durum şeridi), A |
| Form-section kartı | container, header, body(=form alanları), actions | Kayıt Oluştur/Düzenle ekranında bölüm gruplama | D (settings-group), B |
| Commerce ürün kartı | container, media, header, body, meta, actions | Müşteri-yüzlü ürün/paket vitrini | F |

Tür bazlı bağlayıcı notlar:

- **Metric/KPI**: değer `tabular-nums`; birim ve yön (artış/azalış) ikon + metinle; sayı
  hizası logical end DEĞİL — kart bağlamında logical start serbesttir, tablo içi sayı kuralı
  ([04](./04-table-varyantlari.md)) kartlara taşınmaz. Sarı vurgulu odak metriği yalnız
  E (kritik KPI değeri) ve F (odak metrikleri) accent dozunun izin verdiği yerde.
- **Entity**: media opsiyonel; başlık + kimlik meta'sı zorunlu; actions overflow menüye
  taşabilir (min 44×44 hit area korunur).
- **List-item**: tek kartlık dikey listelerde kullanılır; seçim işareti varyantın eksen 5
  kararını birebir uygular; klavye ile gezinme headless katmandan gelir.
- **Form-section**: içindeki alanlar [03-form-varyantlari.md](./03-form-varyantlari.md)
  spesifikasyonunu uygular; kart yalnız gruplama ve başlık sağlar; tek kolon kuralı geçerlidir.
- **Commerce**: media üstte; fiyatta `tabular-nums`; CTA sarı buton kuralına tabidir;
  hover'da scale/translate YASAK (F'te dahi yalnız gölge derinleşir).

## 3. A–F kart spesifikasyonu (12 eksenin karta uygulanışı)

Her alt bölüm 12 mikro-ekseni [01](./01-varyant-cercevesi.md)'deki sırayla işler. Eksen 8–9
form-section kartındaki alanlara, eksen 10 kart-içi tablo desenine uygulanır. Focus ring
rengi tüm varyantlarda semantik token çiftidir: light **#003399**, dark **#93A8F4**.

### 3.1 Variant A — "Hairline" (çizgisel hassasiyet)

Kart karakteri: en yoğun kart; Analytical Console'da grid yanı entity/list-item kartları.

| # | Eksen | Karta uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Kart = 1px semantik border (#E4E4EE / dark #26224A); yüzey tonu canvas ile AYNI; gölge yok |
| 2 | Radius | sm (4px) — tüm kart türlerinde |
| 3 | Focus-visible | Etkileşimli kartta 2px blue ring (light #003399, dark #93A8F4), offset 2px |
| 4 | Hover | Border rengi koyulaşır + %4 zemin tintı; başka efekt yok |
| 5 | Selected | 2px mavi border + logical-start kenarında ince işaret; dolgu YOK, yalnız border |
| 6 | Divider | Kart içi bölge ayrımı 1px tam genişlik (header/body/actions arası) |
| 7 | Density | Compact'a yakın standard; list-item kartı en sık bu varyantta sıkışır |
| 8 | Input (form-section) | 0px keskin, 1px border; focus'ta 2px ring |
| 9 | Label | Roboto 500, input üstü; kart başlığı da 500 |
| 10 | Kart-içi tablo başlığı | Transparan zemin, 1px alt çizgi (sticky'de 2px'e kalınlaşır), metin 1rem/500 |
| 11 | Accent dozu | Minimum; sarı YALNIZ primary CTA (commerce kartında tek sarı öğe CTA'dır) |
| 12 | Motion | Hover'da animasyon yok; yalnız state geçişi 120ms opacity/color |

### 3.2 Variant B — "Tonal" (ton katmanlı)

Kart karakteri: en yumuşak kart; metric/KPI ve dashboard kartlarının doğal evi.

| # | Eksen | Karta uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Border'sız; kart canvas'tan bir ton ayrılır (dark: 950→900→800 merdiveni; light: 50→beyaz→gölgesiz beyaz+ton) |
| 2 | Radius | lg (8px) |
| 3 | Focus-visible | 2px ring + kart zemini bir ton aydınlanır |
| 4 | Hover | Yüzey bir ton açılır (150ms background-color) |
| 5 | Selected | Blue %8 dolgu (light: #003399 %8, dark: #93A8F4 %10) + seçim ikonu |
| 6 | Divider | YOK — kart içi bölgeleri spacing ayırır (space/4 = 16px öneri) |
| 7 | Density | Comfortable'a yakın standard; metric kartı ferah nefes alır |
| 8 | Input (form-section) | Filled-tonal (zemin bir ton koyu/açık), 4px radius, border yok; focus'ta 2px ring + alt kenar 2px mavi |
| 9 | Label | Roboto 500; dolgu alanının dışında üstte |
| 10 | Kart-içi tablo başlığı | Bir ton farklı zemin bandı, üst köşeler 8px, metin 1rem/500 |
| 11 | Accent dozu | Orta; kartın empty state'inde sarı vurgu ikonu serbest |
| 12 | Motion | 150ms zemin geçişleri; kart/panel girişleri 200ms slide+fade |

### 3.3 Variant C — "Stripe" (kenar-şerit grameri)

Kart karakteri: durum taşıyan kart; EOP exception/workflow list-item kartlarının doğal evi.

| # | Eksen | Karta uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Çok soluk 1px border (%50 opaklıkta border token) + durum bilgisini taşıyan 2px logical-start şeridi (selected=mavi, error=kırmızı, success=yeşil, AI-üretimi=sarı) |
| 2 | Radius | md (6px) |
| 3 | Focus-visible | Start şeridi 2px mavi + 2px ring birlikte |
| 4 | Hover | Şerit %40 opaklıkta belirir + %4 zemin tintı |
| 5 | Selected | 2px mavi start şeridi + zemin %6 mavi dolgu |
| 6 | Divider | 1px, yalnız içerik bölgesinde (inset — start hizasından içeride başlar) |
| 7 | Density | Standard |
| 8 | Input (form-section) | 0px keskin; default'ta 1px border, focus'ta start kenarında 2px mavi şerit + ring |
| 9 | Label | Roboto 500; zorunlu alan metinle işaretlenir ("(zorunlu)"), yalnız yıldız değil |
| 10 | Kart-içi tablo başlığı | Transparan; sıralı kolonda başlık hücresine 2px alt şerit; satır durumları start şeridiyle |
| 11 | Accent dozu | Şerit sistemiyle yüksek ama alan olarak küçük; sarı şerit = AI provenance işareti (agent-üretimi kart içeriği bu şeritle işaretlenir) |
| 12 | Motion | Şerit 120ms genişleyerek belirir (transform scaleY; reduced-motion'da anlık) |

### 3.4 Variant D — "Inset" (gruplanmış yüzey)

Kart karakteri: kartın kendisi bir grup; form-section ve ERX property editor kartlarının doğal evi.

| # | Eksen | Karta uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Dış kart tek büyük gruplanmış yüzey (settings-group deseni): 1px border + bir ton fark; iç öğeler (satırlar/alanlar) border'sız |
| 2 | Radius | Dış konteyner lg (8px); İÇ öğeler 0px (köşeler konteynerden gelir) |
| 3 | Focus-visible | İç öğe zemininde ring İÇERİDE (inset ring 2px), kart dışına taşma yok |
| 4 | Hover | İç satır dolgusu bir ton |
| 5 | Selected | İç satır dolgusu + logical-end'de check ikonu |
| 6 | Divider | İç ayraç 1px, tam genişlik (grubun içinde) |
| 7 | Density | Comfortable |
| 8 | Input (form-section) | Borderless-inset — zemin bir ton koyu/açık, 4px radius; focus'ta inset ring |
| 9 | Label | Roboto 400 + 500 değer hiyerarşisi tersine de kurulabilir (label 400, değer 500) — property editor semantiği |
| 10 | Kart-içi tablo başlığı | Grup zemininin bir ton koyusu bant; tablo dış kasası 8px, hücreler 0px |
| 11 | Accent dozu | Düşük; grup başlıklarında mavi (light #003399 / dark #93A8F4) küçük vurgu |
| 12 | Motion | Grup expand/collapse 200ms height+fade |

### 3.5 Variant E — "Rule" (editoryal çizgi)

Kart karakteri: kart-karşıtı varyant; kutu yalnız gerçekten gerekli yerde, yapıyı tipografi
ve yatay çizgiler kurar. EBM raporlama metric/KPI blokları çoğunlukla "kartsız kart"tır.

| # | Eksen | Karta uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Konteyner kutuları minimum; yapı tipografik hiyerarşi + yatay rule'larla; kart yüzeyi yalnız gerçekten gerekli yerde |
| 2 | Radius | xs (2px) — nadiren görünür çünkü kutu az |
| 3 | Focus-visible | 2px ring + etkileşimli metinde alt çizgi kalınlaşması |
| 4 | Hover | Metin/etiket rengi koyulaşır; zemin tintı YOK (yalnız satır bazlı öğelerde %3 tint) |
| 5 | Selected | Kalın (2px) rule üstte+altta veya başında bold işaret; dolgu minimum |
| 6 | Divider | Rule hiyerarşisi — bölüm arası 2px, öğe arası 1px |
| 7 | Density | Standard |
| 8 | Input (form-section) | 1px border, 0px keskin; alt kenar 2px (görsel ağırlık altta — editoryal his); focus'ta ring |
| 9 | Label | Label 500, bölüm başlığı 700; küçük ölçek KULLANILMAZ, min 1rem korunur |
| 10 | Kart-içi tablo başlığı | Çift rule (üst 2px + alt 1px) arasında; zemin transparan; metin 1rem/700 |
| 11 | Accent dozu | Çok düşük; sarı yalnız primary CTA ve kritik KPI değeri |
| 12 | Motion | Neredeyse yok; yalnız içerik giriş fade 150ms |

### 3.6 Variant F — "Elevated" (ölçülü yükselti)

Kart karakteri: ailenin tek gölgeli kartı; commerce ürün kartının ve müşteri-yüzlü
panellerin doğal evi.

| # | Eksen | Karta uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Flat 2.0 + tek yönlü ölçülü gölge; raised kart: y=2 blur=8 %10 siyah (dark modda gölge yerine bir ton + 1px border kombinasyonu, bkz. §5); overlay: y=4 blur=16 %12 |
| 2 | Radius | lg (8px) |
| 3 | Focus-visible | 2px ring + hafif yükselti (gölge derinleşir) |
| 4 | Hover | Z-lift — gölge derinleşir (y=4 blur=12); SCALE YOK, translate YOK |
| 5 | Selected | Kalıcı yükselti + 1px mavi border |
| 6 | Divider | YOK (kart içi gerekiyorsa 1px) |
| 7 | Density | Standard |
| 8 | Input (form-section) | Pill-shape (tam yuvarlak) — muafiyetin pill ucu; 1px border, focus'ta 2px ring; iç padding start/end 20px |
| 9 | Label | Roboto 500, pill'in DIŞINDA üstte (asla floating/inside değil) |
| 10 | Kart-içi tablo başlığı | Kart-içi tablo deseni; başlık bandı bir ton + tablo kasası kartın parçası (8px) |
| 11 | Accent dozu | Orta-yüksek; sarı CTA + sarı odak metrikleri |
| 12 | Motion | 200ms gölge/renk; kart giriş 240ms fade + 4px rise; reduced-motion'da yalnız fade |

### 3.7 Özet tablo (eksen × varyant, kart perspektifi)

| Eksen | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| 1 Ayrım | 1px border, ton yok | Ton basamağı, border yok | Soluk 1px + 2px start şeridi | Dış 1px+ton, iç border'sız | Kutu minimum, rule | Gölge y=2 blur=8 %10 |
| 2 Radius | 4px | 8px | 6px | Dış 8px / iç 0px | 2px | 8px |
| 3 Focus | 2px ring, offset 2px | Ring + ton aydınlanma | Şerit + ring | Inset ring 2px | Ring + alt çizgi | Ring + yükselti |
| 4 Hover | Border koyulaşır + %4 tint | Bir ton açılır 150ms | Şerit %40 + %4 tint | İç satır bir ton | Metin koyulaşır | Gölge y=4 blur=12 |
| 5 Selected | 2px mavi border + işaret | Blue %8/%10 dolgu + ikon | Mavi şerit + %6 dolgu | Dolgu + check (end) | 2px rule / bold işaret | Yükselti + 1px mavi border |
| 6 Divider | 1px tam genişlik | Yok (spacing) | 1px inset | İç 1px tam | 2px bölüm / 1px öğe | Yok (gerekirse 1px) |
| 7 Density | Compact'a yakın std | Comfortable'a yakın std | Standard | Comfortable | Standard | Standard |
| 8 Input | 0px, 1px border | Filled-tonal 4px | 0px, focus şeridi | Borderless-inset 4px | 0px, alt kenar 2px | Pill, padding 20px |
| 9 Label | 500 üstte | 500 dolgu dışı üstte | 500 + "(zorunlu)" | 400/500 ters kurulabilir | 500; bölüm 700 | 500 pill dışı üstte |
| 10 Tablo başlığı | 1px→2px alt çizgi, 1rem/500 | Ton bandı, 1rem/500 | 2px sıralama şeridi | Ton bandı, kasa 8px | Çift rule, 1rem/700 | Kart-içi ton bandı |
| 11 Accent | Minimum | Orta | Şeritle yüksek/küçük alan | Düşük | Çok düşük | Orta-yüksek |
| 12 Motion | 120ms state | 150ms/200ms | Şerit 120ms scaleY | 200ms expand | Fade 150ms | 200ms / giriş 240ms |

## 4. 320px davranışı (mobile-native first)

Önce 320px tasarlanır; bantlar 320 / 480 / 768 / 1024 / 1440. Kart yerleşimi container-query
önceliklidir: kart, viewport'a değil içine konduğu konteynerin genişliğine tepki verir.

| Konu | Kural |
|---|---|
| Kart genişliği | 320px bandında kart her varyantta tam genişlik (inline-size %100); sayfa gutter'ı space/4 (16px) |
| Yığılma | Tüm kart türleri tek kolon dikey yığılır; grid 480'den itibaren metric/KPI için 2 kolona, 768'den itibaren içerik izin verirse 3 kolona çıkabilir (container query eşiği) |
| Kart arası boşluk | Dikey space/4 (16px); Variant B'de ayrım yalnız boşlukla kurulduğundan space/5 (24px) önerilir |
| Header | Başlık + overflow menü tek satır; başlık kısaltılmaz, sarar (Almanca uzama testi zorunlu) |
| Media | Commerce/entity media üstte, tam genişlik, sabit en-boy oranı |
| Meta | Meta öğeleri satır sonunda alt satıra sarar; durum ikonu + metin birlikte taşınır |
| Actions | Eylemler logical end hizasını korur; sığmazsa alt satıra tam genişlik yığılır; hit area min 44×44px her koşulda |
| Kart-içi tablo | 320px'te kart-içi tablo [04](./04-table-varyantlari.md) mobil stratejilerine devreder (priority columns / row summary+detail); kart içinde yatay scroll son çaredir ve kontrollü olmalıdır |
| Density | 320px'te density varsayılanı varyantın kendi kararıdır; density font küçülterek DEĞİL padding/metadata görünürlüğü ile uygulanır |
| Tipografi | Min 1rem her bantta korunur; 320px'te de 16px altı YASAK |

Varyant notları (320px): A'nın 1px border'ları dar ekranda görsel gürültü yaratmaz (ton
farkı olmadığından); B'de ton basamakları dar ekranda kartları ayırmak için yeterlidir,
ekstra çizgi eklenmez; C'nin start şeridi RTL'de otomatik olarak sağa geçer (logical
property); E 320px'te fiilen "kartsız" akışa yaklaşır — rule'lar tam genişliktir; F'in
gölgesi dar ekranda da y=2 blur=8 %10 kalır, büyütülmez.

## 5. Dark/Light notları

Tema geçişi yalnız semantic token + mode ile yapılır; kart CSS'inde hardcode hex yasaktır.
Metin kontrastı her iki temada, her state'te ≥4.5:1 doğrulanır.

| Varyant | Light | Dark |
|---|---|---|
| A | Border #E4E4EE; yüzey = canvas | Border #26224A; yüzey = #080616 (ton farkı yok) |
| B | 50 (#F7F7FB) → beyaz → gölgesiz beyaz+ton | 950 (#080616) → 900 (#0D0A24) → 800 (#16123A) merdiveni |
| C | Şerit renkleri semantic/600 seti | Semantik şerit renkleri için aydınlatılmış dark türevleri (P0'da üretilir); selected/focus şeridi #93A8F4 |
| D | Dış border #E4E4EE + bir ton fark | Dış border #26224A + merdivenden bir ton fark |
| E | Rule'lar border token'ı | Rule'lar #26224A; metin #FFFFFF %87 / %60 opaklık kuralına tabi |
| F | Gölge: raised y=2 blur=8 %10; hover y=4 blur=12; overlay y=4 blur=16 %12 | Gölge YOK — ikame: yüzey bir ton yukarı (kart 900, hover'da 800) + 1px border #26224A; "yükselti" ton+border ile ifade edilir |

Ek bağlayıcı notlar:

- **F gölge ikamesi (kritik)**: Dark modda gölge algısı zayıftır; F'in raised kartı dark'ta
  gölge yerine **bir ton + 1px border** kombinasyonunu kullanır. Uygulama: kart yüzeyi
  ink/900 (#0D0A24) + border #26224A; hover Z-lift, gölge derinleşmesi yerine bir ton daha
  açılma (ink/800 #16123A) olarak ifade edilir. Selected'daki 1px mavi border dark'ta
  #93A8F4 token'ına döner.
- **Mavi kullanımı**: #003399 dark zeminde metin/ince ikon olarak KULLANILAMAZ; kartlarda
  dark modda yalnız geniş dolgu, border ve yapısal yüzey rolünde görünebilir. Metin-seviyesi
  mavi accent (link, seçili etiket, D'nin grup başlığı vurgusu) dark'ta **blue/300 = #93A8F4**.
- **Seçim dolguları**: B'nin selected dolgusu light'ta #003399 %8, dark'ta #93A8F4 %10;
  C'nin %6 mavi dolgusu dark'ta aynı türev mantığını izler.
- **Sarı sabittir**: #FFB900 her iki temada aynıdır; üstündeki metin her iki temada #080616.
- **Focus ring**: her iki temada görünür olmalı; yalnız border rengi değişimi focus göstergesi
  sayılmaz (bkz. [01](./01-varyant-cercevesi.md) davranış değişmezleri).

## 6. MCP üretim prompt'ları (karta özel örnekler)

Tam katalog [06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ve
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) dosyalarındadır; aşağıdaki
iki örnek Card ailesine özeldir. Kural: Figma'da üretimden önce envanter
(önce get_metadata ile node tespiti, sonra yalnız hedef node'lara scope'lu
get_design_context) alınır ve `use_figma` öncesi /figma-use skill okunur.

Card component set'ini (5 tür × variant prop A–F) Figma'da üretmek için kullanılır; mevcut
Code Connect eşlemesi olan bileşen varsa yeniden yaratılmaz.

```text
Using the existing variable collections (primitive, semantic, density, and the VARIANT
overlay collection with modes a-f), create a "Card" component set with two properties:
type = metric | entity | list-item | form-section | commerce, and variant = A | B | C | D
| E | F. Anatomy slots: container, header, media (entity/commerce only), body, meta,
actions. Bind every fill, stroke, radius, spacing and effect to variables only — no
hardcoded hex. Respect the frozen rules: radius ceiling 8px, min text size 1rem, Roboto
400/500/700 only, yellow #FFB900 surfaces always use #080616 text, no drop shadows except
variant F (raised: y=2 blur=8 10% black; dark mode replaces the shadow with a one-step
surface tone plus a 1px #26224A border). Before creating anything, run get_metadata to
locate existing Card components, then get_design_context scoped to those nodes, and
reuse them if present.
```

Card matrix story'sini (tür × varyant × tema × density) ve kart etkileşim testlerini
üretmek için kullanılır; axe fail-blocking'dir.

```text
Generate Storybook stories for the Card family using the shared decorators (globalTypes:
theme = light | dark, density = comfortable | standard | compact, variant = a-f). Create a
matrix story rendering all 5 card types across the 6 variants for visual regression, plus
play tests: keyboard focus shows the variant's focus-visible treatment, selected state
renders the variant's selection marker, and hover never applies scale or translate. Add
axe checks (fail-blocking: color-contrast, label, focus) in both themes, an ar-RTL story
verifying the variant C start stripe mirrors to the right, and a 320px viewport story
verifying single-column stacking and 44x44px minimum hit areas on card actions.
```

## 7. Yasaklar (Card ailesi)

- Kart yüzeyinde glass/blur — hiçbir varyantta, hiçbir temada.
- Hover'da scale veya translate — F'in Z-lift'i dahil hiçbir varyantta (yalnız gölge/ton değişir).
- Neumorphism (çift yönlü gölge, kabartma) — F'in tek yönlü ölçülü gölgesi tek istisnadır ve o da yalnız F'te.
- 8px üstü radius (12px yalnız mutlak üst limit bağlamında anılır; operasyonel tavan 8px sabit).
- 1rem (16px) altı metin — meta, caption ve KPI birimi dahil.
- Hardcode hex — yalnız semantic token + mode.
- Sarı zemin üstünde beyaz metin; dark zeminde #003399 metin/ince ikon.
- Durumun yalnız renkle iletilmesi (C'nin şeridi tek başına yeterli değildir; ikon + metin şart).
- Floating/inside label — F'in pill input'u dahil her yerde label dışarıda ve üstte.
- Placeholder'a gömülü label; yalnız yıldızla zorunluluk işareti (C'de "(zorunlu)" metni şart).
- Emoji ikon; Phosphor dışı ikon seti (istisnasız SVG).
- Yeni varyant, yeni renk, yeni radius, yeni gölge değeri icadı.
- Dekoratif kart (gruplama gereği olmayan kutulama) — özellikle E'nin ruhuna aykırıdır.
- Kart başına birden fazla sarı primary CTA.

## 8. Kabul kriterleri

- [ ] 5 kart türü × 6 varyant Figma component set'inde tek `Card` seti altında (type + variant property) üretildi; tüm stiller variable'lara bağlı, hardcode hex yok.
- [ ] Her varyantın kart spesifikasyonu 12 mikro-ekseni bu dosyadaki sırayla karşılıyor; eksen kararları [01](./01-varyant-cercevesi.md) matrisiyle birebir aynı.
- [ ] Storybook matrix story: 5 tür × 6 varyant × 2 tema × 3 density render ediliyor; görsel regresyon taban çizgisi alındı.
- [ ] Play testleri yeşil: focus-visible her varyantta görünür; selected işareti varyant tanımına uygun; hover'da scale/translate yok.
- [ ] axe fail-blocking yeşil (color-contrast, label, focus) — her iki temada, tüm kart state'lerinde metin kontrastı ≥4.5:1.
- [ ] Dark modda F kartı gölgesiz: bir ton (ink/900, hover ink/800) + 1px #26224A border ikamesi doğrulandı.
- [ ] Dark modda #003399 hiçbir kartta metin/ince ikon rolünde değil; metin-seviyesi mavi accent #93A8F4.
- [ ] Sarı yüzeyli tüm kart CTA'larında metin #080616; kart başına en fazla bir sarı primary CTA.
- [ ] 320px story: tüm kart türleri tek kolon, tam genişlik; actions hit area min 44×44px; min font 1rem korunuyor.
- [ ] ar-RTL story: C start şeridi ve tüm logical hizalar doğru mirror'lanıyor; de story: uzun başlık sarıyor, kısaltılmıyor.
- [ ] `prefers-reduced-motion` altında C şerit animasyonu anlık, F kart girişi yalnız fade; tüm süreler 120–240ms bandında.
- [ ] Kart-içi tablo desenleri (A, D, F) [04](./04-table-varyantlari.md) başlık kurallarıyla çelişmiyor.
- [ ] Form-section kartındaki alanlar [03](./03-form-varyantlari.md) input muafiyet spesifikasyonunu birebir uyguluyor (A/C/E: 0px, B/D: 4px, F: pill).
- [ ] Yasaklar bölümündeki maddeler lint/review checklist'ine aktarıldı; ihlal PR'ı bloke ediyor.
