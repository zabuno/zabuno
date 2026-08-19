# 12 — Analytics, Content & SEO

**PLANNING ONLY.**

## 1. First-party event ledger — source of truth

Analytics'in **tek gerçek kaynağı** first-party event ledger'dır (kendi
veritabanımız); GTM/GA4/Yandex gibi üçüncü taraf araçlar **adaptördür**, kaynak
değildir. Versiyonlanmış bir `dataLayer` sözleşmesi tanımlanır (event şeması
zamanla değişebilir, versiyon alanıyla takip edilir).

- **Consent before tags**: hiçbir üçüncü taraf tag, kullanıcı rızası alınmadan
  tetiklenmez (`docs/04` CORE-16 Legal Records ile entegre).
- **No PII**: event ledger'a kişisel tanımlayıcı veri yazılmaz.
- **Bot filtering**: bilinen bot/crawler trafiği ayrı sınıflandırılır, "scan"
  sayaçlarına karışmaz.

## 2. QR Resolve vs. Confirmed Menu Open ayrımı

İki farklı event:

- **QR Resolve**: redirect endpoint'ine istek geldi (güvenlik tarayıcıları,
  sosyal medya link-preview botları, monitoring servisleri de buraya düşebilir).
- **Confirmed Menu Open**: public menu gerçekten açıldı ve frontend event
  gönderdi (gerçek insan etkileşimi sinyali daha güçlü).

Bu ayrım olmadan "scan sayısı" metriği sahte şişebilir.

## 3. Unique scan tanımı (owner kararı gerektirir)

Önerilen taslak: aynı QR + aynı anonim cihaz fingerprint'i + belirli zaman
penceresi (örn. 30 dakika) → tek unique scan. **Kesin pencere süresi,
fingerprint yöntemi ve consent/privacy etkisi owner kararı gerektirir** —
`docs/16` ANL-02.

## 4. Analytics gizliliği

Mümkün olduğunca: tam IP tutulmaz veya kısa süreli/anonimleştirilmiş tutulur,
kesin konum tutulmaz, üçüncü taraf tracking script'i kullanılmaz, raw event
retention süresi sınırlıdır. IP ve çevrim içi davranış verisi kişisel veri
riski taşıdığından bu tasarım KVKK kapsamında değerlendirilmelidir
(`docs/04` CORE-16).

## 5. GA4 / Yandex ecommerce sözleşmesi (outbound)

GA4 enhanced ecommerce event/item şeması ve Yandex ecommerce şeması resmi
dokümantasyona göre uygulanır (`docs/28`); Metabase **signed embed + tenant
row-level security** opsiyonel katman olarak eklenebilir (koşullu — ek altyapı
gerektirir, shared-host varsayılanı değildir, self-hosted kalır).

## 5a. GA4 / Yandex Metrica inbound reporting (read-only)

§5, platformdan **dışarı** giden (outbound) tag/event akışını kapsar. Buna ek
olarak, tenant tarafından **yetkilendirilen** (tenant-authorized), **salt-okunur**
bir inbound raporlama adaptörü planlanır — bir tenant kendi GA4/Yandex Metrica
hesabını platforma bağlayıp bu hesabın raporlama verisini platform dashboard'unda
görebilir:

- **GA4 Data API** ve **Yandex Metrica Reporting API** — yalnız gerekli OAuth
  scope/izin ile, tenant'ın kendi hesabı üzerinden (`modules/integration-hub.md`
  ile aynı secret-custody disiplini, CORE-06 encrypted-at-rest).
- **Quota/backoff**: her iki API'nin de rate limit/quota politikasına uyulur;
  429/quota aşımı sessizce yutulmaz, dashboard'da "veri şu an güncellenemiyor"
  şeklinde **açıkça** işaretlenir.
- **Latency/freshness label**: gösterilen provider verisinin "ne zaman
  çekildiği" (freshness) her zaman görünür — gerçek zamanlı değildir, bu iddia
  edilmez.
- **Tenant isolation**: bir tenant'ın bağladığı GA4/Yandex hesabı yalnız o
  tenant'a görünür, başka tenant'ın routing/raporlama adayları arasında asla
  görünmez (`modules/ai-provider-account-vault.md` §Tenant isolation ile aynı
  yapısal izolasyon ilkesi).
- **Yalnız aggregate metric** çekilir — ham PII (kullanıcı bazlı kimlik,
  tam IP vb.) bu adaptör üzerinden **asla** platforma alınmaz (§4 ile tutarlı).

**Provider verisi first-party ledger'ın yerine geçmez**: §1'deki first-party
event ledger **tek gerçek kaynak** olmaya devam eder. GA4/Yandex inbound verisi
yalnız **karşılaştırma/mutabakat (reconciliation)** amaçlıdır — bir
**mapping tablosu** (first-party event ↔ provider metric) ve bir
**discrepancy indicator** (iki kaynak arasındaki sapma görünür şekilde
işaretlenir, sessizce üzerinden geçilmez) sağlanır. Provider tarafında bir
outage/erişim sorunu yaşandığında first-party dashboard **bozulmaz** —
yalnız provider-kaynaklı panel "kullanılamıyor" durumuna düşer, ana metrikler
etkilenmez.

Açık madde: kapsam/quota/şema/attribution/veri-uyuşmazlığı belirsizlikleri
`docs/16` ANL-03'te kayıtlıdır.

## 6. Page Composition vs. Content/Frontpages ayrımı

- **Page Composition** (`docs/04`): header/footer/navigasyon/component slot
  *yapısını* yönetir — "hangi bileşen nerede".
- **Content/Frontpages**: son kullanıcı *içeriğini* ve **pricing projection**'ı
  yönetir — "o bileşenin içinde ne yazıyor".

Bu iki modül birbirinin yerine geçmez; Page Composition bir modülün UI
iskeletini, Content/Frontpages onun içeriğini besler.

## 7. Tek birleşik SEO capability map

Owner'ın kullandığı tüm SEO/arama-görünürlüğü etiketleri **tek bir motorun**
alias/facet/channel'larıdır — her biri **ayrı bir motor olarak** ayrı ayrı
implemente **edilmez**. Aşağıdaki liste, bu külliyatta karşılaşılan/kullanıcı
tarafından adlandırılan her etiketi **tam olarak bir kez** governed mapping'e
yerleştirir:

```
SEO, pSEO, ASEO, AI SEO, AEO, GEO, LLMO, AIO, AISO, SXO, ASO, PEO, LEO, KGO,
VSO, SMO, SERM, Technical SEO, On-Page SEO, Off-Page SEO, Local SEO,
International SEO, Multilingual SEO, Multiregional SEO, Enterprise SEO,
E-commerce SEO, Marketplace SEO, SaaS SEO, B2B SEO, B2C SEO, Programmatic SEO,
Semantic SEO, Entity SEO, Topical SEO, Content SEO, Editorial SEO,
Product SEO, Category SEO, Landing Page SEO, Image SEO, Video SEO,
YouTube SEO, News SEO, Podcast SEO, Voice Search SEO, Visual Search SEO,
Mobile SEO, JavaScript SEO, Headless SEO, Edge SEO, Faceted Navigation SEO,
Zero-Click SEO, Featured Snippet SEO, Google Discover SEO, Google Maps SEO,
App Store Optimization, Search Everywhere Optimization,
Agentic Search Optimization, Academic SEO, White-Hat SEO, Gray-Hat SEO,
Black-Hat SEO, Negative SEO, Parasite SEO, Barnacle SEO
```

Governed mapping (alias → facet/channel → risk/compliance sınıfı):

- **alias** (aynı çekirdek disiplinin farklı isimleri, ayrı motor değil): SEO,
  ASEO, AI SEO, AEO, GEO, LLMO, AIO, AISO, SXO, PEO, LEO, KGO, VSO, SMO, SERM,
  ASO, Search Everywhere Optimization, Agentic Search Optimization.
- **facet: technical** — Technical SEO, On-Page SEO, Off-Page SEO,
  JavaScript SEO, Headless SEO, Edge SEO, Faceted Navigation SEO, Mobile SEO
  (structured data, canonical, hreflang, robots/sitemap, Core Web Vitals,
  `docs/12` §1, §5).
- **facet: content/entity-semantic** — Content SEO, Editorial SEO,
  Product SEO, Category SEO, Landing Page SEO, Topical SEO, Semantic SEO,
  Entity SEO (pSEO kalite kapısı — thin-content/spam engellenir, §8).
- **facet: local/international** — Local SEO, International SEO,
  Multilingual SEO, Multiregional SEO, Google Maps SEO (hreflang +
  LocalBusiness schema).
- **facet: media** — Image SEO, Video SEO, YouTube SEO, News SEO,
  Podcast SEO, Voice Search SEO, Visual Search SEO.
- **facet: AI-surface** — AEO, GEO, LLMO, AIO, AISO'nun somut uygulama
  yüzeyi (bkz. §8 abartı-yasağı).
- **facet: discovery-ops** — IndexNow, sitemap, Google Discover SEO,
  Zero-Click SEO, Featured Snippet SEO.
- **channel: platform-özel** — App Store Optimization (uygulama mağazası
  keşfi, `docs/16` APP-01 ile ilişkili — bu bir web-SEO facet'i değil, ayrı
  bir keşif kanalıdır ama aynı governance şemsiyesi altında izlenir).
- **channel: pazar/segment bağlamı** — Enterprise SEO, E-commerce SEO,
  Marketplace SEO, SaaS SEO, B2B SEO, B2C SEO, Programmatic SEO (bunlar yeni
  bir teknik yetenek değil, yukarıdaki facet'lerin belirli bir pazar/segment
  bağlamına uygulanma biçimidir).
- **channel: akademik** — Academic SEO (citation/scholarly indexation
  bağlamı — nonstandart, `docs/16`'ya glossary-unknown olarak düşülmüştür).
- **risk/compliance: allowed posture** — White-Hat SEO (bu külliyatın
  varsayılan ve tek uygulanan duruşu).
- **risk/compliance: prohibited/defensive** — Gray-Hat SEO, Black-Hat SEO,
  Negative SEO (bunlar bir "özellik" değildir; yalnız tehdit modeli/karşı
  önlem kapsamında ele alınır, §8).
- **risk/compliance: high-risk governance** — Parasite SEO (üçüncü taraf
  otorite/alan adı üzerinden görünürlük — yalnız açık risk değerlendirmesi ve
  owner onayıyla, otomatik uygulanmaz).
- **risk/compliance: governed external dependency** — Barnacle SEO
  (üçüncü taraf platformlarda (harita/pazar yeri/inceleme sitesi) görünürlük —
  **governed bir keşif bağımlılığıdır, otomatik onay/endorsement değildir**;
  her üçüncü taraf yüzey ayrı ayrı değerlendirilir).

Nonstandart/belirsiz kısaltmaların (ör. ASEO, AISO, PEO, LEO, KGO, VSO, SERM,
Academic SEO) kesin tanımı henüz kanıtlanmış bir birincil kaynağa bağlanmadı;
bu etiketler `docs/16`'da evidence/glossary-unknown olarak işaretlenir ve
public iddialarda bu netleşmeden **kanıtlanmış** sınıfıyla sunulmaz (bkz. §8,
`docs/16` SEO-02).

## 8. AI arama netleştirmesi — abartı yasak

Google'ın resmi AI search guidance'ına göre **AI optimizasyonu mevcut SEO
temellerini değiştirmez** — bu külliyat şu iddiaları **bilinçli olarak
reddeder**:

- "`llms.txt` bir sıralama girdisidir" — **yanlış iddia**, kullanılmaz.
- Gray/black/negative/parasite SEO teknikleri bir "özellik" **değildir** —
  bunlar risk/threat/compliance sınıfında ele alınır (uygulanacak bir yetenek
  değil, karşı önlem alınacak bir tehdit modeli, §7).
- Barnacle SEO (üçüncü taraf platform görünürlüğü) **otomatik bir
  onay/endorsement değildir** — governed bir keşif bağımlılığı olarak
  değerlendirilir, körü körüne uygulanmaz (§7).
- pSEO (programmatic SEO), thin-content/spam kalite kapısından geçmeden
  ölçeklendirilmez.
- Nonstandart kısaltmaların (§7 son paragraf) tanımı kanıtlanmadan kesinmiş
  gibi public iddiaya taşınmaz — `docs/16` SEO-02.

## 9. Kanonik sahiplik

Analytics/consent modeli ve tek birleşik SEO capability map burada kanoniktir.
Modül-özel uygulama detayı `modules/analytics-consent-tagging.md` ve
`modules/seo-search-discovery.md`'de yaşar.
