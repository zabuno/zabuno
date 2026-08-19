# AI Platform

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
Feature×model matrisi, invoke routing ve AI güvenlik/policy katmanını
sağlamak (`docs/14`, `docs/32`). Provider **hesap/bağlantı** yönetimi (N adet
yetkili API organization/project/workspace/service-account/key/OAuth
connection, platform-owned vs. tenant BYOK, rotation/health/failover) ayrı bir
bileşen spec'idir: [`modules/ai-provider-account-vault.md`](ai-provider-account-vault.md).
Bu ikisi birlikte AI Capability Plane'i oluşturur.

## Bounded context
AI çağrı altyapısı (invoke routing, feature policy, kill switch, credit
ledger tüketimi). Her özelliğin AI kullanıp kullanmayacağı ilgili modülün
kararıdır (`docs/32` Mode alanı); bu modül **ortak altyapıyı** sağlar. Hesap/
bağlantı seçimi ve provider-taraflı rate/quota disiplini Vault'un
sorumluluğundadır (yalnız orada, burada tekrar edilmez).

## Owner
Engineering + Security.

## Sınıf
Required infrastructure — **AI Capability Plane runtime-support
spesifikasyonu**. Bu, bağımsız tak-çıkar bir domain modülü **değildir**;
CORE-01..16 finite Kernel'in yanına eklenen **yatay** bir plane'dir (kök
yönetişim talimatı madde 1). Disable edilebilir (kill switch, bkz. Rollback)
ama "opsiyonel katalogdan bir OPT modülü" gibi paketlenmez — 61 modülün
tamamının Mode alanını (`docs/32`) besleyen ortak omurgadır.

## Bağımlılıklar
CORE-03 (authorization — AI hiçbir authz kararı veremez), CORE-04
(entitlement/usage — AI credit metering buradan geçer), CORE-06 (secrets —
provider credential'ları `ai-provider-account-vault`'ta, erişim CORE-06
hiyerarşisiyle), CORE-07 (audit/outbox — her AI çağrısı audit'e yazılır),
CORE-12 (money/ledger — AI credit reserve/debit/reconcile/release/refund
CORE-12 sözleşmelerini tüketir, kendi ledger'ını icat etmez), CORE-11 (tool
allowlist ECA ile ilişkili), [`ai-provider-account-vault`](ai-provider-account-vault.md).

## Public contracts / events
`AIPort::invoke(feature, model, input, schema)`; `AIInvoked`, `AIKillSwitchActivated`
event'leri.

## Tenant isolation
AI context tenant'lar arası **sızmaz**; her çağrı tenant-scoped izole edilir.

## Permissions
`ai.manage`, `ai.use.<feature>`.

## Entitlement / quota
AI credit/model/tool erişimi CORE-04 üzerinden.

## ECA hooks
AI önerileri ECA action'ı olarak tetiklenebilir ama `requires_human_approval`
bayrağıyla (`docs/14` §4).

## AI-off / AI-on davranışı
Bu modülün **kendisi** bu ayrımın uygulayıcısıdır — kill switch burada yaşar.

## UX one-click journey
AI önerisini tek tık kabul/reddetme (asla otomatik uygulanmaz, destructive
olmayan öneriler hariç).

## States
AI çağrısı: `queued → processing → completed/failed`.

## Data retention / export
Prompt/response logları redaction'lı saklanır (PII sızıntısı önlenir).

## Observability
Maliyet/latency/hata oranı, provider bazlı kırılım.

## Security / privacy
Prompt injection/content-poisoning koruması, tool allowlist, tenant isolation
(`docs/14` §3).

## Accessibility / i18n
AI öneri arayüzü WCAG 2.2 AA.

## Phase delivery
Stage 2 Post-MVP — temel kapsam (`docs/19`).

## Acceptance
Kill switch'in gerçekten tüm AI çağrılarını durdurduğunun testi; tool
allowlist dışı bir eylemin reddedildiğinin testi.

## Rollback
Kill switch ile anlık global durdurma; modülün kendisi disable edilirse tüm
AI-destekli öneriler kaybolur, çekirdek işlevler etkilenmez (`docs/01` §3).

## Open questions
Vendor drift izlenmiyor (`docs/16` AI-01); prompt-injection eval seti
otomatikleştirilmedi (`docs/16` AI-02).

## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`. Bu modül plane'in **kendisi** olduğu
için bu bölüm istisnai biçimde tüm plane için bağlayıcı varsayılanları taşır;
her tüketici modül kendi dosyasında yalnız kendi Mode'una özgü satırları
doldurur.

- **deterministic_baseline**: required (plane kapansa da 61 tüketici modülün
  hepsi kendi deterministik omurgasında tam çalışır).
- **ai_posture**: advisory (plane'in kendisi hiçbir eylemi kendi başına kalıcı
  hale getirmez; her öneri ayrı bir insan kabul eylemi gerektirir — bağımlı
  modüllerin kendi `ai_posture` değerleri `docs/32`'de tanımlıdır: advisory,
  assistive, automated_guarded, agentic_guarded).
- **Optional AI use case(ler)**: Feature×model routing, kill switch, cost cap,
  eval seti, tool allowlist, redaction, prompt versiyonlama + JSON schema —
  bunlar 61 tüketici modülün kendi opsiyonel AI kullanım örneklerini (bkz.
  `docs/32`) çalıştıran ortak omurgadır.
- **AI-off / no-credit deterministic path**: Kill switch aktifse veya
  `ai-provider-account-vault` sıfır iç kredi/no-provider-credit/quota/429/
  outage/residency-denial/safety-block/invalid-schema durumlarından birini
  bildirirse — tüm bağımlı modüller (`docs/32` Mode: advisory/assistive
  olanlar) görünmez/pasif hale gelir, kullanıcı girdisi/taslağı **korunur**,
  templates/rules/manual UX ile normal ve kritik akış sürer; gizli ücret veya
  otomatik top-up yoktur (`docs/14` §1, kök yönetişim talimatı madde 9).
- **Data classification**: Prompt/response redaction'lı loglanır; PII prompt'a
  sızdırılmaz; tenant context asla başka tenant'a sızmaz.
- **Allowed tools/side effects**: Yalnız tool-allowlist'te
  `requires_human_approval` bayrağıyla register edilmiş action'lar; destructive/
  publish/payment/permission action'ları asla onaysız çalışmaz (`docs/14` §4).
- **Forbidden authority (final-authority)**: authz (CORE-03), tenant isolation (CORE-02), money/
  ledger/ödeme finalitesi (CORE-12, Iyzico Payment), permission (CORE-03),
  publish/delete/purge (Publication, CORE-15), legal/consent (CORE-16) — bu
  altı alan hiçbir koşulda AI kararına devredilmez.
- **Human approval**: Her assistive/advisory öneri tek-tık kabul/red ile
  insana devredilir (destructive olmayan öneriler dahil, `docs/06` §7).
- **Feature policy**: feature × provider/model × account × policy ×
  tenant/residency matrisi burada çözülür; hesap seçimi
  `ai-provider-account-vault`'a delege edilir (`docs/14` §2).
- **Budget/credit behavior**: reserve → invoke → actual debit/reconcile/
  release/refund; idempotent, immutable audit; provider maliyeti ile iç
  entitlement kredisi ayrı tutulur (bu ayrımın ledger detayı
  `ai-provider-account-vault` §credit ledger'da).
- **Eval/audit**: Prompt-injection/content-poisoning eval seti (`docs/16`
  AI-02, henüz otomatikleştirilmedi); her çağrı `AIInvoked`/
  `AIKillSwitchActivated` event'leriyle CORE-07'ye audit edilir.
- **Phase**: Stage 2 Post-MVP (`docs/19`).
