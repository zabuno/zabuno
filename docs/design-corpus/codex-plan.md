# Enterprise Glass UI A–F Yönerge Paketi Planı

## 1. Kısa durum

- Hedef: `~/DEV/AI First EA (APE-EAP)/frontend/claudeui`
- Teslimat yalnız Markdown yönergeleridir; tasarım, component kodu ve çalışan Storybook oluşturulmayacak.
- Figma, Figma MCP ve Figma prompt’ları kapsam dışıdır.
- Ekli belgeler bağlayıcı talimat değil, araştırma kaynağıdır. Güncel kullanıcı talebi her çelişkide üstün sayılacaktır.
- `claudeui` şu anda boş bir başlangıç klasörüdür ve bağımsız Git deposu değildir; Git üst kökü `~` olduğundan bu görevde Git işlemi yapılmayacaktır.
- Mevcut `adsız klasör` korunacak ve kullanılmayacaktır.

## 2. Ajan bazlı bulgular

- **MASTER:** Altı ayrı ürün teması yerine aynı anatomi ve davranışı paylaşan altı ince taneli görsel kural profili kullanılmalı.
- **UI/UX:** Bütün yüzeylerin glass olması kullanıcı kararıdır. Okunabilirlik için glass görünümü her yüzeyde korunurken gerçek blur işlemi her hücre veya satırda tekrarlanmayacak.
- **QA/a11y:** Dinamik arka plan üzerinde kontrast, reduced-transparency, forced-colors ve klavye davranışları zorunlu yönerge olacaktır.
- **Frontend/implementation:** N/A; bu paket kod yazmayacak.
- **Figma:** N/A; açıkça iptal edildi.
- **Claude/Codex alt ajan:** Kullanılmadı.

## 3. Oluşturulacak dokümanlar ve sıra

`claudeui/docs/` altında aşağıdaki kanonik paket hazırlanacak:

1. `README.md`
   - Belge haritası, okuma sırası, kanonik sahiplik ve sürümleme kuralları.

2. `00-request-source-boundary.md`
   - Güncel talep ile ek belgeleri kesin biçimde ayırır.
   - Her kaynak maddesini `adopted / modified / rejected / superseded` olarak sınıflandırır.
   - “Doküman-only”, “Figma iptal”, “kodlama yok”, “bütün yüzeyler glass” kararlarını sabitler.

3. `01-common-design-constitution.md`
   - Ortak felsefe: task-first, content-first, typography-first, data-dense, adaptive, accessible, Flat 2.0 affordance ve enterprise glass.
   - A–F arasında değişmesi yasak olan anatomi, davranış, renk, tipografi, semantik state ve erişilebilirlik kuralları.
   - Renkler: `#FFB900`, `#080616`, başlangıç Parliament Blue değeri `#1E3A8A`.
   - Roboto, minimum görünür metin `1rem`, minimum weight `400`.
   - Uzunluklarda REM-first: sabit değerler ve breakpoint’ler `rem`; akış için `%`, `fr`, `dvh` ve birimsiz değerler serbest; `px/mm/cm/pt` yasak.
   - Breakpoint sözlüğü: `20rem`, `26.875rem`, `48rem`, `64rem`, `75rem`, `90rem`.

4. `02-enterprise-glass-and-layer-rules.md`
   - Card, form, field, table, modal, drawer, popover ve genel component yüzeylerinin tamamını glass olarak tanımlar.
   - Gerçek blur yalnız composition root üzerinde uygulanır; nested yüzey, satır ve hücreler blur’u miras alan tint/edge katmanları kullanır.
   - Modal açıldığında ortak ve değişmez kural:
     - cool-grey scrim,
     - arka katmana Gaussian blur `0.125rem`,
     - tek scrim ve tek blur kökü,
     - arka plan `inert`,
     - focus trap, Escape, scroll lock ve kapanınca focus restoration.
   - `prefers-reduced-transparency`, forced-colors ve desteklenmeyen tarayıcılar için yüksek opaklıklı erişilebilir fallback tanımlanır. Bu gereklilik [MDN backdrop-filter](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Properties/backdrop-filter) ve [reduced-transparency](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/%40media/prefers-reduced-transparency) davranışlarına dayanır.
   - Bütün içerik yüzeylerinde glass kullanımı, Apple’ın Liquid Glass’ı esasen functional layer ile sınırlandıran rehberinden bilinçli bir sapma olarak kaydedilir; Apple görünümünün kopyası olduğu iddia edilmez. [Apple Materials](https://developer.apple.com/design/human-interface-guidelines/materials)

5. `03-ui-variant-catalog-a-f.md`
   - Altı profil aynı renkleri, DOM anatomisini, component state’lerini, yoğunluk değerini ve örnek veriyi kullanır.
   - Yalnız yüzey opaklığı, blur, kenar netliği, specular highlight, tonal ayrım ve elevation hissi değişir.

| Profil | Ad | Glass dolgu | Surface blur | İnce fark |
|---|---|---:|---:|---|
| A | Crystal Precision | `0.94` | `0.25rem` | En net sınırlar, en az optik efekt |
| B | Frost Analytical | `0.92` | `0.375rem` | Cool-grey tonu ve güçlü veri ayraçları |
| C | Quiet Editorial | `0.90` | `0.5rem` | Daha yumuşak kenar ve düşük görsel gürültü |
| D | Layered Modular | `0.88` | `0.625rem` | İç içe katman seviyeleri daha belirgin |
| E | Luminous Liquid | `0.84` | `0.75rem` | En güçlü ışık kırılması ve iç highlight |
| F | Executive Balance | `0.90` | `0.5rem` | Net kenar, kontrollü vurgu ve dengeli derinlik |

   - Modal scrim blur’u varyantlardan etkilenmez; her zaman `0.125rem` kalır.
   - Bu profiller istatistiksel A/B testi veya kullanıcıya rastgele dağıtılan deneyler değildir; tasarım kuralı karşılaştırma kataloğudur.

6. `04-component-guidelines.md`
   - **Card:** header/body/actions anatomisi, interactive-card sınırı, nested-card yasağı ve glass composition kuralları.
   - **Form:** görünür label, helper/error, required/readonly/disabled/loading, error summary, validation zamanı, tek ve çift kolon geçişleri.
   - **Table/DataGrid:** table-grid karar ağacı, sort/filter/select/bulk action, pagination, sticky alanlar, sanallaştırma, boş/loading/error durumları.
   - `20rem` mobil görünümde tablo, öncelikli alanları gösteren kayıt listesine dönüşür; yatay tablo yalnız ikincil kullanıcı seçeneğidir.
   - Table blur’u container seviyesinde bir kez uygulanır; row/cell başına filtre kesin olarak yasaktır.
   - **Genel component’ler:** button, icon button, badge, tabs, menu, tooltip, toast, skeleton, empty state, modal, drawer ve popover.
   - Bütün component’lerde A–F görünümü değişebilir; görev akışı, semantik ve erişilebilirlik değişemez.

7. `05-storybook-vibecoding-promptbook.md`
   - Türkçe açıklama + İngilizce kopyala-yapıştır agent prompt’ları.
   - Prompt sırası: inventory → foundations → glass surfaces → cards → forms → tables → overlays → A–F comparison stories → a11y/i18n/performance → final audit.
   - Her prompt allowlist, non-goals, beklenen story’ler, acceptance criteria ve rollback sınırı taşır.
   - Storybook MCP varsa önce `list-all-documentation`, `get-documentation`, `get-storybook-story-instructions`, `preview-stories` ve `run-story-tests` kullanılması istenir; component prop’ları tahmin edilmez.
   - Resmî Storybook MCP halen preview ve React odaklı olduğundan ilerideki kodlama için varsayılan hedef React + TypeScript + Vite olarak belgelenir; sürüm sabitlenmeden “stable” iddiası kurulmaz. [Storybook AI/MCP](https://storybook.js.org/docs/ai), [MCP araçları](https://storybook.js.org/docs/ai/mcp/overview)
   - Figma’ya ilişkin tek bir uygulama prompt’u bulunmaz.

8. `06-storybook-story-and-quality-matrix.md`
   - A–F × light/dark × density × viewport × locale/state kapsam matrisi.
   - Aynı fixture ve içerikle yan yana `A–F Variant Gallery` karşılaştırması.
   - Modal overlay, uzun label, RTL, klavye, reduced-motion, reduced-transparency ve high-contrast senaryoları.
   - Storybook interaction ve a11y yaklaşımı resmî [interaction testing](https://storybook.js.org/docs/writing-tests/interaction-testing) ve [accessibility testing](https://storybook.js.org/docs/writing-tests/accessibility-testing) rehberlerine bağlanır.

9. `07-roadmap-governance-and-rollback.md`
   - Dokümanların uygulamaya aktarılma sırası, değişiklik yönetimi, varyant seçme yöntemi ve geriye dönüş kuralları.
   - Önce tek ortak component anatomisi; sonra altı token profili; en son karşılaştırmalı Storybook stories.
   - Varyant seçimi estetik beğeniyle değil görev tamamlama, tarama hızı, kontrast, performans ve hata oranıyla yapılır.

## 4. Riskler, arayüzler ve varsayımlar

- Kod veya public API değişikliği yoktur. Belgeler gelecekte kullanılacak normatif eksenleri tanımlar: `uiVariant=A…F`, `theme`, `density`, `transparencyPreference`.
- Bütün yüzeylerde glass kararı performans ve kontrast riski taşır. Tek blur-root, adversarial-background kontrast testi ve erişilebilir fallback zorunludur.
- WCAG 2.2 AA tabanı kullanılacak: normal metin en az `4.5:1`, UI sınırları en az `3:1`; önemli dokunma hedefi `2.75rem` olacaktır. [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- Kaynak metinlerdeki Figma, opak tablo yüzeyi veya “scrim blur kullanma” yönlendirmeleri güncel taleple çeliştiği için `superseded` olarak işaretlenecek.
- Belgeler Türkçe; vibecoding prompt’ları İngilizce olacaktır.
- Mevcut managed sözleşme Codex’in kalıcı Markdown dosyası yazmasını yasaklıyor. Bu plan Codex MASTER tarafından hazırlanabilir; fakat gerçek dosyaların “Codex-only” üretilmesi mevcut yetki kaydı değişmeden blokludur.

## 5. Doğrulama planı

- Doküman linkleri ve Markdown yapısı geçerli olmalı.
- Her A–F profilinde card, form, table ve overlay için açık fark bulunmalı.
- Ortak davranışın varyantlar arasında değişmediği çapraz matrisle kanıtlanmalı.
- Normatif ölçülerde yasaklı mutlak birimler bulunmamalı.
- Figma uygulama prompt’u veya Figma bağımlılığı kalmamalı.
- Glass kontrast kuralları açık, koyu, doygun ve yüksek frekanslı arka planlarda tanımlanmalı.
- Overlay kuralı her belgede aynı olmalı: cool-grey scrim + `0.125rem` blur.
- Gelecek Storybook kabulü: klavye tamlığı, RTL, uzun içerik, `20rem` reflow, 10.000 satır senaryosu, reduced-transparency ve modal focus yönetimi.

## 6. Rollback planı

- Yalnız yeni `README.md` ve `docs/` paketi hedeflenir; mevcut klasörlere dokunulmaz.
- Git kullanılmadığı için oluşturulan dosyaların manifesti tutulur.
- Geri dönüş gerektiğinde paket silinmek yerine tarih damgalı bir karantina klasörüne taşınır.
- Kaynak ekler değiştirilmez veya proje içine kopyalanmaz.

## 7. MASTER Nihai Kararı

Plan karar-tamdır: kapsam yalnız Storybook-vibecoding Markdown yönergeleri, Figma iptal, A–F ince taneli kural profilleri, bütün yüzeylerde kontrollü enterprise glass ve REM-first fluid ölçü sistemidir.

Plan tamamlandı; henüz dosya, tasarım veya kod üretilmedi. Codex-only kalıcı doküman yazımı mevcut managed yetki sözleşmesi nedeniyle blokludur.
