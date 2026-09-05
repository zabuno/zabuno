# CORE-11 — ECA Rules

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
Event-Condition-Action otomasyon motoru ve Automation Studio UI'ı sağlamak.

## Bounded context
Kural tanımlama/yürütme altyapısı. Kuralın kendisi kullanıcı tarafından
tanımlanır; motor yalnız güvenli/idempotent/audit'li yürütür.

## Owner
Engineering + Security (allowlist onayı için).

## Sınıf
Core.

## Bağımlılıklar
CORE-07, CORE-10 (event kaynakları).

## Public contracts / events
`ECAEngine::registerEvent/registerAction(module, name, schema)`;
`RuleTriggered`, `RuleFailed` event'leri.

## Tenant isolation
Kurallar tenant-scoped tanımlanır ve yürütülür.

## Permissions
`automation.manage`, `automation.approve` (version/approval ayrımı — bir
kişinin tanımladığı kuralı başka birinin onaylaması).

## Entitlement / quota
Kural sayısı/karmaşıklığı plana göre kısıtlanabilir.

## ECA hooks
Bu modülün kendisi ECA altyapısıdır.

## AI-off / AI-on davranışı
AI, kural **önerebilir** ama kuralın devreye alınması insan onayı gerektirir
(`docs/06` §7).

## UX one-click journey
Automation Studio'da dry-run ile tek tık test, sonra publish.

## States
Kural: `draft → pending_approval → active → paused → archived`.

## Data retention / export
Kural geçmişi ve tetiklenme logları saklanır.

## Observability
Tetiklenme sıklığı, hata oranı, recursion-guard tetiklenme sayısı.

## Security / privacy
`eval` yasak; yalnız allowlisted action; condition AST serbest kod çalıştırmaz
(`docs/10` §3).

## Accessibility / i18n
Automation Studio WCAG 2.2 AA.

## Phase delivery
Stage 2 Post-MVP — tam kapsam (`docs/19`).

## Acceptance
Recursion/cycle guard testi; idempotency testi (aynı event iki kez işlense bile
action tek uygulanır).

## Rollback
Core modül ama **kill switch** ile tüm motor anlık durdurulabilir (bir modül
gibi disable edilemez ama global pause desteklenir).

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: agentic_guarded
- **Optional AI use case(ler)**: ECA kural taslağı üretimi ve sandbox'ta (üretim verisine dokunmadan) simülasyonu
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Kural tanımı + simülasyon için örnek/sentetik event verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Kuralın canlıya alınması (enabled:true) ve action'ın gerçek eylemi her zaman insan onayı + requires_human_approval gerektirir; AI kuralı otomatik yayına almaz
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
