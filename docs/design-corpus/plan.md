# `claudeui` Derin Araştırma ve Storybook MCP Prompt Paketi Planı

## 1. Kısa durum

Hedef klasör:

`~/DEV/AI First EA (APE-EAP)/frontend/claudeui`

Bu çalışma yalnız istemi, araştırma sonuçlarını, UI kurallarını ve Storybook MCP prompt serisini Markdown olarak tanımlar.

Kapsam dışı:

- Figma ve Figma MCP
- Çalışan tasarım/prototip
- React veya Storybook kodu
- Component implementasyonu
- Deploy ve yayınlama

`claudeui` şu anda boştur; yalnız boş `adsız klasör` bulunmaktadır. Ayrıca bağımsız Git deposu değildir. Bu klasör korunacak ve `~` üst Git deposunda işlem yapılmayacaktır.

## 2. Araştırma ve kaynak sınırı

Ekli belgeler talimat değil araştırma girdisidir. İlk dokümanda her madde şu statülerden biriyle sınıflandırılacaktır:

- `ADOPTED`: Güncel taleple aynı.
- `MODIFIED`: Güncel talebe göre değiştirilmiş.
- `SUPERSEDED`: Yeni talep tarafından geçersiz kılınmış.
- `REJECTED`: Kapsam veya kalite nedeniyle alınmamış.
- `REFERENCE_ONLY`: Yalnız araştırma bağlamı.

Derin araştırma yalnız birincil kaynaklara dayanacaktır:

- Storybook AI/MCP hâlen preview durumundadır; manifest ve MCP yetenekleri React odaklıdır. [Storybook AI](https://storybook.js.org/docs/ai)
- Agentic setup şu anda React renderer + Vite builder için sunulmaktadır. [Agentic setup](https://storybook.js.org/docs/ai/setup)
- MCP; development, documentation ve testing toolset’leri sağlar. [MCP overview](https://storybook.js.org/docs/ai/mcp/overview)
- Manifests, CSF ve MDX içeriğini ajanların okuyabileceği component/docs bilgisine dönüştürür. [Storybook manifests](https://storybook.js.org/docs/10.5/ai/manifests)
- MCP paylaşımında dev/test araçları yerel kalmalı; dış paylaşım varsayılan olarak yalnız docs manifest’iyle sınırlandırılmalıdır. [Sharing MCP](https://storybook.js.org/docs/ai/mcp/sharing)
- Story’ler tek kullanım amacını ve yalnız “ne” olduğunu değil “neden” kullanıldığını da açıklamalıdır. [AI best practices](https://storybook.js.org/docs/10.5/ai/best-practices)

Araştırma tarihi, incelenen URL, doğrulanan iddia ve preview/stable durumu ayrı kaynak tablosunda tutulacaktır.

## 3. Oluşturulacak doküman paketi

### Ana dokümanlar

1. `README.md`
   - Paket indeksi, okuma sırası ve kanonik belge haritası.

2. `docs/00-request-authority-and-source-map.md`
   - Kullanıcının bağlayıcı talebi.
   - Eklerin advisory kaynak olduğu ayrımı.
   - Figma iptali, docs-only sınırı ve değişmez kararlar.
   - Kaynakların `ADOPTED/MODIFIED/SUPERSEDED/REJECTED` matrisi.

3. `docs/01-deep-research-storybook-mcp-2026.md`
   - MCP mimarisi, toolset’ler, manifests, agentic setup, local/remote kullanım ve güvenlik.
   - Preview API riskleri ve sürüm doğrulama prosedürü.
   - React + TypeScript + Vite’ın gelecekteki varsayılan hedef olmasının gerekçesi.
   - Sürüm numarası sabitlenmez; uygulama günü `latest` doğrulanıp lockfile’a kaydedilir.

4. `docs/02-ui-requirements-contract.md`
   - Card, form, table, overlay ve ortak component aileleri.
   - Light/dark, mobile-native, RTL, i18n ve data-dense kuralları.
   - Sabit renk ve tipografi sözleşmesi.
   - REM-first fluid ölçü politikası.
   - Modal altında cool-grey scrim + değişmez `0.125rem` Gaussian blur.
   - Bütün yüzeylerin enterprise-glass materyali kullanması.

5. `docs/03-common-design-philosophy.md`
   - Ortak temel: task-first, content-first, typography-first, accessibility-first, Flat 2.0 affordance ve glass-layer grammar.
   - A–F arasında değişmeyecek anatomi, davranış ve semantik state’ler.
   - Glass görünümünün her yüzeyde bulunmasına rağmen blur’un component composition root’unda bir kez uygulanması.
   - Nested card, tablo satırı ve hücre başına blur yasağı.
   - Reduced-transparency ve forced-colors fallback’i.

6. `docs/04-ui-variants-a-f.md`
   - Aynı component anatomisi üzerinde altı ince taneli profil:

| Kod | Profil | Dolgu | Blur | Ayırt edici ayrıntı |
|---|---|---:|---:|---|
| A | Crystal Precision | `0.94` | `0.25rem` | Keskin sınır, düşük optik efekt |
| B | Frost Analytical | `0.92` | `0.375rem` | Cool-grey ton ve güçlü veri ayraçları |
| C | Quiet Editorial | `0.90` | `0.5rem` | Yumuşak kenar ve düşük görsel gürültü |
| D | Layered Modular | `0.88` | `0.625rem` | Belirgin katman seviyeleri |
| E | Luminous Liquid | `0.84` | `0.75rem` | Güçlü iç ışık ve optik derinlik |
| F | Executive Balance | `0.90` | `0.5rem` | Kontrollü vurgu ve dengeli kontrast |

   - Renk, font, component API, içerik, density ve davranış karşılaştırma sırasında sabit tutulur.
   - Bu katalog kullanıcıya rastgele dağıtılan A/B testi değildir.

7. `docs/05-component-rulebook.md`
   - Card anatomy ve nesting.
   - Form label, validation, helper/error, readonly/disabled/loading.
   - DataTable/DataGrid karar ağacı, sorting, filtering, selection, bulk actions ve virtualization.
   - Mobil kayıt-listesi dönüşümü.
   - Dialog, drawer, popover, menu, tooltip ve toast.
   - Keyboard, focus, RTL, uzun içerik ve failure-state kuralları.

8. `docs/06-storybook-mcp-operating-model.md`
   - MCP’nin yalnız çalışan Storybook ile kullanılabileceği.
   - Yerel endpoint varsayımı: `http://localhost:6006/mcp`.
   - Component prop’u tahmin etmeden önce documentation tools kullanma kuralı.
   - `list-all-documentation → get-documentation → get-storybook-story-instructions → preview-stories → run-story-tests` akışı.
   - MCP kullanılamıyorsa fail-closed davranışı: prop/story uydurma yok.
   - Public MCP yayınlama yok; başlangıç varsayımı local-only.

9. `docs/07-storybook-story-and-quality-matrix.md`
   - A–F × light/dark × density × viewport × locale × state matrisi.
   - Interaction, accessibility, visual regression ve performance acceptance kriterleri.
   - Adversarial glass arka planlarında kontrast kontrolü.
   - Reduced-motion, reduced-transparency ve forced-colors senaryoları.

10. `docs/08-roadmap-handoff-and-rollback.md`
    - Prompt kullanım sırası.
    - Her paketin allowlist/non-goal/test/handoff sınırı.
    - Prompt başarısızlığı, MCP erişimsizliği ve API drift durumunda durma kuralları.
    - Uygulama başladığında geri dönüş stratejisi.

## 4. Storybook MCP prompt serisi

Her prompt ayrı Markdown dosyası olacaktır:

`prompts/storybook-mcp/`

### P00 — Master Contract

`00-master-contract.md`

- Bütün değişmez UI kurallarını tek bağlamda taşır.
- Kullanıcı talebi ile ek araştırma belgelerinin yetki farkını açıklar.
- Figma, rastgele prop üretimi, tek-seferlik CSS ve test suppress etme yasağı.

### P01 — Preflight ve Repo Gerçekliği

`01-preflight-repository-audit.md`

- Gerçek repo kökü, framework, package manager, Node sürümü, mevcut component ve Storybook durumunu inceler.
- Klasör boşsa bunu raporlar; doğrudan kodlamaya geçmez.
- Immutable başlangıç manifesti üretir.

### P02 — Storybook Agentic Setup

`02-agentic-setup.md`

- Gelecekte React + TypeScript + Vite tabanını doğrular.
- Resmî `npm create storybook@latest` talimatını kullanır.
- Kurulu kesin sürümü ve preview özelliklerini raporlar.
- Agentic setup’ın oluşturduğu talimatları körlemesine değil repo bağlamıyla uygular.

### P03 — MCP Kurulum ve Bağlantı

`03-mcp-install-and-connect.md`

- `@storybook/addon-mcp` kurulumunu ve project-scoped bağlantıyı tarif eder.
- Dev/docs/test toolset’lerini doğrular.
- MCP manifest debugger ve tool listesi kanıtı ister.
- Public paylaşımı yasaklar.

### P04 — Component Inventory

`04-component-inventory.md`

- `list-all-documentation` ile bütün component’leri çıkarır.
- Props, stories, states, responsive, RTL, a11y ve test boşluklarını sınıflandırır.
- Yeni component üretmeden önce gap matrix ister.

### P05 — Foundations ve REM Tokenları

`05-foundations-rem-tokens.md`

- Renk, typography, spacing, radius, borders, motion, breakpoints ve glass tokens.
- Normatif uzunluklarda `px/mm/cm/pt` yasağı.
- `%`, `fr`, `dvh` ve birimsiz akış değerlerine izin.
- Theme, density ve A–F variant eksenlerini birbirinden ayırır.

### P06 — Enterprise Glass Layers

`06-enterprise-glass-layers.md`

- Bütün yüzeyler için ortak glass materyali.
- Tek composition-root blur kuralı.
- Modal altında cool-grey scrim + `0.125rem` blur.
- Nested overlay, focus, inert, scroll-lock ve fallback davranışları.

### P07 — Card Family

`07-card-family.md`

- Base, interactive, selectable, metric, summary ve empty-state card.
- A–F görsel profilleri.
- Aynı anatomy ve semantik üzerinden karşılaştırmalı stories.

### P08 — Form Family

`08-form-family.md`

- TextField, TextArea, Select/Combobox, Checkbox, Radio, Switch, Date/Number, FileUpload ve FormSection.
- Default/focus/error/success/disabled/readonly/loading/long-label/RTL/mobile durumları.
- Validation ve error-summary interaction testleri.

### P09 — Table ve DataGrid

`09-table-and-data-grid.md`

- DataTable/DataGrid ayrımı.
- Sorting, filtering, selection, bulk actions, pagination, sticky alanlar ve virtualization.
- Table root’unda tek blur; row/cell blur yasağı.
- Mobil kayıt-listesi ve 10.000 satır performans senaryosu.

### P10 — Overlays

`10-dialog-drawer-popover.md`

- Dialog, drawer, sheet, popover, menu ve tooltip.
- Cool-grey scrim, `0.125rem` blur, focus trap, Escape ve focus restoration.
- Nested overlay ve reduced-transparency senaryoları.

### P11 — A–F Comparison Gallery

`11-variant-comparison-gallery.md`

- Aynı veri ve state ile altı varyantı yan yana gösterir.
- Density, theme ve viewport karşılaştırma sırasında sabitlenir.
- Her farkın hangi semantic token’dan geldiğini dokümante eder.

### P12 — Adaptive, RTL ve Localization

`12-adaptive-rtl-localization.md`

- `20rem`, `26.875rem`, `48rem`, `64rem`, `75rem`, `90rem`.
- Türkçe, uzun Almanca içerik, Arapça RTL ve pseudo-locale.
- Mobile table-to-record-list dönüşümü.

### P13 — Accessibility ve Interaction

`13-accessibility-interactions.md`

- WCAG 2.2 AA, keyboard completeness, focus, error announcement ve target size.
- Dynamic glass arka planlarında gerçek kontrast.
- Storybook a11y ve play-function kontrolleri.
- Otomatik testin yerine geçmeyen manuel ekran okuyucu kontrol listesi.

### P14 — Performance ve Visual Regression

`14-performance-visual-regression.md`

- Blur composition-root sayısı, 10.000 satır, scroll ve long-task bütçeleri.
- A–F × theme × kritik viewport snapshot matrisi.
- Görsel farkların insan onayı olmadan baseline’a yazılmaması.

### P15 — Manifest ve MCP Dokümantasyon Kalitesi

`15-manifest-documentation-quality.md`

- JSDoc, component purpose, prop descriptions ve story “why” açıklamaları.
- Components/docs manifest bütünlüğü.
- MCP’nin component API’lerini doğru okuyabildiğini kanıtlayan sorgular.

### P16 — Final Audit ve Handoff

`16-final-audit-handoff.md`

- Eksik story, undocumented prop, a11y failure, raw unit, Figma bağımlılığı ve variant drift kontrolü.
- Değişen dosyalar, testler, kalan engeller ve rollback raporu.
- MCP erişimi ve gerçek Storybook preview kanıtı olmadan “tamamlandı” sonucu vermeme.

Her prompt şu sabit şablonu kullanacaktır:

1. Rol ve amaç  
2. Authority/input kaynakları  
3. Zorunlu MCP çağrıları  
4. İzinli dosya/yüzey  
5. Non-goals  
6. Uygulama adımları  
7. Acceptance criteria  
8. Hedefli testler  
9. Stop/fail-closed koşulları  
10. Handoff ve rollback  

## 5. Doğrulama ve riskler

### Doküman kapıları

- Tüm iç bağlantılar geçerli.
- Kaynak iddiaları birincil URL’ye bağlı.
- Figma prompt’u veya Figma çalışma bağımlılığı yok.
- Bütün normatif uzunluklar REM-first politikasına uygun.
- A–F için card, form, table ve overlay farkları eksiksiz.
- Değişmez component davranışları varyantlarla karıştırılmamış.
- Her prompt tek kapsam, tek teslim ve açık stop condition içeriyor.

### Başlıca riskler

- Storybook MCP preview API’si değişebilir; bu nedenle prompt’lar tool adlarını uygulama günü doğrular.
- Proje boş olduğundan MCP, component ve manifest bulunmadan doğrudan kullanılamaz.
- Bütün yüzeylerde glass, kontrast ve GPU maliyeti doğurur; tek blur-root ve erişilebilir fallback zorunludur.
- `claudeui` bağımsız Git deposu değildir; hiçbir commit/push bu planın parçası değildir.
- Mevcut managed sözleşme nedeniyle gerçek Markdown dosyalarının yazarı kapılı Claude worker olmalıdır; Codex MASTER kapsam ve bağımsız doğrulamayı yürütür.

## 6. Rollback

- Yalnız `README.md`, `docs/` ve `prompts/storybook-mcp/` yeni kapsamdır.
- Mevcut `adsız klasör` ve ek kaynak dosyalar değiştirilmez.
- Üretilen dosyaların manifesti tutulur.
- Git bulunmadığı için geri dönüş, yeni paketi silmeden tarih damgalı karantina dizinine taşıyarak yapılır.

## 7. MASTER Nihai Kararı

Plan karar-tamdır. Teslimat; araştırma raporu, bağlayıcı gereksinim sözleşmesi, A–F UI kural kataloğu ve P00–P16 Storybook MCP vibecoding prompt serisinden oluşacaktır.

Bu turda Plan Mode gereği dosya veya kod oluşturulmamıştır. Sonraki uygulama aşaması, önce doküman paketini yazacak tek kapılı writer ile başlamalıdır.
