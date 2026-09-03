# 87 — Yalnız canlıda görünen iki kusur (FF-29)

zabuno.com canlıya alındıktan sonra üretim ilk kez **dışarıdan** kontrol
edildi. Altı paketin göçleri uygulanmış, şema sağlam, hiçbir yerde 500 yok.
Ama iki şey yerelde çalışıp üretimde çalışmıyordu — ikisi de **yalnız canlıda
görülebilecek** cinsten.

## 1. Üretim hangi commit'i sunduğunu söyleyemiyordu

```html
<meta name="zabuno-build-revision" content="">
```

Bu depo `preview-truth` kapısını tam olarak bir soru için kurmuştu:
**"baktığım şey gerçekten yazdığım kod mu?"** Geliştirme checkout'u bir
commit'teyken localhost başkasını sunuyordu ve bunu gösteren hiçbir şey
yoktu; o tur boyunca yapılan her görsel değerlendirme boşa gitmişti.

Üretimde aynı soru cevapsızdı. Deploy `.image.env` dosyasına yalnız imaj
etiketini yazıyor, konteynere hiçbir revizyon geçmiyordu.

İki sonucu vardı:

- Canlı sayfaya bakıp "bu hangi sürüm" diye sorulamıyordu.
- **Geri alma kördü.** Eski bir SHA etiketine dönüldüğünde sayfada
  hangisinin canlı olduğunu söyleyen hiçbir şey yoktu — oysa geri almanın
  tek amacı, doğru sürüme döndüğünden emin olmak.

Revizyon artık akıştan `.image.env`'e, oradan compose ile konteynere
geçiyor. Test **iki ucu da** ölçüyor: yalnız akışa yazmak yetmez, compose'un
da onu aktarması gerekir.

## 2. `robots.txt` üretimde ölüydü

Uygulamanın `ShowRobotsController`'ı var, rotası kayıtlı ve yerelde 200
dönüyor. Üretimde **404**.

Sebep `docker/nginx.conf` içindeki tek satırdı — Laravel'in nginx
şablonundan gelen standart satır:

```nginx
location = /robots.txt  { access_log off; log_not_found off; }
```

Bu **tam eşleşmeli** blokta ne `try_files` var ne de PHP'ye aktarım. nginx
isteği statik dosya olarak arıyor, `public/robots.txt` olmadığı için 404
dönüyor ve istek **Laravel'e hiç ulaşmıyor**.

Yerelde `artisan serve` çalışıyor ve nginx devrede değil — bu yüzden rota
yerelde çalışıyor, üretimde ölü kalıyordu. Hiçbir birim testi yakalayamazdı:
sorun kodda değil, **aktarımdaydı**.

Satır kaldırıldı; istek artık `try_files` ile `index.php`'ye düşüyor.
`favicon.ico` bloğu kaldı, çünkü o **gerçekten** bir statik dosya
(`public/favicon.ico`).

## Neden bu iki kusur birbirine benziyor

İkisi de **koddan değil, kodun taşındığı yoldan** kaynaklanıyordu. Depo bunu
daha önce bir kez yaşamış ve yazmış:

> URL politikası konteynere GEÇMELİ… Bu yerelde tüm yığın çalıştırılırken
> bulundu — birim testleri yakalayamazdı, çünkü sorun kodda değil
> aktarımdaydı.

Aynı sınıf, üçüncü kez. İkisi de artık `DeploymentContractTest` içinde
dondurulmuş ve mutasyonla doğrulanmış: nginx satırını geri koymak ya da
revizyon geçişini kaldırmak kapıyı kırmızıya çeviriyor.

## Bu turda kontrol edilen ve SAĞLAM çıkan şeyler

| Ne | Sonuç |
| --- | --- |
| Altı paketin göçleri canlıda uygulandı mı | ✅ konteyner açılışta `migrate --force` çalıştırıyor |
| Göçler PostgreSQL'de sınandı mı | ✅ CI tüm suiti pgsql'de koşuyor |
| Hüseyin'in deploy dosyalarıyla çakışma | ✅ sıfır dosya kesişimi |
| Canlıda 500 var mı | ✅ `/`, `/login`, `/up`, `/sitemap.xml` 200 |
| Veritabanına dokunan herkese açık yol | ✅ `sitemap.xml` menü tablolarını okuyup dönüyor |
| Tekdüze 404 korunuyor mu | ✅ `/menu/...` ve `/q/...` |

## Ürün iddiası

Çalışır: canlı sayfa hangi commit'i sunduğunu söyler, ve `robots.txt`
uygulamanın kendi politikasını döner.
