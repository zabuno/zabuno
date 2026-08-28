# 75 — Misafir menüsünde restoran kimliği (P0-03)

## Önce ne oluyordu

Misafir masadaki karekodu okutuyor. Sayfa açılıyor. Gördüğü ilk kelime:

> **Menü**

Restoranın adı yok. Adresi yok. Telefonu yok. Üç ayrı kayıp:

- **Sahip için marka kaybı.** Menü dijitalleşiyor ama üstünde kimin menüsü
  olduğu yazmıyor.
- **Misafir için belirsizlik.** "Doğru yere mi geldim?" — masadaki karekod
  yanlış masaya yapıştırılmış olabilir.
- **Paylaşılan bağlantıda tam kayıp.** Bu sayfa çoğunlukla WhatsApp'ta
  paylaşılır. Önizlemede yazan başlık "Menü" idi: kimsenin tanımadığı bir
  bağlantı.

Marka adı sistemde vardı — ama yalnız **yapılandırılmış veriye** giriyordu.
Yani arama motoru restoranın adını biliyordu, masadaki misafir bilmiyordu.

## Şimdi ne oluyor

Sayfanın başlığı restoranın **gerçek adı**. Altında şube adı, adres ve
tıklanabilir telefon. Sekme başlığı ve paylaşım önizlemesi de aynı adı
taşıyor.

```
Zeytin Restoranları
Kadıköy Şubesi
Bahariye Cd. No:1, 34710 İstanbul
+90 216 555 12 34          ← dokun, arar
```

## Kimlik neden snapshot'a yazılıyor

Kimlik yayın anında **donar**; misafir sayfası onu canlı sorgudan çekmez.

Sebep bir kullanıcı yolculuğu: sahip Ocak'ta menüsünü yayınlar, Mart'ta
şubenin telefonunu değiştirir. Kimlik canlı okunsaydı, **Ocak'ta
onayladığı yayın** Mart'ta habersiz değişirdi. Oysa yayın, sahibin "bunu
onayladım" dediği donmuş hâldir — içindeki fiyat gibi, üstündeki ad da.

Yeni bilgi yeni yayınla gelir. Sahip telefonu değiştirdikten sonra Yayınla'ya
basar, yeni numara yayına girer.

## Eski yayınlar

Kimlik alanı eklenmeden önce yayınlanmış menüler hâlâ var. Onlarda donmuş
bir kimlik **yok**, dolayısıyla donmuşluk ihlali de yok: sayfa sunucunun
canlı olarak bildiği ada düşer. Sabit "Menü" metnine geri dönmez.

## Kanıt

`tests/Feature/Publication/PublicationIdentityTest.php`

| Requirement | Test |
| --- | --- |
| `PUB-IDENTITY-SNAPSHOT-01` | Yayın snapshot'ı ad, şube, adres ve telefon taşır |
| `PUB-IDENTITY-FROZEN-01` | Marka adı ve telefon sonradan değişince **mevcut yayın değişmez**; yeni yayın yeni değeri alır |
| `PUB-IDENTITY-HEADING-01` | `<h1>` restoranın adıdır; sabit `"Menü"` artık başlık değildir |
| `PUB-IDENTITY-TEL-01` | Telefon `tel:` bağlantısıdır ve boşluksuz normalize edilir |
| `PUB-IDENTITY-ABSENT-01` | Kimliksiz eski snapshot sayfayı bozmaz |

`tel:` içindeki boşluk ve parantez bazı telefonlarda çağrıyı bozar; görünen
metin insan için, bağlantı makine içindir.

## Sınır: logo

`config/media-slots.php` bir `logo` slotu tanımlıyor, ama medya boru hattı
bugün iskelet: rendition üreten tek uygulama `UnavailableMediaAssetProcessor`
ve bir servis rotası yok. Logo, gerçek medya işleme (**P0-08**) kurulduktan
sonra ürün görseliyle (**P0-04**) aynı pakette bağlanacak — snapshot'taki
kimlik yapısı o alanı almaya hazır.

## Ürün iddiası

Çalışır: masadaki misafir gittiği yerin adını, adresini görür ve telefonu
dokunarak arar.
Çalışmaz: logo henüz başlıkta gösterilemiyor (P0-08 → P0-04).
