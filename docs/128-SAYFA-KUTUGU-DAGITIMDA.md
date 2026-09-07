<!--
    KARAR BELGESİ — FF-211, 2026-09-07.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her "ölçüldü" ifadesi
    gerçekten koşturulmuş bir komuta dayanır; ölçülemeyen şeyler §6'da AÇIKÇA
    "bilinmiyor" diye ayrılmıştır.
-->

# Sayfa kütüğü dağıtımda

## 1. Sahibin gördüğü şey

Depoda on altı kurumsal sayfanın İngilizce içeriği **yazılmış** durumda: ürün
genel bakışı, karekod menü, menü yönetimi ve dört alt sayfası, masalar ve
karekod, analitik, Zabuno AI, görsel ve medya, çoklu dil, çoklu şube,
tasarım ve marka, çözümler, fiyatlandırma.

Canlı sitede bunların **hiçbiri açılmıyor.**

## 2. Zincirin hangi halkası eksikti

Bir kurumsal sayfanın ziyaretçiye ulaşması için dört halka gerekiyor:

| # | Halka | Nerede yaşar | Durum (ölçüm öncesi) |
|---|---|---|---|
| 1 | Sayfanın **metni** | Kodda (`ProductPageLibrary`) | ✅ on altı sayfa yazılmış |
| 2 | Sayfanın **kütük kaydı** — "bu adres var" | `content_pages` tablosu | ❌ **üretimde boştu** |
| 3 | Sayfanın **yayın durumu** — "gösterilebilir" | Aynı tablonun bir alanı | ❌ (2 olmadan zaten anlamsız) |
| 4 | **Kapı** — durumu okuyup karar veren kod | `ShowCorporatePageController` | ✅ çalışıyor |

Eksik olan **2. halkaydı** ve eksikliği görünmüyordu.

### Somut yolculuk

Sahip bir sayfayı onayladı diyelim. Onay, kütükteki kaydın durumunu
ilerletmek demektir. Ama üretimde **o kayıt hiç yoktu** — onaylanacak bir
satır yoktu. Kapı, kütükte bulamadığı bir adrese 404 verir; sayfanın metni
kodda dursa bile.

Bunun kötü tarafı sessiz oluşuydu. Dağıtım yeşil yanıyor, konteyner ayağa
kalkıyor, sağlık kontrolü geçiyor. Dışarıdan bakan biri her şeyin yolunda
olduğunu görür. Oysa ürünün bütün kurumsal sitesi yok.

## 3. Kök neden — ölçüldü, varsayılmadı

Kütüğü dolduran komut **depoda vardı**: `php artisan site:import-map`.
Testleri de vardı. Ölçülen şey, onu kimin çalıştırdığıydı:

- `docker/entrypoint.sh` — göçleri ve plan kataloğunu çalıştırıyordu, kütüğü
  **çalıştırmıyordu**.
- `.github/workflows/deploy.yml` — hiçbir `artisan` adımı yok; imajı derleyip
  sunucuya aktarıyor, çalışma zamanı kurulumu tamamen giriş betiğine ait.
- `install.sh` — içinde tek bir `artisan` çağrısı yok.
- `scripts/` — kütükle ilgili hiçbir şey yok.

Depo genelinde arandığında `site:import-map` yalnız **testlerde** ve
**belgelerde** geçiyordu. Yani komut bir *dağıtım adımı* değil, birinin
hatırlaması gereken bir *cümleydi*. Hatırlanmadı.

### İkinci kusur: komut üretimde zaten çalışamazdı

Adımı eklerken ikinci bir şey ölçüldü ve tek başına birinciyi kalıcı
kılıyordu: `.dockerignore` bütün `docs` dizinini imajın dışında bırakıyor.
Kütük ise tam olarak o dizindeki site haritası girdisinden
(`docs/106-SITE-MAP-INPUT.md`) üretiliyor.

Yani biri sunucuya girip komutu elle çalıştırsaydı bile, komut
"site haritası bulunamadı" diyip dururdu. Kütüğün üretimde dolmasının **iki**
sebebi vardı, biri değil.

## 4. Verilen karar

Giriş betiğine (`docker/entrypoint.sh`), göçlerden ve plan kataloğundan
sonra tek bir adım eklendi:

```
php artisan site:import-map
```

Ve `.dockerignore`, `docs` dizinini elemeye devam ederken **yalnız o tek
veri dosyasını** geri alıyor.

### Neden göç (migration) değil

Bir göç hayatı boyunca **bir kez** çalışır. Site haritası büyüdüğünde yeni
adresler kütüğe hiç girmezdi ve aynı kusur, ikinci kez, daha sinsi biçimde
geri gelirdi. Ayrıca 402 satırı bir göç dosyasına kopyalamak, site
haritasının ikinci bir kopyasını yaratmak demekti — iki kopya bir gün
ayrışır.

### Neden giriş betiği

Çünkü **plan kataloğu tam olarak orada yaşıyor** ve aynı sınıftan bir
problemi çözüyor: şema değil *veri* eksikliği, ve eksikliği hiçbir yerde
kırmızı göstermiyor. İkisini ayrı yerlere koymak, bir sonraki kişinin
"veri kurulumu nerede yapılır" sorusuna iki cevap bulması demekti.

### Neden tekrar koşması zararsız

Komut yıkıcı değildir ve bu artık **ölçülüyor**, yalnız iddia edilmiyor:

- Var olan bir kaydı çoğaltmaz (`ContentPageIdentityTest`, önceden vardı).
- Bir insanın verdiği yayın kararına dokunmaz — yayın durumu, yayın tarihi
  ve geçmiş korunur (`ImportSiteMapCommandTest`, **bu pakette yazıldı**).

İkincisi eksikti ve eksikliği önemsizdi *komut elle çalıştığı sürece*. Komut
her konteyner açılışında koştuğu andan itibaren o iddia, sahibin yayına
aldığı her sayfayı ayakta tutan tek şeydir. Kırılma senaryosu somut: birisi
"belgeden gelen alanları tazeleyelim" diye yayın durumunu da tazelemeye
kalkar, ve o günden sonra **her dağıtım siteyi boşaltır.** Test tam olarak
bunu kırar.

### Neden yayın durumu ilerletilmiyor

`site:sync-content-status` komutu, içeriği yazılmış sayfaları bir kademe
ilerletir ve tavanı bilerek `content_draft`tır. O komut giriş betiğine
**bilerek konmadı** ve konmadığı bir kapıyla sabitlendi.

Gerekçe komutun kendi sözü: kalite kapısı — içerik onayı, tasarım, SEO,
erişilebilirlik, QA — insanların işidir, ve *bir betiğin her seferinde
geçtiği kapı, kapı değildir*. Dağıtım kütüğü **doldurur**, karar **vermez**.
Durum ilerletme elle ve bilerek çalıştırılır.

## 5. Adım başarısız olursa ne olur

Sessizce geçmez. Giriş betiği `set -euo pipefail` ile çalışır: komut
başarısız olursa konteyner **hiç açılmaz**, sağlık kontrolü geçmez ve deploy
akışı kırmızıya döner.

Bu bilinçli bir denge. Kütüğü dolmamış bir sürüm dışarıdan sağlıklı görünür
ama kurumsal sitesi yoktur; öyle bir sürümün sessizce yayına girmesindense
dağıtımın açıkça durması yeğdir. Göç adımı ve plan kataloğu tohumu da yıllardır
aynı kuralla çalışıyor — bu adımı istisna yapmak, tutarsızlığın kendisi
olurdu.

Bu aynı zamanda §3'teki ikinci kusurun kalıcı emniyetidir: `.dockerignore`
geri alma satırı bir gün silinirse, komut dosyayı bulamaz ve **ilk dağıtımda
yüksek sesle** durur. Sessizce boş bir kütükle yayına çıkmaz.

## 6. Bu paketin ölçemedikleri — açıkça

1. **Sunucuda kütüğün bugünkü hâli bilinmiyor.** Üretim sunucusuna erişim
   yok. Bir kurumsal adresin 404 dönmesinin iki ayrı sebebi olabilir —
   kayıt hiç yok, ya da kayıt var ama durumu yayın öncesi — ve ikisi
   dışarıdan **birebir aynı görünür**. Hangisi olduğu ölçülemedi. Ölçülen
   şey, kaydı üretecek hiçbir otomatik adımın var olmadığıdır.

2. **`.dockerignore` geri alma satırı gerçek bir imaj derlemesiyle
   doğrulanamadı.** Docker daemon bu makinede çalışmıyor. Kural Docker'ın
   belgelenmiş davranışına dayanıyor (son eşleşen desen kazanır, geri alma
   deseni varken dizin budanmaz) ve satır sırası buna göre yazıldı — ama
   **ölçülmedi**. §5'teki yüksek sesli durma, tam da bunun için var: kural
   beklendiği gibi çalışmazsa ilk dağıtım kırmızı döner, sessiz kalmaz.

3. **Sayfaların görünürlüğü bu paketle değişmiyor.** Ölçüldü: içe aktarma
   kayıtları `planned` olarak yaratır ve üretim ortamında yayınlanmamış her
   sayfa 404 döner (`PageGate`). Yani bu paket **hiçbir sayfayı canlıya
   çıkarmaz** ve çıkarmamalıdır. Yaptığı şey, sahibin onaylayacağı satırların
   üretimde var olmasını sağlamaktır — 2. halka, 3. halka için.

4. **`sitemap.xml` kurumsal sayfaları hâlâ listelemeyecek.** Ölçüldü:
   `ShowSitemapController` kütüğü hiç okumuyor; `/`, `/terms`, `/privacy`,
   `/kvkk` adreslerini sabit yazıyor ve üzerine yayınlanmış menüleri
   ekliyor. Yani canlı sitemap'teki beş adres, kütükten **bağımsız** bir
   sebeple beş. Bu ayrı bir eksiktir, bu paketin kapsamı dışındadır ve
   burada yalnız kayda geçiriliyor.

## 7. Ölçülen sayılar

Temiz bir veritabanında içe aktarma koşturuldu:

- **402** kütük satırı üretiliyor — **386** Türkçe, **16** kaynak dil
  (İngilizce).
- İçeriği yazılmış **16** sayfanın **16**'sı kütükte karşılığını buluyor;
  eksik yok.
- İkinci koşu satır sayısını değiştirmiyor.

## 8. Değişen dosyalar

| Dosya | Ne oldu |
|---|---|
| `docker/entrypoint.sh` | Kütük içe aktarma adımı eklendi (göçlerden sonra) |
| `.dockerignore` | Site haritası girdisi imaja geri alındı |
| `tests/Feature/Deployment/DeploymentContractTest.php` | Üç kapı: adım var mı, kaynak imajda mı, dağıtım yayın kararı veriyor mu |
| `tests/Feature/Content/ImportSiteMapCommandTest.php` | Yeni: yeniden dağıtım kararı geri almaz, kaynak yoksa yüksek sesle durur |

Yayın durumu ilerletilmedi, hiçbir sayfa yayınlanmadı, `SyncContentStatusCommand`
tavanına dokunulmadı.
