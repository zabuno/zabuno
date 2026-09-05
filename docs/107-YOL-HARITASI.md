# 107 — Yol haritası: fazlar, bağımlılıklar ve "bitti" tanımları

> **BU BELGE ÜRÜN YOL HARİTASIDIR.** Yönetişim aşamalarının ayrı ve daha eski
> bir sayacı var (`docs/17`, sekiz aşama); ikisi farklı soruları cevaplar ve
> birbirinin yerine geçmez. Sahibe ilerleme raporlanırken payda **burasıdır**;
> `docs/17` bir aşamanın çıkış kapısının kanıtlanıp kanıtlanmadığını sayar.
> Paket bazlı durum için `docs/26`. (Çelişki denetimi FF-161, 2026-09-05.)

Sahibin talebi (2026-09-04): *"yapılmayanların planını yap. faz'lara böl.
faz 1, olmazsa olmaz'lar nelerdir? faz 2, GTM için gereklilikler. faz 3,
kurumsallaşmak için gereklilikler. faz 4-5-…-10, …20, 30, 50."*

## 0. Bu belge nasıl okunur

- **Fazlar tarihe göre değil BAĞIMLILIĞA göre sıralı.** Faz 2'nin bir maddesi
  Faz 1'in bir maddesine dayanıyorsa önce o gelir. Buraya tarih yazılmadı;
  yazılsaydı uydurulmuş olurdu.
- **Sayaç tek ve sabit: 10 faz.** "Ufuk 20/30/50" birer faz değil, yön
  bildirimidir; sayaca girmez ve söz vermez.
- **Her fazın bir ÜRÜN VAADİ var.** Bir faz, teknik maddeleri bitti diye değil,
  o vaat tutulabildiğinde biter. Yönetişim kapılarının yeşile dönmesi ürün
  hazırlığı değildir ve öyle sunulmaz.
- **Durum ölçüldü, varsayılmadı.** Aşağıdaki "bugün" satırları depo taranarak
  yazıldı; bir madde "var" diyorsa gerçekten çalışıyor demektir.

**Sayaç: 0/10 tamamlandı, 1/10 aktif.**

---

## Faz 1 — Olmazsa olmaz: ilk parayı almadan önce

**Ürün vaadi:** *Bir restoran Zabuno'ya para ödeyip menüsünü yayınlayabilir ve
biz o parayı yasal olarak tahsil edebiliriz.*

Bu fazın maddeleri "iyi olurdu" değil; biri eksikken tahsilat yapmak ya
imkânsız ya da hukuka aykırıdır.

| # | Madde | Bugün |
| --- | --- | --- |
| 1.1 | **Gerçek ödeme alma.** | ❌ Depoda yalnız `IyzipaySandboxGateway` var. Sandbox para tahsil etmez. Üretim sağlayıcısı, 3D Secure akışı, başarısız ödeme ve iade yolu yazılmalı. |
| 1.2 | **Yasal metinler.** | ❌ `/terms`, `/privacy`, `/kvkk` bugün "hazırlanıyor" yazan yer tutucular (`public/legal.blade.php` 13 satır). Uzaktan satış için mesafeli satış sözleşmesi, ön bilgilendirme formu, iptal ve iade, KVKK aydınlatma, çerez politikası **ve tercih ekranı**, elektronik ileti izni gerekir. |
| 1.3 | **Abonelik yaşam döngüsü.** | ◐ Plan kataloğu ve abonelik okuma var; iptal, plan yükseltme/düşürme, başarısız ödemede askıya alma ve geri dönüş yolu yok. |
| 1.4 | **Fatura.** | ❌ Tahsilatın karşılığında belge kesilmeli; e-arşiv/e-fatura yolu yok. |
| 1.5 | **Yedekleme ve geri yükleme TATBİKATI.** | ◐ Kanıt uçları var (`/security/evidence/backup-restore`); gerçek bir geri yükleme denemesi ve kaydı yok. Denenmemiş bir yedek, yedek değildir. |
| 1.6 | **Destek kanalı ve yanıt taahhüdü.** | ◐ İletişim formu var; taahhüt ve takip yok. |
| 1.7 | **İlk 15 dakika.** | ◐ Yardım makalesi var; ürün içi rehberli kurulum yok. |

**Bitti ne demek:** Gerçek bir restoran kartını girer, para hesaba geçer, fatura
düşer, sözleşmeyi okuyabilir, iptal edebilir; ve biz o restoranın verisini
kaybedersek geri getirebildiğimizi bir tatbikatla göstermiş oluruz.

**kullaniciYolculugu:** Kadıköy'deki bir kebapçı fiyatlandırma sayfasından
"Pro"yu seçer, kartını girer, 3D Secure ekranından geçer, e-postasına faturası
düşer, menüsünü yayınlar ve masalarına kart basar. Bugün bu yolculuk **ödeme
adımında** durur.

---

## Faz 2 — GTM: pazara çıkış

**Ürün vaadi:** *Bizi tanımayan bir restoran sahibi aramadan bulur, ne
yaptığımızı anlar, deneyebilir ve ölçebiliriz.*

| # | Madde | Bugün |
| --- | --- | --- |
| 2.1 | **Kurumsal sitenin P0 sayfaları** Türkçe yazılır (`docs/106` P0 listesi: ana sayfa, QR menü, menü yönetimi, masa ve QR, tasarım, medya, çoklu dil, çoklu şube, analitik, Zabuno AI, işletme türleri, fiyatlandırma, örnek menüler, yardım, hakkımızda, iletişim). | ◐ 386 yol kütükte `planned`; kapı ve hazırlanıyor ekranı çalışıyor, içerik yok. |
| 2.2 | **Yaşayan adreslerin `/tr/` göçü.** `/pricing`, `/help`, `/contact`, `/terms`, `/privacy`, `/kvkk` tek atımlı 301 ile taşınır; sitemap ve robots aynı anda güncellenir. | ❌ Politikası `docs/105` §4.1'de, uygulaması yok. |
| 2.3 | **Ölçüm sözleşmesi.** Sayfa görüntüleme, form gönderimi ve CTA tıklaması GA4/Metrica/GTM'e **kiracı bazında** düşer. | ◐ Misafir menüsünde var; kurumsal sitede yok. |
| 2.4 | **Keşif altyapısı.** Türe ve dile bölünmüş sitemap index, `hreflang`, `x-default` (İngilizce tamamlanana kadar Türkçe canonical). | ◐ Tek sitemap var; hreflang hiç yok. |
| 2.5 | **Demo ve teklif formları** bir yere düşer ve takip edilir. | ❌ |
| 2.6 | **Canlı örnek menüler.** Gerçek bir restoranın izinli menüsü. | ❌ Uydurma örnek yayınlanmaz. |
| 2.7 | **Fiyatlandırma sayfası** gerçek planlar ve SSS ile. | ◐ Planlar veritabanından okunuyor; sayfa metni eksik. |
| 2.8 | **Yardım merkezi P0 makaleleri.** | ◐ Bir makale var. |

**Bitti ne demek:** Google'da "qr menü" araması bizi bulur, sayfa bir soruya
cevap verir, demo formu bir insana ulaşır ve hangi kanalın kaç kayıt getirdiğini
kiracı bazında görebiliriz.

---

## Faz 3 — Kurumsallaşma: büyük müşterinin sorduğu sorular

**Ürün vaadi:** *Bir zincirin satın alma ya da hukuk birimi bize soru
sorduğunda, cevap bir sayfada hazır durur.*

| # | Madde | Bugün |
| --- | --- | --- |
| 3.1 | **Güven merkezi**: güvenlik yaklaşımı, altyapı ve süreklilik, yedekleme politikası, olay yönetimi, alt işleyen listesi, uyum, sorumlu açıklama. | ❌ Kütükte planlı. |
| 3.2 | **Sözleşmeler**: DPA (veri işleme), SLA (hizmet seviyesi), kabul edilebilir kullanım, üçüncü taraf lisansları. | ❌ |
| 3.3 | **KVKK hakları ürün içinde**: kiracı verisini dışa aktarma ve silme, denetim kaydı. | ◐ Medya denetim kaydı var; hesap düzeyinde yok. |
| 3.4 | **Durum sayfası** ve olay geçmişi. | ❌ `status.zabuno.com` planlı. |
| 3.5 | **Erişilebilirlik beyanı** ve WCAG 2.2 AA denetimi. | ◐ Kurallar kodda ve testlerde; beyan ve dış denetim yok. |
| 3.6 | **Rol ve yetki matrisi** belgesi; müşteri onu okuyup kendi ekibini kurabilsin. | ◐ İzinler kodda; belge yok. |
| 3.7 | **Teklif → sözleşme → fatura** akışı. | ❌ |

**Bitti ne demek:** Bir zincirin hukukçusu "veri nerede tutuluyor, kim
erişebiliyor, silmek istersek ne oluyor" diye sorduğunda üç bağlantı
gönderebiliriz.

---

## Faz 4 — Ölçek: zincir ve franchise

**Vaat:** *On şubeli bir zincir menüsünü merkezden yönetir, şubeye özel fiyat
verir ve şubeleri karşılaştırır.*

Merkezi menü, şubeye özel fiyat, rol ve yetki, merkezi raporlama, franchise
standartları. Çoklu şube temeli **bugün var**; eksik olan merkezîleştirme ve
karşılaştırma.

---

## Faz 5 — Entegrasyonlar

**Vaat:** *Restoranın zaten kullandığı sistemle konuşuruz.*

POS ve adisyon (SambaPOS, Menulux, NarPOS, Adisyo, RobotPOS), pazar yerleri
(Yemeksepeti, Getir, Trendyol), muhasebe ve e-dönüşüm (Logo, Paraşüt, e-fatura),
ödeme sağlayıcıları. **Kural:** entegrasyon sayfası ancak entegrasyon gerçekten
çalışıyorsa yayınlanır (`docs/105`).

---

## Faz 6 — İçerik motoru ve programatik SEO

**Vaat:** *Aramadan gelen trafik kendi kendini büyütür.*

Blog, rehberler, karşılaştırmalar, sözlük, ücretsiz araçlar (QR üretici, ROI
hesaplayıcı, menü sağlığı testi), P1/P2 sayfaları. **Ürün adreslerinin
sitemap'e girmesi burada** — binlerce URL kendi kalite kapısını (açıklaması
olmayan ürün indekslenmez) ve sayfalanmış bir sitemap index'ini gerektirir.

---

## Faz 7 — Sipariş

QR ile masaya sipariş, gel-al, paket servis. Ürünün "menü" olmaktan çıkıp
"sipariş" olduğu eşik; mutfak tarafı olmadan tek başına açılmaz.

---

## Faz 8 — Ödeme (masada öde)

Pay at Table, hesap bölme, bahşiş, ön ödeme. Faz 7 olmadan anlamsız: ödenecek
bir hesap olması gerekir.

---

## Faz 9 — Misafir etkileşimi

Garson çağırma, geri bildirim, yorum yönetimi, CRM, sadakat, rezervasyon.
Sipariş verisi olmadan sadakat programı boş bir kart olur.

---

## Faz 10 — Operasyon

Mutfak ekranı (KDS), self-servis kiosk, garson el terminali, kurye takip, stok
ve maliyet. Donanım ve saha desteği gerektirir; en pahalı faz budur ve en son
gelir.

---

## Ufuk — söz değil, yön

Bunlar faz değildir ve sayaca girmez. Yönü kaybetmemek için yazılıdır.

- **Ufuk 20 — Platform:** herkese açık API, webhook, SDK, iş ortağı programı,
  partner portalı. Ürünün başkalarının üstüne bina kurabildiği eşik.
- **Ufuk 30 — Uluslararasılaşma:** çoklu para birimi, bölgesel fiyat, ülkeye
  göre mevzuat, İngilizce ve sonrasında diğer diller. **Çeviri kilidi bu ufka
  kadar kapalı kalır** ve yalnız sahibin açık `ÇEVİRİLERE BAŞLA` komutuyla
  açılır.
- **Ufuk 50 — Ekosistem:** food hall / multi-vendor, pazar yeri, üçüncü taraf
  uygulama mağazası.

---

## Faz sırasının gerekçesi

Sıra keyfî değil; her faz bir öncekinin ürettiği şeye dayanıyor:

1. **Para almadan** hiçbir şeyin sürdürülebilirliği yok → Faz 1.
2. **Müşteri bulmadan** ölçek sorunları hayalî → Faz 2.
3. **Büyük müşteri sormadan** kurumsal belge yazmak erken; ama ilk zincir
   geldiğinde hazır olmak gerekir → Faz 3.
4. **Zincir gelmeden** merkezi yönetim kimseye lazım değil → Faz 4.
5. **Entegrasyon**, müşterinin "mevcut sistemim var" itirazına cevaptır; itiraz
   duyulmadan hangi entegrasyonun önce geleceği bilinemez → Faz 5.
6. **İçerik motoru** uzun vadeli ve bileşik getirili; erken başlamak iyidir ama
   ürün anlatılabilir olmadan yazılamaz → Faz 6.
7–10. **Sipariş → ödeme → misafir → operasyon** zinciri, her biri bir
   öncekinin verisine dayanır.

---

## Tek cümlelik özet

Bugün ürün **menüyü yayınlayıp masaya kart basabiliyor.** Eksik olan, o işi
para karşılığı ve yasal olarak yapabilmek (Faz 1) ve onu duyurabilmek (Faz 2).
Geri kalan her şey bu ikisinin üstüne kurulur.
