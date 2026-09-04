# Toplayıcı — `collector`

**SÖZLEŞME — bu bir agent spesifikasyonudur; kodda karşılığı:
`App\Application\Ai\Batch\MenuBatchCollector` (deterministik) +
`FinishMenuBatch`.** Sahibin sorduğu "collector adlı ajan" budur: LLM
değil, kod — çünkü toplama hatası ölçülebilir olmalı (`docs/adr/ADR-L11`).

## Amaç
40 sayfalık bir menü okumasının sayfa sonuçlarını TEK inceleme listesine
toplamak; yinelenen satırları ayıklamak; düşen sayfaları sebebiyle
listelemek. Restoran sahibi 40 ayrı sonuç yerine bir liste görür.

## Tetikleyici
Bir partinin son sayfası terminal (done|failed) olduğunda, o sayfanın işi
`FinishMenuBatch::handle()` çağırır. Zamanlayıcı yok.

## Hafıza
- Kalıcı: `ai_batches.collector_summary` (satırlar, artifactIds,
  duplicatesSkipped, failedPages) — yazan: `FinishMenuBatch`.
- Geçici: yok (toplayıcı durumsuzdur).

## Hesap ve bütçe
Sağlayıcı çağrısı YAPMAZ. Bütçe tüketmez.

## İzin verilen
- `ai_artifacts.fields` okumak; `kategori|ürün` anahtarıyla yineleme saymak
- `ai_batches` özetini yazmak; parti durumunu `collected`/`failed` yapmak

## Yasak
- Menüye, ürüne, kategoriye YAZMAK (uygulama insan onaylı `apply`'dadır)
- Artifact içeriğini değiştirmek (sadece toplar, düzeltmez)
- Yinelenen satırı SİLMEK (sayar ve atlar; artifact durur)

## İnsan onayı
Zorunlu — özet `MenuCatalogWorkspace` inceleme listesinde gösterilir; sahip
"Ekle" der, `ai-imports/batch/apply` çalışır.

## Kanıt
`tests/Feature/Ai/MenuBatchOrchestraTest.php` (yineleme ayıklama, kısmi
başarısızlık, parti kapanışı).
