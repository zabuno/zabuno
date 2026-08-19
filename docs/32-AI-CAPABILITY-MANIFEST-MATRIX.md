# 32 — AI Capability Manifest Matrix

**PLANNING ONLY.** Bu doküman, mevcut 61 modülün (CORE-01..16 + 16 required
product + OPT-01..29) her birinin AI Capability Plane ile ilişkisinin **tek
kanonik, satır-satır** kaydıdır (kök yönetişim talimatı madde 3). Her satırın
tam detayı ilgili `modules/<key>.md` dosyasının "AI Capability Manifest"
bölümündedir (şema: `templates/AI-CAPABILITY-MANIFEST.md`); bu tablo yalnız
**deterministic_baseline**, **ai_posture** ve kısa opsiyonel kullanım örneğini
taşır, tekrar etmez.

## Temel ilke — AI ürünün genlerindedir, klasik izole bir modül değildir

AI klasik bir iş modülü değildir — deterministik ürün omurgasına (CORE-01..16 +
16 required + 29 OPT, `docs/04`) **yatay** bir Capability Plane eklenir.
`modules/ai-platform.md` bu plane'in runtime-support spesifikasyonudur (bağımsız
tak-çıkar modül değil); `modules/ai-provider-account-vault.md` ayrı bir bileşen
spec'idir. CORE-17 **yaratılmamıştır** — plane, mevcut CORE-03 (authz), CORE-04
(entitlement/usage), CORE-06 (settings/secrets), CORE-07 (audit/outbox), CORE-12
(money/ledger) sözleşmelerini tüketir, kendi CORE kodu talep etmez (`docs/04` §5
"Core'u sınırsız büyütmeme kuralı" ile tutarlı).

**"AI-off'ta tam determinizm" ile "AI kabiliyeti yok" aynı şey değildir — bunlar
iki ayrı eksendir.** Deterministik kabiliyet **her zaman** mevcuttur;
AI-destekli zenginleştirme **opt-in/config'e bağlı** ama mimari olarak Stage
0'dan itibaren **derinlemesine pre-wired**'dır (`templates/AI-CAPABILITY-MANIFEST.md`
§İki eksen). "AI yokluğu varsayılan, varlığı istisna" ifadesi bu külliyatın AI-
first felsefesini **yanlış** tarif eder — doğrusu: deterministik kabiliyet her
zaman mevcuttur, AI zenginleştirmesi opsiyoneldir ve fazın/riskin gerektirdiği
yerde varsayılan **kapalı** tutulur, ama 62/62 modülde port/event/permission/
eval kancasıyla önceden bağlanmıştır.

## İki eksen özet dağılımı

| ai_posture | Modül sayısı | Anlamı |
|---|---|---|
| advisory | 32 | AI açıklar/özetler; hiçbir alana taslak yazmaz |
| assistive | 24 | AI düzenlenebilir taslak üretir; onaysız kalıcı olmaz |
| automated_guarded | 1 | Dar kapsamlı, geri alınabilir, loglanan otomatik eylem |
| agentic_guarded | 3 | Sınırlı tool-allowlist içinde sandbox/simülasyon |
| none (istisnai, gerekçeli) | 1 | Saf paketleme/shell katmanı — içerik/karar yüzeyi yok |

(32 + 24 + 1 + 3 + 1 = 61.)

`deterministic_baseline: required` **61/61 modülde sabittir** — hiçbir
modülde gevşetilmez veya "opsiyonel" olarak işaretlenmez.

## A. Core Kernel (CORE-01..16) — 16 modül

| Modül | ai_posture | Opsiyonel AI kullanım örneği (özet) | Phase |
|---|---|---|---|
| [`core-identity-sessions`](../modules/core-identity-sessions.md) | advisory | Şüpheli oturum risk açıklaması | Stage 1 (AI-hazır Stage 0'dan) |
| [`core-tenancy`](../modules/core-tenancy.md) | advisory | Workspace kurulum tutarsızlık uyarısı | Stage 1 |
| [`core-authorization`](../modules/core-authorization.md) | advisory | Yetki politikası taslağı + red açıklaması (simülasyon) | Stage 1 |
| [`core-entitlements`](../modules/core-entitlements.md) | advisory | Kota aşım riski tahmini | Stage 1 |
| [`core-module-registry`](../modules/core-module-registry.md) | advisory | Enable/disable etki açıklaması | Stage 1 |
| [`core-settings-secrets`](../modules/core-settings-secrets.md) | advisory | Konfigürasyon drift açıklaması (secret değeri asla) | Stage 1 |
| [`core-audit-outbox`](../modules/core-audit-outbox.md) | advisory | Audit anomali özetleme | Stage 1 |
| [`core-localization`](../modules/core-localization.md) | assistive | Eksik çeviri anahtarı taslağı | Stage 1 |
| [`core-taxonomy`](../modules/core-taxonomy.md) | assistive | Duplicate terim tespiti/birleştirme önerisi | Stage 1–2 |
| [`core-workflow-state`](../modules/core-workflow-state.md) | advisory | Sıradaki muhtemel adım önerisi | Stage 1–2 |
| [`core-eca-rules`](../modules/core-eca-rules.md) | agentic_guarded | Kural taslağı + sandbox simülasyonu | Stage 2 |
| [`core-money-ledger`](../modules/core-money-ledger.md) | advisory | Ledger anomali açıklaması (hesaplama/finalite hariç) | Stage 1 |
| [`core-file-media`](../modules/core-file-media.md) | automated_guarded | Otomatik alt-text + moderasyon bayrağı (geri alınabilir) | Stage 1 |
| [`core-notifications`](../modules/core-notifications.md) | assistive | Bildirim şablonu taslağı | Stage 1 |
| [`core-data-lifecycle`](../modules/core-data-lifecycle.md) | advisory | Retention/purge etki açıklaması (karar hariç) | Stage 1 |
| [`core-legal-records`](../modules/core-legal-records.md) | assistive | Şartlar/aydınlatma metni taslağı (hukuk onaylı) | Stage 1 |

## B. Required product modülleri — 16 modül

| Modül | ai_posture | Opsiyonel AI kullanım örneği (özet) | Phase |
|---|---|---|---|
| [`onboarding`](../modules/onboarding.md) | assistive | Kurulum sihirbazı rehberlik metni | Stage 1 |
| [`menu-catalog`](../modules/menu-catalog.md) | assistive | Veri kalitesi/eksik alan önerisi | Stage 1 |
| [`publication`](../modules/publication.md) | advisory | Yayın-öncesi kalite kontrol açıklaması | Stage 1 |
| [`qr-destination`](../modules/qr-destination.md) | advisory | Destination sağlık açıklaması | Stage 1 |
| [`qr-print-export`](../modules/qr-print-export.md) | assistive | Baskı/kontrast/layout önerisi | Stage 1 |
| [`themes-brand`](../modules/themes-brand.md) | assistive | Marka renk/tema token önerisi | Stage 1 |
| [`page-composition`](../modules/page-composition.md) | assistive | Sayfa slot kompozisyon önerisi | Stage 1 |
| [`content-frontpages`](../modules/content-frontpages.md) | assistive | Landing/pricing/FAQ içerik taslağı | Stage 1 |
| [`pricing-subscription-billing`](../modules/pricing-subscription-billing.md) | advisory | Churn/maliyet içgörüsü (ücretlendirme hariç) | Stage 1, 3 |
| [`iyzico-payment`](../modules/iyzico-payment.md) | advisory | Ödeme anomali bilgilendirmesi (karar hariç) | Stage 1, 3 |
| [`analytics-consent-tagging`](../modules/analytics-consent-tagging.md) | advisory | Trafik/dönüşüm içgörü özeti | Stage 1 |
| [`seo-search-discovery`](../modules/seo-search-discovery.md) | assistive | Meta/JSON-LD taslak önerisi | Stage 3 |
| [`mini-crm`](../modules/mini-crm.md) | advisory | Contact özeti + sıradaki-en-iyi-eylem önerisi | Stage 2 |
| [`helpdesk-tickets`](../modules/helpdesk-tickets.md) | assistive | Ticket yanıt taslağı + triage önerisi | Stage 2 |
| [`ai-platform`](../modules/ai-platform.md) | advisory | Plane'in kendisi — routing/kill switch/eval | Stage 2 |
| [`integration-hub`](../modules/integration-hub.md) | agentic_guarded | Webhook/API eşleme + sandbox test | Stage 6 |

## C. Optional katalog (OPT-01..29) — 29 modül

| Modül | ai_posture | Opsiyonel AI kullanım örneği (özet) | Phase |
|---|---|---|---|
| [`opt-01-product-variants`](../modules/opt-01-product-variants.md) | assistive | Varyant seti önerisi | M1 |
| [`opt-02-product-extras-modifiers`](../modules/opt-02-product-extras-modifiers.md) | assistive | Modifier grubu önerisi | M1 |
| [`opt-03-multiple-menus`](../modules/opt-03-multiple-menus.md) | advisory | Zaman dilimi menü segmentasyonu önerisi | M1 |
| [`opt-04-multi-language-content`](../modules/opt-04-multi-language-content.md) | advisory | Dil kapsama analizi | M1 |
| [`opt-05-advanced-qr-designer`](../modules/opt-05-advanced-qr-designer.md) | assistive | Tasarım/renk/logo önerisi | M1 |
| [`opt-06-advanced-analytics`](../modules/opt-06-advanced-analytics.md) | advisory | Trend/anomali içgörü anlatımı | M1 |
| [`opt-07-csv-import-export`](../modules/opt-07-csv-import-export.md) | assistive | CSV sütun→şema eşleme önerisi | M1 |
| [`opt-08-custom-branding`](../modules/opt-08-custom-branding.md) | assistive | Marka varlığı tutarlılık önerisi | M1 |
| [`opt-09-custom-domain`](../modules/opt-09-custom-domain.md) | advisory | DNS hata açıklaması | M1 |
| [`opt-10-multi-branch-management`](../modules/opt-10-multi-branch-management.md) | advisory | Şube performans karşılaştırması | M1 |
| [`opt-11-scheduled-publishing`](../modules/opt-11-scheduled-publishing.md) | advisory | En uygun yayın zamanı önerisi | M1 |
| [`opt-12-menu-version-rollback`](../modules/opt-12-menu-version-rollback.md) | advisory | Versiyon farkı açıklaması | M1 |
| [`opt-13-pos-integrations`](../modules/opt-13-pos-integrations.md) | agentic_guarded | Alan eşleme + sandbox test senkronizasyonu | M2 |
| [`opt-14-online-ordering`](../modules/opt-14-online-ordering.md) | advisory | Sipariş anomali bilgilendirmesi | M2 |
| [`opt-15-restaurant-payment`](../modules/opt-15-restaurant-payment.md) | advisory | Ödeme anomali bilgilendirmesi (karar hariç) | M2 |
| [`opt-16-reservation`](../modules/opt-16-reservation.md) | assistive | No-show riski + masa atama önerisi | M2 |
| [`opt-17-loyalty`](../modules/opt-17-loyalty.md) | advisory | Ödül seviyesi içgörüsü | M2 |
| [`opt-18-crm-extended`](../modules/opt-18-crm-extended.md) | advisory | Segment özeti + sıradaki-en-iyi-eylem | M2 |
| [`opt-19-marketing-automation`](../modules/opt-19-marketing-automation.md) | assistive | Otomasyon adımı içerik taslağı | M2 |
| [`opt-20-campaign-management`](../modules/opt-20-campaign-management.md) | assistive | Kampanya metni/brief taslağı | M2 |
| [`opt-21-ai-menu-import`](../modules/opt-21-ai-menu-import.md) | assistive | Fotoğraf/PDF → ürün taslağı | M2 |
| [`opt-22-ai-translation`](../modules/opt-22-ai-translation.md) | assistive | Çeviri taslağı önerisi | M2 |
| [`opt-23-ai-product-description`](../modules/opt-23-ai-product-description.md) | assistive | Ürün açıklaması taslağı (alerjen hariç) | M2 |
| [`opt-24-inventory-recipes`](../modules/opt-24-inventory-recipes.md) | advisory | Stok tükenme/reçete maliyet içgörüsü | M2 |
| [`opt-25-feedback-nps`](../modules/opt-25-feedback-nps.md) | advisory | Geri bildirim duygu/tema özeti | M2 |
| [`opt-26-developer-api-webhooks-extended`](../modules/opt-26-developer-api-webhooks-extended.md) | assistive | Webhook payload/şema taslağı | M2 |
| [`opt-27-marketplace`](../modules/opt-27-marketplace.md) | advisory | Listeleme kalite önerisi | M2+ (Growth) |
| [`opt-28-metabase-embed`](../modules/opt-28-metabase-embed.md) | advisory | Dashboard veri anlatımı (RLS sınırlı) | M2+ (Growth) |
| [`opt-29-native-app-shell`](../modules/opt-29-native-app-shell.md) | none (istisnai, gerekçeli) | — saf paketleme/shell, içerik yüzeyi yok | Growth |

## Değişmez kurallar (her satırda geçerli, tekrar edilmez)

- **`ai_posture` ne olursa olsun**, authz (CORE-03), tenant isolation (CORE-02),
  para/ledger/ödeme finalitesi (CORE-12, Iyzico Payment), permission (CORE-03),
  publish/delete/purge (Publication, CORE-15), legal/consent (CORE-16) kararı
  **hiçbir zaman** AI'ya devredilmez. Bu kısıt bir modülün geri kalanının AI
  desteğinden mahrum kalması için gerekçe **değildir** — nitekim
  `core-money-ledger` finalite için kapalı ama anomaly-explanation için
  `advisory`'dir.
- AI hard-off/sıfır-kredi/quota/429/outage/residency-denial/safety-block/
  invalid-schema durumlarında her modülün `deterministic_baseline` yolu veri
  kaybı olmadan tamamlanır.
- AI kullanılabilirliği hiçbir zaman bir entitlement ön-koşulu **değildir** —
  temel plan özelliği erişimi AI'nın çalışır olmasına bağlanmaz.

## Sayım doğrulaması

16 (Core) + 16 (Required) + 29 (Optional) = **61**. Bu tablo tam 61 satır
taşır; eksik veya duplicate modül yoktur (bkz. kök yönetişim talimatı
"ACCEPTANCE" madde 2). `ai-provider-account-vault.md` bu tabloda **yer almaz**
— o, 61 eski modülün dışında yeni eklenen 62. modüldür (`ai_posture: none`,
aynı istisna sınıfında `opt-29` ile birlikte — 62 modül içinde toplam **iki**
`none` örneği, ikisi de gerekçeli); kendi AI Capability Manifest'i kendi
dosyasında yaşar.

## Kanonik sahiplik

`ai_posture` ve opsiyonel kullanım örneğinin tek kanonik kaynağı burasıdır.
Alan detayı (Data classification, Allowed tools, Forbidden authority, Human
approval, Budget/credit behavior vb.) yalnız ilgili `modules/*.md` dosyasında
yaşar, burada tekrar edilmez.
