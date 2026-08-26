# 00 — Genel Plan: EA Platform UI Çok-Varyant Geliştirme

Bu doküman, EA Platform ailesi (EA, EBP, EOP, EBM, ERX) için tek tasarım felsefesinin
(Flat 2.0 temeli + bağlamsal kartlar) altı ince-taneli mikro-stil yorumunun — Variant A–F —
paralel olarak tasarlanması, kodlanması, ölçülmesi ve karara bağlanması için ana plandır.
Amaç, kapsam, teslimatlar, P0–P5 fazları (adım, sorumlu, kabul), hafta bazlı zaman çizelgesi,
risk kaydı, karar kapıları ve dosya haritası burada tanımlanır; ayrıntılı spesifikasyonlar
bağlantılı dosyalardadır.

Bağlantılı dosyalar: [01-varyant-cercevesi.md](01-varyant-cercevesi.md) ·
[02-card-varyantlari.md](02-card-varyantlari.md) · [03-form-varyantlari.md](03-form-varyantlari.md) ·
[04-table-varyantlari.md](04-table-varyantlari.md) · [05-bilesen-varyantlari.md](05-bilesen-varyantlari.md) ·
[06-figma-mcp-promptlari.md](06-figma-mcp-promptlari.md) · [07-storybook-mcp-promptlari.md](07-storybook-mcp-promptlari.md) ·
[08-degerlendirme-protokolu.md](08-degerlendirme-protokolu.md)

## 1. Amaç

- Tek tasarım dilinin (Flat 2.0 + contextual cards) 6 mikro-stil yorumunu (A–F) aynı davranış
  katmanı üzerinde, gerçek bileşenler ve gerçek kompozisyon ekranlarıyla üretmek.
- Varyantları önyargıyla değil ölçümle (tarama hızı, kontrast/a11y, render maliyeti, state
  iletişimi, density esnekliği, i18n dayanıklılığı) karşılaştırıp domain başına doğru varyantı
  seçmek; kazanan(ları) design system v1.0 olarak dondurmak.
- İnsan (tasarım/FE/a11y/ürün) ile MCP-ajan (Figma MCP, Storybook MCP) iş bölümünü baştan
  tanımlayarak üretimi tekrarlanabilir ve denetlenebilir kılmak.

## 2. Kapsam

### 2.1 Kapsam içi

| Alan | İçerik |
|---|---|
| Token mimarisi | primitive / semantic / density + variant overlay (a–f); Figma Variables + tokens JSON + Style Dictionary → CSS custom properties |
| Bileşen aileleri | Card, Form, Data Table/Grid, destekleyici bileşenler (Button, Tabs, SidePanel, Modal, CommandPalette vb.) — her aile × A–F |
| Kompozisyon | 3 referans ekran (Kayıt Listesi, Kayıt Oluştur, Detay+Inline Edit) × 6 varyant × 320/1440 |
| Kalite | Storybook matrix, play testleri, axe (fail-blocking), i18n story'leri (de/tr/ar-RTL), 10k satır perf story, görsel regresyon, token drift CI |
| Değerlendirme | 08 protokolü: ağırlıklı skor kartı, eleme turu, domain eşleme, freeze ve terfi |

### 2.2 Kapsam dışı

- Yeni varyant, yeni renk, yeni radius icadı (brief değişmezleri bağlayıcıdır).
- Neumorphism; form/tablo/veri yüzeyi ve scrim'de glass/blur (glass yalnız global header,
  command palette ve geçici overlay'de opsiyonel, opak fallback şartıyla).
- Next.js veya SSR altyapısı (stack: Vite + React + TypeScript + SCSS/CSS custom properties).
- Pazarlama sitesi, mobil native uygulamalar, 2030/2035 agent-readable UI'nin uygulanması
  (yalnız tokenların buna hazır kurgulanması kapsam içidir).

## 3. Teslimatlar

| # | Teslimat | Faz | Kanıt/Yer |
|---|---|---|---|
| T1 | Token seti: primitive/semantic/density + variant overlay a–f (Figma Variables + JSON + CSS custom properties) | P0 | Figma kütüphanesi, `tokens/`, Style Dictionary build |
| T2 | data-variant altyapısı + headless davranış katmanı + Storybook decorator (theme × density × variant) | P1 | kod tabanı, Storybook |
| T3 | Bileşen aileleri × A–F: Figma component set (variant prop) + kod + matrix/play/axe story'leri | P2 | Figma + Storybook |
| T4 | 6-lı karşılaştırma canvas'ı (Figma) ve Storybook karşılaştırma sayfası (3 ekran × A–F × 320/1440) | P3 | Figma + Storybook |
| T5 | Doldurulmuş ağırlıklı skor kartı + imzalı karar dokümanı (domain → varyant eşlemesi) | P4 | 08 protokol çıktısı |
| T6 | Design system v1.0: kazanan varyant(lar) default token'a terfi, Code Connect eşlemesi, arşiv flag'leri, kalıcı CI kapıları | P5 | v1.0 tag |
| T7 | Bu doküman seti (00–08) | P0'dan itibaren | `docs/ui-variant-plan/` |

## 4. Değişmezler (özet — tam metin: [01](01-varyant-cercevesi.md))

Aşağıdaki değerler tüm varyantlarda ortaktır ve hiçbir fazda değiştirilemez.

| Alan | Değişmez |
|---|---|
| Tipografi | Roboto (Latin) + Noto Sans script fallback, self-host; weight yalnız 400/500/700; min font 1rem (16px) — başlık/caption dahil; sayısal hücrede `tabular-nums` |
| Primary | #FFB900; sarı zemin üstü metin HER ZAMAN #080616 (11.63:1 AAA) — asla beyaz (1.72:1 FAIL) |
| Secondary | #003399; koyu zeminde (#080616) metin/ince ikon olarak YASAK (1.85:1); dark metin-seviyesi mavi: blue/300 = #93A8F4 |
| Yüzeyler | dark: ink/950=#080616, ink/900=#0D0A24, ink/800=#16123A; light: ink/50=#F7F7FB, #FFFFFF, border #E4E4EE (dark border #26224A) |
| Semantik renk | success/600=#15803D, error/600=#DC2626, warning/600=#B45309, info/600=#1D4ED8; dark'ta aydınlatılmış türev; durum asla yalnız renkle iletilmez |
| Geometri | radius xs=2/sm=4/md=6/lg=8 px, operasyonel tavan 8px (12px yalnız mutlak üst limit); input muaf (0px / 4px / pill — pill yalnız Variant F input'ları ve semantik kapsüller); spacing 4/8/12/16/24/32/48; hit area min 44×44px |
| Davranış | Dark/Light semantic token ile (hardcode hex yasak); density comfortable(52)/standard(44)/compact(36); WCAG 2.2 AA ≥4.5:1 her state; focus-visible zorunlu; motion 120–240ms ease-out, hover'da scale yok, `prefers-reduced-motion` |
| Yerleşim/i18n | 320-first, bantlar 320/480/768/1024/1440, container-query öncelikli; RTL mirror + CSS logical properties; label her zaman görünür ve üstte; ikon Phosphor, emoji yasak |

## 5. Varyant A–F özeti ve domain hipotezi

Altı varyant, [01](01-varyant-cercevesi.md)'de tanımlı 12 mikro-eksende (konteyner ayrım
grameri, radius eşlemesi, focus stili, hover, seçim, ayraç, density varsayılanı, input biçimi,
label işlenişi, tablo başlığı, accent dozu, motion) farklılaşır; felsefe ve token temeli ortaktır.

| Varyant | İsim | Ayrım grameri | Radius | Density varsayılanı | Input biçimi | Accent dozu | Karakter |
|---|---|---|---|---|---|---|---|
| A | Hairline | 1px semantik border; ton farkı ve gölge yok | sm (4px) | compact'a yakın standard | 0px keskin, 1px border | Minimum; sarı yalnız primary CTA | En yoğun, "mühendis" his |
| B | Tonal | Border'sız; yüzey tonu basamakları (950→900→800) | lg (8px) | comfortable'a yakın standard | filled-tonal, 4px, border yok | Orta; empty state'te sarı ikon serbest | En yumuşak, "ürünleşmiş SaaS" |
| C | Stripe | Soluk 1px border (%50 opaklık) + 2px logical-start durum şeridi | md (6px) | standard | 0px keskin; focus'ta start şeridi | Şeritle yüksek, alan olarak küçük; sarı şerit = AI provenance | Durum-yoğun operasyon |
| D | Inset | Tek gruplanmış dış yüzey (1px border + ton); iç öğeler border'sız | dış lg (8px), iç 0px | comfortable | borderless-inset, 4px | Düşük; grup başlığında mavi/#93A8F4 vurgu | Ayar/property editor semantiği |
| E | Rule | Kutu minimum; tipografik hiyerarşi + yatay çizgiler (2px/1px) | xs (2px) | standard | 1px border, 0px keskin, alt kenar 2px | Çok düşük; sarı yalnız CTA + kritik KPI | Editoryal, raporlama |
| F | Elevated | Ölçülü gölge (raised y=2 blur=8 %10; overlay y=4 blur=16 %12; dark'ta ton + 1px border) | lg (8px) | standard | pill-shape, 1px border, iç padding start/end 20px | Orta-yüksek; sarı CTA + odak metrikleri | Commerce, müşteri-yüzlü |

Domain eşleşme hipotezi (bağlayıcı değil; P4'te 08 protokolüyle test edilir):

| Varyant | Doğal domain adayı |
|---|---|
| A | Analytical Console (EA/EOP grid) |
| B | EBP shell / dashboard |
| C | EOP exception / workflow |
| D | ERX konfigürasyon |
| E | EBM raporlama / governance |
| F | Commerce / müşteri-yüzlü paneller |

## 6. Fazlar P0–P5

Genel iş bölümü ilkesi: MCP-ajanlar (Figma MCP, Storybook MCP) tekrarlanabilir üretimi yapar
(variables kurulumu, component set üretimi, matrix story, test iskeleti); insan roller
(Tasarım lideri, FE lead, A11y gözden geçirici, Ürün sahibi) karar, onay ve istisna yönetimini
üstlenir. Hiçbir ajan çıktısı insan gözden geçirmesi olmadan "kabul edildi" sayılmaz.
Prompt katalogları: Figma için [06](06-figma-mcp-promptlari.md), Storybook için
[07](07-storybook-mcp-promptlari.md).

### P0 — Token temeli (Hafta 1–2)

Hedef: tüm varyantların üzerine oturacağı token mimarisini kurmak ve kontrastı kanıtlamak.

| Adım | İnsan sorumluluğu | MCP-ajan sorumluluğu |
|---|---|---|
| Primitive token seti (renk/spacing/radius/typography) JSON'da | Tasarım lideri: değer onayı (brief birebir) | — (kaynak insan onaylı JSON) |
| Semantic + density katmanı (light/dark modları) | Tasarım lideri + FE lead: adlandırma şeması | Storybook MCP ajanı: Style Dictionary build iskeleti |
| Variant overlay koleksiyonu (a–f) | Tasarım lideri: eksen kararlarının token'a çevirisi | Figma MCP ajanı: koleksiyon/mod kurulumu |
| Figma Variables kurulumu ve JSON eşitliği | Tasarım lideri: örneklem denetimi | Figma MCP ajanı: envanter (get_metadata/get_design_context) sonra yazım |
| Kontrast matrisi + token drift CI | A11y gözden geçirici: matris onayı | Storybook MCP ajanı: otomatik kontrast hesap scripti + CI job |

Örnek Figma prompt'u (tam katalog: [06](06-figma-mcp-promptlari.md)); Variables kurulumundan
önce mevcut kütüphane envanteri zorunludur:

```text
Using the Figma MCP: first run get_metadata and get_design_context on the target library
file to inventory existing variable collections. Then create collections "primitive",
"semantic" (modes: light, dark), "density" (modes: comfortable, standard, compact) and
"variant-overlay" (modes: a, b, c, d, e, f). Import all values verbatim from the approved
tokens JSON; do not invent colors, radii or spacing steps. Report conflicts with existing
collections before writing anything.
```

Kabul kriterleri (P0):
- Tüm semantic renk çiftleri her iki modda AA (metin ≥4.5:1) — kontrast matrisi imzalı.
- Token drift kontrolü CI'da çalışıyor (Figma Variables ↔ tokens JSON ↔ CSS çıktısı birebir).
- #FFB900 üstü metin tokenı #080616'ya kilitli; #003399 dark'ta metin rolüne atanamıyor
  (lint kuralı ile).

### P1 — Varyant çerçevesi ve primitives (Hafta 2–3)

Hedef: tek headless davranış katmanı + `data-variant="a..f"` CSS overlay altyapısı.

| Adım | İnsan sorumluluğu | MCP-ajan sorumluluğu |
|---|---|---|
| `data-variant` attribute + CSS custom property overlay mimarisi | FE lead: mimari karar ve kod incelemesi | Storybook MCP ajanı: iskelet üretimi |
| Headless davranış katmanı (focus/keyboard/state makineleri) | FE lead: API tasarımı | — |
| Focus/hover/divider mikro-pattern'lerinin 6 varyant CSS overlay'i | Tasarım lideri: eksen kararlarına uygunluk denetimi | Storybook MCP ajanı: overlay CSS üretimi |
| Storybook decorator (globalTypes: theme, density, variant a–f) | FE lead: onay | Storybook MCP ajanı: decorator + toolbar |
| Pilot bileşen: TextField 6 varyantta | A11y gözden geçirici: focus-visible ve label denetimi | İki ajan: Figma set + matrix story |

Kabul kriterleri (P1):
- TextField 6 varyantta matrix story'de (varyant × tema × density) render oluyor.
- Varyant değişimi yalnız token overlay + minimum yapısal prop ile; davranış kodu tek.
- Focus-visible 6 varyantta da görünür ve yalnız border rengi değişimine dayanmıyor.

### P2 — Bileşen aileleri × A–F (Hafta 3–7)

Hedef: card → form → table → destekleyici sırasıyla dört ailenin Figma + kod + story üçlüsü.
Spesifikasyon kaynakları: [02](02-card-varyantlari.md), [03](03-form-varyantlari.md),
[04](04-table-varyantlari.md), [05](05-bilesen-varyantlari.md).

| Adım (aile başına tekrar) | İnsan sorumluluğu | MCP-ajan sorumluluğu |
|---|---|---|
| Aile spesifikasyonunun donması (02–05 ilgili dosya) | Tasarım lideri + Ürün sahibi: onay | — |
| Figma component set (tek set, `variant` prop A–F) | Tasarım lideri: görsel denetim | Figma MCP ajanı: set üretimi (Code Connect'li mevcut bileşeni yeniden yaratmak yasak) |
| Kod bileşenleri (headless + overlay) | FE lead: kod incelemesi | Storybook MCP ajanı: story/test iskeleti |
| Matrix story + play testleri (klavye, validation, bulk-select, inline-edit, aria-sort) | FE lead: senaryo onayı | Storybook MCP ajanı: üretim |
| axe fail-blocking (color-contrast, label, focus) + i18n story (de/tr/ar-RTL) | A11y gözden geçirici: fail analizi | Storybook MCP ajanı: koşum + rapor |
| Table için 10k satır sanallaştırma perf story | FE lead: profil analizi | Storybook MCP ajanı: ölçüm story'si |

Örnek Storybook prompt'u (tam katalog: [07](07-storybook-mcp-promptlari.md)); her aile
tamamlandığında matrix üretimi için kullanılır:

```text
Using the Storybook MCP: generate a matrix story for the Card family rendering every card
type across data-variant a-f, theme light/dark and density comfortable/standard/compact,
wired through the existing globalTypes decorators. Mark axe color-contrast, label and
focus rules as fail-blocking, and tag the stories for the visual regression matrix.
```

Kabul kriterleri (P2):
- Dört ailenin her biri için: Figma component set + kod + matrix story tamam.
- Aile başına play testleri yeşil; axe fail-blocking kuralları yeşil.
- Table: 10k satırda 60fps hedefi, main-thread work <50ms ölçülmüş ve raporlanmış.
- Karar Kapısı 1 (bkz. Bölüm 8) geçilmiş.

### P3 — Kompozisyon ekranları (Hafta 7–9)

Hedef: varyantları bileşen düzeyinden ekran düzeyine taşımak; varyant-içi tutarlılığı kanıtlamak.

| Adım | İnsan sorumluluğu | MCP-ajan sorumluluğu |
|---|---|---|
| 3 referans ekran içeriği (Kayıt Listesi, Kayıt Oluştur, Detay+Inline Edit) | Ürün sahibi: gerçekçi veri ve akış | — |
| Figma: 6-lı karşılaştırma canvas'ı (aynı ekran × A–F, 320px + 1440px) | Tasarım lideri: kompozisyon denetimi | Figma MCP ajanı: canvas üretimi |
| Kod: Storybook 6-lı karşılaştırma sayfası | FE lead: kod incelemesi | Storybook MCP ajanı: sayfa üretimi |
| Prototip bağlantıları (Figma) | Tasarım lideri: akış onayı | Figma MCP ajanı: bağlantılar |
| Görsel regresyon temel çizgisi (budanmış matris) | FE lead: matris alt kümesi onayı | Storybook MCP ajanı: baseline alma |

Kabul kriterleri (P3):
- 6-lı karşılaştırma canvas'ı Figma'da; Storybook karşılaştırma sayfası kodda hazır.
- Her ekran 320px'te yatay scroll'suz; tablo mobil stratejisi [04](04-table-varyantlari.md)'e uygun.
- Aynı varyantın card/form/table'ı aynı mikro-gramerle görünüyor (varyant-içi tutarlılık denetimi imzalı).

### P4 — Değerlendirme (Hafta 9–11)

Hedef: [08](08-degerlendirme-protokolu.md) protokolünü uygulayıp imzalı karara varmak.

| Adım | İnsan sorumluluğu | MCP-ajan sorumluluğu |
|---|---|---|
| Ölçümler: Storybook matrix + axe + Playwright perf | FE lead + A11y: sonuç doğrulama | Storybook MCP ajanı: koşum ve ham veri |
| 5-saniye / first-click kullanıcı mini-testleri | Tasarım lideri: moderasyon ve analiz | — (ajan yalnız materyal hazırlar) |
| Ağırlıklı skor kartının doldurulması (6 kriter + marka + domain uyumu) | Tüm roller: puanlama; Ürün sahibi: ağırlık onayı | Ajanlar: otomatik ölçülebilir hücrelerin doldurulması |
| Eleme turu → domain eşleme turu | Karar kurulu (4 rol) | — |
| Karar dokümanının yazımı ve imza | Ürün sahibi: nihai imza | — |

Kabul kriterleri (P4):
- Skor kartı tüm hücreleriyle dolu; ölçüm yöntemi her hücrede izlenebilir.
- Eleme ve domain eşleme turları tamamlandı; domain başına varyant ataması gerekçeli.
- İmzalı karar dokümanı yayımlandı. Karar Kapısı 2 (bkz. Bölüm 8) geçilmiş.

### P5 — Freeze ve terfi (Hafta 11–12)

Hedef: kararı kalıcılaştırmak; sistemin 2027 governance'ına devri.

| Adım | İnsan sorumluluğu | MCP-ajan sorumluluğu |
|---|---|---|
| Kazanan varyant(lar)ın default token'a terfisi | FE lead + Tasarım lideri: migrasyon planı | Storybook MCP ajanı: token yeniden eşleme |
| Code Connect eşlemesi (Figma ↔ kod) | FE lead: eşleme doğrulama | Figma MCP ajanı: eşleme üretimi |
| Kalan varyantlara arşiv flag'i (silme yok) | Tasarım lideri: arşiv kaydı | İki ajan: flag/etiketleme |
| CI kalite kapılarının kalıcılaştırılması | FE lead: pipeline onayı | Storybook MCP ajanı: konfigürasyon |
| v1.0 tag + governance devri | Ürün sahibi: devir onayı | — |

Kabul kriterleri (P5):
- Design system v1.0 tag'i atıldı; kazanan varyant(lar) default, kalanlar arşivde erişilebilir.
- Code Connect eşlemesi terfi eden bileşenlerde tam; CI kapıları (test-runner, görsel
  regresyon, token drift) kalıcı olarak zorunlu.
- 2027 governance planına devir dokümante edildi.

## 7. Zaman çizelgesi (başlangıç: 2026-08-17, Pazartesi)

Faz sınırlarındaki haftalar (2, 3, 7, 9, 11) bilinçli olarak örtüşür: yeni faz, önceki fazın
kabul kriterleri kapanırken ısınır.

| Hafta | Tarih aralığı | Faz | Ana iş | Kilometre taşı |
|---|---|---|---|---|
| 1 | 2026-08-17 – 2026-08-23 | P0 | Primitive + semantic + density token'ları; Figma Variables | — |
| 2 | 2026-08-24 – 2026-08-30 | P0 → P1 | Variant overlay a–f; kontrast matrisi; drift CI; data-variant altyapısı başlar | P0 kabul |
| 3 | 2026-08-31 – 2026-09-06 | P1 → P2 | Headless katman + decorator; TextField pilotu; card ailesi başlar | P1 kabul |
| 4 | 2026-09-07 – 2026-09-13 | P2 | Card ailesi × A–F (Figma + kod + story) | Card matrix yeşil |
| 5 | 2026-09-14 – 2026-09-20 | P2 | Form ailesi × A–F | Form matrix + play yeşil |
| 6 | 2026-09-21 – 2026-09-27 | P2 | Table ailesi × A–F; 10k satır perf story | Table matrix + perf raporu |
| 7 | 2026-09-28 – 2026-10-04 | P2 → P3 | Destekleyici bileşenler × A–F; referans ekran içerikleri | P2 kabul; Karar Kapısı 1 |
| 8 | 2026-10-05 – 2026-10-11 | P3 | 6-lı canvas (Figma) + karşılaştırma sayfası (kod), 320/1440 | — |
| 9 | 2026-10-12 – 2026-10-18 | P3 → P4 | Prototip bağlantıları; görsel regresyon baseline; ölçüm koşumları başlar | P3 kabul |
| 10 | 2026-10-19 – 2026-10-25 | P4 | Ölçümler + kullanıcı mini-testleri; skor kartı doldurulur | Skor kartı dolu |
| 11 | 2026-10-26 – 2026-11-01 | P4 → P5 | Eleme + domain eşleme; karar dokümanı; terfi hazırlığı | P4 kabul; Karar Kapısı 2 |
| 12 | 2026-11-02 – 2026-11-08 | P5 | Terfi, Code Connect, arşiv, CI kalıcılaştırma | v1.0 tag |

## 8. Karar kapıları

| Kapı | Zaman | Soru | Girdi | Olası çıktılar |
|---|---|---|---|---|
| KK-1 | P2 sonu (Hafta 7) | "Altı varyantın tamamı kompozisyon fazına taşınmalı mı?" | Aile başına matrix/play/axe sonuçları, perf raporu, uygulama maliyeti gözlemleri | (a) 6 varyantla devam; (b) teknik kabulde tekrarlanan fail veren varyant(lar) için erken eleme ÖNERİSİ kaydı — resmi eleme yine P4'tedir; (c) faz takvimi revizyonu |
| KK-2 | P4 sonu (Hafta 11) | "Hangi domain'e hangi varyant; freeze onaylanıyor mu?" | Doldurulmuş skor kartı, eleme + domain eşleme tur sonuçları, kullanıcı mini-test bulguları | (a) İmzalı karar: domain başına varyant ataması (tek kazanan şart değil); (b) çelişkisiz eksenlerde hibritleşme kararı ([08](08-degerlendirme-protokolu.md) hibrit kuralı); (c) yetersiz veri → P4 uzatması (maks 1 hafta, P5 sıkışır) |

Kapı kararları karar kurulunca (Tasarım lideri, FE lead, A11y gözden geçirici, Ürün sahibi)
verilir ve yazılı gerekçeyle kaydedilir; MCP-ajan çıktıları kapılarda yalnız kanıt girdisidir.

## 9. Risk kaydı

| ID | Risk | Etki | Olasılık | Önlem | Erken sinyal |
|---|---|---|---|---|---|
| R1 | Token drift: Figma Variables, tokens JSON ve CSS çıktısı birbirinden ayrışır | Yüksek — varyant karşılaştırması geçersizleşir | Orta | Tek doğruluk kaynağı (JSON); P0'dan itibaren CI drift kontrolü; Figma'ya yalnız MCP ajanı envanter-sonrası yazar; elle Figma değeri değişikliği yasak | Drift CI uyarısı; Figma'da JSON'da olmayan değer |
| R2 | Varyant kayması / scope creep: eksen dışı farklar sızar, "yedinci varyant" fiilen doğar | Yüksek — 12-eksen karşılaştırılabilirliği bozulur | Yüksek | Varyantlar YALNIZ 12 mikro-eksende farklılaşır; yeni varyant/renk/radius icadı yasak; PR şablonunda "hangi eksen?" alanı; eksen dışı fark tespitinde değişiklik reddi | Eksen tablosuna eşlenemeyen CSS farkı; brief'te olmayan değer talebi |
| R3 | Kontrast regresyonu: overlay kombinasyonlarında (özellikle dark mod, hover/selected state) AA altına düşüş | Yüksek — a11y kabulü ve yasal uygunluk | Orta | axe color-contrast fail-blocking; P0 kontrast matrisi state'leri de kapsar; #003399'un dark'ta metin rolüne atanmasını engelleyen lint; sarı üstü metin tokenı #080616'ya kilitli | axe raporunda yeni fail; matris dışı renk çifti kullanımı |
| R4 | Performans: 10k satır grid'de varyant overlay + sanallaştırma hedefi tutmaz (60fps, main-thread <50ms) | Orta-yüksek — A/C gibi grid-ağır varyant adayları haksız elenir veya gecikir | Orta | Perf story P2'de erken (Hafta 6); overlay yalnız CSS custom property (runtime JS maliyeti yok); Playwright perf ölçümü varyant başına ayrı; profil sonuçları KK-1 girdisi | Perf story'de kare düşüşü; scripting süresinde varyantlar arası anlamlı fark |
| R5 | i18n gecikmesi: de/tr/ar-RTL story'leri ve logical-properties dönüşümü sona bırakılır | Orta — P4'te i18n kriteri ölçülemez, karar sakatlanır | Orta | i18n story'leri aile kabul kriterinin parçası (P2 içinde, ertelenemez); CSS'te fiziksel yön (left/right) lint yasağı; Almanca uzama + Arapça RTL test zorunluluğu | Aile "tamam" ama i18n story yok; kodda left/right sızıntısı |
| R6 | Karar felci: 6 varyant arasında P4'te seçim yapılamaz, tartışma uzar | Orta — P5 ve v1.0 gecikir | Orta | 08'in ağırlıklı skor kartı ve iki-turlu yapısı (eleme → domain eşleme) önceden imzalı; "tek kazanan şart değil, domain başına atama meşru" ilkesi baskıyı düşürür; KK-2'de maks 1 hafta uzatma sınırı | Skor kartı ağırlıkları üstünde P4 içinde yeniden pazarlık; kapı toplantısının karara bağlanmadan dağılması |
| R7 | Figma–kod senkron kopması: component set ile kod bileşeni ayrışır, Code Connect eşlemesi çürür | Orta — P5 terfisi ve tasarım-kod güveni zedelenir | Orta | Mevcut Code Connect bileşeni varken yeniden yaratma yasak (06 kuralı); aile kabulünde Figma+kod birlikte incelenir; P5'te eşleme doğrulaması ayrı adım | Aynı bileşenin Figma ve Storybook görünümünde eksen dışı fark |
| R8 | MCP-ajan çıktısının denetimsiz kabulü: hatalı üretim (yanlış token, eksik state) fark edilmeden yayılır | Orta | Düşük-orta | Her ajan çıktısı için insan gözden geçirme zorunlu (Bölüm 6 ilkesi); ajanlara "önce envanter, sonra üretim" kuralı; üretim prompt'ları 06/07 kataloglarından, serbest doğaçlama değil | İncelenmemiş ajan PR'ının merge edilmesi; envantersiz yazım |

## 10. Dosya haritası

| Dosya | Tek cümle özet |
|---|---|
| [01-varyant-cercevesi.md](01-varyant-cercevesi.md) | Değişmezler, 12 mikro-eksen, A–F tam tanımları + eksen × varyant karşılaştırma matrisi, mühendislik (data-variant + token overlay + headless) ve Figma (component set + variant prop) modeli. |
| [02-card-varyantlari.md](02-card-varyantlari.md) | Card ailesi: anatomi, kart türleri (metric/KPI, entity, list-item, form-section, commerce) ve her tür için A–F eksen uygulaması, 320px davranışı, dark/light notları, yasaklar. |
| [03-form-varyantlari.md](03-form-varyantlari.md) | Form ailesi: bileşen seti ve state seti, A–F spesifikasyonu (özellikle input muafiyet alanı), validation davranış modeli, tek kolon kuralı, 320px ve RTL. |
| [04-table-varyantlari.md](04-table-varyantlari.md) | Data table/grid: bölge ve hücre tipleri, A–F satır/başlık/seçim kararları, mobil stratejiler, 10k satır sanallaştırma performans hedefi, density üçlüsü. |
| [05-bilesen-varyantlari.md](05-bilesen-varyantlari.md) | Destekleyici bileşenler (Button'dan CommandPalette'e) için A–F mikro-kararları, sarı buton kuralı (#FFB900 zemin + #080616 metin) ve glass'ın sınırlı kullanım alanı. |
| [06-figma-mcp-promptlari.md](06-figma-mcp-promptlari.md) | Figma MCP prompt kataloğu: envanter/audit, Variables kurulumu, component set üretimi, 6-lı karşılaştırma canvas'ı, prototip bağlantıları, Code Connect eşlemesi ve kullanım kuralları. |
| [07-storybook-mcp-promptlari.md](07-storybook-mcp-promptlari.md) | Storybook MCP prompt kataloğu: token senkron + decorator altyapısı, matrix story'ler, etkileşim/a11y/i18n/perf testleri ve CI kalite kapıları. |
| [08-degerlendirme-protokolu.md](08-degerlendirme-protokolu.md) | Seçim protokolü: 6 temel kriter + marka + domain uyumu, ağırlıklı skor kartı, ölçüm yöntemleri, eleme → domain eşleme → freeze turları ve hibritleşme kuralı. |

## Kabul kriterleri

- [ ] Amaç, kapsam (içi/dışı) ve teslimatlar (T1–T7) tanımlı; teslimatlar fazlara eşlenmiş.
- [ ] P0–P5 fazlarının her biri adım tablosu (insan + MCP-ajan iş bölümü) ve faz kabul kriterleriyle genişletilmiş.
- [ ] Zaman çizelgesi 2026-08-17 başlangıçlı, 12 haftalık, faz sınırları ve kilometre taşlarıyla tutarlı.
- [ ] Risk kaydı en az 6 riski (token drift, scope creep, kontrast regresyonu, performans, i18n gecikmesi, karar felci dahil) etki/olasılık/önlem/erken sinyal ile içeriyor.
- [ ] Karar kapıları KK-1 (P2 sonu) ve KK-2 (P4 sonu) soru/girdi/olası çıktılarla tanımlı.
- [ ] Varyant A–F tek tabloda özetlenmiş ve domain eşleşme hipotezi ayrı tabloda, "bağlayıcı değil" ibaresiyle verilmiş.
- [ ] Dosya haritası 01–08'in tamamına göreli link ve tek cümle özet veriyor.
- [ ] Tüm hex, px, rem, weight ve süre değerleri brief §1–§2 ile birebir aynı; yeni varyant/renk/radius yok; 12px yalnız "mutlak üst limit" bağlamında geçiyor.
- [ ] MCP prompt'ları İngilizce, `text` kod bloğunda ve üstlerinde Türkçe kullanım açıklamasıyla; emoji yok.
