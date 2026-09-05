# CORE-16 — Legal Records

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
Şartlar, aydınlatma metni, açık rıza ve doküman versiyonlarını ayrı ayrı
kaydetmek (KVKK uyumu).

## Bounded context
Rıza/legal doküman kaydı. Tek bir "KVKK onayı" alanı **yeterli değildir**
(`docs/01` §6, kaynak dokümandan korunmuş kritik uyarı).

## Owner
Owner + Hukuk danışmanlığı.

## Sınıf
Core.

## Bağımlılıklar
CORE-01 (kullanıcıya bağlı rıza kayıtları), CORE-07 (audit).

## Public contracts / events
`ConsentPort::record(user, documentType, version, action)`; `ConsentGiven`,
`ConsentWithdrawn`, `LegalDocumentPublished` event'leri.

## Tenant isolation
Rıza kayıtları kullanıcıya bağlı (tenant-bağımsız olabilir — kullanıcı birden
fazla workspace'e üye olsa bile rızası platform geneli olabilir; kesin model
`docs/16` LEG-01'e bağlı).

## Permissions
`legal.manage` (doküman versiyon yayınlama, Platform Owner).

## Entitlement / quota
Yok.

## ECA hooks
`ConsentWithdrawn` → ilgili analytics/marketing tag'lerinin devre dışı
bırakılması tetiklenir (`docs/12` §1 consent-before-tags).

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
Rıza tercihleri ekranında tek tık geri çekme.

## States
Rıza: `given → withdrawn` (her ikisi de zaman damgalı, geçmiş korunur).

## Data retention / export
Rıza kayıtları uzun süre saklanır (yasal kanıt niteliğinde) — kesin süre
`docs/16` LEG-01/DATA-01'e bağlı.

## Observability
Rıza geri çekme oranı, doküman versiyon güncelleme sıklığı.

## Security / privacy
Bu modülün **kendisi** KVKK uyum altyapısıdır.

## Accessibility / i18n
Aydınlatma metinleri çok dilli, WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — kayıt akışındaki ayrık rıza alanları; tam doküman versiyonlama
Stage 2/3.

## Acceptance
Ayrı rıza kayıtlarının (kullanım şartı, aydınlatma, açık rıza, pazarlama izni,
cookie tercihi) gerçekten ayrı satırlar olarak tutulduğunun testi.

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
Restoran mı platform mu veri sorumlusu — hukuk teyidi gerekir (`docs/16` LEG-01).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Şartlar/aydınlatma metni taslağı üretimi (hukuki dilde, hukuk onayı zorunlu)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Hukuki metin taslağı (kişisel veri değil)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Rıza/consent kaydının kendisi (kullanıcının onay verip vermediği) AI'ya devredilmez, yalnız metin taslağı üretir
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
