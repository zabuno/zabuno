# CORE-13 — File / Media

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
Dosya yükleme, doğrulama, depolama ve derivative (türev) üretim pipeline'ının
CORE altyapısını sağlamak.

## Bounded context
Depolama + işleme altyapısı. Slot/recipe tanımları tema/modül sahibindedir
(`docs/07` §3).

## Owner
Engineering.

## Sınıf
Core.

## Bağımlılıklar
CORE-02 (tenant-scoped storage quota için), CORE-04 (storage kotası).

## Public contracts / events
`MediaPort::upload/derive(recipe)`; event'ler (fail-closed sırayla, `docs/07`
§5): `AssetUploaded` (yalnız durable **quarantined intake** anlamına gelir —
bytes private/non-executable/tenant-scoped quarantine storage'a yazıldı;
**hiçbir decode/optimize/derivative işlemini tetiklemez**), `AssetRejected`
(intake-validation veya security-scan reddetti — terminal), `AssetSecurityScanPassed`
/ `AssetAccepted` (malware scan geçti — bounded/sandboxed decode ve
işleme'yi başlatan **tek** tetikleyici budur), `DerivativeGenerated`,
`AssetDeleted`.

## Tenant isolation
Her asset tenant-scoped; storage path tenant id içerir.

## Permissions
`media.upload`, `media.delete`, `media.manage`.

## Entitlement / quota
Storage kotası CORE-04 üzerinden kontrol edilir.

## ECA hooks
`AssetUploaded` **hiçbir ECA kuralını tetiklemez** — yalnız quarantine
intake'in durable olarak kaydedildiğini bildirir, optimize/derivation
**değildir** ve öyle kullanılamaz. Otomatik optimize etme kuralı **yalnız**
`AssetSecurityScanPassed`/`AssetAccepted` event'ine bağlanabilir (`docs/07`
§5 fail-closed sırası). `AssetRejected` → bildirim/manuel-review kuralı
tetiklenebilir, hiçbir işleme kuralı tetiklemez.

## AI-off / AI-on davranışı
AI destekli görsel etiketleme (ileri özellik) opsiyoneldir, çekirdek pipeline
AI'dan bağımsız çalışır.

## UX one-click journey
Sürükle-bırak upload → (arka planda: quarantine → validate → scan, kullanıcı
"taranıyor" durumunu görür) → scan geçince otomatik optimize → tek tık
kullanım (`docs/07` §7). Scan tamamlanmadan hiçbir önizleme/kullanım
"hazır" olarak sunulmaz.

## States
Asset: `quarantined (intake) → validating → scanning → accepted →
processing → ready`, terminal/exception dallar: `rejected` (validating veya
scanning reddetti — quarantine'den asla çıkmaz), `failed` (accepted sonrası
processing hatası). Sıra **fail-closed**tır (`docs/07` §5): `processing`
hiçbir koşulda `scanning`/`accepted`'dan **önce** gelmez; bir asset scan'den
geçmeden (`accepted` durumuna ulaşmadan) `processing`/`ready`'e **geçemez**;
scanner eksik/kullanılamaz/timeout/belirsiz sonuç verirse asset
`quarantined`/`validating`/`scanning`'de kalır (safe quarantine, otomatik
`accepted`/`ready`'e düşmez).

## Data retention / export
Silinen asset'ler CORE-15 retention politikasına tabi (soft delete).

## Observability
İşleme hata oranı, ortalama işleme süresi, kapasite-degradation olayları
(`docs/15` §4).

## Security / privacy
Fail-closed intake sırası (`docs/07` §5): quarantine intake → intake-validation
(auth/quota, byte-size, magic/MIME, decode gerektirmez) → security-scan
(malware scan, hâlâ quarantine'de) → yalnız `AssetSecurityScanPassed`/
`AssetAccepted` sonrası bounded/sandboxed decode + decompression-bomb kontrolü
+ EXIF orient/strip + crop + optimize/encode. MIME/magic-byte doğrulama
decode'dan **önce** çalışır; SVG sanitize scan'den **sonra**, release'den
**önce** uygulanır. Malware scan'in geçmesi decode'u risksiz **yapmaz** —
decode her zaman bounded/sandboxed'dır. Derivative görünürlüğü kaynağın yayın
durumundan **inherit** edilir — draft/private asset türevleri private/signed
URL, yalnız published public slot derivative'i public (`docs/07` §2, tenant
authorization ile birlikte). Taranmamış (unscanned) hiçbir asset public/
published olamaz; scanner eksik/kullanılamaz/timeout/belirsiz sonuç verirse
asset quarantine zincirinde kalır (`validating`/`scanning`), hard-fail yerine
safe quarantine + manuel/harici scanner rotası uygulanır — bu, §Phase
delivery'deki genel "zarif degradation" ilkesinin **istisnasıdır** (güvenlik
asla degrade edilmez).

## Accessibility / i18n
Alt-text zorunlu alan (erişilebilirlik).

## Phase delivery
Stage 1 MVP — temel upload + resize **+ quarantine/security-scan zorunluluğu**
(güvenlik MVP'den itibaren geçerlidir, ertelenmez); Stage 2'de tam
fingerprint/idempotence pipeline (`docs/07` §2).

## Acceptance
Fingerprint idempotence testi (aynı girdi iki kez işlenmez); güvenlik pipeline
golden-file testleri; **fail-closed sıralama testi** — `AssetUploaded`'ın
tek başına hiçbir decode/optimize/derivative işlemi tetiklemediğinin,
`processing`'in `accepted`'dan (scan geçişinden) önce **hiçbir koşulda**
başlamadığının doğrulaması; quarantine/security-scan testi (taranmamış bir
asset'in public/published slot'a **asla** bağlanamadığının, scanner eksik/
kullanılamaz/timeout/belirsiz durumunda asset'in quarantine zincirinde
kaldığının doğrulaması, `docs/16` MED-03); derivative kalıtımlı görünürlük
testi (draft asset türevinin signed URL olmadan erişilemez olduğunun,
yalnız publish sonrası public olduğunun testi).

## Rollback
Core modül — devre dışı bırakılamaz.

## Open questions
ICC profil politikası netleşmedi (`docs/16` MED-02); host capability bilinmiyor
(`docs/16` MED-01); local/harici malware-scan adaptörü seçimi ve shared-host
kullanılabilirliği/maliyeti/gizliliği netleşmedi (`docs/16` MED-03).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required — AI kapalıyken/sıfır krediyle bu
  modülün ana işlevi eksiksiz çalışır, veri kaybı olmaz.
- **ai_posture**: automated_guarded
- **Optional AI use case(ler)**: Otomatik alt-text taslağı ve moderasyon bayrağı (görünür "AI-generated" etiketiyle, tek tık geri alınabilir)
- **AI-off / no-credit deterministic path**: AI kill switch aktifken, sıfır
  iç kredi/provider kredisi yokken, quota/429/outage/residency-denial/
  safety-block/invalid-schema durumlarında bu modülün AI-destekli önerisi
  görünmez/pasif olur; kullanıcı girdisi/taslağı korunur, modülün temel işlevi
  manuel/deterministik olarak tam çalışmaya devam eder.
- **Data classification**: Görsel/video meta verisi + görsel içeriğin kendisi (moderasyon amaçlı, redaction politikasına tabi)
- **Allowed tools/side effects**: Yalnız yukarıdaki opsiyonel kullanım
  örneğiyle sınırlı öneri/taslak/açıklama üretimi; `docs/14` §3 tool-allowlist
  dışına çıkmaz.
- **Forbidden authority (final-authority)**: Dosya silme/yayına alma kararı AI'ya devredilmez; moderasyon bayrağı yalnız işaretler, otomatik silmez
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
