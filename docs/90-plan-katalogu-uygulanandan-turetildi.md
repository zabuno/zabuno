# 90 — Plan kademeleri uydurulmaz, uygulanandan türetilir

`docs/88` fiyat sayfasını kurdu ama katalog boştu; sayfa dürüstçe "fiyatlar
henüz yayımlanmadı" diyordu. Sahip kararı bana bıraktı.

## Kademeler nereden geldi

Bu üründe **tam üç yetenek** plana bağlı ve üçü de gerçekten kapıda
uygulanıyor:

| Yetenek | Nerede kapanıyor |
| --- | --- |
| `qr.bulk-generation` | Toplu karekod üretimi |
| `analytics.reporting` | Analitik raporu |
| `team.invitations` | Ekip daveti |

Ve kritik olan şu: **temel zincir plansız çalışıyor.** Kayıt → menü → yayın →
karekod → misafir sayfası, hiçbir plan olmadan işliyor ve
`RestaurantCriticalJourneyTest` bunu donduruyor.

Dolayısıyla dördüncü bir kademe icat etmek, **parası alınan ama kapanmayan
bir kapı** satmak olurdu. Bir test her satılan yeteneğin gerçekten uygulanan
bir yetenek olduğunu kontrol ediyor.

## Karar

| Plan | Fiyat | Ne ekler |
| --- | --- | --- |
| **Starter** | Ücretsiz | — |
| **Restaurant** | 499 TL/ay | Toplu karekod + analitik |
| **Team** | 999 TL/ay | + ekip daveti |

**Starter ücretsiz, çünkü zaten ücretsiz.** Temel zincir plansız çalışıyor;
buna "Starter" demek var olan gerçeği adlandırmaktır. Yokmuş gibi davranmak,
ziyaretçiye ödemeden önce yalan söylemek olurdu.

**Restaurant**, kırk masalık bir restoranın ilk gün ihtiyacı: kodları tek tek
değil toplu üretmek, ve menüsünde neyin işe yaradığını görmek.

**Team**, sahibin menüyü tek başına yönetmediği yer.

### Rakamlar hakkında dürüst not

499 ve 999, ürünün neyin yerine geçtiğine dayanıyor: bir restoran menüsünü
yılda birkaç kez yeniden bastırır ve 499 TL/ay, yılda bir baskıyla
karşılaştırılabilir bir tutar. İkiye katlayan bir merdiven okunması kolaydır.

**Bunlar taze pazar araştırmasına dayanmıyor.** Bir başlangıç noktası, bir
taahhüt değil — ve **veri oldukları için** sahibi platform ekranından
saniyeler içinde değiştirir.

## Merdiven yalnız büyür

Üst kademe alt kademenin **her şeyini** içerir; bir test bunu donduruyor.
İçermeseydi "yükselt" düğmesi bazı şeyleri kaybettirirdi — ve bunu ancak
yükselttikten sonra fark ederdiniz.

## Her planda olan, bir kez söylenir

Yetenek listesi **ek** yetkileri anlatır, temel zinciri değil. Yalnız onları
göstermek, ücretsiz kademeyi "hiçbir şey içermiyor" gibi gösterirdi — oysa
menü, yayın, karekod ve misafir sayfası her planda var.

Sayfa bunu **bir kez**, planların üstünde söylüyor; her plan altında ne
**eklediğini** yazıyor.

## Müşteri geliştirici dilini okumaz

Yetenek anahtarları (`qr.bulk-generation`) ham hâlde basılıyordu. Artık
katalogdan insanca karşılıkları geliyor ve **tanınmayan bir anahtar hiç
gösterilmiyor** — ham anahtar basmak, sessizce iç dili sızdırmak olurdu.

## Sıfır, "ücretsiz" demektir

`0,00 TRY` teknik olarak doğru ama insan onu "ücretsiz" diye okumaz, bir hata
sanır. Üç durum ayrı ele alınıyor:

| Tutar | Ne gösterilir |
| --- | --- |
| `null` (fiyatlanmamış) | "Restorana göre fiyatlanır — bize yazın" |
| `0` | "Free" |
| Diğer | Biçimlendirilmiş fiyat + "per month" |

## Yol boyunca çıkan gerçek kusur

Kataloğu ilk hâlinde bir **göçe** yazdım ve **dokuz mevcut test kırıldı**:
hepsi `plans` tablosunun boş başladığını varsayıyordu ve biri tam olarak
**boş tablo davranışını** ölçüyordu.

Testler haklıydı. **Göç şema içindir, iş verisi için değil.** Fiyat şema
değildir; sahibi onu yarın değiştirir. Katalog bir **tohuma** taşındı ve
tohum var olan bir plan koduna dokunmuyor — yani sahibin panelden yaptığı
düzenleme her dağıtımda geri alınmıyor.

Karşılığında bir kapı geldi: **giriş betiği tohumu göçlerden sonra
çalıştırmak zorunda.** Çalıştırmasaydı üretimde katalog boş kalır, fiyat
sayfası "henüz yayımlanmadı" demeye devam eder ve **kimse fark etmezdi** —
göçler geçmiş, dağıtım yeşil görünürdü.

## Kanıt

`PlanCatalogueDecisionTest` (6), `DeploymentContractTest` (+1)

| Requirement | Ne donduruluyor |
| --- | --- |
| `PLAN-CATALOG-PUBLISHED-01` | Üç kademe, ücretsizden yukarı sıralı |
| `PLAN-TIERS-MATCH-ENFORCED-01` | Satılan her yetenek gerçekten uygulanıyor |
| `PLAN-FREE-IS-FREE-01` | Sıfır "Free" okunur, "0,00" değil |
| `PLAN-INCLUDED-STATED-ONCE-01` | Her planda olan bir kez söylenir |
| `PLAN-LABELS-ARE-HUMAN-01` | Müşteri iç anahtar okumaz |

## Ürün iddiası

Çalışır: kaydolmamış bir ziyaretçi üç kademeyi, fiyatlarını ve her birinin ne
eklediğini görür; sattığımız her yetenek gerçekten kapıda uygulanıyor.
Çalışmaz: **satın alma yok** (P1-02, Iyzico anahtarları sahibinde). Sayfa
fiyatı söylüyor, ödemeyi almıyor.
