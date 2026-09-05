# OPT-29 — Native App Shell (Capacitor)

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

**Amaç**: Restoran personeli için Capacitor tabanlı native mobil shell
sağlamak (`docs/15` §5).
**Bounded context**: Mevcut React panel kodunu Capacitor ile sarmalayan bir
dağıtım katmanı — **yeni bir UI stack'i değildir**, aynı React kodu native
shell içinde çalışır.
**Owner**: Engineering. **Sınıf**: Optional (Growth Stage).
**Bağımlılıklar**: Restaurant Admin panel (React), CORE-14 (push notification).
**Public contracts/events**: `NativeAppInstalled`, `PushTokenRegistered`.
**Tenant isolation**: Panel ile aynı (native shell yalnız taşıyıcıdır).
**Permissions**: Panel izinleriyle aynı.
**Entitlement**: Growth+ edition.
**ECA hooks**: Push notification ECA action'ı olarak kullanılabilir.
**AI-off/on**: AI'dan bağımsız.
**UX**: App Store/Play Store'dan indirme, ilk açılışta workspace girişi.
**States**: Yok.
**Retention**: Push token'lar kullanıcı çıkışında temizlenir.
**Observability**: Install sayısı, push teslim oranı.
**Security**: Secure storage (native keychain) kullanımı zorunlu.
**A11y/i18n**: Native shell içindeki React kodu aynı WCAG 2.2 AA standardına
tabi.
**Phase delivery**: Growth Stage (`docs/22`).
**Acceptance**: Push notification'ın gerçek cihazda teslim edildiğinin testi;
offline draft/sync senaryosunun veri kaybetmediğinin testi.
**Rollback**: Native app kaldırılsa bile web/PWA her zaman çalışır (`docs/15`
§5 — web fallback garantisi).
**Open questions**: Apple onayı garanti edilemez (`docs/16` APP-01).


## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` ve
`templates/AI-CAPABILITY-MANIFEST.md`.

- **deterministic_baseline**: required
- **ai_posture**: none — istisnai, gerekçeli
- **Optional AI use case(ler)**: — (bkz. gerekçe)
- **AI-off / no-credit deterministic path**: Bu modül zaten AI kullanmaz;
  davranışı AI plane durumundan tamamen bağımsızdır.
- **Data classification**: —
- **Allowed tools/side effects**: —
- **Forbidden authority (final-authority)**: Bu modül saf paketleme/shell katmanıdır — kullanıcıya sunulan içerik/karar yüzeyi yok, bu yüzden hiçbir AI kabiliyeti anlamlı değildir (istisnai ve gerekçeli none)
- **Human approval**: N/A
- **Feature policy**: Bu modülün AI plane'e bağımlılığı yok.
- **Budget/credit behavior**: N/A
- **Eval/audit**: N/A
- **Phase**: Bkz. `docs/32` ilgili tablo satırı.
