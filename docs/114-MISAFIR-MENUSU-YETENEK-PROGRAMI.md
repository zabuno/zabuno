# 114 — Misafir menüsü yetenek programı: eksik yirmi sekiz bileşen

**Sahibin bildirimi (2026-09-05):** *"Benim verdiğim HTML sadece UI, yani
arkaplan ve backend geliştirmek ve headless olarak entegre etmek senin
işin… Dolayısıyla söylediğim herşeyi istiyorum."*

Bu belge `docs/113`'ün devamıdır ve onun "YOK" saydığı bileşenlerin
**yapılacağını** kayda geçirir. `docs/113` bir envanterdi; bu bir programdır.

## 0. Kapsam düzeltmesi — neden ayrı bir belge

`docs/113` kırk iki bileşenin yirmi sekizini "YOK" saydı ve bunu bir sınır
gibi okudu. **Yanlış okumaydı.** Kaynak bir arayüz tasarımıdır; arkasını
kurmak bu ekibin işidir. Bir bileşenin bugün karşılığı olmaması, o
bileşenin kapsam dışı olduğu anlamına gelmez — yalnız **henüz
yazılmadığı** anlamına gelir.

Ayrım önemli, çünkü bu depoda "yok" cümlesi bir kez yazıldığında kimse
geri dönüp bakmıyor (`docs/109` §8.6, §8.7). Bu belge o cümlelerin
karşısına tarih koyuyor.

**İki istisna gerçekten sınırdır ve program onları taşımaz:**

| Sınır | Neden kapsam kararı değil |
| --- | --- |
| "Vegan sertifikalı", "alerjensiz" gibi **iddialar** | Yanlış bir alerjen iddiası bir sağlık olayıdır. Filtre gelir, iddia gelmez: ürün "bu üründe fıstık YOK" diyemez, "restoran fıstık BİLDİRMEDİ" der. |
| Ölçüm onayı olmadan üçüncü taraf script | Hukuki, ve kapısı bu oturumda kuruldu |

Mikrofon kısıtı bir sınır **değildir**: kendi güvenlik başlığımızda kapalı
ve sesli arama paketinde açılacak.

## 1. Yönetici ilke — headless

Kaynak bir arayüzdür; ürün bir **API + o API'yi tüketen arayüzdür**. Her
yetenek şu sırayla kurulur ve sırası tersine çevrilemez:

1. Alan modeli ve göç
2. Uygulama katmanı (port + kullanım senaryosu)
3. Uç nokta (kiracı sınırı sorgunun içinde, hız sınırı, yetki)
4. Ölçüm olayı (`docs/112` taksonomisine EKLENEREK — serbest dize yok)
5. Ekran

Ekranı önce yazmak bu depoda bir kez yapıldı ve maliyeti ölçüldü: "çalışıyor
görünen ama arkası olmayan yüzey" ailesi (`docs/109` §8.7).

## 2. Fiyat kademesi ilkesi

Sahibin cümlesi: *"Anonim kullanıcı frontend tarafında resimleri
görmeyebilir, veya pricing table'da üst pakete geçerse görebilir."*

Yani **misafirin gördüğü şey, restoranın planına bağlıdır.** Bu bugün
uygulanmıyor: misafir yüzeyi plan tanımıyor.

**Kural — pazarlık edilemez:** kademe bir yeteneği **açar**, temel
yolculuğu **kapatmaz**. Hangi planda olursa olsun misafir menüyü görür,
fiyatı okur, alerjeni öğrenir. Kapanan şey süs değil ama yaşamsal da
değildir. Bu kural depoda zaten yazılı (`Entitlement`: "ek yetki verir,
temel yolculuğu kapatmaz") ve bir test onu donduruyor.

**Bugünkü haklar üç tane** (`qr.bulk-generation`, `team.invitations`,
`analytics.reporting`) ve plan kademeleri **Starter · Restaurant · Team**.

> **UYARI — plan yönetimi hakları SERBEST DİZE kabul ediyor ve tanımadığını
> SESSİZCE yok sayıyor** (`StoreManagedPlanRequest`). Yeni hak eklenirken
> doğrulama enum'a bağlanmalı; yoksa ilk yazım hatasında sessizce hiçbir şey
> açmaz ve kimse fark etmez.

## 3. Program — yetenekler, sırası ve gerekçesi

Sıra iki ölçüte göre: **bağımlılık** (neyin neye dayandığı) ve **misafire
değer** (masadaki insan için ne değişiyor).

### Dalga 1 — Marka kimliği ve okunabilirlik

| Yetenek | Ne gerekiyor | Hak |
| --- | --- | --- |
| Marka tonu + türetilen rampa | Kontrast ölçümü, yayına dondurma | `branding.custom` |
| Biçim ekseni (variant) | Mevcut AEP katmanı tüketilir | `branding.custom` |
| Fotoğraf anahtarı | Misafir tercihi, sunucu tarafı yok | — |

Önce burası, çünkü **her sonraki bileşen bu token'ları kullanacak.** Sonra
kurulursa hepsi yeniden boyanır.

### Dalga 2 — Bulmak

| Yetenek | Ne gerekiyor | Hak |
| --- | --- | --- |
| Arama | Sunucu tarafı arama ucu; bugün misafir araması yalnız istemcide | — |
| **Sesli arama** | Tarayıcı konuşma tanıma + `SecurityHeaders`'ta mikrofon iznini AÇMAK | — |
| Filtreler | Alerjen/kategori/fiyat ekseni; alerjen verisi ZATEN var | — |
| Kategori gezinme | Var, kaynağın diline uydurulacak | — |

**Sesli arama tarayıcıda çalışır, sunucuya ses göndermez.** Bu bir tasarım
kararıdır: ses kaydı kişisel veridir ve onu sunucuya taşımak, çözdüğünden
çok sorun getirirdi. Tarayıcı metni üretir, ürün metni arar.

Mikrofon izni yalnız **kullanıcı düğmeye bastığında** istenir; sayfa
açılışında istenmez.

### Dalga 3 — Favoriler

| Yetenek | Ne gerekiyor | Hak |
| --- | --- | --- |
| Favori işaretleme | Misafir ANONİM — depolama kararı aşağıda | — |

**Anonim misafirin favorisi nerede yaşar?** Üç seçenek ve gerekçeleri:

- **Cihazda (yerel depolama)** — hesap gerektirmez, kişisel veri toplamaz,
  masadaki misafirin beklentisine uyar. Bedeli: telefon değişince kaybolur.
- Sunucuda ziyaretçi anahtarıyla — anahtar günlük döner, yani favori de
  günlük kaybolur; kalıcı yapmak takibi kalıcı yapmak demektir.
- Hesapla — misafirden kayıt istemek, QR menünün bütün vaadini bozar.

**Karar: cihazda.** Favori bir kolaylıktır, bir varlık değil; kalıcılık
için kimlik istemek orantısız.

### Dalga 4 — Puanlama ve geri bildirim

| Yetenek | Ne gerekiyor | Hak |
| --- | --- | --- |
| Ürün puanı | Yeni tablo, kötüye kullanım koruması, moderasyon | `feedback.collect` (yeni) |
| Puan gösterimi | Eşik altında GÖSTERİLMEZ | — |

> **BU BÖLÜM `docs/116` İLE GENİŞLETİLDİ (2026-09-05).** Sahip puanlamanın
> KPI ve OKR'larının bir **algoritma dosyasına** bağlanmasını, algoritmanın
> zamanla mevcut veriyle geliştirilmesini ve ileride Zomato · Swarm · Google
> Maps · sosyal uygulamanın buraya bağlanmasını istedi.
>
> Bu, puanlamayı bir alandan **hesaplanan bir çıktıya** çeviriyor ve şimdi
> alınmazsa sonradan alınamayacak dört önlem doğuruyor (kaynak alanı, ham
> sinyal/türetilmiş puan ayrımı, algoritma sürüm damgası, dış kimlik eşleme
> tablosu). Tamamı `docs/116`'da.
>
> Aşağıdaki üç kural GEÇERLİ ve oraya devredildi.

**Üç kural baştan konur, sonradan eklenemez:**

1. **Eşik altında puan gösterilmez.** Üç kişinin verdiği 5 yıldız bir bilgi
   değildir; gösterilirse yeni ürün her zaman en iyi görünür.
2. **Kötüye kullanım koruması ilk günden.** Puanlama anonimse tekrar
   engellenmeli; ziyaretçi anahtarı + ürün başına tek oy.
3. **Sahip puanı SİLEMEZ.** Silebiliyorsa ortalama bir pazarlama sayısıdır.
   Sahip yanıt verebilir; kaldıramaz.

### Dalga 5 — Sepet ve sipariş

| Yetenek | Ne gerekiyor | Hak |
| --- | --- | --- |
| Sepet (cihazda) | Ürün + adet, sipariş YOK | — |
| Sipariş iletimi | Yeni hat: sipariş modeli, mutfağa bildirim, durum | `ordering.basic` (yeni) |
| Ödeme | Ayrı ve daha büyük: sağlayıcı, iade, uzlaşma | `ordering.payment` (yeni) |

> **BU BÖLÜM GEÇERSİZ — 2026-09-05, sahibin tarifiyle.** Burada "sepet
> sipariş vermez, sepet ekranı 'sipariş ver' demez" yazıyordu ve o karar
> bana aitti, sahibe değil. Sahip akışı tarif etti: sepetten sipariş onayı
> verilir, kuyruğa düşer, garson panelden onaylar, onaylanınca mutfak
> monitörüne düşer. Tam akış, kullanıcı hikâyeleri ve görev sırası
> **`docs/115`**'te.
>
> Ayakta kalan tek ayrım şu: **sepet sunucuya gitmez.** Cihazda yaşar;
> sunucuya yalnız GÖNDERİLEN sipariş yazılır. Hiç sipariş vermeyecek her
> misafir için satır yazmak gereksizdi.

Sepet ile sipariş ayrı **katmanlardır** ama tek akıştır. Sepet cihazda
yaşayan bir listedir; sipariş bir taahhüttür — mutfağa iş açar, iptal
kuralı ister, durum ister. İkisinin arasındaki kapı **garsonun gözüdür**:
misafirin gönderdiği bir taleptir, garsonun onayladığı bir iştir.

### Dalga 6 — Fotoğraf ve plan kademesi

| Yetenek | Ne gerekiyor | Hak |
| --- | --- | --- |
| Misafir yüzeyinde plan farkı | Yayın anlık görüntüsüne plan yazılır | — |
| Zengin görsel | Plan kademesine bağlı | `menu.rich-media` (yeni) |

**Plan yayın anlık görüntüsüne DONDURULUR.** Sebep: sahip planını
düşürdüğünde basılı karekod aynı kalır ve o karekodun gösterdiği yayın
değişmemelidir. Plan değişikliği **bir sonraki yayında** etkisini gösterir.
Aksi hâlde ödeme gecikmesi, masadaki misafirin gördüğü menüyü anında
değiştirirdi.

## 4. Rol ve yetki ayrımı

Yeni yeteneklerin panel tarafı mevcut rollere düşer, yeni rol
üretilmez (`MembershipRole`: Sahip · Yönetici · Editör · Mutfak).

| İş | Kim |
| --- | --- |
| Marka tonu ve biçim seçmek | Sahip, Yönetici |
| Fotoğraf/zengin medya yönetmek | Sahip, Yönetici, Editör |
| Puanlara yanıt vermek | Sahip, Yönetici |
| Gelen siparişi görmek ve durum değiştirmek | **Mutfak dahil** |
| Sipariş/ödeme ayarları | Yalnız Sahip |

**Mutfak siparişi görür** ve bu rolün tanımını genişletmez, tamamlar:
"alerjen ve bugün bitti" cümlesi mutfağın **servis anında** ihtiyaç
duyduğu şeyi anlatıyordu; gelen sipariş de aynı anın işidir. Ama menüyü
hâlâ değiştiremez.

## 5. Modül kurgusu

`docs/113` yeni modül gerekmediğini söylemişti; program bunu **kısmen**
değiştiriyor:

| Yetenek | Modül |
| --- | --- |
| Marka tonu, biçim ekseni | `themes-brand` + `opt-08-custom-branding` |
| Arama, sesli arama, filtreler | `menu-catalog` + `seo-search-discovery` |
| Favoriler | Modül gerekmez — cihazda yaşar |
| Puanlama | `opt-25-feedback-nps` (tanım var, kod yok) |
| Sepet | `menu-catalog` kapsamında |
| Sipariş | `opt-14-online-ordering` (tanım var, kod yok) |
| Ödeme | `opt-15-restaurant-payment` — Iyzico entegrasyonundan AYRI: o abonelik ödemesi, bu misafir ödemesi |

**Yeni `modules/*.md` yazılmayacak**; var olan tanımlar kullanılacak ve
teslim edildiklerinde durumları koddan okunacak — çünkü durum alanı
kaldırıldı ve orada bir daha durum yazılmayacak.

## 6. Ölçüm

Her yetenek `docs/112` taksonomisine **olay ekleyerek** gelir. Serbest dize
kabul edilmiyor; kapı bunu zorluyor. Yeni olaylar en az şunları
cevaplamalı:

- Arama: kaç kişi aradı, kaçı sonuç bulamadı (sunucu tarafı defterde ZATEN
  var, GTM'e taşınmaz)
- Sesli arama: kaç kişi denedi, kaçında tarayıcı desteklemedi
- Favori: kaç kişi işaretledi (ürün adı basılmaz)
- Puan: kaç kişi verdi, dağılım — **puanın kendisi kişisel veri değil ama
  yorum metni olabilir; metin basılmaz**
- Sepet: kaç kişi ekledi, kaçı boşalttı

## 7. Bu programın kendi gerekçe süresi

`docs/109` §8.6'nın kuralı buraya da uygulanır: yukarıdaki her "gerekiyor"
o günün ölçümüdür. Bir dalga başlamadan önce sorulacak soru şudur —
**gerekçe hâlâ doğru mu?** Özellikle:

- Favorinin cihazda yaşaması kararı, misafir hesabı diye bir şey doğarsa
  yeniden bakılmalı.
- Sesli aramanın sunucuya ses göndermemesi kararı, tarayıcı desteği
  yetersiz çıkarsa yeniden bakılmalı — ama o zaman da karar "ses gönder"
  değil, "sesli aramayı sunma" olabilir.
