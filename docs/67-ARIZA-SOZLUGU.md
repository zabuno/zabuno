# 67 — Arıza sözlüğü: "bir şeyler ters gitti" kimseye bir şey söylemez

**Tur:** 6/6 · **Kaynak:** marka formu UX raporu §5, §6 · form standardı §5 ·
`docs/61` C8–C12

## 1. Önce ölçüm: altyapı zaten vardı

Bu tur bir taksonomi yazmakla başladı — ve yazılan şey **zaten vardı**.
`lib/requestFailure.ts` altı arıza sınıfını tanımlıyor, `ErrorSummary`
bileşeni hata özetini ve odak taşımayı yapıyor, `validationErrors.ts`
sunucunun 422 gövdesini okuyor.

Yazdığım kopya silindi. İki paralel sözlük, tek bir yanlış sözlükten kötüdür:
biri düzeldiğinde diğeri eski hâlinde kalır ve hangisinin doğru olduğu
kaybolur.

**Eksik olan altyapı değil, YAYILIMDI.** Taksonomiyi yalnız `BrandEditForm`
kullanıyordu. Diğer üç form hâlâ her başarısızlığı tek bir cümleye
düşürüyordu.

## 2. Neden altı ayrı cümle

| Durum | Kullanıcının yapması gereken |
| --- | --- |
| 422 | Alanı düzelt — hangi alan olduğu sunucudan gelir |
| 403/401 | Yetki iste — kimden isteneceği söylenir |
| 409 | Yeniden yükle, sonra değişikliği tekrar uygula |
| 404 | Kayıt yok; başka sekmede silinmiş olabilir |
| 5xx | Birazdan tekrar dene — girilen veri doğru |
| bağlantı yok | Tekrar dene — **girilenler duruyor** |

**"Tekrar deneyin" bu altı durumun yalnız ikisinde doğru tavsiyedir.** Yetki
yoksa tekrar denemek hiçbir zaman işe yaramaz. Çakışma varsa veriyi
değiştirmek gerekir. Yanlış tavsiye, kullanıcıyı aynı yolu tekrar tekrar
denemeye ve sonunda vazgeçmeye götürür.

Bir test bu ayrımın kendisini donduruyor: beş arıza sınıfı **beş farklı**
cümle üretmelidir, ve "tekrar dene" yalnız denemenin işe yarayabileceği
yerde geçmelidir.

## 3. `messageForFailure` ortak yere çıkarıldı

Eşleme `BrandEditForm`'un içinde özeldi. Dört form aynı sözlüğü kullanacaksa
sözlük tek yerde durmalı: ayrı ayrı kopyalansaydı biri düzeldiğinde diğerleri
eski hâlinde kalırdı.

## 4. Bulunan kusur: sahte yanıtların başlığı yoktu

Testlerdeki `jsonResponse` yardımcıları `Response` taklidi yapıyor ama
`headers` taşımıyordu. Gerçek bir `Response` her zaman taşır.

Sonucu sinsiydi: başlık okuyan her kod yolu testte **istisna fırlatıyor**,
istisna dış `catch` bloğuna düşüyor ve arıza "bağlantı kopması" gibi
görünüyordu. Yani 422 dönen bir sunucu, testte ağ hatası olarak okunuyordu.

Kırk dört fikstür düzeltildi. Alternatif — `response.headers?.get(...)` ile
tolerans göstermek — kusuru testten gizlerdi ve üretimde başlığı gerçekten
okunamayan bir yanıt sessizce yanlış sınıflandırılırdı.

## 5. Bir metin değişti ve testi güncellendi

Marka formu kendi ağ hatası cümlesini taşıyordu: "We could not reach the
server." Ortak sözlükteki cümle daha iyi: ürünü adıyla anıyor ve asıl korkuyu
cevaplıyor — "everything you typed is still here."

Test metni değil DAVRANIŞI ölçecek şekilde güncellendi ve verinin durduğunu
söyleyen cümle ayrıca iddia edildi.

## 6. Kalan

- **C11 idempotent submit**: istemci tarafı çift tıklama koruması var (istek
  uçarken düğme devre dışı). Sunucu tarafı idempotency yalnız faturalamada
  var (`Idempotency-Key`); kiracı formlarında yok. Başlığı kimse okumadan
  göndermek ölü ağırlık olurdu — arka uç değişikliği gerektirir.
- **C12 sabit `form_id`/`field_id`/`error_code`**: `trackEvent` altyapısı var
  ve arıza sınıfları zaten sabit kimlikler; olay şeması ayrı bir pakettir ve
  ölçüm sorularının önce tanımlanmasını ister (`docs/47`: "event planı kod
  yazılmadan önce iş sorularından türetilmelidir").
- Ekip daveti ve menü katalog formları henüz ortak sözlüğü kullanmıyor.
