# CORE-05 — Module Registry

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
Tüm modüllerin manifest'ini, bağımlılık grafiğini ve yaşam döngüsü durumunu
merkezi olarak yönetmek.

## Bounded context
"Hangi modüller var, hangileri enable, bağımlılıkları neler" sorusu. Bir
modülün iş mantığını içermez, yalnız onun varlığını/durumunu yönetir.

## Owner
Architecture + Engineering.

## Sınıf
Core.

## Bağımlılıklar
Yok (en temel katmanlardan biri, CORE-01/02 ile birlikte).

## Public contracts / events
`ModuleEnabled`, `ModuleDisabled`, `ModuleUpgraded` event'leri; `ModuleManifest`
value object (name, version, dependencies, permissions, routes, migrations,
frontend entry, settings, entitlements, events, jobs, health checks —
`docs/03` ADR-L04).

## Tenant isolation
Deployment-level install tenant-bağımsızdır; tenant-level enable/entitlement
CORE-04 ile birlikte çalışır (`docs/03` ADR-L04 kritik ayrım).

## Permissions
`module.manage` (yalnız Platform Owner/Admin).

## Entitlement / quota
Bir modülün tenant için enable olması entitlement'a bağlıdır (CORE-04).

## ECA hooks
`ModuleDisabled` → o modülün route/menü/API/job üretimini durduran sistem
kuralı (bu bir ECA kuralı değil, çekirdek davranıştır — ama event ECA'ya da
yayınlanır, izlenebilirlik için).

## AI-off / AI-on davranışı
AI Platform da bir modül olarak bu registry'de kayıtlıdır; kill switch bu
registry üzerinden de tetiklenebilir.

## UX one-click journey
Superadmin panelinde tek tıkla modül enable/disable (`docs/06` Superadmin
System > Modules).

## States
Modül: `not_installed → installed → enabled → disabled → uninstalled`.

## Data retention / export
Disable veri **silmez**; yalnız erişimi durdurur (`docs/03` ADR-L04).

## Observability
Modül health check sonuçları, enable/disable audit trail'i.

## Security / privacy
Bir modülün disable edilmesi diğer modüllerin güvenlik varsayımlarını
bozmamalı (bağımlılık grafiği doğrulaması).

## Accessibility / i18n
Modül yönetim ekranı WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — CORE modülleri sabit kayıtlı; Stage 2 Post-MVP'de optional modül
registry'si tam işlevsel.

## Acceptance
Bir modülün disable edilip verisinin korunduğunun testi; bağımlı modül
kontrolü (bağımlılığı olan bir modül disable edilmeye çalışıldığında uyarı).

## Rollback
Core modül — devre dışı bırakılamaz (registry'nin kendisi).

## Open questions
Semantic compatibility resolver paketi seçilmedi (`docs/16` MOD-01).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Modül enable/disable öncesi bağımlılık/uyumluluk etki açıklaması
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Modül manifest meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Lifecycle geçişi (install/enable/disable) deterministik registry kararıdır
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
