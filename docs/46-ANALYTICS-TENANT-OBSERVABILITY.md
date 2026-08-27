# 46 — Ölçüm ve Gözlem: Tenant Bazında (KİLİT KURAL)

**Durum:** Faz 1 kuruldu (dikiş + CSP + tenant bağlamı + testler).
**Requirement ID:** `ANALYTICS-TENANT-SEAM`
**İlgili:** `docs/38-URL-POLICY.md` §4, `docs/39-URL-SEO-ROADMAP.md`

---

## 1. Kural

Sahibin sözleri:

> "google analytics, yandex metrica, google tag manager, dataLayer, hotjar,
> metabase, gibi analiz araçları ile herşeyi analiz edip gözlemleyebilmeliyim
> **tenant bazında**. bu kesin kural, kemik kural, kilit kural."

Bu kural **değiştirilemez**. Yeni bir ekran, yeni bir yüzey veya yeni bir
gezinti biçimi eklendiğinde, o şeyin tenant bazında ölçülebilir olduğu
gösterilmeden iş bitmiş sayılmaz.

---

## 2. Kuralın ilk kurbanı: fragment gezintisi

Panel `#menu`, `#brand`, `#dashboard` gibi **fragment** adresleriyle
geziniyordu. `docs/38` §4 bunu zaten yasaklamıştı:

> Fragment sunucuya hiç gönderilmez. Bir ekranı fragment ile temsil etmek, o
> ekranı sunucu günlüklerinden, analitikten ve arama motorundan gizlemektir.

Politika yazılmış ama panele **uygulanmamıştı**. Sonucu somut olarak şuydu:
bir restoran sahibi panelde on ekran gezse, Google Analytics'te **tek
sayfalık bir ziyaret** görünürdü. "Hangi ekran kullanılıyor, hangisi terk
ediliyor" sorusunun cevabı ölçüm araçlarında **hiç yoktu**.

Bu paket fragment gezintisini kaldırdı. Artık her ekranın gerçek bir adresi
var:

| Yüzey | Adres |
|---|---|
| Restoran paneli | `/app/{workspace}/{section}` |
| Geliştirici paneli | `/platform/{section}` |
| QR menü | `/m/{key}/{slug}` (zaten gerçek adres) |
| Tanıtım sitesi | `/` ve alt sayfaları (zaten gerçek adres) |

Sunucu bölüm adını **doğrulamaz**: bölüm listesinin sahibi istemcidir.
Sunucuda ikinci bir liste tutmak, iki listenin ayrışacağı bir gün yaratırdı.
Tanınmayan bir bölüm dashboard'a düşer.

---

## 3. Tek dikiş yeri: Google Tag Manager

Uygulamaya **yalnız GTM** girer. GA4, Yandex Metrica ve Hotjar konteynerin
içinden yönetilir. Bu bir mühendislik kararıdır:

- Yeni bir araç eklemek **deploy gerektirmez**; sahibi GTM arayüzünden ekler.
- Ölçüm sözleşmesi tek yerdedir: `window.dataLayer`. Altı ayrı SDK, altı ayrı
  olay adlandırması ve altı ayrı tenant alanı demek olurdu; ilk tutarsızlık
  günü raporlar birbirini tutmazdı.
- Bir aracı kapatmak, konteynerden bir etiketi durdurmaktır — koddan script
  sökmek değil.

**Metabase bu listede değildir ve olmamalıdır.** O tarayıcıda çalışmaz;
doğrudan PostgreSQL'i okur. Onun tenant bazlı olması bir script meselesi
değil, **veri modeli** meselesidir (§5).

### Yapılandırma

`config/analytics.php` (ortam değişkenleriyle):

| Değişken | Anlamı |
|---|---|
| `ANALYTICS_GTM_CONTAINER_ID` | `GTM-XXXXXXX`. **Boşsa ölçüm tamamen kapalıdır.** |
| `ANALYTICS_GA4_ENABLED` | GA4 için CSP izinlerini açar |
| `ANALYTICS_YANDEX_METRICA_ENABLED` | Metrica (+ Webvisor iframe) izinleri |
| `ANALYTICS_HOTJAR_ENABLED` | Hotjar izinleri |

Yerel geliştirme ve testler kapalı durumdadır: tek bir script yüklenmez ve
testler ağ üzerinden bir ölçüm aracına asla bağlanmaz.

---

## 4. Sessiz kayıpların kapatıldığı yerler

Bu bölüm, "sayfa çalışıyor ama veri yok" durumlarının listesidir. Hiçbiri
tarayıcıda hata üretmez; bu yüzden hepsi teste bağlanmıştır.

### 4.1 CSP, ölçümü sessizce boğar

Uygulamanın CSP'si `strict-dynamic` ve `connect-src 'self'` ile sıkıdır.
Hiçbir şey yapılmasaydı GTM **yüklenirdi** ama GA4/Metrica/Hotjar
ölçümleri sunucuya **hiç ulaşamazdı**: sayfa sorunsuz görünür, raporlar boş
kalırdı.

İzinler **açılan araçtan türetilir**, elle yazılmaz
(`AnalyticsConfiguration::cspSourcesFor()`). Ölçüm kapalıyken CSP bugünkü
kadar sıkı kalır — kapalı her araç, saldırı yüzeyi olarak da kapalıdır.

`script-src-elem` bilerek genişletilmez: `strict-dynamic` altında bir host
listesi yazmak, nonce'un sağladığı güveni host güvenine indirger.

### 4.2 Tek sayfa uygulamasında sayfa görüntülemesi oluşmaz

`history.pushState` tarayıcıya göre sayfa **değiştirmez**. GA4 ve Metrica ilk
yüklemede bir kez ölçer; sonrası **hiç ölçülmez**. Bu yüzden her bölüm
değişimi `trackPageView` ile açıkça bildirilir
(`resources/js/lib/analytics.ts`).

`page_path`, **sunucunun gördüğü yolun aynısıdır**. Sunucu günlüğü, GA4
raporu ve Metabase sorgusu aynı satırı gösterebilmeli; üç kaynağın birbirini
doğrulayabilmesinin tek yolu budur.

### 4.3 Tenant bağlamı geç gelir

Workspace kimliği API'den döner. O ana kadar oluşan olaylar tenant'sız
olurdu — ve boş kalan olay **her zaman aynı olaydır**: ziyaretin ilk sayfası.
Yani en çok ölçülmek istenen an, hiçbir restorana ait olmayan bir satır
olurdu.

Bu yüzden bağlam bilinene kadar olaylar **kuyruğa alınır** (üst sınır 50) ve
bağlam gelince tenant alanıyla birlikte gönderilir.

Ayrıca `/app` adresi, workspace yüklenince `replaceState` ile kanonik
adresine çekilir (`/app/{slug}/{section}`) — `pushState` değil, çünkü bu bir
gezinti değil, aynı yerin tam adının yazılmasıdır.

### 4.4 Kişisel veri geri alınamaz

`dataLayer`'a giren şey GTM üzerinden **üçüncü taraflara akar**. Bu yüzden
bilinen kişisel alan adları (`email`, `name`, `phone`, `token`, `address`…)
geliştirme ve testte **hata fırlatır**, üretimde sessizce düşürülür.

`dataLayer`'a giren tenant alanları: `zabuno_tenant_id`,
`zabuno_tenant_slug`, `zabuno_surface`, `zabuno_locale`, ve menüde
`zabuno_location_id` / `zabuno_menu_id`. Kullanıcı adı, e-posta veya telefon
**hiçbir koşulda** girmez.

---

## 5. Metabase tarafı (sunucu)

Metabase tarayıcıyı değil veritabanını okur. Tenant bazlı analiz orada bir
**veri modeli** garantisidir:

- Ölçülebilir her olgu tablosu `workspace_id` taşır. QR çözümlemesi ve menü
  açılışı zaten `workspace_id`, `location_id`, `qr_code_id`, `menu_id` ile
  kaydedilir (`AnalyticsEventType`).
- Metabase için **salt-okunur** bir PostgreSQL rolü kullanılır; uygulama
  rolüyle bağlanılmaz.
- Ürün metriği ile web ziyaret metriği **karıştırılmaz**: "kaç kez tarandı"
  (QR çözümlemesi) ile "kaç kez görüntülendi" (sayfa görüntülemesi) ayrı
  kanallardır. Arama motorundan gelen bir ziyaretçi karekod taramamıştır; onu
  tarama gibi saymak ürünün birincil metriğini sessizce şişirirdi.

---

## 6. Kalan işler

| # | İş | Durum |
|---|---|---|
| 1 | Fragment gezintisini kaldır, gerçek adresler | ✅ |
| 2 | GTM dikişi + CSP türetimi + tenant bağlamı | ✅ |
| 3 | Dört yüzeyde tenant bağlamı | ✅ |
| 4 | Kişisel veri koruması + testler | ✅ |
| 5 | Ürün olayları (menü yayınlama, davet, sipariş) `trackEvent` ile | ⬜ |
| 6 | Metabase salt-okunur rolü ve kurulum yönergesi | ⬜ |
| 7 | Onboarding hunisi: workspace'i olmayan kullanıcının ölçümü | ⬜ |
| 8 | Hotjar oturum kaydında hassas alan maskeleme (`data-hj-suppress`) | ⬜ |
| 9 | Çerez/izin (consent) yönetimi — GTM Consent Mode | ⬜ |

**7 hakkında not:** bugün workspace yüklenmeden sayfa görüntülemesi
gönderilmez, çünkü tenant'sız bir olay kuralın istediği soruya cevap vermez.
Onboarding hunisini ölçmek ayrı bir karar gerektirir (tenant'sız bir yüzey
kimliği kabul edilecek mi?) ve sahibinin kararına bırakılmıştır.
