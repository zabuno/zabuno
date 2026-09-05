# 110 — MVP müşteri faydası eksikleri: bağımsız değerlendirme

> **BU BELGE TARİHSEL BİR KAYITTIR — 2026-09-05'te kurtarıldı.**
>
> Öksüz dalda `docs/71` numarasını taşıyordu; o numara bu arada
> `docs/71-ODAK-GOSTERGESI.md` tarafından alınmış. Belge kurtarılırken
> **110**'a taşındı — iki belgenin aynı numarayı taşıması, koddaki
> `docs/71` atıflarını okunamaz hâle getirirdi.
>
> Sekiz gün boyunca `zabuno-mvp-customer-value-gaps-doc-v1` dalında öksüz
> kaldı: PR açılmamış, main'e hiç girmemişti. Depo envanteri çıkarılırken
> bulundu ve içeriği hiçbir yerde başka kopyası olmadığı için buraya alındı.
>
> **Tabanı aşağıda yazılı commit'tir ve o taban artık eskidir.** Aradan
> geçen sürede burada "yok" denen bazı şeyler yapıldı — mutfak rolü, çoklu
> menü, şube çalışma saatleri, menü denetim izi, misafir tarafı servis-dışı
> ve kapalı durumları gibi. Bu yüzden liste bir GÖREV listesi değil, o
> tarihteki bir ÖLÇÜMDÜR.
>
> Yöntemi hâlâ geçerli ve asıl değeri orada: iddialar belgelerden değil
> koddan türetilmiş, "belge var diyor ama kodda karşılığı yok" olan hiçbir
> madde "var" sayılmamış. Bugün bir madde okunurken sorulacak soru şudur:
> *gerekçe hâlâ doğru mu?* (bkz. `docs/109` §8.6)

**Değişmez taban:** `main` @ `2d55c007281ed0b14db6908f8bec192b5890ce65`
**Tarih:** 2026-08-28
**Kapsam:** Bu belge yalnız **eksikleri** kaydeder. Kapsam daraltma veya
özellik kaldırma kararı vermez; fazlalık gözlemi yalnız en sondaki
[§12 Yorum](#12-yorum--mvp-için-erken-veya-fazla-geniş-yatırımlar)
bölümündedir ve orada da bir öneri olarak durur, bir karar olarak değil.

**Yöntem.** Değerlendirme, mevcut belgelerin iddialarından değil, depodaki
koddan türetildi: `routes/api/*.php` yüzeyinin tamamı, `app/Domain`,
`app/Application`, `app/Http/Controllers`, `database/migrations`,
`resources/views/public-menu.blade.php`, `resources/js/components/workspace`,
`config/*`. Belgeler (`docs/18`, `docs/61`, `docs/68`, `docs/69`, `docs/70`)
yalnız **teyit** için kullanıldı; bir belge "var" dediği hâlde kodda karşılığı
bulunmayan hiçbir madde bu listede "var" sayılmadı.

---

## 1. Ana hüküm

**Kapsam yatayda geniş, günlük restoran işletme yolculuğunda dikey olarak
eksik.**

Depo bugün 17 uygulama bağlamı (`app/Application/*`), 62 modül
tanımı (`modules/`), yetmişe yakın tasarım/yönetişim belgesi ve 142 PHP test dosyası
taşıyor. Kimlik, kiracılık, yetkilendirme, para/defter, medya veri modeli,
AI yetenek düzlemi, platform yönetimi, SEO, i18n boru hattı ve tasarım
sistemi — hepsinin bir iskeleti var.

Buna karşılık, bir restoran sahibinin **her gün** yapacağı şey şudur:

> Menüyü kur → fiyatı değiştir → tükenen ürünü kaldır → yanlış yazılanı
> düzelt → sırayı düzenle → yayınla → gerekirse geri al.

Bu yedi adımın **üçü** bugün üründe yok:

| Günlük eylem | Bugün mümkün mü |
| --- | --- |
| Kategori/ürün ekle | ✅ `POST .../categories`, `.../menu-entries` |
| Fiyat değiştir | ✅ `PUT .../menu-items/{id}/price` |
| Alerjen değiştir | ✅ `PUT .../menu-items/{id}/allergens` |
| Görünürlüğü aç/kapat | ✅ `PUT .../menu-items/{id}/visibility` |
| **Ürünü/kategoriyi sil** | ❌ hiçbir `DELETE` yok |
| **Adı düzelt (kategori/ürün)** | ❌ hiçbir `PUT name` yok |
| **Sırayı değiştir** | ❌ `position` sütunu var, uç nokta yok |
| **Yayını geri al** | ❌ yalnız `POST publications` + `GET current` |

Kanıt: `routes/api/menu-catalog.php` (9 satırın tamamı),
`routes/api/publication.php` (2 satır). Arayüz tarafında da aynı:
`MenuCatalogWorkspace.tsx` içindeki tüm eylem işleyicileri
`handleCreateMenu`, `handleCreateCategory`, `handleAddMenuEntry`,
`handleSaveAllergens`, `handleSavePrice`, `handleToggleVisibility` —
silme, yeniden adlandırma veya sıralama işleyicisi yok.

Bunun müşteri için anlamı tek cümleyle: **Zabuno bugün bir menüyü
yayımlayabilir, ama bir menüyü işletemez.** Yazım hatası yapan bir sahip,
o hatayı düzeltmek için ürünü gizlemekten ve doğrusunu yeniden eklemekten
başka bir yol bulamaz — ve eskisi veritabanında sonsuza kadar kalır.

---

## 2. İki hedef ayrıdır ve karıştırılmamalıdır

Bu belge boyunca iki farklı bitiş çizgisi kullanılır. Aynı listeyle ikisine
birden gidilmez.

### Hedef A — Kapalı pilot (5–10 restoran, elle kurulum destekli)

Kurulumu Zabuno ekibi yapar; sahibin telefonundan yaptığı tek şey günlük
bakımdır. Ödeme yoktur, sözleşme yoktur, destek doğrudan insandır.
**Amaç:** ürünün gerçek bir serviste ayakta kaldığını ve gerçek bir menünün
gerçek bir misafire doğru göründüğünü öğrenmek.

Bu hedef için gereken şey **ürünün eksiksiz olması değil**, günlük döngünün
kapanmasıdır. Pilot **başlamadan önce** kapatılması gereken liste P0'dır
(§4). Pilotun kendisinin **ürettiği** kanıt ise ayrı bir sınıftır ve
PV-01'dir (§4b) — pilot başlamadan var olamaz.

### Hedef B — Halka açık ücretli self-service MVP

Sahibi hiç tanımadığımız biridir, siteye kendi gelir, kendi kaydolur, kendi
kurar, kendi öder, tıkandığında kendi çözer. **Amaç:** gelir ve tekrar
edilebilir aktivasyon.

Bu hedef, A'nın üstüne fiyat, ödeme, kendi kendini onaran hesap yönetimi ve
destek yüzeyi ekler. P1 listesi budur.

**Kritik ayrım:** A'daki bir eksiği insan emeğiyle kapatmak meşrudur
(concierge kurulum, elle içe aktarma). B'de aynı eksik doğrudan gelir
kaybıdır, çünkü orada insan yoktur.

---

## 3. Taban: bugün gerçekten çalışan değer

Eksik listesinin doğru okunması için önce çalışanı kaydediyoruz. Aşağıdaki
maddelerin hepsi kodda karşılığı olan, testle korunan yeteneklerdir; eksik
listesinde **tekrar edilmezler**.

| Yetenek | Kanıt |
| --- | --- |
| Basılı QR sabit kalır, yayın değişir | `/q/{token}` → 301 → `/menu/{key}/{slug}`; `qr_codes.token` değişmez, yayın `menu_publications` snapshot'ıdır. Fiyat güncellemesi QR'ı yeniden bastırmaz. |
| Yayın anlık görüntüdür, taslak sızmaz | `BuildPublicationSnapshot::fromDraftTree()` — istemcinin gönderdiği snapshot **hiç** kullanılmaz, sunucudaki taslaktan üretilir |
| Misafir menüsü: kategori gezinme, arama, fiyat, alerjen | `resources/views/public-menu.blade.php` — kategori çıpaları, `#menu-search` canlı filtre, `PriceLabel`, alerjen çipleri |
| Misafir menüsü hafiftir | 12 kategori × 20 ürün = **7,4 KB gzip** (`PublicMenuPayloadBudgetTest`) |
| PWA / çevrimdışı | `public-diner-sw.js`, `/menu/` ve `/q/` kapsamlarına kayıtlı; yükleme düğmesi ve çevrimdışı durumu |
| Temel analitik | `qr_resolve` ve `menu_open` ayrı olaylar; bugün/7/30 gün, yaklaşık benzersiz, lokasyon ve QR kırılımı (`docs/68`) |
| Ekip rolleri gerçekten sınır çizer | `MembershipRole` Owner/Manager/Editor/Member; `RoleBoundariesTest` sekiz sınırı, yarısı bir iznin **yokluğunu** dondurur |
| Kiracı izolasyonu | `WorkspaceIsolationJourneyTest`, `tenant_isolation_evidence` (yerel kapsamlı) |
| Adres politikası | kanonik URL, `sitemap.xml`, `robots.txt`, slug değişince 301 |
| Uçtan uca kritik yolculuk | `RestaurantCriticalJourneyTest` — kayıt → menü → yayın → QR → misafir → analitik |

Bu taban gerçektir ve küçümsenmemelidir: **aynı QR ile yayın güncelleme**
bu ürün kategorisinin çekirdek vaadidir ve Zabuno onu yapıyor.

---

## 4. Kapalı pilot (5–10 restoran): başlatma eksikleri (P0) ve pilot validasyonu (PV)

Bu bölüm **iki ayrı sınıf** taşır ve ikisi aynı zaman diliminde yaşamaz.

- **P0-01 … P0-09 — pilot başlatma eksikleri.** Pilot **başlamadan önce**
  kapatılmış olmalıdır. **Dokuz** maddedir.
- **PV-01 — pilot validasyon / başarı kanıtı.** Pilot başlamadan önce
  **var olamaz**: pilot boyunca üretilir ve **pilot bitişinde** GREEN olur.
  Bir eksik değil, pilotun kendi çıktısıdır.

Ayrım mantıksaldır ve önceki bir taslakta karışmıştı: gerçek bir restoranın
gerçek servisinde çalıştığının kanıtı, o restoran ürünü kullanmadan önce
toplanamaz. Pilot **başlangıcında** hazır olması gereken şey ürün, altyapı ve
**ölçüm mekanizmasıdır** (P0-01…P0-09 ve §9.1–§9.3 giriş kapıları); gerçek
kullanım, destek yükü ve retention **sonucu** pilot **sırasında** ölçülür ve
pilot sonunda değerlendirilir (PV-01 ve §9.5 çıkış kapısı; ayrıca U-08, U-12,
U-13).

Her madde: müşteri sorunu · mevcut repo kanıtı · gerekli ürün sonucu ·
gözlenebilir kabul ölçütü.

---

### P0-01 — Menü CRUD eksik: silme, yeniden adlandırma, sıralama yok

**Müşteri sorunu.** Sahip "Mercimek Çorbsı" yazdı. Düzeltmenin yolu yok.
Kategoriyi yanlış sırada kurdu; içecekler en üstte, çorbalar en altta kaldı.
Sezonluk "Yaz Menüsü" kategorisi eylülde de duruyor. Sahip ürünü *gizler* ve
doğrusunu yeniden ekler — menüsü her hatada biraz daha çöple dolar ve o çöp
`GET .../menu` yanıtında panelde görünmeye devam eder.

**Mevcut repo kanıtı.**
- `routes/api/menu-catalog.php` — dokuz uç noktanın hiçbiri `DELETE` değil;
  `PUT` yalnız `allergens`, `price`, `visibility`.
- `database/migrations/2026_08_20_000002_*`: `menu_categories.position` ve
  `menu_items.position` sütunları **var** ve `unique(menu_id, position)` /
  `unique(category_id, position)` kısıtıyla korunuyor — yani sıralama veri
  modelinde tasarlanmış, yüzeyi yazılmamış. (Bu kısıt, saf bir "iki satırı
  değiştir" güncellemesinin çakışacağı anlamına da gelir; sıralama uç noktası
  toplu ve ertelenmiş kısıt kontrolü isteyecektir.)
- `MenuCatalogWorkspace.tsx` — altı eylem işleyicisi, hiçbiri silme/yeniden
  adlandırma/sıralama değil.

**Gerekli ürün sonucu.** Sahip, panelden: kategoriyi ve ürünü **silebilir**
(yayınlanmış bir sürümü bozmadan), kategori ve ürün **adını düzeltebilir**,
kategori ve ürünleri **sürükleyerek veya yukarı/aşağı düğmesiyle
sıralayabilir**. Silme, o ürünü kullanan yayınlanmış snapshot'ı geriye dönük
değiştirmez.

**Gözlenebilir kabul ölçütü.**
1. `DELETE /api/workspaces/{w}/menu-categories/{c}` ve
   `DELETE /api/workspaces/{w}/menu-items/{i}` 200 döner; silinen öğe
   `GET .../menu` yanıtında yoktur; **mevcut yayın snapshot'ı bayt bayt
   değişmemiştir.**
2. `PUT .../menu-categories/{c}` ve `PUT .../menu-items/{i}` ad değişikliğini
   kalıcılaştırır; boş/yalnız boşluk ad 422 verir.
3. Sıralama uç noktası bir kategorinin ürünlerini verilen sıraya taşır ve
   `unique(category_id, position)` kısıtını ihlal etmez; 12 ürünlü bir
   kategoride tam ters sıralama tek istekte başarılı olur.
4. Misafir menüsünde ürünler panelde görünen sırayla çıkar (yayın sonrası).
5. Bir kabul testi, silinen ürünün eski yayın snapshot'ında **hâlâ**
   göründüğünü dondurur.

---

### P0-02 — Yeni ürün varsayılan olarak gizli: sessiz aktivasyon duvarı

**Müşteri sorunu.** Sahip 40 ürün girdi, "Yayınla"ya bastı ve hata aldı:
menüde gösterilecek hiçbir şey yok. Çünkü girdiği 40 ürünün 40'ı da gizli
oluşturuldu ve her birinin görünürlüğünü **tek tek** açması gerekiyor. Bu,
ilk kullanımda ürünün kendi kendine kurduğu bir duvardır ve sahip bunu
"program bozuk" diye okur.

**Mevcut repo kanıtı.**
- `2026_08_20_000002_create_menu_catalog_tables.php`:
  `$table->boolean('is_visible')->default(false);`
- `BuildPublicationSnapshot::fromDraftTree()` görünmez ürünü atlar
  (`if (! $item['isVisible']) { continue; }`) ve hiç görünür ürün yoksa
  `UnreadyDraftException::noVisibleItem()` fırlatır.
- Arayüzde toplu görünürlük eylemi yok; `handleToggleVisibility(item)` tek
  ürün üzerinde çalışır.

**Gerekli ürün sonucu.** İki seçenekten biri, ölçüyle seçilmiş olmalı:
(a) yeni ürün **varsayılan görünür** oluşturulur ve gizleme bilinçli bir
eylemdir; veya (b) varsayılan gizli kalır ama **kategori/menü düzeyinde
toplu görünür yap** eylemi vardır ve yayın ekranı "37 ürün gizli — hepsini
göster" diyen tek tıklık bir çıkış yolu sunar.

Not: varsayılanı değiştirmek geriye dönük bir davranış değişikliğidir; mevcut
kayıtlara dokunulmamalı, yalnız yeni kayıtlar etkilenmelidir.

**Gözlenebilir kabul ölçütü.**
1. Sıfırdan bir hesapta, 10 ürün ekleyen bir kullanıcı **hiçbir görünürlük
   düğmesine dokunmadan** yayın yapabilir; veya yapamıyorsa yayın ekranı
   tek eylemle bunu düzeltir.
2. Yayın ön kontrolü, kaç ürünün gizli olduğunu **sayıyla** söyler ve o
   sayıyı sıfırlayan bir eylem sunar.
3. Toplu eylem tenant sınırını aşmaz; başka çalışma alanının ürünü
   etkilenmez (izolasyon testi).

---

### P0-03 — Misafir menüsünde restoran kimliği yok

**Müşteri sorunu.** Misafir masadaki QR'ı tarar ve gördüğü ilk kelime
**"Menü"**dir. Restoranın adı yok, logosu yok, adresi ve telefonu yok. Sahip
için bu bir marka kaybıdır; misafir için "doğru yere mi geldim?" sorusudur;
paylaşılan bir bağlantıda ise sayfa kimsenin tanımadığı bir sayfadır.

**Mevcut repo kanıtı.**
- `resources/views/public-menu.blade.php:255` —
  `<h1 class="qr-menu-title">Menü</h1>` (sabit metin).
- `BuildPublicationSnapshot` snapshot'a **yalnız** şunları koyar:
  `categories[].name`, `menuItems[].productName`, `priceMinorAmount`,
  `currencyCode`, `allergens`. Marka adı, logo, adres, telefon, çalışma
  saatleri snapshot'ta yoktur.
- Marka adı yalnız yapılandırılmış veriye (`MenuStructuredData::forMenu(...,
  $address['brand_name'])`) girer — yani arama motoru görür, **misafir
  görmez**.
- `config/media-slots.php` `logo` slotunu (min 512×512, şeffaflık korunur,
  64/128/256/512 rendition) tanımlar; hiçbir yerde menüye bağlanmaz.

**Gerekli ürün sonucu.** Yayın snapshot'ı restoran kimliğini de dondurur:
ad, logo (rendition'lı), adres, telefon; misafir sayfası bunları başlıkta
gösterir. Kimlik **snapshot'a** yazılır, canlı sorguyla çekilmez — aksi
hâlde şubenin adı değişince eski bir yayın sessizce değişir.

**Gözlenebilir kabul ölçütü.**
1. Yayınlanmış menü sayfasının `<h1>`'i restoranın gerçek adıdır; `"Menü"`
   sabit metni artık başlık değildir.
2. Logo yüklenmişse başlıkta görünür ve `alt` metni doludur; yüklenmemişse
   düzen bozulmaz.
3. Adres ve telefon, yayın anındaki değerlerdir; sonradan şube kaydı
   değiştirilse bile **mevcut yayın değişmez**, yeni yayın yeni değeri alır.
4. Telefon numarası tıklanabilir (`tel:`) ve 320 px genişlikte taşmaz.

---

### P0-04 — Ürün açıklaması ve görseli yayın snapshot'ına bağlı değil

**Müşteri sorunu.** Sahip menüsünü dijitale taşımasının en somut sebebini
kullanamıyor: fotoğraf. "Adana Kebap · 380,00 TL" bir satırdır; fotoğraflı ve
açıklamalı bir kart satış aracıdır. Bugün ürün için ne açıklama alanı, ne
görsel bağı var.

**Mevcut repo kanıtı.**
- `products` tablosu: `id`, `workspace_id`, `name`, zaman damgaları.
  **Açıklama sütunu yok.**
- Snapshot şeması (`BuildPublicationSnapshot` dönüş tipi):
  `productName`, `priceMinorAmount`, `currencyCode`, `allergens`. **Görsel
  veya açıklama alanı yok.**
- Medya veri modeli tam kuruludur: `media_blobs`, `media_versions`,
  `media_renditions`, `media_usages` (`entity_type`/`entity_id`/`slot`/
  `publication_id`/`alt_text_override`), `media_processing_jobs`
  (`2026_08_27_000400_*`). `media_usages.publication_id` alanı bu bağın
  **tasarlandığını** ama kurulmadığını gösteriyor.
- `routes/api/media.php` yalnız yükleme, listeleme, silme ve slot politikası
  sunar — bir varlığı bir ürüne **iliştiren** uç nokta yoktur.
- `docs/61` F6/F7/F10: rendition seti ⬜, değişmez URL ⬜, yayın snapshot'ı
  asset version'a bağlı ⬜.

**Gerekli ürün sonucu.** Ürün açıklaması (kısa, düz metin) ve ürün görseli
üründe düzenlenebilir; yayın anında snapshot açıklamayı ve **belirli bir
görsel sürümünün** URL'sini dondurur. Yayınlanmış menü, sonradan düzenlenen
bir fotoğrafı habersiz göstermez.

**Gözlenebilir kabul ölçütü.**
1. `products.description` (veya menü öğesi düzeyinde eşdeğeri) yazılabilir ve
   misafir menüsünde ürün adının altında görünür; boşsa satır düzeni bozulmaz.
2. Bir ürüne görsel iliştirilir; `media_usages` satırı `entity_type`,
   `entity_id`, `slot`, `media_version_id` ile oluşur.
3. Yayın snapshot'ı görselin **sürüm** kimliğini taşır. Yayından sonra aynı
   varlığın yeni bir sürümü üretildiğinde, mevcut yayın hâlâ eski sürümü
   gösterir; yeni yayın yenisini gösterir. Bir test bunu dondurur.
4. Misafir sayfası görselleri `srcset` ile boyuta göre indirir ve
   `PublicMenuPayloadBudget` bütçesi (`docs/06`) görselli menüde yeniden
   ölçülür ve geçer.

---

### P0-05 — Menüyü hızlı aktarma yolu yok (foto / PDF / CSV) ve insan onaylı yayım

**Müşteri sorunu.** Restoranın menüsü zaten var — basılı, PDF veya Excel.
Bugün Zabuno o menüyü **tek tek elle** yeniden yazdırıyor. 60 kalemlik bir
menü, her kalem için ayrı bir form gönderimi demektir. Bu, pilotta ekibin
saatini, self-service'te ise müşteriyi yakar.

**Mevcut repo kanıtı.**
- `routes/api/*` içinde hiçbir içe aktarma uç noktası yok; `menu-catalog.php`
  yalnız tekil oluşturma sunar.
- AI yetenek düzlemi kuruludur ama **erişilemez**: `app/Application/Ai/Port`
  altında `OcrPort`, `VisionExtractionPort`, `StructuredGenerationPort`
  arayüzleri, `ai_invocations`/`ai_artifacts` tabloları (artifact **taslakta**
  durur, `applied_at` yalnız insan onayından sonra dolar) ve
  `ArtifactSchemaValidator` var. Buna karşılık tek uygulama
  `Infrastructure/Ai/FakeProvider.php`, `config/ai.php` içinde
  `'enabled' => env('AI_ENABLED', false)` ve dört sağlayıcının da
  `'connections' => []`, ve **hiçbir HTTP rotası yok**.
- `modules/opt-07-csv-import-export.md` ve `modules/opt-21-ai-menu-import.md`
  "opsiyonel" olarak sınıflandırılmış — yani MVP dışı sayılmış.

**Gerekli ürün sonucu.** Sahip menüsünün fotoğrafını, PDF'ini veya CSV'sini
yükler; sistem kategori/ürün/fiyat taslağı üretir; sahip **onaylamadan
hiçbir şey yayına girmez**; her alan tek tek düzeltilebilir; belirsiz
alanlar işaretlidir.

CSV yolu tek başına da yeterlidir ve sağlayıcı gerektirmez. Pilotta
"concierge import" (ekip CSV'yi hazırlar) meşru bir başlangıçtır — ama
o zaman CSV **uç noktası** gerçekten var olmalıdır, yoksa ekip de elle
yazar.

**Gözlenebilir kabul ölçütü.**
1. Bir CSV (kategori, ürün, fiyat, para birimi, alerjen) yüklendiğinde 60
   kalemlik menü **tek işlemde** taslağa dönüşür; hatalı satırlar satır
   numarasıyla raporlanır ve geçerli satırlar kaybolmaz.
2. Fotoğraf/PDF yolu açıldığında sonuç bir `ai_artifacts` satırıdır,
   `applied_at` **boştur**; onay ekranı her alanın kaynağını ve güven
   durumunu gösterir; onaydan sonra `applied_at` dolar ve aynı
   `idempotency_key` ikinci kez uygulanmaz.
3. Hiçbir aktarım yolu, onay olmadan yayına dokunmaz. Bir test, artifact
   uygulanmadan yayın snapshot'ının değişmediğini dondurur.
4. 60 kalemlik bir menünün aktarımdan yayına kadar geçen süresi ölçülür ve
   kaydedilir (bkz. §7 hedef çatışması).

---

### P0-06 — Gerçek e-posta gönderimi yok

**Müşteri sorunu.** Sahip ekibine davet gönderir, davet **hiç ulaşmaz**.
Şifresini unutur, sıfırlama bağlantısı **hiç ulaşmaz**. Kayıt olur, doğrulama
e-postası **hiç ulaşmaz** ve `/app` `verified` middleware'ine takıldığı için
ürüne giremez. Pilotta bu, ilk gün karşılaşılacak bir duvardır.

**Mevcut repo kanıtı.**
- `config/mail.php`: `'default' => env('MAIL_MAILER', 'log')`.
- `.env.example` ve **`.env.production.example`** ikisi de
  `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS="noreply@example.invalid"`.
- `app/Mail/VerifyEmailMail.php` ve `app/Mail/TeamInvitationMail.php`
  yazılmış ve testleri var — yani eksik olan şablon değil, **taşıyıcı**.
- `routes/web.php`: `/app` `['auth:web', 'verified']` ile korunuyor.

**Gerekli ürün sonucu.** Üretim ortamında gerçek bir SMTP/API taşıyıcısı
yapılandırılmış, gönderen alan adı doğrulanmış (SPF/DKIM), teslimat
başarısızlığı sessiz değil görünür.

**Gözlenebilir kabul ölçütü.**
1. Canlı ortamda yeni kayıt, doğrulama e-postasını **gerçek bir gelen
   kutusuna** (Gmail ve bir kurumsal alan adı, en az ikisi) 60 saniye içinde
   teslim eder ve spam'e düşmez.
2. Ekip daveti ve şifre sıfırlama aynı şekilde teslim edilir.
3. Gönderim başarısız olduğunda kullanıcıya "gönderildi" denmez; hata
   kaydedilir ve arayüzde yeniden gönderme yolu vardır.
4. `.env.production.example` artık `log` taşıyıcısını üretim varsayılanı
   olarak önermez.

---

### P0-07 — Canlı bir dağıtımda çalıştığı kanıtlanmadı

**Müşteri sorunu.** "Geliştirici makinesinde çalışıyor" ile "senin
restoranının servis saatinde çalışacak" arasında bir uçurum var. Pilot
restoran o uçurumun üstünde durur.

**Mevcut repo kanıtı.**
- `docs/18` Exit Gate: **owner ilanı NO-GO** (2026-08-27). Kiracı izolasyonu
  ve yedekten dönüş kanıtları "yalnız geliştirme makinesi" kapsamlı; kayıtlar
  kendi sınırlarını yazıyor ("not a pentest", "not a production proof").
- Aynı belge: p95 gecikme ve LCP **"henüz yapılmamıştır; hiçbir test bunu
  iddia etmez"**.
- Menü yükü bütçesi ölçülmüş (7,4 KB gzip) ama belge bunun **sıkıştırmaya
  bağlı** olduğunu ve dağıtımda gzip/brotli kapalıysa bütçenin tutmayacağını
  açıkça söylüyor.
- `bootstrap/app.php` ters vekile `at: '*'` ile güveniyor; bu güvenliğin
  dayanağı `app` servisinin ana makineye **hiç port yayımlamaması**. Bu bir
  dağıtım şartıdır ve gerçek kurulumda doğrulanmalıdır.

**Gerekli ürün sonucu.** Gerçek bir sunucuda (netcup VPS) çalışan, TLS'i
sonlanan, sıkıştırma açık, yedeği alınan ve geri dönüşü denenmiş bir kurulum.

**Gözlenebilir kabul ölçütü.**
1. Canlı adres HTTPS üzerinden çalışır; `sitemap.xml` `https://` şeması yayar;
   yönlendirme döngüsü yoktur.
2. Misafir menüsü yanıtı **sıkıştırılmış** iner (`content-encoding`), ve
   gerçek bir mobil bağlantıda LCP ve p95 ölçülüp kaydedilir.
3. Kiracı izolasyonu ve yedekten geri dönüş tatbikatı **canlı sunucuda**
   koşulur ve kanıtı kaydedilir (`tenant_isolation_evidence`,
   `backup_restore_evidence` satırları o koşumdan gelir).
4. Sunucu yeniden başlatıldıktan sonra kuyruk ve zamanlanmış işler kendiliğinden
   ayağa kalkar; basılı bir QR taraması kesintisiz çalışmaya devam eder.

---

### P0-08 — Medya işleme güvenilir değil

**Müşteri sorunu.** Sahip telefonuyla çektiği 6 MB'lık HEIC fotoğrafı yükler.
Bugün o dosya hiçbir zaman kullanılabilir bir görsele dönüşmez ve karantinada
kalır — ya da durumu belirsiz kalır. Sahip neden olduğunu göremez.

**Mevcut repo kanıtı.**
- `app/Providers/AppServiceProvider.php:132`:
  `$this->app->bind(MediaAssetProcessorPort::class, UnavailableMediaAssetProcessor::class);`
- `UnavailableMediaAssetProcessor::process()` **her zaman**
  `MediaProcessingOutcome::Indeterminate` döner — yani üretimde bağlı olan
  tek işleyici hiçbir şey işlemez.
- `media_assets.processing_status` varsayılanı `'quarantined'`.
- Malware tarayıcı için `ClamavMalwareScanner` ve `UnavailableMalwareScanner`
  ikisi de var; hangisinin bağlandığı ortama bağlı
  (`AppServiceProvider.php:122`).
- `docs/61` E9 (medya arayüzü) ⬜, F5 (doğrulama+karantina+AV) 🔶,
  F6 (rendition seti) ⬜.

**Gerekli ürün sonucu.** Yüklenen görsel gerçekten işlenir: format
dönüştürülür, rendition'lar üretilir, karantinadan çıkar; başarısızlık
kullanıcıya **anlaşılır** bir cümleyle döner ve tekrar denenebilir.

**Gözlenebilir kabul ölçütü.**
1. Canlı ortamda yüklenen JPEG/PNG/HEIC bir görsel, tanımlı rendition
   genişliklerinde türevlerini üretir ve `processing_status` `quarantined`
   dışına çıkar.
2. Bozuk/desteklenmeyen bir dosya, sessizce beklemek yerine görünür bir
   hata durumuna geçer ve `media_processing_jobs.failure_reason` dolar.
3. Yüklenen dosya AV taramasından geçer; tarayıcı devre dışıysa ürün bunu
   **kullanıcıya** karşı "tarandı" gibi göstermez.
4. Bir görsel silindiğinde, onu kullanan yayınlanmış menü kırılmaz
   (`media_usages` üzerinden etki analizi).

---

### P0-09 — Kullanıcı kendi verisini dışa aktaramıyor

**Müşteri sorunu.** Sahip "menümü alıp gidebilir miyim?" diye sorar. Cevap
bugün hayır. Pilot restoranın kilitlenme korkusunu kaldıran şey budur; ve
KVKK/GDPR kapsamında bir hak olarak da beklenir. Ürün `/kvkk` sayfası
yayımlıyor ama veri taşınabilirliği için hiçbir mekanizması yok.

**Mevcut repo kanıtı.**
- Depo genelinde `export`/`veri dışa aktarım` araması yalnız QR görsel
  export'larını (`ExportQrCodePngController` vb.) ve `ShowSitemapController`
  ile `FoundationStatusController` içindeki alakasız eşleşmeleri buluyor.
  Menü/kullanıcı/analitik verisi için **hiçbir** dışa aktarma yüzeyi yok.
- `routes/web.php` `/kvkk` sayfasını yayımlıyor.
- `modules/opt-07-csv-import-export.md` opsiyonel modül olarak duruyor.

**Gerekli ürün sonucu.** Sahip, çalışma alanının menüsünü (kategori, ürün,
fiyat, alerjen, açıklama) makine-okunur bir dosya olarak indirebilir; hesap
verisi talebi için tanımlı bir yol vardır.

**Gözlenebilir kabul ölçütü.**
1. Panelden tek eylemle menü CSV/JSON olarak indirilir; indirilen dosya
   P0-05'teki içe aktarma ile **geri yüklenebilir** (tur gidiş-dönüş testi).
2. Dışa aktarım yalnız o çalışma alanının verisini içerir; ikinci bir
   kiracının tek satırı bile sızmaz (izolasyon testi).
3. Dışa aktarma isteği yetkiye tabidir ve hız sınırlıdır.

---

## 4b. PV — pilot validasyon kanıtı (pilot sırasında üretilir)

Aşağıdaki tek madde **P0 sınıfında değildir.** Pilot başlamadan önce
kapatılabilecek bir eksik değil, pilotun **ürettiği çıktıdır**: pilot
süresince toplanır, pilot bitişinde GREEN olur ya da olmaz. Bir sonraki
karar (self-service'e geçiş) bu kanıta bakar.

---

### PV-01 — Gerçek bir restoranda çalıştığının kanıtı

**Sınıf.** Pilot validasyonu. **Ne zaman ölçülür:** pilot süresince.
**Ne zaman GREEN olur:** pilot bitişinde. **Ön koşulu:** P0-01…P0-09 ve
§9.1–§9.3 giriş kapıları (ölçüm mekanizması pilot **başlarken** hazır
olmalıdır; sonuç pilot **bitince** okunur).

**Müşteri sorunu.** Bugüne kadar ürünü kullanan tek kişi onu yapan kişi.
Otomatik test bir sözleşmenin tutulduğunu kanıtlar; bir restoranın akşam
servisinde işe yaradığını kanıtlamaz.

**Mevcut repo kanıtı.**
- `RestaurantCriticalJourneyTest` — otomatik, HTTP yüzeyinden, 5 test.
- `docs/18`: QR fiziksel tarama kanıtı **owner'ın kendi telefonuyla, LAN
  üzerinden** üretilmiş; "basılı ölçü/scannability (A4, masa mesafesi)
  ayrıca ölçülmedi".
- `docs/18` aynı gün iki ölümcül kusurun **testler geçerken** bulunduğunu
  kaydediyor (konumsuz çalışma alanında sonsuz "Loading your menu…", 422
  gövdesini atan marka formu). Belgenin kendi cümlesi: *"Yeşil bir süit,
  ürünün çalıştığının kanıtı değildir."*

**Gerekli sonuç.** En az bir gerçek restoran — hedef beş — gerçek menüsüyle,
gerçek masalarında, gerçek misafirleriyle en az bir tam servis haftası
boyunca Zabuno kullanmış ve bu kullanım **ölçülmüş** olmalı.

**Pilot başlarken hazır olması gereken (bu P0 kapsamındadır).** Ölçümü
mümkün kılan mekanizma: analitik olay akışı (§9.3, G-13/G-14) ve destek
teması kaydı (G-15). Mekanizma yoksa pilot biter ve elde sayı olmaz.

**Gözlenebilir kabul ölçütü — pilot bitişinde okunur.**
1. Bir restoranın basılı QR'ı en az 7 gün masada durur; `qr_resolve` ve
   `menu_open` olayları o restoranın gerçek servis saatleriyle uyumlu bir
   dağılım gösterir (öğle/akşam pikleri).
2. Sahip, o hafta içinde **kendi başına** en az bir fiyat değişikliği yapıp
   yayınlar ve değişiklik misafir sayfasında görünür.
3. Haftanın sonunda yapılandırılmış bir görüşme kaydı vardır: neyi yapamadı,
   nerede takıldı, kime sordu.
4. U-08 (basılı QR taranabilirliği), U-12 (destek yükü) ve U-13 (retention)
   için sayı üretilmiştir — tahmin değil, sayım.

---

## 5. P1 — halka açık ücretli self-service MVP öncesi eksikler

P0'ın tamamı burada da geçerlidir ve PV-01 (pilot kanıtı) bu hedefe geçiş
kararının **girdisidir**; aşağıdakiler **ek**tir.

---

### P1-01 — Fiyat, deneme, destek ve iletişim yüzeyi yok

**Müşteri sorunu.** Siteye gelen bir restoran sahibi üç soru sorar: ne kadar,
deneyebilir miyim, tıkanırsam kime sorarım. Bugün üçünün de cevabı sayfada
"henüz yok" yazıyor. Bu, dönüşümü sıfırlar.

**Mevcut repo kanıtı.**
- `resources/views/public/home.blade.php` Pricing bölümü:
  *"There are no published plan prices yet, and checkout is not available
  yet."*; Contact bölümü: *"There is no connected contact form yet."*;
  FAQ: *"Is pricing available? — Not yet."*
- `ListPlansController` `/api/workspaces/{workspace}/plans` altındadır ve
  `auth` + çalışma alanı bağlamı ister — yani **plan listesi, kaydolmadan
  görülemez**.
- `docs/61` A12: Help merkezi ⛔ ("arkasında içerik yok").

**Gerekli ürün sonucu.** Kaydolmadan görülebilen bir fiyat sayfası; deneme
şartlarının açık yazımı; çalışan bir iletişim yolu; en az bir "ilk 15
dakika" yardım içeriği.

**Gözlenebilir kabul ölçütü.**
1. Oturum açmamış bir ziyaretçi `/pricing` (veya ana sayfa bölümü) üzerinde
   gerçek plan adlarını, fiyatlarını ve para birimini görür; bu veri plan
   kataloğundan gelir, sayfaya elle yazılmaz.
2. İletişim yolu gerçekten ulaşır (P0-06'ya bağımlıdır) ve gönderene bir
   teyit verir.
3. Yardım içeriği en az şu üç soruyu cevaplar: menümü nasıl aktarırım,
   QR'ı nasıl basarım, fiyatı nasıl güncellerim.

---

### P1-02 — Gerçek ödeme yok; "deneme" kavramı da yok

**Müşteri sorunu.** Ücretli bir MVP'de para alınamıyorsa MVP ücretli
değildir. Bugün tek ödeme yolu sandbox'tır ve tek gerçek yol, platform
yöneticisinin elle ödeme kaydı girmesidir — yani her müşteri manuel emek
demektir.

**Mevcut repo kanıtı.**
- Uç noktaların adı: `POST /workspaces/{w}/iyzico-sandbox/session`,
  `POST /api/webhooks/iyzico-sandbox`. Tek sağlayıcı uygulaması
  `Infrastructure/Billing/Provider/IyzipaySandboxGateway.php`.
- Elle ödeme yalnız platform yöneticisindedir:
  `POST /admin/workspaces/{w}/manual-payments` (`EnsurePlatformSuperAdmin`).
- `subscriptions` tablosu: `plan_id`, `state`, `ends_at`. **`trial_ends_at`
  veya deneme durumu yok.** Kiracının kendi kendine bir plana abone olduğu
  bir uç nokta yok.

**Gerekli ürün sonucu.** İki yoldan biri, açıkça seçilmiş: (a) canlı ödeme —
gerçek Iyzico üretim kimlik bilgileri, imzalı webhook, başarısız ödeme
davranışı, iptal/iade yolu; veya (b) **ödemesiz açık deneme** — kredi kartı
istenmez, süre açıkça yazılır, süre dolduğunda ne olacağı önceden söylenir.

(b) daha küçük ve pilot-sonrası ilk sürüm için yeterlidir; ama "deneme"
bugün veri modelinde bile yok, yani (b) de iş ister.

**Gözlenebilir kabul ölçütü — (b) seçilirse.**
1. Yeni çalışma alanı otomatik olarak tanımlı süreli denemeye girer; kalan
   gün panelde görünür.
2. Süre dolduğunda misafir menüsü **kapanmaz** (basılı QR'lar ölmez); yalnız
   düzenleme kısıtlanır ve ekranda nedeni ve çözümü yazar.
3. Süre uzatma/plana geçme yolu tek eylemdir ve `subscriptions.state`
   geçişleri testle dondurulur.

**Gözlenebilir kabul ölçütü — (a) seçilirse.** Ek olarak: üretim
kimlik bilgileriyle gerçek bir tahsilat yapılır, webhook imzası doğrulanır,
aynı ödeme iki kez bildirildiğinde defterde tek satır oluşur (mevcut
`ledger_entries` davranışı canlıda doğrulanır), başarısız kart açık bir
mesajla döner.

---

### P1-03 — QR hedefi değiştirilemiyor ve devre dışı bırakılan QR geri alınamıyor

**Müşteri sorunu.** Sahip 40 masa için QR bastı, sonra menüyü yeniden
düzenledi ve QR'ların yeni menüye bakmasını istiyor — yapamıyor. Ya da bir
QR'ı yanlışlıkla devre dışı bıraktı; geri açamıyor, masadaki basılı kod
kalıcı olarak ölü. Yeniden bastırmak, bu ürünün temel vaadinin ihlalidir.

**Mevcut repo kanıtı.**
- Veri modeli bunu **destekliyor**: `qr_destinations` (geçmiş) +
  `qr_code_current_destinations` (`qr_code_id` unique) —
  `2026_08_22_000005_*`. Yani "hedefi değiştir" tasarlanmış.
- `routes/api/qr-destination.php`: oluştur, toplu oluştur, listele,
  `PUT .../disable`, üç export. **Hedef değiştirme yok, yeniden etkinleştirme
  yok.**
- `DisableQrCodeController` tek yönlüdür; karşılığı olan bir
  `EnableQrCodeController` dosyası yok.

**Gerekli ürün sonucu.** Bir QR'ın hedefi başka bir menüye taşınabilir ve
devre dışı bırakılan QR geri etkinleştirilebilir; her iki işlem de geçmişe
yazılır.

**Gözlenebilir kabul ölçütü.**
1. Hedef değiştirildiğinde `qr_destinations` yeni satır alır,
   `qr_code_current_destinations` onu gösterir, **`qr_codes.token`
   değişmez** ve aynı basılı kod yeni menüyü açar.
2. Devre dışı bırakılan bir QR yeniden etkinleştirilebilir ve `/q/{token}`
   yeniden çalışır.
3. Devre dışıyken `/q/{token}` mevcut çıkmaz-sokak davranışını korur ve rota
   şeklini ifşa etmez.

---

### P1-04 — "Tükendi" durumu yok

**Müşteri sorunu.** Akşam servisinde balık bitti. Sahibin tek seçeneği ürünü
**gizlemek**; o zaman ürün menüden tamamen kaybolur. Misafir "bugün balık var
mı?" diye sorar, garson "vardı, bitti" der — dijital menünün çözmesi gereken
sürtünme aynen kalır. Ertesi gün sahip 6 ürünü tek tek geri açmak zorundadır.

**Mevcut repo kanıtı.**
- `menu_items` tablosunda tek durum alanı `is_visible` (boolean).
  `is_available` / `sold_out` yok.
- `BuildPublicationSnapshot` görünmez ürünü snapshot'tan **tamamen** çıkarır;
  "var ama tükendi" diye bir ara durum ifade edilemez.
- Rakip ürünler bu davranışı ayrı ve adlandırılmış bir işlev olarak sunuyor
  (bkz. §10): ürünü tükendi olarak işaretleme, herhangi bir cihazdan ve
  anında.

**Gerekli ürün sonucu.** Ürün için görünürlükten ayrı bir **stok durumu**:
menüde kalır, "bugün tükendi" olarak işaretlidir, fiyatı görünür, sipariş
edilemeyeceği bellidir. Tercihen gün sonunda otomatik sıfırlanabilir.

**Gözlenebilir kabul ölçütü.**
1. Tükendi işaretlenen ürün misafir menüsünde **görünmeye devam eder** ve
   görsel/metinsel olarak tükendi olduğu bellidir (yalnız renkle değil).
2. Görünürlük ve stok durumu birbirinden bağımsızdır; gizli bir ürün
   tükendi işaretinden etkilenmez.
3. Tek ekrandan çoklu ürün tükendi/geri-getir yapılabilir ve yayın
   gerektirmeden misafire yansıyıp yansımadığı **açıkça** kararlaştırılmış ve
   testle dondurulmuştur.

---

### P1-05 — Yayın geri alınamıyor

**Müşteri sorunu.** Sahip yanlış fiyat listesini yayınladı; bütün menü %30
yanlış. Bugün geri dönüş yolu yok — ancak taslağı düzeltip **yeniden**
yayınlayabilir. Panik anında bu, en yavaş yoldur.

**Mevcut repo kanıtı.**
- `routes/api/publication.php` yalnız iki satır: `POST .../publications`,
  `GET .../publications/current`. Geri alma, listeleme veya sürüm seçme yok.
- `modules/opt-12-menu-version-rollback.md` opsiyonel modül olarak duruyor —
  yani bilinçli olarak MVP dışı sayılmış.
- Veri modeli destekliyor: `menu_publications` her yayını ayrı satır olarak
  saklıyor (`docs/69`'daki "Published #55" kaydı bunu gösteriyor).

**Gerekli ürün sonucu.** Sahip önceki yayına tek eylemle dönebilir; hangi
sürümün canlı olduğu her zaman görünürdür.

**Gözlenebilir kabul ölçütü.**
1. Yayın listesi sürüm numarası ve zaman damgasıyla görünür; canlı olan
   işaretlidir.
2. "Bu sürüme dön" eylemi, eski snapshot'ı **yeni bir yayın olarak** yazar
   (geçmiş silinmez) ve misafir sayfası anında o snapshot'ı gösterir.
3. Geri alma, QR token'ına ve kalıcı adrese dokunmaz.

---

### P1-06 — Misafir dil seçimi (ihtiyaca göre)

**Müşteri sorunu.** Turistik bir restoranda misafirin yarısı Türkçe
okumuyor. Bugün misafir sayfasının arayüz metinleri **Blade şablonuna sabit
Türkçe** yazılmış ve misafir için dil değiştirme yolu yok.

**Mevcut repo kanıtı.**
- `public-menu.blade.php` içinde sabit Türkçe metinler: `<h1>Menü</h1>`,
  `"Menüde ara"`, `"Ürün adı yazın"`, `"{{ $categoryCount }} kategori,
  {{ $itemCount }} ürün"`, `"Bu kategoride henüz ürün yok."`,
  `"Eşleşen ürün bulunamadı."`
- `lang` özniteliği yayının `contentLocale`'inden gelir — yani sayfa **tek**
  dilde sabittir, seçilebilir değildir.
- Mühürlü i18n kataloğu (515 anahtar) `resources/js/i18n/workspace` altında;
  **panel** için, misafir sayfası için değil.
- `docs/61` C3: genel `locale` alanının ui/content/supported diye
  parçalanması ⬜.
- İçerik çevirisi ayrı ve daha büyük bir iştir:
  `modules/opt-04-multi-language-content.md` opsiyonel.

**Gerekli ürün sonucu — ölçülü.** İki katman ayrılmalı: (a) misafir
sayfasının **arayüz metinleri** katalogdan gelir ve en az bir ikinci dili
(İngilizce) destekler; (b) **menü içeriğinin** çevirisi ayrı bir karardır ve
gerçek talep ölçülmeden yapılmamalıdır (bkz. §8 U-06).

**Gözlenebilir kabul ölçütü.**
1. Misafir sayfasının hiçbir kullanıcı metni şablona sabit yazılmamıştır;
   bir test bunu dondurur.
2. Misafir, aynı QR'dan girip dili değiştirebilir ve seçim aynı cihazda
   hatırlanır; `lang`/`dir` seçime göre doğru üretilir.
3. İçerik çevirisi yoksa ürün bunu **yanlış tanıtmaz**: arayüz İngilizce
   olsa da ürün adları orijinal dilinde kalır ve bu durum yanıltıcı
   sunulmaz.

---

### P1-07 — Hesap bakımı eksik: profil, şifre değiştirme, ekip rol düzenleme

**Müşteri sorunu.** Self-service bir üründe kullanıcı kendi hesabını kendi
onarır. Bugün: adını değiştiremez, **oturumu açıkken şifresini
değiştiremez**, davet ettiği kişinin rolünü sonradan düzeltemez. Yanlış rol
verdiyse tek çare üyeyi silip yeniden davet etmektir.

**Mevcut repo kanıtı.**
- `config/fortify.php` `features`: `registration()`, `emailVerification()`,
  `resetPasswords()`. **`updatePasswords()` ve
  `updateProfileInformation()` yok.**
- `routes/api/team.php`: üye listeleme, üye **silme**, sahiplik devri, davet
  oluştur/iptal/kabul. Rol güncelleme uç noktası yok.
- `docs/70` §3 bunu kendi kaydında doğruluyor: *"Üye listesinde rol
  değiştirme: davet rolü seçiliyor, sonradan değiştirme yok."*

**Gerekli ürün sonucu.** Kullanıcı adını ve şifresini panelden
güncelleyebilir; sahip bir üyenin rolünü değiştirebilir.

**Gözlenebilir kabul ölçütü.**
1. Oturum açık kullanıcı, mevcut şifresini doğrulayarak yeni şifre belirler;
   diğer oturumlar için davranış (sonlandır / sonlandırma) açıkça
   kararlaştırılmış ve testli.
2. Rol değişikliği anında etkilidir; rolü düşürülen kullanıcının yaptığı bir
   sonraki yetkili istek 403 alır (`RoleBoundariesTest` tarzında, iznin
   **yokluğunu** ölçen bir test).
3. Sahip kendi rolünü düşüremez ve son sahibi silemez.

---

### P1-08 — Anlamlı ürün seviyesi analitik yok

**Müşteri sorunu.** Sahip "menümde ne işe yarıyor?" diye sorar. Bugün alınan
cevap "menün 214 kez açıldı"dır. Hangi ürüne bakıldığı, hangi kategoriye hiç
girilmediği, hangi ürünün arandığı ama bulunamadığı bilinmiyor. Bu, menü
mühendisliği için gereken tek bilgidir — ve rakip ürünlerin açıkça tanıttığı
bir yetenektir: en çok görüntülenen ürünlerin raporlanması ve menünün buna
göre düzenlenmesi (bkz. §10).

**Mevcut repo kanıtı.**
- `app/Domain/Analytics/AnalyticsEventType.php` **iki** değer taşır:
  `qr_resolve`, `menu_open`. Başka olay tipi yok.
- `docs/61` H2 (ürün analitiği olay taksonomisi) ⬜, H3 (form olayları) ⬜.
- `docs/69`: cihaz/OS/tarayıcı ve ülke/şehir/referrer/saatlik ⛔ post-MVP.
- Misafir sayfasında arama vardır (`#menu-search`) ve **hiçbir arama
  ölçülmez** — yani "misafirlerin aradığı ama menüde olmayan ürün" verisi,
  toplanabilir olduğu hâlde toplanmıyor.

**Gerekli ürün sonucu.** Ürün ve kategori düzeyinde görüntülenme, ve
"sonuçsuz arama" ölçümü; panelde en çok/en az bakılan ürünler listesi.

**Gözlenebilir kabul ölçütü.**
1. Olay taksonomisi **önce iş sorusundan** türetilir ve yazılır (hangi soruyu
   hangi olay cevaplıyor); `docs/61` H2'nin kendi gerekçesi budur.
2. Ürün görüntülenmesi ve sonuçsuz arama olayları kaydedilir ve tenant
   bazında ayrışır.
3. Panelde "en çok bakılan 10 ürün" ve "hiç bakılmayan ürünler" listesi
   görünür; veri yetersizse ekran boş bir tablo değil, nedenini ve eşiğini
   yazan bir durum gösterir (`docs/66` disiplini).
4. Ölçüm rıza politikasına uygundur ve misafir kimliği kurmaz.

---

## 6. P0 / PV / P1 özet tablosu

### 6.1 P0 — pilot başlatma eksikleri (9 madde, pilot ÖNCESİ kapatılır)

| ID | Başlık | Hedef | Bağımlılık |
| --- | --- | --- | --- |
| P0-01 | Menü CRUD: silme, ad düzeltme, sıralama | A | — |
| P0-02 | Varsayılan gizli ürün → aktivasyon sürtünmesi | A | — |
| P0-03 | Misafir menüsünde restoran kimliği | A | — |
| P0-04 | Açıklama + görsel, yayın snapshot'ına bağlı | A | P0-08 |
| P0-05 | Foto/PDF/CSV aktarma + insan onaylı yayım | A | — |
| P0-06 | Gerçek e-posta gönderimi | A | P0-07 |
| P0-07 | Canlı dağıtım kanıtı | A | — |
| P0-08 | Medya işleme güvenilirliği | A | P0-07 |
| P0-09 | Kullanıcı veri dışa aktarımı | A | P0-05 (biçim paylaşımı) |

### 6.2 PV — pilot validasyonu (1 madde, pilot SIRASINDA üretilir)

| ID | Başlık | Hedef | Ne zaman GREEN | Ön koşul |
| --- | --- | --- | --- | --- |
| PV-01 | Gerçek restoran pilot kanıtı | A (çıktı) | pilot bitişinde | P0-01…P0-09 + G-13/G-14/G-15 |

### 6.3 P1 — halka açık ücretli self-service (8 madde)

| ID | Başlık | Hedef | Bağımlılık |
| --- | --- | --- | --- |
| P1-01 | Fiyat / deneme / destek / iletişim yüzeyi | B | P0-06 |
| P1-02 | Gerçek ödeme **veya** açık deneme kararı | B | P1-01 |
| P1-03 | QR hedefi değiştirme + yeniden etkinleştirme | B | — |
| P1-04 | Tükendi durumu | B | — |
| P1-05 | Yayını geri alma | B | — |
| P1-06 | Misafir dil seçimi (ihtiyaca göre) | B | U-06 ölçümü |
| P1-07 | Profil / şifre / ekip rol bakımı | B | — |
| P1-08 | Ürün seviyesi analitik | B | — |

---

## 7. Hedef çatışması: "5 dakika" mı, 15 dakika mı?

Bu bir eksik değil, **karar gerektiren bir çelişkidir** ve bu belge onu
sessizce değiştirmez.

**Yazılı hedef.** `docs/18-STAGE-01-MVP.md:166`:
> **Time to First QR** (birincil metrik, hedef < 5 dakika — kaynak dokümandan
> korunmuş hedef, `docs/00` §4).

**Bugünkü gerçeklik.**
1. Bu metrik **hiç ölçülmüyor**. Ölçülebilmesi için gereken olay taksonomisi
   `docs/61` H2/H3'te ⬜ olarak duruyor. Yani hedef ne tutuluyor ne
   tutulmuyor — bilinmiyor.
2. Beş dakikaya sığması gereken yol bugün şudur: kayıt → e-posta doğrulama →
   çalışma alanı → marka → şube (saat dilimi, ülke) → menü → kategori → ürün
   (her biri ayrı form gönderimi) → **her ürünün görünürlüğünü ayrı ayrı
   açma** (P0-02) → yayın → QR üretme → indirme.
3. 40 kalemlik gerçek bir menü için bu, iyimser bir tahminle onlarca form
   gönderimidir. İçe aktarma yolu yoktur (P0-05).

**Çatışma.** Bir tarafta `docs/18`'in koruduğu **< 5 dakika**; diğer tarafta,
gerçek bir menüyle self-service kurulumun makul göründüğü **~15 dakika**
bandı. İkisi aynı şeyi ölçüyorsa biri yanlıştır; farklı şeyi ölçüyorsa ikisi
de tanımsızdır.

**Karar seçenekleri — sahibinin/MASTER'ın kararı:**

| Seçenek | Anlamı | Bedeli |
| --- | --- | --- |
| (A) 5 dakikayı koru, tanımı daralt | "İlk QR" = **boş/örnek** menüyle ilk QR üretimi. Ölçülebilir ve muhtemelen tutulabilir. | Metrik gerçek aktivasyonu ölçmez; bir restoran boş menüyle QR basmaz. |
| (B) 5 dakikayı koru, ürünü ona uydur | P0-05 (içe aktarma) ve P0-02 (varsayılan görünürlük) zorunlu ön koşul olur. | En büyük iş; ama müşteri faydası da en yüksek. |
| (C) Hedefi ikiye ayır | `TTF-QR-boş < 5 dk` **ve** `TTF-menü-yayında < 15 dk`. İkisi ayrı ölçülür. | İki metrik, iki eşik; ama her ikisi de dürüst. |
| (D) 15 dakikaya çek | Tek hedef, gerçekçi. | `docs/00`'dan korunmuş bir hedefi değiştirmek — provenance kararı. |

**Bu belgenin tavsiyesi (karar değil):** (C). Sebep: iki metrik iki farklı
şeyi ölçüyor ve ikisi de gerçekten önemli. Boş menüyle ilk QR, ürünün
teknik akışkanlığını; menü yayında ilk QR, **aktivasyonu** ölçer. Ancak
hangisi seçilirse seçilsin, ilk iş metriği **ölçülebilir hâle getirmektir** —
bugün hiçbiri ölçülmüyor.

---

## 8. Bilinmeyenler → ölçülebilir keşif soruları

Aşağıdaki maddelerin her biri bugün bir **hipotez**tir ve hiçbiri ölçülmemiştir.
Her satır, pilotta cevaplanacak somut bir soruya ve o cevabın nasıl
toplanacağına dönüştürülmüştür.

| ID | Bilinmeyen | Ölçülebilir soru | Nasıl ölçülür | Kararı ne değiştirir |
| --- | --- | --- | --- | --- |
| U-01 | **ICP** — hangi restoran | Pilot 10 restoranın kaç masası, kaç menü kalemi, kaç çalışanı var; kaçı zincir? | Pilot kayıt formu + ilk görüşme | Çok-şube (P1) ve rol modeli önceliği |
| U-02 | **Menü boyutu** | Gerçek menülerin kalem sayısı dağılımı (medyan, p90) | Pilot menülerinin sayımı | İçe aktarmanın (P0-05) zorunlu mu opsiyonel mi olduğu |
| U-03 | **Kaynak biçimi** | Menü bugün hangi biçimde var: basılı, PDF, Word/Excel, POS, hiçbiri? | Pilot kaydında dosya toplama | Hangi aktarma yolunun önce yazılacağı (CSV vs PDF vs foto) |
| U-04 | **Time-to-First-QR** | Kayıttan ilk yayınlanmış QR'a kaç dakika geçiyor (medyan, p90)? | Olay taksonomisi (H2) + sunucu zaman damgaları | §7'deki hedef kararı |
| U-05 | **Telefondan günlük kullanım** | Günlük düzenlemelerin yüzde kaçı mobil cihazdan yapılıyor? Mobil oturum tamamlama oranı nedir? | Panel oturumlarında cihaz sınıfı (`NegotiateDeviceClass` zaten var) | Mobil paketin kapsamı; `docs/61` A8 (mobil inspector) kararı |
| U-06 | **Dil ihtiyacı** | Misafir menüsü açılışlarının kaçı Türkçe olmayan `Accept-Language` taşıyor? | Misafir isteğinde dil başlığı sayımı (kimlik kurmadan) | P1-06'nın kapsamı: yalnız arayüz mü, içerik çevirisi mi |
| U-07 | **Fiyat ve ödeme isteği** | Sahipler aylık ne öder; hangi eşikte "hayır" der? | Pilot sonu yapılandırılmış görüşme + gerçek bir fiyat teklifi | P1-02'de (a) canlı ödeme mi (b) deneme mi |
| U-08 | **Basılı QR taranabilirliği** | Basılı QR, masa mesafesinden (60–80 cm), restoran ışığında, ilk denemede taranıyor mu? Kaç deneme gerekiyor? | Her pilot restoranda 10 tarama denemesi, sayımla | QR boyut/kontrast/hata düzeltme seviyesi ve `docs/08` baskı şablonu |
| U-09 | **Canlı performans** | Gerçek mobil şebekede menü LCP ve p95 gecikme kaç ms? | Canlı sunucuda ölçüm (bugün **hiç yapılmadı**, `docs/18`) | Görsel eklemenin (P0-04) bütçeye etkisi |
| U-10 | **Kiracı izolasyonu ve geri dönüş** | İzolasyon ve restore tatbikatı **canlı sunucuda** geçiyor mu? RPO 24 sa / RTO 4 sa gerçekte tutuyor mu? | Canlı koşum, kanıt tablolarına yazım | Ücretli müşteri kabul edilip edilemeyeceği |
| U-11 | **Alerjen sorumluluğu** | Alerjen bilgisi yanlışsa hukuki sorumluluk kimde? Sahip bunu biliyor mu? | Hukuki görüş + pilot sözleşme metni + arayüzde sorumluluk beyanı testi | Alerjen alanının zorunlu/opsiyonel olması ve yayın öncesi uyarı |
| U-12 | **Destek yükü** | Restoran başına ilk 30 günde kaç destek teması oluyor; en sık üç konu ne? | Pilotta her temasın kaydı (tarih, konu, süre) | Self-service'e geçişin mümkün olup olmadığı; yardım içeriğinin konusu |
| U-13 | **Retention** | Pilot restoranların kaçı 30. günde hâlâ menüsünü **güncelliyor**? | `menu_publications` yayın tarihleri | Ürünün günlük araç mı yoksa tek seferlik kurulum mu olduğu |

**U-11 hakkında bir not.** Bu, listenin ticari değil **hukuki** riskidir ve
ürün onu bugün hiç ele almıyor: alerjen çipleri misafire gösteriliyor,
doğruluğundan kimin sorumlu olduğu hiçbir yerde yazmıyor. Pilot öncesinde
en azından bir sorumluluk beyanı gerekir.

---

## 9. 5–10 restoranlık pilot: giriş kapıları ve çıkış kapısı

Kapılar iki gruba ayrılır ve bu ayrım §4'teki P0/PV sınıflandırmasıyla
birebir aynıdır:

- **§9.1–§9.4 — giriş kapıları (G-01…G-20).** Pilot **başlamadan önce**
  yirmisinin de yeşil olması gerekir. Bunlar ürünün, altyapının ve **ölçüm
  mekanizmasının** hazır olduğunu söyler.
- **§9.5 — çıkış kapısı (PV-01).** Pilot **bitişinde** değerlendirilir.
  Başlangıçta yeşil olması beklenmez; olamaz da.

Her satır gözlenebilir; "yazıldı" değil, "çalıştığı görüldü" anlamındadır.

### 9.1 Ürün kapıları

| # | Kapı | Kanıt biçimi |
| --- | --- | --- |
| G-01 | Menü CRUD tam (sil, ad düzelt, sırala) — **P0-01** | Uç noktalar + panel eylemleri + eski yayının değişmediğini gösteren test |
| G-02 | Yeni ürün aktivasyon duvarı kaldırıldı — **P0-02** | Sıfır hesapta görünürlük düğmesine dokunmadan yayın |
| G-03 | Misafir menüsünde restoran adı/logo/iletişim — **P0-03** | Gerçek bir yayının ekran görüntüsü + snapshot içeriği |
| G-04 | Ürün açıklaması ve görseli yayında — **P0-04** | Sürüme bağlı görsel; yayın sonrası düzenlemenin eski yayını değiştirmediği test |
| G-05 | En az bir hızlı aktarma yolu (CSV yeterli) — **P0-05** | 60 kalemlik menünün tek işlemde taslağa dönüşü |
| G-06 | Menü dışa aktarma çalışıyor — **P0-09** | Dışa aktar → içe aktar gidiş-dönüşü aynı menüyü üretir |

### 9.2 Altyapı kapıları

| # | Kapı | Kanıt biçimi |
| --- | --- | --- |
| G-07 | Canlı ortam ayakta, HTTPS, sıkıştırma açık — **P0-07** | Canlı adres + `content-encoding` + `sitemap.xml` şeması |
| G-08 | Gerçek e-posta teslim ediliyor — **P0-06** | İki farklı sağlayıcıda gelen kutusuna teslim, spam'e düşmeden |
| G-09 | Medya işleme çalışıyor — **P0-08** | Telefon fotoğrafı → rendition'lar; bozuk dosya görünür hata verir |
| G-10 | Kiracı izolasyonu **canlı sunucuda** geçti — U-10 | Kanıt tablosunda canlı koşum satırı |
| G-11 | Yedekten geri dönüş **canlı sunucuda** tatbik edildi — U-10 | Kanıt tablosunda canlı koşum satırı, RPO/RTO ölçümü |
| G-12 | Sunucu yeniden başlatma sonrası kendini toparlıyor | Kasıtlı restart + kesintisiz `/q/{token}` taraması |

### 9.3 Ölçüm kapıları

| # | Kapı | Kanıt biçimi |
| --- | --- | --- |
| G-13 | Time-to-First-QR ölçülebiliyor — U-04, §7 | Olay akışından hesaplanan gerçek bir süre |
| G-14 | Pilot başına analitik ayrışıyor | Her restoranın kendi `qr_resolve`/`menu_open` serisi |
| G-15 | Destek teması kaydı tutuluyor — U-12 | Tarih/konu/süre alanlı basit bir kayıt |

### 9.4 Operasyon ve hukuk kapıları

| # | Kapı | Kanıt biçimi |
| --- | --- | --- |
| G-16 | Pilot sözleşmesi/mutabakatı imzalı | Veri sahipliği, sonlandırma, veri iadesi maddeleri |
| G-17 | Alerjen sorumluluk beyanı arayüzde — U-11 | Yayın öncesi görünen, kapatılabilir olmayan beyan |
| G-18 | Basılı QR şablonu **tezgâh koşullarında** test edildi — U-08 ön ölçümü | 10 taramada başarı sayısı, mesafe ve ışık koşuluyla (gerçek restoran ölçümü PV-01'dedir) |
| G-19 | Geri çekilme planı yazılı | Pilot durursa restoranın menüsünü nasıl geri alacağı (G-06'ya bağlı) |
| G-20 | Tek destek kanalı ve yanıt süresi taahhüdü belli | Kanal + hedef yanıt süresi, restoranla paylaşılmış |

### 9.5 Çıkış kapısı — pilot bitişinde (PV-01)

Bu kapı pilot **başlarken kapalıdır ve öyle olmalıdır**. Onu açacak veri,
pilot süresince toplanır.

| # | Kapı | Kanıt biçimi |
| --- | --- | --- |
| X-01 | 7 gün × en az 5 restoran gerçek kullanım — **PV-01** | Restoran başına `qr_resolve`/`menu_open` serisi, servis saatleriyle uyumlu |
| X-02 | Her restoranda en az bir **sahip-eliyle** yapılmış yayın — **PV-01** | `menu_publications` satırı + aktörü |
| X-03 | U-04, U-08, U-12, U-13 için sayı üretildi | Ölçüm çıktısı; tahmin değil, sayım |
| X-04 | Her restoranla pilot sonu görüşmesi yapıldı | Yapılandırılmış kayıt: neyi yapamadı, nerede takıldı, kime sordu |

**Bu dört satır yeşilse** self-service (Hedef B / P1) kararı gerçek veriye
dayanarak verilebilir. Yeşil değilse, eksik olan pilot değil — pilotun
cevaplaması gereken sorudur.

---

## 10. Pazar kalibrasyonu

Aşağıdaki gözlemler **rakip özellik listeleri**dir, ürün gereksinimi değil.
Amaç tek: bu kategoride bir restoran sahibinin **beklediği** taban neyse onu
görmek.

### 10.1 Okunabilen kaynaklar

Aşağıdakiler, ilgili resmi sayfaların **desteklediği özelliklerin kendi
cümlelerimizle özetidir**; pazarlama metni alıntılanmamıştır. Doğrulama için
kaynak sayfalara bakılmalıdır.

**GustoQR** — kaynak: [gustoqr.com — restoranlar için dijital menü](https://www.gustoqr.com/digital-menu-for/restaurants)
(erişim 2026-08-28). Sayfanın tanıttığı yetenekler:

- Mevcut menünün fotoğrafından veya PDF'inden yapılandırılmış menü üretme
  (→ **P0-05**)
- Fiyat değişikliğinin ve bir ürünü tükendi olarak işaretlemenin anında
  yayına yansıması (→ **P1-04**)
- Ürün görselleri: üretilen görseller veya hazır bir görsel kütüphanesi
  (→ **P0-04**)
- Otomatik menü çevirisi, çok sayıda dil ve misafirin aynı QR üzerinden dil
  değiştirebilmesi (→ **P1-06**)
- Markalı QR kodları; PNG, SVG ve baskıya hazır çıktı (Zabuno'da PNG/SVG/PDF
  export **var**)
- Kredi kartı istemeyen ücretsiz bir başlangıç planı (→ **P1-02**)
- Tarama ve görüntülenme analitiği, cihaz/konum kırılımı, dışa aktarma ve en
  çok görüntülenen ürünlerin raporlanması (→ **P1-08**, **P0-09**)

**TableMenuQR** — kaynak: [tablemenuqr.com](https://www.tablemenuqr.com/en)
(erişim 2026-08-28). Sayfanın tanıttığı yetenekler:

- Sürükle-bırak menü düzenleyici, fiyat ve ürünlerin gerçek zamanlı
  güncellenmesi, sayıca sınırsız menü/kategori/ürün (→ **P0-01**)
- Ürünü herhangi bir cihazdan tükendi olarak işaretleme (→ **P1-04**)
- Plana dâhil birkaç dil ve misafirin tek QR üzerinden dil seçmesi
  (→ **P1-06**)
- PDF'ten içe aktarma ile kategori ve ürünlerin hazırlanması; alternatif
  olarak elle kurulum (→ **P0-05**)
- Kredi kartı istemeyen bir aylık deneme ve ardından aylık abonelik fiyatı
  (→ **P1-02**)
- Kurulumun dakikalar içinde tamamlanabildiği ve uygulama indirmeyi
  gerektirmediği yönünde bir kurulum-süresi iddiası (→ §7)

### 10.2 Okunamayan kaynaklar — dürüst kayıt

[intermenu.io](https://intermenu.io/) ve [almenu.io](https://almenu.io/) bu
oturumda **anlamlı içerik döndürmedi**: birincisi yalnız çerez onay katmanını, ikincisi yalnız şirket
adı ve kategori ifadesini verdi. Bu iki site hakkında özellik veya fiyat
iddiası **yapılmamaktadır**; kalibrasyon yalnız yukarıdaki iki kaynağa
dayanır. Bu bir eksikliktir ve ileride tarayıcıyla yeniden denenmelidir.

### 10.3 Kalibrasyondan çıkan tek cümle

İki okunabilen kaynağın **ikisi de** şu dördünü taban sayıyor: içe aktarma,
tükendi işareti, ürün görseli, misafirin dil seçimi. Bunların üçü Zabuno'da
yok, biri (dil) yarım. Buna karşılık Zabuno'nun **snapshot disiplini** (yayın
değişmez, taslak sızmaz) ve **kalıcı adres politikası** (slug değişince 301,
token ölmez) bu kaynakların hiçbirinde reklam edilmiyor — bu gerçek bir
farklılaşmadır ve kaybedilmemelidir.

---

## 11. AI'ın doğru yeri: müşteri sonucu değil, uygulama aracı

Bu ayrım, kapsamın en kolay şişeceği yerdir; net yazıyoruz.

**Müşteri sonucu şudur:** *"Menümü yeniden yazmadan, hızlıca yayına alabildim."*
Sahip AI istemiyor; **yeniden yazmamak** istiyor.

**Dolayısıyla AI burada bir araçtır, bir özellik değil.** Bunun üç somut
sonucu var:

1. **Tek sağlayıcı yeter.** `config/ai.php` bugün dört sağlayıcı ailesi
   (local/google/openai/anthropic), yetenek→model yönlendirmesi ve
   `AiBudgetLedger` ile bir çoklu-model altyapısı tarif ediyor; hiçbirinin
   bağlantısı yok. Dikey akış (P0-05) için **bir** sağlayıcı yeterlidir.
   Çoklu-model altyapısı, dikey akış çalışmadan önce zorunlu değildir.
2. **Sınırlı concierge import önce gelir.** Pilotta 5–10 restoranın menüsünü
   ekip aktarır. Bu, otomatik çıkarımın doğruluğunu ölçmenin **en ucuz**
   yoludur: elle yapılan aktarımlar, sonradan otomatik çıkarımın karşılaştırma
   kümesidir. Bunun için gereken tek şey CSV uç noktasıdır.
3. **İnsan onayı mimaride zaten var ve korunmalıdır.** `ai_artifacts` tablosu
   artifact'i taslakta tutar (`applied_at` yalnız onaydan sonra dolar) ve
   `idempotency_key` çift uygulamayı engeller. Bu doğru tasarımdır. AI'ın
   ürettiği hiçbir şey onaysız yayına girmemelidir — özellikle **fiyat** ve
   **alerjen**, çünkü ikisinin de yanlışı müşteriye maliyet yazar.

**Bu belgenin AI hakkındaki tek gereksinimi:** P0-05'in kabul ölçütleri.
Onun ötesinde bir AI yatırımı bu listede yer almıyor.

---

## 12. Yorum — MVP için erken veya fazla geniş yatırımlar

**Bu bölüm bir karar değildir.** Hiçbir şeyin kaldırılmasını önermez; mevcut
kodun silinmesi bu belgenin yetkisi dışındadır ve zaten ödenmiş bir maliyeti
geri getirmez. Buradaki tek amaç, **§1'deki hükmün nereden doğduğunu**
göstermek: yatay genişlik, dikey derinliğin önüne geçmiş.

Gözlem şu: aşağıdaki alanların her biri iyi tasarlanmış, iyi belgelenmiş ve
testli. Ama hiçbiri bugün bir restoran sahibinin gününü değiştirmiyor —
çünkü hepsi, henüz tamamlanmamış bir dikey akışın **yanında** duruyor.

| Alan | Depodaki hâli | Neden şimdilik erken görünüyor |
| --- | --- | --- |
| **AI yetenek düzlemi** | 5 port, 5 domain nesnesi, 2 tablo, bütçe defteri, şema doğrulayıcı, prompt redaktörü, 4 sağlayıcı ailesi yapılandırması | Bağlı sağlayıcı yok, HTTP rotası yok. Tek gerçek AI ihtiyacı (menü aktarma) hiç açılmamış. Altyapı, kullanacağı özellikten önce geldi. |
| **Medya/DAM veri modeli** | `media_assets` + blob/version/rendition/usage/job (5 tablo), slot politikaları, AV portu | Arayüzü yok (`docs/61` E9 ⬜), işleyicisi `Unavailable`. Yani en zor kısım (model) yazıldı, en gerekli kısım (bir ürüne fotoğraf iliştirmek) yazılmadı. |
| **Iyzico sandbox dikey dilimi** | Gateway, oturum, callback, webhook, işlem tablosu, konflikt/bad-gateway istisnaları | Fiyat yayımlanmamış, deneme kavramı yok, kimse ödeme yapamaz. Ödeme altyapısı, satılacak bir şey netleşmeden geldi. |
| **Çift kayıtlı defter (CORE-12)** | `ledger_entries`, değişmez, idempotent tahsilat | Gerçek bir tahsilat hiç olmadı. Defter, kaydedeceği ilk işlemi bekliyor. |
| **Platform admin kabuğu** | Ayrı kabuk, plan yönetimi, elle ödeme, süper-admin middleware | Yönetilecek kiracı yok. |
| **6 dilli i18n boru hattı + RTL** | PO→MO→JSON, 6 katalog, RTL yön türetimi, kritik akış RTL testi | Panel için tam; **misafir sayfası hâlâ sabit Türkçe** (P1-06). Yatırım, misafirin olduğu yere değil, sahibin olduğu yere yapılmış. |
| **Güvenlik kanıt tabloları** | `tenant_isolation_evidence`, `backup_restore_evidence`, ayrı uç noktalar | Kanıtların kendisi hâlâ yerel kapsamlı (`docs/18`). Kanıtı **saklama** mekanizması, kanıtın **üretimi**nden önce geldi. |
| **SEO düzlemi** | sitemap, robots, kanonik URL, JSON-LD, slug 301 | Yayınlanmış gerçek menü yok. Ama bu yatırım ucuz ve geri dönüşü yüksek — listedekiler arasında en savunulabilir olanı. |
| **Tasarım sistemi / Storybook / token boru hattı** | `docs/35`, `docs/36`, `docs/37`, `docs/41`, katalog bileşenleri, paket bütçe testi | Menüde silme düğmesi yokken bileşen kataloğu olgun. Sıra tersine dönmüş. |
| **Adaptive cihaz paketleri** | `NegotiateDeviceClass`, masaüstü/mobil ayrı giriş noktaları, `adaptive-bundle-gate` | Mobil kullanım oranı hiç ölçülmedi (U-05). Optimizasyon, ölçümden önce yapıldı. |
| **Host yetenek probu** | `tools/host-capability-probe.php`, anahtar paritesi testi | `docs/18`'in kendi kaydına göre artık beklenen bir owner eylemi değil; hedef sağlayıcı netcup olarak belirlenmiş. |

**Bu tablodan çıkan tek yapıcı gözlem.** Yukarıdaki alanların çoğu, dikey
akış tamamlandığında **gerçekten** işe yarayacak. Sorun yatırımın kalitesi
değil, **sırası**. Bir sonraki paket seçimi yapılırken sorulacak soru şu
olabilir:

> Bu iş, pilot restoranın sahibinin yarın sabah yapacağı bir şeyi değiştiriyor
> mu?

P0 listesindeki dokuz maddenin dokuzunda cevap "evet". Bu tablodaki on bir
alanın hiçbirinde bugün "evet" değil.

---

## 13. Durum özeti

**önce (once).** Zabuno MVP'sinin müşteri faydası açısından bağımsız bir
değerlendirmesi yoktu. `docs/61` bir **plan uygulama** envanteriydi (planın
maddeleri karşılandı mı), `docs/69` bir **Definition of Done** kanıt
listesiydi (iddia edilenin kanıtı var mı). İkisi de "planı ne kadar
uyguladık" sorusunu cevaplıyordu; **"restoran sahibi bununla ne yapabiliyor"**
sorusunu değil. Eksikler bu yüzden modül başlıklarına dağılmış hâldeydi ve
hangi eksiğin pilotu, hangisinin geliri blokladığı ayrışmamıştı.

**şimdi (simdi).** `docs/71` tek bir belgede: (1) çalışan tabanı kaydediyor,
(2) dokuz P0 pilot **başlatma** eksiğini, bir PV pilot **validasyon** kanıtını
ve sekiz P1 eksiğini ID, müşteri sorunu, **dosya/satır düzeyinde repo
kanıtı**, gerekli ürün sonucu ve gözlenebilir kabul ölçütüyle tanımlıyor,
(3) kapalı pilot ile ücretli self-service hedeflerini ayırıyor,
(4) on üç bilinmeyeni ölçülebilir keşif sorusuna çeviriyor, (5) yirmi giriş
kapısı ve dört maddelik bir pilot **çıkış** kapısı veriyor, (6) `docs/18`'deki
5 dakika hedefi ile gerçekçi 15 dakika bandı arasındaki çatışmayı dört
seçenekle **karar masasına** koyuyor, (7) AI'ı müşteri sonucu değil uygulama
aracı olarak konumluyor.

**fark (fark).** Ölçülebilir fark: bir sonraki paket seçimi artık "hangi
modül eksik" değil, **"restoran sahibinin yarın sabahını hangi iş
değiştirir"** sorusuyla yapılabilir. Somut olarak, bugüne kadar hiçbir
belgede tek yerde yazmamış olan şu üç gerçek yazılı hâle geldi: menüde
**silme/yeniden adlandırma/sıralama uç noktası yok**, misafirin gördüğü ilk
kelime **"Menü"** (restoran adı değil), ve üretim e-posta taşıyıcısı hâlâ
**`log`**.

**kullaniciYolculugu.** Ayşe Hanım'ın 40 masalı lokantası. Bugün: kaydolur —
doğrulama e-postası **gelmez** (P0-06), çünkü üretimde e-posta günlüğe
yazılır. Diyelim ki bunu aştı: marka, şube, menü kurar; 42 ürünü **tek tek**
yazar (P0-05); "Yayınla"ya basar, hata alır çünkü 42 ürünün 42'si de gizli
oluşturulmuştur (P0-02); hepsini tek tek açar; yayınlar. Misafir masadaki
QR'ı tarar ve ekranda **"Menü"** yazan bir başlık görür — lokantanın adı
yoktur (P0-03), fotoğraf yoktur (P0-04). Akşam balık biter; Ayşe Hanım ürünü
gizler, ürün menüden **tamamen kaybolur** (P1-04). Ertesi gün "Mercimek
Çorbsı" yazdığını fark eder ve düzeltemez (P0-01). Bu belge sonrası: bu on
bir durağın her birinin bir ID'si, bir kanıtı ve "ne zaman çözülmüş sayılır"
ölçütü var.

**kalanEngel.** (1) `docs/18` Exit Gate hâlâ **NO-GO**; kiracı izolasyonu ve
yedekten dönüş kanıtları yerel kapsamlı, canlı sunucuda p95/LCP hiç
ölçülmedi. (2) §7'deki hedef çatışması **karar bekliyor** — bu belge onu
çözmez, çözmeye yetkili de değildir. (3) P1-02'de canlı ödeme mi açık deneme
mi seçileceği owner kararıdır (dış maliyet + ticari kapsam). (4) U-11
(alerjen hukuki sorumluluğu) hukuki görüş gerektirir. (5) `intermenu.io` ve
`almenu.io` bu oturumda okunamadı; pazar kalibrasyonu iki kaynağa dayanıyor.
(6) PV-01 ve §9.5 çıkış kapısı **tanımı gereği** pilot bitene kadar açık
kalır; bu bir gecikme değil, sınıfın kendisidir.

**capability_delta.** Kod, test, yapılandırma, roadmap veya stage sayacı
**değişmedi**. Eklenen yetenek: karar-hazır bir eksik envanteri ile ayrılmış
pilot **giriş** ve **çıkış** kapıları. Eklenmeyen yetenek: hiçbir çalışma
zamanı davranışı.

**Somut çalıştırılabilir / çalıştırılamaz iddiası.**
- **Çalıştırılabilir:** `docs/71-MVP-MUSTERI-FAYDASI-EKSIKLERI.md` okunabilir
  ve bir sonraki paketin kapsamı doğrudan §4 (P0), §4b (PV) veya §5 (P1)
  içindeki bir ID'ye sabitlenebilir; §9.1–§9.4'teki yirmi giriş kapısı bugün
  tek tek denetlenebilir.
- **Çalıştırılamaz:** Bu belge hiçbir eksiği kapatmaz. Ayşe Hanım bugün hâlâ
  menüsünden bir ürünü silemez, misafiri hâlâ lokantanın adını göremez ve
  doğrulama e-postası hâlâ gelmez. Bu belgenin yayımlanması bir ürün
  ilerlemesi **değildir**; yalnız bir sonraki ilerlemenin nereye yapılacağını
  belirler.

---

## Ek — bu belgede kullanılan repo kanıtlarının dizini

| Konu | Dosya |
| --- | --- |
| Menü uç noktalarının tamamı | `routes/api/menu-catalog.php` |
| Yayın uç noktaları | `routes/api/publication.php` |
| QR uç noktaları | `routes/api/qr-destination.php` |
| Ekip uç noktaları | `routes/api/team.php` |
| Medya uç noktaları | `routes/api/media.php` |
| Görünürlük varsayılanı, `position` sütunları | `database/migrations/2026_08_20_000002_create_menu_catalog_tables.php` |
| QR hedef geçmişi tabloları | `database/migrations/2026_08_22_000005_create_qr_destination_tables.php` |
| Medya blob/version/rendition/usage/job | `database/migrations/2026_08_27_000400_create_media_asset_model_tables.php` |
| AI invocation/artifact tabloları | `database/migrations/2026_08_27_000500_create_ai_plane_tables.php` |
| Abonelik/elle ödeme tabloları | `database/migrations/2026_08_23_000013_*` |
| Snapshot içeriği | `app/Application/Publication/UseCase/BuildPublicationSnapshot.php` |
| Misafir sayfası | `resources/views/public-menu.blade.php` |
| Panel menü eylemleri | `resources/js/components/catalog/menu/macro/MenuCatalogWorkspace.tsx` |
| Analitik olay tipleri | `app/Domain/Analytics/AnalyticsEventType.php` |
| Medya işleyici bağlaması | `app/Providers/AppServiceProvider.php` |
| Fortify özellikleri | `config/fortify.php` |
| Posta taşıyıcısı | `config/mail.php`, `.env.production.example` |
| AI yapılandırması | `config/ai.php` |
| Medya slot politikaları | `config/media-slots.php` |
| Fiyat/iletişim bölümleri | `resources/views/public/home.blade.php` |
| Exit Gate, TTFQ hedefi, ölçülmemiş p95/LCP | `docs/18-STAGE-01-MVP.md` |
| Plan uygulama envanteri (⬜/🔶 işaretleri) | `docs/61-PLAN-UYGULAMA-DURUMU.md` |
| MVP analitik metrikleri | `docs/68-MVP-ANALITIK-METRIKLERI.md` |
| Definition of Done | `docs/69-MVP-DEFINITION-OF-DONE.md` |
| Rol sınırları, rol değiştirme eksiği | `docs/70-ROLLER-VE-ILK-KULLANIM.md` |
