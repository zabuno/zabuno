# CORE-15 — Data Lifecycle

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
Export, arşivleme, soft delete, retention ve purge politikalarını merkezi
olarak yönetmek.

## Bounded context
Veri yaşam döngüsü altyapısı. Hangi verinin ne kadar saklanacağı ilgili modül
sahibinde tanımlanır, bu modül **uygular**.

## Owner
Engineering + Owner (retention politikası kararları için).

## Sınıf
Core.

## Bağımlılıklar
CORE-02, CORE-07 (silme/export işlemleri audit'e yazılır).

## Public contracts / events
`DataLifecyclePort::export/archive/purge(entity)`; `ExportRequested`,
`PurgeScheduled`, `PurgeCompleted` event'leri.

## Tenant isolation
Export/purge işlemleri tenant-scoped.

## Permissions
`data.export`, `data.purge` (yüksek yetki, audit zorunlu).

## Entitlement / quota
Export sıklığı/boyutu plana göre kısıtlanabilir.

## ECA hooks
`PurgeScheduled` → bildirim tetikleyebilir (kullanıcıya "veriniz N gün sonra
silinecek" uyarısı).

## AI-off / AI-on davranışı
AI'dan bağımsız.

## UX one-click journey
"Verilerimi indir" tek tık export talebi (`docs/06` panel Veri ve Gizlilik).

## States
Kayıt: `active → soft_deleted → archived → purged`.

## Data retention / export
Bu modülün **kendisi** bu kavramın altyapısıdır.

## Observability
Bekleyen purge job sayısı, export talep işlem süresi.

## Security / privacy
Purge işlemi geri alınamaz — çift onay gerekir (destructive action dialog,
`docs/06` §5).

## Accessibility / i18n
Export/silme akışı WCAG 2.2 AA.

## Phase delivery
Stage 1 MVP — temel soft delete + export; Stage 2/3'te tam retention/purge
otomasyonu.

## Acceptance
Purge sonrası verinin gerçekten erişilemez olduğunun testi; soft-delete'in
CORE-07'de doğru loglandığının testi.

## Rollback
Core modül — devre dışı bırakılamaz. Purge işleminin kendisi geri alınamaz
(rollback yalnızca job'un *tetiklenmesini* iptal edebilir, tamamlanmış purge'ü
değil).

## Open questions
Hesap silme sonrası mali/teknik log saklama süresi netleşmedi (`docs/16` DATA-01).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: advisory
- **Optional AI use case(ler)**: Retention/export/purge işleminin etki açıklaması ("bu purge şu N kaydı, şu bağımlı modülleri etkiler")
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Retention politika meta verisi
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Purge kararının kendisi asla AI'ya devredilmez — yalnız etki açıklaması sunar, tetiklemez
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
