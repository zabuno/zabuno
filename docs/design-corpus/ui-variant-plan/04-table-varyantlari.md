# 04 — Table Varyantları: Data Table/Grid Ailesi (A–F)

Bu dosya, EA Platform çok-varyant planının data table/grid ailesi spesifikasyonudur: yapı
parçaları ve state seti, hücre tipleri ve hizalama kuralları, table ile data grid ayrımı,
`comparison_priority` kavramı, 12 mikro-detay ekseninin her varyantta (A–F) tabloya
uygulanışı, 4 mobil strateji ve 10k satır sanallaştırma performans hedefleri burada
tanımlanır. Değişmezler ve eksen tanımları [01-varyant-cercevesi.md](./01-varyant-cercevesi.md)
dosyasından devralınır; buradaki hiçbir karar oradaki hex, px, rem, weight ve süre
değerlerini değiştiremez. Tablo ailesi P2 fazında card ve form ailelerinden sonra üretilir
(bkz. [00-genel-plan.md](./00-genel-plan.md)). Bağlayıcı temel karar: data table'da zebra
YOK — satır ayrımı 1px ayraç + hover dolgusu ile kurulur ve varyantlar bunun ince-taneli
yorumlarıdır.

Bağlantılı dosyalar: [00-genel-plan.md](./00-genel-plan.md) ·
[01-varyant-cercevesi.md](./01-varyant-cercevesi.md) ·
[02-card-varyantlari.md](./02-card-varyantlari.md) ·
[03-form-varyantlari.md](./03-form-varyantlari.md) ·
[05-bilesen-varyantlari.md](./05-bilesen-varyantlari.md) ·
[06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ·
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)

## 1. Table vs data grid ayrımı

İki bileşen aynı görsel grameri paylaşır; fark davranış katmanındadır. Davranış headless ve
TEK'tir (TanStack Table); varyant farkları yalnız `data-variant="a..f"` + CSS custom
property overlay ile gelir.

| Boyut | Table | Data grid |
|---|---|---|
| Birincil amaç | Okuma/tarama; veri sunumu | Çalışma yüzeyi; veri üzerinde operasyon |
| Etkileşim | Sıralama, sayfalama, satır linki | + seçim/bulk-actions, inline-edit, tree/expand, kolon pinleme |
| Klavye | Satır bazlı gezinme, link odağı | Hücre bazlı gezinme (grid pattern), Enter=edit, Esc=iptal |
| ARIA | `<table>` semantiği yeterli | `role="grid"` + `aria-sort`, `aria-selected`, `aria-expanded` |
| Sanallaştırma | Opsiyonel (uzun listelerde) | Zorunlu hedef: 10k satır (bkz. §7) |
| Doğal ev | EBM rapor tabloları (E) | Analytical Console EA/EOP/ERX grid (A), EOP exception (C) |

Karar kuralı: kullanıcı hücre içeriğini DEĞİŞTİRİYORSA veya satırları toplu işliyorsa grid;
yalnız okuyorsa table. Aynı ekranda ikisi karışmaz.

## 2. Yapı parçaları ve state seti

| Parça | İçerik | Bağlayıcı kurallar |
|---|---|---|
| toolbar | Arama, filtre, density anahtarı, primary CTA | Filtre alanları [03](./03-form-varyantlari.md) spec'ini uygular; sarı yalnız tek primary CTA (#FFB900 zemin + #080616 metin) |
| header | Kolon başlık hücreleri, select-all checkbox | Metin min 1rem; weight varyanta göre 500 veya 700; sticky davranışı varyant ekseni 10 |
| row | Veri satırı | Yükseklik density'den: 52/44/36; hover/selected varyant eksen 4–5 |
| cell | Hücre tipleri (bkz. §3) | Hiza logical start/end; sayısalda `tabular-nums` |
| bulk-actions | Seçim sayacı + toplu eylem çubuğu | Seçim ≥1 olunca görünür; sayaç metin+sayı ("3 kayıt seçildi"); asla yalnız renkle |
| pagination | Sayfa/aralık kontrolü | [05](./05-bilesen-varyantlari.md) Pagination; sayılar `tabular-nums`; hit area min 44×44px |
| empty | Boş durum | İkon + metin + eylem; sarı vurgu ikonu yalnız B'nin accent dozunda serbest |
| loading (skeleton) | Yükleme iskeleti | Satır yüksekliği gerçek density değeriyle aynı (layout shift yok); animasyon 120–240ms bandında, `prefers-reduced-motion`'da statik |
| error | Hata durumu | error/600 #DC2626 + ikon + metin + yeniden dene eylemi; asla yalnız renk |
| inline-edit | Hücre içi düzenleme | Input biçimi varyant ekseni 8 (muafiyet alanı); validation modeli [03](./03-form-varyantlari.md) |
| tree | Hiyerarşik satırlar | Expand ikonu Phosphor, hit area 44×44; girinti spacing ölçeğinden; `aria-expanded` |

State seti (parça bazında uygulanır; tümü her iki temada AA kontrast ile):

| State | Kapsam | Not |
|---|---|---|
| default / hover / focus-visible | row, cell, header, toolbar öğeleri | Focus ring rengi: light #003399, dark #93A8F4; stil varyanttan |
| selected | row (+ bulk-actions tetiklenir) | İşaret varyant ekseni 5; checkbox durumu + görsel vurgu birlikte |
| sorted (asc/desc) | header hücresi | `aria-sort` zorunlu; ikon + varyant vurgusu (bkz. §5) |
| expanded / collapsed | tree satırı | İkon yönü + `aria-expanded`; motion varyant ekseni 12 |
| editing / invalid | inline-edit hücresi | Değer korunur → açıklama → düzeltme → onay ([03](./03-form-varyantlari.md)) |
| disabled / readonly | row, cell, toolbar | Kontrast disabled'da da okunur kalır; readonly görsel olarak input'tan ayrışır |
| loading / empty / error | tablo gövdesi | Birbirini dışlar; üçü de toolbar'ı korur |
| sticky / pinned | header, ilk kolon | Logical start kolonu pinlenir; RTL'de otomatik mirror |

## 3. Hücre tipleri ve hizalama kuralları

Hizalama değişmezi ([01](./01-varyant-cercevesi.md)): metin → logical start, sayı/para →
logical end, actions → logical end. Tüm hizalar CSS logical properties ile (RTL mirror
otomatik). Formatlama CLDR/Intl ile yapılır; hardcode format yasak.

| Tip | Hiza | Format/tipografi | Notlar |
|---|---|---|---|
| text | logical start | Min 1rem; tek satır kısaltma varsayılan | Tam değere erişim şart (tooltip/detay); Almanca uzama testi zorunlu |
| number | logical end | `tabular-nums`; `Intl.NumberFormat` | Ondalık ayracı locale'den; birim başlıkta, hücrede değil |
| currency | logical end | `tabular-nums`; Intl para formatı | Para birimi kodu/sembolü locale kuralına göre; negatif değer ikon+renkle değil, işaretle |
| date | logical start | `Intl.DateTimeFormat`; `tabular-nums` ile sabit genişlik | Göreli zaman ("2 gün önce") yalnız meta bağlamında; kolonda mutlak tarih |
| status-badge | logical start | Badge kuralları [05](./05-bilesen-varyantlari.md) | İkon + metin ZORUNLU; durum asla yalnız renkle; semantik renkler success/600 #15803D, error/600 #DC2626, warning/600 #B45309, info/600 #1D4ED8 (dark'ta aydınlatılmış türev) |
| user | logical start | Avatar (semantik kapsül, pill serbest) + isim | Yalnız avatar YASAK; isim her zaman görünür metin |
| actions | logical end | IconButton'lar, overflow menü | Görünür ikon 20–24px, hit area min 44×44px; en fazla 2 görünür eylem + overflow |
| checkbox | logical start (ilk kolon) | [03](./03-form-varyantlari.md) Checkbox | Header'da select-all + indeterminate; hit area 44×44; kolon pinlenirse checkbox'la birlikte pinlenir |

## 4. comparison_priority kavramı

`comparison_priority`, bir tablonun satırlar arası KOLON KARŞILAŞTIRMASINA ne kadar bağımlı
olduğunu bildiren semantik metadata'dır: `none / low / medium / high / critical`. Tablo
düzeyinde bildirilir (`data-comparison-priority`), kolon düzeyinde her kolona bir öncelik
sırası eşlik eder (priority columns stratejisinin girdisi). Agent-readable UI vizyonunun
(2030/2035) parçasıdır: ajan, tablonun karşılaştırma sözleşmesini DOM'dan okuyabilir.

| Değer | Anlamı | Tipik örnek |
|---|---|---|
| none | Satırlar bağımsız kayıtlar; karşılaştırma yok | Aktivite/log listesi |
| low | Nadiren 1–2 alan karşılaştırılır | Kayıt listesi (isim + durum) |
| medium | Birkaç kolon düzenli karşılaştırılır | EA capability listesi (durum, sahip, tarih) |
| high | Kolonlar arası karşılaştırma birincil iş | EOP exception grid, ERX kural listesi |
| critical | Karşılaştırma bozulursa ekran anlamsız | Finansal/metrik karşılaştırma matrisi, EBM skor tablosu |

`comparison_priority` üç kararı yönlendirir: (1) mobil strateji seçimi (§6), (2) compact
density'de hangi kolonların düşeceği (§8), (3) table/grid seçiminde sanallaştırma ve kolon
pinleme ihtiyacının erken tespiti.

## 5. A–F tablo spesifikasyonu (12 eksenin tabloya uygulanışı)

Her alt bölüm 12 mikro-ekseni [01](./01-varyant-cercevesi.md)'deki sırayla işler; ardından
tablo-özel notlar beş konuyu bağlar: satır ayrımı, başlık işlenişi, seçim, sticky, sort
göstergesi. Tüm varyantlarda ortak: sort göstergesi Phosphor caret ikonu (asc/desc/unsorted)
+ `aria-sort` — davranış headless katmandan gelir; varyant yalnız sıralı kolonun GÖRSEL
vurgusunu belirler. Focus ring çifti: light #003399, dark #93A8F4.

### 5.1 Variant A — "Hairline" (çizgisel hassasiyet)

Tablo karakteri: en yoğun grid; Analytical Console (EA/EOP/ERX grid) doğal adayı.

| # | Eksen | Tabloya uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Tablo kasası 1px semantik border (#E4E4EE / dark #26224A); yüzey tonu canvas ile AYNI; gölge yok |
| 2 | Radius | sm (4px) — kasa; hücreler köşesiz |
| 3 | Focus-visible | Hücre/satır odağında 2px blue ring, offset 2px |
| 4 | Hover | Satırda %4 zemin tintı; kasa border'ı koyulaşır |
| 5 | Selected | Satırda 2px mavi border + logical-start kenarında ince işaret; blue %8 dolgu YOK, yalnız border |
| 6 | Divider | Satır ayracı 1px tam genişlik |
| 7 | Density | Compact'a yakın standard |
| 8 | Input (inline-edit) | 0px keskin, 1px border; focus'ta 2px ring |
| 9 | Label | Toolbar filtre alanlarında Roboto 500, üstte |
| 10 | Tablo başlığı | Transparan zemin, 1px alt çizgi — sticky'de 2px'e kalınlaşır; metin 1rem/500 |
| 11 | Accent dozu | Minimum; sarı YALNIZ toolbar'daki primary CTA |
| 12 | Motion | Hover'da animasyon yok; yalnız state geçişi 120ms opacity/color |

Tablo-özel notlar: satır ayrımı yalnız 1px çizgi — zebra ve ton yok; sticky başlıkta alt
çizginin 1px→2px kalınlaşması "scrolled" ipucudur; pinned kolon ayrımı da 1px border (ton
farkı olmadığı için çizgi tek araçtır); sıralı kolon vurgusu başlık metninin 500→700'e
çıkmasıdır (izinli weight seti içinde, çizgi grameri sticky kalınlaşmasıyla çakışmasın diye);
seçim yalnız border'la işaretlendiğinden checkbox durumu görsel vurgunun zorunlu eşlikçisidir.

### 5.2 Variant B — "Tonal" (ton katmanlı)

Tablo karakteri: en yumuşak tablo; EBP shell/dashboard içi listelerin doğal adayı.

| # | Eksen | Tabloya uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Border'sız; kasa canvas'tan bir ton ayrılır (dark: 950→900→800; light: 50→beyaz→gölgesiz beyaz+ton) |
| 2 | Radius | lg (8px) — kasa |
| 3 | Focus-visible | 2px ring + satır zemini bir ton aydınlanır |
| 4 | Hover | Satır zemini bir ton açılır (150ms background-color) |
| 5 | Selected | Blue %8 dolgu (light #003399 %8, dark #93A8F4 %10) + seçim ikonu |
| 6 | Divider | YOK — satırları boşluk (satır yüksekliği + padding) ayırır |
| 7 | Density | Comfortable'a yakın standard |
| 8 | Input (inline-edit) | Filled-tonal (zemin bir ton), 4px radius, border yok; focus'ta 2px ring + alt kenar 2px mavi |
| 9 | Label | Roboto 500; dolgu alanının dışında üstte |
| 10 | Tablo başlığı | Bir ton farklı zemin bandı, üst köşeler 8px, metin 1rem/500 |
| 11 | Accent dozu | Orta; empty state'te sarı vurgu ikonu serbest |
| 12 | Motion | 150ms zemin geçişleri; panel girişleri 200ms slide+fade |

Tablo-özel notlar: çizgisiz satır ayrımı yoğun gridlerde tarama hatası riski taşır — bu risk
[08](./08-degerlendirme-protokolu.md) tarama hızı kriterinde ölçülür; sticky başlık ton
bandını korur, ek gölge EKLENMEZ (gölge yalnız F'e aittir); sıralı kolonun başlık hücresi
banttan bir ton daha ayrışır; pinned kolon gövdede bir ton farkla ayrılır; seçim dolgusu +
ikon birlikte (yalnız dolgu yetmez).

### 5.3 Variant C — "Stripe" (kenar-şerit grameri)

Tablo karakteri: durum-yoğun operasyon grid'i; EOP exception/workflow doğal adayı.

| # | Eksen | Tabloya uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Çok soluk 1px kasa border'ı (%50 opaklıkta border token); satır durumları 2px logical-start şeridiyle (selected=mavi, error=kırmızı, success=yeşil, AI-üretimi=sarı) |
| 2 | Radius | md (6px) — kasa |
| 3 | Focus-visible | Start şeridi 2px mavi + 2px ring birlikte |
| 4 | Hover | Şerit %40 opaklıkta belirir + %4 zemin tintı |
| 5 | Selected | 2px mavi start şeridi + satırda %6 mavi dolgu |
| 6 | Divider | Satır ayracı 1px, inset (start hizasından içeride başlar) |
| 7 | Density | Standard |
| 8 | Input (inline-edit) | 0px keskin; default 1px border, focus'ta start kenarında 2px mavi şerit + ring |
| 9 | Label | Roboto 500; zorunlu filtre alanı metinle işaretlenir ("(zorunlu)") |
| 10 | Tablo başlığı | Transparan; sıralı kolonda başlık hücresine 2px alt şerit; satır durumları start şeridiyle |
| 11 | Accent dozu | Şerit sistemiyle yüksek ama alan olarak küçük; sarı şerit = AI provenance (ajan-üretimi satır işareti) |
| 12 | Motion | Şerit 120ms genişleyerek belirir (transform scaleY; reduced-motion'da anlık) |

Tablo-özel notlar: satır şeridi durum kanalıdır ama TEK kanal değildir — status-badge hücresi
(ikon+metin) her durumda eşlik eder; sort göstergesi bu varyantta brief'in kendi kararıdır:
sıralı başlık hücresinde 2px alt şerit; sticky başlık transparan kaldığı için altına scrim
DEĞİL, kasa zemin token'ı uygulanır (içerik başlığın altından görünmez); şerit animasyonu
yalnız state DEĞİŞİMİNDE oynar, sanallaştırılmış scroll'da satır mount'unda OYNAMAZ (§7).

### 5.4 Variant D — "Inset" (gruplanmış yüzey)

Tablo karakteri: gruplanmış/section'lı tablolar, ERX konfigürasyon listeleri; tree table'ın
doğal evi (grup = üst satır).

| # | Eksen | Tabloya uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Dış kasa tek gruplanmış yüzey: 1px border + bir ton fark; satırlar border'sız, iç ayraçlarla |
| 2 | Radius | Dış kasa lg (8px); hücreler ve iç öğeler 0px (köşeler kasadan gelir) |
| 3 | Focus-visible | Satır zemininde ring İÇERİDE (inset ring 2px), kasadan taşma yok |
| 4 | Hover | Satır dolgusu bir ton |
| 5 | Selected | Satır dolgusu + logical-end'de check ikonu |
| 6 | Divider | İç ayraç 1px, tam genişlik (kasanın içinde) |
| 7 | Density | Comfortable |
| 8 | Input (inline-edit) | Borderless-inset — zemin bir ton, 4px radius; focus'ta inset ring |
| 9 | Label | 400/500 hiyerarşisi ters kurulabilir (label 400, değer 500) — property editor semantiği |
| 10 | Tablo başlığı | Grup zemininin bir ton koyusu bant; kasa 8px, hücreler 0px |
| 11 | Accent dozu | Düşük; grup başlıklarında mavi (light #003399 / dark #93A8F4) küçük vurgu |
| 12 | Motion | Grup/tree expand-collapse 200ms height+fade |

Tablo-özel notlar: tree table bu varyantta en doğaldır — grup satırı settings-group başlığı
gibi davranır, alt satırlar iç ayraçla bölünür; sticky başlık bandı ton koyusunu korur; sort
vurgusu sıralı başlık hücresinin bant içinde bir ton daha koyulaşmasıdır; inset focus ring
sanallaştırılmış scroll'da kasa dışına taşma/kırpılma sorunu yaratmaz (ring içeride kaldığı
için) — bu, D'yi grid klavye gezinmesi için teknik olarak rahat kılar.

### 5.5 Variant E — "Rule" (editoryal çizgi)

Tablo karakteri: EBM raporlama ve governance tabloları; kasasız, tipografik tablo.

| # | Eksen | Tabloya uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Kasa kutusu minimum/yok; tabloyu tipografik hiyerarşi + yatay rule'lar kurar |
| 2 | Radius | xs (2px) — nadiren görünür çünkü kutu az |
| 3 | Focus-visible | 2px ring + etkileşimli metinde alt çizgi kalınlaşması |
| 4 | Hover | Metin/etiket koyulaşır; satır bazlı öğe olduğu için %3 zemin tintı serbest |
| 5 | Selected | Kalın (2px) rule üstte+altta veya start'ta bold işaret; dolgu minimum |
| 6 | Divider | Rule hiyerarşisi — bölüm arası 2px, satır arası 1px |
| 7 | Density | Standard |
| 8 | Input (inline-edit) | 1px border, 0px keskin; alt kenar 2px (görsel ağırlık altta); focus'ta ring |
| 9 | Label | Label 500, bölüm başlığı 700; min 1rem her yerde korunur |
| 10 | Tablo başlığı | Çift rule (üst 2px + alt 1px) arasında; zemin transparan; metin 1rem/700 |
| 11 | Accent dozu | Çok düşük; sarı yalnız primary CTA ve kritik KPI değeri |
| 12 | Motion | Neredeyse yok; yalnız içerik giriş fade 150ms |

Tablo-özel notlar: satır ayrımı 1px rule, bölüm (grup) geçişi 2px rule — hiyerarşi çizgi
kalınlığıyla anlatılır; sticky başlıkta çift rule korunur ve transparan zemin kasa token'ıyla
doldurulur (C'deki kuralın aynısı); sort vurgusu sıralı kolonda başlığın alt rule'unun
1px→2px kalınlaşmasıdır; seçim dolgusuz olduğu için bulk-select senaryosunda checkbox +
sayaç bilgisi taşıyıcıdır; bu varyant inline-edit yoğun grid'den çok read-mostly table için
uygundur — grid gerekiyorsa [08](./08-degerlendirme-protokolu.md) domain eşleme turunda not düşülür.

### 5.6 Variant F — "Elevated" (ölçülü yükselti)

Tablo karakteri: kart-içi tablo deseni; commerce/müşteri-yüzlü panellerin listeleri.

| # | Eksen | Tabloya uygulanışı |
|---|---|---|
| 1 | Konteyner ayrımı | Tablo, raised kartın parçasıdır: kart y=2 blur=8 %10 siyah (dark'ta gölge yerine bir ton + 1px border #26224A); overlay y=4 blur=16 %12 |
| 2 | Radius | lg (8px) — kart/kasa |
| 3 | Focus-visible | 2px ring + hafif yükselti (kart gölgesi derinleşir) |
| 4 | Hover | Kart düzeyinde Z-lift (y=4 blur=12); satır hover'ı dolgu ile; SCALE YOK, translate YOK |
| 5 | Selected | Seçili satırda 1px mavi border + bir ton zemin dolgusu (hover dolgusuyla aynı ton — [01](./01-varyant-cercevesi.md) satır-düzeyi ikame kuralı); kalıcı yükselti kart düzeyinde kalır — satır başına gölge YASAK |
| 6 | Divider | Varsayılan yok; kart içi gerekiyorsa satır ayracı 1px |
| 7 | Density | Standard |
| 8 | Input (inline-edit) | Pill-shape; 1px border, focus'ta 2px ring; iç padding start/end 20px; pill yüksekliği satır yüksekliğine sığar, hit area min 44×44px |
| 9 | Label | Roboto 500, pill'in DIŞINDA üstte (asla floating/inside) |
| 10 | Tablo başlığı | Başlık bandı bir ton + tablo kasası kartın parçası (8px) |
| 11 | Accent dozu | Orta-yüksek; sarı CTA + sarı odak metrikleri |
| 12 | Motion | 200ms gölge/renk; kart giriş 240ms fade + 4px rise; reduced-motion'da yalnız fade; SATIR bazında giriş animasyonu yok |

Tablo-özel notlar: gölge YALNIZ kart konteynerine aittir — satır/hücre düzeyinde gölge
performans ve neumorphism riski nedeniyle yasaktır; sticky başlık bandı ton farkını korur,
gölge eklemez; sort vurgusu sıralı başlık hücresinin bant içinde bir ton ayrışmasıdır; dark
modda kart ikamesi [02](./02-card-varyantlari.md) §5 ile birebir aynıdır (ink/900 yüzey +
1px #26224A border; hover'da ink/800).

### 5.7 Tablo-özel karşılaştırma (5 konu × varyant)

| Konu | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| Satır ayrımı | 1px tam genişlik | Yok (boşluk/ton) | 1px inset + durum şeridi | İç ayraç 1px tam | Rule 1px (bölüm 2px) | Yok (gerekirse 1px) |
| Başlık | Transparan, 1px→2px alt çizgi, 1rem/500 | Ton bandı, üst köşe 8px, 1rem/500 | Transparan, sıralıda 2px alt şerit | Ton koyusu bant, kasa 8px | Çift rule, 1rem/700 | Kart-içi ton bandı |
| Seçim | 2px mavi border + start işareti, dolgu YOK | %8/%10 mavi dolgu + ikon | 2px mavi şerit + %6 dolgu | Dolgu + end check | 2px rule / bold işaret | 1px mavi border + bir ton dolgu (satır) |
| Sticky | Alt çizgi 2px'e kalınlaşır | Ton bandı korunur, gölge yok | Zemin kasa token'ıyla dolar | Bant korunur | Çift rule + zemin dolar | Bant korunur, gölge yok |
| Sort vurgusu | Başlık 500→700 | Hücre bir ton ayrışır | 2px alt şerit | Bant içinde bir ton koyu | Alt rule 1px→2px | Bant içinde bir ton |

## 6. Mobil stratejiler ve comparison_priority eşlemesi

Mobile-native first: önce 320px; bantlar 320/480/768/1024/1440; container-query öncelikli.
Dört strateji vardır; seçimi tablonun `comparison_priority` değeri yönlendirir. Ortak
kurallar: min font 1rem her stratejide; hit area min 44×44px; durum her zaman ikon+metin.

| Strateji | Mekanik | Ne korunur / ne feda edilir |
|---|---|---|
| 1. Priority columns | Kolonlar öncelik sırasına göre düşer; 320px'te 2–3 kolon kalır, kalanı satır detayında | Kolon hizası ve karşılaştırma kısmen korunur; ikincil alanlar detaya iner |
| 2. Row summary + detail | Her satır tek özet satıra iner (birincil alan + durum); dokunuşla detay açılır (expand veya panel) | Tarama hızı korunur; kolon karşılaştırması feda edilir |
| 3. Stacked | Satır, alan-etiket çiftleriyle dikey karta dönüşür ([02](./02-card-varyantlari.md) list-item kartına devreder) | Okunabilirlik maksimum; tablo formu tamamen feda edilir |
| 4. Controlled horizontal | Tablo formu korunur; gövde kontrollü yatay scroll alır; ilk kolon (kimlik) pinli, scroll affordance görünür | Karşılaştırma tam korunur; tek elle kullanım maliyeti kabul edilir |

Eşleme (bağlayıcı varsayılan; ekran bazında gerekçeli sapma [08](./08-degerlendirme-protokolu.md)
protokolüne not düşülerek mümkündür):

| comparison_priority | 320–480 varsayılan stratejisi | Gerekçe |
|---|---|---|
| none | 3. Stacked | Karşılaştırma yok; kart formu en okunur çözüm |
| low | 2. Row summary + detail | Tek tek kayıt taraması yeterli; detay istendiğinde açılır |
| medium | 1. Priority columns | Birkaç kolonun hizalı kalması gerekir |
| high | 1. Priority columns + gerekirse 4'e geçiş | Önce öncelikli kolonlar; kullanıcı "tüm kolonlar" isterse kontrollü yatay |
| critical | 4. Controlled horizontal | Kolon hizası bozulamaz; pinli kimlik kolonu + yatay scroll tek meşru çözüm |

Notlar: yatay scroll YALNIZ strateji 4'te ve kontrollü (pinli kolon + affordance + klavye
erişimi) olarak meşrudur — kazara taşma bir strateji değildir; strateji 3'te varyantın kart
grameri [02](./02-card-varyantlari.md)'deki A–F kararlarıyla birebir aynıdır (örn. C'nin
durum şeridi kartta da start kenarındadır); 768 ve üstünde tam tablo formuna dönülür.

## 7. Sanallaştırma ve performans hedefleri

Bağlayıcı hedefler (P2 kabulünün parçası; ölçüm story'leri
[07](./07-storybook-mcp-promptlari.md)):

| Hedef | Değer |
|---|---|
| Satır kapasitesi | 10.000 satır (sanallaştırılmış) |
| Scroll akıcılığı | 60fps |
| Etkileşim maliyeti | Main-thread task <50ms (sort, filtre, seçim, inline-edit açılışı dahil) |
| Araç | TanStack Virtual (TanStack Table üstünde) |

Uygulama kuralları:

- Satır yüksekliği density'den SABİTTİR (52/44/36) — ölçümsüz (fixed-size) sanallaştırma
  mümkün olur; dinamik satır yüksekliği yalnız tree/expand satırlarında ve ölçüm cache'iyle.
- Overscan küçük tutulur; skeleton satırları gerçek satır yüksekliğiyle render edilir
  (scroll pozisyonu ve layout kararlıdır, CLS yok).
- Giriş/mount animasyonu sanallaştırılmış satırlarda OYNATILMAZ: C'nin şerit scaleY'i ve
  F'in kart giriş animasyonu yalnız state değişiminde/kart düzeyinde oynar. Scroll sırasında
  motion yok.
- Satır başına gölge, blur ve filtre yasak (F dahil — gölge kart düzeyinde); bu kural 60fps
  hedefinin ön koşuludur.
- Sticky header + sanallaştırma birlikte test edilir (transparan başlıklı C ve E'de zemin
  dolgusu şart, bkz. §5).
- Sort/filtre hesaplaması memoize edilir; 10k satırda sort main-thread <50ms hedefini
  aşarsa işleme parçalanır (yield) — kullanıcıya loading state ile bildirilir.
- Ölçüm: Storybook 10k satır perf story + Playwright trace ([07](./07-storybook-mcp-promptlari.md),
  [08](./08-degerlendirme-protokolu.md) render maliyeti kriterinin veri kaynağı).

## 8. Density üçlüsünün tabloya uygulanışı

Density font küçülterek DEĞİL, satır yüksekliği/padding ve metadata/kolon görünürlüğü ile
sağlanır. Min font 1rem her density'de korunur.

| Density | Satır yüksekliği | Uygulama |
|---|---|---|
| comfortable | 52px | Padding-block geniş; ikincil metadata satırı (varsa) görünür; tüm kolonlar açık |
| standard | 44px | Varsayılan üretim modu; padding-block orta; satır tek metin satırı |
| compact | 36px | Padding-block dar; ikincil metadata gizlenir; düşük öncelikli kolonlar (comparison_priority kolon sırasına göre) düşer; hit area'lar yine min 44×44px (görünür ikon 20–24px, dokunma alanı taşarak korunur) |

Varyant density varsayılanları (eksen 7): A compact'a yakın standard · B comfortable'a
yakın standard · C standard · D comfortable · E standard · F standard. Kullanıcı density'yi
toolbar'dan değiştirebilir; seçim oturumlar arası kalıcıdır. Padding değerleri spacing
ölçeğinden (4/8/12/16/24/32/48) seçilir ve satır yüksekliği token'ından türetilir; satır
yüksekliği değerleri (52/44/36) bağlayıcıdır, padding'e göre yeniden hesaplanmaz.

## 9. MCP prompt referansları

Tam kataloglar [06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ve
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) dosyalarındadır. Kural:
Figma'da üretimden önce envanter (önce get_metadata ile node tespiti, sonra yalnız hedef
node'lara scope'lu get_design_context), `use_figma` öncesi
/figma-use skill; mevcut Code Connect eşlemesi olan bileşen yeniden yaratılmaz.

Table component set'ini (variant prop A–F + density modları) Figma'da üretmek için
kullanılır.

```text
Using the existing variable collections (primitive, semantic, density, and the VARIANT
overlay collection with modes a-f), create a "DataTable" component set with properties:
variant = A | B | C | D | E | F and density = comfortable | standard | compact (row
heights 52 / 44 / 36 px bound to density variables). Anatomy: toolbar, header, row, cells
(text, number, currency, date, status-badge, user, actions, checkbox), bulk-actions bar,
pagination, plus empty / loading-skeleton / error states and a tree-row example. Bind all
fills, strokes, radii and spacing to variables only. Respect the frozen rules: no zebra
striping (row separation is a 1px divider plus hover fill, interpreted per variant), radius
ceiling 8px, header text minimum 1rem at weight 500 (700 for variant E), tabular-nums on
numeric cells, text columns aligned to logical start and number/currency/actions to logical
end, selection column at logical start with a select-all checkbox. Variant specifics: A
transparent header with a 1px bottom line thickening to 2px when sticky; B tonal header
band with 8px top corners and no row dividers; C a 2px bottom stripe on the sorted header
cell and 2px logical-start status stripes on rows (selected=blue, error=red, success=green,
AI-generated=yellow); D an outer 8px container with 0px inner cells and a one-step darker
header band; E a double rule header (2px top, 1px bottom) at 1rem/700; F the table as part
of a raised card (y=2 blur=8 10% black; dark mode: one-step surface tone plus 1px #26224A
border instead of the shadow). Before creating anything, run get_metadata to locate
existing table components, then get_design_context scoped to those nodes, and reuse
them if present.
```

Table matrix + etkileşim + perf story'lerini üretmek için kullanılır; axe fail-blocking'dir.

```text
Generate Storybook stories for the DataTable family using the shared decorators
(globalTypes: theme = light | dark, density = comfortable | standard | compact, variant =
a-f). Create: (1) a matrix story rendering header, rows with all 8 cell types, bulk-actions,
pagination, empty, loading-skeleton, error and tree states across the 6 variants for visual
regression; (2) play tests for keyboard grid navigation, aria-sort toggling with the
variant's sorted-column emphasis, bulk select via the select-all checkbox with an
indeterminate state, and inline-edit (Enter to edit, Esc to cancel, invalid value is
preserved and explained); (3) axe checks (fail-blocking: color-contrast, label, focus) in
both themes; (4) an ar-RTL story verifying logical start/end alignment mirroring including
the variant C status stripe and the pinned identity column; (5) a 10k-row virtualization
perf story using TanStack Virtual with fixed row heights from the density tokens (52 / 44 /
36), asserting 60fps scrolling and main-thread tasks under 50ms for sort, filter and
selection, with no mount animations on virtualized rows; (6) a 320px story per
comparison_priority value (none=stacked, low=row summary+detail, medium=priority columns,
high=priority columns, critical=controlled horizontal with a pinned first column).
```

## 10. Yasaklar (table ailesi)

- Zebra striping — hiçbir varyantta; satır ayrımı 1px ayraç + hover dolgusunun varyant yorumudur.
- Tablo/veri yüzeyinde glass/blur — hiçbir varyantta, hiçbir temada.
- Hover'da scale/translate — satır, hücre ve F'in kart Z-lift'i dahil (yalnız gölge/ton değişir).
- Satır veya hücre düzeyinde gölge — F dahil (gölge yalnız kart konteynerinde).
- 1rem (16px) altı metin — başlık hücreleri, badge ve pagination sayıları DAHİL.
- Hardcode hex — yalnız semantic token + mode; density değerleri de token'dan.
- Sarı zemin üstünde beyaz metin; dark zeminde #003399 metin/ince ikon (metin-seviyesi mavi dark'ta #93A8F4).
- Durumun yalnız renkle/şeritle iletilmesi — status-badge her zaman ikon + metin.
- Sıralamanın yalnız renkle gösterilmesi — Phosphor caret ikonu + `aria-sort` zorunlu.
- Kontrolsüz yatay taşma — yatay scroll yalnız strateji 4'te, pinli kolon + affordance ile.
- Density'de font küçültme; 36px compact satırda 44×44 hit area'nın ihlali.
- Sanallaştırılmış satırlarda mount animasyonu; scroll sırasında herhangi bir motion.
- Floating/inside label (F'in pill inline-edit input'u dahil); placeholder'a gömülü label.
- Yeni varyant, yeni renk, yeni radius, yeni satır yüksekliği icadı.

## 11. Kabul kriterleri

- [ ] DataTable component set'i Figma'da tek set altında (variant A–F + density property) üretildi; tüm stiller variable'lara bağlı, hardcode hex yok.
- [ ] Her varyantın tablo spesifikasyonu 12 mikro-ekseni bu dosyadaki sırayla karşılıyor; eksen kararları [01](./01-varyant-cercevesi.md) matrisiyle birebir aynı.
- [ ] 8 hücre tipi hizalama değişmezine uyuyor: metin/tarih/status/user/checkbox logical start, number/currency/actions logical end; sayısal hücrelerde `tabular-nums` doğrulandı.
- [ ] Hiçbir varyantta zebra yok; satır ayrımı varyantın divider/ton/şerit/rule kararıyla kurulmuş.
- [ ] Sticky başlık 6 varyantta da test edildi: A'da alt çizgi 2px'e kalınlaşıyor; C ve E'de transparan zemin kasa token'ıyla doluyor; hiçbir varyantta sticky gölgesi yok (F'te bant korunuyor).
- [ ] Sort: `aria-sort` + Phosphor caret her varyantta; sıralı kolon vurgusu §5.7 tablosuyla birebir (C'de 2px alt şerit, E'de alt rule 1px→2px).
- [ ] Seçim: select-all + indeterminate çalışıyor; bulk-actions çubuğu seçim sayacını metinle veriyor; A'nın dolgusuz border seçimi ve B/C'nin dolgu oranları (%8/%10, %6) tanıma uygun.
- [ ] Inline-edit her varyantta eksen 8 input biçimini uyguluyor (A/C/E 0px, B 4px filled-tonal, D borderless-inset 4px, F pill + 20px padding); validation modeli [03](./03-form-varyantlari.md) ile aynı.
- [ ] Density üçlüsü satır yüksekliğinden uygulanıyor (52/44/36); compact'ta font küçülmüyor, metadata/kolon görünürlüğü düşüyor; hit area her density'de min 44×44px.
- [ ] 10k satır perf story yeşil: 60fps scroll, main-thread task <50ms (sort/filtre/seçim), sanallaştırılmış satırlarda mount animasyonu yok, skeleton yüksekliği gerçek satırla aynı.
- [ ] 4 mobil strateji 320px story'lerinde comparison_priority eşlemesine göre çalışıyor; strateji 4'te ilk kolon pinli ve scroll affordance görünür.
- [ ] ar-RTL story: tüm logical hizalar, C start şeridi ve pinli kolon doğru mirror'lanıyor; de story: uzun başlık ve hücre metni kısaltma kuralına uyuyor, tam değere erişim var.
- [ ] axe fail-blocking yeşil (color-contrast, label, focus) — her iki temada, tüm tablo state'lerinde metin kontrastı ≥4.5:1; durum hücreleri ikon + metin taşıyor.
- [ ] `prefers-reduced-motion` altında C şeridi anlık, F kart girişi yalnız fade; tüm süreler 120–240ms bandında.
- [ ] Dark modda F tablosu gölgesiz (ink/900 + 1px #26224A ikamesi); dark'ta #003399 hiçbir hücrede metin/ince ikon rolünde değil.
- [ ] Yasaklar bölümü lint/review checklist'ine aktarıldı; ihlal PR'ı bloke ediyor.
