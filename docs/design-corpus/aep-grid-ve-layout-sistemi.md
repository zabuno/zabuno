# AEP Design System — Grid ve Layout Sistemi (320px-First, AI-First Enterprise SaaS)

## TL;DR
- 320px-first, AI-first bir enterprise panelde doğru omurga: 8pt tabanlı (4pt alt-adımlı) spacing, mobilde 4 kolonlu / desktop'ta 12 kolonlu **fluid + fixed hibrit** grid, **media query ile sayfa iskeleti + container query ile bileşen davranışı**, `clamp()` ile akışkan kenar boşlukları ve **tek kolon önceliği**. Bu, IBM Carbon 2x Grid (8px mini unit), Material Design 3 (4/8/12 kolon) ve SAP Fiori/Ant Design/Adobe Spectrum'un sentezidir.
- Somut sayılar: margin `clamp(16px, 5vw, 72px)`, gutter 16px (mobil) → 24px (desktop), max içerik container 1296px, form ekranı max 720px, dokunma hedefi min 44px (WCAG 2.5.5 AAA), bottom nav max 5 öğe, satır uzunluğu 45–75 karakter (mobilde ~35–50ch); safe-area için `padding-bottom: max(16px, env(safe-area-inset-bottom))`.
- Prompt seti **A5–A8** (Figma MCP: layout token temeli, layout primitives, 320px şablonları, responsive + RTL) ve **B5–B8** (Storybook MCP: token senkronu, primitive story, viewport regresyon, AI-first makine-okur state) sabit kısıtları (Roboto, #FFB900 üzerine #080616, ≤8px radius, Phosphor ikon, emoji yasak) gömülü içerir; insan onayı olmadan main'e push yoktur, AI/bot yalnızca PR açar.

---

## Key Findings

1. **Container query üretime hazır ama tek başına yeterli değil.** Container size query'ler Chrome/Edge 105 (Ağustos 2022), Safari 16 (Eylül 2022), Firefox 110 (Şubat 2023) ile geldi; Baseline "newly available" Şubat 2023, **"Widely Available" ise Ağustos 2025** oldu. Doğru mimari "media query = sayfa iskeleti, container query = bileşen davranışı" ayrımıdır. Eski cihazlar için `@supports (container-type: inline-size)` ile progressive enhancement zorunludur.

2. **Enterprise design system'ler 320px'te tek/dar kolona yakınsıyor.** Material Design 3 mobilde 4 kolon; IBM Carbon sm breakpoint'te 4 kolon; SAP Fiori S boyutunda 4 kolon; Ant Design xs'te tek kolon (24/24); Salesforce Lightning mobile-first tek kolon stack. Yani "320px = 4 kolon (pratikte tek kolon davranışı)" endüstri fikir birliğidir ve kullanıcının istediği yaklaşımla birebir uyumludur.

3. **8pt taban + 4pt alt-adım en yaygın enterprise ritmidir.** IBM Carbon 8px "mini unit" üzerine kurulu; Atlassian Design System 8px base unit (`space.100`) kullanıp 2/4/6px ince ayar adımlarını (`space.025/.050/.075`) ekliyor. Atlassian'ın tam token skalası: `space.0`(0)→`space.025`(2px)→`space.050`(4px)→`space.075`(6px)→`space.100`(8px)→`space.150`(12px)→`space.200`(16px)→`space.300`(24px)→`space.400`(32px)→`space.600`(48px)→`space.1000`(80px). Kullanıcının istediği 4/8/12/16/24/32/48 skalası bu standartlarla uyumludur.

4. **`clamp()` akışkan ölçüler medya sorgusu sayısını azaltır** ve yüksek tarayıcı desteğiyle (dvh/svh birim ailesi için küresel destek ~%96,4) üretime hazırdır; ancak `rem` tabanını korumak (zoom erişilebilirliği) ve min 16px gövde metnini asla ihlal etmemek şarttır.

5. **Mobil viewport ve klavye yönetimi yeni CSS birimleriyle çözülür.** `dvh/svh/lvh` Chrome 108+, Firefox 101+, Safari 15.4+ ile geldi ve **Haziran 2025'te Baseline Widely Available** oldu; sticky yüzeyler için sabit px/rem, tam yükseklik alanlar için `svh` önerilir. Klavye için `interactive-widget=resizes-content` **yalnızca Chrome 108+/Firefox 132+'da** çalışır, iOS Safari desteklemez (WebKit standards-positions #65) — VisualViewport API fallback gerekir.

6. **AI-first arayüz = erişilebilirlik ağacı + kararlı data-attribute'lar.** AI ajanlar sayfayı ARIA snapshot / accessibility tree üzerinden "görür" (Playwright MCP, WebMCP yönü). Layout state'lerinin (`data-density`, `data-panel-state`, `aria-expanded`) hem görsel hem makine-okur senkron olması gerekir; aksi halde ajan bayat snapshot üzerinde çalışır (InfoWorld/Siteimprove).

7. **Safe-area ve dokunma hedefi 320px'te kritik.** `env(safe-area-inset-*)` **Ocak 2020'den beri Baseline** (Chrome 69+, Firefox 65+, Safari 11.1+); `viewport-fit=cover` meta gerekir. WCAG 2.5.5 (AAA) 44×44 CSS piksel, WCAG 2.5.8 (AA, WCAG 2.2) 24×24 CSS piksel, Material 48dp önerir; kullanıcının 44px hedefi AAA seviyesiyle uyumlu doğru bir taban seçimdir.

---

## Details

### 1. Grid/Layout Sisteminin Felsefesi

**Bu nedir / ne işe yarar?** Grid ve layout sistemi, AEP panelinin (Ebp/Eop/Ebm/ERX modülleri) tüm ekranlarında içeriğin nereye, hangi ölçüde ve hangi ritimde yerleşeceğini belirleyen tek doğruluk kaynağıdır. Amacı: 320px'lik eski/küçük cihazlarda bilgiyi eksiksiz ve okunur göstermek; daha büyük ekranlarda aynı içeriği bozup yeniden tasarlamadan akışkan şekilde genişletmek.

**Temel ilkeler ve karar gerekçeleri:**

- **Neden 4pt/8pt spacing?** İnsan gözü ve GPU pikselleri 8'e bölünebilir ritimden hoşlanır; tüm boşluk/ölçüler 8'in (gerektiğinde 4'ün) katı olunca tasarımcı "keyfi sayı" seçmez, sistem öngörülebilir olur ve token'lar temiz eşlenir. IBM Carbon'un tüm geometrisi 8px mini unit üzerine kuruludur; Atlassian'ın açık ifadesiyle "spacing sistemimiz 8 piksellik bir taban birimi etrafında kuruludur."
- **Neden fluid + fixed hibrit?** Saf fluid (her şey %) çok geniş ekranlarda satırları okunamaz uzunluğa çıkarır; saf fixed (her şey px) küçük ekranlarda taşar. Doğru cevap: kenar boşlukları ve içerik genişliği `clamp()`/% ile akışkan, ama bir **max container** (ör. 1296px — Atlassian fixed-wide değeriyle hizalı) ve form için **max 720px** ile sınırlı. Adobe Spectrum bu modeli açıkça iki tipte tanımlar: fluid grid (%100 genişlik, uygulama/UI için) ve fixed grid (max genişlik, okunabilirlik için).
- **Neden tek kolon önceliği?** 320px'te iki kolon = her kolon ~150px = okunamaz. Tek kolon; dikey akış, tam genişlik dokunma hedefleri ve doğal okuma sırası sağlar. Çok kolon yalnızca ≥768px'te devreye girer.
- **Content-out (içerikten dışa) vs canvas-in (tuvalden içe):** Bu sistem **content-out**tur. Yani önce 320px'te içeriğin doğal minimumu tasarlanır (en küçük okunur birim), sonra ekran büyüdükçe boşluk ve kolon eklenir. Canvas-in (önce 1440px masaüstü tasarlayıp küçültmek) 320px hedefli bu projede yasaktır çünkü küçük ekranı ikincil bırakır. Bu, Material 3'ün "breakpoint'lerde sabit boyuta kilitleme; aralarda esnesin" ilkesiyle örtüşür.
- **Container query vs media query kararı:** Sayfanın makro iskeleti (header'ın açılıp yan panelin görünmesi, form'un 1→2 kolona geçmesi) **media query** ile; bir kartın/panelin içindeki bileşenin dar mı geniş mi olduğuna göre yeniden düzenlenmesi **container query** ile yapılır. Bu, bileşenleri (Option Information Panel gibi) farklı yuvalarda taşınabilir kılar.

**Aktörler (net ayrım):**
- **Sistem ne yapar:** token'ları CSS custom property'ye derler, breakpoint'lerde kolon/gutter/margin'i uygular, `clamp()` ile akışkan ölçüleri hesaplar, RTL'de logical property'lerle otomatik ayna yapar, safe-area padding'i enjekte eder.
- **Tasarımcı ne yapar:** Figma Variables'ta grid token'larını tanımlar, hangi bileşenin hangi breakpoint'te kaç kolon kaplayacağını belirler, içerik önceliğini (progressive disclosure sırası) kurar. Keyfi piksel girmez; token seçer.
- **AI ajan ne yapar:** rol/kullanıma göre layout'u uyarlar (Adaptive AI: yoğunluk modunu değiştirir, paneli açar/kapar, bento hücrelerini yeniden sıralar), ama yalnızca makine-okur state'leri (`data-*`, `aria-*`) üzerinden ve yalnızca PR açarak — asla doğrudan main'e yazmadan.

**Ne yapar / ne yapmaz:**
- **Yapar:** 320px'te tek kolon garanti eder; 8pt ritmi zorlar; RTL'yi logical property ile aynalar; sticky yüzey çakışmasını yönetir; container query destekli cihazda bileşen düzeyi uyum sağlar.
- **Yapmaz:** yatay scroll üretmez (tablo görünümü hariç); 16px altına gövde metni düşürmez; keyfi (token dışı) boşluk kabul etmez; container query'yi fallback olmadan tek dayanak yapmaz; emoji/beyaz-üzeri-sarı gibi kısıt ihlallerine izin vermez.

### 2. Yüzde Ölçüleri ve Grid Ölçüleri (somut sayılar)

**320px base.** Kullanıcının hedefi doğrultusunda 320px'te **4 kolon** kullanılır. Kenar boşluğu (margin) 16px, gutter 16px. Hesap: 320 − (2×16 margin) = 288px içerik; 288 − (3×16 gutter) = 240px ÷ 4 = her kolon 60px. Yüzde karşılığı: margin ≈ %5, gutter ≈ %5, kolon ≈ %18,75. Pratikte 320px'te layout tek kolon davranır (tüm bileşenler 4/4 span); 4 kolon yalnızca ikonografik/kart ızgara hizası içindir. (Karşılaştırma: Material 3 mobilde 16dp margin + 4 kolon; IBM Carbon sm'de 16px padding + 4 kolon önerir.)

Aşağıdaki tablo her breakpoint için kolon/gutter/margin/max-container değerlerini verir. Değerler Material 3, IBM Carbon, SAP Fiori ve Atlassian sentezidir; margin'ler breakpoint içinde sabit, kolonlar fluid'dir.

| Breakpoint | Genişlik | Kolon | Gutter | Margin | Max container |
|---|---|---|---|---|---|
| xxs (native) | 320px | 4 | 16px | 16px | akışkan (%100) |
| xs (phone+) | 430px | 4 | 16px | 16px | akışkan |
| sm (mini tablet) | 768px | 8 | 24px | 24px | akışkan |
| md (tablet/laptop) | 1024px | 12 | 24px | 32px | akışkan |
| lg (desktop) | 1440px | 12 | 24px | `clamp(32px,5vw,72px)` | 1296px (ortalanır) |

**clamp() ve % tabanlı fluid formüller (kopyalanabilir):**
- Kenar boşluğu: `margin-inline: clamp(16px, 5vw, 72px);`
- Bölüm dolgusu: `padding-block: clamp(16px, 4vw, 48px);`
- İçerik genişliği: `width: clamp(320px, 90%, 1296px);`
- Form genişliği: `max-width: min(100%, 720px);`
- Akışkan tipografi (min 16px korunur): `font-size: clamp(1rem, 0.94rem + 0.3vw, 1.125rem);`
- Genel slope/intercept formülü: `preferred = min + (max − min) × (100vw − minVP) / (maxVP − minVP)`; slope = (maxSize − minSize) / (maxVP − minVP), intercept = −(minVP × slope) + minSize.

**Spacing scale ↔ grid ilişkisi.** Skala 4/8/12/16/24/32/48 (token: `space.050/100/150/200/300/400/600`). Gutter'lar bu skaladan seçilir (16 ve 24). Bileşen iç dolgusu 8–16px, bölüm arası 24–48px. Böylece grid gutter'ı ile bileşen boşluğu aynı ritimden gelir — bu, Carbon'un "margin ve padding her zaman sabit mini unit katları" ilkesiyle aynıdır.

**Data-dense panel satır yüksekliği ve dokunma hedefi.** Yoğunluk modları: comfortable 48px satır, compact 40px, dense 32px. Ancak **dokunulabilir** her hedef (buton, satır aksiyonu, checkbox) minimum 44px efektif alan korur (gerekirse görünmez padding ile). WCAG 2.5.5 AAA tam metni: "İşaretçi girdileri için hedef boyutu en az 44 × 44 CSS piksel olmalıdır" (eşdeğer/inline/kullanıcı-ajanı/temel istisnalar hariç). Material 48dp önerir. 44px taban + 8px aralık güvenli sentezdir. Dense modda görsel satır 32px olsa da dokunma hedefi padding ile 44px'e çıkarılır. (Not: WCAG 2.2 AA seviyesi 2.5.8 yalnızca 24×24 CSS piksel ister; 44px daha katı ve doğru tercihtir.)

**Bento grid (dashboard) 320px kuralı.** Desktop'ta 12 kolonlu bento; hücreler 4/6/8/12 kolon span. 768px'te 2 kolonlu ızgaraya, 320–767px'te **tek kolonlu dikey yığına** iner (`grid-template-columns: 1fr`). Kural: hero hücre önce, sonra öncelik sırasına göre; `grid-auto-flow: dense` mobilde kapatılır (DOM sırası = okuma sırası korunur — erişilebilirlik ve odak sırası için kritik). En fazla 2 hero hücre per bölüm (üç hero birbirini iptal eder, göz çapa noktası bulamaz).

**Sektör standartları karşılaştırması ve sentez:**

| Sistem | Kolon (mobil→desktop) | Taban birim | 320px yaklaşımı |
|---|---|---|---|
| Material Design 3 | 4 / 8 / 12 | 8dp | 4 kolon, 16dp margin |
| IBM Carbon 2x | 4 / 8 / 16 | 8px mini unit | sm=4 kolon, 16px padding, max 1584px |
| SAP Fiori | 4 / 8 / 12(16) | rem, 12-kolon akış | S=4 kolon, tabloda pop-in |
| Ant Design | 24 (tek→çok box) | flexbox, 24-bölüm | xs=24/24 (tek kolon) |
| Salesforce Lightning | 12 (kesir tabanlı) | flexbox, mobile-first | tek kolon stack |
| Adobe Spectrum | 12 | fluid+fixed | XS=12 kolon, 16px gutter |
| Atlassian | 12 | 8px base | mobil (xxs) dahil, fixed-wide 1296px |

**Sentez (kullanıcının kısıtına en uygun):** 320px'te 4 kolon (Material/Carbon/Fiori fikir birliği), 8px taban ritmi (Carbon/Atlassian), 768'de 8 kolon, 1024+'ta 12 kolon, `clamp()` akışkan margin + 1296px max container (Atlassian fixed-wide ile hizalı). Ant Design'ın 24-kolonu enterprise yoğunluk için güçlü ama tasarımcı yükü fazla; 12 kolon (mobilde 4'e inen) daha yönetilebilir doğrudur. Carbon'un 16 kolonu tam-genişlik ürün kabukları için ideal ama AEP'nin çok panelli düzeninde 12 kolon daha esnek.

### 3. Grid Standartları

**Layout token'larının Figma Variables modeli.** Ayrı bir "Layout" koleksiyonu; mode = breakpoint (xxs/xs/sm/md/lg). Token grupları:
- `grid.columns` (4/4/8/12/12), `grid.gutter` (16/16/24/24/24), `grid.margin` (16/16/24/32/clamp), `grid.max-container` (—/—/—/—/1296)
- `space.*` (0/2/4/6/8/12/16/20/24/32/40/48/64/80 → `space.0…space.1000`)
- `radius.*` (max 8px, üst sınır 12px; input hariç), `radius.input` (ayrı, daha büyük)
- `z.*` katmanları: `z.base=0`, `z.sticky=100`, `z.header=200`, `z.bottomnav=200`, `z.panel=300`, `z.overlay=400`, `z.modal=500`, `z.toast=600`
- `safe.top/right/bottom/left` → `env(safe-area-inset-*)` eşlenir.

Bunlar Style Dictionary ile CSS custom property'ye derlenir (DTCG `$type: dimension`). Figma Variables export JSON → SD-Transforms preprocessor (spacing→dimension dönüşümü) → `:root` custom property + `[data-theme]`/`[data-density]` override. Not: Figma'nın native variable export'u W3C DTCG formatını birebir izlemez; alias'ları çözmek için custom parser/SD-Transforms gerekir.

**Layout bileşen mimarisi (primitives):**
- `PageShell` — grid-template-areas ile header/nav/content/panel iskeleti; `min-block-size: 100svh`.
- `AppHeader` (mega header) — çok katmanlı; `position: sticky; top: 0; padding-top: env(safe-area-inset-top)`.
- `BottomNav` — yalnızca <768px, max 5 öğe, `padding-bottom: max(8px, env(safe-area-inset-bottom))`.
- `OptionGalleryPanel` (sol) — collapsible, filtrelenebilir; <1024px'te off-canvas drawer.
- `OptionInformationPanel` (sağ) — context-aware, motion-first; <1024px'te bottom sheet.
- `ContentArea` — `width: clamp(320px, 90%, 1296px)`, container context (`container-type: inline-size`).
- `BentoGrid` — 12→2→1 kolon; `gap: var(--grid-gutter)`.
- `StackLayout` — tek boyutlu dikey yığın, `gap` token'lı (320px varsayılanı).
- `MegaFooter` — çok katmanlı, tam genişlik, container query ile kolon sayısı.

**RTL mirroring kuralları.** Tüm yönlü ölçüler logical property ile yazılır: `margin-inline-start`, `padding-inline`, `inset-inline-start`, `border-start-start-radius`, `text-align: start`, `inline-size`/`block-size`. `[dir="rtl"]` override'ı gerekmez — sol panel RTL'de otomatik sağa geçer (MDN: RTL'de `inline-start` otomatik olarak sağa eşlenir). İkonların yönlü olanları (ok, chevron) `transform: scaleX(-1)` ile aynalanır. Almanca uzun kelime taşması için `overflow-wrap: anywhere` + `hyphens: auto` + esnek min-width.

**Sticky/fixed yüzey çakışma yönetimi (320px).** Aynı anda header (sticky top), bottom nav (fixed bottom), form footer (sticky bottom) ve bulk actions bar çakışabilir. Kurallar: (1) tek seferde tek alt-yüzey görünür — bulk actions bar açıkken bottom nav gizlenir; (2) içerik alanına `scroll-padding-block: var(--header-h) var(--bottomnav-h)` verilir ki sticky altında kalmasın; (3) form footer, klavye açıkken bottom nav'ın yerine gelir; (4) z-index token'ları çakışmayı deterministik çözer. MDN'in sticky footer + safe-area örneği: `footer { position: sticky; bottom: 0; padding: 1em 1em calc(1em + env(safe-area-inset-bottom)); }`.

**Klavye açıldığında viewport davranışı.** `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">`. MDN: "`interactive-widget` özelliğini `resizes-content` yaparak sayfa düzeninin sanal klavyenin varlığına uyum sağlamasını sağlayabilirsiniz." `resizes-content` Chrome 108+/Firefox 132+'da layout'u küçültür; **iOS Safari desteklemez**, bu yüzden `window.visualViewport` `resize` olayıyla aktif input'u görünür tutan JS fallback gerekir. Tam yükseklik alanlar `100svh`, sticky yüzeyler sabit `rem` kullanır (dvh kayması önlenir).

**Scroll stratejisi.** Tek ana scroll konteyneri (content area) tercih edilir; iç içe scroll'dan kaçınılır. Yan paneller off-canvas'ta kendi scroll'una sahiptir. `overscroll-behavior: contain` ile scroll zinciri kırılır. Tablo görünümü tek istisnadır: `overflow-x: auto` + `position: sticky; left: 0` ilk kolon + kenar gölge (kaydırılabilirlik işareti — NNG'nin karşılaştırma tabloları için önerdiği desen).

### 4. 320px Özel Kurallar

iPhone 4 / Galaxy S4 sınıfı cihazlar düşük DPI, sınırlı RAM/CPU ve eski tarayıcı olasılığı taşır. Kurallar:
- **Tek kolon zorunluluğu** <768px; **yatay scroll yasağı** (tablo hariç).
- **Bottom navigation** max 5 öğe; her öğe min 44px; ikon + kısa etiket.
- **Progressive disclosure:** accordion/collapsible ile bilgi katmanlama; ekranda aynı anda en fazla 1 birincil aksiyon.
- **Skeleton loading** düşük CPU'da spinner yerine tercih edilir (layout shift önler).
- **16px min font'un satır uzunluğu etkisi:** 320px − 32px margin = 288px içerik. 16px Roboto'da bu ~32–36 karakter/satır demektir; hedef mobilde 45–50ch üstü değil, tipik enterprise metin için 35–50ch, `max-width: 60ch` ile üst sınır. Genel kural (Bringhurst): 45–75 karakter/satır ideal, mobilde 30–50 kabul edilebilir; WCAG 1.4.8 non-CJK için ≤80. Uzun metin bölümlerinde `text-wrap: pretty`.
- **safe-area-inset'ler:** `viewport-fit=cover` + `env(safe-area-inset-*)` tüm sticky/fixed yüzeylerde; min taban için `max(16px, env(...))`.
- **Container query fallback:** eski cihaz tarayıcıları container query desteklemez. Strateji: taban stiller mobil-first tek kolon (query'siz zaten çalışır) → `@supports (container-type: inline-size)` ile bileşen düzeyi geliştirme → desteklemeyen tarayıcı media query fallback alır (`@supports not (container-type: inline-size) { @media (min-width:…) {…} }`). Subgrid için `@supports (grid-template-rows: subgrid)` — Subgrid **15 Mart 2026'da Baseline Widely Available** oldu (Firefox 71/2019, Safari 16/2022, Chrome-Edge 117/2023); desteklemeyen tarayıcı normal nested grid görür.

**Güvenli / riskli / enterprise doğrusu / pratik öneri (kritik grid kararları):**

- **Container query kullanımı:** *Riskli:* tüm responsive'i yalnızca container query'ye bağlamak (eski cihazda kırılır). *Güvenli:* sadece media query. *Enterprise doğrusu:* media query = iskelet + container query = bileşen, `@supports` fallback ile. *Pratik öneri:* AEP için hibrit — 320px taban query'siz çalışsın, container query bir geliştirme katmanı olsun.
- **Fluid vs fixed:** *Riskli:* saf fluid (okunamaz satırlar). *Güvenli:* sabit breakpoint px. *Enterprise doğrusu:* `clamp()` fluid + max container. *Pratik öneri:* margin/padding `clamp()`, kolon fluid, içerik 1296px / form 720px cap.
- **Subgrid:** *Güvenli:* nested grid. *Enterprise doğrusu:* `@supports` ile subgrid geliştirme. *Pratik:* kart hizası için subgrid, fallback nested grid.
- **Container style query (custom property sorgulama):** *Riskli:* üretimde tek dayanak (Chrome 111+/Safari 18+ destekler, Firefox henüz desteklemiyor). *Enterprise doğrusu:* yoğunluk/tema geçişini `data-density`/`data-theme` ile yap, style query'yi opsiyonel katman bırak. *Pratik:* size query'ler güvenli, style query erteleme.

### 5. AI-First Makine-Okur Layout State'leri

AI ajanlar arayüzü accessibility tree / ARIA snapshot üzerinden okur (Playwright MCP, WebMCP yönü). "Ajan hata"larının çoğu akıl yürütme değil, eksik/muğlak semantik kaynaklıdır (Siteimprove RNS modeli: Role–Name–State). Bu yüzden layout state'leri hem görsel hem makine-okur senkron olmalı:
- Kararlı, insan-okur `data-*` seçicileri (build-hash'li class değil): `data-testid`, `data-density="comfortable|compact|dense"`, `data-panel="gallery|info"`, `data-panel-state="open|collapsed"`, `data-layout="stack|bento|table|cards"`.
- ARIA senkronu: panel `aria-expanded`, aktif nav `aria-current="page"`, canlı bölge `aria-live` yoğunluk/panel değişiminde.
- İlke (InfoWorld): "State görünür olmalı — görsel değişen her state accessibility ağacına da yansımalı, yoksa ajan bayat snapshot üzerinde çalışır." Kimlik ver]erini kararlı tut (ör. `checkout.submit_order`), refactor/redesign'dan sağ çıksın.
- Adaptive AI yalnızca bu state'leri değiştirir (yoğunluk, panel, bento sırası) ve PR açar; asla main'e doğrudan yazmaz.

---

## PROMPT SETİ

### BÖLÜM A — Figma MCP Prompt'ları (devam: A5–A8)

**A5 — Layout/Grid Token Temeli**
```
Rol: AEP Design System için Figma Variables layout token temeli kur.
Bağlam: 320px-first, AI-first enterprise SaaS panel (Ebp/Eop/Ebm/ERX).
Sabit kısıtlar: Roboto (min 400, min gövde 1rem/16px); primary #FFB900 üzerine
DAİMA #080616 metin (asla beyaz); secondary #1E3A8A; dark bg #080616;
border radius max 8px (üst sınır 12px), input hariç; emoji YASAK;
ikon Phosphor (öncelik) / FontAwesome SVG. Next.js/Supabase önerme.
Görev:
1) "Layout" adında yeni Variables koleksiyonu; modes: xxs(320), xs(430),
   sm(768), md(1024), lg(1440).
2) Token grupları ve değerleri:
   grid.columns = 4/4/8/12/12
   grid.gutter  = 16/16/24/24/24 (px)
   grid.margin  = 16/16/24/32/ (lg: clamp(32,5vw,72))
   grid.maxContainer = —/—/—/—/1296px
   space = 0,2,4,6,8,12,16,20,24,32,40,48,64,80 → space.0..space.1000
   radius = radius.sm 8, radius.md 12; radius.input ayrı (daha büyük ok)
   z = base 0, sticky 100, header 200, bottomnav 200, panel 300,
       overlay 400, modal 500, toast 600
   safe = top/right/bottom/left → env(safe-area-inset-*) eşlenecek
3) Tüm dimension token'ları DTCG $type: dimension; alias yapısı primitive→semantic.
4) Light/Dark mode için yalnızca renk/z gölge; ölçüler mode'dan bağımsız.
Çıktı: Variables tablosu + isimlendirme + Style Dictionary export notu.
Mock data doldurma; yapı ve mantığı ver.
```

**A6 — Layout Primitives Bileşen Kiti**
```
Rol: A5 token'larını kullanan Figma layout primitive bileşenleri üret.
Sabit kısıtlar: (A5 ile aynı — Roboto, #FFB900/#080616, radius≤8px, Phosphor,
emoji yasak, 320px-first).
Bileşenler (her biri variant + auto-layout, token bağlı):
- PageShell: grid-areas header/nav/content/panel; min-block-size 100svh.
- AppHeader (mega header): çok katmanlı; sticky top; padding-top safe.top.
- BottomNav: <768px; max 5 öğe (fazlasını engelle); item min 44px;
  padding-bottom max(8, safe.bottom).
- OptionGalleryPanel (sol): collapsible+filtre; <1024 off-canvas drawer.
- OptionInformationPanel (sağ): context-aware, motion-first; <1024 bottom sheet.
- ContentArea: clamp(320,90%,1296); container context.
- BentoGrid: 12→2→1 kolon variant'ları.
- StackLayout: dikey yığın, gap token'lı.
- MegaFooter: çok katmanlı, tam genişlik.
Kurallar: tüm yönlü ölçüler logical (inline/block); dokunma hedefi min 44px;
radius input hariç ≤8px; renkte #FFB900 üzerine #080616.
Çıktı: bileşen ağacı + variant matrisi + token eşlemesi. Gereksiz mock yok.
```

**A7 — 320px Sayfa Şablonları (Page Templates)**
```
Rol: A6 primitive'leriyle 320px-first sayfa şablonları kur (hi-fi iskelet).
Sabit kısıtlar: (A5). Birincil hedef 320px doğru; diğerleri adaptive fluid.
Şablonlar:
1) Dashboard (Bento): 320px tek kolon dikey yığın → 768 iki kolon →
   1024+ 12 kolon bento; en fazla 2 hero hücre; grid-auto-flow dense
   mobilde kapalı (DOM=okuma sırası).
2) Form ekranı: 320 tek kolon; ≥768 max 2 kolon; ≥1200 içerik max 720px;
   sticky form footer (klavye açılınca bottom nav yerine gelir).
3) Data table: 320–767 kart listesine dönüşür; ≥768 tablo + sticky ilk kolon
   + overflow-x auto (tek yatay scroll istisnası).
4) Hero + Option Gallery (sol) + Option Information (sağ): mobilde paneller
   off-canvas/bottom sheet; hero clamp() dolgulu.
Kurallar: yatay scroll yasağı (tablo hariç); satır uzunluğu max 60ch;
progressive disclosure (accordion); skeleton loading slotları.
Çıktı: her şablon için 320/768/1024 kırılım + primitive kullanımı.
```

**A8 — Responsive Davranış ve RTL Mirroring**
```
Rol: A5–A7 için responsive kurallar ve RTL/i18n davranışını tanımla.
Sabit kısıtlar: (A5). RTL/Arapça + Almanca uzun kelime toleransı zorunlu.
Görev:
1) Breakpoint davranış matrisi: her primitive'in xxs/xs/sm/md/lg'de kolon
   span, görünürlük, off-canvas durumu.
2) RTL: tüm ölçüler logical property (margin-inline-start, inset-inline-start,
   border-start-start-radius, text-align:start, inline-size); yönlü ikon
   scaleX(-1); [dir=rtl] override GEREKMEZ ilkesi.
3) Almanca: overflow-wrap anywhere + hyphens auto + esnek min-width; buton
   metni taşmada 2 satıra iner, kesilmez.
4) Density modları: comfortable/compact/dense satır 48/40/32; dokunma hedefi
   her modda min 44px (görünmez padding).
5) Safe-area: viewport-fit=cover; sticky/fixed yüzeylerde env(safe-area-inset-*).
6) AI-first: data-density, data-panel-state, aria-expanded state eşlemeleri.
Çıktı: kural tablosu + logical property haritası. Mock doldurma.
```

### BÖLÜM B — Storybook MCP Prompt'ları (devam: B5–B8)

Stack: Storybook 8 (Vite + React + TypeScript + SCSS). Token kaynağı Figma Variables export JSON → Style Dictionary → CSS custom property. Next.js YOK. CI'da insan onayı olmadan main'e push yok; AI/bot yalnızca PR açar.

**B5 — Layout Token Senkronu + Grid CSS Altyapısı**
```
Rol: Figma Layout Variables'ı Storybook 8'e senkronla ve grid CSS altyapısı kur.
Sabit kısıtlar: Roboto (min 16px gövde), #FFB900 üzerine #080616, radius≤8px,
Phosphor ikon, emoji yasak, 320px-first. Next.js/Supabase kullanma.
Görev:
1) Style Dictionary config: Figma export JSON → build/tokens.css (:root +
   [data-theme=dark] + [data-density]). DTCG dimension desteği (SD-Transforms;
   Figma alias'larını çözmek için custom parser).
2) SCSS grid katmanı: --grid-columns/gutter/margin/max-container custom
   property'leri; .l-grid (display:grid, grid-template-columns:
   repeat(var(--grid-columns),1fr), gap: var(--grid-gutter)).
3) clamp() yardımcıları: --space-fluid-md: clamp(16px,4vw,48px);
   --margin-fluid: clamp(16px,5vw,72px) vb.
4) Container context util: .l-container { container-type: inline-size }.
5) Logical property mixin'leri (RTL için).
6) @supports fallback: (container-type: inline-size) yoksa media query;
   (grid-template-rows: subgrid) yoksa nested grid.
Çıktı: config + SCSS + tokens.css yapısı. Somut değerler A5'ten. Mock yok.
```

**B6 — Layout Primitive Story'leri**
```
Rol: A6 primitive'leri için CSF3 story'leri yaz (Storybook 8).
Sabit kısıtlar: (B5). Her primitive TypeScript + SCSS, token bağlı.
Görev:
1) Her primitive (PageShell, AppHeader, BottomNav, OptionGalleryPanel,
   OptionInformationPanel, ContentArea, BentoGrid, StackLayout, MegaFooter)
   için CSF3 Meta + Story'ler.
2) args: density (comfortable/compact/dense), dir (ltr/rtl), theme (light/dark),
   panelState (open/collapsed).
3) play function: panel aç/kapa etkileşimi + aria-expanded doğrulaması
   (@storybook/test, testing-library).
4) A11y: her story'de min 44px dokunma hedefi ve renk kontrastı (#FFB900 üzerine
   #080616) assert.
5) BottomNav story'de 5 öğe sınırı testi.
Çıktı: .stories.tsx iskeletleri + play fonksiyonları. Gereksiz mock data yok;
yapı ve mantık.
```

**B7 — 320px Viewport Regresyon ve Responsive Story'leri**
```
Rol: 320px-first responsive görsel regresyon ve viewport story'leri kur.
Sabit kısıtlar: (B5).
Görev:
1) .storybook/preview: özel viewport'lar — xxs 320x568, xs 430, sm 768,
   md 1024, lg 1440.
2) Her sayfa şablonu (A7: Dashboard/Form/DataTable/Hero) için her viewport'ta
   story; DataTable 320'de kart, 768'de tablo doğrulaması (play ile).
3) Container query testi: aynı bileşen dar/geniş yuvada; @container davranışı.
4) Görsel regresyon: Chromatic veya Playwright screenshot; 320px baseline.
5) Yatay scroll yasağı testi: play'de document.scrollingElement.scrollWidth
   <= clientWidth assert (tablo story hariç).
6) svh/safe-area: sticky header+bottom nav çakışma story'si.
Çıktı: preview config + story matrisi + regresyon setup. Mock doldurma.
```

**B8 — AI-First Makine-Okur Layout State'leri ve Test + Kalite Kapıları/CI**
```
Rol: AI-first makine-okur layout state'lerini test et ve CI kalite kapıları kur.
Sabit kısıtlar: (B5). CI: insan onayı olmadan main'e push YOK; AI/bot yalnızca
PR açar.
Görev:
1) Makine-okur state story'leri: data-density, data-panel-state, data-layout,
   aria-expanded, aria-current; görsel↔ARIA senkron assert (play + testing-library).
2) axe (a11y): @storybook/addon-a11y + test-runner (axe-playwright); WCAG AA
   ihlali = fail; 44px hedef ve kontrast dahil (injectAxe/checkA11y).
3) Container query regresyon: subgrid @supports fallback testi.
4) CI (GitHub Actions vb.): storybook build --test; Chromatic/Playwright görsel;
   axe; TypeScript + lint kapıları. Tümü PR'da zorunlu; main branch protection;
   bot yalnızca PR açar, merge insan onayı ister.
5) AI ajan sözleşmesi: layout state'leri yalnızca data-*/aria-* üzerinden
   değiştirilir; her değişiklik PR + story + görsel diff üretir.
Çıktı: test dosyaları + CI workflow iskeleti + branch protection notu. Mock yok.
```

---

## Recommendations

1. **Önce token temelini kilitle (A5 + B5).** Grid/space/z/safe token'ları tek koleksiyonda modellenmeden bileşen yazma. Eşik: Figma Variables → Style Dictionary → `tokens.css` pipeline'ı yeşil olduğunda A6/B6'ya geç.
2. **320px'i "birincil geliştirme viewport'u" yap.** Storybook varsayılan viewport'unu 320×568 ayarla; her PR'da 320px Chromatic baseline zorunlu. Eşik: yatay scroll testi (tablo hariç) geçmeden merge yok.
3. **Container query'yi geliştirme katmanı olarak ekle, dayanak yapma.** Taban tek kolon query'siz çalışsın; `@supports` ile katmanla. Eşik: eski tarayıcı (container query yok) simülasyonunda layout bozulmuyorsa onayla.
4. **Dokunma hedefi ve satır uzunluğunu CI'da zorla.** 44px min + 45–75ch (mobilde ~35–50ch) axe/özel kural. Eşik: ihlal = fail.
5. **AI state'lerini erişilebilirlik ağacına bağla.** `data-*` + `aria-*` senkronu olmadan Adaptive AI'ı devreye alma. Eşik: play testinde görsel↔ARIA senkron assert geçmeli.
6. **Klavye/safe-area davranışını gerçek cihazda test et.** `interactive-widget` iOS'ta çalışmadığından VisualViewport fallback şart. Eşik: iPhone SE/eski Android'de form footer klavye üstünde kalıyorsa onayla.

**Kararı değiştirecek eşikler:** Container query global desteği hedef kitlede %98'i geçerse media query fallback'ini sadeleştir. 320px trafiği ölçümlerde %5 altına düşerse kolon tabanını yeniden değerlendir (yine de tek kolon mobil öncelik kalır). Container style query Firefox'a gelip Baseline olursa yoğunluk/tema geçişini style query'ye taşımayı değerlendir.

## Caveats
- **Kaynak niteliği:** Grid sayıları (Material 3, IBM Carbon, SAP Fiori, Ant Design, Adobe Spectrum, Atlassian) resmî dokümanlardan; `clamp()`/dvh/subgrid/container query destek durumları MDN, web.dev Baseline ve 2025–2026 tarihli teknik bloglardan. Bazı "2026 trend" ifadeleri (bento yaygınlığı, container style query benimsenmesi) tahmin/öngörü içerir; kesin gerçek gibi sunulmamalıdır.
- **interactive-widget=resizes-content** yalnızca Chrome 108+/Firefox 132+; iOS Safari desteklemez — JS (VisualViewport) fallback zorunlu.
- **Container style query** (custom property sorgulama) Chrome 111+/Safari 18+ ile destekli; Firefox henüz desteklemiyor — üretimde tek dayanak yapma, size query'ler güvenli.
- **env(safe-area-inset-*)** bazı iOS sürümlerinde portrait'te 0px dönebilir (bilinen kenar durum); `max()` ile min padding tabanı önerilir.
- **320px eski cihaz + eski tarayıcı** kombinasyonu container query/subgrid desteklemeyebilir; progressive enhancement olmadan bu cihazlarda bileşen düzeyi uyum kaybolur (ama tek kolon taban çalışır).
- **Atlassian grid** dokümantasyonu "yalnızca tasarımcılar için" notu taşır; mühendislik tarafı ADS'de ayrı Layout bileşeni kullanır — AEP'de grid token + layout primitive ayrımı bu nedenle önemlidir.
- Bu rapor Figma/Storybook MCP prompt "yapı ve mantığını" verir; gerçek token değerleri projede doğrulanmalı ve kısıt gereği Next.js/Supabase kullanılmamalıdır.