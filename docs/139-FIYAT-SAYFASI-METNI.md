<!--
    KARAR BELGESİ — FF-239, 2026-09-08.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan gelir; ölçülemeyenler §8'de AÇIKÇA "bilinmiyor"
    diye ayrılmıştır. Uydurulmuş fiyat, indirim, müşteri sayısı, popülerlik
    ya da soru yoktur.
-->

# 139 — Fiyat sayfasının metni: kime uygun, ne dahil değil, gerçek SSS

**Kapsam:** `docs/107` **Faz 2.7** — *"Fiyatlandırma sayfası gerçek planlar ve
SSS ile."* Bugünkü ölçüm o satırda şöyleydi: *"◐ Planlar veritabanından
okunuyor; sayfa metni eksik."* Bu paket **metni** teslim eder.

**Sahiplenmez ve tekrarlamaz:** rakamın nereden geldiğini (`docs/88`),
kademelerin neden üç olduğunu (`docs/90`), abonelik yaşam döngüsünü
(`docs/134`), ödeme kipini (`docs/123`) ve kurumsal tasarım yasasını
(`docs/136`). Bu paket onların hepsini **okur**; hiçbirini yeniden yazmaz.

---

## 0. Ölçülen başlangıç ve varış

| | Önce (`origin/main`, ölçüldü) | Şimdi |
| --- | --- | --- |
| Sayfanın bölümü | **1** — ad, tutar, "Adds" | **6** — giriş, her planda olan, planlar, ne dahil değil, SSS, ödeme ve çıkış |
| "Hangisi benim?" | Cevapsız — kademe adı söylemiyor | Her planın yanında kendi durumunu tarif eden bir cümle |
| "Ödedikten sonra ne DEĞİŞMEYECEK?" | Sayfada hiç yok | Altı ölçülmüş yokluk, adıyla |
| SSS | Sayfada hiç yok (ana sayfada üç genel soru var) | Yedi soru, üçü yasal metinden, ikisi ürünün "ne değildir" listesinden, biri yardım makalesinden, biri ödemesiz süreden |
| "Nasıl çıkarım?" | Yalnız sözleşmenin içinde | Fiyatın yanında, `/refund-policy` bağlantısıyla |
| `#main-content` hedefi | **YOK** — atlama bağlantısı hiçbir yere gitmiyordu | Var |
| 320×568 denetim bulgusu | 2 (`small-target`) | **0** |
| 1280×800 denetim bulgusu | ölçülmemişti | **0** |

---

## 1. Kime yazıldı

Teknoloji bilmeyen, acelesi olan, **telefondan** bakan bir restoran sahibi.
Fiyat sayfası satın alma kararının verildiği yerdir ve tek bir kural onu
yönetir: **hangi planı alacağını anlayamayan kimse almaz.**

Bu cümlenin üç sonucu var ve paketin tamamı o üçünden türedi:

1. Kademe adı bir ölçüt değildir. "Team" kelimesi, kırk masalık bir
   dönercinin hangisini alacağını söylemez.
2. Tik dolu bir tablo pahalı sütunun **neye sahip** olduğunu söyler; parası
   ödendikten sonra masada **hâlâ olmayacak** şeyi söylemez.
3. "Çıkmak istersem ne olur?" sorusunun cevabı sözleşmenin on birinci
   bölümündeyse, o cevap **ödeme adımından sonra** öğrenilir.

---

## 2. Rakam yazılmadı — ve bu ölçülüyor

Şablonda **bir tek tutar, bir tek plan adı ve bir tek hak adı yoktur.**
Hepsi `plans` tablosundan gelir (`FoundationStatusController::publicPlans`)
ve sıralama `sort_order`'dan. Elle yazmak, fiyat değiştiği gün ikinci bir
gerçek kaynak yaratırdı ve ikisi ayrıştığında hangisinin doğru olduğunu kimse
bilemezdi (`docs/88`).

Katalog bugün üç kademe yayınlıyor ve rakamları buraya **kopyalanmadı**: bu
belge de sayfanın kendisi gibi kataloğu adres olarak gösterir
(`database/seeders/PlanCatalogueSeeder.php`). Kopyalasaydı, sahibin panelden
yaptığı ilk düzenlemede bu belge de eskirdi.

**Plan kodu şablona hiç geçmiyor.** Kod bir eşleme anahtarıdır ve
denetleyicide çözülür; şablona verilseydi bir gün `data-plan="restaurant"`
diye basılır ve *"müşteri geliştirici dilini okumaz"* kararının
(`PLAN-LABELS-ARE-HUMAN-01`) ikinci bir yerden delinmiş hâli olurdu.

---

## 3. "Kime uygun" cümleleri nereden geldi

Üç cümle de **uydurulmadı**; `PlanCatalogueSeeder`'ın kendi gerekçesinden
türetildi (`docs/90`):

| Kademe | Tohumdaki gerekçe | Sayfadaki cümlenin dayanağı |
| --- | --- | --- |
| Ücretsiz | *"Zaten ücretsiz. Temel zincir plansız çalışıyor."* | İlk kez deneyen, tek başına yöneten, ödemeden görmek isteyen |
| İlk ücretli | *"Kırk masalık bir restoranın ilk gün ihtiyacı… misafirin gördüğü sayfa nötrdür."* | Salonu kendi işleten, misafirin gördüğü sayfanın kendi markası olmasını isteyen |
| Üst | *"Sahibin menüyü tek başına yönetmediği yer."* | Müdür, garson ya da muhasebeci kendi hesabını isteyen |

Yani sayfada okunan cümle, **kademenin var olma sebebinin kendisidir** —
pazarlama için sonradan yazılmış ikinci bir gerekçe değil.

**Cümleler hakları tekrar saymaz.** "Adds" listesi zaten onu yapıyor; aynı
şeyi iki kez yazmak 320 pikselde kartın yarısını harcardı.

### 3.1 Tanınmayan kademeye kitle UYDURULMAZ

`SiteText::planAudienceLabel()` tanımadığı kodda `null` döner ve sayfa o plan
için hiçbir cümle çizmez — `entitlementLabel()` ile birebir aynı sessizlik
kuralı. Sahibin panelden açtığı yeni bir kademeye hazır bir kitle
yakıştırmak, bu bölümün engellemek için var olduğu şeyin tam kendisi olurdu.

Bu doğru davranışın bilinen bir bedeli var ve `docs/90` onu zaten bir kez
ödemişti (`branding.custom` fiyat sayfasından sessizce düşmüştü): eşlemesi
yazılmayan kademe **sessizce cümlesiz kalır**. Bu yüzden kapı ters yönden
kuruldu — `PRICING-AUDIENCE-STATED-01` kataloğun **her yayınlanmış kodunun**
burada bir karşılığı olmasını ister. Yeni bir kademe, cümlesi yazılmadan
yeşil geçemez.

---

## 4. SSS: sorular nereden türetildi — ve nereden TÜRETİLMEDİ

Yedi sorunun yedisi de depoda ölçülebilir bir olguya dayanır:

| Soru | Kaynak |
| --- | --- |
| Ödemeyi bırakırsam menüme ne olur? | `RefundPolicy` §*"If a period is not paid for"* + temel zincirin plansız çalışması (`docs/90`) |
| Kendim iptal edebilir miyim? | `RefundPolicy` §*"How to cancel"*, §*"When cancellation takes effect"*; `docs/134` K1–K2 |
| Dönem ortasında iptal edersem para iade edilir mi? | `RefundPolicy` §*"Refunds"*, §*"Consumers: statutory right of withdrawal"* |
| Büyük/küçük plana nasıl geçerim? | `RefundPolicy` §*"Moving to a different plan"*; `docs/134` |
| Ücretsiz plan biten bir deneme mi? | `PricingPage` §*"No trial, no discount, no campaign"*; `docs/90` |
| İkinci şube ya da ikinci garson daha mı pahalı? | `PricingPage` §*"One price, not a price per branch or per person"* |
| Fiyatı değiştirirsem kodları yeniden bastırmalı mıyım? | Yardım makalesi (`resources/help/en/first-15-minutes.blade.php`): *"Print once… the printed code keeps working"* |

### 4.1 Destek talebi yüzeyi BİLEREK kullanılmadı

Görev tanımı destek taleplerini bir kaynak olarak sayıyordu. Ölçüldü ve
**kullanılamadı**, sebebi de kayda geçiyor:

- `support_requests` bir **konu taksonomisi taşımıyor**; panel formunda tek
  bir serbest metin alanı var (`workspace.support.form.subject`), kamu
  formunda konu mesajın ilk satırından türetiliyor (`docs/125` §1).
- Depoda gerçek bir talep kütüğü yok; üretimdeki satırlara bu paketten
  erişilmedi.

Böyle bir yüzeyden soru "türetmek", **uydurmanın kaynak göstermiş hâli**
olurdu. Konu alanı bir gün taksonomiye kavuşursa bu paragraf değişir.

### 4.2 Cevaplarda söz verilmeyen şeyler

- **Süre yok.** Ödemesiz sürenin gün sayısı yazılmadı: o bir yapılandırmadır
  (`billing.subscription.grace_days`) ve metne kopyalansaydı ilk değişiklikte
  ayrışırdı — `RefundPolicy`'nin kendi gerekçesiyle aynı.
- **Oran ve garanti yok.** "Ortalama %X tasarruf", "para iade garantisi",
  "7/24" gibi bir cümle yok; hiçbirinin depoda karşılığı yok.
- **Cevaplar HTML'de durur.** `<details>` kapalı başlar ama içeriği gövdededir:
  arama motoru ve JavaScript çalıştırmayan bot için gövde budur
  (`docs/118` E2). Betikle sonradan doldurulsaydı sayfanın en çok aranan
  yarısı hiçbir bota görünmezdi.

---

## 5. "Ne dahil değil" — ve bu bir kademe farkı değil

Başlık **"hiçbir plan"** der, "ucuz plan" değil: bunlar bir merdiven basamağı
değil, ürünün **bugünkü sınırıdır**.

| Yokluk | Dili nereden alındı |
| --- | --- |
| Masada ödeme yok | `OrderingPage` §*"It does not take payment"* |
| POS / adisyon / muhasebe bağlantısı yok | `OrderingPage` §*"It does not talk to your till"* |
| Paket servis, gel-al, önceden sipariş yok | `OrderingPage` §*"Only from a table in the room"* |
| Mutfak yazıcısı, ses, bildirim yok | `OrderingPage` §*"Nothing prints and nothing beeps"* |
| Deneme, indirim, kampanya, yıllık indirim yok | `PricingPage` §*"No trial, no discount, no campaign"* |
| Tek para birimi | `PricingPage` §*"One currency"* |

**Dil ödünç alındı, yeniden icat edilmedi.** Aynı yokluğu iki yüzeyde iki
ayrı cümleyle anlatmak, bir gün hangisinin doğru olduğunu bilinmez yapardı
(`docs/137` §2a aynı kuralı sayfa haritası için koyuyor).

**Bilerek yazılmayanlar** (ölçüm 2026-09-08): "en popüler" rozeti, indirim,
kampanya, para iade garantisi, müşteri sayısı, referans logosu. Bir fiyat
sayfasının en kolay uydurduğu şeyler tam olarak bunlardır ve
`PRICING-NO-INVENTED-CLAIM-01` yedi ayrı desenle onları arıyor. **"En popüler"
rozeti ölçülmüş bir popülerlik gerektirir ve bu depoda öyle bir ölçüm hiç
yapılmadı.**

---

## 6. 320 piksel: fiyat tablosu değil, KART YIĞINI

Fiyat tablosu dar ekranda en zor çizilen şeydir. Burada **tablo yok**:

- Planlar `repeat(auto-fit, minmax(min(18rem, 100%), 1fr))` ızgarasında; 320
  pikselde tek sütun, ekran genişledikçe kendiliğinden çoğalır. **Kırılma
  noktası jetonu yok** (`MP-05`, testle sabit) ve `overflow-x` yok: yatay
  kaydırılan bir fiyat tablosu, ikinci sütununu kimsenin görmediği bir
  tablodur.
- SSS `<details>` içinde ve **kapalı başlar**: yedi soru açık hâlde fiyatın
  altını bir duvara çevirirdi. Betiksiz çalışır (altbilginin pSEO katıyla
  aynı karar, `docs/136` §6).
- Bağlantılar cümlenin içinde değil, **kendi satırında ve 44 piksel**. Satır
  içi bir bağlantı dar ekranda 18-42 piksel yüksekliğinde kalıyor ve parmakla
  ıskalanıyor (`docs/117`) — sayfanın eski iki bağlantısı (`Contact us`,
  `Ask us`) tam olarak bu yüzden denetimde bulgu veriyordu.

### 6.1 daisyUI'nin ölçüsü çekildi (`docs/136` §5)

Paketten okunan gerçek değerler ve neden ezildikleri:

| daisyUI | Kendi değeri | Sayfada |
| --- | --- | --- |
| `.card-body` dolgusu | `1.5rem` (24px) | `--space-4` (16px) — iç içe dolgu 320'de birikirdi |
| `.card-body` gövde boyutu | `.875rem` (14px) | `--aep-text-body` (1rem) — yoğunluk **fontu küçülterek** sağlanmaz (`docs/118` E3) |
| `.collapse-title` yüksekliği | `1lh` (~24px) | `--control-height` (≥44px) — parmak imleç değildir |
| `.collapse-title` sondaki boşluk | — | `--space-6` (32px); ok `1.4rem` içeriden 8px boyunda çiziliyor ve 24px yetmiyordu (ölçüldü: son sözcük okun üstüne biniyordu) |

`!important` **gerekmedi ve kullanılmadı**: daisyUI kuralları `@layer
utilities` içinde yaşıyor, `site-pricing.css` katmansız ve katmansız bir kural
katmanlı bir kurala karşı zaten kazanıyor.

### 6.2 Yol boyunca çıkan gerçek kusur

Madde imleri **sessizce kaybolmuştu**. Tailwind'in preflight'ı her `ul` için
`list-style: none` yazıyor; eski bölüm bunu `list-disc` yardımcı sınıfıyla
telafi ediyordu ve kendi sınıfına geçen liste onu kaybetti. Ölçülebilir
sonucu: 320 pikselde altı madde imsiz cümle, bir liste değil bir **paragraf
yığını** gibi okunuyordu. Hiçbir test bunu yakalayamazdı — denetim aracı
taşma, hedef ve kırpılma ölçer, "bu bir liste gibi mi okunuyor" ölçmez. Gerçek
bir tarayıcıda bakılmasaydı görülmezdi.

İkinci kusur, ondan da eskisi: bu sayfada **`id="main-content"` hiç yoktu.**
Kabuğun atlama bağlantısı `#main-content`e gidiyor ve `/pricing` üzerinde
hiçbir yere gitmiyordu — klavyeyle gezen biri her seferinde kabuğun tamamını
baştan geçiyordu. Ana sayfa, `/about` ve yasal sayfalar hedefi taşıyordu;
yalnız fiyat sayfası taşımıyordu.

---

## 7. Kanıt

`PricingPageContentTest` (8 senaryo, 52 iddia).

| Requirement | Ne donduruluyor |
| --- | --- |
| `PRICING-AUDIENCE-STATED-01` | Yayınlanmış her planın yanında kime uygun olduğu yazar |
| `PRICING-AUDIENCE-FROM-CATALOG-01` | Tanınmayan kademeye kitle uydurulmaz; plan yine de çizilir |
| `PRICING-EXCLUSIONS-STATED-01` | Altı ölçülmüş yokluk sayfada adıyla durur |
| `PRICING-FAQ-ANSWERS-REAL-QUESTIONS-01` | Yedi soru ve **cevapları** gövdede durur |
| `PRICING-EXIT-PATH-LINKED-01` | Ödeme yöntemi ve çıkış yolu fiyatın yanında; üç bağlantı da gerçekten açılıyor |
| `PRICING-NO-INVENTED-CLAIM-01` | Rozet, indirim, garanti ve müşteri sayısı uydurulamaz |
| `PRICING-MOBILE-FIRST-01` | Kırılma noktası ve yatay kaydırma yok; atlama hedefi var |

Var olan kapılar **değişmeden** geçiyor: `PUBLIC-PRICING-NO-AUTH-01`,
`PUBLIC-PRICING-FROM-CATALOG-01`, `PUBLIC-PRICING-INACTIVE-HIDDEN-01`,
`PUBLIC-PRICING-EMPTY-HONEST-01`, `PUBLIC-PRICING-SURVIVES-CATALOG-FAILURE-01`,
`PLAN-FREE-IS-FREE-01`, `PLAN-INCLUDED-STATED-ONCE-01`,
`PLAN-LABELS-ARE-HUMAN-01`, `MP-01…MP-06`, `I18N-SSR-RATCHET-16`.

### 7.1 Kapı sonuçları (koşturuldu)

| Kapı | Sonuç |
| --- | --- |
| `vendor/bin/pint --test app resources tests database` | **geçti** |
| `php -d memory_limit=-1 artisan test` | **2.869 test, 21.148 iddia, 0 hata** (3 atlandı, 1 riskli — ikisi de temelden) |
| `npx vitest run resources/js` | **284 dosya, 2.153 test, 0 hata** |
| `npx prettier --check .` | **geçti** |
| `npm run i18n:check` | **geçti** — 31 yeni anahtar, 7 PO dosyasına yazıldı |
| `scripts/mobile-ux-audit` 320×568 | `/pricing`: **0 bulgu** (öncesi 2 `small-target`), kullanılabilir genişlik 309/320 |
| `scripts/mobile-ux-audit --width 1280` | `/pricing`: **0 bulgu**, kullanılabilir genişlik 1141/1280 |

---

## 8. Ölçülmeyenler — açıkça

- **480 piksel yükseklik ölçülmedi.** Araç dar ekranı 320×**568** olarak
  sabitliyor ve yükseklik ayrı bir bayrak değil (`scripts/mobile-ux-audit`).
  Görev 320×480 istiyordu; ölçülen yükseklik 568'dir ve aradaki fark
  raporlanmaktadır. Ekranda gözle 320×480'de de bakıldı ve bir kusur
  görülmedi, ama **gözle bakmak bir ölçüm değildir**.
- **iOS Safari doğrulanmadı.** Denetim Chrome'da koşuyor.
- **Çeviri yapılmadı.** 31 yeni anahtar altı locale PO'suna boş `msgstr` ile
  yazıldı ve öyle duruyor; çeviri kilidi kapalı (`docs/118`) ve yalnız
  sahibin açık komutuyla açılır. Bugün Türkçe tarayıcılı bir ziyaretçi bu
  bölümleri **İngilizce** okur — bu, sayfanın kendi durumu değil, deponun
  bilinen ve kayıtlı durumudur.
- **Sayfanın ikna ediciliği ölçülmedi.** Kaç ziyaretçinin okuyup iletişime
  geçtiği bir ölçüm işidir (`docs/107` Faz 2.3) ve o ölçüm kurumsal sitede
  henüz yok. Bu paket metnin **doğru** olduğunu ölçer, **işe yaradığını**
  değil.
- **Kontrast oranları burada ölçülmedi.** Renk jeton katmanının işidir ve
  orada ölçülür; sayfa o katmandan besleniyor (`docs/136` §3).

---

## 9. Ürün iddiası

**Çalışır:** kaydolmamış bir ziyaretçi telefonundan `/pricing` açar; üç
kademeyi, gerçek tutarlarını, her birinin ne eklediğini ve **hangisinin kendi
durumuna uyduğunu** okur; parasını ödedikten sonra masada hâlâ olmayacak altı
şeyi ödemeden önce görür; ödemeyi bıraktığında menüsüne ne olacağını,
iptalin ne zaman yürürlüğe gireceğini ve iadenin hangi hâlde yapıldığını
sayfada bulur; ödeme yöntemine ve iptal/iade politikasına oradan gider.

**Çalışmaz:** satın alma bu sayfadan başlamaz. Ödeme, çalışma alanının
Faturalandırma ekranından yürür ve canlı kip üç kapıya bağlıdır
(`docs/123` K1) — sayfa bir "Satın al" düğmesi göstermiyor, çünkü göstereceği
yer burası değil. `/pricing` hâlâ `sitemap.xml`de değil (`docs/129` §4) ve
Türkçe karşılığı (`/tr/fiyatlandirma/`) yayında değil (`docs/137` §1.3);
ikisi de Faz 2'nin ayrı maddeleridir (2.2, 2.4) ve bu pakette
sahiplenilmedi.
