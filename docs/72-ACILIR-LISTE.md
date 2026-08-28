# 72 — Açılır listeyi tarayıcıya bırakmamak

**Talep:** açılır menü Safari'de de Chrome'da da aynı stille açılmalı.

## 1. Neden CSS ile çözülemez

`<select>`'in **açılan paneli sayfanın DOM'unda değildir**. İşletim sistemi
çizer:

- Chrome macOS'ta kendi koyu panelini gösterir.
- Safari macOS'un yerli menüsünü gösterir — mavi seçim çubuğuyla.

İkisi farklı görünür ve hiçbir CSS kuralı ikisine birden ulaşamaz. `reset.css`
de çözmezdi; ortada sıfırlanacak bir stil yok, çünkü ortada bir DOM yok.

Kapalı kontrol her zaman biçimlendirilebilirdi ve zaten biçimlendirilmişti.
Şikâyet edilen ekran görüntüsü **açık** paneldi.

## 2. Yapılan

Panel kendimiz çiziyoruz. Ama `<select>` DOM'da **kalıyor** ve kontrolün
sahibi o:

| Sorumluluk | Kimde |
| --- | --- |
| Erişilebilir ad, `aria-invalid`, form gönderimi | `<select>` |
| Ok tuşları, Home/End, harfle arama | `<select>` — tarayıcıdan gelir, taklit edilmez |
| Ekran okuyucu listesi | `<select>` |
| İşaretçiyle açılan panelin GÖRÜNÜMÜ | bizim |

Yerli panelin açılması `mousedown` varsayılanı engellenerek durdurulur — üç
tarayıcıda da çalışan yol budur.

Panelimiz `aria-hidden`: erişilebilir kontrol `<select>`'in kendisidir ve
ekran okuyucu onun yerli listesini duyar. Aynı seçenekleri ikinci kez
duyurmak, kullanıcıyı iki listeyle baş başa bırakırdı.

### 2.1 Neden `<select>` tamamen kaldırılmadı

Tam özel bir listbox yazmak, klavye dolaşımını, harfle aramayı, form
gönderimini ve ekran okuyucu davranışını **elle yeniden yazmak** demekti —
tarayıcının bedavaya ve doğru yaptığı dört şey. Ayrıca on iki test dosyası
`userEvent.selectOptions` kullanıyor; o yardımcı gerçek bir `<select>` ister.

Görünümü değiştirmek için davranışı yeniden yazmak, çözülen sorundan büyük bir
sorun üretirdi.

## 3. Tarayıcıda ölçüm

Chromium 1440×900, karanlık tema, gerçek bileşen:

| Ölçüm | Değer |
| --- | --- |
| Panel sayfanın DOM'unda mı | **evet** |
| Kontrol genişliği | 340 px |
| Panel genişliği | 340 px — **eşit** |
| Sol hizalama | aynı |
| Kontrol ile panel arası | 4 px |
| `appearance` | `none` — motor süslemesi yok |
| Kontrol yüksekliği | 44 px |
| Satır yüksekliği | 44 px |
| Panel zemini | `oklch(0.2 0 0)` |
| Panel kenarlığı | `oklch(0.32 0 0)` |
| Seçili satır rengi | `oklch(0.95 0 0)` |
| Diğer satırlar | `oklch(0.7 0 0)` |

**Her renk kroma 0.** Panelde tek bir renkli piksel yok — dolayısıyla mavi de
yok. Ve her piksel bizim CSS'imizden geldiği için Safari'de aynı çizilir:
farkın kaynağı olan işletim sistemi paneli artık devrede değil.

Seçili satır renkle değil **işaretle** ayrılıyor: yüksek kontrast modunda ve
renk körlüğünde renk kaybolur.

## 4. Doğrulama nasıl yapıldı

Uygulamanın `<select>` taşıyan tüm ekranları oturum arkasında. Ölçüm için
gerçek bileşeni mount eden geçici bir sayfa derlendi, ölçüldü ve **silindi**.
Ölçülen şey bileşenin kendisidir; taklit bir işaretleme değil.

## 5. Kalan

- Panel içinde ok tuşuyla dolaşım yok ve olmamalı: klavye `<select>`'in
  kendisinde çalışıyor ve panel yalnız işaretçi için açılıyor. İkisini birden
  klavyeye açmak, aynı listeyi iki ayrı klavye modeliyle sunmak olurdu.
- Çok uzun listelerde arama alanı yok; bugün en uzun liste ülke listesi ve
  ülke seçimi zaten ülkeye göre daraltılmış durumda (`docs/62`).
