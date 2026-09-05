# 115 — Sipariş akışı: sepetten mutfak monitörüne

**Sahibin tarifi (2026-09-05), birebir:**

> Sepete kadar eklenir. Sepetten sipariş onayını verebilir. Kuyruğa düşer.
> Garson panelden onaylar. Anonim kullanıcı sipariş vermek için oturum
> açmaz. Karekod aracılığıyla masası tespit edilir. Garson göz ile kontrol
> eder. Restoran Admin panelden onaylarsa sipariş mutfak tarafındaki
> monitöre düşer. Monitör, restoran Admin tarafında vardır. Admin panelde bu
> monitörün full screen olabilmesi için gereken UI olmalıdır.

Bu belge `docs/114` §3 Dalga 5'i **değiştirir**: orada "sepet ile sipariş
ayrı gelir, sepet sipariş vermez" yazıyordu. Sahip sipariş akışını tarif
etti; sepet artık siparişe bağlanıyor.

## 0. Zaten hazır olan tek şey — ve neden önemli

**`qr_codes.dining_table_id` var.** Karekod zaten masaya bağlı; QR kartına
masa adı da basılıyor. Yani *"karekod aracılığıyla masası tespit edilir"*
uydurulacak bir şey değil, var olan bir bağın okunmasıdır.

Bu, akışın en kırılgan yerini ortadan kaldırıyor: misafirden masa numarası
istemek gerekmiyor, dolayısıyla yanlış masa yazma ihtimali de yok.

## 1. Kimlik yok, ama sorumluluk var

Misafir oturum açmıyor. Bir siparişin arkasında kimlik olmadığında iki
gerçek risk doğar ve ikisinin de cevabı sahibin tarifinde:

| Risk | Cevap |
| --- | --- |
| Dışarıdan biri karekodu okutup sipariş açar | **Garson göz ile kontrol eder.** İnsan onayı kapının kendisidir; yazılım onun yerine geçmeye çalışmaz. |
| Aynı kişi arka arkaya onlarca sipariş açar | Hız sınırı + masa başına açık sipariş sınırı. Bu yazılımın işi. |

**Yazılım kimliği taklit etmez.** Ziyaretçi anahtarı bir kimlik değildir ve
öyle sunulmaz; yalnız aynı cihazın siparişini kendi ekranında görebilmesi
için kullanılır.

## 2. Durum makinesi — ve neden iki onay var

```
  taslak (cihazda, sunucuya hiç gitmez)
     │  misafir "siparişi gönder" der
     ▼
  bekliyor ────────────► iptal (misafir, yalnız onaydan önce)
     │  garson/yönetici onaylar
     ▼
  onaylandı ──────────► reddedildi (garson, sebebiyle)
     │  mutfak monitörüne düşer
     ▼
  hazırlanıyor
     │
     ▼
  hazır ──────────────► teslim edildi
```

**"Bekliyor" ile "onaylandı" arasındaki fark bu akışın kemiğidir.**
Misafirin gönderdiği şey bir *talep*, garsonun onayladığı şey bir *iş*tir.
Talebi doğrudan mutfağa düşürmek, masada oturmayan birinin mutfağa iş
açabilmesi demektir.

**Mutfak monitörü yalnız "onaylandı" ve sonrasını görür.** Bekleyen sipariş
mutfağa hiç görünmez — görünseydi aşçı onaylanmamış bir işi hazırlamaya
başlardı.

**Sepet sunucuya gitmez.** Cihazda yaşar; sunucuya yalnız gönderilen sipariş
yazılır. Sepeti sunucuda tutmak, hiç sipariş vermeyecek her misafir için
satır yazmak olurdu.

## 3. Kullanıcı hikâyeleri

### Misafir (anonim, masada)

| # | Hikâye | Kabul ölçütü |
| --- | --- | --- |
| M1 | Menüden ürün seçip sepetime eklerim | Sepet cihazda kalır; sayfa yenilenince kaybolmaz |
| M2 | Sepetteki adedi değiştiririm, ürün çıkarırım | Toplam anında güncellenir; para biçimi kanonik biçimlendiriciden |
| M3 | Siparişimi gönderirim ve **masamı yazmam** | Masa karekoddan gelir; misafire sorulmaz |
| M4 | Gönderdikten sonra durumunu görürüm | "Bekliyor / onaylandı / hazırlanıyor / hazır" — uydurma süre YOK |
| M5 | Onaylanmadan önce iptal ederim | Onaydan sonra iptal yok; ekran bunu söyler |
| M6 | Karekodu masa dışından okutmuşsam sipariş yine gönderilir ama onaylanmayabilir | Ret sebebi ekranda görünür |
| M7 | Tükenmiş ürünü sepete ekleyemem | "Bugün bitti" işareti sipariş yolunda da geçerli |

### Garson / servis

| # | Hikâye | Kabul ölçütü |
| --- | --- | --- |
| G1 | Bekleyen siparişleri masa numarasıyla görürüm | Liste masaya göre; en eski üstte |
| G2 | Siparişi onaylarım | Onaylanan mutfak monitörüne düşer |
| G3 | Siparişi reddederim ve sebebini yazarım | Sebep misafirin ekranında görünür |
| G4 | Yeni sipariş geldiğinde fark ederim | Görsel + sayı; ses **sahibin kararı** (aşağıda) |
| G5 | Aynı siparişi iki kez onaylayamam | Onay atomik; ikinci deneme sessizce geçmez, durumu söyler |

### Mutfak

| # | Hikâye | Kabul ölçütü |
| --- | --- | --- |
| K1 | Onaylanmış siparişleri monitörde görürüm | Yalnız `onaylandı` ve sonrası |
| K2 | Monitörü **tam ekran** yaparım | Tarayıcı tam ekranı + ekran uyanık kalır |
| K3 | Siparişi "hazırlanıyor" ve "hazır" işaretlerim | Tek dokunuş; hedef 44 px'ten büyük (elleri meşgul) |
| K4 | Alerjen uyarısını sipariş satırında görürüm | Ürünün alerjen verisi siparişe **kopyalanır** |
| K5 | Uzaktan okurum | Monitör tipografisi mutfak mesafesine göre |

### Yönetici / sahip

| # | Hikâye | Kabul ölçütü |
| --- | --- | --- |
| Y1 | Sipariş almayı açar/kapatırım | Kapalıyken misafir sepeti görür ama gönderemez ve **sebebini okur** |
| Y2 | Geçmiş siparişleri görürüm | Silinmez; denetim izi gibi kalıcı |
| Y3 | Sipariş alma planıma dahil mi bilirim | Hak yoksa dürüst cümle, boş ekran değil |

## 4. Rol ve yetki

**Yeni bir izin ekseni gerekiyor; yeni bir rol GEREKMİYOR.**

| İzin | Sahip | Yönetici | Editör | Mutfak | Servis* |
| --- | --- | --- | --- | --- | --- |
| `order.view` | ✓ | ✓ | — | ✓ | ✓ |
| `order.confirm` | ✓ | ✓ | — | — | ✓ |
| `order.kitchen` | ✓ | ✓ | — | ✓ | — |
| `order.settings` | ✓ | — | — | — | — |

\* **Servis rolü sonraki pakete bırakıldı.** Sahibin tarifinde garson ve
yönetici ayrı cümlelerde geçiyor, ama küçük bir restoranda ikisi aynı
kişidir. Bugün `order.confirm` Sahip ve Yönetici'de; ayrı bir garson rolü
gerektiği ölçülünce eklenir — mutfak rolünün eklenme biçiminin aynısıyla.

**Mutfak siparişi görür ve durum değiştirir, ama ONAYLAYAMAZ.** Onay bir
servis kararıdır: masada kimin oturduğunu gören kişi verir.

**Editör sipariş görmez.** Editör içerik düzenler; servis anının işi değil.

## 5. Fiyat kademesi

Yeni hak: **`ordering.basic`**. Kural `docs/114` §2'den değişmedi — kademe
bir yeteneği açar, temel yolculuğu kapatmaz: hak yoksa misafir menüyü
görmeye devam eder, yalnız sipariş gönderemez ve **sebebini okur.**

**Hak yayın anlık görüntüsüne dondurulur** (`docs/114` §3 Dalga 6 kuralı):
sahip planını düşürdüğünde masadaki karekod aynı kalır ve o karekodun
gösterdiği yayın değişmez.

> Plan yönetimi hakları serbest dize kabul ediyor ve tanımadığını sessizce
> yok sayıyor (`StoreManagedPlanRequest`). `ordering.basic` eklenirken o
> doğrulama enum'a bağlanmalı.

## 6. Gerçek zamanlılık — dürüst karar

**Depoda WebSocket yok** ve kuyruk cron ile yürüyor
(`queue:work --stop-when-empty`, dakikada bir). Yani "anında" bir kanal
bugün yok.

**Karar: kısa aralıklı yoklama (polling), ve bu bir eksiklik olarak
YAZILIR.** Garson ekranı ve mutfak monitörü sunucuyu belirli aralıkla
sorar. Sayfa görünür değilken yoklama **durur** — arka planda duran bir
monitör sunucuyu boşuna meşgul etmemeli.

Uydurma yok: ekran "anlık" demez. Son güncelleme anı yazılır, çünkü mutfakta
donmuş bir ekranla dolu bir ekran aynı görünür.

**Ses uyarısı sahibin kararıdır** ve bu pakette yapılmaz: tarayıcı otomatik
ses çalmayı engeller, kullanıcı etkileşimi ister, ve gürültülü bir mutfakta
duyulup duyulmayacağı ölçülmemiştir.

## 7. Görevler ve geliştirme sırası

Sıra bağımlılığa göre; her adım bir öncekinin üstüne biner.

### S1 — Sipariş alanı ve durum makinesi (backend)

- Göç: `orders` (workspace, location, menu, `dining_table_id`, durum,
  ziyaretçi anahtarı, toplam, para birimi, zaman damgaları) ve
  `order_items` (ürün adı, fiyat, adet, **alerjen kopyası**).
- **Ad ve fiyat siparişe KOPYALANIR**, ürüne bağlanmaz: yarın fiyat
  değişince dünkü sipariş değişmemeli. Aynı gerekçe yayın anlık görüntüsünde
  de kullanıldı.
- Durum geçişleri tek yerde; geçersiz geçiş sessizce yutulmaz.
- Kiracı ve şube sınırı sorgunun içinde.

### S2 — Sipariş gönderme ucu (backend, misafir)

- Uç: karekod belirteci → masa çözümü → sipariş yazımı. Misafirden masa
  ALINMAZ.
- Hız sınırı, masa başına açık sipariş sınırı, tükenmiş ürün reddi.
- Sipariş alma kapalıysa ya da hak yoksa **dürüst ret**.
- Ölçüm olayı `docs/112` taksonomisine eklenir (ürün adı ve fiyat
  basılmaz).

### S3 — Misafir sepeti ve gönderme (frontend, 320 px)

- Sepet cihazda; adet, çıkarma, toplam.
- Gönderme ve durum ekranı; uydurma süre yok.
- Onaydan önce iptal.
- **320 px'te tam erişilebilir** — `docs/48`.

### S4 — Garson kuyruğu (frontend + backend)

- Bekleyen siparişler, masaya göre, en eski üstte.
- Onayla / reddet (sebebiyle). Onay **atomik**; ikinci deneme durumu söyler.
- Yoklama; sayfa gizliyken durur.

### S5 — Mutfak monitörü (frontend + backend)

- Yalnız `onaylandı` ve sonrası.
- **Tam ekran kipi**: tarayıcı tam ekranı + ekran uyanık tutma; ikisi de
  desteklenmiyorsa dürüst düşer.
- Uzaktan okunur tipografi; 44 px'ten büyük hedefler.
- Alerjen satırda görünür.
- Son güncelleme anı yazılı.

### S6 — Sipariş ayarları ve geçmiş (panel)

- Sipariş almayı aç/kapat (yalnız Sahip).
- Geçmiş; silinmez.
- Plan kapısı ve dürüst cümle.

### S7 — Servis rolü (ölçüldükten sonra)

Ayrı bir garson rolüne ihtiyaç ölçülürse, mutfak rolünün eklenme biçiminin
aynısıyla: dar izin kümesi, sunucuda sınır, ekranda yapılamayan iş
çizilmez.

## 8. Bu pakette YAPILMAYACAKLAR

| Yapılmayacak | Neden |
| --- | --- |
| **Ödeme** | Ayrı ve daha büyük: sağlayıcı, iade, uzlaşma. `opt-15`. Sipariş ödemesiz de çalışır — masada ödenir. |
| Sipariş hazır olduğunda misafire **bildirim** | Anonim misafire push kanalı yok; kurmak kimlik ister |
| Tahmini hazırlık süresi | Ölçülmemiş bir sayıdır ve yanlış olduğunda misafiri sinirlendirir |
| Masa birleştirme, hesap bölme | Ayrı bir problem alanı (adisyon), sipariş değil |
| Sesli uyarı | §6 — sahibin kararı, ölçülmemiş |
| POS entegrasyonu | `opt-13`, ayrı hat |
