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
| 1.1 | **Gerçek ödeme alma.** | ◐ **Kod hazır, canlı tahsilat sahibin anahtarına bağlı** (`docs/123`, FF-197). `IyzipayGateway` (üretim adresi sabit, anahtar KASADAN), üç kapılı kip anahtarı (`IYZICO_MODE` + süperadmin `PUT /admin/settings/billing-mode` + kasa), kendi kendine ödeme (`POST /workspaces/{w}/checkout`, tutar sunucudan, fatura profili zorunlu), `/api/webhooks/iyzico`, başarısız ödemede sebep + tekrar yolu, süperadmin iadesi (defterde ters kayıt, dönem düşülür). Hepsi SAHTE geçitle test edildi; gerçek kartla gerçek para henüz hareket etmedi. 3D Secure Iyzico'nun barındırdığı Checkout Form sayfasındadır; kodda ayrı akış yok. Bitti sayılması için: kasaya üretim anahtarı, üretim env'inde `live`, anahtar açık, Iyzico panelinde webhook adresi — ve ilk gerçek tahsilat + iadenin ölçülmesi. |
| 1.2 | **Yasal metinler.** | ◐ **Dokuz belge + iki kurumsal sayfa yayında** (FF-198 `docs/124`, FF-216 `docs/131`): hizmet koşulları, gizlilik, KVKK, mesafeli satış (**sürüm 0.2, 18 bölüm**), ön bilgilendirme (**0.2, 13 bölüm**), **teslimat/ifa (yeni)**, iptal-iade, çerez politikası ve tercih ekranı, ticari ileti izni; **`/about` (yeni)** ve `/contact` artık satıcının adres/telefon/e-postasını gösteriyor. Mesafeli Sözleşmeler Yönetmeliği'nin saydığı başlıkların hepsi metinde (cayma hakkının kullanılamayacağı hâller, ifaya derhâl başlama onayı, süre/yenileme, iletişim aracının bedeli, kabul edilen ödeme yöntemleri, şikâyet yolu, metnin dili dâhil); hakem heyeti sınırı ve KDV oranı **atıfla**, rakamla değil. **Ödeme adımındaki iki onay bağlandı:** ayrı ve önceden işaretlenmemiş kutular, boş kutuyla 422, `consent_records`a `checkout` + `immediate_performance` satırları. **Eksik sözleşme artık sessiz değil:** şirket bilgisi boşken o üç belge + `/about` uyarı bandıyla açılır, `noindex` döner, sitemap'ten düşer ve **canlı kipte tahsilat 409 `seller_identity_missing` ile reddedilir** (sandbox açık). 320 pikselde iki durumda ayrı ayrı ölçüldü: yeni/değişen sayfalarda ve sekiz ödeme paneli hikâyesinde bulgu sıfır. **Eksik:** hukukçu incelemesi (`LEGAL_REVIEWED_AT`), şirket bilgisinin yedi alanı `.env`'de boş, Türkçe metinler (çeviri değil — hukukçudan gelecek; `docs/131` §8 altyapı için üç madde bırakıyor). |
| 1.3 | **Abonelik yaşam döngüsü.** | ◐ **Eksik yarısı indi** (`docs/134`, FF-219). Ödemeyle oluşturma/uzatma zaten vardı (`docs/123` K5). Şimdi: panelden **iptal** (ödenmiş dönem sürer, yenileme durur) ve **iptalden cayma** (yeniden ödeme yok); **plan düşürme** dönem sonunda yürürlüğe girer ve **fark iade edilmez** — bu, yayınlanmış İptal ve İade Politikasının "başlamış dönemin ücreti iade edilmez" cümlesiyle ölçülerek uyumlandı; kaybedilecek yetenek onaydan önce adıyla gösterilir. Ödenmiş dönemin ortasında ucuz planı satın almak 422 ile reddedilir (sessiz düşürme kapatıldı). Ödeme gelmediğinde **ödemesiz süre** (`billing.subscription.grace_days`, varsayılan 7) sonra **askı**; başarılı ödeme askıyı elle müdahale olmadan kaldırır. Misafir hiçbir aşamada etkilenmez (yayına donmuş haklar). Dördü de `platform_audits`'e düşer. **Kalan eksik:** sahip ödemesiz süreye girdiğini yalnız panele bakarsa öğrenir — e-posta hatırlatması yok. |
| 1.4 | **Fatura.** | ◐ **Belge var, e-arşiv yolu sahibin dışarıdan alacağı bir şeye bağlı** (FF-215, `docs/130`). Başarılı her tahsilat numaralı ve DEĞİŞMEZ bir belge doğuruyor (`invoices`; `updated_at` yok), iade karşı belge kesiyor (`credit_note`, aynı seriden sıradaki numara), belge A4 PDF olarak indiriliyor ve panelde 320 pikselde ölçülmüş bir bölge var. Numara sıralı ve BOŞLUKSUZ; tahsis veritabanının atomik artırımıyla, belgeyle aynı işlemin içinde yapılıyor ve son güvence `unique(series, number)` — gerçek eşzamanlılık PostgreSQL ayağında ölçülüyor, yerelde sonuç "bilinmiyor". **Eksik:** (a) e-arşiv/e-fatura GÖNDERİMİ — port var (`EArchiveGatewayPort`), adaptör YOK ve olmayan uygulama sessizce başarılı dönmüyor, açıkça duruyor; kayıtlı bir entegratör sözleşmesi ya da GİB portalı sahibin işidir ve bugün yok. (b) Şirket bilgisi `.env`'de boş (FF-198) — belge bunu SÖYLÜYOR ("not yet provided"), uydurmuyor; doldurulana kadar tam bir ticari fatura değil. (c) KDV oranı yapılandırmada BOŞ; girilene kadar belge vergi ayrımı göstermiyor (uydurma oran yok). |
| 1.5 | **Yedekleme ve geri yükleme TATBİKATI.** | ◐ (2026-09-06, `docs/124`) Tatbikat kodu iki motor için var — SQLite (geliştirici makinesi) ve PostgreSQL (üretim motoru: `pg_dump` + geçici veritabanına `pg_restore`, satır sayısı ve içerik özeti eşleşmesi) — ve `storage/app` medya kökünü de kapsıyor (tar + SHA-256 manifesti). Koşucu bağlantıya göre seçiliyor; günlük zamanlama tanımlı; kanıt ucu koşucu türünü ve medya kaydını dönüyor. CI'da PostgreSQL üzerinde tatbikat gerçek `pg_dump`/`pg_restore` ile koşuyor (`PostgresBackupRestoreDrillTest`, `DB_CONNECTION=pgsql` işi; yerelde PostgreSQL yok, sonuç orada "bilinmiyor"). **Üretim sunucusunda ilk tatbikat henüz yapılmadı;** üretimde hiçbir kanıt satırı yok ve `db-backups` hacmine yazan bir iş yok. Denenmemiş bir yedek, yedek değildir. |
| 1.6 | **Destek kanalı ve yanıt taahhüdü.** | ◐ Kanal var (`docs/125`): kamu formu ve panel `support_requests`'e yazar, her talep referans alır (`ZB-XXXXX`), gönderene referanslı alındı e-postası çıkar ve sonucu kayda geçer, durum panelde görünür; süperadmin uçları var. **Eksik:** taahhüdün SAYISI sahibin kararı — verilene kadar hiçbir yüzey süre yazmaz; süperadmin cevap ekranı yok (`docs/122` Y7); destek adresi (`SUPPORT_EMAIL`) boş. |
| 1.7 | **İlk 15 dakika.** | ◐ Ürün içi rehberlik var (FF-202): beş adımın hedef ekranında ilk-kez ipucu, marka→şube ve şube→menü "sıradaki adım" kutuları, panelden yardım makalesine bağlantı, ilk yayına kadar geçen süre sunucuda ölçülüyor ve panoda görünüyor. Ölçüm `docs/101` §4a: Home'dan ilk yayına 12 dokunuş → 10, ilk dokunuştaki çıkmaz sokak kapandı. Gerçek acemiyle **ölçülmedi**; `docs/110` §7 süre hedefi hâlâ bilinmiyor. |

**Bitti ne demek:** Gerçek bir restoran kartını girer, para hesaba geçer, fatura
düşer, sözleşmeyi okuyabilir, iptal edebilir; ve biz o restoranın verisini
kaybedersek geri getirebildiğimizi bir tatbikatla göstermiş oluruz.

"Fatura düşer" iki ayrı eşiktir ve karıştırılmaz: (1) sahibin indirip
muhasebecisine verebileceği numaralı bir belge — **bu var** (1.4); (2) o
belgenin GİB'e ulaşan bir e-arşiv/e-faturaya dönüşmesi — bu bir kod eksiği
değil, yapılmamış bir entegratör sözleşmesidir.

**kullaniciYolculugu:** Kadıköy'deki bir kebapçı fiyatlandırma sayfasından
"Pro"yu seçer, kartını girer, 3D Secure ekranından geçer, e-postasına faturası
düşer, menüsünü yayınlar ve masalarına kart basar. Bugün bu yolculuk kod
düzeyinde ödeme adımını geçer ve sandbox'ta prova edilir (`docs/123`);
gerçek kartla tahsilat kasadaki üretim anahtarına bağlıdır. Fatura adımı
ARTIK GEÇİLİYOR (FF-215): kebapçı panelden `2026-000001` numaralı belgesini
PDF olarak indirir. Ama muhasebecisi o belgeye baktığında Zabuno'nun ünvanını
ve vergi numarasını göremez — belge bunu "not yet provided" diye söyler — ve
belge hiçbir yere e-arşiv olarak gönderilmemiştir. Yolculuk artık **e-arşiv
eşiğinde** durur ve o eşik sahibin bir entegratörle sözleşmesine bağlıdır
(`docs/130`).

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
| 3.2 | **Sözleşmeler**: DPA (veri işleme), SLA (hizmet seviyesi), kabul edilebilir kullanım, üçüncü taraf lisansları. | ◐ **Dört belge yayında** (FF-228, `docs/140`): `/data-processing`, `/sla`, `/acceptable-use`, `/third-party-licenses` — mevcut yasal belge sistemine eklendi (kütüphane 9 → 13), sürüm ve "pending legal review" notuyla. Alt işleyen listesi kasadan ve ölçüm yapılandırmasından **ölçülüyor** (bugün 1 kayıt: netcup GmbH, Karlsruhe); lisans listesi kilit dosyalarından **türetiliyor** (13 + 7 doğrudan, 100 + 494 dolaylı). **SLA'da hiçbir rakam yok ve olamaz**: çalışma süresi ölçülmüyor (3.4 ❌) — dört `SLA_*` değeri sahibin kararını bekliyor. Kalan: hukukçu incelemesi ve altbilgi bağlantısı. |
| 3.3 | **KVKK hakları ürün içinde**: kiracı verisini dışa aktarma ve silme, denetim kaydı. | ✅ Ayarlar → Çalışma alanı: 50 bölümlük arşiv (JSON + CSV + README), gecikmeli ve geri alınabilir silme, silinen satırlar sayılıyor; kayıt Ayarlar → Denetim izi'nde. Yedi tablo yasal saklama gerekçesiyle silinmiyor ve ekran onları adıyla sayıyor (`docs/138`). Kişisel hesabın silinmesi hâlâ iletişim formunda. |
| 3.4 | **Durum sayfası** ve olay geçmişi. | ❌ `status.zabuno.com` planlı. |
| 3.5 | **Erişilebilirlik beyanı** ve WCAG 2.2 AA denetimi. | ◐ Kurallar kodda ve testlerde; beyan ve dış denetim yok. |
| 3.6 | **Rol ve yetki matrisi** belgesi; müşteri onu okuyup kendi ekibini kurabilsin. | ◐ İzinler kodda; belge yok. |
| 3.7 | **Teklif → sözleşme → fatura** akışı. | ❌ |

**Bitti ne demek:** Bir zincirin hukukçusu "veri nerede tutuluyor, kim
erişebiliyor, silmek istersek ne oluyor" diye sorduğunda üç bağlantı
gönderebiliriz.

**Bugün üçüncü sorunun cevabı bir ekran** (`docs/138`, 2026-09-08). Aynı
paket birinci sorunun eksik cevabını da düzeltti: yasal metinler artık
verinin Almanya'da barındırıldığını ve yedeklerin aynı sunucuda tutulduğunu
söylüyor — önce yalnız "sağlayıcı yurt dışında olabilir" yazıyordu. İkinci
soru (kim erişebiliyor) hâlâ 3.6'ya bağlı: izinler kodda, belge yok.

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
