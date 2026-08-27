# 38 — URL politikası ve URL motoru

**Bu belge NEDENİ taşır. DEĞERLER `config/url-policy.php`'dedir ve orada
kanoniktir; bu belge hiçbir sayıyı, listeyi veya eşiği tekrar etmez.**
Kuralları zorlayan kapılar `tests/Feature/Url` ve `tests/Unit/Url` altındadır.

## 1. Neden bir "URL motoru"?

URL bu üründe bir yönlendirme ayrıntısı değil, **beş ayrı sözleşmedir**:

| Sözleşme | Kime karşı | Bozulunca ne olur |
| --- | --- | --- |
| Kullanıcı | Yer imi, paylaşım, geri tuşu | Paylaşılan bağlantı yanlış yere gider |
| Arama motoru | Canonical, tekilleştirme | Aynı menü iki adreste, ikisi de zayıf |
| Güvenlik | Yetki sınırı | URL'deki kimlik yetki sanılır |
| Erişilebilirlik | Odak ve kaydırma | Ekran okuyucu değişimi duyurmaz |
| **Fiziksel varlık** | **Basılmış QR** | **Masadaki kod ölür, kimse fark etmez** |

Sonuncusu bu ürünü diğerlerinden ayırır. Bir web sayfasının adresi
değiştiğinde 301 koyarsınız. **Basılmış bir QR kodunu geri alamazsınız.**
Bu yüzden URL kuralları koda dağılmaz; tek bir motorda toplanır ve testle
zorlanır.

## 2. Motorun parçaları

| Parça | Sorumluluk |
| --- | --- |
| `config/url-policy.php` | Politikanın tek kanonik kaynağı (değerler) |
| `App\Domain\Url\UrlPolicy` | Politikayı tiplenmiş biçimde okur |
| `App\Domain\Url\UrlNormalizer` | Saf dönüşüm: bir adresi kanonik biçimine indirger |
| `App\Http\Middleware\CanonicalUrl` | Motoru tüm yüzeye uygular |
| `App\Rules\NotReservedSlug` | URL ad alanını korur |

Normalizer'ın **saf** olması kasıtlıdır: aynı kuralı middleware, canonical
etiketi ve sitemap ayrı ayrı yazsaydı, üç farklı doğru ortaya çıkardı.

## 3. Dış araştırmanın bu depoda GEÇERSİZ olan tavsiyesi

Yaygın URL rehberleri şunu söyler: *"Path'i tamamen küçük harfe indir ve
301 at."*

**Bu üründe o kural her basılı QR kodunu öldürür.** QR token'ı
`[A-Za-z0-9_-]{43}` biçimindedir (`App\Domain\QrDestination\QrToken`) ve
büyük/küçük harfe **duyarlıdır**. `/q/AbC...` adresini `/q/abc...` yapan bir
kural, masadaki kodu değiştirmez — yalnız onu hiçbir menüye gitmez hâle
getirir. Hata da vermez: kullanıcı 404 görür, restoran neden olduğunu bilmez.

Bu yüzden motorda harf katlama **yalnız politikada açıkça sayılan statik
öneklerde** yapılır ve bilinmeyen bir yol asla katlanmaz. Kural
`URL-NORM-OPAQUE-03` ve `URL-MW-QR-INTACT-09` ile dondurulmuştur.

Aynı araştırmanın **geçerli çıkmayan** ikinci tavsiyesi: "Türkçe karakterler
için özel çeviri katmanı yaz." Ölçüldü, gerek yok: `Str::slug()` zaten
`"Çiğköfteci Ömer'in Şöleni"` → `cigkofteci-omerin-soleni` ve `"Işıl Şahin"`
→ `isil-sahin` üretiyor. Çalışan bir davranışı ikinci kez yazmak, iki farklı
doğru üretmektir.

Araştırmanın **Filament** bölümü de bu depoya uygulanamaz: bu depoda Filament
kurulu değildir, admin yüzeyi React'tir.

## 4. Karar tablosu: path, query, fragment, state

| Kullanıcının niyeti | Doğru yer | Örnek |
| --- | --- | --- |
| Başka bir ekran | Gerçek route | `/app` |
| Paylaşılabilir görünüm | Query | `?page=2` |
| Geçici arayüz durumu | Bileşen state'i | URL değişmez |
| Aynı belgede bir başlık | Fragment | `#section-menu` |

Fragment sunucuya **hiç gönderilmez**. Bir ekranı fragment ile temsil etmek,
o ekranı sunucu günlüklerinden, analitikten ve arama motorundan gizlemektir.
Bu depoda bir kez yaşandı: gezinti bağlantıları `#menu` idi ve sayfalar aynı
`id`'yi taşıyordu; tarayıcı standart davranışıyla o elemana kaydırdı. Düzeltme
`#54`/`#57`'de kapsayıcı id'lerini `section-*` ile ayırarak yapıldı — çünkü
sorun kaydırma değil, **iki farklı şeyin aynı ismi taşımasıydı**.

## 5. Yönlendirme kodları

| Durum | Kod | Neden |
| --- | --- | --- |
| Kanonik biçime indirgeme | 301 | Gerçekten kalıcı |
| QR çözümleyici → menü | 302 | Hedef değişebilir; 301 önbellekte kilitlenir |
| Yinelenen sorgu anahtarı | 400 | Sessizce birini seçmek yetki kararını değiştirebilir |
| POST | **asla yönlendirilmez** | Gövde kaybolur, kullanıcının formu silinir |

Zincir yasaktır: hedef doğrudan nihai biçimdir (`URL-MW-SINGLE-HOP-08`).

## 6. İzleme parametreleri

`utm_*`, `gclid`, `fbclid` bir yönlendirme **tetiklemez**. Yönlendirmek,
ölçüm yapılmadan parametreyi silmek olurdu. Onlar yalnız **canonical adresin
dışında** bırakılır.

## 7. Rezerve ad alanı

Bir işletme kendine `menu` slug'ını alabilseydi, o yol iki şey birden ifade
ederdi ve hangisinin kazandığı route sırasına kalırdı. Rezerve liste elle
tutulur; `URL-RESERVED-COVERS-ROUTES-13` onun gerçek route ağacıyla uyumunu
zorlar — yeni bir üst düzey yol eklenip listeye yazılmazsa test kırılır.

Bu kural yazılırken dört yol listede olmadığı için gerçekten bulundu:
`forgot-password`, `reset-password`, `user`, `sanctum`.

## 8. Barındırma kısıtı (owner kararı, 2026-08-27)

Hedef: **netcup (AMD EPYC), Hetzner, Turhost paylaşımlı, Natro paylaşımlı,
Güzel Hosting paylaşımlı — hepsinde kalıcı olarak çalışmak.**

Bunun URL motoruna doğrudan üç etkisi var:

1. **Kanonik host koda gömülmez.** `enforce_host` varsayılan olarak kapalıdır
   ve `.env` ile açılır; aksi hâlde aynı kod beş barındırıcıda çalışamaz.
2. **Normalizasyon uygulama katmanındadır**, web sunucusu yapılandırmasında
   değil. Paylaşımlı barındırmada `nginx.conf`'a erişemezsiniz; `.htaccess`
   ise sağlayıcıya göre farklı davranır. Kural uygulamada olursa her yerde
   aynı çalışır.
3. **Ek bağımlılık yok.** Motor yalnız PHP standart kütüphanesini kullanır.

## 9. Kanıt

`URL-NORM-SLASH-01`, `-CASE-02`, `-OPAQUE-03`, `-QUERY-04`, `-DUPLICATE-05`,
`-IDEMPOTENT-06`, `URL-MW-REDIRECT-07`, `-SINGLE-HOP-08`, `-QR-INTACT-09`,
`-POST-SAFE-10`, `-DUPLICATE-11`, `URL-RESERVED-12`,
`URL-RESERVED-COVERS-ROUTES-13`.

## 10. Tarama ile indeksleme aynı şey değildir

Bu ayrım pratikte en sık karıştırılan yerdir ve iki liste bu yüzden AYRI:

| Mekanizma | Ne yapar | Kim için |
| --- | --- | --- |
| `robots.txt` `Disallow` | Botun sayfayı ÇEKMESİNİ engeller | Kimlik korumalı yüzeyler |
| `X-Robots-Tag: noindex` | Sonuçlarda GÖRÜNMEYİ engeller | Herkese açık ama indekslenmemesi gereken yüzeyler |

QR çözümleyici (`/q/`) bilerek **taranabilir** bırakılır. `Disallow` edilseydi
bot sayfayı hiç çekemez, dolayısıyla `noindex` başlığını da **okuyamazdı** —
ve başka bir yerden link verilmiş bir `/q/...` adresi içeriksiz biçimde yine
de indekslenebilirdi. Taranmasına izin verip "gösterme" demek, hiç
taratmamaktan daha güvenilirdir.

`robots.txt` elle yazılmaz, politikadan üretilir. Statik bir dosya kaçınılmaz
olarak gerçekle ayrışır: yeni bir yönetim yolu eklenir, dosyaya yazılmaz ve o
yol taranmaya açık kalır.

Hiçbiri güvenlik değildir. Gerçek koruma kimlik doğrulamadır.

## 11. QR çözümleyici neden 302 ve `no-store`?

Bu, ürünün en geri alınamaz kararıdır: **basılmış bir QR kodu geri
çağrılamaz.**

- **302, 301 değil.** Bu bir kalıcı taşıma değil, işletmenin
  değiştirebileceği bir eşlemedir (öğle/akşam menüsü, şube taşınması, kampanya).
  301 tarayıcıda ve ara katmanlarda kalıcı olarak önbelleklenir; hedef
  değiştiğinde masadaki kod eski adrese gitmeye devam eder.
- **`no-store`.** Önbelleklenen bir yönlendirme, basılı kodu eski hedefe
  kilitler.
- **Hedef adı verilmiş route'tan üretilir**, elle birleştirilmez: yol bir gün
  değişirse basılı kod yine doğru yere gitmelidir.

Kanıt kaynağı taramasıyla değil, gerçek yanıtla alınır (`URL-QR-CACHE-19`):
bir yorum satırındaki "301" kelimesi testi yanıltmamalıdır.

## 12. Kanonik adres sunucuda üretilir

`<link rel="canonical">` ve Open Graph etiketleri sunucu tarafında basılır.
İstemcide üretilseydi, JavaScript çalıştırmayan önizleme ve tarama botları
onları hiç görmezdi — ve bu sayfa çoğunlukla WhatsApp'ta paylaşılıyor.

Kanonik adres aynı normalizer'ı kullanır. İkinci bir yerde yeniden
yazılsaydı, kanonik etiket ile yönlendirmenin farklı adresler üretmesi an
meselesiydi.

## 13. Misafirin çıkmaz sokağı — ve 410'un neden kullanılmadığı

Bir karekod artık bir menüye gitmiyorsa, onu tarayan kişi restoran masasında
oturan bir müşteridir. Ona `{"message":"Not Found."}` göstermek, ürünü bozuk
gösterir. Artık okunabilir bir sayfa görüyor ve ne yapacağı söyleniyor.

**Yaygın SEO tavsiyesi burada UYGULANMAZ.** Rehberler "kalıcı olarak
kaldırılan kaynak için 410 Gone" der. Bu depoda bilinmeyen, bozuk ve devre
dışı bırakılmış token **ayırt edilemez** biçimde aynı 404'ü döner ve bu
`QR-PUBLIC-404-UNIFORM-01` ile önceden dondurulmuş bir güvenlik kararıdır.

Sebep: 410 "bu vardı ve gitti" der — yani saklamak istediğimiz bilgiyi tam
olarak açık eder. Farklı yanıt vermek, saldırganın hangi token'ların bir
zamanlar var olduğunu ölçmesine, dolayısıyla geçerli kod uzayını
daraltmasına izin verirdi. Arama motoru faydası, bu sızıntının yanında
önemsizdir — üstelik sayfa zaten `noindex`.

Yanıt biçimi **isteyene** göre değişir (tarayıcıya HTML, API istemcisine
JSON), **vakaya** göre değil; aksi hâlde tekdüzelik bozulurdu.

## 14. Host güveni ve QR hız sınırı

**Host başlığı istemciden gelir.** Laravel varsayılan olarak ona güvenir; bu,
ürettiğimiz kanonik ve imzalı adreslerin saldırganın alan adına kaymasına izin
verir. Doğrulama e-postasındaki bağlantı o alan adına giderse, kullanıcı kimlik
bilgisini oraya yazar.

Uygulama artık hangi Host'lara cevap verdiğini beyan ediyor. Liste boş
bırakılırsa `APP_URL`'in host'una düşer — host'u koda gömmek beş barındırıcıda
çalışmayı imkânsız kılardı.

Denetim **çerçevenin `TrustHosts`'u yerine URL motorunda** yapılır. Sebep
ölçüldü: `TrustHosts`, Symfony'nin süreç-genelinde statik `setTrustedHosts`
çağrısını kullanır; süitte bir yer onu tetiklediğinde 16 test birden 400
döndü ve hata, onu tetikleyen testte değil çok sonrasında göründü. Host
politikasının zaten tek sahibi URL motorudur; ikinci bir sahip eklemek bu
sınıf hatayı davet eder.

Yerel ve test ortamı muaftır: aksi hâlde her geliştiricinin makinesi ve her
CI koşusu ayrı yapılandırma ister, kural da ilk engelde toptan kapatılırdı.

**QR çözümleyici hız sınırlıdır.** Token uzayı taranabilir bir yüzeydir ve her
istek bir veritabanı araması yapar. Sınır cömerttir (aynı IP'den dakikada 60) —
bir masadaki misafirlerin arka arkaya taraması engellenmemeli — ama bir
tarayıcı için değersizdir.

## 15. QR token uzunluğu (owner kararı, 2026-08-27)

Token 43 karakter kalır. Baskıda daha küçük/yoğun bir kod için kısaltma
**yapılmayacaktır**. Bu bir ürün kararıdır: kısa kod QR yoğunluğunu düşürür
ama token uzayını da daraltır, ve basılmış kodlar geriye dönük değiştirilemez.

## 16. Herkese açık sayfalar sunucuda üretilir

Ölçüm, tahmini yendi. Pazarlama ve yasal sayfalar istemcide üretilirken bir
tarayıcı botunun gördüğü gövde **1.736 bayttı** ve içerik
`<div id="app"></div>`'den ibaretti. Yani ürünün kendi tanıtımı ne arama
motorunda ne de JavaScript çalıştırmayan AI botlarında görünüyordu. Sunucuda
üretildikten sonra aynı sayfa **9.271 bayt** gerçek içerik.

Bu sayfalarda etkileşim yoktur — yalnız metin ve bağlantı. Bu yüzden React
paketi hiç yüklenmez: botun göremeyeceği bir yükü herkese indirtmenin
karşılığı yok. Etkileşimli yüzeyler (`/app`, `/platform`) React olarak kalır.

**ADR-L06 kapsamı netleşti:** "Flowbite React birincil bileşen kütüphanesidir"
kuralı React YÜZEYLERİ için geçerlidir. Pazarlama sayfaları artık bir React
yüzeyi değildir; aynı Tailwind sınıfları ve aynı token'lar kullanıldığı için
görsel tutarlılık korunur.

Sözleşmeler silinmedi, taşındı: `AppShellRootCta.test.tsx` içindeki her madde
`tests/Feature/PublicSite/PublicHomeContractTest.php`'ye geçti (atlama
bağlantısı, bölümler, dürüst fiyatlandırma/iletişim metni, uydurma sosyal
kanıt yasağı, kırılma noktasız akışkan düzen).

Eski sözleşme "sayfa bir React montaj noktası veriyor" diyordu. Montaj
noktası, içeriğin ulaşmasının **vekiliydi** — ve o vekil yanıltıcı çıktı.
Yeni sözleşme doğrudan içeriği ister.

## 17. Henüz yapılmayanlar

Slug geçmişi ve 301 tablosu (bir işletme adını değiştirdiğinde eski adresin
yaşaması), sitemap üretimi ve `hreflang` kümesi ayrı paketlerdir. Bu paket
kanonik adresi, robots'u, noindex'i ve QR çözümleyiciyi kapsar.
