# 08 — Değerlendirme ve Seçim Protokolü [a..f]

Bu doküman, A–F varyantlarının nasıl ölçüleceğini, elemeleneceğini ve üretime nasıl terfi
ettirileceğini tanımlayan bağlayıcı protokoldür. Kapsam: 6 temel kriter + marka uyumu +
domain görev-uyumu tanımları, ağırlıklı skor kartı, ölçüm yöntemleri (Storybook matrix +
axe, Playwright perf trace, 5-saniye ve first-click mini-testleri, RTL/Almanca tarama),
3 karar kapısı, hibritleşme kuralı ve ADR formatında sonuç dokümanı şablonu. Tüm değerler
(hex, px, rem, weight, süre) [01-varyant-cercevesi.md](01-varyant-cercevesi.md)'deki
değişmezlerle birebir aynıdır; bu protokol yeni varyant veya token icat edemez.

Bağlantılı dosyalar: [00-genel-plan.md](00-genel-plan.md) ·
[01-varyant-cercevesi.md](01-varyant-cercevesi.md) · [02-card-varyantlari.md](02-card-varyantlari.md) ·
[03-form-varyantlari.md](03-form-varyantlari.md) · [04-table-varyantlari.md](04-table-varyantlari.md) ·
[05-bilesen-varyantlari.md](05-bilesen-varyantlari.md) · [06-figma-mcp-promptlari.md](06-figma-mcp-promptlari.md) ·
[07-storybook-mcp-promptlari.md](07-storybook-mcp-promptlari.md)

## 1. Protokolün yeri ve girdileri

Protokol P4'te (Hafta 9–11) uygulanır; çıktısı P5'i (Hafta 11–12, freeze ve terfi) besler
([00-genel-plan.md](00-genel-plan.md)). Değerlendirme birimi tek bileşen değil, P3'te
üretilen 3 referans ekranın (Kayıt Listesi, Kayıt Oluştur, Detay+Inline Edit) domain
bağlamındaki tam kompozisyonudur; her ekran 320px ve 1440px'te değerlendirilir.

| Girdi | Kaynak | Zorunlu mu |
|---|---|---|
| 6-lı karşılaştırma canvas'ı (Figma) ve Storybook karşılaştırma sayfası | P3, [06](06-figma-mcp-promptlari.md) P-06-6 | Evet |
| Matrix story'ler + axe raporları (fail-blocking: color-contrast, label, focus) | [07](07-storybook-mcp-promptlari.md) Prompt 2 (matrix) ve Prompt 5 (axe) | Evet |
| 10k satır sanallaştırma perf story'si | [07](07-storybook-mcp-promptlari.md) Prompt 7, [04](04-table-varyantlari.md) | Evet |
| i18n story'leri (de/tr/ar-RTL, uzun içerik) | [07](07-storybook-mcp-promptlari.md) Prompt 6 | Evet |
| Görsel regresyon matrisi (budanmış alt küme) | [07](07-storybook-mcp-promptlari.md) Prompt 8 | Evet |
| Domain görev senaryoları (domain başına 3 görev) | Bu doküman §4.4 | Evet |

## 2. Kriter seti ve tanımlar

Puanlama ölçeği her kriterde 1–5'tir; ham metrikler §3'teki eşiklere göre banda çevrilir
(eşik altı = 1–2, eşik = 3, eşik üstü belirgin fark = 4–5). Sübjektif kriterlerde (tarama
hızı rubriği, marka uyumu) en az 3 bağımsız değerlendirici puanlar, medyan alınır.

### 2.1 Altı temel kriter

| # | Kriter | Tanım | Ölçüm yöntemi |
|---|---|---|---|
| K1 | Tarama hızı | Kullanıcının ekranı ilk bakışta çözümleme ve doğru hedefe yönelme hızı; konteyner ayrım gramerinin (eksen 1) ve accent dozunun (eksen 11) bilişsel yükü | 5-saniye testi (§4.3) + first-click süre/isabet (§4.4) |
| K2 | Kontrast / a11y | WCAG 2.2 AA: her state'te metin kontrastı ≥4.5:1, focus-visible varlığı ve algılanabilirliği (eksen 3), durumun asla yalnız renkle verilmemesi | Storybook matrix × axe raporu (§4.1) + manuel klavye turu |
| K3 | Render maliyeti | Varyantın CSS overlay'inin çizim maliyeti: 10k satır sanal listede kaydırma akıcılığı; gölge (F), şerit animasyonu (C), ton geçişleri (B) gibi eksen kararlarının fiyatı | Playwright perf trace (§4.2): 60fps, main-thread task <50ms |
| K4 | State iletişimi | hover/focus/selected/error/disabled/loading state'lerinin ayırt edilebilirliği ve tutarlılığı (eksen 3–5, 12); ikon+metin kuralına uyum | Play testleri yeşil + state ayırt etme rubriği (3 değerlendirici) |
| K5 | Density esnekliği | comfortable(52)/standard(44)/compact(36) üçlüsünde gramerin ayakta kalması: compact'ta ayraç/şerit okunurluğu, hit area min 44×44px korunumu, min 1rem font ihlali olmaması | Matrix story 3 density × 2 tema; görsel regresyon + axe |
| K6 | i18n dayanıklılığı | RTL mirror doğruluğu (logical properties), Almanca uzamada kırılmama, CLDR formatlama, label'ların her koşulda görünür kalması | RTL/Almanca kırılma taraması (§4.5) |

### 2.2 Marka uyumu

Varyantın "Flat 2.0 + bağlamsal kartlar" ana diliyle ve marka renk disipliniyle örtüşmesi:
sarı (#FFB900) dozunun eksen 11 tanımına sadakati, sarı zemin üstünde her zaman #080616
metin, #003399'un dark'ta yalnız geniş dolgu/border/yapısal yüzey olarak kullanımı
(metin-seviyesi için #93A8F4). Ölçüm: 5-saniye testine eklenen marka algı soruları
("Bu ürün ciddi/güvenilir mi görünüyor?") + 3 kişilik marka rubriği (medyan).

### 2.3 Domain görev-uyumu

Varyantın karakterinin hedef domain'in baskın görev tipine uygunluğu: yoğun grid taraması
(Analytical Console), sürekli oturum yönetimi (EBP shell), istisna triyajı (EOP), özellik
düzenleme (ERX), rapor okuma (EBM), ürün keşfi (commerce). Ölçüm: domain başına yazılmış
3 görev senaryosunda first-click isabeti + görev tamamlama oranı + uzman değerlendirme.
§5.2'deki eşleşme hipotezi başlangıç noktasıdır, sonuç değildir.

## 3. Ağırlıklı skor kartı

Ağırlıklar toplamı 100'dür. Skor kartı domain bağlamı başına ayrı doldurulur (aynı varyant
farklı domain'lerde farklı toplam alabilir — bu beklenen sonuçtur). K2 ve K3 aynı zamanda
Kapı 1'in hard-fail kriterleridir: eşiği geçemeyen varyant puanlanmadan elenir.

| Kriter | Ağırlık (öneri) | Ölçüm aracı | Geçer eşik |
|---|---|---|---|
| K1 Tarama hızı | 15 | 5-saniye testi + first-click | 5-sn'de doğru amaç/CTA hatırlama ≥%80; first-click isabet ≥%75 |
| K2 Kontrast / a11y | 15 | Storybook matrix + axe + klavye turu | 0 axe ihlali (color-contrast, label, focus); tüm state'lerde ≥4.5:1 |
| K3 Render maliyeti | 10 | Playwright perf trace (10k satır story) | p95 frame ≤16.7ms (60fps); en uzun main-thread task <50ms |
| K4 State iletişimi | 15 | Play testleri + rubrik | Play %100 yeşil; rubrik medyan ≥4/5 |
| K5 Density esnekliği | 10 | Matrix story (3 density) + görsel regresyon | 3 modda da AA + hit area ≥44×44px + 1rem tabanı korunur |
| K6 i18n dayanıklılığı | 10 | RTL/Almanca taraması | de/ar-RTL story'lerinde 0 kırılma; mirror tam |
| Marka uyumu | 10 | Marka rubriği + 5-sn algı soruları | Rubrik medyan ≥4/5; renk kuralı ihlali 0 |
| Domain görev-uyumu | 15 | Görev senaryoları (first-click + tamamlama) | Tamamlama ≥%90; first-click isabet ≥%75 |

Ağırlıklar domain bağlamına göre kalibre edilebilir (örn. Analytical Console'da K1+K3
ağırlığı artırılabilir); kalibrasyon değerlendirme başlamadan ÖNCE yazılır ve ADR'de
gerekçelendirilir — veri görüldükten sonra ağırlık değiştirmek yasaktır.

## 4. Ölçüm yöntemleri detayı

### 4.1 Storybook matrix + axe raporu

[07](07-storybook-mcp-promptlari.md) Prompt 2/5 çıktıları kullanılır: bileşen × varyant ×
tema × density matrix story'leri üzerinde axe, fail-blocking kural setiyle
(color-contrast, label, focus) koşar. Rapor varyant başına toplanır; tek bir ihlal bile
Kapı 1'de eleme nedenidir. Manuel tamamlayıcı: her varyantta klavye-yalnız tur (Tab/Shift+Tab,
ok tuşları) ile focus-visible'ın her zeminde algılanabilirliği doğrulanır.

Ne zaman kullanılır: P4 başında, tüm varyantların a11y kanıtını tek raporda toplamak için.

```text
Context: Storybook 10.x with @storybook/addon-mcp at http://localhost:6006/mcp.
Matrix stories (component x variant a-f x theme x density) and axe integration
already exist per the 07 prompt catalog.
Task: Run the full a11y sweep for evaluation gate 1. For every variant a-f,
execute axe on all matrix stories with fail-blocking rules color-contrast,
label, focus-order-semantics. Aggregate results into
docs/ui-variant-plan/evaluation/axe-summary.json with shape:
{ variant, theme, density, story_id, violations: [{rule, impact, selector}] }.
Also emit a per-variant totals table (variant, stories_run, violations_total,
worst_rule) as Markdown to docs/ui-variant-plan/evaluation/axe-summary.md.
Do not modify component code or stories; this is a read-and-report pass.
```

### 4.2 Playwright perf trace

[07](07-storybook-mcp-promptlari.md) Prompt 7'deki 10k satır sanallaştırma story'si her
varyant için Playwright trace ile ölçülür: deterministik kaydırma senaryosu Prompt 7'nin
story parametreleriyle birebir aynıdır (satır 0'dan 10.000'e programatik scroll), trace'ten
p95 frame süresi ve en uzun main-thread task çıkarılır. Hedefler
[04](04-table-varyantlari.md) ile aynıdır: 60fps (frame ≤16.7ms) ve main-thread task <50ms.
Ölçüm 2 tema × 3 density kombinasyonlarının tamamında koşulur; skor kartına en kötü
kombinasyon yazılır (budama yalnız raporlamada yapılır, koşumda yapılmaz).

Ne zaman kullanılır: P4 başında, Kapı 1'in render maliyeti kanıtını üretmek için.

```text
Context: @storybook/test-runner (Playwright-based) against the 10k-row
virtualization story (TanStack Virtual) available for each variant a-f.
Task: For each variant, open the 10k-row story in dark and light themes at
all three densities (comfortable, standard, compact), start a Playwright
performance trace, run the same scripted smooth scroll the story's play
function defines (row 0 to row 10,000), then stop the trace. Extract
p95 frame duration and the longest main-thread task per run. Write
docs/ui-variant-plan/evaluation/perf-summary.md as a table:
variant | theme | density | p95_frame_ms | longest_task_ms | pass(60fps & <50ms).
Use a fixed seed dataset (date 2026-08-16), no Math.random. Flag any run
exceeding 16.7ms p95 frame or 50ms task as HARD FAIL.
```

### 4.3 5-saniye testi

Amaç: tarama hızı (K1) ve marka algısının ilk izlenim ölçümü. Uygulama: katılımcıya P3
referans ekranının 1440px görüntüsü 5 saniye gösterilir, ardından kapatılıp sorulur:
(1) "Bu ekran ne için?" (2) "Birincil eylem neydi?" (3) marka algı soruları (§2.2).
Katılımcı: hücre başına en az 8 kişi (iç kullanıcı kabul edilir); her katılımcı aynı
ekranın yalnız TEK varyantını görür (öğrenme etkisini önlemek için denekler-arası tasarım).
Maliyeti sınırlamak için test, Kapı 1'i geçen varyantlarla ve domain başına skor kartı
ilk 3'üyle sınırlandırılır. Eşik: amaç + birincil eylem doğru hatırlama ≥%80.

### 4.4 First-click testi

Amaç: K1 ve domain görev-uyumunun davranışsal ölçümü. Uygulama: Storybook karşılaştırma
sayfasındaki etkileşimli ekran üzerinde, domain başına 3 görev senaryosu ("X kaydını
filtrele", "yeni kayıt oluştur", "hatalı alanı düzelt") verilir; ilk tıklamanın hedef
bölgede olup olmadığı ve time-to-first-click kaydedilir (basit click-capture overlay
yeterlidir, harici araç şart değil). Eşik: ilk tıklama isabeti ≥%75, görev tamamlama ≥%90.
320px mobil sürümü, dokunma hedefi (min 44×44px) isabetini ayrıca raporlar.

### 4.5 RTL/Almanca kırılma taraması

[07](07-storybook-mcp-promptlari.md) Prompt 6'nın i18n story'leri (de/tr/ar-RTL, uzun
içerik) 320px ve 1440px'te görsel regresyon anlık görüntüleriyle taranır. Kırılma sayılan
durumlar: kontrolsüz yatay taşma, görünür label kaybı veya kesilmesi, ikon/şerit mirror
hatası (özellikle C'nin logical-start şeridi ve tablo hizalama kuralları: metin→start,
sayı/para→end), CLDR dışı tarih/sayı formatı, 16px altına düşen metin. Eşik: 0 kırılma;
Almanca uzamada satır sayısı artışı kırılma değildir, kayıp/örtüşme kırılmadır.

## 5. Karar kapıları

### 5.1 Kapı 1 — Eleme turu (hard fail)

Skor kartı doldurulmadan önce her varyant üç hard-fail kriterinden geçer. Fail eden
varyant o değerlendirme döngüsünden düşer. Hata varyantın grameri yerine token/CSS
overlay seviyesindeki bir uygulama hatasından kaynaklanıyorsa TEK düzeltme turu hakkı
vardır (P4 takvimi içinde); ikinci fail kesin elemedir.

| Hard fail | Tanım | Kanıt kaynağı |
|---|---|---|
| AA ihlali | Herhangi bir varyant × tema × density × state kombinasyonunda metin kontrastı <4.5:1 veya axe fail-blocking ihlali (color-contrast, label, focus) | §4.1 axe raporu + klavye turu |
| 60fps altı | 10k satır story'de p95 frame >16.7ms veya main-thread task ≥50ms (herhangi bir ölçülen kombinasyonda) | §4.2 perf trace |
| 320px kırılması | 320px'te kontrolsüz yatay taşma, label kaybı, hit area <44×44px veya 1rem (16px) altı metin | Görsel regresyon + §4.5 taraması |

### 5.2 Kapı 2 — Domain eşleme turu

Kapı 1'i geçen varyantlar için skor kartı domain bağlamı başına doldurulur. KRİTİK İLKE:
tek kazanan zorunlu DEĞİLDİR; domain başına farklı varyant ataması meşru ve beklenen bir
sonuçtur — [01](01-varyant-cercevesi.md)'deki mühendislik modeli (tek headless katman +
`data-variant` overlay) çoklu atamanın bakım maliyetini zaten sınırlar. Bakım gerekçesiyle
üretimde tutulacak farklı varyant sayısını sınırlamak (öneri: en fazla 3) serbesttir;
bu sınır ADR'de gerekçelendirilir.

Aşağıdaki hipotez ([01](01-varyant-cercevesi.md)'deki doğal eşleşme önerisi) başlangıç
noktasıdır; skor kartı hipotezi doğrulayabilir veya çürütebilir:

| Domain bağlamı | Hipotez varyant | Hipotezin dayanağı |
|---|---|---|
| Analytical Console (EA/EOP grid) | A "Hairline" | En yoğun, çizgisel gramer; compact'a yakın density |
| EBP shell / dashboard | B "Tonal" | Ton katmanlı, yumuşak SaaS hissi |
| EOP exception / workflow | C "Stripe" | Durum taşıyan start şeridi; sarı = AI provenance |
| ERX konfigürasyon | D "Inset" | Gruplanmış yüzey, property editor semantiği |
| EBM raporlama / governance | E "Rule" | Editoryal çizgi hiyerarşisi, minimum kutu |
| Commerce / müşteri-yüzlü | F "Elevated" | Ölçülü yükselti, pill input, orta-yüksek accent |

Karar kuralı: bir domain'de en yüksek ağırlıklı toplamı alan varyant atanır; ilk iki
varyant arasındaki fark 5 puandan azsa (100 üzerinden) hipotez varyantı öncelik kazanır
(eşitlik bozucu), fark yine kapanmıyorsa §6 hibritleşme değerlendirilir.

### 5.3 Kapı 3 — Freeze ve terfi

P5'te (Hafta 11–12) uygulanır; her adım imzalı ADR'ye (§7) bağlanır.

| Adım | İşlem | Kabul kanıtı |
|---|---|---|
| Token terfisi | Kazanan varyant(lar)ın overlay token'ları ilgili domain'in default semantic token'ına terfi eder; hardcode hex yine yasak | Style Dictionary build + token drift CI yeşil |
| Arşiv flag | Kalan varyantlar silinmez; `data-variant` altyapısında arşiv flag'i ile işaretlenir, Storybook'ta "archived" etiketiyle kalır | Arşivli varyant story'leri CI matrisinden çıkar, kod tabanında durur |
| Code Connect | Kazanan bileşen seti Figma component set'ine Code Connect ile eşlenir ([06](06-figma-mcp-promptlari.md) P-06-7); mevcut eşleme varken yeniden yaratma yasak | Code Connect map raporu |
| CI kapıları | axe fail-blocking, perf eşikleri, token drift ve görsel regresyon kapıları kalıcılaşır ([07](07-storybook-mcp-promptlari.md) Prompt 8) | CI pipeline'da zorunlu check |
| Sürümleme | Design system v1.0 tag; 2027 governance planına devir | v1.0 tag + imzalı karar dokümanı |

## 6. Hibritleşme kuralı

Tanım: eksen bazında en iyi mikro-kararların tek varyantta birleştirilmesi. Kural:
hibrit her zaman BİR taban varyanttan başlar ve başka varyantlardan yalnız BÜTÜN eksen
kararları ithal eder (eksen içinden parça seçilemez). İthal edilen karar, tabanın eksen 1
(konteyner ayrım grameri) kararıyla çelişmiyorsa ve [01](01-varyant-cercevesi.md)
değişmezlerini ihlal etmiyorsa geçerlidir. Hibrit sonuç yeni bir harf almaz; ADR'de
"taban + ithal eksen listesi" olarak kaydedilir ve TÜM bileşen ailelerine aynı anda
uygulanır (varyant-içi tutarlılık ilkesi korunur).

Eksen bağımlılık haritası (12 eksen, [01](01-varyant-cercevesi.md) sırasıyla):

| Eksen | Bağımlılık | İthal serbestliği |
|---|---|---|
| 1 Konteyner ayrım grameri | Kök karar — 5 ve 6 ve 10'u belirler | İthal edilemez; taban varyanttan gelir |
| 2 Radius eşlemesi | 1 ile zayıf bağlı | İthal edilebilir (ölçek içinden: 2/4/6/8px) |
| 3 Focus-visible stili | 1 ile orta bağlı (inset ring D gramerini gerektirir) | Koşullu |
| 4 Hover geri bildirimi | 1 ile orta bağlı (ton açılması B basamağını gerektirir) | Koşullu |
| 5 Seçim işareti | 1'e sıkı bağlı (border/şerit/dolgu grameri) | Genelde ithal edilemez |
| 6 Ayraç stratejisi | 1'e sıkı bağlı | Genelde ithal edilemez |
| 7 Density varsayılanı | Bağımsız | Serbest |
| 8 Input biçimi | Muafiyet alanı; 1'den bağımsız (pill yalnız F'de) | Serbest (pill hariç: pill yalnız F tabanında) |
| 9 Label ağırlığı/işlenişi | Bağımsız | Serbest |
| 10 Tablo başlık hücresi | 1'e sıkı bağlı | Genelde ithal edilemez |
| 11 Accent dozu | Yarı bağımsız (şerit tabanlı sarı C gramerini gerektirir) | Koşullu |
| 12 Motion mikro-davranışı | Bağımsız (120–240ms sınırı içinde) | Serbest |

Örnek — geçerli hibrit: taban D "Inset", ithal C'nin eksen 9 kararı (zorunlu alan
"(zorunlu)" metniyle işaretlenir, yalnız yıldız değil). Eksen 9 bağımsızdır, D'nin
gruplanmış yüzey grameriyle çelişmez; form ailesi dahil her ailede uygulanır.

Örnek — geçersiz hibrit: taban B "Tonal" (eksen 1: border'sız, ton basamaklı ayrım),
ithal A'nın eksen 5 kararı (selected = yalnız 2px mavi border). A'nın seçim işareti
border gramerini varsayar; B'de border yoktur — eksen 5, eksen 1'e sıkı bağlı olduğu
için bu birleşim çelişkilidir ve reddedilir. Doğru yol: B'nin kendi seçim kararı
(blue %8 dolgu + ikon) korunur.

## 7. Sonuç dokümanı şablonu (ADR)

Kapı 2 ve Kapı 3 kararlarının her biri ayrı ADR olarak `docs/ui-variant-plan/decisions/`
altına yazılır ve imzalanır. Şablon:

```md
# ADR-EAPUI-NNN: <Karar başlığı, örn. "EOP exception ekranlarına Variant C ataması">

- Durum: Önerildi | Kabul edildi | Reddedildi | Yerini aldı: ADR-EAPUI-MMM
- Tarih: 2026-AA-GG
- Karar vericiler: <isim, rol — en az tasarım + frontend + ürün birer imza>
- Faz / Kapı: P4 Kapı 2 (domain eşleme) | P5 Kapı 3 (freeze ve terfi)

## Bağlam
<Domain bağlamı, kullanılan referans ekranlar (Kayıt Listesi / Kayıt Oluştur /
Detay+Inline Edit), Kapı 1 eleme sonucu, ağırlık kalibrasyonu ve gerekçesi.>

## Karar
<Domain X -> Variant Y. Hibrit ise: taban varyant + ithal eksen listesi
(eksen no + kaynak varyant), §6 uygunluk kontrolü sonucu.>

## Skor özeti
| Varyant | Ağırlıklı toplam (/100) | Kapı 1 | Not |
|---|---|---|---|

## Gerekçe
<Skor kartındaki belirleyici kriterler; hipotezden sapma varsa nedeni;
eşitlik bozucu uygulandıysa açıkça belirtilir.>

## Sonuçlar
- Token terfisi: <hangi overlay -> hangi default semantic token>
- Arşiv flag: <arşivlenen varyantlar>
- Code Connect: <güncellenen eşlemeler>
- CI kapıları: <kalıcılaşan kontroller>

## Kanıt bağlantıları
- axe-summary.md / perf-summary.md / görsel regresyon raporu
- 5-saniye ve first-click ham verileri
- i18n tarama anlık görüntüleri
```

## Kabul kriterleri

- [ ] 6 temel kriter + marka uyumu + domain görev-uyumu tanımlı ve her birinin ölçüm yöntemi belirtilmiş
- [ ] Ağırlıklı skor kartı tablosu (kriter, ağırlık, ölçüm aracı, geçer eşik) dolduruldu; ağırlıklar toplamı 100 ve kalibrasyon veri görülmeden yazıldı
- [ ] Kapı 1 hard-fail kanıtları (axe raporu, perf trace, 320px taraması) tüm varyantlar için üretildi
- [ ] 5-saniye ve first-click testleri denekler-arası tasarımla, eşiklerle birlikte uygulandı
- [ ] RTL/Almanca taraması de/tr/ar-RTL story'lerinde 320px + 1440px'te tamamlandı, 0 kırılma doğrulandı
- [ ] Kapı 2 sonucu domain başına ADR'lerle kaydedildi; tek kazanan dayatılmadı, hipotezden sapmalar gerekçelendirildi
- [ ] Hibrit önerildiyse §6 bağımlılık haritasına göre çelişkisizlik kontrolü ADR'de belgelendi
- [ ] Kapı 3: kazanan token terfisi, arşiv flag'leri, Code Connect eşlemesi ve kalıcı CI kapıları tamamlandı; design system v1.0 tag atıldı
- [ ] Dokümandaki tüm hex/px/rem/süre değerleri [01-varyant-cercevesi.md](01-varyant-cercevesi.md) ile birebir aynı; yeni varyant/token icat edilmedi
