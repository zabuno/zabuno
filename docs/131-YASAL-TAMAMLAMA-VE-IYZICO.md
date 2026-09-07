# 131 — Yasal tamamlama ve Iyzico başvurusu: sözleşme artık bir sözleşme

**Paket:** FF-216 (`docs/107` Faz 1.2). **Kaynak dil:** İngilizce
(`docs/118` E4). **Durum:** on bir sayfa yayında; **metinler hâlâ hukukçu
incelemesi bekliyor** ve `LEGAL_REVIEWED_AT` dolana kadar her sayfa bunu
üstte söylemeye devam ediyor. **Şirket bilgisi hâlâ girilmedi** — ama artık
bu, sayfanın satır aralarında değil, en üstünde yazıyor.

---

## 0. Bir yolculukla: restoran sahibi Pro'yu seçti, üç gün sonra vazgeçti

Kadıköy'deki kebapçı fiyat sayfasını açıyor. Planların altında, ödeme
adımına gitmeden **"Ücretli planlar Iyzico üzerinden kartla ödenir; başka
bir ödeme yöntemi yok"** yazıyor ve yanında ön bilgilendirme formuna giden
bir bağlantı var. Havale yapmayı planlıyorsa bunu şimdi öğreniyor, kartını
çıkardıktan sonra değil.

Panelde "Pro"yu seçiyor, fatura bilgilerini giriyor ve **iki onay kutusu**
görüyor. İkisi de boş; hiçbiri önceden işaretli değil:

1. *"Ön Bilgilendirme Formu'nu ve Mesafeli Satış Sözleşmesi'ni okudum ve
   kabul ediyorum."*
2. *"Ödemem onaylanır onaylanmaz hizmetin başlamasını istiyorum ve
   başladıktan sonra cayma hakkımın sona erdiğini okudum."*

Kutuların üstünde okuması gereken dört belge kendi satırlarında duruyor:
ön bilgilendirme, mesafeli satış, teslimat/ifa, iptal-iade. Kutuları
işaretlemeden "Proceed to payment"a basarsa, ödeme sayfasına **gitmiyor**:
her boş kutu kendi satırında sebebini yazıyor ve sunucuya hiçbir istek
gitmiyor. İşaretleyip basınca Iyzico'nun kendi sayfasına gidiyor, kartını
oraya giriyor (bizim hiçbir ekranımızda kart alanı yok) ve dönüyor.

O anda **onay defterine üç satır** düşüyor: ön bilgilendirme formunu kabul
etti, mesafeli satış sözleşmesini kabul etti, ve **ayrıca** ifaya derhâl
başlanmasını istedi. Üçüncü satır ayrı, çünkü ayrı bir hukuki olgu —
cayma hakkının ne zaman sona erdiğinin kanıtı o satır.

**Üç gün sonra vazgeçiyor.** `/distance-sales` sayfasını açıp
"Cayma hakkının kullanılamayacağı hâller" bölümünü okuyor: hizmet
elektronik ortamda anında ifa edilen bir hizmet ve kendi isteğiyle ifaya
başlandığı için cayma hakkı sona ermiş. Bunu ödeme adımında ayrı bir
kutuda onaylamıştı ve o onay kayıtlı. Aynı sayfa ona ne YAPABİLECEĞİNİ de
söylüyor: iptal-iade politikası, ödediği dönemin sonuna kadar hizmetin
açık kalacağını ve iptalin yenilemeyi durduracağını yazıyor; iptal yolu
bugün hâlâ iletişim formu (`docs/107` 1.3).

**Bugün bu yolculuğun neresinde duruyoruz:** sözleşme metni tamam, onay
alınıyor ve kaydediliyor, sayfalar 320 pikselde okunuyor. Duran tek yer,
sözleşmenin **tarafı**: şirket bilgileri girilmediği için mesafeli satış
sayfası "bu belge henüz tamam değil" diyen kırmızı bir bantla açılıyor ve
canlı kipte tahsilat hiç başlamıyor.

---

## 1. Ölçülen boşluk (2026-09-07, paket öncesi)

| Konu | Önce |
| --- | --- |
| `/distance-sales` | 200 dönüyor, altbilgide, sitemap'te. **Sekiz yerde "not yet provided"**, bir yerde "pending legal review". Sayfa tamam görünüyor. |
| Cayma hakkının kullanılamayacağı hâller | Ayrı başlık YOK; cayma bölümünün içinde tek cümle |
| İfaya derhâl başlama onayı | Metinde geçiyor, **alınmıyor ve kaydedilmiyor** |
| Sözleşmenin süresi ve yenilenmesi | Başlık yok |
| Uzaktan iletişim aracının bedeli | Hiç yok |
| Kabul edilen ödeme yöntemleri | Hiç yok (ne sözleşmede ne sitede) |
| Şikâyet başvurusu | Uyuşmazlık başlığının içine gömülü |
| Metnin dili / Türkçe sürüm | Okuyucuya hiç söylenmiyor |
| Teslimat / ifa koşulları | Ayrı sayfa YOK |
| Hakkımızda | Sayfa YOK |
| İletişim | Form VAR, **adres/telefon/e-posta YOK** |
| Ödeme adımındaki onay | `ConsentRecorder::recordCheckout` yazılmış, **hiç çağrılmıyor** (FF-197 ödeme akışını yazdı, sözleşmeye hiç değmedi) |
| Satıcı kimliği boşken canlı tahsilat | Açık — alıcının bilgisi zorunlu, satıcınınki hiç sorulmuyor |

---

## 2. Hangi sayfa vardı, hangisi eklendi

| Adres | Durum | Ne yapar |
| --- | --- | --- |
| `/terms` `/privacy` `/kvkk` `/cookies` `/marketing-consent` | **Vardı**, dokunulmadı | FF-198 |
| `/refund-policy` | **Vardı**, dokunulmadı | İptal yolu bugün hâlâ iletişim formu (`docs/107` 1.3) |
| `/distance-sales` | **Vardı, TAMAMLANDI** — 9 bölüm → 18, sürüm 0.1 → **0.2** | Aşağıda §3 |
| `/pre-information` | **Vardı, TAMAMLANDI** — 7 bölüm → 13, sürüm 0.1 → **0.2** | Aşağıda §3 |
| `/delivery` | **YENİ** — Teslimat ve İfa Koşulları, sürüm 0.1 | §4 |
| `/about` | **YENİ** — Hakkımızda: satıcı kim, hizmet ne, kabul edilen ödeme yöntemi | §5 |
| `/contact` | **Vardı, TAMAMLANDI** — formun üstüne satıcının gerçek adres/telefon/e-postası eklendi | §5 |
| `/pricing` | **Vardı, TAMAMLANDI** — kabul edilen ödeme yöntemi fiyatın yanında | §5 |

Kütüphane sekizden **dokuz belgeye** çıktı; adresler yine tek denetleyiciden
(`ShowLegalDocumentController`) ve `routes/web.php` içindeki tek `foreach`ten
geliyor. Altbilgi, sitemap ve statik önizleme yeni sayfaları kendiliğinden
aldı — üçü de tek kaynaktan okuyor.

---

## 3. Mesafeli satışta hangi başlıklar kapatıldı

Mesafeli Sözleşmeler Yönetmeliği'nin saydığı başlıkların hepsi artık
metinde var. **Bu bir hukuki yeterlilik iddiası DEĞİLDİR** — ölçülen tek
şey, her başlığın karşılığının VAR olduğu
(`DistanceSellingCompletenessTest`, 35 test).

| Yönetmeliğin istediği | Nerede | Yeni mi? |
| --- | --- | --- |
| Satıcının kimliği ve iletişimi | 1. Taraflar | vardı |
| Hizmetin temel nitelikleri | 3. Essential characteristics — ürünün gerçek yetenekleri | **yeni** |
| Vergiler dâhil toplam fiyat | 4. Price, taxes and additional costs | genişletildi |
| Ek maliyet olmadığı | 4. — "no packaging cost", kargo yok | **yeni** |
| Kabul edilen ödeme yöntemleri | 5. Payment methods accepted | **yeni** |
| Uzaktan iletişim aracının bedeli | 6. — kendi tarifesi, ek ücret yok | **yeni** |
| İfa | 7. Performance — `/delivery`'ye atıf | genişletildi |
| Sözleşmenin süresi, yenilenmesi ve sona ermesi | 8. Duration, renewal and termination | **yeni** |
| Cayma hakkı, süresi ve kullanım usulü | 9. Right of withdrawal | genişletildi |
| **Cayma hakkının KULLANILAMAYACAĞI hâller** | 10. — iki hâl adıyla sayılır | **yeni** |
| **İfaya derhâl başlama onayı** | 11. Express consent — kutunun nasıl alındığı ve kaydedildiği | **yeni** |
| İptal ve iade | 12. — iptal-iade politikasına atıf | vardı |
| Şikâyet ve itiraz başvuruları | 15. Complaints and objections — ayrı başlık | **ayrıldı** |
| Hakem heyeti / tüketici mahkemesi | 15. — parasal sınırlara **ATIF**, rakam YOK | genişletildi |
| Uygulanacak hukuk ve yetkili merci | 16. Governing law and jurisdiction | vardı |
| Sözleşmenin kaydı ve erişimi | 17. Entry into force and the record | genişletildi |
| **Metnin dili ve Türkçe sürümün durumu** | 18. Language of this agreement | **yeni** |

Ön bilgilendirme formu aynı olguları kendi diliyle taşır (13 bölüm) —
sözleşmenin özeti değil, öncesidir; yönetmelik ikisini de ister.

**Uydurulmayanlar:** hakem heyeti parasal sınırı (her yıl ilan edilir, atıfla
yazıldı), KDV oranı (sipariş özetinden gelir), abonelik dönemi
(`billing.subscription.period_days`, sipariş özetine bağlandı), kart markası
(sağlayıcının yapılandırmasından türer, bu depoda liste yok), yanıt süresi,
çalışma süresi yüzdesi. Cayma süresi kanundan gelir ve kanuna atıfla yazıldı.
Bir test bunu her belgede tarıyor: on karakterlik bir rakam dizisi ya da
"KDV", "TL", "30 days" gibi bir ifade metne giremez.

---

## 4. Teslimat/ifa neden AYRI bir sayfa oldu

Karar: **ayrı sayfa** (`/delivery`), mesafeli satışın içinde bir bölüm değil.
İki sebeple:

1. **Ödeme kuruluşunun incelemesi başlığı ADIYLA arıyor.** İnceleyen kişi
   altbilgideki bağlantı listesine bakar; on sekiz bölümlük bir sözleşmenin
   yedinci bölümünü açmaz. Ayrı sayfa, altbilgide "Delivery and Performance
   Terms" diye görünür.
2. **Dijital hizmette "teslimat" kelimesi açıklanmayı hak ediyor.** Kargo
   yok, adres yok, süre yok; "teslimat" = ödemenin onaylandığı anda planın
   çalışma alanında kullanılabilir hâle gelmesi. Bu tanımı bir cümleye
   sıkıştırmak yerine kendi sayfasında anlatmak, ziyaretçiye "ne zaman
   kullanmaya başlarım?" sorusunun cevabını verir.

Sayfa aynı olguyu iki kez tanımlamaz: ifa anı **burada** tanımlanır,
sözleşme buraya **atıf** yapar.

---

## 5. Sekiz alan, tek giriş noktası

### Nereden okunur ve neden

**Karar: `.env` → `config/legal.php#company` → `CompanyProfile`.** Sağlayıcı
kasası (`/platform/credentials`, `docs/94`) **değil**. Üç sebeple:

1. **Kasa SIR içindir.** Bir tüzel kişinin ünvanı, adresi, MERSİS ve vergi
   numarası sır değildir — kanun onları yayınlamayı emreder. Kimliği sır
   kasasına koymak, kasanın ne için var olduğunu bulanıklaştırır.
2. **Kasa veritabanıdır.** Kurumsal sayfalar bilerek veritabanına
   dokunmuyor (`SiteNavigation` bir veritabanı düşüşünde tanıtım sitesini
   ayakta tutmak için boş liste döner). Sözleşmenin tarafını veritabanına
   bağlamak, veritabanı tökezlediğinde sözleşmeyi **tarafsız** bırakırdı.
3. **Statik önizleme veritabanı olmadan çizilir.** Kasadan okunan bir ünvan
   `site:export-static` çıktısında hep boş görünürdü.

Yani: **sır kasadan, kimlik ortamdan.**

### Bir kez girilir, her yere yayılır

Sahip yedi değeri `.env`'e bir kez girer:

```
LEGAL_COMPANY_LEGAL_NAME  LEGAL_COMPANY_ADDRESS  LEGAL_COMPANY_MERSIS
LEGAL_COMPANY_TAX_OFFICE  LEGAL_COMPANY_TAX_NUMBER
LEGAL_COMPANY_EMAIL       LEGAL_COMPANY_PHONE
```

O anda **aynı anda** doğru olan yerler: `/distance-sales`,
`/pre-information`, `/delivery`, `/refund-policy` (metin içindeki
`{company.*}` yer tutucuları), `/about` ve `/contact` (kimlik listesi),
sitemap kararı, ve canlı kipte ödeme kapısı. İkinci bir yere kopyalamak
gerekmiyor.

Alan adı → etiket eşlemesi **tek yerde**: `App\Support\Site\CompanyIdentity`.
`/about` ve `/contact` aynı Blade parçasını çizer
(`public/partials/company-identity.blade.php`); iki ayrı liste olsaydı, bir
alan eklendiğinde biri onu gösterir, diğeri sessizce atlardı.

**Sekizinci alan** `LEGAL_REVIEWED_AT`'tir ve şirket bilgisi değildir: metni
bir hukukçunun okuduğu gündür. Girilene kadar her sayfa "pending legal
review" der ve geçersiz bir değer ("yes", yazım hatası) inceleme sayılmaz.

---

## 6. Eksikken sayfa ne gösteriyor — ve ne yapmıyor

Satıcının kimliğini **söylemek zorunda** olan üç belge
(`distance-sales`, `pre-information`, `delivery`) ve `/about`, şirket
bilgisi girilmemişken:

1. **Üstte kırmızı bir uyarı bandı** (`role="alert"`, inceleme notunun
   ÜSTÜNDE): *"This document is not complete yet."* + eksik alanların
   **adları** tek tek. "Bir şeyler eksik" bir bilgi değildir.
2. **`X-Robots-Tag: noindex, nofollow`** — eksik bir sözleşme arama
   motoruna sunulmaz.
3. **Sitemap'ten düşer** — sayfanın robots sinyali ile sitemap aynı cevabı
   vermek zorunda; çelişkili sinyal arama motorunu kendi kararını vermeye
   davet eder.
4. **Canlı kipte tahsilat başlamaz.** `POST /workspaces/{w}/checkout`,
   etkin kip `live` ve şirket alanları boşken **409 `seller_identity_missing`**
   döner; sağlayıcı hiç çağrılmaz, onay defterine satır yazılmaz. Panel
   bunu adıyla söyler ve kullanıcıya "bilgilerinizi düzeltin" demez — eksik
   olan satıcının kendi kimliğidir.

**Sandbox'ta yol AÇIK kalır:** prova, sahibin şirketini kurmasını beklemek
zorunda değil. Etkin kip zaten üç kapılıdır (`docs/123`).

**Değerlendirilip REDDEDİLEN seçenekler:** sayfanın 404 ya da 503 dönmesi.
Okunamayan bir sözleşme, eksik bir sözleşmeden daha kötüdür — alıcı neyi
kabul ettiğini hiç göremez, ödeme kuruluşunun incelemesi sayfayı hiç
bulamazdı.

`/contact` **arama motoruna açık kalır** (yalnız kimlik listesi "girilmedi"
der): iletişim formu şirket bilgisi olmadan da çalışan bir yoldur ve onu
gizlemek, tıkanan birinin bize hiç ulaşamaması demekti.

---

## 7. Cayma hakkı ve dijital ifa onayı nasıl kaydedildi

**İki ayrı kutu, iki ayrı defter satırı.** Yeni bir mekanizma icat
edilmedi: aynı `consent_records` tablosu, aynı sütunlar, aynı "sürüm
kütüphaneden okunur" kuralı (`ConsentRecorder`).

| Kip | Belge | Ne zaman |
| --- | --- | --- |
| `checkout` | `pre-information` | Sipariş başladığında |
| `checkout` | `distance-sales` | Sipariş başladığında |
| **`immediate_performance`** | `distance-sales` | **Yalnız kutu işaretlendiyse** |

Üçüncü satır neden ayrı: yönetmelikte cayma süresi dolmadan ifaya
başlanması tüketicinin **ayrı ve açık** onayına bağlıdır ve bu onayın cayma
hakkı üzerinde sonucu vardır. İki onayı tek kutuda toplamak, defterde
"sözleşmeyi kabul etti" satırı bırakır ama "ifaya derhâl başlanmasını
istedi" satırı bırakmazdı — ve tam olarak o ikinci satır, cayma hakkının ne
zaman sona erdiğini gösteren kanıttır.

Kurallar: hiçbir kutu önceden işaretli değil; boş kutuyla istek **hiç
gitmez** (istemci) ve sunucu ikisini de ayrıca `accepted` ister (boş kutu
422); işaretlenmemiş kutu için satır **yazılmaz** — sessizlik onay değildir.
Onay, sipariş sağlayıcıda gerçekten başladıktan sonra yazılır: sağlayıcıya
hiç ulaşmamış bir deneme sipariş değildir.

`docs/124` §3'ün deseni değişmedi; yalnız o desenin bekleyen ucu bağlandı.

---

## 8. Türkçe metin boşluğu — kapanmadı, YAZILDI

**Bu pakette tek bir Türkçe cümle üretilmedi.** Çeviri kilidi kapalı
(`docs/105` §8, `docs/118` E4, `docs/121`); `shipped_locales` yalnız
`['en']`. PO dosyalarına yalnız boş `msgstr` ile anahtar girdi.

**Ve bu bir eksiktir.** Türk tüketicisine sunulan bir mesafeli satış
sözleşmesinin Türkçe olması gerekir. O metin bir çeviri değil, **hukukçunun
kendi kaleme aldığı bir metindir** ve sahibin dışarıdan alacağı bir şeydir.

Boşluk artık **okuyucuya da yazılı**: mesafeli satış (18. bölüm), ön
bilgilendirme (13. bölüm) ve teslimat/ifa (8. bölüm) metinlerinin sonunda,
metnin İngilizce yayınlandığı, kabul edilen metnin İngilizce olduğu ve
Türkçe bir metnin henüz yayınlanmadığı yazıyor.

**Altyapı tarafında bu pakette alınan önlem** (`docs/121` Ö11): her belge
artık kendi dilini taşıyor (`LegalDocument::$language`) ve sayfa
`<main lang="en">` çiziyor. Bir belge dilini söylemiyorsa, Türkçe bir
kabuğun içine düşen İngilizce bir sözleşme ekran okuyucuya yanlış dilde
okunur ve arama motoruna yanlış dilde ilan edilir.

**Türkçe metin geldiği gün hâlâ gerekecek olanlar** (bu pakette YAPILMADI,
bilerek):

1. `LegalLibrary` bugün anahtar başına **tek** belge tutuyor. İkinci bir dil
   için `LegalLibraryPort::find()` bir dil parametresi almalı ve düşüş
   kuralı yazılı olmalı (istenen dilde metin yoksa kaynak dil döner, kendi
   `lang`ıyla işaretli).
2. `consent_records` hangi **dildeki** metnin kabul edildiğini tutmuyor.
   Sürüm var, dil yok. Türkçe ve İngilizce metin aynı sürümü taşıyacaksa bu
   sütun gerekir — yoksa "hangi metni kabul etti" sorusu bir gün cevapsız
   kalır.
3. Adres kararı: `/tr/mesafeli-satis-sozlesmesi/` mi, aynı adres dile göre
   mi (`docs/105` §4.1). Bu bir SEO ve kütük kararıdır, bu paketin işi değil.

---

## 9. Iyzico başvurusunda hangi sayfa hangi ihtiyacı karşılıyor

| Ödeme kuruluşunun aradığı | Sayfa | Bugün |
| --- | --- | --- |
| Mesafeli satış sözleşmesi | `/distance-sales` | ✅ tamam · şirket bilgisi bekliyor |
| Ön bilgilendirme formu | `/pre-information` | ✅ tamam · şirket bilgisi bekliyor |
| İptal ve iade koşulları | `/refund-policy` | ✅ |
| **Teslimat / ifa koşulları** | `/delivery` | ✅ **yeni** · şirket bilgisi bekliyor |
| Gizlilik politikası | `/privacy` | ✅ |
| KVKK aydınlatma | `/kvkk` | ✅ |
| Çerez politikası | `/cookies` | ✅ |
| Kullanım koşulları | `/terms` | ✅ |
| **Hakkımızda** | `/about` | ✅ **yeni** · şirket bilgisi bekliyor |
| **İletişim: adres, telefon, e-posta** | `/contact` | ✅ **tamamlandı** · şirket bilgisi bekliyor |
| **Kabul edilen ödeme yöntemleri** | `/pricing`, `/about`, `/pre-information`, `/distance-sales` | ✅ **yeni** · logo YOK |
| Fiyat listesi | `/pricing` | ✅ (plan kataloğundan) |

**Kart ve banka logosu bilerek YOK.** Hangi kartların kabul edildiği ödeme
sağlayıcısının kendi yapılandırmasından türer ve bu depoda öyle bir liste
yapılandırılmamıştır. Uydurulmuş bir logo tablosu, kabul edilmeyen bir
kartı kabul ediliyor göstermek olurdu. Sağlayıcının **adı** ise ölçülmüş
bir olgudur (`IyzipayGateway`) ve yazılıyor.

---

## 10. Ölçüm (2026-09-07)

**320×568, gerçek Chrome** (`site:export-static` + `scripts/mobile-ux-audit`),
**iki durumda ayrı ayrı**: (a) şirket alanları boş — yani uyarı bandı
ekrandayken, (b) alanlar uzun gerçekçi değerlerle dolu — yani kimlik
listesi, uzun bir ünvan, uzun bir adres ve bir MERSİS numarasıyla dolu.

14 sayfa. **Yeni ve değişen altı sayfanın hepsinde iki durumda da yapısal
bulgu SIFIR**: `/about`, `/contact`, `/delivery`, `/distance-sales`,
`/pre-information`, ve dokuz yasal sayfanın tamamı. Kullanılabilir içerik
genişliği **309/320** (oran 0.97).

Aynı koşuda kalan **7 bulgu, üç sayfada** (`index.html`, `pricing/`,
`help/`) — hepsi **#279'un satır içi bağlantı borcu** ve `docs/124` §5'te
zaten raporlanmış: "Contact us" 79×18, "Ask us" 48×18, "Pricing" 50×18,
"Write to us" 78×18 ve 267×42. Bu paket o dosyaları yeniden düzenlemedi.

**Bu paketin fiyat sayfasına eklediği bağlantı o borcu BÜYÜTMEDİ:** ilk
ölçümde satır içi olarak 283×42 çıktı (2 piksel eksik), kendi satırına
alınıp 44 piksele çıkarıldı ve ikinci ölçümde temiz.

**Storybook, 320×568** — ödeme panelinin sekiz hikâyesi
(`surface-workspace-billingcheckoutpanel--*`, üçü bu pakette yeni:
`consent-given`, `consent-missing`, `seller-identity-missing`):
**sekizinde de yapısal bulgu SIFIR**, kullanılabilir genişlik **296/320**
(oran 0.925). Ölçülen en yoğun hâl `consent-missing`: iki uzun onay metni,
iki hata satırı ve dört belge bağlantısı aynı ekranda — yatay taşma yok,
kırpılma yok, 44 pikselin altında hedef yok, hedefler arası ayrım tamam.

---

## 11. Kapı sonuçları

| Kapı | Sonuç |
| --- | --- |
| `./vendor/bin/pint --test` | ✅ |
| `php artisan test` | ✅ |
| `npx vitest run resources/js` | ✅ |
| `npx prettier --check .` | ✅ |
| `npm run i18n:check` | ✅ |
| `scripts/mobile-ux-audit` | ✅ yeni/değişen sayfalarda 0 bulgu |

Rakamlar paketin kendi raporundadır.

---

## 12. Sahibin DIŞARIDAN alması gerekenler — tam liste

Aşağıdakilerin hiçbiri kodla çözülemez; hepsi sahibin bir olguyu girmesi ya
da bir uzmandan alması gereken şeyler.

1. **Hukukçu incelemesi.** On bir metin standart ve dürüst ama bir hukukçu
   okumadı. Okutulduktan sonra düzeltmeler belge sınıflarında yapılır,
   sürüm yükseltilir, `LEGAL_REVIEWED_AT` doldurulur, not kalkar.
2. **Şirketin yasal kimliği — yedi alan.** `LEGAL_COMPANY_LEGAL_NAME`,
   `_ADDRESS`, `_MERSIS`, `_TAX_OFFICE`, `_TAX_NUMBER`, `_EMAIL`, `_PHONE`.
   Girilene kadar dört sayfa uyarı bandıyla açılır, sitemap'ten düşer ve
   canlı tahsilat başlamaz.
3. **Türkçe hukuki metinler.** Hukukçunun kendi kaleme aldığı metinler —
   çeviri değil. Altyapı için gerekenler §8'de üç madde hâlinde.
4. **Veri talebi adresi.** `LEGAL_DATA_REQUEST_ADDRESS` (FF-169).
5. **İletişim bildirim adresi.** `CONTACT_NOTIFICATION_ADDRESS` — boşken
   mesaj saklanır ama e-posta çıkmaz (`docs/93`).
6. **Saklama süreleri ve ticari ileti geri alma kaydı.** Hukuk kararı;
   defter yalnız ekler, silmez.
7. **İYS (İleti Yönetim Sistemi).** Entegrasyon yok ve metin bunu iddia
   etmiyor. Ticari e-posta gönderilmeye başlanmadan önce sahibin kararı.
8. **Iyzico üye iş yeri başvurusu ve üretim anahtarı.** Kasaya üretim
   anahtarı, üretim ortamında `IYZICO_MODE=live`, süperadmin panelden kip
   anahtarı, Iyzico panelinde webhook adresi (`docs/123`, `docs/107` 1.1).
9. **Fatura.** Tahsilatın karşılığında belge kesilmeli; e-arşiv/e-fatura
   yolu yok (`docs/107` 1.4). Bu paket onu çözmedi ve çözdüğünü iddia
   etmiyor.

---

**kullaniciYolculugu:** §0. **capability_delta:** Türkiye'de uzaktan satış
için gereken belge kümesi ve ödeme adımındaki iki onay artık ÜRÜNDE var ve
kaydediliyor; ödeme kuruluşunun aradığı on iki sayfa başlığının hepsinin
adresi var. **Çalışan:** bir restoran sözleşmeyi okur, iki kutuyu
işaretleyerek sandbox'ta abone olur ve onayı defterde kayıtlıdır.
**Çalışmayan:** gerçek para hâlâ hareket etmedi (üretim anahtarı sahipte),
fatura kesilmiyor, metinler hukukçu incelemesi bekliyor, şirket kimliği
girilmedi ve o girilene kadar canlı tahsilat kapalı. Yönetişim kapılarının
yeşil olması ürün hazırlığı değildir.
