# EA / EBP / EOP / EBM / ERX için 2026–2035 SaaS Panel Tasarım Sistemi, Form–Table Disiplinleri ve Figma–Storybook MCP Prompt Rehberi

## Yönetici sonucu: hangi tasarım anlayışı doğru?

Araştırmanın ana sonucu şu: **EA Platform, EBP, EOP, EBM ve ERX gibi veri yoğun, uluslararası ve uzun ömürlü enterprise SaaS sistemlerinde temel tasarım dili olarak tek başına “glassmorphism”, “neumorphism”, “card UI” veya “minimalism” seçmek doğru problem tanımı değildir.** Asıl ihtiyaç, bir **enterprise interaction grammar** oluşturmaktır. Görsel paradigma bunun üzerine oturmalıdır.

Bu ürün ailesi için benim önerdiğim ana formül:

> **Typography-first + Content-first + Data-dense + Mobile-native + Adaptive + Accessible + Flat 2.0 affordance + restrained elevation + selective cards + selective glass + functional motion + AI-aware interaction**

Bunun pratik karşılığı, **form ve tabloların büyük ölçüde opak, yüksek okunabilirlikli, keskin hiyerarşili ve güçlü affordance'lara sahip olması; glass/translucency ve daha deneysel estetiklerin ise global navigation, command palette, overlay, hero ve geçici katmanlarla sınırlandırılmasıdır.**

Bu karar yalnızca estetik tercih değildir. Nielsen Norman Group'un flat design araştırmaları, aşırı düz tasarımın tıklanabilirlik işaretlerini zayıflatabildiğini; “Flat 2.0” yaklaşımının gölge, sınır, kontrast ve katman gibi ipuçlarını kontrollü biçimde geri getirerek daha iyi kullanılabilirlik sunduğunu gösteriyor. Aynı kuruluş glassmorphism için de translucency'nin derinlik oluşturabileceğini fakat aşırı kullanımın okunabilirlik ve erişilebilirlik sorunları yaratabileceğini özellikle vurguluyor. citeturn5search0turn5search1turn5search8

IBM Carbon'ın güncel enterprise bileşenleri de aynı yönde ilerliyor: form, data table, pagination, loading, AI presence, accessibility ve farklı yoğunluk seviyeleri birbirinden ayrı sistem davranışları olarak ele alınıyor; tabloya mümkün olduğunca yeterli yatay alan verilmesi ve yoğun verinin gerektiğinde condensed grid ile sunulması öneriliyor. citeturn1search0turn4search0turn4search3

Dolayısıyla temel karar tablosu şöyle olmalı:

| Paradigma | Form | Data table/grid | Dashboard | Commerce / Hero | Global chrome | Karar |
|---|---:|---:|---:|---:|---:|---|
| Flat 2.0 | Çok güçlü | Çok güçlü | Güçlü | Güçlü | Güçlü | Ana dil |
| Card UI | Orta | Zayıf–orta | Çok güçlü | Çok güçlü | Orta | Seçici kullan |
| Glassmorphism | Zayıf | Çok zayıf | Orta | Çok güçlü | Çok güçlü | Sınırlı kullan |
| Neumorphism | Zayıf | Çok zayıf | Zayıf | Orta | Orta | Ana sistemden çıkar |
| Editorial/minimal | Güçlü | Orta | Güçlü | Güçlü | Güçlü | EBM/reporting için iyi |
| Analytical console | Çok güçlü | Çok güçlü | Çok güçlü | Zayıf | Güçlü | EA/EOP/ERX için çok iyi |
| Material Expressive tipi yaklaşım | Orta | Orta | Güçlü | Çok güçlü | Çok güçlü | Seçici |
| Liquid/Glass tipi yaklaşım | Zayıf | Zayıf | Orta | Çok güçlü | Çok güçlü | Chrome/overlay ile sınırla |

Apple'ın güncel tasarım sistemi Liquid Glass'ı navigation ve controls gibi katmanlarda bir malzeme olarak konumlarken, Google'ın Material 3 Expressive yaklaşımı motion, shape, adaptive component ve typography tarafında daha ifade gücü yüksek bir tasarım dili getiriyor. Bunlar 2026 sonrası görsel trend yönünü anlamak açısından önemli; ancak veri girişinin ve karşılaştırmanın merkezde olduğu enterprise çalışma yüzeylerinin tamamını bu estetiklere dönüştürmek için doğrudan gerekçe oluşturmuyor. citeturn5search2turn5search6turn5search3turn5search7turn5search11

### Tasarım sisteminin öncelik sırası

Senin tanımlarını biraz yeniden sıralamak gerekir. “Motion first” ve “conversion first” değerli kavramlar, fakat EA/EBP/EOP/EBM/ERX gibi platformlarda aşağıdaki hiyerarşi daha sağlıklıdır:

**Task completion → content → typography → data semantics → accessibility → affordance → responsiveness → localization → performance → conversion → motion → aesthetic expression.**

Commerce modüllerinde conversion daha yukarı çıkar. Operational ve administrative modüllerde ise doğruluk, hız, hata önleme, karşılaştırılabilirlik ve auditability öne geçer. Carbon'ın form yaklaşımı basit ve karmaşık formları page, side panel ve dialog gibi farklı bağlamlara ayırırken; Fluent spacing rehberi de yoğun bilginin yalnızca “daha az boşluk” anlamına gelmediğini, spacing'in bilgi ilişkilerini ve hiyerarşiyi göstermesi gerektiğini vurguluyor. citeturn1search6turn2search3

Bu nedenle senin sistemini şu biçimde tanımlamak daha doğru olur:

> **Adaptive Enterprise Interface System**
>
> Görsel olarak Flat 2.0 tabanlı, işlevsel olarak data-dense, yapısal olarak semantic-token driven, mobile-native, AI-aware ve internationalization-native.

“Card UI + Flat 2.0” formülünü ise şu şekilde değiştirmeni öneriyorum:

> **Flat 2.0 foundation + contextual cards**

Çünkü her şeyi karta dönüştürmek özellikle table, property editor, configuration form ve master-detail ekranlarında gereksiz container nesting üretir. Fluent 2, spacing ve proximity'nin zaten gruplama oluşturabildiğine dikkat çekiyor; her bilgi grubunu fiziksel bir karta sokmak gerekmiyor. citeturn2search3


## Gereksinim modeli ve 2026–2035 vizyonu

Bu ürün ailesini tek bir “admin panel” olarak düşünmek yerine dört interaction domain'e ayırmak daha sağlıklı olur:

| Domain | Ana görev |
|---|---|
| Operational | Durum izleme, exception, workflow, execution |
| Transactional | Form, kayıt, approval, configuration |
| Analytical | Table/grid, comparison, filtering, reporting |
| Navigational / knowledge | Architecture map, relationships, documentation |
| Commercial | Search, product discovery, option selection, conversion |

EA Platform ağırlıklı olarak analytical + navigational; EOP operational; EBM analytical + governance; ERX transactional + operational; EBP ise bunların bir platform shell'i altında birleşimi gibi ele alınabilir.

Bu sınıflandırma, tek bir UI stilini bütün ürüne zorlamak yerine ortak design tokens ve component primitives üzerinde farklı “experience patterns” üretmeye olanak verir. Carbon, Fluent, Material, Spectrum ve Ant Design gibi büyük sistemlerin ortak özelliği de renk paletinden ziyade component anatomy, token, layout, states ve kullanım kurallarını standardize etmeleridir. citeturn1search14turn1search15turn2search0turn2search7turn2search2

### 2026 hedefi

Ağustos 2026 itibarıyla ilk hedef “görsel olarak mükemmel panel” değil, **tasarım-kod-AI üçgenini tek bir source-of-truth sistemine bağlamak** olmalıdır.

Figma'nın güncel MCP altyapısı variables, components, layout context ve screenshot gibi structured design context'i agent'lara aktarabiliyor; remote MCP ayrıca uygun yetki ve skill'lerle canvas üzerinde frame, component, variable ve auto layout oluşturup değiştirebiliyor. Figma ayrıca MCP ile kullanılan design system'in gerçek kod bileşenleriyle eşleşmesini güçlendirmek için Code Connect'i öneriyor. citeturn8search0turn8search1turn8search2turn8search4

2026 sonunda olgunlaşması gereken çekirdek:

| Katman | Hedef |
|---|---|
| Foundations | typography, spacing, radius, color, elevation, motion |
| Semantic tokens | light/dark, status, action, border, foreground, surface |
| Layout | adaptive shell, grid, stack, split pane |
| Forms | tüm form primitives + validation |
| Data | table/data grid/tree grid/list |
| Navigation | header, side nav, tabs, breadcrumb, command/search |
| Feedback | alert, toast, skeleton, empty/error states |
| Overlay | sheet, drawer, dialog, popover |
| i18n | locale, RTL, bidi, currencies, timezone |
| AI | provenance, suggestion, review, explanation |
| Commerce | gallery, buy/info panel, search, mega menu, footer |
| Storybook | state stories + interaction/a11y tests |
| Figma | variables + components + Code Connect/MCP |

Storybook tarafında MCP desteği 2026'da doğrudan agentic component development akışına girmiş durumda. Storybook 10.3 React MCP'yi component docs, story generation ve test guardrails ile sundu; 10.4 ise React Component Meta tabanlı daha zengin MCP metadata üzerinde çalışmaya başladı. Güncel MCP dokümantasyonundaki docs, dev ve test toolset'leri agent'ın component documentation sorgulamasına, preview üretmesine ve story testlerini çalıştırmasına izin veriyor. citeturn0search9turn13search0turn8search3turn8search7

### 2027 hedefi

2027'de hedef component üretmek değil, **tasarım sistemini işletim sistemine dönüştürmek** olmalı.

Benim önerdiğim ikinci faz:

| Yetkinlik | 2027 seviyesi |
|---|---|
| Design token CI | Figma–code drift tespiti |
| Component governance | contribution + deprecation politikası |
| Design QA | automatic visual + interaction + a11y |
| Density | comfortable / standard / compact |
| Localization QA | pseudo-locale + RTL regression |
| AI UI | provenance + confidence + review + rollback |
| Personalization | role/task bazlı saved layouts |
| Domain packs | EA/EOP/EBM/ERX özel patternleri |
| Observability | component kullanım telemetry'si |
| Adaptive UI | viewport değil container/task temelli dönüşüm |

Figma'nın variables ve modes sistemi light/dark gibi bağlamların aynı semantic token katmanı üzerinden yönetilmesine, ayrıca variable collection'ların farklı theme ve brand bağlamlarına genişletilmesine olanak veriyor. Bu, 2027'de sadece “dark mode” değil çoklu brand/tenant ve high-contrast architecture'a evrilebilmek açısından önemli. citeturn8search17

### 2030 vizyonu

Buradaki bölüm bir tahmin değil, **tasarım hedefi** olarak okunmalıdır.

2030 için ekranları statik page template'ler değil, **semantic task descriptions** olarak tasarlamanı öneriyorum.

Örneğin:

```text
Task:
Review purchase exception

Objects:
Order
Supplier
Budget
Policy violation

Capabilities:
Inspect
Compare
Comment
Approve
Reject
Escalate

Risk:
High

Data density:
High

Primary interaction:
Comparison + decision
```

Aynı task:

```text
320px phone
→ record summary
→ key exceptions
→ sequential inspection
→ bottom action surface
```

```text
1440px desktop
→ grid
→ master/detail
→ persistent inspector
→ batch operations
```

```text
AI-assisted mode
→ anomaly summary
→ evidence
→ recommendation
→ human decision
```

Böylece adaptive UI, “responsive CSS” olmaktan çıkar ve **task-adaptive interface architecture** haline gelir.

Bu yönün bugün temeli zaten oluşuyor: Figma MCP designs/components/variables bağlamını agent'a taşıyor; Storybook MCP agent'ın gerçek component metadata'sını ve testlerini sorgulamasına imkan veriyor; Carbon ise data table içinde AI-generated cell/row/table için ayrı AI presence ve explainability işaretleri tanımlamaya başladı. Bunları birleştirdiğimizde semantic, agent-readable design systems yönünde güçlü bir eğilim olduğu söylenebilir; bu bir gelecek çıkarımıdır. citeturn8search14turn8search3turn4search0

### 2035 vizyonu

2035 tasarım sistemi yalnız web component library olmayabilir. Aynı semantic layer:

```text
visual UI
conversational UI
voice
agent
automation
mobile
desktop
embedded
```

tarafından tüketilebilir hale gelmelidir.

Bunun için bugünden component'in yalnız “nasıl göründüğü” değil:

```text
purpose
data semantics
permissions
risk
states
actions
relationships
localization rules
accessibility semantics
AI provenance
```

bilgilerinin de sistemde tanımlanması gerekir.

WCAG 3 bu gelecek açısından takip edilmesi gereken önemli çalışma alanlarından biri; ancak Mart 2026 sürümü hâlâ **Working Draft** durumunda. Bu nedenle bugün production conformance hedefini WCAG 2.2 AA üzerinden kurmak, WCAG 3'ü ise gelecek uyumluluğu için izlemek daha doğrudur. citeturn6search0turn6search4turn6search8


## Form ve data table tasarım disiplinleri

Anahtar sorunun cevabı burada:

> “Bir form veya data table'ın doğru olduğunu hangi kriter belirler?”

**Görsel stil değil; görevin semantiği, interaction cost, hata riski, erişilebilirlik, karşılaştırma ihtiyacı, localisation resilience ve işlem hacmi belirler.**

### Formlar için doğru disiplin

İyi enterprise form şu modeli takip etmelidir:

```text
Context
  ↓
Section
  ↓
Field group
  ↓
Label
  ↓
Instruction / helper
  ↓
Control
  ↓
Validation
  ↓
Consequences
  ↓
Action
```

WCAG 2.2, input'ların uygun label veya instruction'a sahip olmasını ister. GOV.UK Design System de placeholder yerine anlamlı label, spesifik error mesajı ve uzun formlarda error summary + ilgili alana yönlendirme gibi pattern'ler kullanır. citeturn0search26turn4search10turn4search1turn4search13

Spectrum'ın field label ve combo-box rehberinde top-aligned label'lar özellikle uzun metin, localization ve responsive layout için öneriliyor. Dolayısıyla senin global SaaS sistemin için form default'u **top label** olmalıdır. citeturn2search16turn2search23

Side-label yalnız uzman kullanıcılar ve yüksek yoğunluklu desktop property editor gibi bağlamlarda alternatif variant olmalıdır.

### Form yerleşim kuralı

320px:

```text
Label
Input
Helper/Error

Label
Input
Helper/Error

Primary action
Secondary action
```

Desktop'ta bile kritik formların otomatik olarak iki sütuna dönüştürülmesini önermiyorum. İki kolon ancak birbirine semantik olarak bağlı ve kısa alanlarda kullanılmalı:

```text
First name | Last name
Start date | End date
Min        | Max
City       | Postal code
```

Şunlarda tek kolon daha güvenlidir:

```text
legal settings
permissions
financial configuration
workflow logic
policy
AI instructions
long descriptions
```

Carbon'ın form pattern'i formun complexity ve bağlama göre dedicated page, side panel veya dialog olarak sunulabileceğini açıkça ayırıyor. Fluent drawer rehberi de çok adımlı drawer akışlarını iki-üç adımdan uzun tutmamak ve daha kompleks işler için daha odaklı bir yüzeye geçmek gerektiğini söylüyor. citeturn1search6turn2search12

### Validation davranışı

Doğru model:

```text
User enters
↓
System preserves value
↓
Validation explains exact issue
↓
User corrects
↓
System confirms
```

Yanlış model:

```text
Enter value
↓
INVALID
↓
Red border
```

Hata durumu için yalnızca kırmızı border kullanma. Label/error text, icon veya başka bir non-color indicator ve programmatic association birlikte bulunmalı. WCAG ilişkilerin yalnız görsel formatta değil programmatically de korunmasını gerektiriyor. citeturn10search9

Submit'ten sonra uzun formda:

```text
Error summary
  "3 alan düzeltilmeli"

→ Vergi numarası ...
→ Fatura adresi ...
→ Para birimi ...
```

ve ilgili alanlardaki inline errors aynı anlamı taşımalıdır. GOV.UK error-summary pattern'i keyboard focus'un summary'ye taşınmasını ve listedeki hataların ilgili alanlara bağlantı kurmasını öneriyor. citeturn4search13

### Data table mı, data grid mi?

Bu ayrımı tasarım sisteminde mutlaka formalize etmelisin.

**Table**:

```text
read
scan
compare
sort
```

**Interactive data table**:

```text
sort
filter
search
paginate
select
bulk action
```

**Data grid**:

```text
spreadsheet-like keyboard model
cell editing
row editing
column resizing
column pinning
column visibility
large datasets
complex selection
```

**Tree grid**:

```text
hierarchy
+
column comparison
```

Atlassian'ın Dynamic Table'ı pagination, sorting ve reordering gibi interactive table özelliklerini birleştirirken; Carbon data table da selection, expandable rows, toolbar, pagination, overflow actions ve AI-generated cell/row/column durumlarını ayrı davranışlar olarak tanımlar. citeturn1search7turn4search0

### Table tasarımının temel kriteri: karşılaştırma

Şu soru birinci sorudur:

> Kullanıcı farklı kayıtların aynı property'lerini karşılaştırmak zorunda mı?

Evetse, kart yaklaşımı çoğu zaman yanlış olur.

Örneğin:

```text
Supplier | Country | Risk | Cost | Lead time | Status
```

kullanıcının altı supplier'ı karşılaştırması gerekiyorsa:

**table doğrudur.**

Bunu mobile'da şu hale getirirsen:

```text
[Supplier A card]
Country
Risk
Cost
Lead time
Status

[Supplier B card]
Country
Risk
Cost
Lead time
Status
```

kullanıcının karşılaştırma maliyeti yükselir.

USWDS'nin responsive table yaklaşımı narrow viewport'larda stacked view sunabiliyor, fakat bu yalnız karşılaştırma ihtiyacının sınırlı olduğu veri yapılarında iyi çözümdür. citeturn4search2

Bu nedenle tasarım sisteminde her data component'e bir property eklemeni öneriyorum:

```yaml
comparison_priority:
  none
  low
  medium
  high
  critical
```

`high` veya `critical` ise mobile adaptation table semantics'i mümkün olduğunca korumalı.

### Mobile table stratejileri

Her table için aşağıdaki dört stratejiden biri bilinçli seçilmeli.

**Priority columns**

```text
Name | Status | Value | …
```

geri kalan kolonlar horizontal scroll veya detail view ile erişilebilir.

**Row summary + detail**

```text
Customer A
Risk: High
Revenue: 1.2M
>
```

tıklayınca full record view.

**Stacked record**

Karşılaştırma zayıfsa.

**Controlled horizontal table**

Finansal, spreadsheet, ERP ve comparison-heavy işler için bazen en dürüst mobile çözüm budur.

Buradaki önemli nokta: **horizontal scroll'ü ideolojik olarak yasaklama.** Page'in yatay kayması yanlış olabilir; fakat karşılaştırma semantiğini koruyan kontrollü grid viewport yatay scroll'u bazı professional interfaces için doğrudur.

Carbon da data table için geniş alan ayrılmasını özellikle öneriyor ve mobil/touch koşullarında hover'a bağlı action visibility gibi davranışları değiştirdiğini belirtiyor. citeturn4search0

### Column davranışları

Enterprise data grid standardında aşağıdakiler bulunmalı:

| Özellik | Varsayılan karar |
|---|---|
| Text alignment | logical start |
| Number | logical end |
| Currency | logical end |
| Dates | locale formatted |
| Status | text + semantic styling |
| Actions | logical end |
| Selection | başlangıç control kolonu |
| Sorting | header |
| Filtering | filter bar/header popover |
| Resize | desktop expert mode |
| Reorder | user customization |
| Visibility | column manager |
| Pinned columns | large grid |
| Saved view | enterprise workflows |
| Density | comfortable/standard/compact |
| Row actions | touch/focus ile de erişilebilir |
| AI-generated values | provenance indicator |

CSS açısından `left/right` yerine logical `start/end` düşünmek RTL localization'ı ciddi ölçüde kolaylaştırır. W3C'nin 2026 internationalization teknikleri CSS logical properties kullanımını açıkça tavsiye ediyor. citeturn7search0turn7search4

### Data density nasıl yapılmalı?

Senin `min font-size 1rem` tercihin enterprise SaaS için gayet uygulanabilir.

Density'yi:

```text
font 13px
```

yaparak değil:

```text
row padding
secondary metadata
toolbar padding
column visibility
icon treatment
information hierarchy
```

üzerinden sağlamanı öneriyorum.

Üç density mode yeterli:

```text
comfortable
standard
compact
```

Mobile:

```text
standard / comfortable
```

Desktop pointer-heavy EA/EOP:

```text
compact
```

Fluent spacing sistemi 4px taban kullanıyor ve responsive durumda spacing'in viewport'a göre değişebileceğini belirtiyor; Carbon data table da farklı row heights ve toolbar heights tanımlıyor. citeturn2search3turn4search0


## Adaptive mobile, typography, renk, i18n ve erişilebilirlik

### 320px gerçek başlangıç noktası olsun

Buradaki yaklaşımına katılıyorum:

> Önce 320px tasarla, sonra desktop'a adapte et.

Ama bunu “320 tasarımını büyütmek” şeklinde değil:

> **320px'de task modelini çöz, sonra daha fazla alanda yeni affordance'lar ekle**

şeklinde düşün.

Önerdiğim design/test bands:

| Genişlik | Tasarım sınıfı |
|---:|---|
| 320–359 | native minimum |
| 360–479 | phone |
| 480–599 | plus phone / foldable pane |
| 600–767 | mini tablet |
| 768–1023 | tablet / plus tablet |
| 1024–1439 | laptop |
| 1440+ | desktop / large workspace |

Bunların cihaz tespiti olarak kullanılmasını değil, Figma/Storybook test fixtures olarak kullanılmasını öneriyorum. Material Design da breakpoint'leri sabit cihaz isimlerinden ziyade available window size'a göre layout değişiminin tetikleyicileri olarak tanımlar. citeturn1search8

Modern component library'de ayrıca container query mantığı kullanılmalı. Örneğin DataGrid 1440px browser içinde olsa bile 480px side pane'e yerleştirildiyse 480px davranışını göstermelidir.

### Touch alanları

WCAG 2.2 AA minimum target size kriteri birçok durumda en az 24×24 CSS px hedef tanımlar. Buna karşılık native-platform rehberleri daha geniş hedefler öneriyor: Apple genel olarak 44×44 pt hit region, Material/Android ise 48×48dp touch target öneriyor. citeturn0search14turn11search3turn11search1turn11search2

Bu nedenle mobile SaaS sisteminde:

```text
visible icon: 20–24px
hit target:   44–48px
```

yaklaşımı daha doğru.

Böylece data density ile touch usability arasında doğrudan çelişki oluşmaz.

### Radius

`max 12px` demişsin, ardından yaklaşık `0.5rem` demişsin. 16px root size varsayımında 0.5rem = **8px**, dolayısıyla ikisi aynı değer değil.

Benim önerim:

```text
radius-xs      2px
radius-sm      4px
radius-md      6px
radius-lg      8px

surface max    8px
control max    8px
input          ayrıca yönetilebilir
```

Pill yalnız:

```text
tag
badge
toggle
segmented affordance
avatar
status
```

gibi semantik olarak kapsül/yuvarlak olan bileşenlerde.

Fluent 2 de rectangle components için tipik olarak 4px radius kullanıyor, daha büyük components için 8px ve 12px seviyelerine çıkıyor. Bu nedenle 8px normal SaaS surfaces için yeterince çağdaş ama excessive-rounding üretmeyen mantıklı bir sınırdır. citeturn2search9

### Roboto 400 ve global typography

Roboto 400'ü Latin ana fontu olarak koruyabilirsin.

Fakat “tam g11n/i18n/l10n” istiyorsan:

```css
font-family:
  Roboto,
  "Noto Sans",
  system-ui,
  sans-serif;
```

başlangıcı yeterli olmayabilir; script bazlı Noto families gerekecektir.

Google'ın Noto koleksiyonu 1.000'den fazla dil ve 150'den fazla writing system hedefliyor; dolayısıyla Arabic, CJK, Devanagari, Thai gibi script'leri global font fallback architecture içinde düşünmek gerekir. citeturn7search2turn7search14turn7search26turn7search34

Yani:

```text
Brand typography ≠ tek font dosyası
```

Doğru model:

```text
brand typography policy
    ↓
script-specific family mapping
```

### Globalization mimarisi

i18n'i “translation JSON” olarak görmek kesinlikle yetersiz.

Unicode CLDR; tarih, saat, currency, sayı, unit ve plural davranışlarının diller/ülkeler arasında ciddi farklılık gösterdiğini ve major software systems tarafından locale formatting altyapısında kullanıldığını belirtiyor. citeturn7search1turn7search25

Minimum global architecture:

```text
BCP 47 locale
Unicode
CLDR
time zone
currency
number format
date/time
plural rules
collation
RTL
bidi
CSS logical properties
address formats
phone formats
measurement units
pseudo-localization
text expansion
```

BCP 47 örneği:

```text
tr-TR
en-US
en-GB
de-DE
ar-SA
ja-JP
```

şeklindedir. citeturn0search35

Ayrıca:

```text
language ≠ country
country ≠ currency
currency ≠ timezone
```

modelini sistem boyunca koru.

Örneğin commerce configuration:

```yaml
market: TR
language: tr-TR
currency: TRY
timezone: Europe/Istanbul
tax_mode: inclusive
```

ayrı properties olmalı.

### RTL

RTL yalnız menüyü sağa taşımak değildir.

Şunlar değişir:

```text
navigation direction
alignment
chevrons
drawer direction
table start/end
breadcrumb direction
spacing
icon semantics
mixed bidi text
```

Carbon menu component örneğin RTL'de menu ile caret yönlerini birlikte mirror ediyor. W3C de layout'ta `left/right` yerine logical `start/end` kullanılmasını öneriyor. citeturn4search27turn7search4

### Renk sistemindeki kritik konu

Primary:

```text
#FFB900
```

Dark canvas:

```text
#080616
```

iyi bir marka kombinasyonu oluşturabilir.

Fakat `#FFB900` küçük metin olarak beyaz üzerinde kullanılmamalı.

WCAG normal text için minimum 4.5:1 contrast istiyor. citeturn10search0

Benim standart sRGB hesaplamamda:

```text
#FFB900 / #FFFFFF ≈ 1.72:1
```

dolayısıyla sarı metin + beyaz background başarısızdır.

Buna karşılık:

```text
#000000 / #FFB900 ≈ 12.19:1
```

çok güçlüdür.

Dolayısıyla primary button:

```text
background: #FFB900
foreground: near-black
```

olmalı.

Dark canvas üzerinde sarı accent'in kontrastı da yüksektir:

```text
#FFB900 / #080616 ≈ 11.63:1
```

Ancak bir renk yüksek kontrastlı olsa bile bütün component states'in accessibility koşullarını otomatik karşılamış sayılmaz; hover, focus, border, disabled, selected ve non-text indicators da ayrı değerlendirilmelidir. WCAG non-text ve focus contrast'ı ayrıca ele alır. citeturn10search4turn10search7

“Parlement mavisi” için tek evrensel standardize hex bulunmuyor. Arama sonuçlarında `#012353` “Parliament Blue” adıyla kullanılan örneklerden biri; fakat bunu doğrudan standardın kabul etmeni önermiyorum. Önce brand token olarak `brand.secondary` oluştur, ardından light/dark semantic usage'lara göre kontrast testinden geçirip değeri freeze et. citeturn9search0

### Accessibility hedefi

2026–2027 production baseline:

```text
WCAG 2.2 AA
```

olmalı.

Özellikle:

```text
Focus visible
Focus not obscured
Target size
Labels/instructions
Redundant entry
Accessible authentication
Contrast
Keyboard operation
```

kritik. W3C, WCAG 2.2'yi yeni conformance hedefi olarak tavsiye ediyor. citeturn0search2turn0search6

Global/e-commerce hedefi varsa Avrupa tarafı da önem kazanıyor. European Accessibility Act e-commerce dahil belirli ürün ve hizmetleri kapsıyor; EN 301 549 tarafında 2026'da yeni revizyon süreci de devam ediyor. citeturn6search1turn6search22turn6search26


## Aynı font ve renklerle birbirinden tamamen farklı tasarım paradigmaları

Sen özellikle:

> font ve renk değişmesin, tasarım anlayışları tamamen farklı olsun

diyorsun.

Bu doğru bir deney. Bir tasarım sisteminin olgunluğunu da test eder.

Aşağıdaki paradigmaların hepsinde:

```text
Roboto
#FFB900
Parliament Blue semantic token
#080616 dark canvas
max normal radius: 8px
```

aynı kalabilir.

Değişecek şeyler:

```text
information architecture
spacing
density
containers
elevation
card frequency
navigation
pane structure
toolbar architecture
motion
progressive disclosure
```

### Precision Flat 2.0

**Default design language olarak bunu seçerdim.**

Görsel yapı:

```text
low/no shadow
1px semantic borders
tonal surfaces
6–8px radius
clear input boundaries
clear button fill
minimal cards
strong focus states
```

Form:

```text
label
bordered input
helper
error
```

Table:

```text
flat canvas
row separators
subtle row hover
sticky header
toolbar
```

Avantajı çok yüksek bilgi yoğunluğuna rağmen affordance'ın kaybolmaması. Flat 2.0'ın ana avantajı da tam olarak aşırı-flat tasarımın kaybettiği interaction signifier'larını geri kazandırmasıdır. citeturn5search0turn5search4

### Analytical Console

EA/EOP/ERX için muhtemelen en karakteristik varyant.

Desktop:

```text
┌ Navigation ┬ Workspace ┬ Inspector ┐
│            │           │           │
│            │ data grid │ context   │
│            │           │ actions   │
└────────────┴───────────┴───────────┘
```

Özellikleri:

```text
split panes
dense grids
sticky toolbars
resizable panels
keyboard shortcuts
saved views
command palette
minimum cards
```

Burası “dashboard”dan çok professional workstation hissi verir.

Mobile'da aynı model:

```text
Workspace
↓
Record
↓
Inspector full-screen
↓
Actions
```

şeklinde sequential hale gelir.

### Editorial Enterprise

EBM, governance, strategic planning, reporting ve architecture documentation için.

Görsel merkez:

```text
typography
content hierarchy
sections
narrative
tables embedded in context
```

Daha az card.

Daha fazla:

```text
heading
subheading
description
key metrics
section
table
```

Bu paradigmada page'i boxes değil content flow organize eder. Fluent'in spacing/proximity yaklaşımı fiziksel separator kullanmadan bile ilişkilerin whitespace ile ifade edilebileceğini vurguluyor. citeturn2search3

### Modular Command Center

Rol bazlı ana dashboard'lar için.

```text
┌ KPI ┐ ┌ Alert ┐ ┌ Tasks ┐
├─────┴─────────┬─────────┤
│ Operations    │ AI      │
│               │ insight │
└───────────────┴─────────┘
```

Card UI burada anlamlı.

Ama data table screen'e geçildiğinde card oranı tekrar düşmeli.

Yani:

```text
Dashboard = card-heavy
Workspace = surface-heavy
Forms = section-heavy
Grid = table-heavy
```

Bu ayrım sistemin görsel monotonluğunu da azaltır.

### Controlled Glass

Glass'i tamamen atma; **yerini doğru belirle.**

Kullan:

```text
global header
command palette
floating action surface
temporary contextual toolbar
hero overlay
marketing feature surface
```

Kullanma:

```text
data cells
long forms
validation surfaces
accounting tables
editable grid
dense inspector
```

NN/G glassmorphism rehberi translucency'nin hierarchy/depth sağlayabileceğini fakat yüksek kullanımda erişilebilirlik ve usability sorunları doğurabileceğini belirtiyor. Apple'ın Liquid Glass yaklaşımı da material'i sistem navigation/control diline bağlamış durumda. citeturn5search1turn5search6

Dolayısıyla “glassmorphism design system” değil:

> **glass-capable design system**

yap.

### Expressive Commerce

Amazon Türkiye / Alibaba benzeri yetenek seviyesi için ayrı commerce domain kit üret.

Ancak birebir trade-dress clone yerine capability parity hedeflemeni öneriyorum:

```text
global commerce header
search
suggest
mega navigation
campaign layer
breadcrumbs
gallery
product options
price
promotion
stock
delivery
seller
trust
buy box
reviews
recommendation
legal footer
market/locale controls
```

Product detail desktop:

```text
┌ Gallery panel ──────┬ Option / information panel ┐
│                     │                            │
│ media               │ title                      │
│ thumbnails          │ variant                    │
│ zoom                │ price                      │
│                     │ delivery                   │
│                     │ seller                     │
│                     │ CTA                        │
└─────────────────────┴────────────────────────────┘
```

Mobile:

```text
Title
Gallery
Price
Variant
Delivery
Seller
Sticky CTA
Details
Reviews
```

Yani mobile'da desktop'un “sol panel + sağ panel” metaforunu korumaya çalışma; **decision sequence**'e dönüştür.


## Figma MCP çalışma modeli ve prompt'lar

Ağustos 2026 itibarıyla Figma MCP tasarım sistemleri için sıradan screenshot-to-code aracından daha güçlü durumda. `get_design_context`, component/variable/layout context çıkarabiliyor; `get_screenshot` görsel fidelity kontrolü için kullanılabiliyor; `get_metadata` büyük selection'larda yapısal özet almayı sağlıyor. Figma'nın kendi custom-rules rehberi de önce `get_design_context`, ardından screenshot alınmasını; çok büyük context'te metadata ile scope'un daraltılmasını öneriyor. citeturn8search1turn8search5

Remote MCP ve Figma skills tarafında `figma-use`, frame, component, variables ve layouts gibi native canvas içeriği üretmek/değiştirmek için kullanılan temel skill. citeturn8search13

Bunun için çalışma sırası:

```text
requirements
↓
design principles
↓
tokens
↓
component taxonomy
↓
Figma variables
↓
primitives
↓
patterns
↓
screens
↓
prototype
↓
Code Connect
↓
implementation
↓
Storybook validation
```

olmalı.

**Screen prompt ile başlama.**

İlk prompt “dashboard oluştur” olmamalı.

### Figma MCP master requirement prompt

```text
/figma-use

Act as a principal enterprise product designer,
design-systems architect and accessibility specialist.

We are designing a global enterprise SaaS ecosystem:

EA Platform
EBP — Enterprise Business Platform
EOP — Enterprise Operations Platform
EBM — Enterprise Business Management
ERX — Enterprise Resource eXecution

Before creating UI, inspect the current Figma file/library.

Do not create new components or variables until you have:

1. inventoried existing variables,
2. inventoried components and component properties,
3. found duplicate or conflicting tokens,
4. mapped existing light/dark modes,
5. identified missing responsive states,
6. identified missing RTL/i18n support,
7. identified accessibility risks,
8. identified component gaps.

Non-negotiable design constraints:

- mobile-native first
- design first for exactly 320px
- body and form-label typography >= 1rem equivalent
- Roboto >= weight 400 for Latin
- provide script-compatible Noto fallback strategy
- light and dark are first-class
- primary color #FFB900
- dark canvas #080616
- secondary accent uses the governed Parliament Blue token
- normal component/surface radius <= 8px
- inputs may use separately governed radius
- data dense
- typography first
- content first
- adaptive UI
- conversion aware
- motion must communicate state, continuity or causality
- Flat 2.0 is the baseline interaction language
- cards are contextual, not universal
- glass/translucency is prohibited behind dense forms and tables
- WCAG 2.2 AA target
- keyboard complete
- RTL ready
- localization ready
- CSS logical start/end semantics
- no hard-coded locale assumptions

Produce an audit/design-planning page containing:

- current-state inventory
- design-token inventory
- component inventory
- gap matrix
- risk matrix
- proposed design-system architecture
- proposed component taxonomy
- proposed Figma page/library structure
- implementation priorities

Do not redesign screens yet.
```

### Figma MCP form prompt

```text
/figma-use

Using the approved enterprise design-system foundations,
create a high-fidelity Form component system.

Components:

Field
FieldLabel
FieldHint
FieldError
TextField
TextArea
NumberField
MoneyField
PercentField
Select
Combobox
MultiSelect
Checkbox
CheckboxGroup
RadioGroup
Switch
DateField
DateRange
FileUpload
EntityPicker
AddressFieldset
PhoneField
FormSection
Fieldset
ErrorSummary
InlineMessage
FormActions

Every appropriate component must cover:

default
hover
focus-visible
filled
disabled
readonly
loading
error
success
required
optional
help text
prefix
suffix

Rules:

- label above input by default
- placeholder never replaces a label
- body/label text minimum 1rem
- Roboto minimum weight 400
- error content must wrap
- state cannot rely on color alone
- touch interaction area must remain mobile friendly
- 320px must not horizontally overflow
- support long localized labels
- support RTL
- use Auto Layout
- reuse existing variables and components
- do not introduce primitive hard-coded color values

Create demo compositions for:

simple form
complex configuration form
high-risk enterprise form
side-panel form
multi-step form
mobile 320px form
RTL form
dark form
```

### Figma MCP data-table prompt

```text
/figma-use

Design an enterprise-grade DataTable/DataGrid family
for EA / EBP / EOP / EBM / ERX.

Do not treat the table as a decorative component.
Optimize for comparison, scanning and professional workflows.

Create:

table title/context
toolbar
search
filters
saved views
column header
cell
row
selection
bulk actions
row actions
pagination
empty state
no-results state
loading/skeleton
error state
expanded row
tree row
inline-edit cell
row-edit mode

Capabilities:

density =
comfortable | standard | compact

selection =
none | single | multiple

hierarchy =
flat | expandable | tree

editing =
read | cell | row

column =
normal | sorted | filtered | pinned

AI presence =
none | cell | row | column | table

Alignment rules:

text -> logical start
number -> logical end
currency -> logical end
actions -> logical end

Do not reduce normal data typography below 1rem
to achieve density.

Use padding, row height and secondary-data visibility instead.

Create 320px adaptations for:

A. priority columns + controlled horizontal scroll
B. row summary + full-screen detail
C. stacked record view

Annotate when each mobile strategy is appropriate.

Never convert every table to cards automatically.
```

### Figma MCP farklı paradigma prompt'u

Bu prompt özellikle senin “font/renk aynı, paradigmalar tamamen farklı” talebin için önemli:

```text
/figma-use

Using exactly the same:

- font family
- typography scale
- brand colors
- semantic colors
- radius tokens

create four completely different high-fidelity
enterprise SaaS design concepts for the exact same screen.

Concept A:
Precision Flat 2.0

Concept B:
Analytical Console

Concept C:
Editorial Enterprise

Concept D:
Modular Command Center

Do not differentiate them by changing the font or colors.

Differentiate through:

information architecture
density
whitespace
pane structure
navigation architecture
container strategy
border/elevation grammar
card frequency
toolbar location
progressive disclosure
motion behavior
content hierarchy

The comparison screen must contain:

- a complex enterprise form
- a data grid
- filtering
- page navigation
- contextual actions

Create both:

320px
1440px

for every concept.

Annotate the design philosophy and trade-offs.
```

### Figma MCP controlled-glass prompt

```text
/figma-use

Create an optional Controlled Glass visual treatment.

Glass/translucency is allowed only on:

global navigation
command palette
transient overlays
floating contextual toolbars
hero/showcase layers

Glass is prohibited on:

forms
editable fields
data tables
data grids
validation surfaces
primary reading surfaces

Create light and dark examples.

Every glass surface must also have
an opaque fallback variant.

Do not alter existing typography or brand colors.
```

### Figma MCP commerce prompt

```text
/figma-use

Design a global marketplace product-detail experience
with capability parity to mature international marketplaces,
without copying another company's visual trade dress.

Start at 320px.

Include:

commerce header
search and suggestions
category navigation
breadcrumbs
hero/promotional layer where relevant
product gallery
product information / option panel
variants
price
promotions
stock
delivery
seller/trust information
purchase actions
product attributes
review summary
recommendations
multi-layer footer
language/market controls

For desktop create:

left option gallery panel
right option information / decision panel

Both may use sticky behavior where it does not harm
keyboard order, accessibility or content visibility.

The gallery must optimize product inspection.
The information panel must optimize decision clarity.

Use existing design tokens.
```

Figma ayrıca Code Connect üzerinden gerçek repository components ile Figma components arasında property mappings kurulmasını destekliyor; MCP çıktılarının kendi production component'lerine yönelmesi açısından bu kritik. citeturn8search4turn8search11

Bu nedenle implementation prompt'un her zaman şunu içermesini öneriyorum:

```text
Do not recreate a component when a mapped
Code Connect production component already exists.
```

Ve:

```text
First retrieve design context and screenshot.
Only then implement.
```

Bu akış Figma'nın kendi MCP custom-rules rehberiyle de uyumludur. citeturn8search5


## Storybook MCP mimarisi, prompt'lar ve hazırlanmış MD paketi

Storybook'u yalnız component gallery olarak kullanma. Senin kullanımında Storybook şu role sahip olmalı:

```text
Design-system executable specification
```

Yani bir component için:

```text
What it looks like
How it behaves
Which states exist
How it is used
How it fails
How it localizes
How it responds
Whether it passes tests
```

aynı yerde görülebilmeli.

Storybook MCP'nin docs toolset'i `get-documentation`, `get-documentation-for-story` ve ilgili documentation sorgularını; test toolset'i ise `run-story-tests` üzerinden component ve yapılandırıldıysa accessibility testlerini agent'a açıyor. Dev toolset de changed stories ve previews gibi geliştirme bağlamı sağlıyor. citeturn8search3turn8search7

### Storybook kurulumu

Güncel resmi dokümantasyonda MCP addon kurulumu:

```bash
npx storybook add @storybook/addon-mcp
```

ve dev server MCP endpoint'i tipik olarak:

```text
http://localhost:6006/mcp
```

şeklinde sunuluyor. Agent connection için Storybook dokümantasyonu `mcp-add` kullanımını da gösteriyor. citeturn8search10

Storybook'un 2026 resmi release duyurularında MCP özellikle React tarafında tanıtılmış durumda; 10.4'te React MCP metadata'sı geliştirilmiş. React dışı framework kullanacaksan MCP feature parity'sini implementation sırasında ayrıca doğrulamak gerekir. citeturn12search0turn13search0

### Storybook master inventory prompt

```text
Use the Storybook MCP documentation tools.

Before creating components,
inventory the entire existing design system.

For every component return:

component name
category
props
variants
stories
missing states
responsive coverage
320px coverage
dark-mode coverage
RTL coverage
accessibility coverage
test coverage
possible duplicates

Compare the result against these component families:

foundations
actions
forms
navigation
feedback
overlays
data display
data tables/grids
layout
commerce
AI

Do not add new components until the gap analysis is complete.
```

### Storybook form test prompt

```text
Use the production form components already available
through Storybook documentation.

Create or improve stories for:

Default
Focused
Filled
Disabled
Readonly
Loading
Required
HelpText
Error
Success
LongLabel
LongError
RTL
Dark
Mobile320

Add interaction tests for:

keyboard navigation
validation
submit
error-summary focus management
field focus
disabled behavior

Run Storybook story tests.

Run accessibility checks where configured.

Fix failures in the production component
rather than suppressing the test,
unless the result is a documented false positive.

Rerun tests after each correction.
```

### Storybook data-grid prompt

```text
Use existing production DataTable/DataGrid components.

Create stories for:

Basic
Dense
ManyColumns
LongContent
Sorting
Filtering
SavedView
SingleSelect
MultiSelect
BulkActions
Pagination
Loading
Empty
NoResults
Error
ExpandableRows
TreeGrid
PinnedColumns
InlineEdit
RowEdit
KeyboardNavigation
RTL
Dark
Mobile320PriorityColumns
Mobile320RowDetail

Fixtures must contain realistic enterprise data.

Include:

localized numbers
localized currencies
dates
statuses
long text
mixed identifiers

Do not use lorem ipsum for important states.

Run component tests and accessibility checks.

Document any accessibility limitation introduced
by virtualization or complex grid interaction.
```

### Figma MCP + Storybook MCP birlikte

Bence en önemli prompt bu:

```text
Use Figma MCP and Storybook MCP together.

For the selected Figma component or screen:

1. retrieve Figma design context,
2. retrieve a Figma screenshot,
3. inspect Figma variables and component properties,
4. query Storybook for matching production components,
5. reuse the existing production component where possible,
6. compare props, states, tokens and responsive behavior,
7. list design/code mismatches,
8. implement the smallest change required for parity.

Then:

create/update stories
run story tests
run accessibility checks
verify light
verify dark
verify RTL
verify 320px

Do not create one-off CSS that bypasses
the design system.
```

Bu model Figma'nın structured design context + Code Connect yaklaşımı ile Storybook'un component docs/test toolset'lerinin doğal olarak birbirini tamamladığı noktadır. citeturn8search4turn8search5turn8search3

### Hazırladığım MD paketinin yapısı

Talep ettiğin gereksinim analizi ve prompt belgelerini 13 ayrı Markdown dosyası olarak oluşturdum:

```text
00-vision-2026-2035.md
01-requirements.md
02-design-principles.md
03-design-tokens.md
04-responsive-adaptive.md
05-forms.md
06-data-tables.md
07-i18n-g11n-l10n.md
08-accessibility-quality-gates.md
09-figma-mcp-prompts.md
10-storybook-mcp-prompts.md
11-component-inventory.md
12-quality-scorecard.md
```

Paket içinde bu rapordakilerden daha fazla hazır Figma ve Storybook MCP prompt'u, component inventory, quality scorecard, responsive kuralları, globalization contract ve 2026–2035 vision belgeleri bulunuyor.

[MD gereksinim ve MCP prompt paketini indir](sandbox:/mnt/data/ea-saas-design-system-research-pack.zip)

Bu doküman yapısını bundan sonra repository'de tasarım sistemiyle birlikte version-controlled tutmak doğru olur. Figma MCP skills, tekrar kullanılabilir workflow kurallarını agent'a taşıyabildiği; Storybook MCP de component documentation ve tests'i machine-readable bağlama dönüştürebildiği için, bu `.md` belgeleri tasarım kararlarının yalnız insanlara değil agent'lara da aktarılacağı governance layer haline gelebilir. citeturn8search20turn8search3

En kritik nihai karar ise şu:

> **Form ve data table tasarımında “hangi görsel trendi kullanalım?” sorusu ikinci sorudur. İlk soru “kullanıcının görevi, verinin semantiği, risk düzeyi, karşılaştırma ihtiyacı ve interaction modeli nedir?” olmalıdır.**
>
> Bunun üzerine kurulan en güvenli 2026–2027 foundation: **Flat 2.0 + semantic surfaces + selective cards + functional motion + strict accessibility + native mobile adaptation**.
>
> **Glass, expressive motion ve daha deneysel aesthetics sistemden çıkarılmamalı; fakat veri yüzeylerinin kendisi yerine chrome, hero, navigation ve transient layers üzerinde kontrollü biçimde kullanılmalıdır.**
>
> Böyle bir ayrım, 2030–2035'te interface'in statik ekranlardan semantic, adaptive ve agent-readable task systems'e dönüşmesine de en sağlam altyapıyı verir. Figma MCP, Storybook MCP, Code Connect ve güncel AI-aware design-system yaklaşımlarındaki gelişmeler bu yönde bugünden teknik yapı taşları sağlıyor. citeturn8search0turn8search4turn8search3turn4search0