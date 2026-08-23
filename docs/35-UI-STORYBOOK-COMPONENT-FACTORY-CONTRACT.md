# 35 — UI / Storybook Component Factory Contract (Wave 0, frozen candidate)

**PLANNING ONLY.** Bu dosya Zabuno Theme/UI + Storybook Wave 0 için tek
kanonik senteze giden **dondurulmuş aday sözleşme**dir (marker §15'te).
Var olan kararları (`docs/03`, `docs/06`, `docs/13`, `docs/18`, `docs/26`,
`docs/27`, `docs/33`, `docs/34`) **kopyalamaz**, yalnız birleştirip Wave1–3
writer'ları için tek bir yürütülebilir sözleşmeye indirger. Çelişki halinde
kaynak doküman kazanır; bu dosya yalnız o kaynakların **uygulama sözleşmesi**dir.

## 0. Owner özeti

- **once**: Restoran admin/superadmin ekranları için ortak bir bileşen
  kütüphanesi, token sistemi veya izole görsel test ortamı (Storybook) yok;
  `resources/js/components/` içindeki mevcut bileşenler (`AppShell`,
  `auth/*`, `workspace/WorkspaceApp`) doğrudan Laravel route'larına bağlı,
  izole geliştirilemiyor, backend'siz önizlenemiyor.
- **Fiili durum düzeltmesi (2026-08-22)**: bu bölümdeki "hiçbir Storybook
  paketi kurulmamıştır" ifadesi artık **tarihsel**dir — Storybook `10.5.9`
  bu snapshot'ta kurulu ve yapılandırılmıştır (`.storybook/main.ts`,
  `.storybook/preview.tsx`, `package.json` `@storybook/addon-a11y@^10.5.9`,
  `"storybook": "storybook dev -p 6006"` script'i). Gerçek bileşen kökleri
  `resources/js/components/{catalog,storybook-demo,ui,workspace}/**`
  altında yaşar (örn. `catalog/forms`, `catalog/overlays`, `catalog/layout`,
  `catalog/navigation`, `catalog/feedback`, `catalog/data-display`,
  `catalog/menu`; `storybook-demo/{micro,compound,macro}`); bu köklerde
  hâlihazırda çok sayıda `.stories.tsx` dosyası mevcuttur (örn.
  `catalog/forms/Button.stories.tsx`, `catalog/overlays/compound/
  ConfirmDialog.stories.tsx`, `workspace/BrandEditForm.stories.tsx`,
  `storybook-demo/compound/TextField.stories.tsx`). **Araç kurulumu** (§7.1
  spike gate'i) bu bakımdan **artık geçerlidir**; bu, §10 dosya sahiplik
  matrisinin **tam DTCG token pipeline'ının** (§1, primitive→semantic→
  component→CSS custom properties zinciri) veya §2a'daki micro/compound/
  macro ayrımının Wave1–4 kapsamınca **tüketici tarafından tamamlanmış**
  olduğu anlamına **gelmez** — araç kurulumu ile token/story yazım
  tamlığı ayrı iki iddiadır; hangi mevcut kökün hangi Wave'in dondurulmuş
  sahiplik alanına (§10) karşılık geldiği ayrı bir mutabakat gerektirir ve
  bu dosyanın kapsamı dışıdır (`docs/26` §3 WP izlenebilirliğine bağlıdır).
- **simdi**: Bu dosya, üç dalga (Wave1–3) halinde ilerleyecek UI/Storybook
  fabrikasının **kural kitabını** dondurur — token seti, bileşen katman
  sınırları, route/persona haritası, state modelleri, port/adapter
  sözleşmesi, Storybook iskeleti, WCAG matrisi, responsive/print davranışı
  ve dosya sahiplik matrisi tek yerde toplanmıştır. Storybook aracının
  kurulu olması (yukarıdaki düzeltme) Wave1–3'ün §10 dosya sahiplik
  matrisindeki iş bölümünü, §11 DAG sırasını veya bu dosyanın "dondurulmuş
  aday" statüsünü **değiştirmez** — araç var, tam token/story envanteri
  ve Wave mekaniği bu dosyanın tanımladığı sırayla hâlâ tamamlanmamıştır.
- **fark**: Wave1–3 tamamlandığında her bileşen Storybook'ta backend'siz
  izole geliştirilip/gözden geçirilebilir olacak; restoran admin/superadmin
  ekranları arasında token/bileşen tekrarı olmayacak; her ekran WCAG 2.2 AA
  ve RTL/dark/high-contrast kontrolünden Storybook üzerinden kanıtla geçmiş
  olacak.
- **kullaniciYolculugu** (somut analoji): Bir restoran sahibinin panelde bir
  form doldurup kaydettiği her ekran, bugün her seferinde ayrı ayrı
  "elle çizilen" bir form gibi üretiliyor; Wave1–3 sonrası aynı form,
  ortak bir "form bileşen kalıbı"ndan (fabrika parçası) monte ediliyor —
  parça önceden Storybook'ta tek tek denenmiş, onaylanmış, kataloglanmış
  oluyor; sonuç: aynı "submit → validate → kaydet → görünür" döngüsü ama
  daha az tekrar hata ve daha hızlı üretim.
- **kalanEngel**: Storybook araç kurulumu artık GREEN (yukarıdaki 2026-08-22
  düzeltmesi; React-Vite uyumluluk spike'ı §7.1 fiilen geçerli sayılır) —
  kalan engel, mevcut kökler için tam token pipeline'ının (§1 DTCG zinciri)
  ve §2a micro/compound/macro ayrımının Wave1–4 dosya sahiplik matrisiyle
  (§10) mutabık şekilde tamamlanmamış olmasıdır. Mevcut `WorkspaceApp.tsx`
  hâlâ doğrudan
  `fetch` kullanıyor (port/adapter sözleşmesine — §6 — geçiş henüz yapılmadı);
  superadmin ve frontpage/marketing yüzeyleri için henüz hiçbir WP
  izlenebilirlik satırı yok (`docs/26` §3 Stage 1 registry'sinde superadmin
  panel iskeleti yalnız S1-WP02'nin **not-started** kalan kalıntısı
  içinde zikredilir, ayrı bir WP yok — bkz. §3 aşağıda).
- **capability_delta**: Bu dosyanın kendisi **sıfır çalışan kod üretmez** —
  yalnız Wave1–3'ün nasıl inşa edileceğinin dondurulmuş sözleşmesidir.
  Gerçek capability delta (Storybook kurulu, tokenlar CSS'te, ilk N bileşen
  katalogda) yalnız Wave1 implementasyon paketi tamamlandığında oluşur.

## 1. Semantik design token sözleşmesi

Kaynak: `docs/06` §1 (beş tema domeni + ortak token seti), §8 (WCAG AA/AAA,
RTL). Bu bölüm o kararı **uygulanabilir token taksonomisine** indirger.

- **Token katmanı ayrımı**: `primitive` (ham değer: `blue-500`, `4px`) →
  `semantic` (`color-bg-surface`, `color-text-danger`, `space-inset-md`) →
  `component` (`button-primary-bg`) . Bileşenler **yalnız semantic/component**
  token tüketir, primitive token'a doğrudan bağlanmaz (ADR-L06 duplicate
  önleme ilkesiyle tutarlı — iki paralel token sistemi kurulmaz).
- **Zorunlu eksenler**: renk (`light`/`dark`/`high-contrast`, WCAG AA
  kontrast eşiği §8 doğrular), tipografi (scale + line-height + font-weight
  eksen seti), spacing (4px temelli ölçek), radius, elevation (shadow
  seviyeleri), motion (duration/easing + `prefers-reduced-motion` eşleniği),
  z-index (katman sırası registry'si — modal/toast/dropdown/tooltip çakışma
  önleme), density (comfortable/compact — admin tablo yoğunluğu için).
- **RTL**: token seti mantıksal özellik (`inset-inline-start` vb.) üzerinden
  tanımlanır, `left`/`right` fiziksel değer **doğrudan token'a yazılmaz**;
  RTL/LTR aynı token setini kullanır, yön CSS logical properties ile çözülür
  (`docs/13` §2 Arabic RTL zorunluluğuyla tutarlı).
- **Sahiplik**: token seti Wave1'in **tek** çıktısıdır (§10 dosya matrisi);
  Wave2/3 token **tüketir**, yeni token **icat etmez** — yeni ihtiyaç Wave1
  sahibine geri bildirilir (tek kanonik token kaynağı, `AGENTS.md` §2).

## 2. Bileşen katmanları ve import yönü

Kaynak: `docs/03` ADR-L06, `docs/06` §2 (Flowbite first / shadcn source-owned
/ Radix adapter).

```
tokens (§1)
  ← primitives (Flowbite React bileşenleri, npm bağımlılığı)
  ← source-owned (shadcn/ui, resources/js/components/ui/* — kod kopyalanır)
  ← adapters (resources/js/components/adapters/* — yalnız Flowbite/shadcn'de
      eksik erişilebilir primitive gerektiğinde Radix/headless sarmalanır)
  ← patterns (form/table/dialog gibi domain-agnostic kompozit kalıplar)
  ← surfaces (route-bağlı ekranlar: admin/superadmin/public/frontpage)
```

- İçe aktarım **yalnız yukarıdan aşağıya**; `surfaces` asla `adapters`'ı
  atlayıp doğrudan Radix import etmez, `patterns` asla bir `surface`'a
  bağımlı olmaz (döngüsel bağımlılık yasak — ADR-L05 modül izolasyon
  ilkesinin frontend karşılığı).
- **Duplicate önleme**: aynı primitive (örn. Dialog) iki kütüphaneden
  kurulmaz; mevcut kanıt `resources/js/__tests__/ShadcnSourceOwnership.test.ts`
  ve `resources/js/components/adapters/AccessibleSeparator.tsx` bu ilkenin
  zaten uygulandığını gösterir — Wave1–3 bu deseni **genişletir**, yeni bir
  desen icat etmez.
- **Next.js/Filament** bu katmanların hiçbirinde kullanılamaz (ADR-L06).

## 2a. Micro → Compound → Macro kompozisyon sözleşmesi

§2'deki katman sırasının (`primitives`/`source-owned`/`adapters` →
`patterns` → `surfaces`) form ve diğer feature-inşasına uygulanan
**granülerlik** boyutu. Katman *nereden* import edildiğini belirler; bu
bölüm *ne kadar küçük* parçalara bölüneceğini belirler — ikisi birlikte
kullanılır, biri diğerinin yerine geçmez.

- **Micro (birincil bina taşı)**: Label, HelpText, ErrorText, Input,
  Textarea, Select, Dropdown trigger/content/item, Checkbox, Radio, Switch,
  Icon, Button, Spinner, Divider ve benzerleri. Her micro **kendi
  source-owned dosyasında**, kendi TypeScript props sözleşmesiyle,
  kendi Storybook story setiyle ve (davranış/erişilebilirlik/state
  içeriyorsa) kendi component test'iyle yaşar — bir micro başka bir
  micro'nun dosyasına gömülmez. Micro, backend/route/business-rule
  **bilmez**; yalnız görsel/etkileşim/erişilebilirlik sözleşmesi taşır.
- **Compound / pattern (§2'deki `patterns` katmanının granüler karşılığı)**:
  bir alan (field) veya küçük bir etkileşim birimi — örn. `TextField`
  (Label+Input+HelpText+ErrorText kompozisyonu), `SelectField`,
  `DropdownMenu` (trigger+content+item kompozisyonu), `PasswordField`
  (Input+Icon+Button kompozisyonu). Compound, alttaki micro'ları **compose
  eder** — micro'nun markup'ını veya iç davranış/erişilebilirlik kuralını
  **kopyalamaz**, yalnız import edip birleştirir. Compound kendi state
  orkestrasyonunu (örn. `dirty`/`touched`/hata gösterme zamanlaması)
  taşıyabilir ama bu hâlâ UI-state'tir (§5), backend iş kuralı değildir.
- **Macro**: FormSection, DataTable toolbar, AppShell, MediaPicker,
  PricingTable ve benzeri büyük kompozit birimler. Macro **yalnız alt
  katmanları (micro+compound, gerekirse başka macro) compose eder**; kendi
  markup'ında micro/compound'un iç yapısını yeniden yazmaz. Macro
  **route/fetch/business-rule agnostic'tir** — bir macro asla doğrudan
  `fetch` çağırmaz, asla bir route path'i bilmez, asla bir entitlement/
  yetki kararı vermez (bunlar §6 port/use-case katmanının ve §5 state
  modelinin işidir; macro yalnız kendisine geçirilen `UiState`/veri/callback
  prop'larını render eder). Bir surface, bir macro'yu use-case hook'una
  (§6) bağlar — macro'nun kendisi bu bağlamayı bilmez.
- **Over-fragmentation yasağı**: tek satırlık, tekrar kullanılmayan ve
  kendi semantik davranış/stil/erişilebilirlik sözleşmesi taşımayan markup
  (örn. bir kerelik bir `<span className="mt-1">` sarmalayıcı) ayrı bir
  micro dosyasına **bölünmez** — bu dosya yalnız gerçek bir sözleşmesi
  (props tipi, erişilebilirlik rolü, birden fazla yerde reuse) olan
  parçaları ayrı dosya yapar; "her HTML etiketi kendi dosyası" **zorunlu
  değildir** ve önerilmez.
- **Story/test kapsama kuralı**: her form macro'su için hem **her bir
  bileşen micro'sunun kendi story'si** hem de **macro'nun entegre
  story'si** birlikte var olur — biri diğerinin yerine geçmez. State
  matrisi (§5 `UiState`/`UiError` birlik tipleri) her iki seviyede de
  anlamlı olduğu yerde tekrarlanır: micro seviyesinde (örn. `Input`'un
  `error`/`disabled`/`loading` story varyantları) ve macro seviyesinde
  (örn. `FormSection`'ın `validation-error`/`permission-denied`/
  `quota-exceeded` tam-form story varyantları) — micro seviyesindeki
  matris macro seviyesinde **tekrar test edilmez birebir**, yalnız
  macro'nun kompozisyon-özgü davranışı (örn. birden fazla alan hatasının
  birlikte gösterimi) macro seviyesinde eklenir.

## 3. Route / surface haritası (Preview vs. Unavailable)

Kaynak: `docs/06` §1 beş tema domeni, `docs/18` Scope, `docs/26` §3 Stage 1
WP registry.

| Domen | Route örüntüsü | Backend durumu (bu snapshot) | Wave0 etiketi |
|---|---|---|---|
| Restaurant Admin shell | `/panel/restoran/*` | Kısmi (CORE-01 auth + CORE-02 bounded tenancy `docs/33`/`docs/34`; menü/yayın/QR yok) | **Preview** (auth/tenancy ekranları gerçek API'ye bağlı geliştirilebilir; menü/QR ekranları backend yokluğunda mock adapter ile **Preview**) |
| Superadmin | `/panel/admin/*` | Yok — `docs/26` §3'te ayrı bir WP satırı yok, yalnız S1-WP02'nin genel "admin panel iskeleti" kalıntısı not-started | **Unavailable** (backend yok; Storybook'ta yalnız izole bileşen/mock story, route wiring Wave1–3 kapsamı dışı) |
| Storefront/Marketing (frontpages) | `/`, `/page/*` | Content/Frontpages "temel" S1'de planlı, backend not-started | **Unavailable** (izlenebilirlik: `docs/26` §1 satırı "temel" der ama S1 WP registry'sinde ayrı satır yok — bu bir açık gap, §14 taşıyıcı) |
| Public Menu | `/m/*`, `q.*` | Yok (Menu Catalog/Publication/QR Destination S1-WP03/04, not-started) | **Unavailable** |
| QR Print | mPDF çıktı, mm/DPI tabanlı | Yok (S1-WP04, not-started) | **Unavailable** — web layout kısıtları geçersiz, `docs/08` ayrı sözleşme |

"Preview" = Storybook'ta mock adapter ile geliştirilebilir, gerçek route'a
bağlanabilir ama backend eksik olduğu için uçtan uca çalışmaz. "Unavailable"
= backend hiç yok; Storybook'ta yalnız izole/mock story üretilir, route
wiring **yapılmaz** (üretilirse "vibe says done" ihlali sayılır, `docs/27` §4).

## 4. Persona / navigasyon haritası

Kaynak: `docs/02` (rol taksonomisi — bu dosya tarafından **genişletilmez**),
`docs/06` §4 global panel shell.

- **Tek paylaşılan shell** (`AppShell.tsx` deseni — mevcut kod zaten bunu
  kanıtlar), **ayrı navigasyon ağaçları**: Restaurant Admin ve Superadmin
  aynı shell bileşenini (sidebar/topbar/breadcrumb/workspace selector/
  profile menu/notification/global search/command palette) kullanır, nav
  tree verisi persona'ya göre değişir. Shell'in kendisi iki kez
  implemente edilmez (`docs/06` §4).
- **Diner (müşteri) için ayrı login icat edilmez**: `docs/02` rol
  taksonomisinde diner/müşteri için bir authenticated persona **tanımlı
  değildir** — public menu anonim erişimdir. Wave0–3 hiçbir story/route
  "diner login" veya "diner account" varsayımı **kuramaz**; bu iddia
  `docs/02`'de karşılığı olmayan bir rol icat eder ve reddedilir.
- Nav tree veri şekli: `{ persona: 'restaurant-admin' | 'superadmin', items: NavNode[] }`
  — `NavNode` component katmanında (§2 `patterns`) tanımlanır, surface'lar
  yalnız veri sağlar.

## 5. UI state / hata / yetki / entitlement / kota / AI-off modelleri

Discriminated union olarak tanımlanır (TypeScript `type` + `kind`
ayırıcısı), her surface bu birlik tiplerinden birini render eder:

```ts
type UiState<T> =
  | { kind: 'idle' }
  | { kind: 'loading' }
  | { kind: 'success'; data: T }
  | { kind: 'empty' }
  | { kind: 'error'; error: UiError }

type UiError =
  | { kind: 'validation'; fieldErrors: Record<string, string[]> }
  | { kind: 'permission-denied'; requiredRole?: string }
  | { kind: 'entitlement-denied'; requiredPlan: string; feature: string }
  | { kind: 'quota-exceeded'; limit: number; resetAt?: string }
  | { kind: 'not-found' }
  | { kind: 'server-error'; correlationId?: string }
  | { kind: 'network-error' }

type AiAssistState =
  | { kind: 'ai-off' }               // no-credit invariant, docs/33 §9
  | { kind: 'ai-suggesting'; suggestion: unknown }
  | { kind: 'ai-requires-approval'; action: string } // docs/06 §7
```

- `permission-denied`/`entitlement-denied`/`quota-exceeded` UI'da **yalnız
  görüntü kararıdır** — backend her zaman authoritative'dir (§6 ve `docs/06`
  §5); UI bir filtreyi kaldırırsa veri sızmaz, çünkü UI hiçbir zaman
  kendi başına veri döndürmez, yalnız backend'in döndürdüğünü render eder.
- `ai-off` varsayılan durumdur (`docs/33` §9 no-credit AI invariant; `docs/06`
  §7 destructive/publish/payment/permission action asla onaysız tetiklenmez).
- Bu union'lar `patterns` katmanında tanımlanır (§2), her surface kendi özel
  hata tipini **icat etmez** — yeni bir `kind` ihtiyacı bu dosyaya (Wave1
  sahibine) geri bildirilir.

## 6. Port / use-case / mock / HTTP adapter sözleşmesi

Kaynak: `docs/03` ADR-L02 (Onion yönü — React business rule taşımaz, ECA
kararlarını uygular), mevcut `resources/js/lib/csrfHeader.ts` / `auth.tsx` şekli.

```
surface (React component)
  → use-case hook (örn. useLoginUseCase()) — yalnız orkestrasyon, iş kuralı yok
    → port (interface, örn. AuthPort) — TypeScript interface, framework'siz
      → adapter (implementasyon): HttpAdapter (fetch + csrfHeader) | MockAdapter
```

- **Surface asla doğrudan `fetch` çağırmaz.** Mevcut `WorkspaceApp.tsx`
  doğrudan fetch kullanıyor — bu, migration hedefidir (§14 gap), Wave0
  sözleşmesi **yeni** kod için bu deseni zorunlu kılar, mevcut dosyayı bu
  dosya **değiştirmez** (ayrı bir migration WP gerekir).
- Port'lar, `docs/33`/`docs/34`'teki gerçek HTTP kontrat şekillerine
  (register/login/logout/session, workspace create/list/switch) **birebir
  temellenir** — port imzası backend kontratından bağımsız icat edilmez.
- **Mock adapter yalnız Storybook/test'te** derlenir; production build
  mock adapter'ı **içermez** — bu, bundler seviyesinde (ayrı entry / dead-code
  elimination ile) garanti edilir, çalışma zamanı `if (isDev)` dallanması
  **yeterli sayılmaz** (production'a sızma riski `docs/15` shared-host
  güvenlik disipliniyle tutarsız olur).
- Adapter seçimi dependency injection ile yapılır (context/provider), surface
  kodu hangi adapter'ın aktif olduğunu bilmez.

## 7. Storybook sözleşmesi

- **Klasörleme**: `resources/js/components/**/*.stories.tsx`, dosya adı
  bileşenle birebir (`Button.tsx` → `Button.stories.tsx`), aynı klasörde.
- **Katalog gruplama (§2a ile birebir)**: Storybook title kökü §2a
  granülerlik seviyesini yansıtır — `Micro/…`, `Compound/…` (pattern'lerin
  Storybook karşılığı), `Macro/…`, `Surface/…`. Örnek:
  `Micro/Input`, `Compound/Form/TextField`, `Macro/FormSection`,
  `Surface/RestaurantAdmin/MenuList`. Bu dört kök birbirine **karışmaz**
  (bir Macro story'si `Micro/` altına konmaz) — kök, §10 dosya sahiplik
  matrisindeki köklerle **birebir eşlenir**, ayrı bir taksonomi icat
  edilmez.
- **Kompozisyon/bağımlılık görünürlüğü**: her Compound ve Macro story'sinin
  açıklama (docs) bloğu, hangi alt katman parçalarını (hangi Micro'ları,
  hangi Compound'ları) compose ettiğini listeler — bu, §2a'daki "compound
  micro'yu kopyalamaz, compose eder" kuralının Storybook'ta **gözlemlenebilir
  kanıtıdır**; bir Macro'nun docs bloğunda kendi markup'ını yeniden
  yazdığı bir alt parça görünüyorsa bu §2a ihlalidir.
- **Naming**: kök sonrası yol hiyerarşisi bileşenin katman-içi konumunu
  yansıtır (örn. `Compound/Form/TextField`, `Compound/Menu/DropdownMenu`).
- **Decorator zorunluluğu**: her story tema decorator'ı (light/dark/
  high-contrast) + yön decorator'ı (LTR/RTL) + viewport decorator'ı (320px
  dahil) ile sarmalanır — bu üçü olmadan bir story "tamam" sayılmaz (`docs/06`
  §6 320px-first ilkesiyle tutarlı).
- **Deterministik veri**: story'ler asla gerçek zamana (`Date.now()`),
  rastgele değere veya gerçek API'ye bağımlı olamaz; mock adapter (§6) sabit
  fixture döndürür — aynı story her çalıştırmada aynı görüntüyü üretir
  (görsel regresyon testi için ön koşul, `docs/27` §5).
- **React-Vite uyumluluk spike gate'i (§7.1)**: Storybook kurulumundan önce
  mevcut Vite/React/Tailwind sürüm kombinasyonuyla Storybook'un (React-Vite
  builder) uyumlu kurulup kurulmadığı bir izole spike ile doğrulanmalıdır —
  bu gate **artık fiilen geçerlidir**: Storybook `10.5.9` kurulu ve
  yapılandırılmıştır (§0 "Fiili durum düzeltmesi", 2026-08-22). Bu, yalnız
  **araç kurulum** spike'ının GREEN olduğu anlamına gelir; token pipeline
  (§1) ve micro/compound/macro (§2a) tamlığı ayrı, henüz tamamlanmamış
  iddialardır (§0).

## 8. WCAG 2.2 AA matrisi

Kaynak: `docs/06` §8, `docs/13` §1–2 (RTL), `docs/27` §5 (erişilebilirlik
testi zorunlu kategori).

| Eksen | Zorunlu davranış (AA) | AAA aday (kritik akış) |
|---|---|---|
| Keyboard | Tüm interaktif öğeler Tab/Shift+Tab sırayla ulaşılabilir, trap yok (modal hariç, kasıtlı focus trap) | — |
| Focus | Görünür focus ring her tema/kontrastta ayırt edilebilir | Focus-visible genişletilmiş kontrast |
| Label | Her form alanı programatik label'a sahip (`aria-label`/`<label>`), placeholder tek başına label değildir | — |
| Error | Hata mesajı alanla `aria-describedby` ile ilişkili, ekran okuyucu duyurusu (`aria-live`) | — |
| Overlay (modal/toast/dropdown) | `aria-modal`, `role`, ESC ile kapatma, arkaplan `inert`/`aria-hidden` | — |
| Table | `<th scope>`, sıralanabilir kolon `aria-sort` | — |
| Form | Zorunlu alan `aria-required`, disabled/loading durumu duyurulur | — |
| Dropzone (medya) | Klavye ile dosya seçim alternatifi, sürükle-bırak tek erişim yolu değildir | — |
| Reduced motion | `prefers-reduced-motion` token eşleniği (§1) tüm motion token'ında saygı görür | — |
| Reflow / 320px | 320px genişlikte yatay scroll yok, içerik kaybı yok | — |
| Dark / high-contrast | Token kontrast oranı WCAG AA eşiğini her üç modda karşılar | High-contrast modda AAA kontrast hedefi |
| RTL | Mantıksal CSS özellikleriyle ayna; ikon/ok yönü RTL'de tersine döner | Tam görsel regresyon (Stage 2, `docs/13` §2a) |
| Kritik akış (login, ödeme, hesap silme, veri export) | AA taban | **AAA aday** — `docs/06` §8 ile tutarlı, bu dosya AAA'yı zorunlu kılmaz, yalnız aday işaretler |

Stage 1'de RTL **altyapısı** yeterlidir (`docs/13` §2a); tam RTL görsel
completeness Stage 2 kapsamıdır — bu matris o sınırı **değiştirmez**.

## 9. Responsive breakpoint ve print/mm davranışı

- **320px-first**: tasarım en dar breakpoint'ten yukarı doğru genişler
  (mobile-first), `docs/06` §6.
- Breakpoint eksenleri (semantic, primitive px değeri Wave1 token
  implementasyonunda sabitlenir): `xs` (320+), `sm`, `md`, `lg`, `xl` —
  admin shell'de sidebar `md` altında collapse/drawer'a döner.
- **Print/mm**: yalnız QR Print domeni (`docs/08`) web breakpoint
  sisteminin **dışındadır** — mm/DPI/ISO 216 (A4/B4/A5/B6/A6/B7/A7) tabanlı
  ayrı bir layout motoru kullanır, bu dosyanın responsive token'ları QR
  Print çıktısına **uygulanmaz** (ayrı kanonik sahiplik, `docs/08`).

## 10. Dosya sahiplik matrisi (Wave1–3, non-overlapping)

Tek writer ilkesi (`AGENTS.md` §2, kök yönetişim madde 5) frontend dosya
seviyesinde şu şekilde uygulanır — iki writer aynı dosyayı **aynı pakette**
değiştiremez:

Kökler §2a'daki Micro/Compound/Macro granülerliğiyle **birebir** eşlenir —
bir granülerlik seviyesi asla iki farklı dosya kökünde yaşamaz, ve bir
writer kendi seviyesinin kökü dışına yazmaz.

| Wave | Sahip alan (§2a seviyesi) | Dosya kökü | Yazar |
|---|---|---|---|
| Wave1 | Token sistemi + Storybook iskeleti + spike sonucu | `resources/css/tokens/**`, `.storybook/**` | Wave1 writer (tek) |
| Wave2 | **Micro** primitives (Label/Input/Button/Icon vb.) + source-owned + adapter katmanı + kendi story/test'leri | `resources/js/components/micro/**`, `resources/js/components/adapters/**` | Wave2 writer (tek) |
| Wave3 | **Compound**/pattern (field/menu kompozisyonları) + port/use-case/mock-http adapter + kendi story/test'leri | `resources/js/components/compound/**`, `resources/js/lib/ports/**`, `resources/js/lib/usecases/**` | Wave3 writer (tek) |
| Wave4 | **Macro** (FormSection/DataTable toolbar/MediaPicker/PricingTable/AppShell gövdesi — route/fetch-agnostic) + kendi story/test'leri | `resources/js/components/macro/**` | Wave4 writer (tek) |
| Surface (Wave0 kapsamı dışı, ayrı paket) | Route-bağlı ekranlar (Restaurant Admin/Superadmin/public/frontpage) | `resources/js/surfaces/**` | Ayrı surface writer(lar) — Wave1–4'ün hiçbiri bu kökü yazmaz |
| Integration-writer-only (ortak) | **Yalnız barrel** (`index.ts` re-export) dosyaları + kök config (`package.json`/`vite.config.ts`/`tsconfig.json`) + mevcut `AppShell.tsx`/`WorkspaceApp.tsx`'in yeni köklere migration'ı | `resources/js/components/**/index.ts` barrel'ları + kök config dosyaları + `resources/js/components/AppShell.tsx`, `resources/js/components/workspace/WorkspaceApp.tsx` (yalnız migration sırasında) | **yalnız** ayrı, sonraki bir integration paketi — Wave1–4 hiçbiri bu dosyaları değiştirmez; integration writer da Micro/Compound/Macro'nun **iç içeriğini** değiştirmez, yalnız barrel re-export'u ve wiring'i yazar |

Wave1–4 paralel çalışamayan aşamalar birbirinin dosya kökünü değiştirmez
(Wave2/Wave3/Wave4 kendi aralarında ayrı köklerde olduğu için tekil bir
Wave içinde ≤3 worker paralel çalışabilir, bkz. §11); DAG sırası §11'de. Bir
Wave kendi kökü dışında bir dosyayı değiştirmesi gerektiğini düşünürse, bu
bir **scope ihlali**dir ve entegrasyon paketine geri bildirilir — dosyayı
kendisi değiştirmez.

## 11. Paket DAG / dalgalar (Guardian 3-worker batch, RED→GREEN, QA bütçesi, rollback)

```
Wave1 (1 worker: token + Storybook iskelet + spike)
  → Wave2 (≤3 worker paralel: micro primitives / source-owned / adapter —
      her biri ayrı dosya kökü, §10)
    → Wave3 (≤3 worker paralel: compound/pattern / ports+usecases /
        mock-http adapter)
      → Wave4 (≤3 worker paralel: macro bileşenler — FormSection /
          DataTable toolbar / MediaPicker / PricingTable / AppShell gövdesi
          — her biri kendi dosyasında, ortak `resources/js/components/macro/**`
          kökünde)
        → Integration (1 worker: yalnız barrel + surface wiring, ayrı paket)
```

- Her worker batch'i Guardian'ın **3-worker** eşzamanlılık üst sınırına
  uyar (kök yönetişim madde 2 "bounded atomic package").
- **RED→GREEN**: her Wave paketi, kendi allowlist'i içindeki değişiklik için
  önce başarısız (RED) hedefli test yazar, sonra GREEN'e taşır (`docs/27` §3).
- **QA bütçesi**: her paket kendi §3 bütçesine tabidir — bir tam local QA +
  bir CI/full QA (`docs/27` §3, kök yönetişim madde 9); bütçe paketler
  arası **paylaşılmaz**, her Wave kendi ayrı bütçesini taşır (WP02A/WP02B
  ayrımıyla tutarlı desen, `docs/27` §6).
- **Rollback**: her Wave paketi, kendi dosya kökünü (§10) `git revert` ile
  geri alınabilir tek bir commit/PR sınırında tutar; bir Wave'in rollback'i
  bir sonraki Wave'i bloke ediyorsa, sonraki Wave de birlikte geri alınır
  (DAG sırası bunu zorunlu kılar — Wave3 Wave2'ye, Wave4 Wave3'e bağımlıdır).

## 12. Bağımlılık admission (lisans / bakım / bundle)

- Yeni bir npm bağımlılığı eklenmeden önce üç kontrol zorunludur: **lisans**
  (MIT/Apache-2.0/BSD uyumlu; copyleft veya belirsiz lisans reddedilir),
  **bakım** (son 12 ay içinde aktif commit/release, tek-maintainer riski
  değerlendirilir), **bundle etkisi** (gzip boyutu `docs/06` §3 Public Menu
  performance budget'ını — JS < 200KB gzip — tehlikeye atmaz; admin
  domenlerinde daha gevşek ama yine de ölçülür ve raporlanır).
- Bu kontrol Wave1–3'ün her yeni bağımlılık eklemesinde (özellikle Wave1'in
  Storybook araç seçiminde, §7.1 spike sonucu dahil) uygulanır ve paket
  raporunda kanıtlanır (`docs/27` disiplinine ek — bu dosyanın kendi
  admission kapısı).

## 13. Mock→HTTP migration ve no-fake-feature invariant

- **Mock→HTTP migration**: bir surface, backend gerçekten var olduğunda
  (§3 "Preview"den gerçek route'a geçişte) `MockAdapter`'dan `HttpAdapter`'a
  **yalnız DI konfigürasyonu değişerek** geçer — surface/use-case/pattern
  kodu **değişmez** (§6 port sözleşmesinin garantisi budur).
- **No-fake-feature invariant**: bir surface backend'i olmadan (§3
  "Unavailable") asla gerçek bir route'a bağlanmış gibi sunulmaz; mock
  veriyle çalışan bir ekran production build'de erişilebilir bir route
  arkasına konursa bu "vibe says done" ihlalidir (`docs/27` §4) — Storybook
  dışı hiçbir yerde mock adapter'la "çalışıyor" iddiası kurulmaz.
- **Core modüller kapatılamaz**: shell/nav bu invariantı ihlal edecek şekilde
  CORE modüllerini (CORE-01..16) kullanıcıya "disable edilebilir" gibi
  sunmaz — disable/enable yalnız iş modülleri (business modules) için
  vardır, CORE için yoktur (`docs/03` ADR-L01, `docs/04`).
- **Backend authoritative kalır**: UI hiçbir zaman yetki/entitlement/kota
  kararını kendi başına vermez (§5), yalnız backend'in döndürdüğü durumu
  render eder.

## 14. Bilinen açık gap'ler (bu dosyanın taşıdığı, kapatmadığı)

- Storybook araç kurulumu tamam (§0, 2026-08-22 düzeltmesi); açık kalan
  gap, mevcut kökler için §1 DTCG token pipeline'ının ve §2a
  micro/compound/macro ayrımının Wave1–4 §10 dosya sahiplik matrisiyle
  mutabık biçimde tamamlanmamış olmasıdır.
- `WorkspaceApp.tsx` hâlâ doğrudan `fetch` kullanıyor; §6 port sözleşmesine
  migration ayrı bir paket gerektirir, bu dosya migration'ı **yapmaz**,
  yalnız hedefini tanımlar.
- Superadmin ve frontpage/marketing yüzeyleri için `docs/26` §3 Stage 1
  registry'sinde **ayrı bir WP satırı yok** — yalnız §1 modül×stage
  matrisinde "temel" olarak zikrediliyor; bu izlenebilirlik boşluğu bu
  dosyanın kapsamı dışıdır, `docs/26`/`docs/29` sahiplerine bildirilir.
- Lokal geliştirme URL beklentisi: mevcut foundation `http://127.0.0.1:8787`
  altında çalışır (`docs/27` §6, `docs/33` §13a) — bu dosya bu URL'in **şu an
  çalıştığını iddia etmez**, yalnız Wave1–3 sonrası Storybook'un ayrı bir
  port'ta (örn. `http://localhost:6006` — araç seçimine bağlı, §7.1 spike
  sonucu belirler) çalışacağı beklentisini not eder.

## 15. Kanonik sahiplik

Bu dosya, Zabuno Theme/UI + Storybook Wave 0 sentez sözleşmesinin tek
kanonik kaynağıdır. Token/bileşen/route/persona/state/port/Storybook/WCAG/
responsive/dosya-sahiplik kararlarının **kendisi** burada yaşamaz — her biri
kaynak dokümanında (`docs/03`, `docs/06`, `docs/13`, `docs/18`, `docs/26`,
`docs/27`, `docs/33`, `docs/34`) kanoniktir; bu dosya yalnız onları Wave1–3
writer'larının doğrudan uygulayabileceği tek bir sözleşmeye **indirger**.

**Marker**: `UI_W0_CONTRACT_FROZEN_CANDIDATE`
