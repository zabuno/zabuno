# 134 — Abonelik yaşam döngüsü: çıkış, düşürme, ödemesiz süre, geri dönüş (FF-219)

> **Kapsam.** `docs/107` Faz 1.3'ün eksik yarısı. Plan kataloğu, abonelik
> okuma ve ödemeyle oluşturma/uzatma FF-197'de indi (`docs/123` K5); bu belge
> **iptal**, **plan düşürme ve yükseltme**, **başarısız ödemede askıya alma**
> ve **geri dönüş yolunu** sahiplenir. Fatura ve iade mekanizması burada
> sahiplenilmez ve tekrarlanmaz: onlar `docs/130` (FF-215) ve `docs/123` K6.

---

## 0. Ölçülen başlangıç ve varış

**Önce (2026-09-07, main).** Bir restoran sahibi kendi kendine abone
olabiliyordu ama **çıkamıyordu.** Depoda ölçülen durum:

| Soru | Bugün |
| --- | --- |
| Sahip aboneliğini iptal edebiliyor mu? | Hayır. Tek yol iletişim formuydu ve İptal/İade Politikası bunu açıkça yazıyordu. |
| Planını düşürebiliyor mu? | Hayır — ve daha kötüsü, ödeme ekranından ucuz planı satın alırsa **para öder ve o anda yetenek kaybederdi** (`extendFromPayment` ödenen planı geçerli plan yapar). |
| Ödeme gelmezse ne oluyor? | `ends_at` geçtiği **saniye** bütün plan yetenekleri kapanıyordu (`DatabaseEntitlementRepository`). Ödemesiz süre yoktu. |
| Askıdan dönüş var mı? | Kendiliğinden vardı ama ölçülmemişti; iptal kaydı diye bir şey de olmadığı için silinecek bir niyet yoktu. |
| Bunların denetim kaydı var mı? | Hayır. `platform_audits` yalnız kip anahtarı ve iadeyi tanıyordu. |

**Sonra (bu paket).** Dördü de var, ölçülüyor ve panelde görünüyor;
`SubscriptionLifecycleJourneyTest` (18 senaryo) ile
`BillingPage.lifecycle.test.tsx` (8 senaryo) sözleşmeyi donduruyor.

---

## 1. İPTAL — müşteri kendi kendine çıkabilir

### K1 — İptal ödenmiş dönemi KESMEZ; duran tek şey yenilemedir

Sahip iptal ettiğinde `subscriptions.cancelled_at` yazılır ve
`subscriptions.state` **değişmez.** Bu ikinci cümle kararın kendisidir:
`DatabaseEntitlementRepository` yalnız `active`/`trialing` durumlarına yetki
verir, yani duruma `cancelled` yazmak, iptal eden sahibin yeteneklerini **o
saniye** kapatırdı. Oysa yayınlanmış İptal ve İade Politikası şunu söylüyor:
*"Cancellation stops the subscription from renewing. The plan stays active
until the end of the period you have already paid for."* Kod o cümleye uydu.

Ekranda yazan şey de tam olarak budur ve tarih içerir:

> "You cancelled. You keep using Pro until 2026-09-30, and it will not renew."

### K2 — İptalden CAYMA yolu var ve bedava

Dönem bitmeden fikir değiştiren sahip tek bir `DELETE
/subscription/cancellation` isteğiyle geri döner: `cancelled_at` silinir,
abonelik hiç kesilmemiştir, **yeniden ödeme yapılmaz.** Test bunu ölçüyor:
cayma sırasında hiçbir `payment_transactions` satırı doğmaz.

Dönem bittikten sonra bu yol **kapanır** (422 `period_over`) ve bu da bir
karardır: bitmiş bir dönemin iptalinden caymak, ödenmemiş bir dönemi ücretsiz
açmak olurdu. Oradaki geri dönüş yolu ödemedir, düğme değil.

### K3 — İptal düğmesi saklanmaz, ama tek tıkla da çalışmaz

Düğme kendi bölümünde, `Subscription` bölgesinin **en altında**, ayrı bir
çizginin ardında, ikincil renkte ve `min-h-11` ölçüsünde durur. Saklanmış bir
iptal düğmesi tüketici hukukunun tam olarak hoşlanmadığı şeydir; birincil
eylemin yanına konmuş bir iptal düğmesi ise yanlışlıkla basılan bir düğmedir.

Bir onay adımı var ve o adım **ne olacağını tarihiyle** yazar:

> "Your plan stays active until 2026-09-30. Nothing is refunded for the period
> you have already paid for, and you can undo this before 2026-09-30."

Onay ekranında bir "sebep" alanı **sorulmadı.** Çıkışın önüne doldurulması
gereken bir alan koymak, çıkışı zorlaştırmanın en kibar yoludur.

### K4 — İptal bir denetim kaydı bırakır

`platform_audits`, scope `billing.subscription`, action `cancelled`:
kim (`actor_user_id`), ne zaman (`created_at`), hangi dönemde
(`service_until`), hangi plandan (`plan_code`), hangi evreden hangi evreye
(`phase_before` / `phase_after`). Cayma da ayrı bir kayıttır
(`cancellation_withdrawn`).

---

## 2. PLAN DÜŞÜRME VE YÜKSELTME

### K5 — Yükseltme ZATEN VARDI; ölçüldü, dokunulmadı

Ölçüm: bugün fiyat sayfasından pahalı planı seçip ödeme yapmak yükseltmedir
ve **çalışır** — `extendFromPayment` ödenen planı geçerli plan yapar, kalan
gün kaybolmaz (bitişin üstüne bir dönem eklenir). Yeni bir yükseltme
mekanizması yazılmadı; olan bir şeyi ikinci kez yazmak, iki yoldan birinin
sessizce eskimesi demekti.

Plan değiştirme uçları yükseltmeyi **422 `not_a_downgrade`** ile geri çevirir
ve panel yükseltmeyi hiç listelemez; onun yerine tek cümleyle yolunu söyler:
*"To move up a plan, pay for it below — the higher plan starts as soon as the
payment succeeds."*

### K6 — Düşürmede seçilen karar: (c) DÖNEM SONUNDA YÜRÜRLÜK, İADE YOK

Üç seçenek vardı: (a) fark iade edilir, (b) fark bir sonraki döneme kredi
olur, (c) düşürme dönem sonunda yürürlüğe girer ve hiçbir iade yapılmaz.

**Seçilen: (c).** Gerekçe uydurulmadı, **depodan okundu:**

1. **Yayınlanmış İptal ve İade Politikası (`RefundPolicy`, main'de FF-198'den
   beri) şunu diyor:** *"Fees for a subscription period that has already
   started are not refunded, except in the following cases: … the statutory
   right of withdrawal …; we ended or materially reduced the service before
   the end of a paid period for reasons not caused by you …; or the law
   otherwise requires a refund."*
   (a) ve (b) bu cümleyle **doğrudan çelişir**: ikisi de başlamış bir dönemin
   ücretinin bir kısmını geri verir. Üstelik istisna listesi de kurtarmaz —
   ikinci istisna "**bizim** hizmeti azaltmamız" hâlidir, oysa düşürme
   sahibin kendi kararıdır.
2. **`docs/123` K5 zaten şunu yazıyordu:** *"Plan düşürmede fark iadesi YOK."*
   (c), depodaki mevcut karara **en yakın** olandır; (a) ve (b) o kararı
   sessizce tersine çevirirdi.
3. **En az sürpriz üreten seçenek de budur.** Sahip Pro'nun 30 gününü ödedi;
   (c)'de o 30 günün bir günü ve bir yeteneği eksilmez, 31. gün Starter'a
   iner. (a) parayı geri verip hizmeti anında keserdi; (b) sahibin okumadığı
   bir "kredi" bakiyesi yaratır ve bir sonraki tahsilatı kimsenin
   beklemediği bir tutara çevirirdi.

**Çelişki ölçümü.** Karar `RefundPolicy` metniyle karşılaştırıldı ve
çelişmediği görüldü; metnin kendisi de bu pakette 0.2'ye çıkarıldı — çünkü
0.1 "*ürün içinde iptal yok, iletişim formundan yazın*" diyordu ve bu artık
doğru değildi. **Yön tek:** kod politikaya uydu; politikanın "iade yok"
cümlesi kodu haklı çıkarmak için değiştirilmedi, olduğu gibi durdu.

### K7 — Düşürme, ödenmiş dönemden tek bir gün ya da yetenek eksiltmez

`subscriptions.scheduled_plan_id` bir **niyettir**, bir durum değil. Hangi
planın geçerli olduğu okuma anında **tarihten türetilir**
(`SubscriptionLifecycle::effectivePlanId`):

- `now <= ends_at` → **ödenen plan.** Düşürme kararı hiçbir okumayı
  değiştirmez.
- `now > ends_at` → varsa **zamanlanmış plan.**

Zamanlayıcı **yok** ve bilerek yok: dakikada bir koşan bir komuta bağlansaydı,
komut koşmadığı gün abonelik yanlış planı gösterirdi. Aynı karar bu depoda
zaten vardı — `DatabaseEntitlementRepository` "durum alanı geç güncellenmiş
olabilir ve tarih daha güvenilir bir kanıttır" diyor.

### K8 — Kaybedilecek yetenek, onaydan ÖNCE ve adıyla

`GET /subscription/plan-change?plan_id=` bir **sorgudur**, hiçbir şey
değiştirmez ve şunu döner: yön (`upgrade`/`downgrade`), yürürlük tarihi,
**kaybedilecek** ve **kazanılacak** yeteneklerin anahtar + etiket listesi.

Etiketler `Entitlement` enum'undan gelir ve **sunucudan** iner. Arayüzde
ikinci bir etiket sözlüğü tutulsaydı, plan bir yetenek kazandığı gün ekrandaki
liste sessizce eskirdi.

Ekran, `menu.rich-media` düşecekse bunu adıyla yazar ve misafire etkisini de
söyler:

> "Guests see the difference only in your next publication — the menu behind a
> QR code you have already printed does not change."

Bu cümle bir nezaket değil, **kodun ölçülen davranışıdır** (bkz. §5).

### K9 — Ödenmiş dönemin ortasında ucuz planı satın almak REDDEDİLİR

Bu, paketin kapattığı **gerçek kusurdu.** `extendFromPayment` ödenen planı
geçerli plan yapar; yükseltmede doğru, düşürmede felaket: sahip **para öder**
ve karşılığında **o anda bir yetenek kaybeder**, üstelik ödediği Pro döneminin
günleri hâlâ dururken.

`ManageCheckout::checkout` artık bunu **422 `downgrade_requires_schedule`** ile
geri çevirir ve red sağlayıcıya gitmeden önce olur — hiçbir işlem satırı
ayrılmaz, hiçbir para hareket etmez. Dönem bitmişse (ödemesiz süre ya da askı)
korunacak bir dönem yoktur ve ucuz plan doğrudan satın alınabilir.

### K10 — İptal, zamanlanmış düşürmeyi siler

Çıkan sahip bir sonraki dönem için plan seçmiyor. Aynı şekilde başarılı bir
ödeme **ikisini birden** siler: ödeme yapan sahibin çıkma niyeti de, bir
sonraki döneme dair plan kararı da geçmişte kalmıştır.

---

## 3. BAŞARISIZ ÖDEMEDE ASKIYA ALMA VE GERİ DÖNÜŞ

### K11 — Ödemesiz süre: **7 gün**, yapılandırmadan

`config/billing.php` → `subscription.grace_days`, env
`BILLING_SUBSCRIPTION_GRACE_DAYS`. Koda gömülü hiçbir gün sayısı yok; test
bunu 3 güne çekerek ölçüyor.

**Neden 7 ve neden uydurma değil.** Sayı bu depodaki mevcut **en kısa "hâlâ
geri alabilirsin" penceresinden** alındı: `config/media-quota.php` taban
planın çöp saklama süresini `trash_retention_days => 7` yazar. Yani depo,
sonucu ağır ama geri alınabilir bir olay için yedi günü zaten ölçü kabul
etmiştir; ödemesiz süre de tam olarak o ailedendir. İkinci gerekçe takvimdir:
yedi gün haftanın her gününü **tam bir kez** kapsar — restoranın kapalı olduğu
gün ve hafta sonu, sahip hiçbir şey kaybetmeden içine düşer.

**Üst sınır dönemin kendisidir** ve yapılandırmada değil kodda uygulanır
(`SubscriptionLifecycle::boundedGraceDays`): bir dönemden uzun bir ödemesiz
süre, hiç ödemeyen bir hesabı ödeyenle eşitlerdi. **Sıfır geçerli bir
değerdir** ve bu paketten önceki davranışı verir.

### K12 — Altı evre, tek hesap

`SubscriptionPhase` + `SubscriptionLifecycle` (saf, veritabanı ve saat
okumayan bir hesap). Üç yer de aynı cevabı okur: yetenek çözümlemesi,
abonelik özeti, panel.

| Evre | Koşul | Plan yetenekleri |
| --- | --- | --- |
| `none` | Abonelik yok | ❌ (ücretsiz yolculuk çalışır) |
| `active` | `now ≤ ends_at`, iptal yok | ✅ |
| `cancelling` | `now ≤ ends_at`, iptal var | ✅ |
| `grace` | `ends_at < now ≤ ends_at + grace`, iptal yok | ✅ |
| `suspended` | `now > ends_at + grace`, iptal yok | ❌ |
| `ended` | `now > ends_at`, iptal var | ❌ |

**İptal edilmiş aboneliğe ödemesiz süre verilmez.** Ödemesiz süre, **gelmesi
beklenen** bir ödemeyi beklemek içindir; sahip "çıkıyorum" dediyse beklenen
bir ödeme yoktur ve ona yedi gün daha yetenek vermek, iptali kabul etmemek
olurdu.

### K13 — Askı hesabı KAPATMAZ

Askıda kapanan tek şey **planın verdiği ek yeteneklerdir.** Çalışma alanı
`active` kalır, veri durur, menüler yayında kalır, sahip panelini kullanmaya
devam eder — kayıt→menü→yayın→QR zinciri zaten plansız bir hesapta da çalışır
(`Entitlement` kapsam kuralı). Ekran bunu açıkça yazar ve "hesabınız kapandı"
demez.

### K14 — Geri dönüş yolu elle müdahale istemez

Başarılı bir ödeme `extendFromPayment` üzerinden `ends_at`'i bugünden bir
dönem ileri taşır, ödenen planı geçerli plan yapar ve iptal + zamanlanmış
düşürme niyetlerini siler. Evre kendiliğinden `active` olur; kimse
veritabanına dokunmaz, kimse destekten "hesabımı açar mısınız" diye istemez.

---

### K15 — Hatırlatma: ödemesiz süreye girildiği SÖYLENİR (BILL-GRACE-REMINDER-01)

K11–K14 ödemesiz sürenin mekanizmasını kurmuştu; eksik olan tek şey sahibin
bunu DUYMASIYDI. Panele bakmayan sahip, sürenin dolduğunu ancak bir şey
kapandığında fark ediyordu. `zabuno:send-grace-reminders` bu boşluğu
kapatır.

**Yalnız `Grace`.** Evre `SubscriptionLifecycle`'ın MEVCUT tek hesabından
okunur (K12); bu paket ikinci bir evre hesabı açmadı. `Active`,
`Cancelling`, `Ended` ve `Suspended` sessizdir. İptal etmiş sahibe "ödemen
gecikti" demek, kabul ettiğimiz iptali kabul etmemek olurdu; askıdaki
sahibe ödemesiz süreyi haber vermek ise geçmiş bir tarihi bugünmüş gibi
sunmaktır.

**Alıcı: çalışma alanının BUGÜNKÜ her sahibi**, her biri kendi postasında.
Fatura profilindeki adres DEĞİL — o adres mali müşavirin olabilir ve belgeyi
alan kişi ile paneli açıp ödemeyi yapabilen kişi aynı kişi değildir.
Hatırlatma bir belge değil, bir davranış çağrısıdır. Tek postaya iki sahip
yazılsaydı, bir adresin düşmesi diğerini de düşürürdü.

**Metin iki TARİH ve bir yol taşır:** dönemin bitişi, ödemesiz sürenin
bitişi (= ücretli özelliklerin açık kalacağı SON gün) ve `/app#billing`. Gün
SAYISI yazılmaz: "7 gün kaldı" cümlesi e-postanın okunduğu güne göre
yanlışlaşır. Metin ayrıca yayınlanmış menünün ve verinin KORUNDUĞUNU söyler
(K13): bunu söylemeyen bir hatırlatma, masadaki karekodun söneceğini
sandıran bir hatırlatmadır.

**Damga: abonelik/çalışma alanı + dönemin `ends_at`'i + alıcı**
(`subscription_grace_reminders`). Dönem anahtarın içindedir, çünkü yeni bir
dönem yeni bir olaydır ve yeniden haber verilir; alıcı anahtarın içindedir,
çünkü düşen bir adres yüzünden başarılı bir alıcı ikinci kez rahatsız
edilmemeli, başaramayan alıcı da bir daha hiç denenmemezlik etmemelidir.

**`log` sürücüsü gönderim DEĞİLDİR** ve damga basmaz (`docs/93` deseni,
`MailDataRightsNotifier` ile aynı sözleşme): taşıyıcısı girilmemiş bir
kurulumda damga basılsaydı, hatırlatma "gönderildi" sayılır ve taşıyıcı
geldiğinde bir daha hiç çıkmazdı.

**Bir alıcının düşmesi taramayı kesmez.** Sebep arındırılmış ve kırpılmış
hâliyle günlüğe yazılır, diğer sahipler postasını alır, komut yine de
BAŞARISIZ döner (sessiz başarı, arızayı zamanlayıcının günlüğünde görünmez
kılardı) ve düşen alıcı ödemesiz süre sürdükçe ertesi günkü koşuda yeniden
denenir — başarıya kadar, başarıdan sonra bir daha değil (`RunDueErasures`
ile aynı ders).

**Dil: kullanıcı tercihi şeması YOKTUR** ve bu paket öyle bir şema açmadı.
Metin `SiteText::pick(null)` ile kaynak dile düşer; zamanlayıcıdan koşan bir
işin "o anki dili" zaten kimsenin seçtiği bir dil değildir.

**Ticari ileti değil, hizmet bildirimidir:** yürüyen bir aboneliğin durumunu
söyler, bir şey satmaz — bu yüzden ayrıca onay aranmaz.

Zamanlama `routes/console.php`'de günde bir, sabit saatte ve
`withoutOverlapping` ile kuruludur; paylaşımlı barındırmada uzayan bir
tarama ertesi koşunun üstüne binmez.

**Garantinin sınırı söylenir.** Bu koruma ZAMANLANMIŞ koşular içindir.
Gerçek sıra `alreadyNotified` -> `notify` -> `markNotified` olduğu için —
yani damga, yukarıdaki karar gereği ancak gönderim BAŞARDIKTAN sonra
basıldığı için — komut ELLE ve eşzamanlı tetiklenirse iki koşu da "haber
verilmemiş" görebilir. `subscription_grace_reminders` üzerindeki benzersiz
indeks o durumda çift DEFTER satırını önler, çift POSTAYI değil: hatırlatma
"en çok bir kez" değil, **"en az bir kez"**dir. Bunu kapatmanın yolu damgayı
denemeden ÖNCE basmaktır ve o yol, taşıyıcısı takılan bir kurulumda
hatırlatmayı sessizce yakacağı için bilerek seçilmedi.

Sözleşmeyi donduran test:
`tests/Feature/Billing/SubscriptionGraceReminderTest.php`
(GRACE-REMINDER-01..05).

---

## 4. HEPSİ DENETİME DÜŞER — DEFTERE DEĞİL, VE BU BİR KARAR

`platform_audits`, scope `billing.subscription`, dört eylem:
`cancelled`, `cancellation_withdrawn`, `downgrade_scheduled`,
`downgrade_withdrawn`. `details` okunabilir olgudur: plan kodları, evreler,
tarihler, kaybedilecek yeteneklerin anahtarları. Denetim kaydı defter gibi
append-only'dur.

**Deftere yazılmaz** ve bu ihmal değil: `ledger_entries` çift kayıtlı bir
**para** defteridir ve her satırı bir tutarı bir hesaptan diğerine taşır.
İptal, cayma, zamanlanmış düşürme ve askı — dördü de para hareket ettirmez.
Bunlara sıfır ya da uydurma tutarlı satır yazmak, defterin okunabilirliğini
bozardı; defterin değeri tam olarak "burada yazan her satır gerçekten para"
olmasından gelir. Test bunu ölçüyor: iptalden sonra `ledger_entries` ve
`invoices` boştur.

**Fatura da kesilmez**, aynı sebeple: fatura bir **tahsilatın** karşılığındaki
belgedir. Para hareketi gerektiren tek durum — **iade** — zaten FF-197'nin
yolundan geçer ve orada hem ters defter kaydını (`payment:{id}:refund`) hem
karşı belgeyi (credit note, `docs/130` §K3) doğurur. **İkinci bir mekanizma
icat edilmedi.**

---

## 5. MİSAFİR — hiçbir şey görmez, ve bu ölçüldü

Sorunun kendisi ağırdır: bir restoranın ödeme sorunu yüzünden masadaki
misafirin menüsünü kapatmak, restoranın parasını almadığımız için onun
müşterisini cezalandırmak olurdu.

**Karar: misafir hiçbir şey görmez ve hiçbir şey kaybetmez.** Gerekçe
uydurma değil, deponun mevcut kararının doğrudan sonucudur:

1. **Temel yolculuk zaten plansızdır.** `Entitlement` kapsam kuralı:
   *"entitlement EK YETKİ verir; temel yolculuğu kapatmaz."* Menü, fiyat ve
   alerjen hiçbir kademede kapanmaz — askıda da kapanmaz.
2. **Yayınlanmış menünün hakları yayına DONDURULMUŞTUR**
   (`menu_publications.entitlements`). Askı da düşürme de **canlı** planı
   değiştirir; misafirin gördüğü sayfa ise yayın anındaki hakları okur.
   Masadaki basılı karekod aynı kâğıttır ve o kâğıdın gösterdiği sayfa
   değişmez.
3. **Fark bir sonraki yayında görünür** ve sahip bunu ekrandan önceden okur.

**Kusur ailesi tekrarlanmadı.** Bu depo dondurulmuş yayın haklarını bir kez
kaybetti: `EloquentPublicationRepository::current()` kendi
`PublicationRecord`'unu kurarken alanı atlamıştı ve sonuç, misafirin gördüğü
yayının planını **her zaman** `null` sanmak — yani her okumada sessizce canlı
plana düşmekti. `SubscriptionLifecycleJourneyTest::test_neither_suspension_nor_a_downgrade_changes_what_the_guest_already_sees`
bu yolu **askı evresinden** geçerek ölçüyor: dönem ve ödemesiz süre bittikten
sonra bile `current()` donmuş hakkı taşıyor ve `ApplyGuestRichMedia` misafirin
fotoğrafını yerinde bırakıyor.

**Misafire fatura sorunu yazılmadı** ve yazılmayacak: misafir restoranın
muhasebesini bilmez, bilmemeli ve o cümle restoranı müşterisinin gözünde
küçültürdü.

---

## 6. SAHİBİN YOLCULUĞU — üç gün

Kadıköy'deki kebapçı **Pro** planındaydı ve dönemi **30 Eylül**'de bitiyor.
Kartının son kullanma tarihi 28 Eylül'de doldu.

**30 Eylül (dönem bitti, ödeme gelmedi).** Hiçbir şey kapanmaz. Panelde
Subscription bölümünde kırmızı bir uyarı okur:

> "Your paid period ended on 30 September and no payment has arrived. Your Pro
> features stay on until 7 October. Pay below to continue."

Analitiği açık, toplu QR üretimi açık, misafirin gördüğü menü aynen yerinde.

**3 Ekim (üçüncü gün).** Hâlâ aynı uyarı, hâlâ hiçbir şey kapalı değil.
Bankasını arar, yeni kartını alır. Ödeme sayfasından öder; abonelik **o an**
bugünden itibaren bir dönem ileri gider ve panel yeniden "Your Pro plan runs
until…" der. Kimse kimseye bir şey sormaz.

**10 Ekim (onuncu gün — ödemeseydi).** Ödemesiz süre 7 Ekim'de doldu.
Analitik ve toplu QR kapalı; panel şunu der:

> "Your Pro features have been off since 7 October because the period was not
> paid for. Your published menus are still online and your data is untouched —
> a payment turns the features back on."

**Masadaki misafir bu on gün boyunca hiçbir farkı görmedi.** Karekodu
okuttuğunda menü açıldı, fiyatları ve alerjenleri okudu, tabak fotoğraflarını
gördü — çünkü o yayın Pro dönemindeyken yapılmıştı ve hakları o yayına
dondurulmuştu.

**Sahip çıkmak isterse.** Billing ekranının en altındaki "Cancel
subscription"a basar; ekran "30 Eylül'e kadar kullanmaya devam edeceksiniz,
ödediğiniz dönem için iade yapılmaz ve bu kararı 30 Eylül'den önce geri
alabilirsiniz" der. Basar. 15 Eylül'de fikri değişirse "Undo the
cancellation" ile geri döner ve **kuruş ödemez.**

**Sahip düşürmek isterse.** Aynı ekranda Starter'ı seçer. Ekran, onaydan önce,
**neyi kaybedeceğini adıyla** yazar: "Zengin görsel", "Analitik raporlama" —
ve "30 Eylül'de yürürlüğe girer, mevcut dönem için iade yapılmaz" der. 30
Eylül'e kadar hiçbir şey değişmez.

---

## 7. Yüzeyler

| Uç | Ne yapar |
| --- | --- |
| `GET /api/workspaces/{w}/subscription` | Evre, bitiş, iptal, ödemesiz sürenin sonu, zamanlanmış plan. Aboneliği olmayan için cevap **tam olarak** `{state:'none'}` (donmuş sözleşme). |
| `POST /api/workspaces/{w}/subscription/cancellation` | İptal. Gövde yok. |
| `DELETE /api/workspaces/{w}/subscription/cancellation` | İptalden cayma. |
| `GET /api/workspaces/{w}/subscription/plan-change?plan_id=` | Önizleme: yön, yürürlük, kaybedilecek/kazanılacak yetenekler. Salt sorgu. |
| `POST /api/workspaces/{w}/subscription/plan-change` | Düşürmeyi zamanlar. Yükseltmeyi 422 `not_a_downgrade` ile reddeder. |
| `DELETE /api/workspaces/{w}/subscription/plan-change` | Zamanlanmış düşürmeden vazgeçer. |

Yazma uçları `billing.manage`, sorgu uçları `billing.view` ister; yetkisiz
istek çalışma alanının **varlığını bile sızdırmaz** (404).

Panel: `SubscriptionLifecycle` (kap) + `SubscriptionLifecyclePanel` (sunum) +
sekiz Storybook hikâyesi. Dar ekran taban: tek sütun, `gap-2`/`gap-3` sıkı
boşluk, her dokunma hedefi `min-h-11`; panelin kendi dolgusu yok ki iç içe
kap dolgusu birikmesin.

---

## 8. Bilinen sınır ve açık işler

1. **Otomatik yenileme yok.** Bu depoda her dönem sahibin kendi eliyle
   ödediği bir ödemedir; "başarısız yenileme ödemesi" bu yüzden pratikte
   "dönem bitti, ödeme gelmedi" demektir. Kart saklayan bir yenileme motoru
   geldiğinde ödemesiz süre aynı yerde kalır, tetikleyicisi değişir.
2. **Hatırlatma indi; ama ödemesiz sürenin İÇİNDE tek bir haber verilir.**
   Sahip ödemesiz süreye girdiğini artık e-postayla da öğrenir (K15). Buna
   karşılık dönem BİTMEDEN ÖNCE uyaran bir posta ("N gün sonra bitiyor")
   bu pakette YOKTUR ve uydurulmadı: o, ne zaman ve kaç kez uyarılacağına
   dair bir ürün kararıdır ve sahibindir. Askıya DÜŞÜLDÜĞÜNDE de posta
   çıkmaz — bugün yalnız `Grace`'e GİRİŞ haber verilir.
3. **Yayına dondurulmuş haklar süresizdir.** Askıya düşen bir restoranın
   eski yayını, o yayının haklarıyla çalışmaya devam eder. Bu, basılı
   karekodu koruyan kararın doğrudan sonucudur ve bilinçlidir; bir gün
   sınırlanacaksa bu, misafir yüzeyinin kendi kararı olarak alınmalıdır.
4. **Billing ekranında `/subscription` ucunu artık ÜÇ bileşen okuyor**
   (`CurrentSubscriptionStatus`, `IyzicoSandboxCheckout`, `SubscriptionLifecycle`)
   ve üçü de kendi isteğini atıyor. Bu paket bu duplikasyonu kapatmadı çünkü
   kapatmak iki dondurulmuş bileşenin yeniden yazılması demekti; sayı
   testlerde açıkça yazılı (`BillingPage.subscription.test.tsx`) ve
   birleştirme ayrı bir işin konusudur. Plan kataloğu bu dertten zaten
   kurtarılmıştı: bir kez iner ve sayfadan dağıtılır.
5. **Sessiz düşürme kapısı, iki plan da canlı katalogdayken ölçer.** Sahip
   yayından kaldırılmış bir plandaysa (`purchasablePlan` `null` döner) kapı
   sessiz kalır. Katalogdan çekilmiş plandaki abone sayısı bugün sıfırdır.

---

## 9. Kapılar

Bu paketin ölçüm sonuçları PR gövdesinde raporlanır. Bu belge bir kapı
sonucunu **iddia etmez**; kapılar `docs/27`de sahiplenilir.
