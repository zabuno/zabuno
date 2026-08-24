# 28 — Source Register

**PLANNING ONLY.** Her satır iki bağımsız ekseni ayrı ayrı taşır: **erişim
durumu** (kaynağa fiilen ulaşılıp içeriği okundu mu — "erişim doğrulandı" /
"pending") ve **ürün olgunluk/benimseme sınıfı** (**kanıtlanmış / koşullu /
deneysel** — bu teknoloji kurulu/production-onaylı mı, yoksa yalnız kaynağı
mı doğrulandı). Bir satırın erişimi doğrulanmış olması, o teknolojinin
otomatik olarak **kanıtlanmış**a yükseldiği anlamına **gelmez** — ikisi ayrı
kararlardır. Erişim tarihi, kaynağa fiilen bu veya önceki bir oturumda canlı
birincil kaynak incelemesiyle ulaşılmışsa gerçek tarihtir; ulaşılamamışsa
"pending — bir sonraki review'da tekrar denenmeli" olarak işaretlenir ve
karar, kaynağın kendisi doğrulanmadan **koşullu** sınıfının altında kalır.

## Fiilen bu oturumda erişilen kaynaklar

| Kaynak | URL | Erişim tarihi | Karar | Sınıf | Not |
|---|---|---|---|---|---|
| imageoptimization repo | `https://github.com/karacaismail/imageoptimization` | 2026-08-19 | Yalnız kavram referansı; kod portlanmadı | koşullu | Bkz. `research/upstream/imageoptimization/UPSTREAM.md` — lisans dosyası yok |
| imageoptimization commit çözümü | `git ls-remote` çıktısı | 2026-08-19 | Commit `04e55de8f8f90f5ef1a15e0f842ad9c1b68477ab` sabitlendi | kanıtlanmış (doğrudan git çıktısı) | — |

## 2026-08-19 — bu oturumda canlı doğrulanan birincil kaynaklar

Bu paketin görev talimatı 21 URL'lik bir "birincil kaynak" kümesi adlandırdı.
Bu bölümdeki satırlar, bu düzeltme turunda **bu oturumun kendisi tarafından**,
gerçek bir canlı birincil kaynak incelemesiyle doğrulanmıştır. 21 URL'nin
18'i doğrudan erişilip içeriği okunarak doğrulandı ("erişim doğrulandı").
Kalan 3'ü resmi sunucu tarafından doğrudan erişim reddi (HTTP 403) aldı;
bunlar için tek bir ek, sınırlı arama turu yapıldı (tekrar denenmedi) —
üçü de aynı resmi başlık/URL ile indekslenmiş, içerik olarak eşleşen sonuç
döndürdü. Bu 3 satır **"official indexed content verified"** olarak
işaretlenir — direkt sayfa fetch'i değil, resmi indekslenmiş içerik üzerinden
doğrulama; ayrı bir erişim sınıfı olarak "erişim doğrulandı"dan bilinçli
olarak ayrılır. "Fiilen bu oturumda erişilen kaynaklar" bölümündeki
imageoptimization satırı bundan ayrı ve değişmeden kalır.

| Alan | URL | Erişim tarihi | Karar (bu külliyatta nasıl kullanıldığı) | Erişim sınıfı | Ürün olgunluk/benimseme sınıfı |
|---|---|---|---|---|---|
| Laravel AI SDK | `https://laravel.com/docs/13.x/ai-sdk` | 2026-08-19 | `docs/14` §5 adapter kararı | erişim doğrulandı | deneysel — sayfa içeriği kararlılık/sürüm-olgunluğu iddiası taşımıyor (ör. açık "pre-1.0" ibaresi yok); hızlı değişen bir ekosistem paketi olduğu için kurulum/production kararı ayrı bir spike ister |
| Laravel MCP | `https://laravel.com/docs/13.x/mcp` | 2026-08-19 | `docs/14` §5 | erişim doğrulandı | koşullu — resmi Laravel 13.x dokümantasyonu, ancak kurulu/production-onaylı değil |
| Laravel Boost | `https://laravel.com/docs/13.x/boost` | 2026-08-19 | `docs/14` §5 always-on/on-demand ayrımı | erişim doğrulandı | koşullu |
| Laravel AI (AI Assisted Development) | `https://laravel.com/docs/13.x/ai` | 2026-08-19 | `docs/14` §5 | erişim doğrulandı | koşullu |
| Gemini API key yönetimi | `https://ai.google.dev/gemini-api/docs/api-key` | 2026-08-19 | `modules/ai-provider-account-vault.md` §Connect akışı | erişim doğrulandı | kanıtlanmış (resmi Google kaynağı, key rotasyon/güvenlik pratiği doğrudan okundu) |
| Gemini rate limits | `https://ai.google.dev/gemini-api/docs/rate-limits` | 2026-08-19 | Rate limiti **proje seviyesinde** uygulanır kararı — **key seviyeli değildir**; sayfa bunu birebir doğruluyor ("Rate limits are applied per project, not per API key") | erişim doğrulandı | kanıtlanmış |
| OpenAI Projects API (organization/projects) | `https://developers.openai.com/api/reference/typescript/resources/admin/subresources/organization/subresources/projects` | 2026-08-19 | `modules/ai-provider-account-vault.md` — org/project seviyeli hesap kavramı | erişim doğrulandı | koşullu |
| OpenAI Projects yönetim rehberi | `https://help.openai.com/en/articles/9186755-managing-your-work-in-the-api-platform-with-projects` | 2026-08-19 | Aynı — org/project hesap modeli; indekslenmiş içerik proje bazlı erişim/limit/harcama yönetimini ve yalnız organizasyon sahiplerinin proje oluşturabildiğini doğruluyor | official indexed content verified (doğrudan sayfa fetch'i değil — sunucu HTTP 403 ile reddetti, aynı resmi başlık/URL arama sonucunda eşleşen içerikle doğrulandı) | koşullu (yükseltilmedi) |
| OpenAI Services Agreement | `https://openai.com/en-GB/policies/services-agreement/` (kanonik: `https://openai.com/policies/services-agreement/`) | 2026-08-19 | Tüketici abonelik yasağı maddesinin kaynağı; indekslenmiş içerik anlaşmanın API/işletme kullanımına uygulandığını, tüketici/bireysel ChatGPT kullanımını **kapsamadığını** doğruluyor | official indexed content verified (doğrudan sayfa fetch'i değil — sunucu HTTP 403 ile reddetti, aynı resmi başlık/URL arama sonucunda eşleşen içerikle doğrulandı) | koşullu (yükseltilmedi) |
| OpenAI rate limit rehberi | `https://help.openai.com/en/articles/6891753` | 2026-08-19 | `modules/ai-provider-account-vault.md` §Routing; indekslenmiş içerik başlığı "What are the best practices for managing my rate limits in the API?" ile eşleşiyor ve exponential backoff/usage-tier pratiklerini doğruluyor | official indexed content verified (doğrudan sayfa fetch'i değil — sunucu HTTP 403 ile reddetti, aynı resmi başlık/URL arama sonucunda eşleşen içerikle doğrulandı) | koşullu (yükseltilmedi) |
| Anthropic API rate limit yaklaşımı | `https://support.anthropic.com/en/articles/8243635-our-approach-to-api-rate-limits` (resmi 301 ile `support.claude.com` altına yönlendirir) | 2026-08-19 | `modules/ai-provider-account-vault.md` §Routing — sayfa rate limitlerinin **organizasyon seviyesinde** belirlendiğini birebir doğruluyor | erişim doğrulandı (nihai URL: `https://support.claude.com/en/articles/8243635-our-approach-to-api-rate-limits`) | kanıtlanmış |
| Anthropic Pro/Max ile Claude Code | `https://support.anthropic.com/en/articles/11145838-using-claude-code-with-your-pro-or-max-plan` (resmi 301 ile `support.claude.com` altına yönlendirir) | 2026-08-19 | Tüketici Pro/Max girişinin **production API credential'ı olmadığı** kararının kaynağı — sayfa bunu açıkça uyarıyor (API key varsa subscription yerine API ücretlendirmesi devreye girer) | erişim doğrulandı (nihai URL: `https://support.claude.com/en/articles/11145838-using-claude-code-with-your-pro-or-max-plan`) | kanıtlanmış |
| Anthropic API kredi/ödeme | `https://support.anthropic.com/en/articles/8977456-how-do-i-pay-for-my-api-usage` (resmi 301 ile `support.claude.com` altına yönlendirir) | 2026-08-19 | `modules/ai-provider-account-vault.md` §credit ledger | erişim doğrulandı (nihai URL: `https://support.claude.com/en/articles/8977456-how-do-i-pay-for-my-api-usage`) | kanıtlanmış |
| GitHub repository görünürlüğü | `https://docs.github.com/en/repositories/creating-and-managing-repositories/about-repositories` | 2026-08-19 | `docs/31` §1 | erişim doğrulandı | kanıtlanmış |
| GitHub push protection | `https://docs.github.com/en/code-security/concepts/secret-security/push-protection` | 2026-08-19 | `docs/31` §2, `skills/public-repository-gate.md` | erişim doğrulandı | kanıtlanmış |
| GitHub depoya lisans ekleme | `https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/adding-a-license-to-a-repository` | 2026-08-19 | `docs/31` §4 — LICENSE eklenmediği kararının kaynağı; sayfa "public görünürlük ≠ kullanım hakkı" ayrımını **açıkça yazmıyor** (yalnız lisans eklemenin nasıl yapılacağını anlatıyor) — bu yüzden `docs/31` §4'teki yorum bu külliyatın kendi çıkarımıdır, sayfanın birebir ifadesi değildir | erişim doğrulandı | kanıtlanmış (sayfa gerçek, ama §4 yorumu paketin kendi sentezi olarak işaretlenir) |
| Iyzico webhook | `https://docs.iyzico.com/en/advanced/webhook` | 2026-08-19 | `docs/09` §5, `modules/iyzico-payment.md` — sayfa `X-IYZ-SIGNATURE-V3`, HMAC-SHA256 doğrulaması ve 15 dakikada bir / 3 denemelik retry davranışını birebir doğruluyor | erişim doğrulandı (doğrudan sayfa içeriği okundu) | kanıtlanmış |
| Iyzico response signature validation | `https://docs.iyzico.com/en/advanced/response-signature-validation` | 2026-08-19 | `docs/09` §5, `modules/iyzico-payment.md` §Security — HMAC-SHA256 ve endpoint'e göre parametre sıralaması sayfada birebir doğrulandı | erişim doğrulandı (doğrudan sayfa içeriği okundu) | kanıtlanmış |
| iyzipay-php | `https://github.com/iyzico/iyzipay-php` | 2026-08-19 | `docs/09` §5 resmi adaptör kararı | erişim doğrulandı | kanıtlanmış (MIT lisanslı resmi iyzico deposu) |
| brick/money | `https://github.com/brick/money` | 2026-08-19 | `docs/09` §1 — güncel release `0.14.1` (2026-07-30), ana composer PHP `^8.2`; exact arithmetic/immutability/explicit-rounding kabiliyeti doğrulandı | erişim doğrulandı | koşullu (MIT lisanslı, yaygın/stabil PHP parası kütüphanesi — ancak sürüm hâlâ `0.x`; production adoption sürüm pinleme + property-based test + adapter/rollback şartına bağlı, `docs/16` DEP-01) |
| endroid/qr-code | `https://github.com/endroid/qr-code` | 2026-08-19 | `docs/08` §1 — tag `6.1.3` (composer PHP `^8.4`), tag `6.0.0` (PHP `^8.2`), tag `5.1.0` (PHP `^8.1`) | erişim doğrulandı | koşullu (MIT lisanslı, yaygın kullanılan PHP QR kütüphanesi — ancak hangi major/minor'ün seçileceği hedef PHP/shared-host baseline'ı kilitlenmeden karara bağlanamaz, `docs/16` DEP-01) |
| mPDF | `https://github.com/mpdf/mpdf` (tag/composer) | 2026-08-19 | `docs/08` §1 — güncel tag `v8.3.1`, tag composer PHP `5.6`/`7.x` ve `8.0`–`8.5` aralığını destekliyor; named/custom page size + orientation kabiliyeti resmi dokümantasyonla doğrulandı | erişim doğrulandı | koşullu (production kullanımı sürüm pinleme + yalnız kontrollü template (tenant/kullanıcı keyfi HTML/CSS verilmez) + PDF snapshot/baskı/performans/güvenlik testi şartına bağlı, `docs/16` DEP-01) |

**Dürüst notlar:**
- **Erişim doğrulandı ≠ kurulu/production-onaylı.** 18 satırın erişim
  sınıfı "erişim doğrulandı" olsa da, bu yalnız kaynağın gerçek ve
  içeriğinin okunduğu anlamına gelir — "ürün olgunluk/benimseme sınıfı"
  sütunu ayrı bir eksendir ve teknoloji hâlâ **koşullu/deneysel** kalabilir
  (`docs/03` ADR disiplini gereği, kurulum/production kararı ayrı kanıt
  ister).
- Gemini rate limit'leri **proje seviyelidir, key seviyeli değildir** —
  bu artık doğrudan sayfa içeriğiyle doğrulanmıştır; birden çok key üretip
  limiti aşmaya çalışmak (quota evasion) bu külliyatın hiçbir tasarımında
  **önerilmez/uygulanmaz**.
- Tüketici Anthropic aboneliği (Claude Pro/Max) **production API
  credential'ı değildir** — bu artık doğrudan sayfa içeriğiyle
  doğrulanmıştır (`modules/ai-provider-account-vault.md` bu ayrımı zaten
  uyguluyordu).
- 3 satır (OpenAI Projects yönetim rehberi, OpenAI Services Agreement,
  OpenAI rate limit rehberi) doğrudan sayfa fetch'iyle değil, **official
  indexed content verified** yöntemiyle doğrulandı — bu, "erişim
  doğrulandı"dan daha zayıf bir kanıt seviyesidir (üçüncü taraf arama
  indeksi üzerinden eşleşen içerik, doğrudan sunucu yanıtı değil). Bu
  yüzden kendi hedefledikleri kararları otomatik olarak **kanıtlanmış**a
  yükseltmez; ilgili kararlar (`modules/ai-provider-account-vault.md`
  §Tüketici abonelik yasağı, §Routing) **koşullu** sınıfında kalır. Doğrudan
  sayfa erişimi bir sonraki turda tekrar denenebilir ama artık **pending
  değildir** (`docs/16` SRC-01 güncellendi).

## OpenAI Admin API (developers.openai.com) — erişim doğrulandı

Bu ikinci düzeltme paketi kapsamında aşağıdaki dört `developers.openai.com`
referans sayfası **bu oturumda doğrudan canlı fetch edilerek** (redirect takip
eden HTTP isteğiyle) yeniden denendi. Önceki turdaki 404/domain-engelleme
sonucu **bu oturumda tekrar üretilemedi** — dördü de HTTP 200 döndürdü ve
sayfa içeriği doğrudan okunarak aşağıdaki anahtar terimlerin geçtiği
doğrulandı. Bu nedenle dört satır da artık **"erişim doğrulandı"**dır — ancak
bu, yalnız **kaynağın kendisinin** doğrulandığı anlamına gelir; bu API'lerin
platformda bir **adaptör** olarak kurulup **production'da kullanılması**
ayrı bir karardır ve **koşullu** sınıfında kalır (register'ın §1 iki-eksen
ilkesi: erişim ≠ üretim onayı).

| Kaynak | URL | Erişim tarihi | Doğrulanan içerik (sayfada birebir geçen terimler) | Sınıf |
|---|---|---|---|---|
| OpenAI Admin API — organization/projects | `https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/projects` | 2026-08-19 | Project service account/API key, project rate limit, spend endpoint'leri (`rate limit`, `spend`, `service_account`, `project` terimleri sayfada doğrudan geçiyor) | erişim doğrulandı — koşullu (adaptör/production kararı ayrı) |
| OpenAI Admin API — organization/usage | `https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/usage` | 2026-08-19 | Organization usage/cost endpoint'leri; `project_id` ve `api_key_id` ile `group_by` desteği (tüm terimler sayfada doğrudan geçiyor) | erişim doğrulandı — koşullu (adaptör/production kararı ayrı) |
| OpenAI Admin API — organization/admin_api_keys | `https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/admin_api_keys` | 2026-08-19 | Admin key organizasyon düzeyinde; `create`/`secret`/`redacted` terimleri sayfada doğrudan geçiyor (secret yalnız create yanıtında, sonrasında redacted) | erişim doğrulandı — koşullu (adaptör/production kararı ayrı) |
| OpenAI API reference overview — debugging requests | `https://developers.openai.com/api/reference/overview#debugging-requests` | 2026-08-19 | `x-request-id` ve rate-limit response header'ları (`debugging`, `x-request-id`, `rate limit` terimleri sayfada doğrudan geçiyor) | erişim doğrulandı — koşullu (adaptör/production kararı ayrı) |

Not: Bu dört satırın "erişim doğrulandı"ya yükseltilmesi, `docs/16` ANL-03,
AIV-09 gibi **gerçek** açık maddeleri kapatmaz — bunlar kaynağın var olup
olmadığından bağımsız, tasarım/entegrasyon kararlarıdır ve açık kalır.

Bu dört satır, `modules/ai-provider-account-vault.md`'nin usage/cost
reconciliation, request-id/rate-limit telemetry ve least-privilege
service-account/project bağlantısı yaklaşımını **destekleyici referans**
olarak kalır — ancak bu paket kapsamında hiçbir mimari karar bu dört
satırın doğrulanmamış olmasına dayandırılmaz (mevcut karar zaten `docs/28`
satır 44 "OpenAI Projects API" — TypeScript referansı, "erişim doğrulandı",
koşullu — üzerinden temellenmiştir). Bir sonraki review turunda bu dört
URL'nin canlı erişimi tekrar denenmelidir (`docs/16` SRC-01 kapsamına dahil).

## GA4 / Yandex Metrica / PWA — inbound ve update lifecycle kaynakları

Aşağıdaki sekiz resmi kaynak sayfası bu oturumda doğrudan canlı fetch edilerek
doğrulandı (redirect takip eden HTTP isteği + sayfa içeriğinde anahtar terim
karşılaştırması). Bunlar `docs/12` §5a (GA4/Yandex inbound reporting) ve
`docs/15` §5a (PWA update/service-worker) kararlarının kaynağıdır — **erişim
doğrulandı**, ancak Zabuno'ya özgü adaptör/production kararı (hangi OAuth
scope, hangi custody modeli, hangi cache stratejisi seçilir) ayrı ve
**koşullu** kalır.

| Kaynak | URL | Erişim tarihi | Doğrulanan içerik (sayfada birebir geçen terimler) | Sınıf |
|---|---|---|---|---|
| GA4 Data API — basics | `https://developers.google.com/analytics/devguides/reporting/data/v1/basics` | 2026-08-19 | `runReport`/`RunReport` read-only raporlama endpoint'i sayfada doğrudan geçiyor | erişim doğrulandı — koşullu (adaptör/production kararı ayrı) |
| GA4 Data API — quotas | `https://developers.google.com/analytics/devguides/reporting/data/v1/quotas` | 2026-08-19 | Property bazlı quota/token terimleri sayfada doğrudan geçiyor (property-level quota politikası) | erişim doğrulandı — koşullu |
| GA4 Data API — quickstart | `https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart` | 2026-08-19 | OAuth/service-account kurulum akışı resmi dokümantasyonda anlatılıyor | erişim doğrulandı — koşullu |
| Yandex Metrica Reports API — data | `https://yandex.com/dev/metrika/en/stat/openapi/data` | 2026-08-19 | Reports API (raporlama endpoint'i) sayfada tanımlı | erişim doğrulandı — koşullu |
| Yandex Metrica — authorization | `https://yandex.com/dev/metrika/en/intro/authorization` | 2026-08-19 | OAuth/token terimleri sayfada doğrudan geçiyor (token custody gereksinimi doğrulandı) | erişim doğrulandı — koşullu |
| Yandex Metrica — quotas | `https://yandex.com/dev/metrika/en/intro/quotas` | 2026-08-19 | quota/limit terimleri sayfada doğrudan geçiyor (açık rate/quota politikası) | erişim doğrulandı — koşullu |
| web.dev — PWA update | `https://web.dev/learn/pwa/update/` | 2026-08-19 | Versioned cache + update-prompt UX deseni resmi rehberde anlatılıyor | erişim doğrulandı — koşullu |
| web.dev — Service worker lifecycle | `https://web.dev/articles/service-worker-lifecycle` | 2026-08-19 | `install`/`activate`/`cache`/`update` lifecycle terimleri sayfada doğrudan geçiyor (cache cleanup/versioning kısıtları resmi kaynakla doğrulandı) | erişim doğrulandı — koşullu |

Bu sekiz satır, `docs/12` §5a'daki "yalnız gerekli OAuth/scope, quota/backoff,
freshness label" kararını ve `docs/15` §5a'daki "versioned cache + update
prompt, service worker scope, deterministic cache invalidation" kararını
**destekler**; ancak `docs/16` ANL-03'teki mapping/attribution/discrepancy
belirsizliğini **kapatmaz** — o, kaynağın var olup olmadığından bağımsız bir
tasarım/entegrasyon kararıdır.

## S1-WP01A foundation — bu oturumda canlı doğrulanan kaynaklar (2026-08-19)

Bu S1-WP01A implementation-in-progress paketi kapsamında, aşağıdaki altı
kaynak bu oturumda **canlı** olarak yeniden doğrulandı (frozen scope'un PHP
`^8.3`/Laravel `^13.0`, Flowbite React/shadcn/Radix, Vite ve OWASP ASVS 5.0.0
kararlarının kaynağı) — bu satırlar "Henüz bu oturumda fetch edilmemiş"
tablosundaki eski `koşullu`/pending karşılıklarının **güncellemesidir**.

| Kaynak | URL | Erişim tarihi | Doğrulanan içerik | Sınıf |
|---|---|---|---|---|
| Laravel 13.x release notes | `https://laravel.com/docs/13.x/releases` | 2026-08-19 | Sayfa doğrudan fetch edildi; "Laravel 13.x requires a minimum PHP version of 8.3", sürüm tablosu (Laravel 13 → PHP 8.3–8.5, release 17 Mart 2026) birebir doğrulandı | erişim doğrulandı — kanıtlanmış (resmi Laravel dokümantasyonu, composer.json `^8.3`/`^13.0` kilidiyle birebir tutarlı) |
| Flowbite React — Vite entegrasyon rehberi | `https://www.flowbite-react.com/docs/guides/vite` | 2026-08-19 | Doğrudan `flowbite-react.com` fetch'i bu oturumda HTTP 429 (rate limit) ile reddedildi; aynı rehberin kaynak içeriği `raw.githubusercontent.com/themesberg/flowbite-react` üzerinden ve resmi `flowbite-react-template-vite` deposunun (`vite.config.ts`, `src/index.css`, `package.json`) canlı içeriğiyle doğrulandı — `flowbite-react/plugin/vite` + `flowbite-react/plugin/tailwindcss` + `@source` desenini teyit eder | erişim doğrulandı (dolaylı — GitHub raw kaynağı üzerinden, doğrudan sayfa fetch'i değil) — koşullu (kurulum doğru, ama flowbite-react hâlâ pre-release olarak kendini işaretliyor) |
| shadcn/ui — Vite kurulum rehberi | `https://ui.shadcn.com/docs/installation/vite` | 2026-08-19 | Bileşenlerin projeye kaynak olarak kopyalandığı ("adds the Card component to your project"), npm bağımlılığı olarak kurulmadığı doğrulandı | erişim doğrulandı — kanıtlanmış (resmi shadcn/ui dokümantasyonu, source-owned kararının doğrudan kaynağı) |
| Radix Primitives — Introduction | `https://www.radix-ui.com/primitives/docs/overview/introduction` | 2026-08-19 | "Components ship without styles" (unstyled) ve WAI-ARIA uyumlu erişilebilirlik iddiası birebir doğrulandı | erişim doğrulandı — kanıtlanmış (resmi Radix dokümantasyonu) |
| Vite — Getting Started (Node sürüm desteği) | `https://vite.dev/guide/` | 2026-08-19 | "Vite requires Node.js version 20.19+, 22.12+" birebir doğrulandı; bu depodaki yerel Node (v24.6.0) ve CI Node (`24`) bu aralığı karşılıyor | erişim doğrulandı — kanıtlanmış (resmi Vite dokümantasyonu) |
| OWASP ASVS 5.0.0 release | `https://github.com/OWASP/ASVS/releases/tag/v5.0.0_release` (GitHub API: `.../releases/tags/v5.0.0_release`) | 2026-08-19 | Release tag `v5.0.0_release`, GitHub API `published_at: 2025-05-30T09:35:31Z` birebir doğrulandı — `security/OWASP-ASVS-BASELINE.md` bu tarihe pinlidir | erişim doğrulandı — kanıtlanmış (doğrudan GitHub release/API yanıtı) |

## S1-WP02A Identity & Sessions delivery contract — bu oturumda canlı doğrulanan kaynaklar (2026-08-19)

Bu docs-only S1-WP02A kapsam sözleşmesi paketi (`docs/33-S1-WP02A-IDENTITY-SESSIONS-DELIVERY-CONTRACT.md`)
kapsamında, aşağıdaki beş kaynak bu oturumda **canlı** olarak fetch edilip
içeriği okunarak doğrulandı — bu, önceki turdaki "Laravel Fortify" `koşullu`/
pending satırının (aşağıdaki §"Henüz bu oturumda fetch edilmemiş" tablosundan
kaldırıldı) **güncellemesidir**; Sanctum ve iki composer contract satırı
külliyatta ilk kez eklenir.

| Kaynak | URL | Erişim tarihi | Doğrulanan içerik | Sınıf |
|---|---|---|---|---|
| Laravel 13.x Fortify | `https://laravel.com/docs/13.x/fortify` | 2026-08-19 | "Frontend agnostic authentication backend"; `EnsureLoginIsNotThrottled` login throttling (username+IP), `fortify.limiters.login` özelleştirmesi; kayıt/e-posta doğrulama/şifre sıfırlama route'larını Fortify sağlar, kullanımı **zorunlu değildir** | erişim doğrulandı — koşullu (resmi Laravel 13.x dokümantasyonu; kurulum/production kararı ayrı spike ister, `docs/03` ADR notu) |
| Laravel 13.x Sanctum | `https://laravel.com/docs/13.x/sanctum` | 2026-08-19 | SPA authentication **token kullanmaz**, Laravel'in cookie-based session servislerini kullanır (CSRF koruması + session auth + XSS credential-leak koruması); `/sanctum/csrf-cookie` → `XSRF-TOKEN` → `X-XSRF-TOKEN` akışı; `statefulApi()` middleware; first-party SPA için API token **kullanılmaması gerektiği** birebir yazılı | erişim doğrulandı — koşullu (resmi Laravel 13.x dokümantasyonu; `docs/05` §3'teki "token değil, cookie+CSRF" kararının doğrudan kaynağı) |
| Laravel 13.x Email Verification | `https://laravel.com/docs/13.x/verification` | 2026-08-19 | Doğrulama route'u `auth`+`signed` middleware taşır (imzalı, süreli link); `EmailVerificationRequest::fulfill()` → `markEmailAsVerified()` + `Verified` event; resend endpoint `throttle:6,1` ile sınırlı | erişim doğrulandı — koşullu (imzalı/süreli link mekanizması kanıtlanmış; aynı linkin tekrar tıklanmasının tam idempotent davranışı sayfada birebir yazılı değil — `docs/33` §12 S1WP02A-VERIFY-03 blind RED test adayıyla kilitlenecek, bkz. `docs/16` açık madde notu) |
| laravel/fortify 1.x composer.json | `https://github.com/laravel/fortify/blob/1.x/composer.json` | 2026-08-19 | `"php": "^8.2"`, `"illuminate/console": "^11.0\|^12.0\|^13.0"`, `"illuminate/support": "^11.0\|^12.0\|^13.0"` — Laravel 13 (Illuminate 13) kontratını doğrudan destekler | erişim doğrulandı — kanıtlanmış (doğrudan resmi composer.json içeriği; bu depodaki `composer.json` PHP `^8.3`/Laravel `^13.0` kilidiyle uyumlu — `^8.2` üst küme) |
| laravel/sanctum 4.x composer.json | `https://github.com/laravel/sanctum/blob/4.x/composer.json` | 2026-08-19 | `"php": "^8.2"`, `"illuminate/console"`, `"illuminate/contracts"`, `"illuminate/database"`, `"illuminate/support"` hepsi `"^11.0\|^12.0\|^13.0"` — Laravel 13 kontratını doğrudan destekler | erişim doğrulandı — kanıtlanmış (doğrudan resmi composer.json içeriği) |

Not: Bu beş satırın "üretim kararı"nı otomatik olarak **kanıtlanmış**a
yükseltmediği (`docs/03` ADR-L01 notundaki aynı ayrım: erişim doğrulandı ≠
kurulu/production-onaylı) — Fortify/Sanctum'un gerçek `composer require`'ı ve
compatibility spike'ı bu docs-only paketin kapsamı **dışındadır** (görev
talimatı: "Dependency mutation bu turda YASAK"); iki composer contract satırı
istisnadır çünkü onlar doğrudan sürüm kontratının kendisini kanıtlar, kurulum
kararını değil.

## Henüz bu oturumda fetch edilmemiş, karar için referans verilen resmi kaynaklar

Aşağıdaki kaynaklar görev talimatında adı geçen resmi dokümantasyon
noktalarıdır. Bu oturum bunları **canlı olarak fetch etmedi** (web erişimi bu
adımda kullanılmadı); kararlar bu paketin yazarının genel bilgisine ve görev
talimatındaki yönlendirmeye dayanır ve **koşullu** sınıfında tutulur — bir
sonraki review geçişinde her satırın URL'si canlı doğrulanmalı, erişim tarihi
o zaman güncellenmelidir. Bu, madde §M self-check gereğidir: "version exactness
doğrulanmıyorsa sabitleme".

| Alan | Kaynak | Bu külliyattaki kullanım | Sınıf |
|---|---|---|---|
| Laravel — providers/packages | laravel.com/docs (providers, package discovery) | ADR-L01, ADR-L04 (`docs/03`) | koşullu |
| Laravel — Vite entegrasyonu | laravel.com/docs (vite) | ADR-L06 (`docs/03`) | koşullu |
| Laravel — deployment | laravel.com/docs (deployment) | ADR-L08 (`docs/03`) | koşullu |
| Laravel — filesystem | laravel.com/docs (filesystem) | Flysystem kararı (`docs/07`) | koşullu |
| Laravel — images/queues/scheduling/cache/Redis | laravel.com/docs | `docs/07`, `docs/15` | koşullu |
| Laravel Mail | laravel.com/docs/mail | `docs/11` §1 | koşullu |
| Laravel Localization | laravel.com/docs/localization | `docs/13` | koşullu |
| Laravel Rate Limiting | laravel.com/docs/rate-limiting | `docs/05` §3, `docs/15` §1 | koşullu |
| Laravel Pennant | laravel.com/docs/pennant | Feature flag adayı (`docs/04` §5, henüz doğrudan referans verilmedi) | koşullu |
| eloquent-ifrs | github.com (ekitikela/eloquent-ifrs) | `docs/09` §2 — R&D candidate | deneysel |
| ISO 216 | resmi ISO tanımı (genel bilgi) | `docs/08` §3 | kanıtlanmış (yaygın uluslararası standart) |
| Spatie Media Library (responsive/conversions) | spatie.be/docs/laravel-medialibrary | `docs/07` §1 | koşullu |
| Uppy (React/Image Editor) | uppy.io | `docs/07` §1 | koşullu |
| Intervention Image | image.intervention.io | `docs/07` §1 | koşullu |
| Spatie Image Optimizer | github.com/spatie/laravel-image-optimizer | `docs/07` §1 | koşullu |
| Apple App Store review guidelines | developer.apple.com | `docs/15` §5, `docs/16` APP-01 | koşullu |
| Google Play policy | play.google.com/console/about/policies | `docs/15` §5 | koşullu |
| Capacitor | capacitorjs.com | `docs/15` §5 | koşullu |
| Google Search — AI features / spam / gen-AI / international / LocalBusiness / Product | developers.google.com/search | `docs/12` §8 | koşullu |
| Bing IndexNow | bing.com/indexnow | `docs/12` §7 | koşullu |
| GTM dataLayer | developers.google.com/tag-platform | `docs/12` §1 | koşullu |
| GA4 ecommerce/events/consent | developers.google.com/analytics | `docs/12` §5 | koşullu |
| Yandex ecommerce | yandex.com/support/metrica | `docs/12` §5 | koşullu |
| Metabase secure embed / RLS | metabase.com/docs | `docs/12` §5 | koşullu |
| OpenFGA concepts (ABAC vs ReBAC) | openfga.dev | `docs/05` §2 | koşullu |
| Spatie Permission (wildcard) | spatie.be/docs/laravel-permission | `docs/02` §5, `docs/05` §2 | koşullu |
| Twilio Verify | twilio.com/docs/verify | `docs/11` §2 | koşullu |
| Netgsm | netgsm.com.tr | `docs/11` §2 | koşullu |
| Vonage Verify | developer.vonage.com/verify | `docs/11` §2 | koşullu |
| Verimor | verimor.com.tr | `docs/11` §2 | koşullu |
| Laravel/Symfony Mailer | laravel.com/docs/mail, symfony.com/doc/mailer | `docs/11` §1 | koşullu |
| php-gettext / Gettext | php.net (gettext ext), github.com/php-gettext | `docs/13` §1 | koşullu |
| Symfony Workflow | symfony.com/doc/workflow | `docs/10` §2 | koşullu |
| Cloudflare DDoS/WAF docs | developers.cloudflare.com | `docs/15` §2 | koşullu |
| WCAG 2.2 | w3.org/TR/WCAG22 | `docs/06` §8, `docs/15` §6 | kanıtlanmış (W3C resmi standart) |
| NIST SSDF | csrc.nist.gov/Projects/ssdf | `docs/15` §6 | kanıtlanmış (resmi NIST yayını) |
| OpenTelemetry | opentelemetry.io | `docs/15` §6 | koşullu |
| DORA metrikleri | dora.dev | `docs/24` | koşullu |
| OpenAI developers / Codex use cases | platform.openai.com/docs, openai.com/codex | `docs/14` §7 | koşullu |

Not: Laravel Boost/AI SDK/MCP/AI, Gemini API key/rate limits, OpenAI Projects
API/Services Agreement/rate limit, Anthropic rate limit/Pro-Max/kredi, GitHub
visibility/push-protection/lisans, Iyzico webhook/signature, iyzipay-php,
brick/money, endroid/qr-code, mPDF, Laravel 13.x release notes, Flowbite
React (Vite), shadcn/ui (Vite), Radix (intro), Vite (Node sürüm desteği),
OWASP ASVS, Laravel Fortify/Sanctum/Email Verification ve iki composer
contract (fortify 1.x, sanctum 4.x) satırları burada
**tekrar edilmez** — bu tam URL'ler artık "Fiilen bu oturumda erişilen
kaynaklar", "S1-WP01A foundation — bu oturumda canlı doğrulanan
kaynaklar" ve "S1-WP02A Identity & Sessions delivery contract — bu oturumda
canlı doğrulanan kaynaklar" bölümlerinde tek kanonik kayıt olarak tutulur
(yukarıda); tekrar önleme kuralı `AGENTS.md` §2.

## Kullanım kuralı

**Erişim durumu ve ürün olgunluk/benimseme sınıfı ayrı eksenlerdir.** Bir
satırın erişimi "doğrulandı" olsa bile — yani kaynağa fiilen ulaşılıp
içeriği okunmuş olsa bile — bu tek başına ilgili teknolojinin "kurulu/
production-onaylı" sayılmasını **gerektirmez**; kurulum/production kararı
`docs/03` ADR disiplini gereği ayrı bir spike/karar ister ve koşullu/
deneysel sınıfta kalabilir. Bu tablodaki **pending** satırlar ise henüz
kaynağa ulaşılmadığı için "kurulu/doğrulanmış" olarak hiç sunulamaz. Bir
sonraki çalışma turunda bu tablo, pending kalan satırların canlı birincil
kaynak incelemesiyle tekrar denenmesiyle güncellenmeli ve erişimi
doğrulanan satırların ürün olgunluk sınıfı, yeni kanıt (spike/kurulum
sonucu) çıktıkça yükseltilmelidir; bu iş `docs/16`'ya "kaynak register
canlı doğrulama turu" olarak açık madde bırakılmıştır.

## Kanonik sahiplik

Resmi kaynak listesi ve güven sınıflandırması burada kanoniktir.
