# CORE-02 — Tenancy / Organization / Venue

**Bounded runtime durumu: S1-WP02B'nin bounded baseline'ı — workspace
create+owner-membership (tek transaction), üyelik-scope'lu liste,
current/switch context, enumeration-safe tenant escape reddi —
code/test-local-candidate-targeted-green'dir: hedefli kanıt (23/23 test/72
assertion) ve bağımsız kapanış review'ı GREEN, ama yalnız API kodu + izole
test koşusu seviyesindedir — persistent DB migrate edilmedi, workspace
UI/tarayıcı journey'si yoktur, public-promotion RED (`docs/34` §13a). Bu
modülün geri kalanı — Brand/Location/işletme profili alanları, workspace
state geçiş uç noktaları (suspend/archive/delete), davet akışı, multi-branch
genişlemesi, external/social/delivery provider registry UI'ı vb. — hâlâ
**PLANNING ONLY, şu an çalıştırılamaz**.**


> **DURUM BURADA YAZMAZ — KOD SÖYLER (2026-09-05 düzeltmesi).**
>
> Yukarıdaki "PLANNING ONLY" ifadesi bu modül için de eskidi. 2026-09-05
> envanteri bu modülü UYGULANMIŞ saydı; kanıt kodda ve testlerde.
> Yukarıdaki paragrafın ANLATTIĞI eksikler (şifre sıfırlama kapsamı, oturum
> listesi/iptali, hesap durumları, workspace durum geçişleri, sağlayıcı
> kaydı) hâlâ geçerli olabilir — ama bunların doğrusu da koddan okunur.
>
> Durum alanı bütün modül dosyalarından kaldırıldı: bir modül teslim
> edildiğinde kimse tanım dosyasına geri dönmüyordu ve altmış iki dosyanın
> altmış ikisi de kendini "çalıştırılamaz" ilan ediyordu. Türetilmiş
> envanter için `docs/111`.
## Amaç
Workspace/Brand/Location hiyerarşisini çözmek ve her isteğin doğru tenant
bağlamında çalışmasını garanti etmek.

## Bounded context
Tenant kimliği ve hiyerarşisi. Yetki kararı vermez (CORE-03'e devreder); yalnız
"bu istek hangi workspace/brand/location bağlamında" sorusuna cevap verir.

## Owner
Architecture + Engineering.

## Sınıf
Core.

## Bağımlılıklar
CORE-01 (Membership, User'a bağlı).

## Public contracts / events
`TenantResolver` port'u (session+route+membership+policy+db-scope kesişimini
çözer); `WorkspaceCreated`, `BrandCreated`, `LocationCreated`,
`WorkspaceSuspended` event'leri.

## Business profile contract (alan sahipliği)

Bu modül, restoranın **kurumsal işletme profili** alanlarının tek kanonik veri
sahibidir (görsel/marka tarafı `modules/themes-brand.md`'de, medya slot/asset
ownership tarafı `docs/07` §6'da ayrıca sahiplenilir — bu bölüm yalnız **veri**
sözleşmesini tanımlar):

- **Kimlik**: display name, legal name, slug (public URL), description.
- **İletişim**: telefon, e-posta, structured address (sokak/mahalle/ilçe/il/
  ülke/posta kodu ayrı alanlar) + geolocation (lat/lng).
- **Operasyon**: opening hours (gün bazlı, tatil override desteği), timezone,
  currency, cuisine type(ler), service type(ler) (dine-in/takeaway/delivery
  vb. — `docs/04` Taxonomy CORE modülüyle yönetilen esnek vocabulary).
- **Erişilebilirlik/amenity**: engelli erişimi, evcil hayvan kabulü, otopark
  vb. — yine Taxonomy tabanlı, sabit liste değil.
- **External/social/delivery profilleri**: typed bir **provider registry/
  taxonomy** ile yönetilir (hard-code zorunlu liste **değildir**, yeni
  provider eklemek şema değişikliği gerektirmez). Yaygın platform örnekleri:
  LinkedIn, Facebook, Instagram, YouTube/TikTok, Google Business, Tripadvisor,
  Yemeksepeti/GetirYemek/Trendyol Yemek. Her provider kaydı: provider tipi,
  URL/handle, doğrulama durumu (opsiyonel), görünürlük (public menüde
  gösterilsin mi).

Bu alanların **minimum required vs. optional** ayrımı ve onboarding sırasında
sunulan **one-click sensible defaults** (örn. timezone/currency ülkeye göre
ön-doldurma) `modules/onboarding.md`'de akış olarak kullanılır, burada
**yeniden tanımlanmaz** — onboarding yalnız link verir.

Tenant isolation, permission (`workspace.manage`) ve acceptance kriterleri bu
alan seti için de bu modülün genel tenant izolasyon/acceptance rejimine tabidir
(aşağıdaki ## Tenant isolation, ## Permissions, ## Acceptance bölümleri).

## Tenant isolation
Bu modülün **kendisi** tenant izolasyonunun kaynağıdır — her tenant-aware
kayıt `workspace_id`/`brand_id`/`location_id` taşır, backend hiçbir zaman
yalnız client'tan gelen değere güvenmez (`docs/05` §1). Business profile
alanları da workspace/brand/location scope'una tabidir; başka bir tenant'ın
profil alanına erişim/degişiklik denemesi tenant escape testinin kapsamındadır.

## Permissions
`workspace.view`, `workspace.manage`, `brand.manage`, `location.manage`.

## Entitlement / quota
Maksimum brand/location sayısı CORE-04'te uygulanır.

## ECA hooks
`WorkspaceSuspended` → diğer modüllerin (Menu, QR) "suspended" davranışını
tetikleyen event.

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
Workspace switcher ile tek tıkla aktif tenant değişimi (`docs/06` §4).

## States
Workspace: `onboarding → active → suspended → archived → deletion_pending → deleted` (`docs/10` §2).

## Data retention / export
Workspace silindiğinde retention süresi başlar (CORE-15).

## Observability
Tenant resolver hatası oranı (yanlış/başarısız tenant çözümü), suspend edilen
workspace sayısı.

## Security / privacy
IDOR testleri bu modülün acceptance kriterinin merkezindedir (`docs/05` §6,
`docs/16` AUTH-02).

## Accessibility / i18n
Workspace switcher WCAG 2.2 AA, tüm workspace adları çok dilli değil (kullanıcı
girdisi, çeviri gerektirmez).

## Phase delivery
Stage 1 MVP — tam kapsam (tek location); Stage 5 Growth'ta multi-branch
genişlemesi (`OPT-10`).

## Acceptance
Tenant escape testi (bir workspace'in verisine başka workspace'ten erişim
denemesi başarısız olmalı); workspace state transition testi; business profile
alanlarının (yukarıdaki ## Business profile contract) required/optional
doğrulaması ve external/social/delivery provider registry'sinin şema
değişikliği gerektirmeden yeni bir provider kabul ettiğinin testi.

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Bir kullanıcının açabileceği workspace sayısı sınırsız mı — `docs/16` BIZ-02.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Yeni workspace kurulumunda tutarsızlık/eksik alan uyarı açıklaması
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Tenant meta verisi (iç operasyonel, PII değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Tenant çözümü/izolasyonu deterministiktir, AI tenant kararı vermez
- **Human approval**: Üretilen her AI çıktısı (taslak/öneri/açıklama) kalıcı
  veri veya eylem haline gelmeden önce ayrı, açık bir insan eylemi gerektirir
  (`docs/01` §3, `docs/06` §7).
- **Feature policy**: feature × provider/model × account × policy ×
  tenant/residency `modules/ai-provider-account-vault.md` üzerinden çözülür.
- **Budget/credit behavior**: reserve→invoke→debit/reconcile/release/refund
  (`modules/ai-provider-account-vault.md` §credit ledger); kullanılmayan/
  reddedilen öneri release/refund edilir.
- **Eval/audit**: Kullanım/kabul oranı ve çağrı audit'i CORE-07'ye yazılır;
  modüle özgü eval seti implementasyon başladığında tanımlanır (henüz yok —
  `docs/16`'ya genel AI eval açık maddesi, `docs/16` AI-02 ile ilişkili).
- **Phase**: Mimari olarak Stage 0'dan itibaren pre-wired (port/event/izin
  tanımlı); etkinleştirme fazı için bkz. `docs/32` ilgili tablo satırı ve
  `docs/26`.
