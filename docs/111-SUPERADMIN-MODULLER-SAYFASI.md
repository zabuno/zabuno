# 111 — Superadmin "Modüller" sayfası planı

> **BU BELGE BİR PLANDIR. Bugün kodda karşılığı YOKTUR.**
>
> `/engineering/modules` diye bir adres yok, modül tablosu yok, modül
> açma/kapama yok. Aşağıdaki her satır ya bugünkü bir ölçümdür ya da bir
> karardır; hiçbiri "yapıldı" demez.

**Değişmez taban:** `ff-150-medya-durustlugu` @ `origin` ile güncel,
2026-09-05.

**Yöntem.** `docs/110`'un yöntemi tekrar edildi: iddialar `modules/`
dosyalarından değil koddan türetildi. `modules/*.md` yalnız **modülün ne
olduğunu** öğrenmek için okundu; bir modülün "var" sayılması için
`app/`, `routes/`, `database/migrations/`, `config/` veya `resources/js/`
içinde karşılığı ve mümkünse bir test adı bulundu.

---

## 0. Bu belge neden var

Sahibin sorusu tek cümleydi: **"Mevcutta hangi modüller var?"**

Bu soru bugün cevaplanamıyor. Depoda 62 modül tanımı duruyor
(`modules/*.md`) ve **62'sinin de ilk satırında aynı cümle yazıyor:**

> **PLANNING ONLY. Şu an çalıştırılamaz.**

`modules/menu-catalog.md` de bunu yazıyor. Oysa menü kataloğu bu ürünün
en yoğun bağlamı: 9 domain sınıfı, 23 uygulama sınıfı, kendi rota
dosyası, 21 Feature testi. `modules/publication.md` de bunu yazıyor;
oysa yayınlama, zamanlama, geri alma ve taslak önizleme çalışıyor.

Yani 62 dosyanın durum iddiası, en az 15'inde açıkça yanlış. Bu, bu
deponun `docs/109` §8.7'de adı konmuş kusur ailesinin ta kendisidir:
*cümle bir zamanlar doğruydu, altındaki gerçek değişti, kimse geri dönüp
cümleyi kontrol etmedi.*

Bir ekran bu dosyaların "durum" iddiasını okuyup ekrana bassaydı, o
aileye yeni ve en görünür üyeyi eklerdi: superadmin'in "hangi modüller
var" diye baktığı yerde 62 kez "çalıştırılamaz" yazardı.

Bu yüzden bu sayfa, `modules/` klasörünün bir görüntüleyicisi
**olmayacak**.

---

## 1. Bu sayfa hangi soruyu cevaplıyor

Superadmin teknik bir kullanıcıdır ama sonsuz zamanı yoktur. "62 modül
var" cümlesi ona hiçbir şey vermez — o sayıyı `ls modules | wc -l` de
söyler.

Superadmin bu sayfayı **dört durumda** açar ve dördü de somut:

1. **Bir kiracı arıyor, "fotoğraf yükleyemiyorum" diyor.** Sorması
   gereken: bu kurulumda medya hattı gerçekten ayakta mı, yoksa bu
   ortamda hiç kurulmamış bir yetenek mi? (`docs/109` §8.7'nin dördüncü
   satırı tam olarak buydu: tarayıcı yokken işleme asla bitmiyordu.)
2. **Bir dağıtım öncesi.** Sorması gereken: bu sürümde hangi bağlamlar
   birbirine bağlı, birini değiştirirsem hangisi kırılır?
3. **Bir belge "var" diyor.** Sorması gereken: doğru mu? Bugün bu soruyu
   cevaplamanın tek yolu depoyu elle taramaktır — `docs/110` tam olarak
   bunu yapmak için yazıldı ve bir günlük iş oldu.
4. **Bir kiracı için bir şeyi kapatması gerekiyor.** Sorması gereken:
   kapatabilir miyim? (Bugünkü cevap: hayır — §5.)

Sayfanın cevapladığı soru bu dördünün ortak paydasıdır:

> **"Bu kurulumda hangi yetenek gerçekten çalışıyor, neye bağlı, ve
> bunu nereden biliyorum?"**

Son parça — *nereden biliyorum* — isteğe bağlı değil. Kanıtsız bir
"çalışıyor" rozeti, olmayan bir sayfadan daha kötüdür: superadmin ona
bakıp aramayı bırakır.

---

## 2. Sayfa nereye konur

**Karar: `/engineering/modules`.** `/platform` değil.

Gerekçe deponun kendi cümlesinde yazılı
(`resources/js/components/platform/PlatformApp.tsx`):

> Release readiness ve denetim izi buradan ÇIKTI: `/engineering`.
> Aynı kişi olabilir, aynı iş değil.

`/platform` **ticari** kabuktur: plan, abonelik, sağlayıcı anahtarı —
para ve satış. Modül envanteri para değil, **mühendislik kanıtıdır**;
`/engineering` altında bugün zaten sürüm hazırlığı ve AI denetim izi
duruyor. Modüller oraya komşudur.

İki kabuk da `OpsShell` üzerinde durur ve bölüm **adresten** gelir,
fragment'ten değil (`docs/38` §4, `zabuno-tenant-analytics-locked-rule`).
Yeni bölüm bu kuralı bozmaz: `EngineeringSection` birliğine `'modules'`
eklenir, `basePath="/engineering"` değişmez.

Yetki mevcut olandır: `EnsurePlatformSuperAdmin`, enumeration-safe 404.
Yeni bir izin türetilmez — `modules/core-module-registry.md` `module.manage`
izninden söz eder, ama o izin bir **yönetim** eylemi içindir ve §5'e göre
bugün yönetim yok. Salt okunur bir sayfa için ikinci bir izin adı
uydurmak, hiçbir şeyi korumayan bir kayıt olurdu.

---

## 3. Veri nereden gelir

Bu bölüm belgenin merkezidir. Deponun kuralı nettir: **veri yoksa o alan
çizilmez** (`docs/109` §8.3, §8.2). Bir modül tablosu icat etmek, 62
satırlık uydurma bir kaynak üretmek olurdu.

Bugün beş kaynak adayı var. Dördü gerçek, biri tuzak.

### 3.1 `config/core-modules.php` — GERÇEK, ama yalnız 16 satır

16 CORE modülünün kodu, adı, sürümü, `module_class`'ı, `dependencies`
listesi, `deterministic_baseline` ve `ai_posture` alanları burada. Bu
veri **doğrulanmış**: `App\Domain\Modules\ModuleManifest` her alanı
sınırda reddediyor (semantik sürüm, `CORE-01..CORE-16` aralığı,
`deterministic_baseline='required'`, bilinen `ai_posture` kümesi) ve iki
test bunu donduruyor: `Unit/Domain/Modules/ModuleManifestTest`,
`Unit/Modules/CoreKernelManifestTest`.

Sınırı da net: dosyanın kendi yorumu *"Stage 1 scope: this is a static
manifest registry, not a lifecycle engine"* diyor. 46 çekirdek-dışı
modül burada **yok** ve `ModuleManifest` onları bugün kabul de etmez
(`assertValidCodeForClass` yalnız `core` sınıfını tanıyor).

### 3.2 `config/module-dependency-dag.json` — GERÇEK, ve kanıtlı

13 düğüm, 4 kenar. Her kenarda `evidence.path` ve `evidence.pattern`
var: hangi dosyanın hangi satırı bu bağımlılığı kanıtlıyor. Dosyanın
kendi cümlesi ne olduğunu da ne olmadığını da söylüyor: *"a snapshot of
what the current source imports across module boundaries"* — mimari
zorlama değil, gözlem. `scripts/module-graph-gate` bunu okuyor.

Bu, sayfanın "neye bağlı" sorusunun **tek dürüst kaynağıdır**.
`modules/*.md` dosyalarındaki "Bağımlılıklar" satırları bir tasarım
niyetidir, bir ölçüm değil.

Dikkat: bu dosyanın düğüm adları (`MenuCatalog`, `Publication`, …)
`app/` bağlam adlarıdır, `modules/` dosya adları değil. İki isim uzayı
var ve eşleşmeleri elle kurulmuş değil (§3.5).

### 3.3 Kodun kendisi — GERÇEK, ama türetilmesi gerekir

Bir modülün "bu kurulumda çalışıyor" olması dört gözlemden türetilir ve
dördü de bugün ölçülebilir:

| Gözlem                | Nereden okunur                                       |
| --------------------- | ---------------------------------------------------- |
| Bağlam dizini var mı   | `app/Domain/*`, `app/Application/*`, `app/Infrastructure/*` |
| Yüzeyi var mı          | `routes/api/*.php`, `routes/web.php` kayıtlı rota     |
| Verisi var mı          | `database/migrations/` → tablo şemada mevcut          |
| Kanıtı var mı          | `tests/` altında adıyla eşleşen test dosyası          |

`tests/Feature/Api/ModularApiRouteRegistrationTest` bu tür bir gözlemi
zaten yapıyor: modüler rota dosyalarının gerçekten kayıtlı olduğunu
donduruyor.

### 3.4 `modules/*.md` — TUZAK

Okunabilir. Çalışma zamanında ayrıştırılabilir. Ve **durum sorusuna
cevap veremez**: 62'sinin de üstünde aynı "PLANNING ONLY" satırı var
(§0).

Bu dosyalardan alınabilecek tek dürüst şey **modülün ne olduğudur** —
amacı, sınırı, sahibi. Bunlar niyet beyanıdır ve niyet değişmedikçe
eskimez. Durum, sürüm, bağımlılık ve "enable mi" iddiaları buradan
**alınmaz**.

### 3.5 Veritabanı — YOK

Modül tablosu yok. Migration yok. `ModuleEnabled`/`ModuleDisabled`
event'i yok. `modules/core-module-registry.md`'nin saydığı yaşam
döngüsü (`not_installed → installed → enabled → disabled →
uninstalled`) hiçbir yerde modellenmiş değil.

Bu bir eksiklik değil, bir **kapsam kararıdır** ve §5'te gerekçesi var.

---

## 4. Modül başına çizilecek alanlar

Her alan kendi kaynağını taşır. Kaynağı olmayan alan tabloda yok — bu
yüzden aşağıdaki liste, `modules/*.md` şablonunun taşıdığı 20 küsur
başlıktan çok daha kısa.

| Alan                | Kaynak                                        | Kaynağı yoksa                                     |
| ------------------- | --------------------------------------------- | ------------------------------------------------- |
| Modül adı           | `config/core-modules.php` (CORE) / `modules/*.md` H1 (diğer) | —                                  |
| Kod (`CORE-05`)     | `config/core-modules.php`                      | Çekirdek-dışı modülde **çizilmez** (kod yok)      |
| Sınıf               | `config/core-modules.php#module_class` (CORE); dosya adı öneki `opt-` (opsiyonel); kalanı `ürün` | — |
| Sürüm               | `config/core-modules.php#version`              | Çekirdek-dışında **çizilmez** — uydurma `1.0.0` yazmaz |
| Bağımlılıklar       | CORE için `config/core-modules.php#dependencies`; bağlam düzeyinde `config/module-dependency-dag.json` (kanıt yoluyla birlikte) | Kenar yoksa **boş bırakılır**, "bağımsız" yazmaz |
| Kod karşılığı       | `app/{Domain,Application,Infrastructure}/<Bağlam>` dizini + dosya sayısı | Dizin yoksa satır "kod karşılığı yok" der |
| Yüzey               | Kayıtlı rota (`routes/api/*.php`, `routes/web.php`) | Rota yoksa **boş** — modül içeriden çalışıyor olabilir |
| Kanıt               | Eşleşen test dosyası adı/adedi                 | Test yoksa **boş bırakılır**; bu bilgi başlı başına bir cevaptır |
| AI duruşu           | `config/core-modules.php#ai_posture`           | Çekirdek-dışında **çizilmez**                    |
| Determinist taban   | `config/core-modules.php#deterministic_baseline` | Çekirdek-dışında **çizilmez**                   |
| Spec bağlantısı     | `modules/<ad>.md` dosya varlığı                | Dosya yoksa bağlantı yok                          |
| Durum rozeti        | §4.1'deki türetme                              | Türetilemiyorsa **belirsiz**, "yok" değil        |

### 4.1 Durum rozeti nasıl türetilir

Dört değer, ve dördü de bir gözlemden gelir — hiçbiri elle girilmez:

- **Uygulanmış** — bağlam dizini VE (rota VEYA migration) VE en az bir
  test. Üçü birden.
- **Kısmen** — bağlam dizini var, ama rota/migration/test'ten en az biri
  yok.
- **Yalnız tanım** — `modules/<ad>.md` var, `app/` karşılığı yok.
- **Belirsiz** — eşleme kurulamadı (§4.2).

Rozetin yanında **her zaman** onu üreten gözlem yazılır: "3 dizin ·
1 rota dosyası · 21 test". Rozet tek başına bir iddiadır; gözlem onu
denetlenebilir yapar. `docs/109` §8.7'nin beş örneğinin ortak noktası,
iddiaya eşlik eden ölçümün gösterilmemiş olmasıydı.

### 4.2 İki isim uzayı — ve neden elle eşlenmez

`modules/` dosya adları (`menu-catalog`, `qr-print-export`) ile `app/`
bağlam adları (`MenuCatalog`, `QrDestination`) aynı küme değil.
`qr-print-export`in ayrı bir bağlamı yok; kodu `QrDestination` ve
`Support/QrDestination` içinde yaşıyor. `core-money-ledger` ise iki yere
dağılmış: `Domain/Money` ve `Infrastructure/Ledger`.

Bu eşleme **veridir ve bugün hiçbir yerde yazılı değildir.** İki yol
var:

- **A. Ekranda elle eşle.** Ekran kodunun içine 62 satırlık bir eşleme
  gömülür. Yanlış olduğunda kimse fark etmez, hiçbir test kırılmaz —
  §8.7 ailesine yeni bir üye.
- **B. Eşlemeyi veri yap ve teste bağla.** `modules/<ad>.md` içine tek
  bir alan eklenir (`contexts:`), ve bir test her `modules/*.md` için ya
  bu alanın bulunduğunu ya da açıkça "kod karşılığı yok" dediğini
  doğrular.

**Karar: B.** Ama bu, bu paketin işi değil — B, `modules/` klasörünün
62 dosyasına dokunmayı gerektirir ve o ayrı bir yazardır. Sayfa B'siz
başlar ve eşleşmeyeni **"belirsiz"** gösterir. Belirsiz bir satır
dürüsttür; yanlış eşlenmiş bir satır değildir.

---

## 5. Bir modül açılıp kapatılabilir mi

### 5.1 Bugünkü cevap: HAYIR

Bu bir tasarım tercihi değil, bir ölçüm:

- Modül tablosu yok, `enabled` sütunu yok, `ModuleEnabled`/
  `ModuleDisabled` event'i yok.
- `config/core-modules.php` bir PHP `return [...]`'dır; çalışma zamanında
  yazılamaz.
- `ModuleManifest` değişmez (immutable) bir value object'tir ve dosyanın
  kendi yorumu *"lifecycle state (install/enable/disable) is not modeled
  here yet"* diyor.
- Hiçbir rota, iş (job), menü veya API bir modül anahtarına bakmıyor.

### 5.2 En yakın mekanizma: Pennant — ve neden bu değil

Depoda kiracı bazında bir açma/kapama mekanizması **var**: Laravel
Pennant, kapsamı çalışma alanı (`PennantFeatureFlags`,
`FeatureFlagPort`), `features` tablosu şemada, ve değerler kiracı
bağlamı yükünde istemciye ulaşıyor (`BuildWorkspaceContextPayload` →
`features`).

Ama:

- Tanımlı **tek** bayrak var: `novice-home`, ve o da `static fn () =>
  true`.
- Onu okuyan tek yer bir ekran kutusudur (`DashboardSetupJourney`).
- Onu **yazan hiçbir ekran yok**. Bugün bir kiracıda kapatmanın yolu
  veritabanına elle satır yazmaktır.

`AppServiceProvider`'ın kendi yorumu sınırı da koyuyor: *"Bayrak
kullanılmayan bir yerde tanımlanmaz: tanımı olup okuyanı olmayan bayrak
ölü koddur."* 62 modül için 62 bayrak tanımlamak, 61 ölü bayrak üretmek
olurdu.

### 5.3 `core-entitlements` ile ilişki: aynı şey değil

`modules/core-module-registry.md` "bir modülün tenant için enable olması
entitlement'a bağlıdır" diyor. Kodda bu **kurulamaz**, çünkü kodun
kendisi tersini yazıyor (`App\Domain\Entitlement\Entitlement`):

> **Kapsam kuralı:** entitlement EK YETKİ verir; temel yolculuğu
> kapatmaz. Kayıt→menü→yayın→QR zinciri plansız bir hesapta çalışmaya
> devam eder (`RestaurantCriticalJourneyTest` bunu donduruyor).

Modül disable etmek tanımı gereği **temel yolculuğu kapatmaktır**. Yani
"modülü kapat" entitlement'ın üzerine kurulamaz; kursaydı, bugün
`RestaurantCriticalJourneyTest`'in donduruduğu sözü bozardı.

Bugünkü entitlement kümesi de zaten üç değerdir — `qr.bulk-generation`,
`team.invitations`, `analytics.reporting` — ve üçü de bir **modül** değil,
bir modülün üstündeki bir yetenektir. Plan kataloğu bunları
`plans.entitlements` sütunundan okur (`Feature/Entitlement/
EntitlementResolutionTest`).

**Sonuç:** iki farklı soru, iki farklı mekanizma. Entitlement "bu plan
şunu da yapabilir" der; modül anahtarı "bu yetenek bu kurulumda hiç
yok" der. Aynı düğmeye bağlanmazlar.

### 5.4 Karar: global önce, kiracı sonra — ve bugün ikisi de yok

Modül anahtarı bir gün yapılırsa, **önce dağıtım (global) seviyesinde**
yapılır, kiracı seviyesinde değil. Üç gerekçe:

1. **Bağımlılık grafiği globaldir.** `Publication → MenuCatalog` kenarı
   her kiracıda aynıdır. Kiracı bazında kapatma, aynı grafiği N kez
   doğrulamak demektir; global kapatma bir kez.
2. **Bugün gerçek ihtiyaç global.** `docs/109` §8.2'nin "video oynatıcı
   yok", "CDN yok" satırları kiracıya göre değişmiyor — o yetenek bu
   kurulumda hiç yok. Superadmin'in cevaplayamadığı soru bu.
3. **Kiracı bazlı kapatma, kiracının verisine dokunur.**
   `modules/core-module-registry.md`'nin kendi kuralı: *"Disable veri
   silmez; yalnız erişimi durdurur."* Bu sözü tutmak, her modülün
   okuma/yazma yollarını ayrı ayrı kapatmayı gerektirir — 62 modülün
   62'sinde ayrı bir iş. Global kapatma bunu gerektirmez.

**Sayfada bugün hiçbir anahtar çizilmez.** Sayfa salt okunurdur. Devre
dışı bir anahtar da çizilmez: `docs/109` §8.4'ün kuralı — *"Devre dışı
bir düğme bir söz verir: 'bir gün, bir şekilde'."*

---

## 6. Çizilmeyecekler ve sebepleri

`docs/109` §8.2 geleneği. Her satır **bugün için** doğrudur ve kendi
gerekçesini taşır; gerekçe düştüğünde satır da düşer (§8.6).

| Çizilmeyen                        | Çizilmedi çünkü                                                                 |
| --------------------------------- | ------------------------------------------------------------------------------- |
| Enable/disable anahtarı            | Yaşam döngüsü hiçbir yerde modellenmiş değil; anahtar hiçbir şeyi kapatmazdı (§5.1) |
| Devre dışı (pasif) anahtar         | Tutulmayacak bir söz verirdi — `docs/109` §8.4                                   |
| Modül sağlık durumu (health check) | Modül başına sağlık kontrolü yok; olmayan bir sondanın yeşil rozeti yalan olur   |
| "Kaç kiracı kullanıyor" sayacı     | Modül→kiracı kullanım ölçümü yok; `analytics_events` modül değil misafir olayı ölçer |
| Sürüm geçmişi / upgrade izi        | `ModuleUpgraded` event'i yok, sürüm sabit `1.0.0`, değişiklik kaydı tutulmuyor  |
| Çekirdek-dışı modülde sürüm/AI duruşu | `config/core-modules.php` yalnız 16 CORE taşıyor; kalan 46 için alan yok      |
| "Kurulu / kurulu değil" ayrımı     | Modüler monolit tek dağıtımdır; her şey kuruludur. Ayrım anlamsız olurdu        |
| Bağımlılık grafiği görselleştirmesi | 4 kenar bir grafik gerektirmez; liste dürüst, grafik zengin görünürdü           |
| `modules/*.md` "durum" iddiaları   | 62'si de "PLANNING ONLY" diyor ve en az 15'inde bu yanlış (§0)                  |
| Boş yerlerde "0" veya "yok"        | `docs/109` §8.3 — veri yoksa boş bırakılır                                       |

---

## 7. Uygulama sırası

Sıra, her adımın **kendi başına doğrulanabilir** olmasına göre kuruldu.
Hiçbir adım bir sonrakini beklemeden değer üretir.

**1. Okuma ucu — yalnız CORE 16.**
`GET /api/admin/modules`, `EnsurePlatformSuperAdmin` arkasında. Kaynak
yalnız `config/core-modules.php` + `config/module-dependency-dag.json`.
Neden önce: bu ikisi bugün **doğrulanmış** tek kaynak (§3.1, §3.2). 16
satırlık dürüst bir cevap, 62 satırlık belirsiz bir cevaptan iyidir.

**2. Ekran — `/engineering/modules`.**
`EngineeringSection` birliğine `'modules'`, `OpsShell` bölüm listesine
bir giriş. Bölüm adresten gelir. Neden ikinci: uç olmadan ekranın
gösterecek verisi olmaz, ve uydurma veriyle çizilen bir ekran sonra
gerçek veriye uydurulmaz — baştan yeniden yazılır.

**3. Kod-karşılığı türetmesi.**
Bağlam dizini, rota, migration ve test gözlemleri (§3.3) uca eklenir;
durum rozeti (§4.1) bunlardan türetilir. Neden üçüncü: bu, sayfanın asıl
cevabıdır ama en çok yanılma payı taşıyan parçadır. Kanıt gözlemi rozetle
**aynı anda** çizilir, sonradan eklenmez.

**4. `modules/` eşlemesi — ayrı yazar, ayrı paket.**
62 dosyaya `contexts:` alanı ve onu doğrulayan test (§4.2 B). Neden en
son: `modules/` klasörüne dokunmak bu paketin kapsamı dışıdır ve tek
writer kuralı gereği ayrı bir pakettir. Bu adım gelene kadar eşleşmeyen
her satır **"belirsiz"** kalır.

**5. Modül yaşam döngüsü — bu planın kapsamında DEĞİL.**
Global açma/kapama (§5.4) ayrı bir karar, ayrı bir tasarım ve ayrı bir
veri modelidir. Buraya yalnız sırayı işaretlemek için yazıldı: 1–4
bitmeden 5 başlamaz, çünkü neyin kapatılabileceğini bilmeden anahtar
tasarlanmaz.

---

## 8. Kabul ölçütleri

Her madde bir testle donar; testsiz kural eklenmez
(`docs/37` geleneği).

1. **Uç yalnız gerçek kaynağı okur.** `config/core-modules.php` dışında
   bir CORE alanı üreten kod yoktur; test, dosyaya eklenen bir satırın
   uçta göründüğünü ve çıkarılan bir satırın kaybolduğunu doğrular.
2. **Yetki.** Superadmin olmayan bir kullanıcı `/engineering/modules` ve
   `GET /api/admin/modules` için düz 404 alır (mevcut enumeration-safe
   davranış).
3. **Boş alan boş kalır.** Sürümü olmayan bir modül satırında sürüm
   sütunu boştur; test `"1.0.0"`, `"-"`, `"0"` veya `"bilinmiyor"`
   yazılmadığını doğrular (`docs/109` §8.3).
4. **Rozet gözlemsiz çizilmez.** Her durum rozetinin yanında onu üreten
   gözlem metni bulunur; test rozetin tek başına çizilemediğini
   doğrular.
5. **Bağımlılık kanıtı taşınır.** Çizilen her kenar,
   `config/module-dependency-dag.json` içindeki `evidence.path` değerini
   de gösterir.
6. **Adres fragment değildir.** `/engineering/modules` doğrudan
   açıldığında modüller bölümü çizilir; bilinmeyen bölüm varsayılana
   düşer (`docs/38` §4).
7. **`modules/*.md` durum iddiası hiçbir yerde okunmaz.** Test,
   ekran/uç kodunda `modules/` altındaki dosyaların "PLANNING ONLY"
   satırının ayrıştırılmadığını doğrular.

---

## 9. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6'nın kuralı bu belgeye de uygulanır: §6 tablosundaki her
satır **bugünün** ölçümüdür.

İki satır özellikle gözlenmeli, çünkü ikisinin de gerekçesi düşebilir:

- **"Kaç kiracı kullanıyor" sayacı.** Bugün modül→kiracı ölçümü yok. Ama
  `analytics_events` tablosu bir kez daha genişletilirse (`docs/84`
  menü mühendisliği için bir kez genişletildi), bu gerekçe kendiliğinden
  düşebilir — ve kimse geri dönüp bakmayabilir.
- **"Modül sağlık durumu".** `HostCapabilityProbe` ve
  `ConnectionProbe` bugün **zaten** çalışıyor (`Feature/Platform/
  HostCapabilityProbeTest`, `Feature/PlatformAdmin/ConnectionProbeTest`).
  Bunlar modül başına bir sonda değil, ana bilgisayar ve bağlantı
  sondasıdır — ama aradaki mesafe göründüğünden kısa. `docs/109`
  §8.6'nın uyardığı ikinci tür buydu: *yetenek VARDI ve kimse fark
  etmemişti.*

Bu tabloyu okuyan herkes şunu sormalı: *gerekçe bugün hâlâ doğru mu?*
