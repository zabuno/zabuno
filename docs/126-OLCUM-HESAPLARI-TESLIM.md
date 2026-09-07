# 126 — Ölçüm hesapları teslim edildi: üç kimlik, iki tuzak, bir eksik

> **Bu belge bir teslim kaydıdır.** Ölçümün nasıl kurulduğunu anlatmaz —
> o karar `config/analytics.php` başında ve `docs/46`'da yazılıdır ve burada
> **tekrar edilmez**. Bu belge yalnız şunu söyler: 2026-09-07'de hangi
> kimlikler geldi, her biri nereye girer, girmezse ne olmaz, ve hangi iş
> hâlâ dışarıda bekliyor.

**Sahibin cümlesiyle:** *"ozan tamam ama dns eksik."*

## 0. Ölçülmüş durum (2026-09-07)

| Ne | Durum | Nerede |
| --- | --- | --- |
| GTM konteyner kimliği teslim edildi | Evet | Bu belge §1 |
| GA4 ölçüm kimliği teslim edildi | Evet | Bu belge §1 |
| Yandex Metrica sayaç numarası teslim edildi | Evet | Bu belge §1 |
| Ortam anahtarları örnek dosyalarda bulunabilir | Evet, testle kilitli | `tests/Feature/Analytics/AnalyticsEnvironmentContractTest.php` |
| **Üretim sunucusunda kimlik girildi** | **Hayır.** Bugün üretimde ölçüm KAPALI | — |
| **GTM konteynerinde etiketler kuruldu** | **Bilinmiyor.** Depodan görülmez | — |
| **DNS / SPF / DKIM** | **Eksik.** Ozan'dan bekleniyor | Bu belge §5 |

"Bilinmiyor" burada "geçti" değildir. GTM konteynerinin içi bir dış
arayüzdür; depo onu ne okuyabilir ne de doğrulayabilir.

## 1. Üç kimlik, üç ayrı yer

| Araç | Kimlik | Nereye girer |
| --- | --- | --- |
| Google Tag Manager | `GTM-W3DF46LN` | **Üretim sunucusunun kendi `.env` dosyasına**, `ANALYTICS_GTM_CONTAINER_ID` olarak |
| Google Analytics 4 | `G-LGRKH7L672` | **GTM arayüzüne**, GA4 yapılandırma etiketinin içine. Koda girmez |
| Yandex Metrica (sayaç) | `112364424` | **GTM arayüzüne**, Metrica etiketinin içine. Koda girmez |

Üç kimlikten yalnız biri koda dokunur. Bu bir eksiklik değil, `docs/46`
§3'teki kararın kendisidir: uygulamaya yalnız GTM girer, diğer araçlar
konteynerin içinden yönetilir. Sonucu somut: sahibi yarın bir araç daha
eklemek isterse kimse deploy yapmaz, kimse sunucuya girmez — GTM arayüzünden
eklenir.

Kimlikler örnek dosyalara (`.env.example`, `.env.production.example`,
`.env.staging.example`) **yazılmadı** ve yazılmayacak: o dosyalar depoda,
depo herkese açık. Oraya yazılan bir konteyner kimliği, ürünü kuran her
kişinin aynı konteynere veri basması demek olurdu — raporda yabancı
restoranlar görünürdü. Örnek dosyalar yalnız **anahtarın adını** taşır;
`AnalyticsEnvironmentContractTest` hem adların orada olduğunu hem de
değerlerin boş kaldığını kilitler.

### Depo tarafında bu paketin yaptığı tek şey

Üç örnek dosya artık ölçüm dikişinin dört anahtarını da gösteriyor:
`ANALYTICS_GTM_CONTAINER_ID`, `ANALYTICS_GA4_ENABLED`,
`ANALYTICS_YANDEX_METRICA_ENABLED`, `ANALYTICS_HOTJAR_ENABLED`.

Bunlar zaten `config/analytics.php` tarafından okunuyordu ama **hiçbir örnek
dosyada görünmüyordu**. Var olduğu bilinmeyen bir anahtar aranmaz; aranmayan
anahtar yazılmaz. Dağıtımı yapan kişi ölçümü "kurdum" sanarken kapalı
bırakırdı ve bunu fark ettiren hiçbir hata çıkmazdı.

## 2. Kimlik yoksa ölçüm yoktur — ve bu sessizdir

Bu davranış `config/analytics.php` başında ve `AnalyticsConfiguration::
isEnabled()` içinde yazılıdır; burada tekrar edilmez, yalnız **atıf** yapılır:
konteyner kimliği boşken tek bir script yüklenmez ve CSP bugünkü kadar sıkı
kalır.

Burada söylenmesi gereken tek şey sonucudur: bu durum ekranda **hiçbir iz
bırakmaz**. Menü açılır, panel çalışır, konsolda hata yoktur. Yalnız raporlar
boştur. Ölçüm geriye dönük toplanamaz — kimliğin girilmediği her gün kalıcı
olarak kayıptır.

## 3. Birinci tuzak: hedefi açmadan GTM'de etiket kurmak

Bu, dağıtım günü saatlerce aranan türden bir kusurdur, o yüzden açıkça
yazıyorum:

**GTM'de GA4 etiketini kurmak yetmez.** `ANALYTICS_GA4_ENABLED` açık
değilse tarayıcı GA4'ün isteğini CSP ile **engeller**.

Engellenmiş bir istek, kurulumu yapan kişinin baktığı hiçbir yerde
"başarısız" görünmez:

- Sayfa normal açılır, misafir menüyü görür.
- GTM önizleme modu etiketi **"tetiklendi"** gösterir — çünkü etiket
  gerçekten tetiklenmiştir; engellenen şey ondan sonraki ağ isteğidir.
- GA4 gerçek zamanlı raporu boş kalır.

Üç işaretin ikisi "çalışıyor" der. Aranan yer genelde GTM'in içi olur ve
orada bulunacak bir şey yoktur. `config/analytics.php`'deki `destinations`
listesi GTM'i yapılandırmaz — **CSP'yi** yapılandırır. Bir araç orada açılmadan
konteynerdeki etiketi işe yaramaz.

Doğru sıra tektir: önce `destinations` içinde hedefi aç, sonra GTM'de
etiketi kur.

## 4. Restoran yolculuğu: karekod basıldı, misafir okuttu

Somut olay: bir restoran sahibi karekodunu bastırdı, masaya koydu, misafir
telefonuyla okuttu ve menü açıldı. Sahip ertesi gün panelde bu ziyaretin
görünmesini bekliyor.

O ziyaretin rapora düşmesi için altı halkanın **hepsi** çalışmak zorunda:

1. Misafir `/m/{key}/{slug}` adresini açtı. (Gerçek adres — fragment değil;
   `docs/46` §2.)
2. Sunucu sayfayı konteyner kimliğiyle döndürdü. **`ANALYTICS_GTM_CONTAINER_ID`
   boşsa zincir burada biter.**
3. Tarayıcı GTM'i indirdi — CSP `googletagmanager.com`'a izin verdiği için.
4. Sayfa `dataLayer`'a tenant alanlarını yazdı (`zabuno_tenant_id`,
   `zabuno_tenant_slug`, `zabuno_surface`, `zabuno_locale`). Raporun "hangi
   restoran" sorusuna cevap verebilmesinin tek sebebi bu adımdır.
5. GTM içindeki GA4 etiketi (`G-LGRKH7L672`) tetiklendi ve isteği çıktı —
   **`ANALYTICS_GA4_ENABLED` açık olduğu için** CSP izin verdi (§3).
6. Metrica etiketi (`112364424`) aynısını `mc.yandex.ru` için yaptı.

Halkalardan biri kopuksa **misafir yine menüyü görür**. Kimse şikâyet etmez,
hiçbir ekran kırmızı olmaz. Kaybolan tek şey sahibin ertesi gün bakacağı
satırdır.

Bir uyarı: "karekod kaç kez tarandı" ile "sayfa kaç kez görüntülendi" aynı
şey değildir ve karıştırılmamalıdır (`docs/46` §5). Birincisi ürünün kendi
kaydıdır ve GTM'den bağımsız çalışır; ikincisi bu zincire bağlıdır. Arama
motorundan gelen bir ziyaretçi karekod taramamıştır.

## 5. Hâlâ eksik: DNS, SPF/DKIM

Ölçüm tarafı tamamdır; **e-posta tarafı değildir.** Ozan'dan bekleniyor:

| Kayıt | Türü | Ne için |
| --- | --- | --- |
| SPF | TXT | Gönderen alan adının Mailgun adına posta yollamaya yetkili olduğunu söyler |
| DKIM | TXT | Giden postanın imzasını doğrular |
| Takip kaydı | CNAME | Mailgun'un teslim/açılma takibi |

İş, Mailgun panelinde alan adı **"Verified"** yazana kadar bitmiş sayılmaz.

Bunlar olmadan ne çalışmaz, somut olarak: Zabuno'ya kayıt olan bir restoran
sahibine doğrulama e-postası **çıkmaz**, `/app` kapısı onu içeri almaz ve
elinde yapabileceği hiçbir şey kalmaz. Aynı eksik ekip davetini ve şifre
sıfırlamayı da yutar (`docs/93`, `docs/110` P0-06).

Kum havuzu alanı (`sandbox....mailgun.org`) bunun yerine geçmez: yalnız elle
yetkilendirilmiş alıcılara teslim eder, gerçek bir restoran sahibine davet
gitmez.

Mailgun anahtarı ve alan adı env dosyasına değil, panelin kendi kasasına
girilir (`/platform/credentials`, `docs/94`).

## 6. Kalan işler

| # | İş | Kim | Durum |
| --- | --- | --- | --- |
| 1 | Örnek dosyalarda anahtarların bulunabilirliği | Depo | Yapıldı |
| 2 | Üretim `.env`'ine `ANALYTICS_GTM_CONTAINER_ID=GTM-W3DF46LN` | Dağıtım | Bekliyor |
| 3 | GTM'de GA4 etiketi (`G-LGRKH7L672`) | Sahip / GTM | Bekliyor |
| 4 | GTM'de Metrica etiketi (`112364424`) | Sahip / GTM | Bekliyor |
| 5 | DNS: SPF, DKIM, takip CNAME + Mailgun "Verified" | Ozan | Bekliyor |
| 6 | Hotjar hesabı | — | Yok; hedef kapalı |
| 7 | GTM Consent Mode / çerez izni | Depo | `docs/46` §6 madde 9 |

Madde 6 bir eksiklik değil bir karardır: kapalı bir araç, saldırı yüzeyi
olarak da kapalıdır. "İleride lazım olur" diye açık bırakılmaz.

## 7. Bu belgenin gerekçe süresi

`docs/109` §8.6 bir kuralı kalıcılaştırdı ve burada tekrar geçerlidir:
yukarıdaki her "bekliyor" ve her "yok" satırı **2026-09-07 için** doğrudur
ve her satır bir gerekçe taşır. Gerekçe düştüğünde satır da düşer.

Bu belgede özellikle çabuk eskiyecek üç satır var:

- **§0 "üretimde ölçüm kapalı."** Kimlik sunucuya girildiği gün düşer. Bu
  belge o gün güncellenmezse, ölçüm çalışırken burada "kapalı" yazıyor
  olacak — ve birisi buna bakıp gerçek bir kusuru arayacak.
- **§5 "DNS eksik."** Ozan kayıtları yazıp Mailgun "Verified" dediği gün
  düşer. Doğrulaması dış bir panelde; depo bunu kendi kendine anlayamaz.
- **§6 madde 6 "Hotjar hesabı yok."** Hesap açılırsa bu bir gerekçe olmaktan
  çıkar ve `ANALYTICS_HOTJAR_ENABLED` bir karara dönüşür.

Tehlikeli olan, gerekçesi düşmüş bir satırın yerinde durmasıdır: bir kez
"yok" yazıldıktan sonra kimse geri dönüp bakmaz. Bu belgeyi okuyan herkes
şunu sormalı: *bu satırın gerekçesi bugün hâlâ doğru mu?*
