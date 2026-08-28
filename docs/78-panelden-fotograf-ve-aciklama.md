# 78 — Panelden fotoğraf ve açıklama (FF-20)

## Önce ne oluyordu

`docs/77` uçları açtı: ürüne açıklama yazılabiliyor, fotoğraf bağlanabiliyor,
ikisi de yayına donuyordu. Ama sahip bunların hiçbirini **panelden**
yapamıyordu.

API üzerinden yürünen bir yol, sahibi için **olmayan bir yoldur**.

## Şimdi ne oluyor

Menü satırında **Photo & text** düğmesi. Basınca satırın altında tek bir
düzenleyici açılıyor:

```
Description   [Kömür ateşinde, acılı, yanında bulgur pilavı.        ]
              A short line the guest reads under the name.

Photo         [ Kömürde Adana kebap                            ▾ ]

              [ Save presentation ]
```

## Neden tek düzenleyici

Açıklama ve fotoğraf ayrı düğmeler olsaydı sahip aynı satır için iki kez form
açardı. Oysa yaptığı iş tektir: **"bu ürünü misafire nasıl göstereceğim."**
Ekrandaki düğme sayısı, kullanıcının kafasındaki iş sayısına benzemeli.

## Yalnız hazır görsel seçilebilir

Listede yalnız **işlenmesi bitmiş** (`ready`) ve **bu slota ait**
(`itemImage`) görseller var. İşlenmekte olan bir görseli seçtirmek, menüye
kırık bir kutu koymaya davet etmektir.

Hazır görsel yoksa boş bir açılır menü değil, nereye gidileceği yazıyor:

> No processed photo is available yet. Upload one on the Media page first.

Liste form **açılınca** çekiliyor, sayfa yüklenince değil: menü ekranını açan
herkesin medya listesini indirmesi için sebep yok, ve sahip arada yeni bir
fotoğraf yüklemiş olabilir.

## Yarım başarı yarım anlatılır

Kaydetme iki istek gönderir: önce açıklama, sonra fotoğraf. İlki geçip ikincisi
düşerse ekranda şu yazar:

> The description was saved, but the photo was not attached.

Bunu tek bir "kaydedilemedi" ile anlatmak **yalan** olurdu. Sahip neyin
olduğunu ve neyin olmadığını bilmeli; aksi hâlde açıklamayı ikinci kez yazar.

## Durumun sebebi medya sayfasında

`docs/76` tarayıcı çalışmadığında sebebi kaydediyordu; artık **görünüyor** de.
Rozet DURUMU söyler ("Scanning"), sebebi söylemez — ve "Scanning" rozetiyle
sonsuza kadar bekleyen bir dosyanın karşısında sahip ne olduğunu bilemez.

Sebep rozetin **kendi canlı bölgesinin içinde** duruyor: satır başına ikinci
bir canlı bölge açmak, ekran okuyucuda aynı şeyi iki kez okuturdu. Sorunsuz
bir dosyaya sebep yazılmıyor; sahip her satırda açıklama görmeye başlarsa
gerçek uyarıyı okumaz.

## Kanıt ve bir dürüstlük notu

Bu paket **test-first yazılmadı**: arayüz önce kuruldu, testler yeşil doğdu.
Yeşil doğan bir test, bağladığını kanıtlamaz — bu yüzden iki **mutasyon**
denendi ve ikisi de yakalandı:

| Mutasyon | Sonuç |
| --- | --- |
| Fotoğraf isteği her zaman `null` göndersin | ✅ test kırmızıya döndü |
| Hazır olmayan görseller de listelensin | ✅ test kırmızıya döndü |

`MenuCatalogWorkspace.presentation.test.tsx` (4),
`MediaLibraryRegion.test.tsx` (+2).

## Kapsam dışı

Fotoğraf **yükleme** hâlâ Medya sayfasında; menü ekranından doğrudan yükleme
yok. İki ekran arasında gidip gelmek bir sürtünme, ama menü ekranına ikinci
bir yükleme yüzeyi koymak onu iki işin ekranı yapardı.

## Ürün iddiası

Çalışır: sahip panelden bir ürüne açıklama yazar ve hazır bir fotoğraf bağlar;
yayınlayınca misafir ikisini de görür.
Çalışmaz: fotoğraf menü ekranından yüklenemiyor, önce Medya sayfasına gitmek
gerekiyor.
