# 62 — Saat dilimi sahipliği: markadan şubeye

**Tur:** 1/6 · **Kaynak:** marka formu UX raporu §"Alanları doğru domain
nesnesine taşımak", öncelik listesi madde 1 ve 4 · `docs/61` C1, C4, C5

## 1. Yanlış olan neydi

`brands` tablosu üç bölgesel alan taşıyordu: `locale`, `timezone`, `currency`.
Marka oluşturma formu üçünü de soruyordu.

Saat dilimi markanın alanı değildir. Aynı markanın İstanbul, Dubai ve Berlin
şubesi olabilir ve üçünün saat dilimi farklıdır. Alan markada durduğu sürece
**ikinci şube açılır açılmaz yanlış olur** — ve yanlışlığı görünmez, çünkü tek
şubeli bir işletmede doğru görünmeye devam eder. Bu, sessizce yanlış cevap
veren bir modeldir; hatası ancak müşteri "menü neden 21:00'de değil 19:00'da
kapandı" diye sorduğunda ortaya çıkar.

## 2. Ne yapıldı

`locations.timezone` eklendi ve her şube markasının değerini devraldı: bugünkü
davranış aynen korundu, **sahiplik** değişti.

Göç iki adımlıdır ve bu birincisidir. `brands.timezone` **yerinde bırakıldı**:
onu aynı pakette düşürmek, hâlâ okuyan her kod yolunu tek seferde değiştirmeyi
zorunlu kılardı. Okunmayı bıraktığı kanıtlanmadan sütun düşürmek geri dönüşü
olmayan bir bahistir.

Yeni şube saat dilimi göndermezse **markanınkini devralır** — şube saat
dilimsiz kalmaz. Güncellemede alan yalnız gönderildiğinde yazılır; her istekte
yazsaydı, alanı taşımayan eski bir istemci şubenin saat dilimini sessizce siler
ve yayın saatleri o anda kayardı.

## 3. Ülke de artık listeden seçilir

Şube formu ülkeyi **serbest metin** olarak soruyordu: kullanıcıdan `TR`
yazmasını bekliyordu. Bu bir ISO kodudur, restoran sahibinin dili değil. Marka
formunda 2026-08-27'de düzeltilen hata, şube formunda duruyordu.

İkisi de artık `SelectField`. Etiket de düzeldi: alan `Country code` değil
`Country` diyor, çünkü ekranda kod değil ülke ADI görünüyor.

## 4. Neden aranabilir combobox değil

Plan saat dilimi için aranabilir bir combobox tarif ediyor. Burada
kullanılmadı ve sebebi ölçülebilir: liste **ülkeye göre daraltılıyor**. Küresel
liste 400'den fazla kimlik içerir; ülke seçildikten sonra Türkiye'de bir,
Almanya'da bir, ABD'de yirmi dokuz tane kalır. Bir ve yirmi dokuz arasındaki
bir listede aranacak bir şey yoktur.

Combobox, liste ülkeden bağımsız sunulduğu gün gerekli olur. O gün gelmeden
yazmak, kullanılmayan bir etkileşim modelini bakım yüküne çevirmek olurdu.

## 5. Uygulama sırasında çıkan iki kusur

**Kayıtlı değer listeden düşünce KAYBOLUYORDU.** Referans listesi gelmediğinde
`select` kayıtlı `TR` değerini gösteremiyor ve boşa düşüyordu. Kullanıcı
Kaydet'e bastığında hiç dokunmadığı bir alanı silmiş olurdu. Hem ülke hem saat
dilimi için kayıtlı değer, listede yoksa **seçenek olarak eklenir**.

**Gösterilen ile gönderilen ayrıydı.** Önerilen saat dilimi yalnız `value`
içinde varsayılana düşerek gösteriliyordu; form ise saat dilimsiz gönderiyordu.
Kullanıcı `İstanbul — UTC+03:00` yazan bir alan görüp başka bir şey
kaydediyordu. Öneri artık duruma da yazılır; kullanıcı listeden başkasını
seçerse koşul sağlanmaz ve seçimi ezilmez.

İkincisini bir test yakaladı — daha doğrusu, fikstürlere saat dilimi ekleyen
toplu yamanın yanlışlıkla bir **iddiayı** da düzenlemesi yakalattı. Yama fazla
genişti; sonucu doğru çıktı, ama doğru çıkması şans eseriydi ve her iddia tek
tek gözden geçirildi.

## 6. Kalan

- `brands.timezone` sütununun düşürülmesi (okuyan yol kalmadığı kanıtlanınca).
- `currency` → fiyat listesi: fiyat listesi nesnesi henüz yok, bu yüzden
  taşınacak bir yer de yok. Marka üzerinde kalıyor (`docs/61` C2).
- `locale` alanının parçalanması: kullanıcı arayüz dili, menü ana dili ve menü
  desteklenen dilleri (`docs/61` C3).
