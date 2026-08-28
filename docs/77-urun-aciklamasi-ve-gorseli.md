# 77 — Ürün açıklaması ve görseli yayına bağlandı (P0-04)

## Önce ne oluyordu

Sahip menüsünü dijitale taşımasının **en somut sebebini** kullanamıyordu:
fotoğraf.

> Adana Kebap · 380,00 TL

Bu bir satırdır. Fotoğraflı ve açıklamalı bir kart satış aracıdır: misafir
ne yiyeceğini bilir, garsona sormaz, sipariş hızlanır.

Bugün ürün için ne açıklama alanı vardı (`products` tablosunda `name` ve
zaman damgalarından başka sütun yoktu), ne de bir görsel bağı. Medya veri
modeli tam kuruluydu — `media_usages` tablosundaki `publication_id` sütunu
bu bağın **tasarlandığını** ama kurulmadığını gösteriyordu.

## Şimdi ne oluyor

```
[fotoğraf]  Adana Kebap                              380,00 TL
            Kömür ateşinde, acılı, yanında bulgur pilavı.
            [süt] [gluten]
```

- `products.description` yazılabiliyor, misafir menüsünde ürün adının
  altında görünüyor. Boşsa satır düzeni bozulmuyor.
- Ürüne fotoğraf bağlanıyor; marka logosu da başlıkta görünüyor — bu,
  `docs/75`'te açıkta bırakılan tek maddeyi kapatır.

## Bağ SÜRÜME yapılır

Bu paketin en önemli kararı.

`media_usages` satırı `media_version_id` taşır, `media_asset_id` değil.
Sebep bir kullanıcı yolculuğu: sahip Ocak'ta menüsünü fotoğrafla yayınlar,
Mart'ta aynı fotoğrafı kırpıp yeniden yükler. Bağ varlığa yapılsaydı, Ocak'ta
**onayladığı yayın** Mart'ta habersiz değişirdi.

Yayın snapshot'ı görselin **sürüm kimliğini** ve o sürümün değişmez
adreslerini dondurur. Yeni sürüm, yeni yayınla gelir.

## Taslak bağ ile yayın kaydı ayrıdır

İki farklı soru, iki farklı satır:

| Soru | Satır |
| --- | --- |
| "Şu an bu ürüne hangi fotoğraf bağlı?" | `publication_id` **null** olan taslak satır |
| "Şu yayında hangi fotoğraf kullanılmıştı?" | `publication_id` **dolu** olan yayın satırı |

Taslağı işaretlemek bu iki soruyu tek satıra doldurmak olurdu. Yayın satırı
sayesinde `docs/76`'daki silme koruması artık **gerçek bir yol üzerinde**
çalışıyor: yayınlanmış menüde kullanılan görsel `409` ile korunuyor.

## `srcset`, `loading`, `width`/`height`

Üçü de misafirin masadaki deneyimi için:

- **`srcset`** — 320 px'lik bir telefon 960 px'lik dosyayı indirmez. Slotun
  bütün genişlikleri sunulur, tarayıcı seçer.
- **`loading="lazy"`** — kırk ürünlük bir menüde misafir ilk ekranı görmek
  için kırk fotoğraf beklemez.
- **`width`/`height`** — görsel inerken sayfa zıplamaz; misafir okuduğu
  satırı kaybetmez.

## Bütçe yeniden ölçüldü

Fotoğraf eklemek belgeyi de büyütür: her satır dört adres, ölçüler ve bir
alternatif metin taşır. `docs/06`'daki 100 KB bütçesi, 8 kategori × 10 ürün
fotoğraflı ve açıklamalı bir menüyle **yeniden ölçüldü ve geçti**. Aksi
hâlde "menü artık daha güzel ama açılmıyor" olurdu.

## Yol boyunca çıkan gerçek kusur

Aynı görselin yeniden işlenmesi **başarısız oluyordu**. Aynı baytlar aynı
sağlama toplamını, o da aynı depolama anahtarını üretiyor; `storage_key`
tekil olduğu için ikinci ekleme reddediliyordu.

Oysa değişmemiş bir fotoğrafı yeniden işlemek tamamen normal bir iştir —
kırpma algoritması güncellenince toplu yeniden üretim tam olarak budur.

Çözüm: **kiracı içinde** blob tekilleştirme. Kiracılar **arası** bilerek
yapılmıyor (silme, kota ve "başka bir işletme bu dosyaya sahip mi" sızıntısı
karmaşıklaşır — göç dosyasındaki not). Ayrıca geri alma yolu artık paylaşılan
bir dosyayı silmiyor: temizlik, çalışan bir yayını bozamaz.

## Kanıt

`tests/Feature/Publication/MenuItemMediaTest.php` (9),
`tests/Feature/Performance/PublicMenuPayloadBudgetTest.php` (+1)

| Requirement | Ne donduruluyor |
| --- | --- |
| `MENU-ITEM-DESCRIPTION-01` | Açıklama snapshot'a ve misafir sayfasına geçer |
| `MENU-ITEM-IMAGE-BIND-01` | Bağ bir kullanım satırı yaratır ve SÜRÜME işaret eder |
| `MENU-ITEM-IMAGE-FROZEN-01` | Sonradan üretilen sürüm eski yayını değiştirmez |
| `MENU-ITEM-IMAGE-SRCSET-01` | Tüm genişlikler sunulur; `lazy`, `width`/`height` var |
| `MEDIA-USAGE-ON-PUBLISH-01` | Yayın kullanımı kaydeder; görsel artık silinemez |
| `BRAND-LOGO-ON-MENU-01` | Logo başlıkta, alt metniyle |
| `PERF-MENU-PAYLOAD-01` | Fotoğraflı menü 100 KB bütçesinde |

Açıklama gönderilmediyse **dokunulmaz**: adı düzelten bir istek, sahibin
yazdığı açıklamayı sessizce silmez.

## Sınır: panel arayüzü

Bu paket uçları, veriyi ve misafir sayfasını kurar. Sahibin panelden
fotoğraf seçmesi ve açıklama yazması **FF-20**'nin işi; o gelene kadar bu
yol yalnız API üzerinden yürünebilir.

## Ürün iddiası

Çalışır: bir ürüne fotoğraf ve açıklama bağlanır, yayınlanır, misafir onları
doğru boyutta görür ve yayın sonradan değişmez.
Çalışmaz: sahip bunu **panelden** henüz yapamıyor (FF-20).
