<!--
    ÖLÇÜM VE PLAN BELGESİ — FF-223, 2026-09-08.

    Bu belge KOD DEĞİŞTİRMEZ ve hiçbir sayfayı yayına almaz. Yaptığı tek şey,
    bugün gerçekten ne olduğunu ölçmek ve eksiklerin sırasını yazmaktır.

    Her sayı gerçekten koşturulmuş bir komuttan ya da gerçekten atılmış bir
    HTTP isteğinden gelir. Ölçülemeyen şeyler §4'te AÇIKÇA "bilinmiyor" diye
    ayrılmıştır. Uydurulmuş sayfa sayısı, trafik, müşteri ya da metrik
    yoktur.
-->

# 137 — Kurumsal sayfa haritası: ne var, ne eksik, hangi sırayla

**Sahibin isteği (2026-09-08):** *"Mevcut tüm sayfaları ve eksik (ama
gerekli) sayfaları, yatırımcıya hazır bir girişim olarak lansmanı yarat."*
Ve: *"bir ara 15 sayfa hazır demiştin."*

Bu belge **haritayı** çıkarır. Sayfaların kendisini sonraki paketler yazar.

---

## 0. Ölçüm nasıl yapıldı

| Ne ölçüldü | Nasıl |
| --- | --- |
| Canlıda açılan kurumsal adresler | `curl -s -o /dev/null -w "%{http_code}" -L https://zabuno.com<yol>` — 34 adres tek tek |
| Canlı `sitemap.xml` ve `robots.txt` | Aynı sunucudan indirilip sayıldı |
| Canlı sayfaların gövdesi | `/`, `/about`, `/help` indirilip etiketlerinden arındırılarak okundu |
| Sunucuda yaşayan kurumsal rota sayısı | `php artisan route:list --method=GET --json`, `ExportStaticSiteCommand::SHELL_CONTROLLERS` süzgeciyle |
| Kütük satırları | Temiz bir SQLite'a `php artisan migrate` + `php artisan site:import-map`, sonra doğrudan SQL |
| Yayın durumu tavanı | Aynı veritabanında `php artisan site:sync-content-status` |
| İçeriği yazılmış sayfalar | `app/Infrastructure/Content/Pages/` dosyaları ve `ProductPageLibrary` |
| Ürünün gerçek yetenekleri | `app/Domain/`, `database/seeders/PlanCatalogueSeeder.php`, `config/media-quota.php`, `config/i18n.php` |
| Yatırımcıya gösterilebilecek olgular | `security/`, `evidence/`, `docs/124-YEDEK-TATBIKATI.md`, `.github/workflows/ci.yml`, `tests/` |

Ölçüm tarihi: **2026-09-08**. Depo temeli: `origin/main` (`339b0be3`).
Canlı ölçümler `https://zabuno.com` sunucusundan alındı; sunucudaki
sürümün bu temelle aynı olup olmadığı ölçülemedi (§4.1).

---

## 1. Bugün ne var

### 1.1 Canlıda gerçekten açılan kurumsal sayfalar — on dört

Sunucuda kurumsal kabuğu çizen ve desen taşımayan rota sayısı **14**. On
dördünün de canlıda **200** döndüğü tek tek ölçüldü.

| # | Adres | Mekanizma | Yayın durumu | İçeriği yazılmış mı | Canlı |
| --- | --- | --- | --- | --- | --- |
| 1 | `/` | Blade rotası (`FoundationStatusController`) | Yayında | Evet — kahraman, özellikler, nasıl çalışır, fiyat, SSS, iletişim | 200 |
| 2 | `/pricing` | Blade rotası (aynı denetleyici) | Yayında | Evet — plan kataloğundan | 200 |
| 3 | `/help` | Blade rotası (`ShowHelpController`) | Yayında | Evet — "ilk 15 dakika": CSV, karekod, fiyat | 200 |
| 4 | `/about` | Blade rotası (`ShowAboutController`) | Yayında | **Kısmen** — satıcı kimliği "Not entered yet" | 200 |
| 5 | `/contact` | Blade rotası (`ShowContactFormController`) | Yayında | Evet — hız sınırlı form | 200 |
| 6 | `/terms` | Blade rotası (`ShowLegalDocumentController`) | Yayında | Evet | 200 |
| 7 | `/privacy` | Aynı denetleyici | Yayında | Evet | 200 |
| 8 | `/kvkk` | Aynı denetleyici | Yayında | Evet | 200 |
| 9 | `/distance-sales` | Aynı denetleyici | Yayında | **Kısmen** — satıcı kimliği eksik | 200 |
| 10 | `/pre-information` | Aynı denetleyici | Yayında | **Kısmen** — satıcı kimliği eksik | 200 |
| 11 | `/delivery` | Aynı denetleyici | Yayında | **Kısmen** — satıcı kimliği eksik | 200 |
| 12 | `/refund-policy` | Aynı denetleyici | Yayında | Evet | 200 |
| 13 | `/cookies` | Aynı denetleyici | Yayında | Evet | 200 |
| 14 | `/marketing-consent` | Aynı denetleyici | Yayında | Evet | 200 |

Bunların dışında canlıda 200 dönen iki dosya daha var — `/robots.txt` ve
`/sitemap.xml` — ama ikisi de sayfa değildir, sayılmadı.

Kimlik yüzeyi de ayakta ve ölçüldü: `/login` 200, `/register` 200,
`/forgot-password` 200, `/app` 302 (oturum yoksa girişe atar). Bunlar
kurumsal sayfa değil, `docs/105` §4.4'ün ayrı adres uzayıdır.

### 1.2 Kütükte kayıtlı, ama canlıda hiçbiri açılmayan sayfalar — dört yüz iki satır

Temiz bir veritabanında `site:import-map` koşturuldu:

| Ölçüm | Sayı |
| --- | --- |
| Toplam kütük satırı | **402** |
| Türkçe (`tr`) | 386 |
| İngilizce (`en`) | 16 |
| Şablon satırı (`is_template`) | 73 |
| Dış bağlantı satırı (`is_external`) | 3 |
| **Gerçek sayfa** (şablon ve dış bağlantı hariç) | **326** |
| Bunlardan P0 | 110 · P1 174 · P2 118 |
| İçe aktarmadan sonra yayın durumu | **402'sinin de `planned`** |

`site:sync-content-status` koşturulduğunda **16 satır** bir kademe
ilerliyor ve tavanı `content_draft`. Kalan 386 satır `planned` kalıyor.

`content_draft`tan `published`a kadar **altı insan kapısı** daha var:
içerik incelemesi, tasarım incelemesi, SEO incelemesi, QA, onay, yayın
(`PagePublicationStatus`). Bir betik bu kapılardan geçmez ve bu bilerek
böyle (`docs/128` §4).

### 1.3 İçeriği gerçekten yazılmış on altı sayfa

`app/Infrastructure/Content/Pages/` altında **16 sınıf**, toplam **3.926
satır** içerik. Hepsi `en`; Türkçe yuva bilerek boş (`docs/118` E4).

| # | Sayfa | Kütük adresi | İçerik | Canlı |
| --- | --- | --- | --- | --- |
| 1 | Ürün genel bakış | `/en/product/` | Yazılmış | **404** |
| 2 | Karekod menü | `/en/product/qr-menu/` | Yazılmış | **404** |
| 3 | Menü yönetimi | `/en/product/menu-management/` | Yazılmış | **404** |
| 4 | Kategoriler | `/en/product/menu-management/categories/` | Yazılmış | **404** |
| 5 | Ürünler | `/en/product/menu-management/dishes/` | Yazılmış | **404** |
| 6 | Ürün fiyatları | `/en/product/menu-management/prices/` | Yazılmış | **404** |
| 7 | Stok durumu | `/en/product/menu-management/stock-status/` | Yazılmış | **404** |
| 8 | Masalar ve karekod | `/en/product/tables-and-qr-codes/` | Yazılmış | **404** |
| 9 | Analitik | `/en/product/analytics/` | Yazılmış | **404** |
| 10 | Zabuno AI | `/en/product/zabuno-ai/` | Yazılmış | **404** |
| 11 | Görsel ve medya | `/en/product/images-and-media/` | Yazılmış | **404** |
| 12 | Çoklu dil ve para birimi | `/en/product/languages-and-currency/` | Yazılmış | **404** |
| 13 | Çoklu şube | `/en/product/multiple-branches/` | Yazılmış | **404** |
| 14 | Tasarım ve marka | `/en/product/design-and-branding/` | Yazılmış | **404** |
| 15 | Çözümler | `/en/solutions/` | Yazılmış | **404** |
| 16 | Fiyatlandırma (kurumsal) | `/en/pricing/` | Yazılmış | **404** |

On altısının da canlıda 404 döndüğü tek tek ölçüldü. Türkçe karşılıkları
(`/tr/urun/qr-menu/` gibi) da 404 döndü — onların kütükte satırı var ama
**hiç metni yok**.

**404'ün sebebi kusur değil, kapıdır.** Üretimde yayınlanmamış her sayfa
404 döner ve bu bilerek böyle: yüzlerce adrese 200 ile aynı "hazırlanıyor"
metnini vermek soft-404 üretir ve alan adının tamamının kalitesini düşürür
(`PageGate`, `docs/105` §2.1).

### 1.4 "On beş sayfa hazır" iddiası — ölçüldü

**İddia bu hâliyle doğru değil.** Depoda 15 sayısına karşılık gelen hiçbir
ölçüm yok. Yakınındaki iki gerçek sayı şunlar ve ikisi farklı şeyi sayıyor:

| Sayı | Ne demek | Kanıt |
| --- | --- | --- |
| **14** | Canlıda bugün 200 dönen kurumsal sayfa | Rota ölçümü + 14 ayrı `curl` |
| **16** | İçeriği yazılmış ama canlıda 404 dönen sayfa | `ProductPageLibrary`, 16 sınıf |

`docs/131` §10 aynı 14 sayfayı 320 pikselde gerçek Chrome'la ölçmüş ve
"14 sayfa" diye yazmış. `docs/128` ve `docs/129` ise "on altı sayfa
yazılmış" diyor. On beş, ikisinin arasında kalan ve hiçbir ölçümün
üretmediği bir sayı.

**Doğru cümle:** on dört kurumsal sayfa canlıda açılıyor, on altı sayfanın
daha metni yazılmış ama hiçbiri yayında değil. İkisi toplanmaz — çünkü
biri "ziyaretçi bunu görebiliyor", öteki "bunun metni var" demek.

### 1.5 Zincir: bir sayfanın ziyaretçiye ulaşması için ne gerekiyor

`docs/128` §2 ve `docs/129` §2 bu zinciri kurmuştu. Bugünkü hâli:

| # | Halka | Nerede yaşar | Durum (2026-09-08) |
| --- | --- | --- | --- |
| 1 | Sayfanın metni | `ProductPageLibrary` | 16 sayfa için var, 310 sayfa için yok |
| 2 | Kütük kaydı | `content_pages` | Var — dağıtımda otomatik dolduruluyor (`docker/entrypoint.sh`) |
| 3 | Yayın durumu | Aynı tablonun alanı | **`planned` · sahibin kararını bekliyor** |
| 4 | Kapı | `ShowCorporatePageController` + `PageGate` | Çalışıyor |
| 5 | İlan (`sitemap.xml`) | `ShowSitemapController` | Çalışıyor — kütükten okuyor |

**Bugün kopuk olan tek halka 3.** Kod tarafında yapılacak bir şey yok;
yapılacak şey içerik yazmak ve yayın kararı vermek.

### 1.6 Canlı `sitemap.xml` — sekiz adres

İndirildi ve sayıldı: **8 adres**.

| Adres | Neden orada |
| --- | --- |
| `/` | Yaşayan sabit adres |
| `/terms`, `/privacy`, `/kvkk`, `/refund-policy`, `/cookies`, `/marketing-consent` | Yaşayan sabit yasal adresler |
| `/restaurant/zabuno-zabuno/menu/sm8ay3mg6f` | Yayınlanmış tek menü |

Kodda **11** sabit adres tanımlı; dördü (`/about`, `/distance-sales`,
`/pre-information`, `/delivery`) sitemap'e **girmiyor** çünkü satıcı
kimliği eksik. Kural sınıfın kendi kuralı: eksik bir sözleşme
indekslenmez.

Ayrıca ölçüldü ve `docs/129` §4'te zaten kayıtlı: `/pricing`, `/help` ve
`/contact` bugün 200 dönen canlı adresler ve sitemap'te **yoklar**.

**Sitemap'teki tek menü, Zabuno'nun kendi menüsü.** Yayınlanmış başka
kiracı menüsü ölçülmedi — canlıda görünen tek işletme `zabuno-zabuno`.

### 1.7 Bugün canlıda olmayan alt alan adları

`docs/106` §2 üç alt alan adı varsayıyor. Ölçüldü:

| Alt alan | Ölçüm |
| --- | --- |
| `app.zabuno.com` | **Çözülmüyor** (DNS yok) |
| `status.zabuno.com` | **Çözülmüyor** |
| `docs.zabuno.com` | **Çözülmüyor** |

Uygulama bugün `zabuno.com/app` altında yaşıyor ve çalışıyor. Site
haritasındaki iki `[EXTERNAL]` satır (`/tr/yardim/sistem-durumu/`,
`/tr/gelistiriciler/durum/`) bugün var olmayan bir yere işaret ediyor.

---

## 2. Ne eksik

İki ayrı liste. Karıştırılmaları, ikisinin de yanlış yazılmasına yol açar:
biri restoran sahibinin sorusuna, öteki yatırımcının sorusuna cevap verir.

### 2a. Ürünü satmak için gerekli olanlar

Kural: **ürünün gerçekten yaptığı şeyden türetildi.** Aşağıdaki her satırın
"olgusu" sütunu, o sayfanın anlatacağı şeyin depoda ölçülmüş karşılığıdır.

#### Somut yolculuk

Kırk masalık bir dönerci sahibi telefonundan "qr menü nasıl yapılır"
yazıyor. Bugün Zabuno'nun o aramada gösterebileceği tek sayfa ana
sayfadır; çünkü `sitemap.xml`te başka hiçbir kurumsal adres yok ve
kurumsal sayfaların hiçbiri açılmıyor. Adam ana sayfaya düşse bile
"karekodu bastıktan sonra fiyat değiştirebilir miyim" sorusunun cevabı
`/help` içinde bir paragraf olarak duruyor — kendi başlığı, kendi adresi
ve kendi arama karşılığı olan bir sayfası yok.

| Sayfa | Kim okuyacak | Hangi soruya cevap veriyor | Olgu bugün depoda var mı |
| --- | --- | --- | --- |
| Karekod menü (`/en/product/qr-menu/`) | İlk kez araştıran işletme sahibi | "Karekod menü tam olarak ne yapar, basılı kod ölür mü?" | **Var** — metni yazılmış; kalıcı token, yeniden yönlendirme ve yeniden etkinleştirme `QrDestination` alanında |
| Menü yönetimi ve dört alt sayfası | Menüyü kendi girecek kişi | "60 ürünü tek tek mi gireceğim?" | **Var** — CSV içe/dışa aktarma, kategori, ürün, fiyat, stok durumu `MenuCatalog` alanında; `/help` bunu zaten anlatıyor |
| Yayın ve geri alma | Fiyat listesini toptan değiştiren sahip | "Yanlış listeyi yayınlarsam ne olur?" | **Var** — `Publication` alanı, yayınlanmış sürümler ve geri dönüş; `/help` metninde ölçülü olarak duruyor. **Kütükte kendi satırı YOK** — açılması gereken yeni bir adres |
| Masalar ve karekod | Kırk masalık salon işleten sahip | "Her masaya ayrı kod nasıl basılır?" | **Var** — toplu karekod üretimi, PDF/PNG/SVG dışa aktarma; `qr.bulk-generation` hakkı `restaurant` kademesinde satılıyor |
| Analitik | Menüsünü iyileştirmek isteyen sahip | "Misafir neye bakıyor, neyi arayıp bulamıyor?" | **Var** — `analytics.reporting` hakkı, misafir olay taksonomisi (`docs/84`) |
| Görsel ve medya | Fotoğraf yükleyecek sahip | "Fotoğraflarım ne kadar yer tutar, kim görür?" | **Var** — karantinalı medya alımı, kota (`starter` 200 MB / `restaurant` 2 GB), `menu.rich-media` hakkı |
| Tasarım ve marka | Menüsünün kendi markası gibi görünmesini isteyen sahip | "Misafirin gördüğü sayfa benim markam mı olur?" | **Var** — `branding.custom` hakkı, `Branding` alanı, kontrast rampası testleri |
| Çoklu şube | İki üçüncü şubesini açan sahip | "Şubelerin menüsünü tek yerden yönetebilir miyim?" | **Kısmen** — `locations` ve merkezi menü kodda var; `opt-10` modül şartnamesi ayrı. Sayfa yazılmadan önce şubeler arası menü paylaşımının bugünkü sınırı ölçülmeli |
| Fiyatlandırma (kurumsal) | Karar aşamasındaki sahip | "Ne ödeyeceğim, hangi kademede ne açılıyor?" | **Var** — üç kademe, altı hak, gerçek tutarlar `PlanCatalogueSeeder`'dan; ayrıca bir kapı her hakkın en az bir kademede satılmasını zorluyor |
| Çözümler / işletme türleri | "Bu benim işim için mi?" diye soran sahip | "Kafe / fast food / zincir için ne değişiyor?" | **Kısmen** — `SolutionsPage` yazılmış; alt sayfaların (restoran, kafe, fast food, zincir) olgusu **ürün farkı değil, kullanım farkı**. Uydurma özellik farkı yazılmamalı |
| Sipariş (masadan) | Sipariş almak isteyen sahip | "Misafir masadan sipariş verebilir mi?" | **Var ama sayfası yok** — sipariş hattı uçtan uca çalışıyor ve `ordering.basic` `restaurant` kademesinde satılıyor (`docs/122` §2). Kütükte de, yazılmış içerikte de karşılığı yok. **Satılan ama anlatılmayan yetenek** |
| Puanlama | Misafir geri bildirimi isteyen sahip | "Misafir puan verebilir mi, ben görebilir miyim?" | **Kısmen** — misafir tarafı ve panel ucu var, **panel ekranı yok** (`docs/122` §2). Sayfa, sahibin göremediği bir şeyi anlatırdı |
| Zabuno AI | Fotoğraftan menü aktarmak isteyen sahip | "Fotoğraftan menü gerçekten çıkıyor mu?" | **Kısmen** — onay hattı tamamen çalışıyor ve testli; **sağlayıcı adaptörü gerçek API'ye karşı doğrulanmadı** (`docs/92`). Sayfa bu ayrımı yazmadan yayınlanamaz |
| Yardım merkezi (`/tr/yardim/` ağacı) | Tıkanmış kullanıcı | "Şu ekranda ne yapacağım?" | **Var** — bugünkü `/help` üç iş anlatıyor; kütükte 23 satırlık bir yardım ağacı planlı ve içeriği yok |
| Müşteri hikâyeleri, referanslar, menü örnekleri | Karar öncesi güven arayan sahip | "Benim gibi biri kullanıyor mu?" | **YOK.** Canlıda yayınlanmış tek menü Zabuno'nun kendi menüsü. **Bu üç sayfa, gerçek bir müşteri olana kadar yazılamaz** |
| Entegrasyonlar (POS ve adisyon) | POS'u olan sahip | "Kasa sistemimle konuşur mu?" | **YOK.** `opt-13` bir modül şartnamesidir, çalışan bir entegrasyon değil. Site haritasının kendi kuralı: desteklenmeyen entegrasyon sayfası yayınlanmaz (`docs/106` §1) |
| Yatırım getirisi hesaplayıcı | Fiyatı gerekçelendiren sahip | "Bu bana ne kazandırır?" | **Kısmen** — plan fiyatları gerçek. Ama tasarrufun kendisi varsayımdır; sayfa yalnız kullanıcının kendi girdiği sayılarla hesap yaparsa dürüst olur, "ortalama %X tasarruf" yazarsa olmaz |

**Yazılamayacak olanlar açıkça:** müşteri hikâyeleri, referans logoları,
başarı metrikleri, medyada Zabuno, topluluk — beşinin de olgusu bugün
sıfır. POS entegrasyon sayfaları da aynı kümede.

### 2b. Yatırımcıya hazır görünmek için gerekli olanlar

Burada dürüst olmak zorunlu: **yatırımcı sayfası uydurma metrikle
kurulmaz.** Bu depoda gerçekten yapılmış ve gösterilebilir iş var; aşağıda
yalnız o var.

Ayrıca bir sınır: **ayrı bir "yatırımcı" sayfası önerilmiyor.** Kütükte
öyle bir satır yok ve olmamalı. Bir erken aşama girişiminin yatırımcıya
gösterdiği şey bir tanıtım sayfası değil, **ürünün kendi güven yüzeyidir**
— ve o yüzeyin ağacı kütükte zaten planlı (`/tr/guven/`, sekiz satır).

#### Somut yolculuk

Bir yatırımcı `zabuno.com` açıyor. İlk yaptığı iki şey ölçülebilir: kimin
sattığına bakmak ve verinin nasıl korunduğuna bakmak. Bugün birincisinde
"Not entered yet" yazan bir tablo görüyor, ikincisinde ise hiçbir şey —
çünkü Güven Merkezi'nin sekiz satırı da kütükte `planned` ve 404 dönüyor.
Oysa arkada ASVS taraması yapılmış, kiracı yalıtımı testli, yedek
tatbikatı koşucusu yazılmış. **Yapılmış iş var, gösterilen yüzey yok.**

| Sayfa | Kim okuyacak | Hangi soruya cevap veriyor | Olgu bugün depoda var mı |
| --- | --- | --- | --- |
| **Satıcı kimliği** (`/about`, canlı) | Yatırımcı, ödeme kuruluşu, müşteri | "Bu şirket kim?" | **HAYIR — ve bu en sert engel.** Sayfa hazır, alanlar boş. Kod işi değil: `config` üzerinden girilecek yedi alan (ünvan, adres, MERSİS, vergi dairesi, vergi no, e-posta, telefon). Girilene kadar dört sayfa hem eksik görünüyor hem sitemap'e girmiyor |
| Güven Merkezi girişi (`/tr/guven/`) | Yatırımcı, kurumsal müşteri | "Güvenliği ciddiye alıyor musunuz?" | **Var** — alt sayfaların hepsinin olgusu aşağıda. Giriş sayfası onları toplar |
| Güvenlik yaklaşımı (`/tr/guven/guvenlik/`) | Yatırımcı, teknik due diligence | "Ne doğrulandı, ne doğrulanmadı?" | **Var** — `security/OWASP-ASVS-BASELINE.md`: ASVS 5.0.0 Level 1'e karşı bölüm bölüm inceleme, her "doğrulandı" satırının arkasında otomatik test. Belge kendi sınırını da yazıyor: sızma testi değil, sertifika değil, üçüncü taraf denetimi değil. **Sayfa bu sınırı aynen taşımalı** |
| Yedekleme ve kurtarma (`/tr/guven/yedekleme/`) | Yatırımcı, kurumsal müşteri | "Veriyi kaybedersem geri gelir mi?" | **Kısmen — ve eksiği yazılmalı.** `docs/124`: SQLite tatbikatı koşuyor, PostgreSQL turu CI'da ölçülüyor, medya tatbikatı koşuyor, kanıt ucu var. **Üretim sunucusunda tatbikat hiç yapılmadı ve düzenli yedek işi yok.** Sayfa "yedeklerimiz var" diyemez; "tatbikat mekanizması var, üretimde henüz koşmadı" der |
| Kiracı yalıtımı (`/tr/guven/altyapi/` içinde) | Yatırımcı, çok şubeli müşteri | "Başka bir restoran benim verimi görebilir mi?" | **Var** — on ayrı yalıtım/kaçış testi dosyası (menü, menü API, medya, yayın, karekod, toplu karekod, çalışma alanı yolculuğu) ve bir kanıt ucu (`TenantIsolationEvidenceApiTest`) |
| Olay yönetimi (`/tr/guven/olay-yonetimi/`) | Yatırımcı, kurumsal müşteri | "Bir şey bozulursa ne yapıyorsunuz?" | **YOK.** Yazılı bir olay müdahale prosedürü ölçülemedi. **Bu sayfa, prosedür yazılana kadar yazılamaz** |
| Sorumlu açıklama (`/tr/guven/sorumlu-aciklama/`) | Güvenlik araştırmacısı, yatırımcı | "Açık bulursam kime söylerim?" | **Kısmen** — depo açık kaynak ve iletişim formu var; ayrı bir güvenlik iletişim adresi ve süre taahhüdü ölçülmedi. Sayfa ancak o iki şey kararlaştırılınca yazılır |
| Alt işleyenler (`/tr/guven/alt-isleyenler/`) | KVKK/GDPR bakan alıcı, yatırımcı | "Verim kimlere gidiyor?" | **Kısmen** — ölçülen sağlayıcılar: Iyzico (ödeme), Mailgun (e-posta), OpenAI/Gemini (yapay zekâ, kasada). Barındırma sağlayıcısı ve konumu bu pakette **ölçülmedi**; sayfa eksik listeyle yazılmamalı |
| Uyum (`/tr/guven/uyum/`) | Yatırımcı, kurumsal müşteri | "Hangi mevzuata uyuyorsunuz?" | **Var ama dar** — mesafeli satış, ön bilgilendirme, teslimat, iptal/iade, çerez, elektronik ileti izni ve KVKK metinleri canlıda ve yazılmış. ISO/SOC gibi hiçbir sertifika **yok** ve sayfa öyle bir şey ima etmemeli |
| Erişilebilirlik (`/tr/kurumsal/erisilebilirlik/`) | Yatırımcı, kamu/kurumsal alıcı | "Herkes kullanabiliyor mu?" | **Var** — CI'da gerçek tarayıcıyla 320 piksel denetimi, sağdan sola denetimi, mantıksal yön kapısı, kabuk kaydırma kapısı. Dürüst tarafı da var: `docs/117` M5–M9'da 24 hikâyelik mobil borç açık |
| Sürüm notları / changelog (`/tr/gelistiriciler/changelog/`) | Yatırımcı, mevcut müşteri | "Bu ürün gerçekten ilerliyor mu?" | **Var** — Git geçmişi ve numaralandırılmış karar belgeleri (`docs/` altında 134 markdown dosyası). Yayımlanabilir bir changelog üretmek bir derleme işidir, yeni bir olgu değil |
| Mühendislik ilkeleri / karar kayıtları | Teknik yatırımcı | "Kararlar nasıl veriliyor?" | **Var ama dağınık** — `docs/03`, `docs/118` (ezilen yönergeler), `docs/adr/`. Bir ADR dizini var ama içinde tek dosya var; `docs/119` Faz 0'ın beş ADR'si yazılmamış. **Sayfa yazmadan önce ADR'lerin toplanması gerekir** |
| Sistem durumu (`status.zabuno.com`) | Yatırımcı, müşteri | "Şu an ayakta mı?" | **YOK** — alt alan adı çözülmüyor. Kütükteki iki satır bugün var olmayan bir yere işaret ediyor. **Ölçülmeden yazılamaz** |

**Yazılamayacak olanlar açıkça:** kullanıcı sayısı, gelir, büyüme,
elde tutma, "sektör lideri" benzeri hiçbir cümle. Ölçülen tek kiracı
menüsü Zabuno'nun kendisi; bu sayıların hiçbirinin bugün karşılığı yok.

---

## 3. Sıra — bağımlılığa göre

Sahibin verdiği sıra korunuyor: **kabuk → ana sayfa → ötekiler.** İçi
ölçümle dolduruldu.

### Adım 0 — Satıcı kimliği (kod işi değil, sahibin işi)

Her şeyin önünde duruyor ve tek satır kod gerektirmiyor. Yedi alan
girilene kadar:

- `/about`, `/distance-sales`, `/pre-information`, `/delivery` "Not
  entered yet" gösteriyor,
- aynı dört sayfa `sitemap.xml`e girmiyor,
- ve bir yatırımcının ilk baktığı yer eksik görünüyor.

Bu adım atlanırsa, sonraki her sayfa eksik bir kimliğin üstüne yazılır.

### Adım 1 — Kabuk

**Neden ilk:** kütükteki 326 sayfanın hepsi aynı kabuğu kullanacak.
Kabuk sonradan değişirse 326 sayfa birden değişir; önce doğrultulursa bir
kez değişir.

Bugün ölçülen durum: kurumsal kapı `SiteShell`ten besleniyor ve
`/tr/...` sayfaları bugünkü canlı sayfalarla aynı üst çubuğu ve altbilgiyi
alıyor. Yani kabuk **var**. Eksik olan üç şey ölçüldü:

1. **Üst menü, kütükten değil koddan geliyor.** Bugünkü bağlantılar
   `#features`, `#how-it-works` gibi ana sayfa çapaları. `/tr/` uzayı
   açıldığında bu çapalar başka bir sayfadan çalışmaz.
2. **Dil seçici yok.** `supported_locales` dokuz dil tanıyor,
   `shipped_locales` yalnız `en`. `/tr/` ve `/en/` yan yana yaşayacaksa
   ziyaretçinin arasında geçiş yapacağı bir denetim gerekir.
3. **Ekmek kırıntısı ve ilgili sayfalar çalışıyor** ama yalnız yayınlanmış
   sayfalara bağlanıyor — yani bugün hiçbir şeye. Bu doğru davranış; not
   ediliyor ki sonraki paket "kırıldı" sanmasın.

### Adım 2 — Ana sayfa

**Neden ikinci:** kütükte `/tr/` ve `/en/` satırları var ve **ikisi de
`home` türünde, ikisinin de metni yok**. Ana sayfa, kırıntı ağacının
köküdür: altındaki her sayfa ona bağlanır.

Burada ölçülmüş bir karar noktası var ve sonraki paket ona çarpacak:

> Bugün `/` adresinde çalışan, içeriği yazılmış bir ana sayfa **zaten
> var** (`resources/views/public/home.blade.php`). Kütükteki `/tr/` ve
> `/en/` satırları ise boş. İki ana sayfa olamaz.

`docs/105` §4.1 mevcut adreslerin tek atımlı 301 ile dil dizinine
taşınmasını planlıyor ama göç listesi yazılmamış. **Bu göç, ana sayfa
paketinin kendi kararıdır** ve üç seçeneği var: (a) `/` kalır, `/tr/` ve
`/en/` ona 301 eder, (b) `/` dil dizinine 301 eder, (c) `/` dil seçer ve
yönlendirir. Seçim ölçümle yapılmalı — bugün `/` sitemap'te, robots'ta ve
canonical etiketinde adı geçen tek adres.

### Adım 3 — İçeriği yazılmış on altı sayfa yayına

**Neden üçüncü:** metinleri zaten var. Kabuk ve ana sayfa durduktan sonra
bu on altı sayfa, altı insan kapısından geçirilerek yayınlanabilir ve
**hiç yeni metin yazılmadan** sitede on altı sayfa açılır.

Sıra `docs/119` Faz 6'nın sırasını izler: karekod menü → menü yönetimi →
masalar ve karekod → tasarım ve marka → görsel ve medya → çoklu dil →
çoklu şube → analitik → Zabuno AI → çözümler → fiyatlandırma → ürün genel
bakış.

**Ölçülmüş çelişki, karara muhtaç:** `docs/105` §4.1 "Türkçe tamamlanana
kadar `/en/` sayfaları noindex ve sitemap dışı" diyor. Bu kural **kodda
yok** — `PageGate` yayınlanmış bir sayfaya dile bakmadan `index,follow`
veriyor. `x-default` tarafında karar zaten güncellenmiş
(`ResolveLocaleAlternates`: kaynak dil artık İngilizce, `docs/118` E4).
Yani ya `docs/105` §4.1'in bu yarısı da düşürülür, ya on altı sayfa
yayınlanıp indekslenmez. **Bu bir ürün kararıdır ve sahibindir.**

### Adım 4 — Güven Merkezi (yatırımcı yüzeyi)

**Neden dördüncü ve neden müşteri sayfalarından önce:** olgusu bugün
hazır olan ikinci küme bu. Sekiz satırın beşi bugün yazılabilir
(güvenlik, yedekleme, uyum, kiracı yalıtımı, erişilebilirlik), üçü
(olay yönetimi, sorumlu açıklama, alt işleyenler) önce bir karar ya da bir
ölçüm bekliyor.

### Adım 5 — Yardım merkezi ağacı ve satılan ama anlatılmayan yetenekler

Sipariş, puanlama ve yayın/geri alma. Üçünün de kodu var; ilkinin ve
üçüncüsünün sayfası kütükte bile yok. Yardım ağacının 23 satırı da burada.

### Adım 6 — Türkçe içerik

`shipped_locales` bugün yalnız `en`. Türkçe kurumsal içerik yazmak
**çeviri değildir** (`docs/105` §2.2 madde 9) ama ürün arayüzünün diliyle
çelişmemesi gerekir. Bu adım kendiliğinden başlatılmaz.

### Hiçbir zaman: olgusu olmayan sayfalar

Müşteri hikâyeleri, referanslar, başarı metrikleri, POS entegrasyonları,
sistem durumu, medyada Zabuno. Bunlar sıranın sonunda değil, sıranın
**dışında**: gerçek bir müşteri, gerçek bir entegrasyon ya da gerçek bir
durum sayfası olmadan yazılamazlar.

---

## 4. Bu paketin ölçemedikleri — açıkça

1. **Üretim sunucusundaki kütüğün bugünkü hâli bilinmiyor.** Sunucuya
   erişim yok. Bir kurumsal adresin 404 dönmesinin iki sebebi olabilir —
   kayıt yok ya da kayıt var ama `planned` — ve ikisi dışarıdan birebir
   aynı görünür. Ölçülen şey, on altı adresin de canlıda 404 döndüğüdür;
   sebebi değil. (`docs/128` §6.1 aynı sınırı kaydetmişti.)

2. **Yayınlanmış kiracı menüsü sayısı yalnız sitemap'ten okundu.** Sitemap
   yalnız indekslenebilir menüleri listeler; indekslenmemeyi seçmiş bir
   kiracının menüsü orada görünmez. Yani "tek yayınlanmış menü var"
   denemez; **"sitemap'te tek menü görünüyor ve o Zabuno'nun kendisi"**
   denebilir.

3. **Barındırma sağlayıcısı ve veri konumu ölçülmedi.** Alt işleyenler
   sayfası bu ölçüm yapılmadan yazılamaz.

4. **Olay müdahale prosedürü aranmadı, bulunamadı denemez.** Bu pakette
   `security/` ve `docs/` altında böyle bir belgeye rastlanmadı; kapsamlı
   bir arama yapılmadı. "Yok" değil, **"bu pakette bulunamadı"**.

5. **Sayfa içeriklerinin kalitesi ölçülmedi.** On altı sayfanın metni
   "yazılmış" sayıldı çünkü sınıfları var ve `ProductPageLibraryTest` aynı
   sorunun iki sayfada sorulmasını kırıyor. Metinlerin bir restoran
   sahibine ne kadar iyi cevap verdiği **okunmadı**.

---

## 5. Kendi gerekçe süresi

`docs/109` §8.6'nın kuralı burada da geçerlidir ve bu belge tam olarak o
tuzağa açıktır: **yukarıdaki her "olgusu yok" satırı bugün için doğrudur
ve kendi gerekçesini taşır. Gerekçe düştüğünde satır da düşer.**

İki tür satır var ve ikisini karıştırmak pahalıya patlar:

- **Yetenek YOK.** "POS entegrasyonu" ve "sistem durumu sayfası" böyle.
  Bunlar için yapılacak iş, sayfayı yazmak değil, yeteneği yapmaktır.
- **Yetenek VAR, kimse fark etmemiş.** Bu daha tehlikelidir ve bu pakette
  **üç örneği çıktı**:
  - **Sipariş.** Uçtan uca çalışıyor ve `restaurant` kademesinde
    satılıyor. Kütükte satırı yok, yazılmış içerikte satırı yok. Yani
    para karşılığı satılan bir yetenek, sitede hiçbir yerde
    anlatılmıyor.
  - **Yayın ve geri alma.** `/help` içinde bir paragraf; kendi adresi,
    kendi başlığı ve kendi arama karşılığı yok. Oysa "yanlış listeyi
    yayınlarsam ne olur" sorusu bir satın alma sorusudur.
  - **Güven Merkezi.** Sekiz satırın beşinin olgusu **bugün hazır** —
    ASVS taraması, on yalıtım testi dosyası, yedek tatbikatı koşucusu, CI'daki
    erişilebilirlik kapıları. Kimse bu olguların bir sayfaya karşılık
    geldiğini sormamıştı.

Bu yüzden bu belgeyi okuyan herkesin sorması gereken soru şudur:
*gerekçe bugün hâlâ doğru mu?* Özellikle şu üç satır için, çünkü üçü de
kendi başına düşebilir:

| Satır | Gerekçe | Ne olursa düşer |
| --- | --- | --- |
| "Satıcı kimliği yok" | Yapılandırma alanları boş | Sahip yedi alanı girer — kod değişmeden düşer |
| "Zabuno AI yarım" | Sağlayıcı adaptörü gerçek API'ye karşı doğrulanmadı | Anahtar kasaya girer ve bir tur ölçülür |
| "Müşteri hikâyesi yazılamaz" | Zabuno dışında yayınlanmış menü ölçülmedi | İlk gerçek restoran menüsünü yayınlar |

Ve bu belgenin kendi sayıları da bir tarih taşır: **14 canlı sayfa, 16
yazılmış sayfa, 402 kütük satırı, 8 sitemap adresi — 2026-09-08.** Bir
sonraki paket bu sayıları yeniden ölçmeden kullanmamalıdır.
