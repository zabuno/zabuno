# 127 — Dış kaynak eşlemesi: hangi restoran bizim restoranımız?

**Kapsam:** `docs/116` §6 P7 — `external_references` tablosu ve eşleme
onayı. `docs/116` §1 Ö4 ile §5 D4 kararlarının uygulaması.

**Bu pakette ne YOK:** hiçbir dış API çağrısı, hiçbir adaptör, hiçbir veri
çekme işi. Onlar P8'in işidir ve `docs/116` §5 D2 hâlâ geçerlidir: resmî
API'si olmayan kaynağa adaptör yazılmaz. Bu paket yalnız **eşleme
yüzeyidir** — dış veri gelmeden önce, hangi kaydın bize ait olduğunu
söyleyen defter.

## 1. Somut yolculuk — üç Lezzet Sarayı

Sahip bir gün panelde şunu görecek: *"Google Haritalar'da 'Lezzet Sarayı'
adında bir yer bulduk. Sizin Kadıköy şubeniz mi?"*

Türkiye'de "Lezzet Sarayı" adında **üç** restoran var. Biri Kadıköy'de,
biri Konya'da, biri Adana'da. Üçü de aynı adı taşıyor, üçünün de Google'da
puanı var ve üçü de birbirinden habersiz.

Eşleme tablosu olmasaydı ne olurdu: sistem isim benzerliğine bakar, en yakın
görüneni seçer ve Kadıköy'deki sahibimizin menüsünde **Adana'daki
restoranın 3,1 puanı** görünürdü. Sahip bunu fark ettiğinde bile
düzeltemezdi, çünkü ortada düzeltilecek bir kayıt yoktur — yalnız bir isim
karşılaştırması vardır ve o her gece yeniden çalışır.

Şimdi ne oluyor: makinenin bulduğu şey bir **öneri** olarak yazılıyor.
Öneri, sahip "evet, burası benim restoranım" diyene kadar misafirin gördüğü
hiçbir ekrana ulaşamıyor. Sahip "hayır, bu benim restoranım değil" derse,
o öneri bir daha karşısına çıkmıyor.

Öneri ile onayın farkı tek cümlede budur: **yanlış eşleme, başkasının
puanını bizim restoranımızda göstermektir.**

## 2. Alınan üç karar ve gerekçeleri

### K1 — Güven düzeyi bir cevap değil, bir iddiadır

Her eşleme satırı bir **güven düzeyi** taşır: *düşük* (yalnız isim
benziyor), *orta* (isim ve şehir tutuyor), *yüksek* (isim, adres ve konum
birlikte tutuyor).

Bu üç bant bir yüzdeye çevrilmedi ve bu kasıtlı. "%87 güven" yazan bir
sütun, olmayan bir hassasiyeti gösterir — o sayıyı üretecek ölçülmüş bir
eşleştirici bu depoda **yoktur**. Uydurulmuş bir ondalık, kararı verecek
insanı yanıltır: sahip "%87 ise doğrudur" diye düşünür ve bakmadan onaylar.

**Ve hiçbir bant onayın yerine geçmez.** En yüksek güven bile bir öneridir.
Kural tek bir yerde yaşıyor
(`ExternalReference::mayCarryExternalDataToGuest()`) ve yalnız sahibin
kararına bakıyor; güven düzeyi o karara hiç girmiyor. Bir testte üç bandın
üçü de tek tek deneniyor ve üçünde de kapı kapalı kalıyor.

### K2 — Bir dış kayıt tek bir yere yazılır, ve bu kural kiracıyı aşar

Bir Google yer kimliği **tek bir fiziksel yeri** gösterir. İki farklı
işletmenin ikisinin birden "burası benim" demesi mümkün değildir — biri
mutlaka yanılıyordur.

Bu yüzden "aynı dış kimlik iki varlığa onaylı bağlanamaz" kuralı **bütün
platform genelinde** geçerli. Kiracı sınırının içinde bıraksaydık, komşu
kiracının onayladığı kimliği biz de onaylayabilirdik ve aynı dış puan iki
restoranda birden görünürdü.

Bedeli açık ve kabul edildi: bir sahip, başka bir kiracının önce davrandığı
bir kimliği onaylayamaz. O durumda ekranda şu yazacak: *"bu kayıt başka bir
işletmeye bağlı"* — ve **kimin bağladığı asla söylenmeyecek**. Söylenseydi,
rastgele kimlikler deneyen biri, hangi işletmenin hangi platforma bağlı
olduğunu tek tek haritalayabilirdi.

Simetrik kural da var ve o kiracı içinde kalıyor: bir şube aynı dış sistemde
iki kimliğe onaylı bağlanamaz — iki ayrı yerin puanını tek şubede toplamak
olurdu.

**İkisi de veritabanı düzeyinde.** Uygulama kontrolü ayrıca var ama tek
başına yeterli değil: aynı anda gelen iki onay isteği birbirini görmez ve
tek bir Google kaydı iki restorana birden bağlanırdı. Kontrol sahibe
söylenecek **cümleyi** üretiyor, veritabanı kısıtı **doğruluğu** garanti
ediyor.

### K3 — Reddedilen bir eşleme yarın geri gelmez

Sahip "bu benim restoranım değil" dedikten sonra, eşleştirici yarın aynı
çifti yeniden öneremiyor. Aynı varlık + aynı dış sistem + aynı dış kimlik
ikilisi tabloda **bir kez** yazılabiliyor.

Sebebi basit: geri gelebilseydi verilen cevap hiçbir şey ifade etmezdi.
Sahip her hafta aynı soruyu cevaplar ve bir gün yanlışlıkla "evet" derdi.

Buna karşılık **onaylanmamış öneriler yan yana durabiliyor**: eşleştirici
bir şube için üç aday getirebilir, sahip birini seçer. Kısıt yalnız
*onaylı* satırlar üzerinde; tüm satırlara konsaydı "hangisi doğru?" sorusu
hiç sorulamazdı.

## 3. Ne yazıldı

| Katman | Ne |
| --- | --- |
| Göç | `external_references` — kiracı, varlık türü/kimliği, dış sistem, dış kimlik, dış ad, güven düzeyi, eşleyen, eşlenme anı, sahibin kararı, karar veren, karar anı |
| Kısıtlar | Çift benzersizliği (tüm satırlar) + iki **kısmî** benzersizlik (yalnız onaylı satırlar) |
| Alan modeli | `ExternalSystem`, `ExternalMatchConfidence`, `ExternalMatchedBy`, `ExternalReferenceDecision`, `ExternalReferenceOutcome`, `ExternalReference` |
| Port | `ExternalReferenceRepositoryPort` (yazma), `ExternalReferenceQueryPort` (okuma) |
| Depo | `EloquentExternalReferenceRepository`, `EloquentExternalReferenceQuery` |
| Kullanım | `DecideExternalReference` — onayla / reddet |
| Test | `tests/Unit/Rating/ExternalReferenceTest.php`, `tests/Feature/Rating/ExternalReferenceMappingTest.php` |

**Kiracı kapsamı sorgunun İÇİNDE.** Her okuma ve her yazma `workspace_id`
parametresi ister ve o değer SQL'in `WHERE`'ine girer. Tek istisna
"bu dış kimlik başka bir varlıkta onaylı mı?" kontrolüdür (K2) ve o bile
yalnız *evet/hayır* döner — satırın kendisi dışarı çıkmaz. Kiracı sızıntısı
bu depoda daha önce PostgreSQL'de yakalanmış bir kusur ailesidir; sınırı
çağıranın hatırlamasına bırakmak, bir gün hatırlanmaması demektir.

**`RatingSubject`'e `location` değeri eklendi.** Ö4 zaten *"bizim
varlığımız (şube ya da ürün)"* diyordu; Google Haritalar'daki kayıt bir
tabak değil bir **yerdir** ve şubeyi adlandıramadan bağlayacak bir
varlığımız olmazdı. Bugün hiçbir puan sinyali bu değeri taşımıyor — tek işi
dış kimlik eşlemesi.

## 4. Bugün ne çalışıyor, ne çalışmıyor

**Çalışan:** eşleme yazılabiliyor (otomatik öneri ya da sahibin kendi
girişi), onaylanabiliyor, reddedilebiliyor; onaylanmamış eşleme dış veriyi
misafire taşıyamıyor; çakışan onaylar hem uygulamada hem veritabanında
reddediliyor; komşu kiracının eşlemesi ne görünüyor ne de karar alabiliyor.

**Çalışmayan — ve bu bilinerek böyle:**

- **Sahip bu ekranı henüz göremiyor.** Panelde bir "dış bağlantılar"
  yüzeyi yok. Sebebi dürüst: onaylanacak bir öneri de yok, çünkü öneriyi
  üretecek eşleştirici P8 ile geliyor. Boş bir ekran, var olmayan bir
  yeteneği varmış gibi gösterirdi.
- **Hiçbir dış puan çekilmiyor.** Bu paket bir tek ağ isteği içermiyor.
- **Eşleştirici yok.** Otomatik öneriyi üretecek kod (isim/adres/konum
  karşılaştırması) yazılmadı; bugün yazılan şey, o kod geldiğinde
  önerisinin nereye düşeceği ve nasıl onaylanacağıdır.

## 5. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6: yukarıdaki her karar bugünün ölçümüdür. İkisi yeniden
bakılmayı hak ediyor:

- **Güven düzeyinin üç bant olması**, ölçülmüş bir eşleştirici doğduğunda
  daha ince bir ölçeğe dönüşebilir — ama o gün geldiğinde bile sayı
  ölçülmüş olmalı, tahmin edilmiş değil.
- **Reddin kalıcı olması**, sahip fikrini değiştirdiğinde bir çıkış yolu
  gerektirebilir. Bugünkü cevap "yeni bir eşleme kurulur"dur; sahip bunu
  panelden yapamadığı sürece cevap eksiktir ve P8'in yüzeyiyle birlikte
  yeniden bakılmalıdır.
