# 122 — Yarım kalanlar: ölçüm ve dalga planı

**Sahibin kararı (2026-09-05):** *"Biz başka konulara geçtik ama yarım kalan
geliştirmeler var. Restoran menüsü yarım kaldı, restoran admin panelinde işler
yarım kaldı, `/platform` süper admin paneli eksik. Bunları da paralel agents
ile geliştir."*

Bu belge tahmin taşımaz. Her satır depodan **ölçüldü**.

> **Y1 ve Y3 kapandı (FF-19x). Aşağıdaki satırlar yeniden ölçüldü.**
>
> **Y1 — plan kataloğu.** `ordering.basic` ve yeni tanımlanan
> `menu.rich-media`, `restaurant` kademesinde açılır ve `team` onları devralır
> (`PlanCatalogueSeeder`). Kademe gerekçeleri §2'de. Bir kapı da kondu:
> `Entitlement` enum'una eklenen her yetenek en az bir kademede satılmak
> zorunda (`PlanCatalogueSellsEveryCapabilityTest`). Aynı tur, satılan ama
> fiyat sayfasında hiç yazmayan `branding.custom`'ın kaybını da kapattı.
>
> **Y3 — modüller ekranı.** `docs/111` adım 1 ve 2 zaten FF-168'de
> uygulanmıştı: `GET /api/admin/modules` ve `/engineering/modules` ekranı
> bugün ayakta, 8 Feature + 10 bileşen testiyle. Bu belgenin §3 ve §4'ünde
> "ekran yok" yazıyordu; cümle bir zamanlar doğruydu, altındaki gerçek
> değişti — `docs/109` §8.7'nin kusur ailesinin ta kendisi. Satırlar
> düzeltildi. `docs/111` adım 3–5 hâlâ yapılmadı ve o belgenin kendi kutusu
> sebebini yazıyor.

## 1. Misafir menüsü — `docs/114`'ün altı dalgası

| Dalga | Ne | Durum | Kanıt |
| --- | --- | --- | --- |
| 1 — Marka kimliği ve okunabilirlik | Ton, biçim, kontrast | **Bitti** | `app/Domain/Branding/`, ramp testleri |
| 2 — Bulmak | Arama, sesli arama, filtreler | **Bitti** | `GuestMenuFindTest`, sıfır dış istek |
| 3 — Favoriler | Cihazda favori işaretleme | **YOK** | Kodda karşılığı yok |
| 4 — Puanlama | Oy, eşikli gösterim, sahip yanıtı | **Bitti** | `docs/116` P4–P6 |
| 5 — Sepet ve sipariş | Sepet, gönderim, dürüst ret | **Bitti** | `GuestCartTest`, sipariş ucu |
| 6 — Fotoğraf ve plan kademesi | Zengin görsel, plan farkı | **Hak var, yüzey yok** | `menu.rich-media` tanımlı ve `restaurant` kademesinde; misafir yüzeyi yazılmadı |

**Kalan iki dalga.** Üçüncüsü küçük ve karar zaten verilmiş (favori
**cihazda** yaşar — favori bir kolaylıktır, bir varlık değil; kalıcılık için
kimlik istemek orantısız). Altıncısının **fiyat tarafı FF-19x'te bitti**:
`menu.rich-media` tanımlandı ve `restaurant` kademesine bağlandı. Geriye
misafir yüzeyi kaldı — ve o yüzey inene kadar hak fiyat sayfasında
**duyurulmaz**, çünkü olmayan bir şeyi satmak ödemeden önce söylenmiş bir
yalandır.

## 2. Restoran paneli — on üç bölüm var, eksik olan ne?

Bölümler: Pano, Menü, Karekod, Yayın, Medya, Analitik, Siparişler, Şubeler,
Marka, Ekip, Ayarlar, Profil, Faturalandırma.

**Bölüm sayısı bir şey ölçmez.** Ölçülen eksikler:

| Eksik | Kanıt | Etkisi |
| --- | --- | --- |
| Puanlama panelde çizilmiyor | `docs/116` P5 panel ucu var, ekran yok | Sahip misafirin verdiği oyu göremiyor |
| Sipariş bölümü kenar çubuğunda 12. sırada | Bölüm kaydının `order` alanı | Günlük operasyon, en dipte |
| ~~`ordering.basic` hiçbir planda yok~~ | **KAPANDI (FF-19x)** | `restaurant` ve `team` kademelerinde satılıyor |
| ~~`menu.rich-media` hakkı yok~~ | **KAPANDI (FF-19x)** | Hak tanımlı ve kademeli; yüzeyi Y6'da |
| Mobil borç: 24 hikâye | `mobile-ux-audit.baseline.json` | `docs/117` M5–M9 |

**En sert olanı üçüncüsüydü.** Sipariş hattı uçtan uca çalışıyordu — misafir
gönderiyor, garson onaylıyor, mutfak görüyor — ama hiçbir plan bu hakkı
vermiyordu. Yani **çalışan bir yetenek satılamıyordu.** Bu bir kod eksiği
değil, bir katalog eksiğiydi ve bir gün "neden kimse kullanmıyor?" sorusuna
yanlış cevap verdirirdi.

FF-19x bunu kapattı ve **kusur ailesini de kapattı**: artık `Entitlement`
enum'una eklenip hiçbir kademeye konmayan bir yetenek kapıyı kırar
(`PlanCatalogueSellsEveryCapabilityTest`). Aynı kapı ikinci bir sessiz kaybı
daha yakaladı — `branding.custom` iki ücretli kademede satılıyordu ama fiyat
sayfasında insanca karşılığı yoktu, dolayısıyla sayfa onu hiç göstermiyordu.

## 3. `/platform` süperadmin — ölçülmüş kapsam

**Bugün var olan:** on yedi uç, beş ekran bölümü (ticari, kimlik bilgileri
kasası, entegrasyonlar, planlar, abonelikler). Ekran katmanı **105 satır**.

| Uç ailesi | Uç sayısı | Ekranı var mı |
| --- | --- | --- |
| Çalışma alanları (liste, abonelik, elle ödeme) | 3 | Kısmen |
| Planlar (liste, oluştur, etkinleştir) | 3 | Var |
| Kimlik bilgileri kasası | 3 | Var |
| Bağlantılar (liste, ekle, sonda, durum) | 4 | Var |
| Modüller | 1 | **Var** — `/engineering/modules` (`docs/111` adım 1–2, FF-168) |
| AI denetimi | 1 | Kısmen |
| Sürüm tasdikleri | 1 | Kısmen |

**Ölçülen boşluklar — hepsi bir süperadminin ilk gün ihtiyacı:**

1. **Kiracı ayrıntısı yok.** Liste var, ama bir çalışma alanına tıklayınca
   ne olduğu (şubeleri, menüleri, kullanımı, son olayları) görülemiyor.
2. **Kullanıcı yönetimi yok.** Bir kullanıcının hangi çalışma alanlarında
   olduğu, girişleri, kilitli mi — hiçbiri yok.
3. **Destek görünümü yok.** "Müşteri arıyor, ekranında ne var?" sorusunun
   cevabı yok. Kiracı olarak oturum açma (impersonation) yok — ki bu
   **kasıtlı olarak zor** olmalı ve denetim kaydı bırakmalı.
4. **Sağlık ve olay görünümü yok.** Kuyruk, hata, dağıtım, dış sağlayıcı
   durumu tek yerde değil.
5. ~~**Modüller ekranı yok**~~ — **KAPANDI.** Ekran `/engineering/modules`
   altındadır, `/platform` altında değil: modül envanteri para değil
   **mühendislik kanıtıdır** (`docs/111` §2). Salt okunur; hiçbir anahtar
   çizmez ve `modules/*.md`'nin "PLANNING ONLY" iddiasını okumaz. `docs/111`
   adım 3–5 (durum rozeti, `modules/` eşlemesi, yaşam döngüsü) hâlâ yapılmadı.
6. **Denetim günlüğü ekranı yok.** Kayıt yazılıyor, okunacak yer yok.

## 4. Dalgalar — sıra, yayılma alanına göre

| # | Paket | Yüzey | Neden bu sırada |
| --- | --- | --- | --- |
| ~~Y1~~ | **BİTTİ** — Plan kataloğu: `ordering.basic` + `menu.rich-media` | Süperadmin + fiyat | **Çalışan bir yetenek satılamıyordu.** Kod değil katalog işiydi, en ucuz ve en yüksek etki |
| Y2 | Süperadmin: kiracı ayrıntısı, kullanıcı yönetimi, denetim günlüğü | `/platform` | Süperadminin ilk gün ihtiyacı; hepsi mevcut veriyi OKUYOR, yeni veri üretmiyor |
| ~~Y3~~ | **BİTTİ** — Süperadmin: modüller ekranı (`docs/111` adım 1–2) | `/engineering` | Plan hazırdı, veri hazırdı; ekran FF-168'de inmişti, bu belge geç fark etti |
| Y4 | Panel: puanlama ekranı | Restoran paneli | Uç var, ekran yok — en kısa yol |
| Y5 | Misafir: Dalga 3 favoriler (cihazda) | Misafir menüsü | Küçük, kararı verilmiş |
| Y6 | Misafir: Dalga 6 zengin görsel yüzeyi | Misafir | Y1 bitti, engel kalktı: hak ve kademe hazır, yalnız yüzey yazılacak — ve o gün fiyat sayfası eşlemesi de eklenir |
| Y7 | Süperadmin: destek görünümü ve kiracı olarak bakma | `/platform` | **Denetim kaydı ve zorluk şart** — bu yüzden en sonda |
| Y8 | `docs/117` M5–M9: kalan mobil borç | Her yüzey | Ekran ekran, jetonlar bittiği için artık dar kapsamlı |

## 5. Y7 neden en sonda ve neden zor olmalı

Kiracı olarak bakmak (impersonation) bir destek aracıdır ve **en tehlikeli**
süperadmin yeteneğidir: bir çalışanın, bir restoranın bütün verisini onun
gözüyle görmesidir.

Bu yüzden kolay olmamalı: her oturum bir sebep ister, süreli olur, kiracının
denetim günlüğüne **kiracının görebileceği biçimde** yazılır, ve o oturumda
yapılabilecekler kısıtlıdır. Kolay bir impersonation, bir gün kimsenin
hatırlamadığı bir erişim olur.

## 6. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6. §3'teki boşluk listesi bugünün süperadmin ihtiyacıdır; ürün
büyüdükçe (çok bölgeli dağıtım, iş ortağı portalı) yeniden ölçülür.
