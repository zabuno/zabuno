# 80 — Menüyü almak ve geri koymak (P0-05 CSV yolu + P0-09)

İki soru, tek dosya biçimi.

## Soru 1 — "Menümü nasıl gireceğim?"

Restoranın menüsü **zaten var**: basılı, PDF ya da Excel. Zabuno onu tek tek
elle yeniden yazdırıyordu. 60 kalemlik bir menü, **60 ayrı form gönderimi**
demekti. Bu pilotta ekibin saatini, self-service'te müşteriyi yakar.

## Soru 2 — "Menümü alıp gidebilir miyim?"

Cevap hayırdı. Depoda menü/kullanıcı verisi için hiçbir dışa aktarma yüzeyi
yoktu. Oysa bu cevap, pilot restoranın **kilitlenme korkusunu** kaldıran
şey — ve KVKK/GDPR kapsamında bir hak.

## Tek dosya biçimi

```csv
category,product,price,currency,allergens,description,visible
Çorbalar,Mercimek Çorbası,52.50,TRY,süt;gluten,Ev yapımı,yes
```

Alerjenler **noktalı virgülle** ayrılır: virgül sütun ayracıdır ve aynı
hücrede ikinci bir anlam taşıyamaz.

Dışa aktarılan dosya **geri yüklenebilir**. Bir test bunu donduruyor: dışa
aktar → ikinci bir menüye yükle → yeniden dışa aktar → **bayt bayt aynı**.
Aksi hâlde "menümü alıp gidebilirim" bir söz değil, bir slogan olurdu.

## Neden AI yok

Gereksinim fotoğraf/PDF yollarını da içeriyor ve onlar bir OCR/görü
sağlayıcısı istiyor. Ürünün AI düzlemi kurulu ama kapalı (`AI_ENABLED=false`,
sağlayıcı bağlantısı yok) ve sağlayıcı hesabı **sahibin kararı** — dış
maliyet ve veri işleme kapsamı onun.

Gereksinim belgesi bunu zaten söylüyor: *"CSV yolu tek başına da yeterlidir
ve sağlayıcı gerektirmez."* Pilotta ekip CSV'yi hazırlar — ama o zaman CSV
**uç noktası gerçekten var olmalıdır**, yoksa ekip de elle yazar. Bu paket
o uç noktayı açar.

## Dört karar

### 1. Doğrulama ile yazma ayrıdır

Önce dosyanın tamamı okunur ve **her satır tek tek yargılanır**; yazma
ondan sonra, **tek işlemde** yapılır.

İki şey birden sağlanır:

- Bozuk iki satır yüzünden geçerli 60 satır kaybolmaz — 60 kalemi yeniden
  yazmak, sahibin en başta kaçtığı iştir.
- Yolun ortasında ölen bir aktarım yarım menü bırakmaz.

Reddedilen satırlar **dosyadaki satır numarasıyla** raporlanır (başlık 1.
satırdır). Numara olmadan sahip 60 satırı gözle taramak zorunda kalır.

### 2. Aktarım yayına dokunmaz

Aktarım **taslağa** yazar. Misafirin gördüğü, sahip Yayınla'ya basana kadar
değişmez. Bir test bunu donduruyor: aktarım öncesi ve sonrası yayın
snapshot'ı **birebir aynı**.

### 3. Formül enjeksiyonu

Elektronik tablo, `=` `+` `-` `@` ile başlayan bir hücreyi **formül olarak
çalıştırır**. Menüsünü indiren sahibin makinesinde komut çalıştırmak, bizim
ürettiğimiz bir dosyayla olmamalı.

Hücre tek tırnakla nötrlenir — metin olarak okunur ve **kullanıcının yazdığı
ad kaybolmaz**. Geri yüklemede tırnak kaldırılır, bu yüzden gidiş-dönüş
bozulmaz.

### 4. Kuruşsuz para birimleri

Her yerde iki ondalık hane varsaymak, 380 yeni (JPY) fiyatını **3,80**
yapardı. Sıfır ve üç haneli para birimleri ayrıca ele alınıyor. Kuruşa
çevirirken yuvarlama yapılıyor: `(int) (52.50 * 100)` bazı ondalıklarda 5249
verir ve fiyat bir kuruş eksilir.

## Yetki ve sınır

| Eylem | İzin | Hız sınırı |
| --- | --- | --- |
| Dışa aktar | `menu.view` — kendi verisini almak, onu değiştirme yetkisi gerektirmez | 20/dk |
| İçe aktar | `menu.manage` | 10/dk |

Salt-okunur bir üye menüsünü indirebilir ama içeri aktaramaz. Başkasının
menüsünü indirmeye çalışmak `404` — çalışma alanının varlığı bile sızmaz.

## Küçük ama gerçek ayrıntılar

- **BOM**: Excel Türkçe dosyalarda görünmez bir başlangıç bırakır;
  temizlenmezse ilk sütun adı eşleşmez ve sahip "dosyam neden geçersiz" der.
- **Aynı ada ikinci kategori açılmaz**: sahibin menüsünü ikiye böler ve
  misafir "Kebaplar"ı iki kez görürdü.
- **`visible` boşsa görünür**: `docs/74`'teki sessiz duvar burada da
  kurulmamalı.
- **Panelde `Content-Type` elle yazılmaz**: `multipart/form-data` sınırını
  tarayıcı üretir; elle yazmak sınır dizesini kaybettirir ve sunucu boş bir
  gövde görür.

## Kanıt

`tests/Feature/MenuCatalog/MenuCsvRoundTripTest.php` (7),
`MenuCatalogWorkspace.csv.test.tsx` (3)

| Requirement | Ne donduruluyor |
| --- | --- |
| `MENU-EXPORT-CSV-01` | Sabit başlık, doğru ondalık, doğru görünürlük |
| `MENU-EXPORT-ISOLATION-01` | İkinci bir kiracının tek satırı bile sızmaz |
| `MENU-EXPORT-FORMULA-SAFE-01` | `=` `@` `+` ile başlayan hücre nötrlenir |
| `MENU-IMPORT-BULK-01` | 60 kalem tek işlemde |
| `MENU-IMPORT-PARTIAL-REPORT-01` | İki bozuk satır numarasıyla raporlanır, 60 geçerli satır kaybolmaz |
| `MENU-IMPORT-NO-PUBLISH-01` | Yayın snapshot'ı değişmez |
| `MENU-CSV-ROUNDTRIP-01` | Çıkan dosya geri girer, aynı menüyü verir |
| `MENU-CSV-AUTHZ-01` | Salt-okunur üye indirir, aktaramaz |

## Ürün iddiası

Çalışır: sahip menüsünü CSV olarak indirir, düzenler, geri yükler; 60 kalem
tek işlemde taslağa girer ve bozuk satırlar numarasıyla raporlanır.
Çalışmaz: fotoğraf ve PDF yolları — bir OCR/görü sağlayıcısı gerekiyor ve
sağlayıcı hesabı sahibin kararı (P0-05 kriter 2).
