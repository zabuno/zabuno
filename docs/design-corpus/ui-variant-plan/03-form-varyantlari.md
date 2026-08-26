# 03 — Form Varyantları (A–F)

Bu dosya, EA Platform form ailesinin (TextField, TextArea, Select/Combobox, Checkbox/Radio/Switch,
DateField, SearchField, FileUpload, FormSection, FormFooter, ErrorSummary) altı mikro-stil varyantı
[A–F] altındaki spesifikasyonunu tanımlar. Ortak state seti, validation davranış modeli, tek kolon
kuralı, 320px davranışı ve RTL kuralları tüm varyantlar için bağlayıcıdır; varyantlar yalnız
[01-varyant-cercevesi.md](./01-varyant-cercevesi.md)'ndeki 12 mikro-eksende farklılaşır.

Bağlantılı dosyalar: [00-genel-plan.md](./00-genel-plan.md) ·
[01-varyant-cercevesi.md](./01-varyant-cercevesi.md) ·
[02-card-varyantlari.md](./02-card-varyantlari.md) ·
[04-table-varyantlari.md](./04-table-varyantlari.md) ·
[05-bilesen-varyantlari.md](./05-bilesen-varyantlari.md) ·
[06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ·
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](./08-degerlendirme-protokolu.md)

## 1. Kapsam ve bileşen envanteri

Davranış katmanı headless ve TEK'tir; varyantlar `data-variant="a..f"` + CSS custom property
overlay ile uygulanır. Aşağıdaki envanter, P2 fazında (Hafta 3–7, card'dan sonra ikinci aile)
Figma component set (variant prop A–F) + kod + story olarak üretilir.

| Bileşen | Rol | Varyanta duyarlı yüzeyler |
|---|---|---|
| TextField | Tek satır metin/sayı girişi | Input kabuğu (muafiyet alanı), focus, error işareti |
| TextArea | Çok satır metin; dikey resize | Input kabuğu, satır yüksekliği, karakter sayacı |
| Select / Combobox | Tekli/çoklu seçim + arama | Tetik kabuğu, popover listesi, selected işareti |
| Checkbox / Radio / Switch | İkili ve tekli seçim kontrolleri | Kontrol geometrisi, selected işareti, focus |
| DateField | Tarih/aralık girişi (CLDR/Intl format) | Input kabuğu, takvim popover'ı |
| SearchField | Filtre/arama girişi (toolbar bağlamı dahil) | Input kabuğu, ikon konumu, temizleme aksiyonu |
| FileUpload | Dosya seçimi + sürükle-bırak + dosya listesi | Drop bölgesi, liste ayraçları, ilerleme durumu |
| FormSection | Alan grubu: başlık + açıklama + alanlar | Konteyner ayrım grameri, divider, başlık işlenişi |
| FormFooter | Aksiyon çubuğu (primary/secondary/iptal) | Ayraç, hizalama, sticky davranışı |
| ErrorSummary | Submit sonrası hata özeti (odak hedefi) | Konteyner ayrımı, error accent konuşlandırması |

Ortak değişmezler (bkz. brief §1): label her zaman görünür ve input'un üstünde, asla placeholder'a
gömülmez; minimum font 1rem (16px); izinli weight'ler 400/500/700; ikonlar Phosphor (görünür
20–24px), hit area min 44×44px; form yüzeylerinde glass/blur YASAK; hardcode hex yasak, yalnız
semantic token. Sayısal alanlarda `font-variant-numeric: tabular-nums`.

Kontrol yükseklikleri density üçlüsünü izler: comfortable=52 / standard=44 / compact=36 (px).
Density, font küçülterek DEĞİL padding ile sağlanır. Compact'ta görünür kontrol 36px olsa da
dokunma hit area'sı 44×44px'e tamamlanır.

## 2. Ortak state seti

Sekiz state tüm bileşenlerde ve tüm varyantlarda aynı anlamı taşır; yalnız görsel işleniş
varyantın eksen kararlarına göre değişir.

| State | Ortak davranış (varyanttan bağımsız) | Varyanta bırakılan |
|---|---|---|
| default | Label üstte görünür; placeholder yalnız örnek/format ipucu | Kabuk biçimi (eksen 8), border/ton |
| hover | İmleç geri bildirimi; scale YOK | Eksen 4 kararı |
| focus | `focus-visible` ZORUNLU; asla yalnız border rengi değişimi | Eksen 3 kararı |
| filled | Değer 1rem/400; sayıda tabular-nums; değer asla otomatik silinmez | Kabuk dolgu tonu |
| error | İkon + tam metin açıklama ZORUNLU (asla yalnız renk/border); error/600 #DC2626 (dark'ta aydınlatılmış türev token) | Hata işaretinin taşıyıcısı (border/şerit/alt kenar) |
| disabled | Etkileşim ve focus kapalı; label ve değer görünür kalır; formdan submit edilmez | Soluklaştırma tonu (token) |
| readonly | Değer seçilebilir/kopyalanabilir; giriş affordance'ı kaldırılır; klavye ile odaklanabilir | Kabuk sadeleştirme derecesi |
| loading | Alan iskeleti veya satır içi spinner (Phosphor); değer alanı yer korur; 120–240ms, `prefers-reduced-motion` desteklenir | Geçiş süresi (eksen 12 aralığında) |

Error state hariç tüm state'lerde metin kontrastı ≥4.5:1 (WCAG 2.2 AA) korunur; disabled
kontrast muafiyeti yalnız disabled içeriğe uygulanır, disabled label'a değil.

## 3. Validation davranış modeli

Model dört adımlıdır ve tüm varyantlarda aynıdır:

1. **Değer korunur.** Hatalı submit veya blur sonrası kullanıcının girdiği değer ASLA silinmez
   ve maskelenmez; kullanıcı yazdığını görerek düzeltir.
2. **Tam açıklama.** Hata mesajı neyin yanlış olduğunu VE nasıl düzeltileceğini söyler
   ("Geçersiz" yeterli değildir). Mesaj alanın altında, error ikonu + metin olarak görünür;
   `aria-describedby` ile alana bağlanır.
3. **Düzeltme.** Kullanıcı düzeltmeye başladığında hata, alan geçerli hale gelir gelmez kalkar
   (agresif re-validation yok: hata düzeltilmeden yeni hata mesajı fırlatılmaz).
4. **Onay.** Düzeltilen alan success işareti alabilir (ikon + kısa metin; success/600 #15803D,
   dark'ta aydınlatılmış türev); başarılı submit her zaman açık geri bildirim üretir.

**ErrorSummary + inline error eşleşmesi:** Submit'te birden fazla hata varsa form başına
ErrorSummary yerleştirilir, klavye odağı ona taşınır ve her madde ilgili alana anchor link'tir.
Özetteki metin ile alanın inline mesajı birebir aynıdır (1:1 eşleşme); özette olup alanda
olmayan (veya tersi) hata bulunamaz.

**Asla yalnız kırmızı border:** Durum bilgisi asla yalnız renkle iletilmez. Error state en az
üç kanalla verilir: (1) varyanta özgü kabuk işareti (border/şerit/alt kenar), (2) Phosphor
error ikonu, (3) metin mesajı. Renk körü ve yüksek kontrast senaryolarında ikon+metin tek
başına yeterli olmalıdır.

## 4. Tek kolon kuralı ve istisnalar

- **Kural:** Formlar varsayılan olarak TEK kolondur. Göz akışı yukarıdan aşağıya tek hattır;
  z-desen tarama form doldurma hızını düşürür.
- **İki-kolon istisnası (yalnız ≥768px):** Ancak anlamsal olarak TEK birim oluşturan, kısa ve
  sabit uzunluklu alan çiftleri aynı satırı paylaşabilir: gün/ay/yıl segmentleri, min–maks
  aralığı (DateField range dahil), posta kodu + şehir, ülke kodu + telefon. Bağımsız iki soru
  asla yan yana konmaz.
- İstisna satırları container-query ile yönetilir; kap 768px altına düştüğünde çift otomatik
  tek kolona kırılır. Label'lar istisna satırında da alanların ÜSTÜNDE kalır (yana alınmaz).
- FormSection içi alan aralıkları spacing ölçeğinden seçilir (alanlar arası 16 veya 24,
  bölümler arası 32 veya 48); varyantlar bu ritmi değiştirmez.

## 5. A–F spesifikasyonları

Her varyant bölümü 12 mikro-ekseni brief'teki sırayla işler. Eksen 10 (tablo başlık hücresi)
form ailesinde FileUpload dosya listesi ve form içine gömülü veri listelerinde uygulanır; tam
tablo spesifikasyonu [04-table-varyantlari.md](./04-table-varyantlari.md)'dedir.

### Variant A — "Hairline" (çizgisel hassasiyet)

| # | Eksen | Form ailesindeki karar |
|---|---|---|
| 1 | Konteyner ayrımı | FormSection 1px semantik border; yüzey tonu canvas ile AYNI; gölge yok |
| 2 | Radius | Yüzey/kontroller sm (4px); Checkbox varyant radius'unu izler, Radio daire, Switch pill (kapsül muafiyeti) |
| 3 | Focus | 2px blue ring (light #003399, dark #93A8F4), offset 2px — tüm kontrollerde |
| 4 | Hover | Kabuk border'ı koyulaşır + %4 zemin tintı |
| 5 | Selected | Checkbox/Radio işaretli: border 2px mavi; seçili Combobox opsiyonu yalnız border + başlangıç kenarında ince işaret, dolgu yok |
| 6 | Divider | FormSection'lar arası 1px tam genişlik; FileUpload listesi 1px |
| 7 | Density | Compact'a yakın standard (kontrol 44px, sıkı dikey ritim) |
| 8 | Input biçimi | **0px keskin**, 1px border; focus'ta 2px ring; error'da border error/600 + ikon + mesaj |
| 9 | Label | Roboto 500, input üstü |
| 10 | Başlık hücresi | FileUpload liste başlığı: transparan zemin, 1px alt çizgi, 1rem/500 |
| 11 | Accent dozu | Minimum; sarı (#FFB900 + metin #080616) YALNIZ FormFooter primary CTA |
| 12 | Motion | Hover'da animasyon yok; state geçişi 120ms opacity/color |

Form notu: en yoğun veri-giriş hissi; SearchField toolbar içinde çerçevesiz durabilir ama focus
ring'i korunur. ErrorSummary: 1px error/600 border, transparan zemin, ikon + başlık + madde listesi.

### Variant B — "Tonal" (ton katmanlı)

| # | Eksen | Form ailesindeki karar |
|---|---|---|
| 1 | Konteyner ayrımı | Border'sız; FormSection yüzey tonu basamağıyla ayrılır (dark 950→900→800; light 50→beyaz) |
| 2 | Radius | lg (8px) — FormSection ve popover'lar |
| 3 | Focus | 2px ring + input zemini bir ton aydınlanır |
| 4 | Hover | Kabuk zemini bir ton açılır (150ms background-color) |
| 5 | Selected | Combobox opsiyonu blue %8 dolgu (light #003399 %8, dark #93A8F4 %10) + ikon; Checkbox işaretli dolgu mavi |
| 6 | Divider | Yok — bölümleri spacing ayırır (32/48) |
| 7 | Density | Comfortable'a yakın standard (kontrol 44–52px, geniş nefes) |
| 8 | Input biçimi | **Filled-tonal**: zemin bir ton koyu/açık, **4px radius**, border yok; focus'ta 2px ring + alt kenar 2px mavi; error'da alt kenar 2px error/600 + ikon + mesaj |
| 9 | Label | Roboto 500; dolgu alanının DIŞINDA üstte |
| 10 | Başlık hücresi | FileUpload liste başlığı bir ton farklı zemin bandı, üst köşeler 8px, 1rem/500 |
| 11 | Accent dozu | Orta; FileUpload boş durumunda (empty state) sarı vurgu ikonu serbest |
| 12 | Motion | 150ms zemin geçişleri; Select/Date popover girişi 200ms slide+fade |

Form notu: en yumuşak form hissi; filled kabuk ile canvas arasındaki ton farkı her iki modda AA
metin kontrastını bozmamalıdır (kontrast matrisi P0 çıktısıyla doğrulanır). ErrorSummary:
border'sız, bir ton farklı zemin + error ikonu + mesaj listesi.

### Variant C — "Stripe" (kenar-şerit grameri)

| # | Eksen | Form ailesindeki karar |
|---|---|---|
| 1 | Konteyner ayrımı | Çok soluk 1px border (%50 opaklıkta border token) + durum taşıyan 2px logical-start şeridi (selected=mavi, error=kırmızı, success=yeşil, AI-üretimi=sarı) |
| 2 | Radius | md (6px) — FormSection, popover |
| 3 | Focus | Input start şeridi 2px mavi + 2px ring birlikte |
| 4 | Hover | Şerit %40 opaklıkta belirir + %4 zemin tintı |
| 5 | Selected | Combobox opsiyonu 2px mavi start şeridi + zemin %6 mavi dolgu |
| 6 | Divider | 1px, yalnız içerik bölgesinde (inset — start hizasından içeride) |
| 7 | Density | Standard (44px) |
| 8 | Input biçimi | **0px keskin**; default 1px border, focus'ta start kenarında 2px mavi şerit + ring; error'da start şeridi error/600 + ikon + mesaj; AI ile doldurulan alanda sarı şerit (provenance) |
| 9 | Label | Roboto 500; zorunlu alan METİNLE işaretlenir: "(zorunlu)" — yalnız yıldız değil |
| 10 | Başlık hücresi | FileUpload listesi: transparan başlık; satır durumları (yükleniyor/hata/tamam) start şeridiyle |
| 11 | Accent dozu | Şerit sistemiyle yüksek ama alan olarak küçük; sarı şerit = AI provenance işareti |
| 12 | Motion | Şerit 120ms genişleyerek belirir (transform scaleY; reduced-motion'da anlık) |

Form notu: state-yoğun formların (EOP exception/workflow) doğal adayı; error/success/AI durumları
şerit dilinde tek gramerle okunur. ErrorSummary: 2px kırmızı start şeridi + soluk 1px border —
şerit grameri form ile aynı. Şerit RTL'de logical-start'ı izler (bkz. §8).

### Variant D — "Inset" (gruplanmış yüzey)

| # | Eksen | Form ailesindeki karar |
|---|---|---|
| 1 | Konteyner ayrımı | FormSection = tek büyük gruplanmış yüzey (settings-group deseni): dış 1px border + bir ton fark; iç alanlar border'sız |
| 2 | Radius | Dış konteyner lg (8px); iç öğeler 0px (köşeler konteynerden gelir) |
| 3 | Focus | Ring İÇERİDE (inset ring 2px), gruptan taşma yok |
| 4 | Hover | İç satır dolgusu bir ton |
| 5 | Selected | İç satır dolgusu + logical-end'de check ikonu (Combobox ve seçim listeleri) |
| 6 | Divider | İç ayraç 1px, tam genişlik (grubun içinde alanları böler) |
| 7 | Density | Comfortable (52px) |
| 8 | Input biçimi | **Borderless-inset**: zemin bir ton koyu/açık, **4px radius**; focus'ta inset ring; error'da inset ring error/600 + ikon + mesaj |
| 9 | Label | Roboto 400 + 500 değer hiyerarşisi tersine kurulabilir (label 400, değer 500) — property editor semantiği |
| 10 | Başlık hücresi | Gömülü liste başlığı grup zemininin bir ton koyusu bant; dış kasa 8px, hücreler 0px |
| 11 | Accent dozu | Düşük; grup başlıklarında mavi (light #003399 / dark #93A8F4) küçük vurgu |
| 12 | Motion | FormSection expand/collapse 200ms height+fade |

Form notu: konfigürasyon/ayar formları ve ERX property editor'ün doğal adayı; Checkbox/Switch
satırları grup içinde tam genişlik tıklanabilir satırlardır (hit area 44px). ErrorSummary:
grup yüzeyi deseninde, error ikonlu başlık bandı + iç ayraçlı madde listesi.

### Variant E — "Rule" (editoryal çizgi)

| # | Eksen | Form ailesindeki karar |
|---|---|---|
| 1 | Konteyner ayrımı | Kutular minimum; FormSection'ı tipografik hiyerarşi + yatay rule kurar; kart yalnız gerçekten gerekli yerde |
| 2 | Radius | xs (2px) — nadiren görünür çünkü kutu az |
| 3 | Focus | 2px ring; etkileşimli metinde (link, dosya adı) metin altı çizgi kalınlaşması |
| 4 | Hover | Metin/etiket rengi koyulaşır; zemin tintı YOK (yalnız satır bazlı öğelerde %3 tint) |
| 5 | Selected | Kalın (2px) rule üstte+altta veya başında bold işaret; dolgu minimum |
| 6 | Divider | Rule hiyerarşisi: bölüm arası 2px, öğe arası 1px |
| 7 | Density | Standard (44px) |
| 8 | Input biçimi | **0px keskin**, 1px border; **alt kenar 2px** (görsel ağırlık altta — editoryal his); focus'ta ring; error'da alt kenar error/600 + ikon + mesaj |
| 9 | Label | Küçük ölçek KULLANILMAZ (min 1rem korunur); label 500, bölüm başlığı 700 |
| 10 | Başlık hücresi | Gömülü liste başlığı çift rule (üst 2px + alt 1px) arasında; transparan zemin; 1rem/700 |
| 11 | Accent dozu | Çok düşük; sarı yalnız FormFooter primary CTA |
| 12 | Motion | Neredeyse yok; yalnız içerik giriş fade 150ms |

Form notu: EBM raporlama/governance formlarının doğal adayı; FormFooter üst sınırı 2px rule ile
ayrılır. ErrorSummary kutu değildir: 2px error/600 üst rule + ikonlu 700 başlık + 1px rule'lu
madde listesi.

### Variant F — "Elevated" (ölçülü yükselti)

| # | Eksen | Form ailesindeki karar |
|---|---|---|
| 1 | Konteyner ayrımı | FormSection raised kart: y=2 blur=8 %10 siyah gölge (dark modda gölge yerine bir ton + 1px border); Select/Date popover overlay: y=4 blur=16 %12 |
| 2 | Radius | lg (8px) — kart ve popover |
| 3 | Focus | 2px ring + hafif yükselti (gölge derinleşir) |
| 4 | Hover | Z-lift — gölge derinleşir (y=4 blur=12); SCALE YOK, translate YOK |
| 5 | Selected | Combobox opsiyonu satır-düzeyi öğedir: 1px mavi border + bir ton zemin dolgusu, yükselti YOK ([01](./01-varyant-cercevesi.md) ikame kuralı) |
| 6 | Divider | Yok; kart içi gerekiyorsa 1px |
| 7 | Density | Standard (44px) |
| 8 | Input biçimi | **Pill-shape** (tam yuvarlak — muafiyetin pill ucu yalnız bu varyantta); 1px border; focus'ta 2px ring; iç padding start/end 20px; error'da border error/600 + ikon + mesaj |
| 9 | Label | Roboto 500, pill'in DIŞINDA üstte (asla floating/inside değil) |
| 10 | Başlık hücresi | Kart-içi liste deseni: başlık bandı bir ton + kasa kartın parçası (8px) |
| 11 | Accent dozu | Orta-yüksek; sarı CTA + sarı odak metrikleri |
| 12 | Motion | 200ms gölge/renk; kart giriş 240ms fade+4px rise; reduced-motion'da yalnız fade |

Form notu: müşteri-yüzlü panellerin doğal adayı. Pill TextArea'ya uygulanmaz — TextArea çok
satırlıdır, kabuğu varyantın yüzey radius'unu (8px) kullanır ve iç padding start/end 20px ritmini
korur. ErrorSummary: raised kart deseninde, error ikonlu başlık + mesaj listesi.

## 6. Input muafiyeti özeti (eksen 8 karşılaştırma)

Input alanları genel radius kuralından (operasyonel tavan 8px; 12px yalnız mutlak üst limit
bağlamında anılır) muaftır. Varyant başına muafiyet biçimi:

| Varyant | Kabuk | Radius | Focus işareti | Error taşıyıcısı |
|---|---|---|---|---|
| A | 1px border, keskin | 0px | 2px ring, offset 2px | Border error/600 |
| B | Filled-tonal, border yok | 4px | 2px ring + alt kenar 2px mavi | Alt kenar 2px error/600 |
| C | 1px border, keskin | 0px | Start şeridi 2px mavi + ring | Start şeridi error/600 |
| D | Borderless-inset, ton zemin | 4px | Inset ring 2px | Inset ring error/600 |
| E | 1px border + alt kenar 2px | 0px | 2px ring | Alt kenar error/600 |
| F | 1px border, pill | pill | 2px ring + gölge derinleşir | Border error/600 |

Her hücrede error taşıyıcısına ikon + metin mesajı eşlik eder (bkz. §3, "asla yalnız kırmızı
border"). Ring renkleri her varyantta: light #003399, dark #93A8F4.

## 7. 320px davranışı

- Tüm alanlar tam genişlik, tek kolon; iki-kolon istisnaları dahi kırılır (bkz. §4).
- Kontrol yüksekliği density token'ını izler; compact'ta bile hit area 44×44px'e tamamlanır.
- FormFooter: butonlar tam genişlik, dikey istif; primary en altta başparmak bölgesinde;
  uzun formlarda sticky footer (opak zemin, scrim/blur yok).
- ErrorSummary form başında kalır; submit sonrası odak ve scroll oraya taşınır.
- Select/Combobox ve DateField popover'ları 320px'te tam genişlik panele dönüşür
  (container-query ile); FileUpload drop bölgesi buton öncelikli moda düşer (sürükle-bırak
  ikincil).
- Font 1rem altına inmez; label'lar kısaltılmaz, gerekirse sarar. Almanca uzama testi
  320px'te zorunludur.
- Varyant farkları 320px'te korunur (A keskin/B filled/C şerit/D inset/E alt kenar/F pill);
  yalnız F'nin kart gölgesi mobilde aynı token'la kalır, ek derinlik eklenmez.

## 8. RTL davranışı

- Tüm konumlama CSS logical properties ile yazılır (start/end); fiziksel left/right yasak.
- Variant C start şeridi RTL'de otomatik sağ kenara geçer; error/selected/AI şerit semantiği
  değişmez. Variant C inset divider'ı start hizasını RTL'de aynen izler.
- Variant D check ikonu logical-end'dedir — RTL'de sol kenara geçer.
- Variant F pill iç padding'i start/end 20px logical değerlerdir; RTL'de simetri bozulmaz.
- SearchField arama ikonu logical-start, temizleme aksiyonu logical-end; RTL'de ayna.
  Yön bildiren ikonlar (ör. chevron) RTL'de aynalanır; yön-nötr ikonlar aynalanmaz.
- Sayı ve tarih formatlama CLDR/Intl ile yereldir; Arapça RTL testi (form doldurma + validation
  akışı + ErrorSummary odak sırası) her varyant için zorunludur.
- Variant E alt kenar vurgusu ve B'nin alt kenar focus çizgisi yatay eksende yön değiştirmez
  (blok ekseni), RTL'den etkilenmez.

## 9. MCP prompt referansları

Tam katalog [06-figma-mcp-promptlari.md](./06-figma-mcp-promptlari.md) ve
[07-storybook-mcp-promptlari.md](./07-storybook-mcp-promptlari.md)'dedir; aşağıdaki iki prompt
form ailesine özgüdür.

Figma'da form ailesi component set'ini üretmeden önce envanter alınır (önce get_metadata
ile node tespiti, sonra yalnız hedef node'lara scope'lu get_design_context) ve mevcut
Code Connect bileşeni varken yeniden yaratma yasaktır; use_figma
çağrısından önce /figma-use skill okunur. Bu prompt, form component set'ini variant prop ile kurar.

```text
Using the existing variables collections (primitive/semantic/density + VARIANT overlay A-F),
create one component set per form component (TextField, TextArea, Select, Checkbox, Radio,
Switch, DateField, SearchField, FileUpload, FormSection, FormFooter, ErrorSummary) with
properties: variant=A|B|C|D|E|F, state=default|hover|focus|filled|error|disabled|readonly|loading,
density=comfortable|standard|compact, mode=light|dark. Inputs follow the radius exemption per
variant (A 0px, B filled 4px, C 0px + start stripe on focus, D inset 4px, E 0px + 2px bottom
edge, F pill with 20px logical start/end padding). Labels are always visible above the field,
Roboto 500 (D may invert to label 400 / value 500), min font size 1rem. Never encode state by
color alone: error uses carrier + icon + message. Do not create any component that already has
a Code Connect mapping; audit the file first.
```

Storybook'ta form matrisi ve validation etkileşim testi bu prompt ile üretilir; global
decorator'lar (theme, density, variant a–f) 07 dosyasındaki altyapıyı kullanır.

```text
Generate Storybook stories for the form family using the data-variant decorator
(globalTypes: theme, density, variant a-f). 1) Matrix stories: each component x 6 variants x
2 themes x 3 densities for visual regression. 2) Play tests: keyboard-only completion of a
reference form; submit with two invalid fields and assert that (a) user values are preserved,
(b) an ErrorSummary receives focus and lists both errors with anchor links, (c) each inline
message matches its summary entry 1:1, (d) error state renders icon + text, never color only;
then fix both fields and assert errors clear and confirmation appears. 3) axe checks
fail-blocking on color-contrast, label and focus rules. 4) i18n stories: de (long labels),
tr, ar (RTL mirror of variant C start stripe and D end check icon) at 320px and 1440px.
```

## 10. Kabul kriterleri

- [ ] 10 form bileşeninin tamamı 6 varyantta, `data-variant="a..f"` overlay'i ile tek headless
      davranış katmanı üzerinde çalışıyor; varyantlar yalnız 12 eksende farklılaşıyor.
- [ ] Input muafiyet matrisi (§6) birebir uygulanmış: A=0px, B=filled 4px, C=0px+focus şeridi,
      D=inset 4px, E=0px+alt kenar 2px, F=pill (padding start/end 20px).
- [ ] 8 state'in tamamı her bileşen x varyant kombinasyonunda tanımlı; focus-visible her
      varyantta var ve hiçbir state yalnız border rengi değişimiyle verilmiyor.
- [ ] Validation akışı dört adımı sağlıyor: değer korunur → tam açıklama → düzeltme → onay;
      ErrorSummary ile inline mesajlar 1:1 eşleşiyor ve submit'te odak özete taşınıyor.
- [ ] Error hiçbir varyantta yalnız kırmızı border ile verilmiyor: taşıyıcı + Phosphor ikon +
      metin mesajı üçlüsü mevcut.
- [ ] Label'lar her state'te görünür ve alanın üstünde; hiçbir label placeholder'a gömülmemiş;
      min font 1rem, weight'ler yalnız 400/500/700.
- [ ] Tek kolon kuralı uygulanmış; iki-kolon istisnaları yalnız §4 kriterlerini karşılayan
      çiftlerde ve ≥768px'te; container-query ile kırılıyor.
- [ ] 320px'te tüm alanlar tam genişlik, hit area min 44x44px, FormFooter dikey istif,
      popover'lar tam genişlik panel.
- [ ] RTL: logical properties kullanılmış; C şeridi ve D check ikonu aynalanıyor; Arapça RTL
      ve Almanca uzama testleri geçiyor.
- [ ] Kontrast: tüm state'lerde metin ≥4.5:1 (disabled içerik muafiyeti hariç); sarı zemin
      üstünde metin daima #080616; dark'ta metin-seviyesi mavi yalnız #93A8F4.
- [ ] Dark/light yalnız semantic token ile; hardcode hex yok; form yüzeylerinde glass/blur yok;
      hover'da scale yok; motion 120–240ms ease-out ve reduced-motion destekli.
- [ ] Storybook matrix + play + axe (fail-blocking) + i18n story'leri yeşil (P2 kabulü);
      Figma component set'leri variant prop A–F ile yayınlanmış.
