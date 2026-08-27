# istoc Media Engine — Geliştirme Yönergesi

Frappe app + Vue 3 headless frontend için kurumsal seviye medya kütüphanesi ve görsel/video optimizasyon motorunun waterfall şartname ve görev dokümantasyonu.

**Canlı doküman:** https://karacaismail.github.io/imageoptimization/

- 15 faz, 102 atomik geliştirici görevi; her görevde kabul kriterleri ve kopyalanabilir geliştirici prompt'u
- Çalışan crop & preview simülatörü: 13 cihaz × 5 sayfa yerleşimi, odak noktası, srcset seçim göstergesi
- Tek dosyalık çevrimdışı sürüm: `docs/yonerge/index.html`

## Yapı

| Yol | İçerik |
|---|---|
| `docs/index.html` | Ana doküman (sol menü, tüm bölümler) |
| `docs/simulator.html` | Canlı crop & preview simülatörü |
| `docs/91-gorev-panosu.html` | Filtrelenebilir görev panosu |
| `docs/yonerge/build.py` | Tek dosyalık sürümü üreten betik |
| `gorseller/` | Simülatörün kullandığı örnek ürün görselleri |
