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

> **YENİDEN ÖLÇÜM (2026-09-11, ROADMAP-TRUTH-01).** Yukarıdaki "durum ölçüldü,
> varsayılmadı" sözü bir kez tutulmamıştı: bu belgenin dört satırı gerçeğin
> gerisinde kalmıştı ve **ikisi var olan bir şeye "yok" diyordu.**
>
> | Satır | Yazıyordu | Ölçüldü |
> | --- | --- | --- |
> | 1.5 Yedekleme | "`db-backups` hacmine yazan bir iş yok" | #376 birleşti; komut, zamanlama ve hacim bağlaması var (üretim koşumu hâlâ yok) |
> | 2.8 Yardım merkezi | "Bir makale var" | Dört makale, iki dilde — diskte 8 dosya |
> | 3.1 Güven merkezi | ❌ "Kütükte planlı" | `/trust` canlıda **200**, sekiz bölüm |
> | 3.4 Durum sayfası | ❌ "`status.zabuno.com` planlı" | `zabuno.github.io/status` canlıda **200**, beş uç nokta 09-08'den beri |
>
> Dağılım bu yüzden değişti: **2 ✅ · 16 ◐ · 4 ❌** (önce 2/14/6). Hiçbir satır
> ✅'e çekilmedi — ikisi ❌'ten ◐'ye geldi, çünkü yüzey var ama kalem kapanmadı.
>
> **Neden önemli:** bu belge sahibe raporlanan PAYDADIR. Bir kalem "yok"
> derken varsa, sahip ya parasını zaten sahip olduğu şeye harcar ya da elindeki
> şeyi müşterisine söyleyemez. Bir yol haritası, ölçülmediği gün yol haritası
> olmaktan çıkar ve bir dilek listesine döner.
>
> **2.1 bu pakette ÖLÇÜLMEDİ** ve bu yüzden değiştirilmedi: kütükteki
> `planned`/`published` sayımı çalışan bir uygulama gerektiriyor, tahminle
> yazmak düzeltilen kusurun aynısını üretirdi. Sonucu "bilinmiyor"dur.

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
| 1.3 | **Abonelik yaşam döngüsü.** | ◐ **Eksik yarısı indi** (`docs/134`, FF-219). Ödemeyle oluşturma/uzatma zaten vardı (`docs/123` K5). Şimdi: panelden **iptal** (ödenmiş dönem sürer, yenileme durur) ve **iptalden cayma** (yeniden ödeme yok); **plan düşürme** dönem sonunda yürürlüğe girer ve **fark iade edilmez** — bu, yayınlanmış İptal ve İade Politikasının "başlamış dönemin ücreti iade edilmez" cümlesiyle ölçülerek uyumlandı; kaybedilecek yetenek onaydan önce adıyla gösterilir. Ödenmiş dönemin ortasında ucuz planı satın almak 422 ile reddedilir (sessiz düşürme kapatıldı). Ödeme gelmediğinde **ödemesiz süre** (`billing.subscription.grace_days`, varsayılan 7) sonra **askı**; başarılı ödeme askıyı elle müdahale olmadan kaldırır. Misafir hiçbir aşamada etkilenmez (yayına donmuş haklar). Dördü de `platform_audits`'e düşer. **Hatırlatma indi** (BILL-GRACE-REMINDER-01): ödemesiz süreye giren aboneliğin **bugünkü her sahibi** günde bir koşan `zabuno:send-grace-reminders` ile kendi hizmet e-postasını alır — fatura profilindeki adrese değil, çünkü ödemeyi yapabilen rol sahiptir. Metin iki TARİH taşır (dönemin bitişi, ücretli özelliklerin açık kalacağı son gün) ve `/app#billing`; yayınlanmış menünün korunduğunu söyler. Damga abonelik + dönemin `ends_at`'i + alıcıdan oluşur: zamanlanmış koşuda aynı dönemde tekrar yok (`withoutOverlapping`), yeni dönem yeniden haber verir. **Sınır dürüstçe:** damga gönderim BAŞARDIKTAN sonra basıldığı için benzersiz indeks çift DEFTER satırını önler, çift POSTAYI değil; komut ELLE ve eşzamanlı tetiklenirse hatırlatma "en çok bir kez" değil "en az bir kez" davranır. `log` sürücüsü gönderim sayılmaz ve damga basmaz. Diğer dört evre (`Active`/`Cancelling`/`Ended`/`Suspended`) sessizdir. Sözleşme: `docs/134` K15. |
| 1.4 | **Fatura.** | ◐ **Belge var, e-arşiv yolu sahibin dışarıdan alacağı bir şeye bağlı** (FF-215, `docs/130`). Başarılı her tahsilat numaralı ve DEĞİŞMEZ bir belge doğuruyor (`invoices`; `updated_at` yok), iade karşı belge kesiyor (`credit_note`, aynı seriden sıradaki numara), belge A4 PDF olarak indiriliyor ve panelde 320 pikselde ölçülmüş bir bölge var. Numara sıralı ve BOŞLUKSUZ; tahsis veritabanının atomik artırımıyla, belgeyle aynı işlemin içinde yapılıyor ve son güvence `unique(series, number)` — gerçek eşzamanlılık PostgreSQL ayağında ölçülüyor, yerelde sonuç "bilinmiyor". **Eksik:** (a) e-arşiv/e-fatura GÖNDERİMİ — port var (`EArchiveGatewayPort`), adaptör YOK ve olmayan uygulama sessizce başarılı dönmüyor, açıkça duruyor; kayıtlı bir entegratör sözleşmesi ya da GİB portalı sahibin işidir ve bugün yok. (b) Şirket bilgisi `.env`'de boş (FF-198) — belge bunu SÖYLÜYOR ("not yet provided"), uydurmuyor; doldurulana kadar tam bir ticari fatura değil. (c) KDV oranı yapılandırmada BOŞ; girilene kadar belge vergi ayrımı göstermiyor (uydurma oran yok). |
| 1.5 | **Yedekleme ve geri yükleme TATBİKATI.** | ◐ (2026-09-06, `docs/124`) Tatbikat kodu iki motor için var — SQLite (geliştirici makinesi) ve PostgreSQL (üretim motoru: `pg_dump` + geçici veritabanına `pg_restore`, satır sayısı ve içerik özeti eşleşmesi) — ve `storage/app` medya kökünü de kapsıyor (tar + SHA-256 manifesti). Koşucu bağlantıya göre seçiliyor; günlük zamanlama tanımlı; kanıt ucu koşucu türünü ve medya kaydını dönüyor. CI'da PostgreSQL üzerinde tatbikat gerçek `pg_dump`/`pg_restore` ile koşuyor (`PostgresBackupRestoreDrillTest`, `DB_CONNECTION=pgsql` işi; yerelde PostgreSQL yok, sonuç orada "bilinmiyor"). **DURAN YEDEK İNDİ** (2026-09-11, BACKUP-PRODUCE-01, #376): `zabuno:backup:database` veritabanının TAMAMINI `pg_dump --format=custom` ile döker, arşivi `pg_restore --list` ile OKUYARAK doğrular, SHA-256 özetini yanına kardeş dosya olarak bırakır ve dosyayı tek bir `rename()` ile final adına taşır — yarım döküm `*.dump` adını almaz. Zamanlama 03:30 (çöp boşaltımından sonra, tatbikattan önce) ve `app` servisi artık `db-backups` hacmini `/backups` olarak bağlıyor; o bağlama olmadan döküm konteyner katmanına yazılır ve ilk deploy'da silinirdi. Hiçbir dosya SİLİNMEZ: retention yazılmadan silme yeteneği de yok. Kapılar: `DatabaseBackupCommandTest` (7 madde), `DEPLOY-BACKUP-LANDS-16`. **Bu, "tatbikat edilen yedek" ile "duran yedek" ayrımının kapanmasıdır** — tatbikat kendi dökümünü siler, bu komut bırakır. **Hâlâ eksik ve bu yüzden ◐:** (a) üretim sunucusunda ilk tatbikat yapılmadı, üretimde hiçbir kanıt satırı yok; (b) `/backups` altında bugün ölçülmüş TEK BİR arşiv dosyası yok — zamanlama tanımlı olmak, işin koşmuş olması değildir; (c) arşiv aynı sunucuda duruyor: offsite kopya, retention ve PITR yazılmadı, yani sunucunun kendisi kaybolursa geri dönülebileceği bugün kanıtlanmış değil. Denenmemiş bir yedek, yedek değildir. |
| 1.6 | **Destek kanalı ve yanıt taahhüdü.** | ◐ Kanal var (`docs/125`): kamu formu ve panel `support_requests`'e yazar, her talep referans alır (`ZB-XXXXX`), gönderene referanslı alındı e-postası çıkar ve sonucu kayda geçer, durum panelde görünür; süperadmin uçları var. **Cevap yüzeyi geldi (SUPPORT-REPLY-01):** süperadmin kuyruk satırından düz metin cevap yazıp tek düğmeyle gönderir; cevap GERÇEKTEN dışarı çıkarsa —ve yalnız o zaman— talep `answered` olur ve ilk yanıt damgası bir kez düşer, taşıyıcı yoksa 409 ve satır değişmez. Uç exactly-once DEĞİLDİR: yinelenen gönderimi eleyen bir anahtar yok, iki eşzamanlı POST iki e-posta üretir. **Eksik:** taahhüdün SAYISI sahibin kararı — verilene kadar hiçbir yüzey süre yazmaz; destek adresi (`SUPPORT_EMAIL`) hâlâ boş, bu yüzden müşteri cevaba "cevapla" diyemez. |
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
| 2.7 | **Fiyatlandırma sayfası** gerçek planlar ve SSS ile. | ✅ Planlar, tutarlar ve haklar katalogdan; her planın kime uygun olduğu, hiçbir planda olmayan altı şey ve yedi gerçek soru sayfada (`docs/139`, FF-239). Kalan: `/pricing` hâlâ sitemap'te değil (2.2/2.4) ve satın alma bu sayfadan başlamıyor (Faz 1.1). |
| 2.8 | **Yardım merkezi P0 makaleleri.** | ◐ **Dört makale, iki dilde** (2026-09-09/10, #359/#360/#361): `first-15-minutes`, `a-photo-on-a-dish`, `nothing-changed-for-my-guests`, `table-cards-and-areas` — her biri `resources/help/en/` ve `resources/help/tr/` altında, yani diskte 8 dosya. Bu satır 2026-09-11'e kadar "bir makale var" diyordu; ölçüldüğünde dördü de yerindeydi. **Eksik ve ölçüldü (2026-09-11):** `ff-230-yardim-merkezi` dalında dokuz İngilizce makale duruyor; main'de o dokuzun dördü var. Main'de olmayan beşi: `add-your-restaurant`, `guest-ratings`, `menus-that-change-during-the-day`, `plan-and-invoices`, `who-can-do-what`. **Doğrudan birleştirilemezler ve sebebi önemli:** dalda o beşinin yalnız İngilizcesi var (dalın Türkçe tarafında tek dosya var, `first-15-minutes`), oysa `tr` 2026-09-08'den beri `shipped_locales` içinde — yani beş makaleyi olduğu gibi almak, ürünün yayınlanmış bir dilinde beş boş sayfa açardı ve katalog bütünlüğü kapısı bunu reddeder. Kalan iş "birleştir" değil, **"beş makalenin Türkçesini yaz, sonra birleştir"**. |

**Bitti ne demek:** Google'da "qr menü" araması bizi bulur, sayfa bir soruya
cevap verir, demo formu bir insana ulaşır ve hangi kanalın kaç kayıt getirdiğini
kiracı bazında görebiliriz.

---

## Faz 3 — Kurumsallaşma: büyük müşterinin sorduğu sorular

**Ürün vaadi:** *Bir zincirin satın alma ya da hukuk birimi bize soru
sorduğunda, cevap bir sayfada hazır durur.*

| # | Madde | Bugün |
| --- | --- | --- |
| 3.1 | **Güven merkezi**: güvenlik yaklaşımı, altyapı ve süreklilik, yedekleme politikası, olay yönetimi, alt işleyen listesi, uyum, sorumlu açıklama. | ◐ **SAYFA YAYINDA — bu satır 2026-09-11'e kadar ❌ diyordu ve yanlıştı** (FF-252, #341). `/trust` canlıda **200** dönüyor (`ShowTrustCentreController`, `public.trust`) ve sekiz bölüm taşıyor: nasıl okunur; sertifika/denetim raporu/rozet; **verinin nerede tutulduğu** (altyapı); **veriye başka kim dokunuyor** (alt işleyen listesi, kasadan ÖLÇÜLÜYOR); hizmet seviyesi (olay bildirim süresi dâhil); **yedeğin tatbikatı nasıl ölçülüyor**; kendiniz koşturabileceğiniz güvenlik kanıtı; bu sayfanın cevaplayamadığı sorular. Oturum istemez, veritabanına dokunmaz; her cümle yapılandırmadan ve kasanın DURUM portundan doğar — sayfayı çizen kod bir sırrın değerine erişemez. **Neden ✅ değil:** (a) olay yönetimi yalnız *bildirim süresi* olarak var ve o süre girilmemiş (`SLA_INCIDENT_NOTIFICATION_MINUTES` boş) — müdahale/tırmandırma süreci yazılmadı; (b) sorumlu açıklama (responsible disclosure) yüzeyi `/trust` içinde kendi bölümü değil; (c) uyum bölümü bugün "hiçbir sertifika yok" diyor, ki doğrudur ama kalem "uyum" başlığını bununla kapatmaz. |
| 3.2 | **Sözleşmeler**: DPA (veri işleme), SLA (hizmet seviyesi), kabul edilebilir kullanım, üçüncü taraf lisansları. | ◐ **Dört belge yayında** (FF-228, `docs/140`): `/data-processing`, `/sla`, `/acceptable-use`, `/third-party-licenses` — mevcut yasal belge sistemine eklendi (kütüphane 9 → 13), sürüm ve "pending legal review" notuyla. Alt işleyen listesi kasadan ve ölçüm yapılandırmasından **ölçülüyor** (bugün 1 kayıt: netcup GmbH, Karlsruhe); lisans listesi kilit dosyalarından **türetiliyor** (13 + 7 doğrudan, 100 + 494 dolaylı). **SLA'da hiçbir rakam yok ve olamaz**: çalışma süresi ölçülmüyor (3.4 ❌) — dört `SLA_*` değeri sahibin kararını bekliyor. Kalan: hukukçu incelemesi ve altbilgi bağlantısı. |
| 3.3 | **KVKK hakları ürün içinde**: kiracı verisini dışa aktarma ve silme, denetim kaydı. | ✅ Ayarlar → Çalışma alanı: 50 bölümlük arşiv (JSON + CSV + README), gecikmeli ve geri alınabilir silme, silinen satırlar sayılıyor; kayıt Ayarlar → Denetim izi'nde. Yedi tablo yasal saklama gerekçesiyle silinmiyor ve ekran onları adıyla sayıyor (`docs/138`). Kişisel hesabın silinmesi hâlâ iletişim formunda. |
| 3.4 | **Durum sayfası** ve olay geçmişi. | ◐ **DURUM SAYFASI YAYINDA — bu satır 2026-09-11'e kadar ❌ diyordu ve yanlıştı.** `https://zabuno.github.io/status/` bugün **200** dönüyor: "Independent availability checks for Zabuno, hosted separately from the application", **beş uç nokta 2026-09-08'den beri** izleniyor. Uygulamadan AYRI barındırılması doğru karardır — uygulama düştüğünde durum sayfası da düşerse, tam ihtiyaç duyulduğu anda susar. Ürün ona ADIYLA atıf yapıyor: `/trust` ve `/sla` sayfaları adresi gösteriyor ve aynı cümlede sınırını da söylüyor — gözlemler yalnız kaydedilen dönemi ve o beş ucu kapsar, tam kullanıcı yolculuğunu ölçmez ve **sözleşmesel bir kullanılabilirlik garantisi oluşturmaz**. **Neden ✅ değil:** (a) `status.zabuno.com` CNAME'i bugün de boş (`dig` → kayıt yok), yani sayfa markanın adresinden değil GitHub adresinden yayınlanıyor; (b) olay GEÇMİŞİ yok — sayfa uç nokta ölçümü yapıyor, ilan edilmiş bir olay kaydı tutmuyor; (c) dört `SLA_*` ticari değeri hâlâ sahibin kararında, o yüzden `/sla` rakamsız. **Zincir sanıldığı kadar kilitli değil:** ölçüm kaynağı ARTIK VAR; kalan halka CNAME ve dört ticari değerdir. |
| 3.5 | **Erişilebilirlik beyanı** ve WCAG 2.2 AA denetimi. | ◐ Kurallar kodda ve testlerde; beyan ve dış denetim yok. |
| 3.6 | **Rol ve yetki matrisi** belgesi; müşteri onu okuyup kendi ekibini kurabilsin. | ◐ **Belge var ve KODDAN üretiliyor** (`docs/139`, FF-227). 23 izin × 5 kiracı rolü; her rolün yapabildikleri kadar YAPAMADIKLARI da listeli; süperadmin ayrı satır (izin listesi taşımaz, `EnsurePlatformSuperAdmin` kapısının arkasındaki 16 okuma + 13 yazma ucudur) ve destek penceresi ayrı satır (tek kiracıya çivili; kiracı ayrıntısı ve aboneliği salt okunur, aynı önekteki iki yazma ucu ödeme defterine yazar ve dondurulmuş listededir; kiracı kimliğine bürünme ucu 0). Üretici `php artisan authorization:matrix`; kod ile belgenin ayrıştığı gün `AuthorizationMatrixArtifactTest` kırılır. Ürün içinde de okunur: Ekip ekranındaki rol kartı aynı üretilmiş veriden "yapabilir/yapamaz" listelerini çizer (320 pikselde ölçüldü, bulgu sıfır). **Eksik:** şubeye özel rol yok (Faz 4) ve müşteri kendi rolünü tanımlayamaz; "bu izin hangi ekranı açar" haritası yok — matris dosya sayısıyla ölçüm verir, ekran adıyla değil. |
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
