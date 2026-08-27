# Medya motoru kaynak şartnamesi (depo dışından alındı)

**Kaynak:** `~/DEV/zabuno/imageoptimization-main` — istoc Media Engine
geliştirme yönergesi. 15 faz, 102 atomik görev.

**Neden burada:** `docs/36`'da külliyat için uygulanan yöntemin aynısı —
kararlar depoda kalsın, kaynak aktarılmasa bile.

**Uyarı:** Bu belgeler **Frappe app + Vue 3** için yazılmıştır. Alan bilgisi
(slot politikaları, boru hattı, değişmezler, veri modeli) yığın-bağımsızdır
ve alınmıştır; teknoloji seçimleri alınmamıştır.

| Kaynaktaki | Zabuno'daki karşılığı |
| --- | --- |
| pyvips (Python) | `ext-vips` / `ext-imagick` (PHP) — `ext-gd` yetmez |
| Frappe DocType | Laravel migration + repository port |
| Vue 3 headless | React 19 |
| Frappe queue | Laravel queue |

**Kararların özeti ve fazlanmış plan:**
`docs/49-MEDYA-VE-DOSYA-YONETIMI-PLANI.md`

| Dosya | İçerik |
| --- | --- |
| `30-faz2-medya-standartlari.html` | Slot kataloğu, DPI kuralı, profil matrisi. **"BELİRLENECEK" slotlar blokedir** |
| `41-faz4-veri-modeli.html` | Asset / Source / Version / Rendition / Crop Intent / Profile / Policy / Job / Usage / Quality Report |
| `50-faz6-image-engine.html` | `PROBE → GUARD → … → REPORT` boru hattı ve INV-01..07 |
| `51-faz7-video-engine.html` | Video profilleri |
| `61-faz10-crop-studio.html` | Crop arayüzü |
| `62-faz11-simulator.html` | Cihaz × yerleşim simülatörü |
