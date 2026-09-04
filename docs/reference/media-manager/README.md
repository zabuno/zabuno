# Medya yöneticisi — KANONİK kaynak

Bu klasördeki iki dosya sahibin verdiği **master data**'dır (2026-09-04):

- `Medya Yonetimi v2.dc.html` — güncel sürüm, dokuz bölüm
- `Medya Yonetimi.dc.html` — birinci sürüm

Sahibin cümlesi: *"media manager, file manager için, frontend UI, burası,
bunlar, media için master data bu kaynaklar. İkilemde kalırsan bu kaynakları
seçeceksin."*

## Neden depoya kopyalandı

Kaynak masaüstünde bir `.zip` içindeydi ve klonla gelmiyordu. Bir önceki
pakette tam olarak bu yüzden bir körlük yaşandı: teslim paketi bir *brief*
sanılıp jetonlar elle yeniden türetildi, oysa paketin içinde çalışan bir
tasarım sistemi vardı (`docs/102` §5o). Kaynak depoda durursa o hata
tekrarlanamaz.

**ELLE DÜZENLENMEZ.** Sahip yeni bir sürüm verirse dosya değiştirilir, farkı
görünür olur.

## Dosya biçimi

`.dc.html` bir React-in-HTML biçimidir: `support.js` React'i CDN'den yükler,
`<sc-if>` / `<sc-for>` etiketleri koşul ve döngüdür, `{{ … }}` bağlamadır.
Yani dosya **çalışan bir arayüzdür**, bir maket değil — ölçüler, boşluklar ve
durum adları oradan birebir okunur.

## Dokuz bölüm

| Bölüm | Ne yapar |
| --- | --- |
| Kütüphane | Klasörler, süzme, sıralama, ızgara/liste, çoklu seçim |
| Yükle | Dört adım: seç → istemcide küçült → çerçevele → yükle |
| Dönüştür | Eski biçimi modern biçime çevirir; **aslı korunur**, dönüşen yeni sürüm olur |
| Görüntüle | PDF ve görsel okuyucu |
| Boyut motoru | Hangi boyutların üretileceğinin KURALI + yeniden üretim işi + ölçülen kazanç |
| Kuyruk | İş kuyruğu: sayaçlar, ilerleme, yeniden dene |
| Kota ve çöp | Kota kartları, "yeri ne dolduruyor", çöp |
| Ayarlar | Klasör/ad/tarih deseni, güvenlik anahtarları |
| Olgunluk | MVP olgunluk puanı ve seviyeler |

Uygulama planı ve depodaki karşılıkları: `docs/108-MEDYA-YONETICISI.md`.
