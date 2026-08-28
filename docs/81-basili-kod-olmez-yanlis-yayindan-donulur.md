# 81 — Basılı kod ölmez, yanlış yayından dönülür (P1-03, P1-05)

İki panik anı, iki çıkış yolu.

## Panik 1 — "Yanlış fiyat listesini yayınladım"

Bütün menü %30 yanlış ve misafirler **şu anda** onu okuyor.

Tek yol taslağı düzeltip yeniden yayınlamaktı: panik anında en yavaş yol, ve
düzeltirken ikinci bir hata yapma ihtimali en yüksek olan yol.

Artık yayın geçmişi görünür, canlı sürüm işaretli ve tek eylemle önceki
sürüme dönülüyor.

### Geçmiş silinmez

Geri alma, eski snapshot'ı **yeni bir yayın olarak** yazar. Sürüm 1'e dönmek
sürüm **3**'ü doğurur.

Bir yayını yok saymak, "ne zaman ne yayındaydı" sorusunu cevapsız bırakırdı
— oysa yanlış fiyatı gören misafirle tartışan sahip tam olarak bunu sorar.

### Taslağa dokunulmaz

Geri alma **misafirin gördüğünü** düzeltir, sahibin çalışmasını değil. Sahip
o sırada taslakta düzeltme yapıyor olabilir ve geri alma onun yarım işini
silmemeli.

### Basılı koda dokunulmaz

Kalıcı adres ve token değişmez. Bir test bunu donduruyor.

### Sıralama bir karar

Liste **en yeni önce**. Geri almayı arayan sahip panik hâlindedir ve listenin
dibine inmez.

Snapshot'lar listeye konmaz: kırk yayınlık bir geçmişte her biri menünün
tamamını taşır ve liste ekranı megabaytlarca veri indirirdi.

### Yetki

Geri almak **yayınlamaktır**: `menu.publish` ister. Salt-okunur bir üye
geçmişi okuyabilir ama geri alamaz.

---

## Panik 2 — "40 masaya kod bastırdım"

İki ayrı çıkmaz vardı:

- Kodların **başka bir menüyü** göstermesini istiyor — yapamıyor.
- Bir kodu **yanlışlıkla kapattı** — geri açamıyor ve masadaki kâğıt kalıcı
  olarak ölü.

Her ikisinin de tek çaresi yeniden bastırmaktı — yani "bir kez bas, hedefi
sonra değiştir" vaadinin ihlali.

`DisableQrCodeController` tek yönlüydü; karşılığı olan bir dosya yoktu.

### Hedef değiştirmenin gerçek anlamı

Şema şube başına **tek menü** tutuyor (`menus.location_id` tekil). Dolayısıyla
"aynı şubede başka bir menü" diye bir şey yok.

Hedef değiştirmenin tek gerçek anlamı, basılı bir kodun **başka bir şubenin**
menüsünü göstermesi: kart fiziksel olarak taşınmış ya da şube yapısı
değişmiştir.

Bunu yasaklamak, tam da kaçınmak istediğimiz "yeniden bastır" tuzağını
kurardı. Kiracı sınırı yeterli koruma: menü bu çalışma alanının olmak
zorunda; başka bir restoranın menüsü `404` (varlığı bile sızmaz).

**Kodun kendi şubesi de taşınır.** Aksi hâlde ölçüm, kodun artık
göstermediği şubeye yazılırdı.

### Token değişmez, geçmiş kalır

`qr_destinations` **yeni** bir satır alır; eski satır durur — "bu kod ne
zaman nereye bakıyordu" sorusu geçmişten cevaplanabilmeli.

"Şu an geçerli hedef" işaretçisi **güncellenir**, ikinci bir satır açılmaz:
o sorunun tek bir cevabı olmalı.

### Açma ve kapama aynı izne bağlı

Kapatabilen açabilmelidir; aksi hâlde yetki modeli kullanıcıyı kendi yaptığı
işin içine hapsederdi. Kapalıyken `/q/{token}` mevcut çıkmaz-sokak
davranışını koruyor ve rota şeklini ifşa etmiyor.

---

## Yol boyunca çıkan gerçek bulgu

İlk yazdığım test, "karekod yalnız kendi şubesinin menüsünü gösterebilir"
kuralını donduruyordu. Şema onu **imkânsız** kıldı: şube başına tek menü
olduğu için o kural, hedef değiştirmeyi tamamen kapatıyordu. Kural yanlıştı,
test değil — ve testi yazmasaydım kuralı ürüne gömecektim.

Ayrıca bir arayüz testi **sıra tabanlı** bir fetch sahtesi kullanıyordu.
Sayfa artık ikinci bir istek yapıyor ve React'te çocuk efektleri ebeveyn
efektinden **önce** çalışıyor; kuyruktaki yanıt yanlış isteğe gidiyordu.
Sahte adrese duyarlı hâle getirildi — kırılganlık testin niyetiyle ilgisizdi.

## Kanıt

`PublicationRollbackTest` (5), `QrCodeLifecycleTest` (5),
`PublicationHistoryRegion.test.tsx` (3)

| Requirement | Ne donduruluyor |
| --- | --- |
| `PUB-HISTORY-LIST-01` | En yeni önce; canlı sürüm işaretli |
| `PUB-ROLLBACK-AS-NEW-01` | Geri alma üçüncü bir yayındır; geçmiş silinmez |
| `PUB-ROLLBACK-GUEST-IMMEDIATE-01` | Misafir anında doğru fiyatı görür |
| `PUB-ROLLBACK-QR-UNTOUCHED-01` | Kalıcı adres değişmez |
| `PUB-ROLLBACK-AUTHZ-01` | Geri almak yayınlamaktır |
| `PUB-ROLLBACK-TENANT-01` | Başka restoranın yayını buraya yüklenemez |
| `QR-RETARGET-TOKEN-STABLE-01` | Masadaki kâğıt aynı kâğıttır |
| `QR-RETARGET-HISTORY-01` | Eski hedef satırı durur, işaretçi tektir |
| `QR-RETARGET-LOCATION-FOLLOWS-01` | Ölçüm kodun gösterdiği şubeye yazılır |
| `QR-ENABLE-01` | Kapatılan kod geri açılır ve adres yeniden çalışır |
| `QR-DISABLED-DEAD-END-01` | Kapalıyken çıkmaz sokak korunur |

## Ürün iddiası

Çalışır: sahip yanlış yayından tek tıkla döner (geçmiş korunarak, basılı
kodlara dokunulmadan) ve yanlışlıkla kapattığı karekodu geri açar; basılı bir
kodu başka bir şubenin menüsüne taşıyabilir.
