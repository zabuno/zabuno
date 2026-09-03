# 88 — "Ne kadar? Kime sorarım?" (P1-01)

## Önce ne oluyordu

Siteye gelen bir restoran sahibi üç soru sorar. Sayfa üçüne de "henüz yok"
diyordu:

> There are no published plan prices yet…
> There is no connected contact form yet.
> Is pricing available? — Not yet.

Daha kötüsü: plan listesi `auth` + çalışma alanı bağlamı ardındaydı. **Fiyatı
görmek için kaydolmak gerekiyordu** — yani ürün, kaydolmayı fiyatı görmeye
bağlı kılıyordu. Bir restoran sahibi bunu yapmaz; sekmeyi kapatır.

## Fiyat veridir, kod değil

`/pricing` ve ana sayfa bölümü plan kataloğundan okur: ad, tutar, para birimi
ve hak listesi `plans` tablosundan gelir. **Rakamı sahibi girer.**

Sayfaya elle yazmak, fiyat değiştiği gün ikinci bir gerçek kaynak yaratırdı
ve ikisi ayrıştığında hangisinin doğru olduğunu kimse bilemezdi.

Üç durum ayrı ayrı düşünüldü:

| Durum | Ne gösterilir | Neden |
| --- | --- | --- |
| Plan var, tutar girilmiş | Tutar + para birimi | Para birimsiz bir sayı hangi parayı ödeyeceğini söylemez |
| Plan var, tutar **girilmemiş** | "Restorana göre fiyatlanır — bize yazın" | "0" ya da "ücretsiz" göstermek tutulmayacak bir söz olurdu |
| Hiç plan yok | "Fiyatlar henüz yayımlanmadı — bize yazın" | Boş bir tablo "bu ürün hazır değil" dedirtir |

Kaldırılmış (`is_active = false`) bir plan yeni ziyaretçiye **sunulmaz**;
mevcut abonelerin planı ayrı bir mesele.

Sıra `sort_order`'dan gelir: sahibin planları sunmak istediği sıra, veritabanı
kimliğinin sırası değil.

## İletişim: mesaj önce saklanır

Gereksinim iletişimi **P0-06'ya** (gerçek e-posta) bağlıyor ve o madde
sahibin sağlayıcı hesabını bekliyor.

Ama "ulaşmak" için e-posta şart değil. **Saklanan bir mesaj kaybolmaz.**
E-postaya bağlamak, sağlayıcı gelene kadar formu ölü tutardı — yani sorunun
kendisi devam ederdi. Şema gönderim durumunu (`delivered_at`,
`delivery_failure`) taşıyor; sağlayıcı bağlandığında gönderim onun üstüne
oturur.

**IP ve tarayıcı bilgisi saklanmaz.** Bir restoran sahibinin fiyat sorması,
hakkında iz tutulmasını gerektirmez (`docs/68` ile aynı ilke).

### Bal küpü sessizdir

İnsan görmediği bir alanı doldurmaz. Dolduran bir istek **sessizce düşer ve
başarı gibi görünür** — bota "yakalandın" demek, bir sonraki denemede o alanı
atlamasını öğretirdi.

Alan `aria-hidden` ve `tabindex="-1"` taşıyor: ekran okuyucu ya da klavye
kullanan bir insan da yanlışlıkla dolduramasın. Aksi hâlde tuzak, korumak
istediği kişiyi yakalardı.

## Yol boyunca çıkan gerçek kusur

Bu sayfalar bugüne kadar **tamamen statikti**. Fiyatı katalogdan okumak
onlara bir veritabanı bağımlılığı ekledi — ve ilk hâlinde veritabanı
okunamadığında tanıtım sitesinin tamamı **500** veriyordu.

Fiyat göstermemekten çok daha kötü: ziyaretçi ürünün çöktüğünü görür ve bir
daha gelmez. Katalog okunamazsa sayfa artık dürüst boş hâline düşüyor ve bir
test bunu donduruyor.

## Metin katalogda

Yeni sayfalar tek bir sabit kullanıcı metni taşımıyor: `site` alanı açıldı
(İngilizce kaynak — bu **ürünün** yüzeyi, misafir menüsündeki gibi restoranın
değil).

Bir **görünüm bestecisi** metni her `public.*` görünümüne veriyor: elle
geçirmeyi unutan bir çağıran sayfayı boş etiketlerle basar ya da çökertirdi,
ve bu ekranda görülene kadar fark edilmezdi. Elle verilen değer kazanır.

Çevrilemez dize borcu **62 → 56** düştü: yeni sayfalar sıfır katkı yapıyor ve
ana sayfadan da altı dize kataloğa taşındı.

## Değişen eski sözleşme

Bir test ana sayfanın "henüz fiyat yok / henüz iletişim formu yok"
**demesini** donduruyordu. O gün doğruydu. İkisi de artık var; sınırı hâlâ
ilan etmek bu kez **ters yönde** bir yalan olurdu.

Kuralın kendisi değişmedi — sayfa ne varsa onu söyler. Ölçülen şey artık
cümlelerin yokluğu değil, **yolların varlığı**: test hem bağlantıları hem de
o adreslerin gerçekten açıldığını kontrol ediyor.

## Kapsam dışı: yardım içeriği

Üçüncü ölçüt ("ilk 15 dakika" yardımı) bu pakete girmedi. Yazdığım sayfa 44
ayrı cümle taşıyordu — bir **makale**, arayüz etiketi değil. Cümle başına
katalog anahtarı, makaleler için yanlış şekildir ve o karar ayrı bir tur
istiyor.

`/help` yolu yine de **rezerve edildi**: bu arada bir işletmenin onu slug
olarak alması, sonradan geri alınamaz bir çakışma olurdu.

## Kanıt

`PublicPricingTest` (6), `ContactMessageTest` (5)

| Requirement | Ne donduruluyor |
| --- | --- |
| `PUBLIC-PRICING-NO-AUTH-01` | Oturumsuz ziyaretçi gerçek fiyatları görür |
| `PUBLIC-PRICING-FROM-CATALOG-01` | Veri katalogdan; sıra `sort_order`'dan |
| `PUBLIC-PRICING-INACTIVE-HIDDEN-01` | Kaldırılmış plan sunulmaz |
| `PUBLIC-PRICING-EMPTY-HONEST-01` | Boş hâl bir çıkmaz değil, çıkış yolu bırakır |
| `PUBLIC-PRICING-SURVIVES-CATALOG-FAILURE-01` | Katalog okunamazsa site ölmez |
| `CONTACT-PERSISTED-01` | Mesaj kaybolmaz |
| `CONTACT-CONFIRMED-01` | Gönderene teyit |
| `CONTACT-HONEYPOT-01` | Bot sessizce düşer |

## Ürün iddiası

Çalışır: kaydolmamış bir ziyaretçi fiyatları görür ve mesaj yazar; mesaj
saklanır ve teyit alır.
Çalışmaz: mesaj henüz **e-postayla** gönderilmiyor (P0-06, sağlayıcı hesabı
sahibinde) ve fiyat tablosu **sahibin rakamları girmesini** bekliyor — plan
kataloğu boş olduğu sürece sayfa dürüstçe bunu söylüyor.
