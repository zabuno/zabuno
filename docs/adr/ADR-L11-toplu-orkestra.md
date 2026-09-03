# ADR-L11: Toplu AI işi bir iş boru hattıdır — ajan sürüsü değil

**Durum**: Kabul edildi
**Sınıf**: kanıtlanmış (Laravel kuyruk + veritabanı durum tablosu; `docs/28`)
**Tarih**: 2026-09-04
**Paket**: `docs/98` FF-75 (sahibin 2026-09-04 sorusu: "multi agents, multi
skills, ADR, kalıcı/geçici hafıza, hesaplar arası parçalama, collector")

## Bağlam

Bir restoran 40 sayfalık basılı menüsünün fotoğraflarını yükler. Bugünkü
toplu okuma (`ai-imports/batch`, FF-61) en çok 10 fotoğrafı **aynı istekte,
eşzamanlı** okur; 40 sayfa tek isteğe sığmaz, sığsa da sağlayıcının
dakikalık limitini tek kiracı tüketir ve hata bir sayfada değil bütün
istekte görünür. Sahip sorusu: bunu "ajanlar, skiller, hafızalar,
hesaplar arası dağıtım ve bir toplayıcı ajan" ile mi kurmalıyız?

## Değerlendirilen alternatifler

1. **Ajan sürüsü** (her sayfa için bir LLM ajanı, bir "collector ajanı"
   sonuçları birleştirir, ajanlar arası mesajlaşma) — artı: sahibin
   zihnindeki resme birebir; eksi: deterministik olmayan orkestrasyon,
   test edilemez akış, her ajan bir prompt yüzeyi (enjeksiyon alanı
   büyür), maliyet iki kat (toplayıcı da LLM), `docs/32` "agentic_guarded"
   duruşuna aykırı.
2. **Tek büyük istek** (40 görseli tek çağrıya koy) — artı: basit; eksi:
   sağlayıcı sınırı, tek hata = tümü kaybolur, ilerleme yok.
3. **İş boru hattı** (seçilen) — sayfa başına kuyruk işi, parti ve sayfa
   durumu veritabanında, toplayıcı **deterministik kod**, uygulama mevcut
   insan-onaylı `apply` — artı: test edilebilir, kısmi başarısızlık
   toleranslı, dakikalık bütçe uygulanabilir, hafıza modeli net; eksi:
   sahibin "ajan" kelimesiyle birebir değil — bu ADR o farkı açıklar.

## Karar

Toplu AI işi bir **iş boru hattıdır**: `ai_batches`/`ai_batch_pages`
(kalıcı hafıza) + kuyrukta sayfa başına iş (geçici hafıza) + kiracı başına
dakikalık bütçe + amaç-bazlı hesap yönlendirmesi (`purpose=batch`) +
deterministik toplayıcı + mevcut insan onaylı uygulama.

## Gerekçe

- **Hafıza:** "kalıcı" = veritabanı satırı (parti, sayfa, artifact, sebep);
  "geçici" = kuyruk işi (biter, kaybolur). Ajan belleği diye ayrı bir şey
  yoktur; olsaydı iki yerde iki gerçek olurdu.
- **Hesaplar arası dağıtım:** yapışkanlığa "amaç" boyutu eklendi
  (`docs/97` R30, Faz 5'ten öne). Toplu trafik `purpose=batch` etiketli
  bağlantıya yapışır; etiket yoksa etkileşimli sırayla çalışır. Kota aşmak
  için hesap değiştirme yine YASAK (`skills/ai-account-routing.md`).
- **Limit şişmesin:** `RateLimiter('ai-batch')` kiracı başına dakikada N
  sayfa; sınır aşılınca iş atılmaz, sonraki dakikaya kalır.
- **Collector:** LLM değil, kod (`MenuBatchCollector`): sayfa sonuçlarını
  tek listeye toplar, `kategori|ürün` anahtarıyla yinelenenleri sayar ve
  atlar, düşen sayfaları sebebiyle listeler. Deterministik olduğu için
  testi vardır; LLM olsaydı toplama hatası ölçülemezdi.
- **Onay:** toplayıcı yazmaz. Sonuç `ai-imports/batch/apply`'a gider —
  sahip görür, düzenler, onaylar (`docs/47` Kural 10).

## Sonuçlar

Kolaylaşan: 40+ sayfa; ilerleme (`done/total`); sayfa düzeyinde hata;
farklı hesaba izolasyon; yeniden deneme (sayfa başına). Zorlaşan: kuyruk
çalışanı gerekir (`php artisan queue:work`; testte `sync`); sahibin "ajan"
beklentisi belge ile karşılanır (`agents/collector.md`), kodda ajan yok.

## Kanıt

`tests/Feature/Ai/MenuBatchOrchestraTest.php` (parti kapanışı, kısmi
başarısızlık, yineleme ayıklama, tenant izolasyonu, amaç yönlendirmesi);
`tests/Feature/Ai/BatchPurposeRoutingTest.php`.

## İlişkili

`docs/95` Faz 5 (öne çekilen madde), `docs/96` Agents, `docs/97` R30,
`docs/98` FF-75, `skills/ai-account-routing.md`.

## Geri alma

Uçlar (`ai-batches`) kaldırılır, tablolar düşürülür, `purpose` sütunu
`interactive`'e sabitlenir; etkileşimli 10-fotoğraf yolu hiç değişmedi.
