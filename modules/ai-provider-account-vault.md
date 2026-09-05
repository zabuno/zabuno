# AI Provider Account Vault

> **DURUM BURADA YAZMAZ — KOD SÖYLER.**
>
> Bu satırda bir zamanlar "PLANNING ONLY. Şu an çalıştırılamaz." yazıyordu
> ve **altmış iki modül dosyasının altmış ikisinde de aynı cümle vardı** —
> menü kataloğu, yayınlama, karekod ve medya dahil. Oysa 2026-09-05
> envanterinde on sekiz modül uygulanmış, on dokuzu kısmen uygulanmış
> çıktı. Yani cümle en az on sekiz dosyada açıkça yanlıştı.
>
> Sebebi bir ihmal değil, YAPININ KENDİSİYDİ: bir modül teslim edildiğinde
> kimse tanım dosyasına geri dönmüyor. Aynı cümleyi altmış iki dosyada
> güncel tutmak, aynı hatayı daha büyük ölçekte tekrarlamak olurdu.
>
> Bu yüzden durum alanı **kaldırıldı**. Bu dosya modülün NE OLDUĞUNU
> anlatır; ÇALIŞIP ÇALIŞMADIĞINI kod söyler ve türetilmiş envanter gösterir
> (`docs/111`). Bir soru "bu modül var mı?" ise cevabı burada aramayın.

## Amaç
Provider'a yetkili **hesap/bağlantı** kaydını (organization/project/workspace/
service-account/key/OAuth connection — provider adına göre değişir), bunların
şifreli saklanmasını, N-adet (hard-code edilmemiş) bağlantı desteğini,
platform-owned ile tenant-BYOK ayrımını, sağlık/maliyet/gecikme/kota bilgisine
göre routing'i ve AI credit ledger'ının hesap bazlı defter tutuculuğunu
sağlamak. Feature×model **karar** matrisi `modules/ai-platform.md`'de yaşar;
bu modül yalnız o kararın **hangi hesap üzerinden** yürütüleceğini çözer.

## Bounded context
Hesap/bağlantı yaşam döngüsü (ekle/doğrula/döndür/devre dışı bırak/sil),
routing (priority/weighted/cost/latency/health), rate/quota/circuit-breaker
disiplini, credit ledger. Prompt içeriği, feature seçimi, tool-allowlist kararı
bu modülün **değil**, `ai-platform`'un sorumluluğundadır — cross-module
iletişim yalnız contract/event (`docs/03` ADR-L05).

## Owner
Engineering + Security (secret custody), Finance Operator (credit ledger).

## Sınıf
Required infrastructure — AI Capability Plane'in ikinci yarısı. CORE-17
**değildir** (`docs/04` §5 "Core'u sınırsız büyütmeme kuralı" testinden geçmez:
disable edilebilir, ticari modül olarak paketlenemeyecek kadar altyapısal
olsa da yalnız bir modülün — `ai-platform`'un — bağımlılığıdır, iki farklı
required modülün ortak bağımlılığı değildir).

## Bağımlılıklar
CORE-06 (secrets — hesap credential'ları burada saklanır, encrypted-at-rest,
master key webroot dışında), CORE-07 (audit — her hesap değişikliği/rotasyonu
audit'e yazılır), CORE-12 (money/ledger — AI credit ledger CORE-12'nin
sözleşmelerini tüketir, kendi para birimi/defter mantığını icat etmez),
`modules/ai-platform.md` (tek tüketici).

## Public contracts / events
`ProviderAccountPort::register/rotate/disable/healthCheck(account)`;
`AccountRoutingPort::resolve(feature, tenant, policy) → account`;
`CreditLedgerPort::reserve/debit/reconcile/release/refund(tenant, feature,
account, amount)`; event'ler: `ProviderAccountRegistered`,
`ProviderAccountRotated`, `ProviderAccountDisabled`, `ProviderAccountHealthChanged`,
`AICreditReserved`, `AICreditDebited`, `AICreditReleased`, `AICreditRefunded`.

## Tenant isolation
Platform-owned hesaplar tüm tenant'lar arasında **paylaşılabilir** (routing
politikasına göre); tenant BYOK (bring-your-own-key) hesapları **yalnız o
tenant'a** aittir ve başka tenant'ın routing'inde asla aday olarak görünmez —
bu, yapısal bir izolasyon kuralıdır, yalnız bir filtre değil.

## Permissions
`ai.account.manage.platform` (Platform Owner/Admin), `ai.account.manage.tenant`
(tenant Owner — yalnız kendi BYOK hesapları), `ai.account.view` (salt-okunur,
Engineering/Security). Provider tarafında da **least-privilege** ilkesi
uygulanır: platform-owned hesaplar, mümkün olduğunda provider'ın kendi
organization/project ayrımını kullanarak (varsa) proje-scoped service-account/
API key ile bağlanır — tek bir organizasyon-geneli anahtar yerine, her
kullanım amacına (feature/tenant havuzu) ayrı, sınırlı yetkili bir bağlantı
tercih edilir (`docs/28` "OpenAI Admin API — organization/projects" satırı,
koşullu/pending — erişim bu oturumda doğrulanamadı).

## Entitlement / quota
Bir tenant'ın kaç BYOK hesabı ekleyebileceği ve platform-owned havuzdan alacağı
pay CORE-04 entitlement kayıtlarına tabidir (`docs/09` §4).

## ECA hooks
`ProviderAccountHealthChanged` (degraded/down) → bildirim (CORE-14) ve routing
tablosundan geçici çıkarma tetikleyebilir; otomatik hesap **silme/iptal**
tetiklemez (insan onayı gerekir).

## AI-off / AI-on davranışı
Bu modül AI-off durumunda hiçbir hesabı silmez/iptal etmez — yalnız routing
durdurulur (`ai-platform` kill switch ile). Sıfır iç kredi/no-provider-credit
durumunda da hesap kaydı korunur, yalnız yeni invoke reddedilir.

## UX one-click journey
Superadmin (platform) ve tenant Owner (BYOK) için: **connect → test → rotate →
disable → map**. "Map" adımı bir hesabı hangi feature/policy'ye
yönlendireceğini tek ekranda gösterir. Hiçbir adım tüketici ChatGPT/Claude
Pro/Max girişini production credential olarak kabul etmez (bkz. §Tüketici
abonelik yasağı).

## States
Hesap: `pending_verification → active → degraded → disabled`. Credit rezervasyonu:
`reserved → debited/released` (sonrasında opsiyonel `reconciled` veya `refunded`).

## Data retention / export
Hesap credential'ları export edilmez (yalnız var/yok + maskelenmiş son 4
karakter gösterilir, CORE-06 §Security ile aynı disiplin). Credit ledger
kayıtları immutable, export edilebilir (muhasebe/denetim).

## Observability
Hesap bazlı maliyet/latency/hata oranı, health-check sonucu, rate-limit/429
sıklığı, circuit-breaker açılma sayısı, credit reserve→debit→reconcile
uyuşmazlık oranı. Provider'ın döndürdüğü istek-izleme başlığı (örn. request-id)
ve rate-limit response header'ları, her provider çağrısı audit/observability
kaydına eklenir — destek talebi/hata ayıklama sırasında provider tarafına
başvururken kullanılır (`docs/28` "OpenAI API reference overview — debugging
requests" satırı, koşullu/pending — erişim bu oturumda doğrulanamadı).
Provider organization/project seviyeli usage/cost raporlaması (varsa) periyodik
olarak çekilip CORE-12 ledger'ıyla **reconcile** edilir; `project_id`/hesap
kimliğine göre gruplanabilen bir provider maliyet raporu, hesap bazlı iç
maliyet dağılımını çapraz doğrulamak için kullanılır (`docs/28` "OpenAI Admin
API — organization/usage" satırı, koşullu/pending).

## Security / privacy
Secret material client/log/source control dışında tutulur; encrypted-at-rest;
master key webroot dışında saklanır; opsiyonel external vault adapter (koşullu
sınıf, henüz seçilmedi — `docs/16` AIV-09). Bu, provider'ların kendi admin-key
davranışıyla tutarlıdır — bir secret değeri yalnız oluşturma anında bir kez
gösterilir, sonrasında yalnız redacted metadata görünür kalır (`docs/28`
"OpenAI Admin API — organization/admin_api_keys" satırı, koşullu/pending —
erişim bu oturumda doğrulanamadı); bu modül kendi credential'ları için de aynı
tek-seferlik-görünürlük disiplinini uygular. Shared-hosting varsayımı
altında da bu disiplin geçerlidir (`docs/15` §4 kapasite matrisiyle uyumlu).

## Accessibility / i18n
Hesap yönetim ekranları WCAG 2.2 AA; hata/health mesajları çok dilli.

## Phase delivery
Stage 2 Post-MVP — tek platform-owned hesap + temel routing; tenant BYOK ve
çok-hesaplı weighted/cost/latency routing Stage 3 GTM ve sonrasında genişler
(`docs/26`).

## Acceptance
(1) Tüketici Pro/Max girişinin production credential olarak reddedildiğinin
testi. (2) Bir hesap 429/quota aşımına ulaştığında Retry-After/backoff +
circuit breaker'ın devreye girdiğinin ve **aynı providerın başka bir hesabına
otomatik sınırsız failover yapılmadığının** testi (failover yalnız ayrı,
sözleşmeye uygun kapasiteye, veri sınıfı/residency/policy izin verirse). (3)
BYOK hesabının başka tenant'ın routing adayları arasında **hiçbir koşulda**
görünmediğinin testi. (4) reserve→debit→reconcile→release/refund zincirinin
idempotent olduğunun testi.

## Rollback
Bir hesap disable edilirse yalnız o hesap routing'den çıkar; diğer hesaplar
(varsa) veya AI-off deterministik yol devam eder. Vault'un tamamı devre dışı
bırakılırsa `ai-platform` kill switch senaryosuyla aynı sonuca düşer (§AI-off
davranışı).

## Open questions
Legal entity/hesap sahipliği netleşmedi (`docs/16` AIV-01); provider
şartlarındaki drift'in bu modül tarafından nasıl izleneceği
`skills/ai-provider-evaluator`'a bağlı ama otomatikleştirilmedi (`docs/16`
AI-01); external vault adapter seçimi ve shared-host feasibility'si
yapılmadı (`docs/16` AIV-09).

## Tüketici abonelik yasağı — bağlayıcı not

Tüketici ChatGPT veya Claude Pro/Max hesapları **hiçbir koşulda** production
API credential havuzu olarak kabul edilmez. Account rotation, bir providerın
proje/org düzeyindeki rate/quota/usage-limit'ini aşmak amacıyla
**kullanılamaz**. Bu modülün "connect" akışı yalnız resmi API
organization/project/workspace/service-account/key/OAuth connection kabul
eder; bir tüketici abonelik girişi tespit edilirse (örn. yalnız kişisel
oturum çerezi/token'ı sunan bir akış) `pending_verification` durumunda kalır,
**asla `active`'e geçmez**.

## Feature × provider/model × account × policy × tenant/residency routing

Routing kararı şu boyutları çözer: priority | weighted | cost | latency |
health; session affinity (aynı konuşma/oturum aynı hesapta kalabilir);
idempotency (aynı istek tekrar edilirse aynı sonuç/tekrar ücretlendirme yok);
concurrency limiti; circuit breaker + retry budget; consent (tenant BYOK
kullanımı için açık onay); audit (her routing kararı CORE-07'ye yazılır). Bu
boyutların **hiçbiri** hard-code 1/2/3 hesap varsayımıyla yazılmaz — N,
tenant/plan'a göre değişen bir çalışma zamanı değeridir.

## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` (bu modül 61 eski modülün
dışındadır, bu yüzden orada satırı yoktur — bkz. `docs/32` §Sayım
doğrulaması) ve `templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — hesap kayıtları ve credit ledger
  AI plane kapansa da bozulmadan korunur.
- **ai_posture**: none — istisnai, gerekçeli: bu modül kullanıcıya sunulan
  bir içerik/karar yüzeyi değil, saf routing/ledger altyapısıdır; hiçbir LLM
  çağrısı kendisi yapmaz, yalnız `ai-platform`'un çağıracağı hesabı çözer ve
  krediyi defterler. (Karşılaştırma: `opt-29-native-app-shell` de aynı
  gerekçeyle `none`'dur — ikisi de 62 modül içindeki tek iki istisnadır.)
- **Optional AI use case(ler)**: — (bkz. gerekçe)
- **AI-off / no-credit deterministic path**: Vault kapansa da hesap kayıtları
  ve credit ledger **korunur** (silinmez); yalnız yeni reserve/invoke
  reddedilir — bu, `ai-platform` kill switch'inin doğal bir uzantısıdır.
- **Data classification**: Provider credential (secret sınıfı, CORE-06
  hiyerarşisi); tenant/organization kimlik bilgisi (iç operasyonel).
- **Allowed tools/side effects**: Yok (bu modül LLM tool-call yapmaz).
- **Forbidden authority (final-authority)**: Bu modül feature/prompt/tool-allowlist kararı
  vermez (o `ai-platform`'undur); authz/tenant isolation/money finality/
  permission/publish-delete-purge/legal consent kararı vermez.
- **Human approval**: Hesap ekleme/rotasyon/devre dışı bırakma her zaman
  insan eylemidir (superadmin/tenant Owner); otomatik hesap oluşturma yoktur.
- **Feature policy**: Bu modül feature policy'yi **tüketir**, üretmez
  (`ai-platform`'dan gelir).
- **Budget/credit behavior**: Bu modülün **kendisi** credit ledger'ın
  sahibidir — reserve→invoke→actual debit/reconcile/release/refund burada
  gerçekleşir, CORE-12 sözleşmeleriyle.
- **Eval/audit**: Hesap health-check sonuçları ve routing kararları audit'e
  yazılır; provider fiyat/politika drift taraması `skills/ai-provider-evaluator`
  ile ilişkilidir (periyodik, otomatikleştirilmedi).
- **Phase**: Stage 2 Post-MVP (temel), Stage 3+ genişletilmiş routing.
