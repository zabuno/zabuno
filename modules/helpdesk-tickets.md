# Helpdesk / Tickets

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
Ticket queue, status, SLA, atama, eskalasyon yönetimi sağlamak.

## Bounded context
Destek talebi yaşam döngüsü. Tenant ve platform sınırı net ayrılır (`docs/11`
§4).

## Owner
Support Agent + Engineering.

## Sınıf
Required product.

## Bağımlılıklar
CORE-02, CORE-14 (bildirimler), Mini CRM (opsiyonel ilişki).

## Public contracts / events
`TicketCreated`, `TicketEscalated`, `TicketResolved` event'leri.

## Tenant isolation
Tenant yalnız kendi ticket'larını görür; Support Agent sınır ötesi görünürlüğe
sahip ama audit'e tabi (`docs/11` §4).

## Permissions
`helpdesk.view.tenant`, `helpdesk.view.platform`, `helpdesk.manage`.

## Entitlement / quota
SLA seviyesi plana bağlı.

## ECA hooks
`TicketEscalated` → bildirim + SLA sayaç kuralı.

## AI-off / AI-on davranışı
AI ticket özeti/öneri sunabilir; çözüm/kapama insan kararıyla olur.

## UX one-click journey
Ticket detayında tek tık durum değiştirme, ek dosya sürükle-bırak.

## States
`open → in_progress → escalated → resolved → closed`.

## Data retention / export
Ticket geçmişi retention politikasına tabi.

## Observability
SLA uyum oranı, ortalama çözüm süresi.

## Security / privacy
Support Agent'ın tenant verisine erişimi audit'e yazılır.

## Accessibility / i18n
Ticket ekranı WCAG 2.2 AA, çok dilli.

## Phase delivery
Stage 2 Post-MVP — temel kapsam; Stage 6'da SLA sözleşmeli genişleme.

## Acceptance
Tenant/platform görünürlük sınırının doğrulandığı testi.

## Rollback
Disable edilirse ticket geçmişi arşivlenir, silinmez.

## Open questions
Yok.


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: assistive
- **Optional AI use case(ler)**: Ticket yanıt taslağı ve triage/öncelik önerisi
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Ticket içeriği (PII olabilir, redaction politikasına tabi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Ticket kapatma/SLA ihlali kararı insan/deterministik kuraldadır
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
