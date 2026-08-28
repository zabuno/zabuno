# 76 — Medya işleme gerçekten çalışır (P0-08)

## Önce ne oluyordu

Sahip telefonuyla çektiği yemek fotoğrafını yüklüyor. Yükleme başarılı
görünüyor. Sonra hiçbir şey olmuyor.

Sebep tek satırdı:

```php
$this->app->bind(MediaAssetProcessorPort::class, UnavailableMediaAssetProcessor::class);
```

Üretimde bağlı olan tek işleyici her zaman **"belirsiz"** diyordu. Belirsiz
sonuç terminal değildir, dolayısıyla dosya `processing` durumunda sonsuza
kadar bekliyordu. Ekranda ne bir ilerleme, ne bir hata, ne bir sebep.

Bu, "güvenli varsayılan" değildi. Sahte bir başarı üretmemek doğru bir
kaygıydı — ama ürün canlıya çıkarken yüklenen her fotoğrafı sessizce yutan
bir varsayılan, **sessizce bozuk** olmaktır.

## Şimdi ne oluyor

Yüklenen görsel gerçekten işleniyor: slotun istediği genişliklerde türevler
üretiliyor, diske yazılıyor, `ready` oluyor ve değişmez bir adresten
sunuluyor.

```
kebap.jpg (1200×1200)  →  320w  480w  640w  960w   (itemImage, 1:1 kırpma)
```

## Neden GD

PHP ile birlikte gelir: geliştirme makinesinde, CI'da ve sunucuda ekstra bir
eklenti kurulumu istemez. Imagick daha yeteneklidir ama her ortamda yoktur —
ve *bazı ortamlarda çalışan* bir işleyici, hiç çalışmayan bir işleyicinin
daha sinsi hâlidir.

GD yoksa ürün ölümcül hata vermez: yer tutucu işleyiciye düşer ve orada da
dürüstçe "işleyemiyorum" der.

## Dört karar

### 1. Büyütme yasak

Slot 320/480/640/960 istiyor ama kaynak 500 px ise üretilen set
**320 / 480 / 500** olur. 500'den 960 üretmek bilgi eklemez; menüde bulanık
bir fotoğraf yaratır ve sahip nedenini anlamaz.

### 2. Saydamlık slota göre

`logo` slotu `transparency: preserve` — alfa düzleştirilirse logo koyu
zeminde beyaz bir kutu içinde görünür. Bu yüzden logo **PNG** üretir
(kayıpsız, alfa kenarları temiz). Diğer her yerde **WebP**: aynı görünürlükte
belirgin biçimde küçük — misafirin mobil verisi bizim tercihimiz değil, onun
faturası. WebP yoksa JPEG'e düşer, ürün durmaz.

### 3. Kırpma ortadan

Slotun oranı sabitse (`itemImage` 1:1) kaynak **merkezden** kırpılır. Yemek
fotoğraflarında konu neredeyse her zaman ortadadır; kenardan kırpmak tabağın
yarısını keser.

### 4. Başarısızlık okunabilir

Her deneme bir `media_processing_jobs` satırı bırakır ve satır **sonucundan
önce** yazılır: süreç ortasında ölen bir iş de görünür kalmalı, yoksa sahip
"hiç denenmedi" ile "denendi ve çöktü" arasındaki farkı göremez.

Sebep cümleleri sahibin okuyacağı cümlelerdir. HEIC özellikle ayrı ele
alınır, çünkü iPhone'un varsayılan biçimidir ve sık karşılaşılacaktır:

> Bu fotoğraf HEIC biçiminde ve sunucu onu okuyamıyor. Telefonunuzun kamera
> ayarlarından "En Uyumlu" (JPEG) seçeneğini kullanabilir veya fotoğrafı
> JPEG olarak paylaşıp yeniden yükleyebilirsiniz.

Genel bir "desteklenmeyen dosya" cümlesi burada işe yaramaz: sahip ne
yapacağını bilemez.

## Değişmez adres

```
/media/r/{id}-{sağlama-toplamının-ilk-32-hanesi}.{biçim}
```

Adres içeriğin parmak izini taşır. İki sonuç:

1. İçerik değişirse adres de değişir → tarayıcı bunu bir yıl `immutable`
   olarak saklayabilir, misafir aynı fotoğrafı bir daha indirmez.
2. Adresler sayılarak taranamaz: kimliği bilmek yetmez.

Karşılaştırma sabit sürelidir; adres kamuya açık olsa da bayt bayt tahmin
ettiren bir yan kanal bırakılmaz.

## Tarayıcı dürüstlüğü (kriter 3)

Virüs tarayıcı çalışmıyorsa dosya **ilerletilmez** ve ürün bunu "tarandı"
gibi göstermez. Tarama denemesi `held` durumuyla kaydedilir ve sebebi
sahibin listesinde `statusReason` alanında görünür:

> Virüs taraması bu ortamda çalışmıyor; dosya taranmadan yayına alınmaz.

`held`, `failed`ten **ayrı** bir durumdur: dosyada bir sorun bulunmadı,
tarayıcı konuşamadı. İkisini aynı kelimeyle anlatmak sahibi "dosyam bozuk"
sanmaya iter.

Sorunsuz bir dosyaya sebep yazılmaz — sahip her satırda bir açıklama görmeye
başlarsa gerçek uyarıyı okumaz.

## Silme etkisi (kriter 4)

Yayınlanmış bir menüde kullanılan görsel silinemez; istek `409` ile döner:

> Bu görsel yayınlanmış bir menüde kullanılıyor. Önce menüden kaldırıp
> yeniden yayınlayın, sonra silin.

Yayın, sahibin onayladığı donmuş hâldir; panelden yapılan bir temizlik onu
misafirin gözü önünde bozamaz. Kullanılmayan görsel normal biçimde silinir.

`media_usages` satırlarını **yazan** taraf henüz yok — ürün görselini bir
menü öğesine bağlamak **P0-04**'ün işi. Bu paket koruma tarafını kurar ve
testle dondurur; bağlama gelince koruma zaten yerinde olacak.

## Kanıt

`tests/Feature/Media/MediaRenditionPipelineTest.php` (7),
`tests/Feature/Media/MediaHonestyAndDeletionTest.php` (4)

| Requirement | Ne donduruluyor |
| --- | --- |
| `MEDIA-RENDITION-SET-01` | Slot genişliklerinin tamamı üretilir, dosyalar diske yazılır, sürüm açılır |
| `MEDIA-NO-UPSCALE-01` | Kaynaktan büyük türev icat edilmez |
| `MEDIA-FAILURE-VISIBLE-01` | Bozuk dosya görünür biçimde başarısız olur, sebebi dolar |
| `MEDIA-UNDECODABLE-SAYS-SO-01` | HEIC sessizce beklemez, ne yapılacağını söyler |
| `MEDIA-TRANSPARENCY-PRESERVE-01` | Logonun saydam köşesi saydam kalır |
| `MEDIA-SERVE-IMMUTABLE-01` | Değişmez adres; yanlış parmak izi 404 |
| `MEDIA-SCANNER-HONEST-01` | Taranamayan dosya kabul edilmiş gibi ilerletilmez ve sebebi görünür |
| `MEDIA-DELETE-IMPACT-01` | Yayındaki görsel silinemez, ret açıklamalı |
| `MEDIA-DELETE-UNUSED-OK-01` | Kullanılmayan görsel silinebilir |

## Değişen eski sözleşme

`MediaUploadProcessingJourneyTest` eskiden üretim bağlamasının yer tutucu
**olmasını** donduruyordu. O gün doğruydu: gerçek bir işleyici yoktu.
Zamanla anlamı değişti. Test güncellendi; yer tutucunun dürüst bekleme
davranışı ayrı bir testle korunuyor.

## Ürün iddiası

Çalışır: sahip fotoğraf yükler, türevleri üretilir, değişmez bir adresten
sunulur; başarısızlık okunabilir bir cümleyle döner.
Çalışmaz: görsel henüz bir ürüne **bağlanamıyor** ve menüde görünmüyor —
bağlama P0-04'ün işi. HEIC çözülemiyor; ürün bunu söylüyor.
