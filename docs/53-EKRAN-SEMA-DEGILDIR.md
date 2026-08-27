# 53 — Ekran şema değildir

**Paket:** `FF-01a` — `FRONTEND-FOUNDATION-v1`.
**Durum:** uygulandı.

## 1. Tek bir kök neden

Sahip sekiz ekranı tek tek gösterip aynı soruyu sordu: "böyle mi kalmalı?"
Ekranlar ayrı ayrı incelendiğinde farklı sorunlar gibi görünüyorlar. Değiller.

> Geliştiricinin **"bu gerçekten bağlı"** diye kendine ürettiği kanıt,
> restoran sahibinin ekranında kalıcı hâle gelmiş.

Bu metinler ve rozetler **yanlış değildi** — yanlış **yerdeydi**. Bir modülün
gerçekten kablolandığını göstermek geliştirme sırasında meşru bir ihtiyaçtır;
o kanıtın kalıcı arayüz öğesine dönüşmesi ise ayrı bir karardır ve hiç
verilmemiştir.

## 2. Bulunanlar

### Veritabanı kimlikleri ve sayaçlar, "durum rozeti" kılığında

| Ekran | Rozet | Gerçekte ne |
| --- | --- | --- |
| Brand | `#3` | Markanın **birincil anahtarı** |
| Locations | `#1` | Lokasyon **sayısı** — altında zaten "1 locations" yazıyor |
| Menu | `#3` | Lokasyon kimliği |
| Media | `#0` | Varlık sayısı |
| Publication | `#12` | Yayının kimliği |
| Dashboard | `draft` | Çevrilmemiş ham enum |
| Analytics | `Today` | Hemen altındaki `Range` seçicisinin tekrarı |
| Team | `Invitations connected` | Kablolamanın çalıştığına dair mühendis notu |

Sekizin **altısı** kullanıcıya hiçbir şey söylemiyordu. Bedeli yalnız gürültü
değil: her sayfada bir "her şey yolunda" rozeti bulunması rozetlerin tamamını
okunmayan süse çevirir — ve o noktadan sonra **gerçek uyarı da fark edilmez.**

**Kural:** rozet, kullanıcının *bilmediği* ve hakkında bir şey
*yapabileceği* bir durumu bildirir. "Yüklendi" bunun ikisi de değildir;
sayfanın içeriği zaten kanıtıdır.

### Mühendislik metni, kullanıcı metni yerine

| Nerede | Yazan | Sorun |
| --- | --- | --- |
| Publication | `Requires menu.publish permission` | İç izin anahtarının adı |
| Publication | `Scheduled publish is not available in Stage 1.` | İç yol haritası aşaması |
| Publication | `Publishing creates an immutable snapshot` | Uygulama detayı |
| Publication | `QR destination capability is not available…` | İç yetenek sözcüğü |
| Publication | `This bulk QR wizard has not been submitted` | Test durumu anlatısı |
| Media | `…no scan approves itself.` | Denetçi için yazılmış politika cümlesi |
| Billing | `no billing API has been queried` (×3) | Kablolama anlatısı |
| Billing | `…fetched live from the server-backed billing API…` | Sayfa açıklaması yerine **uygulama raporu** |

Hepsi kullanıcının **yapabileceği** şeyi söyleyen cümlelerle değiştirildi.
Örnek: "Publishing creates an immutable snapshot" →
*"Publishing saves a fixed copy. Later edits stay private until you publish
again."* Aynı gerçek, ama artık bir kullanıcı kararını destekliyor.

### Medya kütüphanesi — en ağırı

Kullanıcının **kendi yüklediği fotoğrafı**, kendi yazdığı açıklamayla değil
`#7` diye listeleniyordu; alt metin onun altında ikincil duruyordu. Sıralama
tersine çevrildi.

Yanında iki erişilebilirlik kusuru daha çıktı:

- Her satırdaki silme düğmesinin adı aynıydı ("Delete"). Ekran okuyucu
  kullanan biri, **geri alınamaz** bir eylemde hangi görseli sildiğini
  ayırt edemiyordu. Artık `Delete {ad}`.
- Bölgedeki üç liste (varlıklar, slot kategorileri, yaşam döngüsü) adsızdı ve
  "Assets" başlığı gerçek bir başlık değil, başlık gibi görünen bir `<p>`ydi.

## 3. Neyi ATMADIM

`{state}` alanını önce tamamen kaldırdım; testler bunu yakaladı ve haklıydılar.
Ham enum'u basmak yanlıştı, ama **durumun kendisi** kullanıcının en çok
önemsediği bilgidir: "menüm yayında mı". Alan atılmadı, **çevrildi**.

Aynı ayrım sürüm numarası için de geçerli: kimlik atıldı, sürüm kaldı — her
yayında arttığı için kullanıcı için okunabilir tek sayı odur.

## 4. Muhafızlar

Tek seferlik temizlik yetmez: kusur ekleme anında değil, **sonraki sayfa**
yazıldığında geri gelir.

- `badges.guard.test.ts` — hiçbir rozet kimlik, sayaç veya çevrilmemiş ham
  alan gösteremez. Yazıldığı anda benim gözden kaçırdığım bir kusuru
  (`PublicationPage`) buldu.
- `workspace-vocabulary.guard.test.ts` — restoran yüzeyindeki hiçbir metin
  mühendislik sözcüğü taşıyamaz. Kapsam bilerek yalnız `workspace/`: platform
  yönetim yüzeyi teknik bir kullanıcıya aittir ve orada "entitlement" meşrudur;
  aynı kuralı oraya dayatmak doğru metni bozardı.

Testlerdeki kimlik iddiaları **yokluk iddiasına** çevrildi: kusuru doğrulayan
test, kusuru engelleyen teste dönüştü.

## 5. Yan bulgu — i18n boru hattı

Muhafız ilk yazıldığında `i18n/workspace/` içine konmuştu. `workspace.ts`
katalogları `import.meta.glob` ile keşfeder, yani dosyayı bir **katalog** sanıp
paketledi ve boru hattı çöktü. Araç yalnız `error.message` yazdığı için hata
`Cannot read properties of undefined` diyordu ve **nerede kırıldığı
görünmüyordu**; yığın izi eklenince sebep bir bakışta çıktı.

Yığın izini bastıran hata işleyicisi kalıcı olarak düzeltildi.

Bu arada bir yanlış teşhis yaptım ve kayda geçiyor: arızayı önce "önceden
vardı" diye sınıflandırdım, çünkü FF-00 öncesi `vite.config.ts` ile de
tekrarlıyordu. Tekrarlıyordu çünkü **muhafız dosyası iki denemede de
oradaydı** — kontrolüm değişkeni izole etmiyordu.

## 6. Kanıt

993 PHP testi, 1012 ön yüz testi, pint ve eslint temiz. Dondurulmuş i18n
kataloğu 436 → 440 anahtar olarak yeniden mühürlendi; mührün yanındaki gerekçe
her değişikliği tek tek anlatıyor.

## 7. Bu paketin KAPSAMADIĞI

Sahibin gösterdiği ekranlarda kalan, daha büyük işler:

| Yüzey | Kalan |
| --- | --- |
| Header | Omnibox yok (ara / git / oluştur / komut / sor); orta alan tamamen boş; e-posta adresi ham hâlde kabukta |
| Sidebar | Dokuz düz madde, gruplama ve ikon yok; hesap maddeleri açılır menü değil düz bağlantı |
| Menu | **Aynı adlı iki kategori** ("Tatlılar") ve aynı adlı iki ürün oluşturulabiliyor; sıra numaraları ekranda |
| Publication | Kullanılamaz durumdayken bile tüm QR dışa aktarma yüzeyi ve altı boş alanlı toplu sihirbaz çiziliyor |
| Media | Slot kataloğu altı kez "no assets yet" tekrarı |
| Brand | `Slug` etiketi ve teknik değeri; düzenleme formunda locale/timezone/currency serbest metin |

Bunlar `FF-01`…`FF-04` kapsamı ve şablon/kontrat işi gerektiriyor.
