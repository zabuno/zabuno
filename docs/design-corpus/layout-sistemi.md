# EA Platform — Grid / Layout Sistemi için Figma MCP + Storybook MCP Prompt Seti (320px Screen-First, AI-First Kurumsal SaaS Panel)

## TL;DR
- 320px'i "fallback" değil ANA tasarım hedefi kabul eden bu layout sistemi, WCAG 1.4.10 Reflow kriterine (320 CSS px'te iki yönlü scroll olmadan tam işlevsellik) uyar; grid mantığını token + CSS custom property + grid-template-areas üzerine kurar, böylece AI ajanları layout'u deterministik olarak okuyup üretebilir ve görsel dil değişse bile layout katmanı yeniden yazılmaz.
- Önerilen grid: 320px'te 4 kolon / 16px margin / 16px gutter (Material Design ve IBM Carbon `sm` breakpoint'i ile birebir uyumlu); 480'de 4-6, 768'de 8, 1024'te 12, 1440'ta 12 kolon — CSS Grid ile makro AppShell (header/nav/main/aside/footer), Flexbox ile bileşen içi hizalama, container query ile bileşen bazlı responsive.
- Rapor iki bölümde kopyala-yapıştır prompt verir: BÖLÜM A (Figma MCP: A5–A8 layout grid token'ları, AppShell şablonu, Bento Grid, responsive prototipler) ve BÖLÜM B (Storybook MCP: B5–B8 CSS Grid altyapısı, viewport story'leri, a11y/landmark testleri, CLS + görsel regresyon kalite kapıları). Her prompt sabit kısıtları gömülü taşır.

## Key Findings

### Layout sisteminin felsefesi (karar aldıran açıklama)
**Bu nedir:** Layout sistemi, EA Platform panelinin iskeletini (AppShell) ve içindeki alanların (header, sol Option Gallery Panel, ana içerik, sağ Option Information Panel, footer) her ekran genişliğinde nasıl yerleşeceğini belirleyen kurallar bütünüdür. Görsel bileşenlerin (buton, form, tablo) *nasıl göründüğünü* değil, *nerede ve hangi oranda durduğunu* yönetir.

**Ne işe yarar:** Tek bir kaynak (token + CSS değişkeni + adlandırılmış grid alanları) üzerinden hem insan geliştiricinin hem de AI ajanının aynı deterministik yapıyı üretmesini sağlar. Bu, "AI-first panel" ifadesinin somut karşılığıdır: AI ajan, `grid-template-areas: "header header" "nav main" "footer footer"` gibi *makine-okur* bir yapıyı okuyup yeniden üretebilir; belirsiz, elle ayarlanmış piksel konumları yerine kural tabanlı bir sisteme dayanır.

**Ne yapar:** 320px'te %100 işlevsellik sunar (tüm veriler, aksiyonlar ve bileşenler yatay scroll olmadan erişilebilir), sonra yukarı doğru progressive enhancement (aşamalı zenginleştirme) ile genişler. Layout kararlarını token'a bağlar.

**Ne yapmaz:** Görsel dili (renk, gölge, tipografi detayı) tanımlamaz — o katman ayrı token setlerindedir. Layout sistemi 320px'i bir "küçük ekran uyarlaması" olarak görmez; tasarım 320px'te başlar.

**Neden 320px-first:** WCAG 1.4.10 Reflow (AA seviyesi), içeriğin 320 CSS piksel genişlikte iki boyutlu scroll gerektirmeden sunulmasını zorunlu kılar. W3C WAI "Understanding SC 1.4.10 Reflow" birebir şöyle der: "320 CSS pixels generally corresponds to a desktop browser window set to a width of 1280px and the browser viewport then zoomed to 400%... 400% applies to the dimension, not the area" (1280÷4=320). Hedef kitle iPhone 4 / Galaxy S4 mini sınıfı 320px cihazları yeni nesil cihazlardan çok daha yoğun kullandığı için, 320px hem yasal/erişilebilirlik tabanı hem de ana ticari hedeftir.

**Neden makine-okur grid semantiği önemli:** Sektördeki 2025–2026 eğilimi, tasarım sistemlerinin "insan için dokümantasyon"dan "makine için yapı, kural ve ilişki"ye kaymasıdır. AI ajanları Notion sayfası ya da 60 sayfalık buton kılavuzu okumaz; yapı, kural ve token okur. Bu yüzden grid değerleri (kolon sayısı, gutter, margin, breakpoint) Figma Variables ve Style Dictionary üzerinden token'laştırılıp W3C Design Tokens formatında dışa aktarılmalıdır (DTCG spesifikasyonu Ekim 2025'te stabil v1'e ulaştı; Figma, Style Dictionary, Tokens Studio destekliyor) ki AI ajan hex/px "halüsinasyonu" yapmadan deterministik üretim yapabilsin.

### Endüstri standartları karşılaştırması (grid)
Aşağıdaki tablo, önce sade açıklamayla: Her kurumsal tasarım sistemi 320px sınıfı mobilde 4 kolona iner; farklılık üst breakpoint'lerdeki kolon sayısında ve gutter/margin değerlerindedir. EA Platform için 320px'te 4 kolon fiili standarttır.

| Sistem | Kolon (mobil→desktop) | Baz birim | Gutter | Margin | Breakpoint stratejisi |
|---|---|---|---|---|---|
| Material Design 3 | 4 (compact) → 8 (medium) → 12 (expanded) | 4dp baseline / 8dp | 16dp (compact) | 16dp (compact), 24dp (medium) | Window size class: compact 0–599, medium 600–839, expanded 840+ |
| IBM Carbon 2x Grid | 4 (sm) → 8 (md) → 16 (lg/xlg/max) | 8px mini unit | 32px (varsayılan wide; narrow 16px, condensed 1px) | sm=0, md/lg/xlg=16px, max=24px | sm=320, md=672, lg=1056, xlg=1312, max=1584 px |
| Salesforce Lightning (SLDS) | 12 (kesir tabanlı, 1-of-12) | Flexbox | esnek | esnek | small/medium/large keyword'leri (mobil/tablet/desktop) |
| Ant Design | 24 (span 1–24) | Flexbox | (16+8n)px önerisi; {xs:8, sm:16, md:24, lg:32} | — | xs<576, sm≥576, md≥768, lg≥992, xl≥1200, xxl≥1600 |
| Bootstrap 5 | 12 | Flexbox | 1.5rem (24px) varsayılan | container-fluid veya sabit | sm576, md768, lg992, xl1200, xxl1400 |
| Shopify Polaris | 12 (columnSpan xs–xl) | 4px spacing grid | responsive gap token | responsive | xs/sm/md/lg/xl |
| Atlassian | fixed-wide max 1296px / fixed-narrow max 864px / fluid | 8px (space.100=8px) | breakpoint bazlı | breakpoint bazlı | viewport tabanlı; mobil (xxs) her zaman dahil |

**IBM Carbon 2x Grid — tam breakpoint tablosu (carbondesignsystem.com resmi):** sm=320px/20rem (4 kolon, margin 0), md=672px/42rem (8 kolon, margin 16px), lg=1056px/66rem (16 kolon, margin 16px), xlg=1312px/82rem (16 kolon, margin 16px), max=1584px/99rem (16 kolon, margin 24px). Padding her breakpoint'te sabit 16px; varsayılan gutter 32px ("The margin around each grid box matches its padding, for a total gutter of 32 pixels"); mini unit 8px.

**Material Design — resmi değerler:** M2 "On mobile, at a breakpoint of 360 dp, this layout grid uses 4 columns" ve "16dp gutters / 16dp margins"; tablet 600dp'de 8 kolon / 24dp gutter. M3 window size class: compact (0–599dp) 4 kolon / 16dp margin; medium (600–839dp) 8 kolon / 24dp margin ("Medium layouts have margins of 24dp"); expanded (840dp+) 12 kolon.

**Güvenli / riskli / enterprise-doğru / EA için pratik ayrımı (kolon sayısı seçimi):**
- **Güvenli olan:** 12 kolon. En yaygın, en çok araç desteği olan, çoğu takımın bildiği sistem (Bootstrap, SLDS, Polaris). Bölünebilirliği yüksek (2, 3, 4, 6'ya tam bölünür).
- **Riskli olan:** 24 kolon (Ant Design). Çok esnek ama data-dense panelde aşırı granülerlik karmaşa üretebilir; AI ajan için karar uzayı gereksiz büyür.
- **Enterprise-doğru olan:** Ölçeklenen kolon — 320px'te 4, tablet'te 8, desktop'ta 12/16 (Material + Carbon yaklaşımı). Kolon *sayısı* breakpoint'e göre değişir, kolon *genişliği* yüzdeseldir.
- **EA Platform için pratik öneri:** 320'de 4 kolon, 480'de 4 (gerekirse 6), 768'de 8, 1024'te 12, 1440'ta 12 kolon; makro layout CSS Grid + grid-template-areas, mikro layout Flexbox. Carbon'un `sm=320px / 4 kolon` tanımı EA'nın 320-first hedefiyle birebir örtüşür ve referans alınmalıdır.

### CSS Grid vs Flexbox — kullanım kuralı
- **CSS Grid:** İki boyutlu makro sayfa iskeleti (AppShell: header/nav/main/aside/footer). `grid-template-areas` ile isimli alanlar hem okunabilir hem makine-okurdur.
- **Flexbox:** Tek boyutlu bileşen içi hizalama (toolbar, buton grubu, form satırı).
- **Container query (@container):** Bileşenin kendi kapsayıcısının genişliğine göre stil değiştirmesi — viewport'a değil. Kesin sürüm tarihleri: Chrome 105 (30 Ağustos 2022), Safari 16 (12 Eylül 2022), Firefox 110 (14 Şubat 2023); geç 2023'te Baseline, global destek >%93. Not: Safari 16.0–16.1'de cqi/cqb hataları 16.4'te giderildi. Aynı bileşenin (örn. bir kart) hem dar sağ panelde hem geniş ana alanda doğru davranmasını sağlar.
- **Subgrid:** İç içe grid'lerin üst grid'in hatlarına hizalanması. Kesin: Firefox 71 (Aralık 2019), Chrome/Edge 117 (Ağustos 2023); "Once all major browsers supported the feature, Subgrid reached Baseline Newly Available status in late 2023." Bento kartlarının başlık/gövde/aksiyon hatlarının hizalı kalması için kullanılır.
- **@supports fallback:** subgrid/container-query için `@supports` ile progressive enhancement; eski tarayıcıda sessizce temel düzene düşer.

### Fluid / adaptive hibrit yaklaşım
- **clamp(min, preferred, max):** Tek satırda akışkan tipografi/spacing. Formül: `preferred = yIntercept + slope*100vw`, `slope = (maxSize-minSize)/(maxVW-minVW)`. Örnek 320px→1280px'te 16px→32px: `clamp(1rem, 0.667rem + 1.667vw, 2rem)`.
- **KRİTİK kısıt:** Gövde metni min 16px (1rem) altına ASLA inmez. clamp min değeri her zaman ≥1rem. clamp içinde `rem + vw` karışımı kullanılmalı (bare vw değil) ki tarayıcı zoom'u ve WCAG 1.4.4 korunsun.
- **Utopia yaklaşımı:** İki kutup (320px min, 1440px max) tanımlanır, aradaki tüm genişlikler için değerler otomatik interpolasyon. Breakpoint-yoğun media query yerine iki sayı/adım.
- **min()/max():** container ve margin yüzdelerinde güvenli üst/alt sınır (örn. `width: min(100%, 720px)` form için).

### Mobil viewport gerçekleri (320px'e özel)
- **100dvh/svh/lvh:** `100vh` mobilde tarayıcı çubuğu yüzünden içeriği kesebilir. `svh` (small viewport) sabit ve güvenli — gizlenmemesi gereken UI için; `dvh` fixed modal için; `lvh` arka plan için. Öneri: `min-height: 100svh` + `@supports` fallback.
- **safe-area-inset (env()):** Notch / home indicator için `padding-bottom: max(16px, env(safe-area-inset-bottom))`. viewport-fit=cover ile birlikte.
- **Thumb zone (başparmak bölgesi):** Steven Hoober'ın UXmatters (2013) 1.333 gözlemlik saha çalışmasına göre kullanıcıların %49'u telefonu tek elle tutar (%36 beşik/cradle, %15 iki elle); Josh Clark ("Designing for Touch") ise ekran etkileşimlerinin ~%75'inin başparmak kaynaklı olduğunu bulmuştur. Yani "%75" başparmak-driven etkileşim oranıdır, "%75 tek elle" değildir. Ekranın alt-orta bölgesi "kolay erişim" (yeşil), üst köşeler "zor" (kırmızı). Bu yüzden 320px'te birincil navigasyon ve aksiyonlar alt bara (bottom nav) taşınmalı, mega header collapse olmalı.

### Storybook 8 + kalite kapıları
- **Viewport story'leri:** CSF3 ile her layout bileşeni 320/430/768/1024/1440 viewport snapshot'ı almalı.
- **Chromatic modes:** `.storybook/modes.ts` içinde viewport genişlikleri tanımlanır; her story her modda snapshot alır (görsel regresyon matrisi). Işık/koyu tema modları eklenebilir.
- **CLS (Cumulative Layout Shift):** Google web.dev "Cumulative Layout Shift (CLS)" birebir: "Good CLS values are 0.1 or less. Poor values are greater than 0.25" (75. persentil / mobil+masaüstü segmentli ölçülür). Nedenleri: boyutsuz görsel/medya, font swap, dinamik enjekte içerik. Önlem: `aspect-ratio`, görsel width/height, skeleton placeholder, `min-height`, `contain: layout`.
- **Landmark a11y:** header/nav/main/aside/footer semantik rolleri, focus order, skip link, RTL mirror.

## Details

### Kısa giriş (prompt setinin başına konulacak felsefe metni)
Aşağıdaki metin, prompt setinin girişinde yer alır ve her prompt'un dayandığı ortak zemini tanımlar:

> **EA Platform Layout Sistemi — Ortak Zemin.** Bu sistem 320px genişlikte başlar ve yukarı doğru genişler (progressive enhancement). 320px bir uyarlama değil, ana tasarım hedefidir; WCAG 1.4.10 Reflow gereği 320 CSS px'te yatay scroll olmadan tam işlevsellik zorunludur. Layout iskeleti CSS Grid + `grid-template-areas` ile kurulur; alan adları (header, nav, main, aside, footer) hem geliştirici hem AI ajan tarafından okunur. Tüm grid ölçüleri (kolon sayısı, gutter, margin, breakpoint) Figma Variables'ta token'dır ve Style Dictionary ile CSS custom property'ye dönüşür. Hex ve piksel hardcode yasaktır. Aktörler açıktır: **Figma/token katmanı** grid değerlerini tanımlar; **AI ajan** bu token'ları okuyup layout üretir ve yalnızca PR açar; **geliştirici** PR'ı inceler; **CI** görsel regresyon + CLS + a11y kapılarını çalıştırır; **insan** main'e merge onayı verir.

### Sabit kısıt bloğu (her prompt'un içine gömülecek — değiştirilemez)
Aşağıdaki blok, A5–A8 ve B5–B8 prompt'larının her birinin başında birebir tekrarlanır:

> **SABİT TASARIM KISITLARI (değiştirilemez).** Font: Roboto, min weight 400, min gövde 1rem (16px) — 16px altına asla inme. Radius: input hariç max 0.5rem (~8px, üst sınır 12px). Primary #FFB900 (limon sarısı); sarı üzerine metin her zaman koyu #080616, asla beyaz. Secondary accent parlamento mavisi #1E3A8A. Dark bg: ink/950=#080616, ink/900=#0D0A24, ink/800=#16123A, ink/50=#F7F7FB. Spacing token: space/1=4, space/2=8, space/3=12, space/4=16, space/5=24, space/6=32, space/7=48. Density modları: comfortable/compact/dense (row-height 52/44/36, cell-padding-x 16/12/8, field-height 48/44/40). Breakpoint seti: 320 (native base), 430/480 (plus phone), 768 (mini tablet), 1024 (plus tablet/laptop), 1440 (desktop/ultra-wide). Emoji YASAK; ikon Phosphor (öncelik) veya FontAwesome, SVG. Dark/Light çift tema; i18n/l10n/g11n hazır, RTL dahil (grid mirroring, logical properties: margin-inline-start/end, padding-inline, inset-inline). Card UI + Flat 2.0, Data Dense, Typography/Content/Motion/Conversion First, Adaptive AI. Stack: Vite + React + TypeScript + SCSS; token kaynağı Figma Variables export JSON → Style Dictionary → CSS custom property. Next.js KESİNLİKLE kullanılmaz/önerilmez. Supabase YASAK. Tailwind zorunlu değil (SCSS + CSS variables ana yaklaşım). Storybook 8, CSF3, viewport addon 320/430/768/1024/1440. Hex hardcode YASAK — her şey token/CSS custom property üzerinden. CI: GitHub Actions, deploy Hetzner/Debian; AI/bot yalnızca PR açar, main'e insan onayı olmadan push yok.

### Grid ölçüleri — EA Platform referans tablosu (prompt'ların içinde kullanılacak)
Önce sade açıklama: 320px'te 4 kolon seçildi çünkü Material ve IBM Carbon `sm` breakpoint'i ile birebir uyumlu, data-dense içerik için yeterli, AI ajan için karar uzayı sade. Kolon genişlikleri yüzdesel (fluid); margin ve gutter breakpoint'e göre sabit px (token).

| Breakpoint | Kolon | Margin | Gutter | Container davranışı | Not |
|---|---|---|---|---|---|
| 320 (native base) | 4 | 16px (space/4) | 16px (space/4) | fluid %100 | Ana hedef; tek kolon içerik yığını |
| 430–480 (plus phone) | 4 (opsiyonel 6) | 16px | 16px | fluid %100 | Kart listesi 1–2 sütun |
| 768 (mini tablet) | 8 | 24px (space/5) | 24px (space/5) | fluid | Sol panel drawer→kalıcı; form max 2 kolon |
| 1024 (plus tablet/laptop) | 12 | 24–32px | 24px | max-width başlar | AppShell 3 sütun (nav/main/aside) |
| 1440 (desktop/ultra-wide) | 12 | 32px (space/6) | 32px | max-width + esnek margin | Bento asimetrik 12 kolon |

Yüzde ölçüleri (320px baz, 4 kolon, 16px margin+gutter): kullanılabilir genişlik = 320 − 2×16 − 3×16 = 240px; kolon = 60px (≈%18.75). Kolon genişlikleri kod tarafında `grid-template-columns: repeat(4, 1fr)` + `gap: var(--grid-gutter)` ile yüzde yerine fr birimi kullanılarak deterministik tutulur (Carbon ve Material'in "kolon genişliği yüzdesel" ilkesiyle uyumlu, ama fr daha sağlam ve gutter matematiğini otomatik çözer).

---

## BÖLÜM A — FIGMA MCP PROMPT'LARI (Layout için)

Her prompt'un başına yukarıdaki **SABİT TASARIM KISITLARI** bloğu birebir eklenir. Aşağıda kısaltmak için bloğu `[SABİT KISITLAR]` ile temsil ediyorum; kullanırken tam metni yapıştır.

### A5 — Layout Grid Token'ları ve Figma Layout Grid Kurulumu
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Figma MCP):** EA Platform için makine-okur bir layout grid token seti ve her breakpoint için Figma Layout Grid kur. Aktör: sen (AI ajan) Figma Variables ve Layout Grid oluşturursun; değerleri hardcode etmezsin, hepsi Variable'a bağlıdır.
>
> 1. `layout` adında bir Variable Collection oluştur; modları breakpoint'ler olsun: `bp-320`, `bp-480`, `bp-768`, `bp-1024`, `bp-1440`.
> 2. Şu Variable'ları oluştur ve her mod için değer ata: `grid/columns` (4/4/8/12/12), `grid/margin` (16/16/24/24/32), `grid/gutter` (16/16/24/24/32), `grid/max-width` (none/none/none/1024/1440). Değerler px; spacing token'larıyla (space/4=16, space/5=24, space/6=32) tutarlı.
> 3. Her breakpoint frame'ine Figma Layout Grid (Columns tipi) uygula; Count, Gutter, Margin alanlarını ilgili Variable'a bağla (Config 2025 grid variable desteği; kolon/satır arası boşluk için variable kullan).
> 4. 320px frame'ini ÖNCE oluştur ve referans al; diğerleri bunun genişlemesi olsun.
> 5. Çıktı: (a) token'ların W3C Design Tokens uyumlu JSON önizlemesi, (b) her breakpoint için grid overlay'li frame, (c) Style Dictionary'nin CSS custom property'ye çevireceği isimlendirme (`--grid-columns`, `--grid-margin`, `--grid-gutter`, `--grid-max-width`).
> **Ne yapma:** min/max width'i Figma grid container'ına gömme (grid auto-layout'ta min/max desteklenmiyor — bunu koddaki CSS'e bırak). Hex/px hardcode etme.

### A6 — Sayfa Şablonları / Layout Template'leri (AppShell)
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Figma MCP):** EA Platform AppShell'ini 5 alanlı olarak kur: Mega Header (üst), sol Option Gallery Panel (nav), ana içerik (main), sağ Option Information Panel (aside), Mega Footer (footer). Auto Layout + min/max ve Variables kullan.
>
> 1. **320px frame'ini ÖNCE tasarla.** 320'de: Mega Header collapse (logo + hamburger + arama ikonu); sol panel bir drawer (üstten/soldan kayan) veya alt bottom-sheet olarak gizli; ana içerik tek kolon tam genişlik; sağ panel tam-ekran sheet olarak açılır; birincil navigasyon alt bar (bottom nav) olarak thumb zone'da. `grid-template-areas` mantığını temsil eden Auto Layout dikey yığın: header → main → bottom-nav.
> 2. **768px:** sol panel kalıcı sütun olur (drawer'dan çıkar); main + aside iki sütun; header genişler.
> 3. **1024/1440px:** üç sütun (nav | main | aside); header çok katmanlı mega header; footer çok katmanlı mega footer. Alan adlarını Figma katman isimleriyle `area/header`, `area/nav`, `area/main`, `area/aside`, `area/footer` olarak ver (AI-okur).
> 4. Auto Layout 5.0'da Hug/Fill için min/max constraint kullan; nav genişliği için `layout/nav-width` Variable'ı bağla.
> 5. Çıktı: 5 breakpoint için AppShell frame'leri + alan adı haritası + hangi alanın hangi breakpoint'te nasıl davrandığının notu.
> **Ne yapma:** Sağ paneli 320'de yan yana bırakma (tam-ekran sheet olmalı). Emoji ikon kullanma; Phosphor SVG kullan.

### A7 — Bento Grid Layout Bileşeni
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Figma MCP):** Dashboard için Bento Grid (asimetrik kart ızgarası) bileşeni kur. Masaüstünde asimetrik 12 kolon; 320px'te önem sırasına göre dikey yığın.
>
> 1. Desktop (1440): 12 kolonluk grid; hero KPI tile 6 kolon × 2 satır span, yanına 2×2 küçük durum kartları. En fazla 2 hero tile per bölüm (üçü odağı yok eder).
> 2. Tile'lara öncelik etiketi ver (`priority/1..n`) — bu, 320px'te dikey yığın sırasını belirler (AI ajan bu etiketi okuyup sıralar).
> 3. 768: hero tile'lar tam genişliğe düşer; 320: tüm tile'lar tek sütun, öncelik sırasına göre.
> 4. Kart radius max 0.5rem (8px); başlık/gövde/aksiyon hatları subgrid ile hizalı kalacak şekilde iç yapı kur.
> 5. Çıktı: 320/768/1440 için Bento frame'leri + tile öncelik haritası + span değerlerinin Variable'a bağlı hali.
> **Ne yapma:** Tüm tile'ları eşit boyut yapma (o zaman bento değil kart ızgarası olur). Boyutu içerik uzunluğuna göre değil, veri önemine göre belirle.

### A8 — Responsive Davranış Prototipleri
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Figma MCP):** AppShell ve Bento için breakpoint geçiş prototipleri kur; her alanın 320→1440 dönüşümünü göster.
>
> 1. Sol panel: 320'de drawer/bottom-sheet → 768'de kalıcı sütun geçiş prototipi (Smart Animate).
> 2. Sağ panel: 320'de tam-ekran sheet → 1024'te sabit aside geçişi.
> 3. Data table: 320'de kart listesi → 768'de tablo dönüşümü.
> 4. Form: 320'de tek kolon → ≥768 max 2 kolon → ≥1200 form max-width 720px (`min(100%, 720px)` mantığını temsil et).
> 5. RTL modu: tüm prototiplerin ayna (mirror) versiyonunu logical property mantığıyla göster (sol panel sağa geçer).
> 6. Çıktı: her dönüşüm için önce/sonra frame + geçiş notu + hangi CSS mekanizmasının (grid-template-areas değişimi, container query) karşılık geldiği.
> **Ne yapma:** 320'de yatay scroll üreten hiçbir düzen bırakma (WCAG 1.4.10). Form'u 320'de 2 kolon yapma.

---

## BÖLÜM B — STORYBOOK MCP PROMPT'LARI (Layout için)

### B5 — Layout Token Senkronu ve CSS Grid Altyapısı
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Storybook MCP):** Figma layout token'larını (A5) SCSS + CSS custom property'ye senkronla ve AppShell'in CSS Grid altyapısını kur.
>
> 1. Style Dictionary ile `layout` collection'ını CSS custom property'ye çevir: `--grid-columns`, `--grid-margin`, `--grid-gutter`, `--grid-max-width`, `--layout-nav-width`. Değerler breakpoint modlarına göre media query içinde override.
> 2. AppShell'i `display: grid` + `grid-template-areas` ile kur. 320: tek sütun `"header" "main" "bottomnav"`; 768: `"nav main"`; 1024+: `"header header header" "nav main aside" "footer footer footer"` + `grid-template-columns: var(--layout-nav-width) 1fr var(--layout-aside-width)`.
> 3. Bileşen bazlı responsive için container query kur: `container-type: inline-size` + `@container` ile kartlar kendi kapsayıcısına göre düzen değiştirsin (viewport'a değil).
> 4. Subgrid ile Bento tile iç hatlarını hizala; `@supports (grid-template-rows: subgrid)` fallback ekle.
> 5. Akışkan spacing/tipografi için clamp kullan ama min ≥1rem: örn. başlık `clamp(1rem, 0.9rem + 0.5vw, 1.5rem)`. Gövde metni asla <16px.
> 6. `min-height: 100svh` + `@supports` fallback (100vh); `padding-bottom: max(space/4, env(safe-area-inset-bottom))`.
> **Ne yapma:** Hex/px hardcode etme (token kullan). Next.js/Supabase kullanma. `100vh`'yi tek başına kullanma.

### B6 — AppShell / Layout Bileşen Story'leri
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Storybook MCP):** AppShell, Bento Grid, sol/sağ panel, data table→kart dönüşümü için CSF3 story'leri yaz; her biri 320/430/768/1024/1440 viewport snapshot'ı alsın.
>
> 1. `.storybook/preview.ts` viewport addon'a 5 breakpoint tanımla (320/430/768/1024/1440).
> 2. Her layout bileşeni için CSF3 `Meta` + story'ler: `Mobile320`, `Phone430`, `Tablet768`, `Laptop1024`, `Desktop1440`. Her story `parameters.viewport.defaultViewport` ile ilgili genişliği set etsin.
> 3. `.storybook/modes.ts` içinde Chromatic modes tanımla (her viewport genişliği + light/dark tema) → görsel regresyon matrisi.
> 4. AppShell story'sinde sol panel drawer/kalıcı, sağ panel sheet/aside dönüşümlerini ayrı story olarak göster.
> 5. RTL story'si ekle (`dir="rtl"`) — grid mirror doğrulaması.
> **Ne yapma:** Tek viewport'ta story bırakma. Emoji kullanma.

### B7 — Layout Etkileşim ve A11y Testleri
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Storybook MCP):** Layout için a11y ve etkileşim testleri (play function) yaz.
>
> 1. Landmark doğrulaması: `<header>`, `<nav>`, `<main>`, `<aside>`, `<footer>` semantik rolleri mevcut ve tekil; axe ile tara.
> 2. Focus order: skip link ("ana içeriğe geç") ilk odak; sonra header → nav → main → aside → footer mantıklı sıra.
> 3. Skip link story'si; klavye ile main'e atlama testi.
> 4. RTL mirror: `dir="rtl"` altında logical property'lerin (margin-inline-start) doğru aynalandığını doğrula.
> 5. Thumb zone: 320 story'sinde birincil aksiyonların bottom nav'da (alt %33) olduğunu doğrula.
> 6. Touch target min 44–48px (field-height token'larıyla uyumlu).
> **Ne yapma:** Landmark'sız div-only layout üretme. 320'de birincil aksiyonu üst köşeye koyma.

### B8 — Layout Kalite Kapıları (CLS + Görsel Regresyon)
Kopyala-yapıştır prompt:

> `[SABİT KISITLAR]`
>
> **Görev (Storybook MCP + CI):** Layout kalite kapılarını kur.
>
> 1. **CLS:** Story seviyesinde layout shift ölç; Google web.dev eşiği: iyi ≤0.1, kötü >0.25. Görsel/medya'ya `aspect-ratio` ve explicit boyut; skeleton placeholder; dinamik alanlara `min-height`; `contain: layout` uygula.
> 2. **Görsel regresyon matrisi:** Chromatic ile 5 viewport × light/dark = 10 snapshot per layout story; animasyonları `pauseAnimationAtEnd` / `disableSnapshot` ile stabilize et.
> 3. **CI (GitHub Actions):** PR'da Chromatic + axe + CLS kapıları çalışsın; eşiği geçemeyen PR bloke. AI/bot yalnızca PR açar; main'e insan onayı olmadan merge yok.
> 4. 320px'te yatay scroll regresyon testi (WCAG 1.4.10): overflow-x kontrolü.
> **Ne yapma:** Kalite kapısı olmadan main'e merge etme. CLS'yi yalnızca lab (Lighthouse) ile ölçüp gerçek kullanıcı koşulunu ihmal etme.

### Prompt kullanım sırası
1. **A5** (token + Figma grid) → temel; her şey buna bağlanır.
2. **A6** (AppShell şablonu) → 320 frame önce.
3. **A7** (Bento) ve **A8** (responsive prototip) → A6'dan sonra, paralel.
4. **B5** (token senkron + CSS Grid altyapısı) → A5–A6 çıktısı hazır olunca.
5. **B6** (viewport story'leri) → B5'ten sonra.
6. **B7** (a11y) ve **B8** (kalite kapıları) → B6'dan sonra, CI'ya bağlanır.

Kural: Figma (A) katmanı token/yapı üretir → Storybook (B) katmanı kodlar ve doğrular → CI kapıları geçince AI ajan PR açar → insan merge eder.

### 2030 / 2035 vizyon notu
Layout katmanı bilinçli olarak üç değişmez üzerine kuruldu: **(1) token** (Figma Variables → Style Dictionary → CSS custom property), **(2) CSS custom property**, **(3) grid-template-areas**. Görsel dil (renk paleti, gölge dili, tipografi detayı, hatta Flat 2.0 yerine gelecek bir estetik) tamamen değişse bile, layout *mantığı* (hangi alan nerede, hangi breakpoint'te nasıl davranır) ve *AI ajan entegrasyonu* (makine-okur alan adları + token'lar) yeniden yazılmaz. Yeni bir görsel tema, yalnızca token değerlerini değiştirir; grid-template-areas yapısı ve alan adları sabit kalır. Bu, 2030/2035'te panelin görsel olarak tamamen yenilenmesine rağmen layout ve AI üretim altyapısının korunmasını garanti eder.

## Recommendations
1. **İlk adım (hemen):** A5'i çalıştırıp `layout` token collection'ını ve 320px Figma grid'ini kur. Bu olmadan diğer hiçbir prompt deterministik çalışmaz. Eşik: token JSON W3C formatında dışa aktarılabiliyor ve 5 modun tümü değer taşıyor.
2. **İkinci adım:** A6 ile AppShell'i 320-first kur, sonra B5 ile CSS Grid altyapısını kodla. Eşik: 320px'te axe taramasında landmark hataları sıfır ve overflow-x yok.
3. **Üçüncü adım:** A7/A8 + B6 ile Bento ve responsive story'leri; sonra B7/B8 kalite kapıları. Eşik: Chromatic 5 viewport × 2 tema matrisi yeşil, CLS ≤0.1.
4. **Grid standardı kararı:** 320'de 4 kolon / 768'de 8 / 1024+'ta 12 kolonu benimse (Material + Carbon uyumlu). 24 kolona (Ant) gitme — data-dense panelde AI karar uzayını gereksiz büyütür. Kararı değiştirecek eşik: eğer tek ekranda 4'ten fazla eşzamanlı veri sütunu düzenli gerekiyorsa 16 kolona (Carbon lg) çık.
5. **Fluid vs fixed kararı:** Kolon genişliği fluid (fr/yüzde), margin/gutter breakpoint-sabit (token px), container max-width ≥1024'te devreye. Form max-width `min(100%, 720px)`.
6. **CI disiplini:** AI/bot yalnızca PR açsın; CLS + Chromatic + axe kapılarını geçmeyen PR bloke; main'e insan onayı zorunlu.

## Caveats
- **Figma sınırları:** Config 2025 grid auto-layout'ta grid container'a min/max width HENÜZ desteklenmiyor (Figma ekibi resmi forumda doğruladı); kolon/rowspan'ı variable'a bağlama tam değil. Bu yüzden min/max ve container max-width mantığı Figma'da değil koddaki CSS'te (B5) uygulanmalı. Figma tarafı yapı ve token üretir, kesin responsive davranış kodda doğrulanır.
- **Material iç tutarsızlık:** Material'in kendi M2 dokümanında 600dp margin değeri bir yerde 24dp, başka yerde (margin-scaling bölümü) 32dp olarak geçer; M3 otoritatif olarak medium=24dp der. EA için token değeri net (768'de 24px) tutulmalı.
- **Carbon margin nüansı:** Carbon'da margin breakpoint'e göre değişir (sm=0, md/lg/xlg=16px, max=24px); "16px her yerde" olan padding'dir, margin değil. EA tablosu bu ayrımı netleştirir.
- **clamp/vw zoom riski:** Akışkan tipografide clamp max'ı min'in 2.5 katından büyükse zoom/WCAG 1.4.4 riski doğar; min ≥1rem ve rem+vw karışımı kuralına sıkı uyulmalı.
- **CLS ölçüm farkı:** Lab (Lighthouse) yalnızca ilk yük CLS'sini görür; gerçek kullanıcı scroll'unda ek shift olur. B8 hem lab hem RUM/synthetic yaklaşımını önerir.
- **Subgrid/container query:** 2023 sonu Baseline; son iki tarayıcı sürümünü hedefleyen EA için güvenli, ancak çok eski WebView'lar için `@supports` fallback şart. Safari 16.0–16.1'deki container query unit hataları 16.4'te giderildiği için minimum hedef Safari 16.4 olmalı.
- **Thumb zone verisi:** "%75" oranı başparmakla yapılan etkileşim oranıdır (Josh Clark); tek elle tutma oranı Hoober'a göre %49'dur. İki metrik karıştırılmamalı.