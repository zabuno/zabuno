# 122 — Yarım kalanlar: ölçüm ve dalga planı

**Sahibin kararı (2026-09-05):** *"Biz başka konulara geçtik ama yarım kalan
geliştirmeler var. Restoran menüsü yarım kaldı, restoran admin panelinde işler
yarım kaldı, `/platform` süper admin paneli eksik. Bunları da paralel agents
ile geliştir."*

Bu belge tahmin taşımaz. Her satır depodan **ölçüldü**.

## 1. Misafir menüsü — `docs/114`'ün altı dalgası

| Dalga | Ne | Durum | Kanıt |
| --- | --- | --- | --- |
| 1 — Marka kimliği ve okunabilirlik | Ton, biçim, kontrast | **Bitti** | `app/Domain/Branding/`, ramp testleri |
| 2 — Bulmak | Arama, sesli arama, filtreler | **Bitti** | `GuestMenuFindTest`, sıfır dış istek |
| 3 — Favoriler | Cihazda favori işaretleme | **YOK** | Kodda karşılığı yok |
| 4 — Puanlama | Oy, eşikli gösterim, sahip yanıtı | **Bitti** | `docs/116` P4–P6 |
| 5 — Sepet ve sipariş | Sepet, gönderim, dürüst ret | **Bitti** | `GuestCartTest`, sipariş ucu |
| 6 — Fotoğraf ve plan kademesi | Zengin görsel, plan farkı | **YOK** | `menu.rich-media` hakkı hiç tanımlanmadı |

**Kalan iki dalga.** Üçüncüsü küçük ve karar zaten verilmiş (favori
**cihazda** yaşar — favori bir kolaylıktır, bir varlık değil; kalıcılık için
kimlik istemek orantısız). Altıncısı bir plan kademesi açıyor ve fiyatlandırma
tarafına dokunuyor.

## 2. Restoran paneli — on üç bölüm var, eksik olan ne?

Bölümler: Pano, Menü, Karekod, Yayın, Medya, Analitik, Siparişler, Şubeler,
Marka, Ekip, Ayarlar, Profil, Faturalandırma.

**Bölüm sayısı bir şey ölçmez.** Ölçülen eksikler:

| Eksik | Kanıt | Etkisi |
| --- | --- | --- |
| Puanlama panelde çizilmiyor | `docs/116` P5 panel ucu var, ekran yok | Sahip misafirin verdiği oyu göremiyor |
| Sipariş bölümü kenar çubuğunda 12. sırada | Bölüm kaydının `order` alanı | Günlük operasyon, en dipte |
| `ordering.basic` hiçbir planda yok | Plan kataloğu | Sipariş **satılamıyor** |
| `menu.rich-media` hakkı yok | Hak listesi | Dalga 6 satılamaz |
| Mobil borç: 24 hikâye | `mobile-ux-audit.baseline.json` | `docs/117` M5–M9 |

**En sert olanı üçüncüsü.** Sipariş hattı uçtan uca çalışıyor — misafir
gönderiyor, garson onaylıyor, mutfak görüyor — ama hiçbir plan bu hakkı
vermiyor. Yani **çalışan bir yetenek satılamıyor durumda.** Bu bir kod
eksiği değil, bir katalog eksiği ve bir gün "neden kimse kullanmıyor?"
sorusuna yanlış cevap verdirir.

## 3. `/platform` süperadmin — ölçülmüş kapsam

**Bugün var olan:** on yedi uç, beş ekran bölümü (ticari, kimlik bilgileri
kasası, entegrasyonlar, planlar, abonelikler). Ekran katmanı **105 satır**.

| Uç ailesi | Uç sayısı | Ekranı var mı |
| --- | --- | --- |
| Çalışma alanları (liste, abonelik, elle ödeme) | 3 | Kısmen |
| Planlar (liste, oluştur, etkinleştir) | 3 | Var |
| Kimlik bilgileri kasası | 3 | Var |
| Bağlantılar (liste, ekle, sonda, durum) | 4 | Var |
| Modüller | 1 | **Yok** (`docs/111` planlı) |
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
5. **Modüller ekranı yok** (`docs/111`).
6. **Denetim günlüğü ekranı yok.** Kayıt yazılıyor, okunacak yer yok.

## 4. Dalgalar — sıra, yayılma alanına göre

| # | Paket | Yüzey | Neden bu sırada |
| --- | --- | --- | --- |
| Y1 | Plan kataloğu: `ordering.basic` + `menu.rich-media` | Süperadmin + fiyat | **Çalışan bir yetenek satılamıyor.** Kod değil katalog işi, en ucuz ve en yüksek etki |
| Y2 | Süperadmin: kiracı ayrıntısı, kullanıcı yönetimi, denetim günlüğü | `/platform` | Süperadminin ilk gün ihtiyacı; hepsi mevcut veriyi OKUYOR, yeni veri üretmiyor |
| Y3 | Süperadmin: modüller ekranı (`docs/111`) | `/platform` | Plan hazır, veri hazır |
| Y4 | Panel: puanlama ekranı | Restoran paneli | Uç var, ekran yok — en kısa yol |
| Y5 | Misafir: Dalga 3 favoriler (cihazda) | Misafir menüsü | Küçük, kararı verilmiş |
| Y6 | Misafir: Dalga 6 zengin görsel + plan kademesi | Misafir + plan | Y1'e bağlı |
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
