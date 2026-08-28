# 73 — Menüyü yayımlamak ile işletmek arasındaki fark

**Kaynak:** `71-MVP-MUSTERI-FAYDASI-EKSIKLERI.md` → **P0-01**

## 1. Eksik olan neydi

Bir restoran sahibinin **her gün** yaptığı yedi işten üçü üründe yoktu:

| Günlük eylem | Önceki durum |
| --- | --- |
| Kategori/ürün ekle | ✅ |
| Fiyat, alerjen, görünürlük değiştir | ✅ |
| **Ürünü/kategoriyi sil** | ❌ hiçbir `DELETE` yok |
| **Adı düzelt** | ❌ hiçbir ad `PUT`'u yok |
| **Sırayı değiştir** | ❌ `position` sütunu var, uç nokta yok |

Sonucu tek cümleyle: **ürün bir menüyü yayımlayabiliyor ama işletemiyordu.**
"Mercimek Çorbsı" yazan bir sahibin tek çaresi ürünü gizleyip doğrusunu
yeniden eklemekti — ve yanlış olan veritabanında sonsuza kadar kalıyordu.

`position` sütunları göçte zaten vardı ve `unique(menu_id, position)` /
`unique(category_id, position)` ile korunuyordu: sıralama **veri modelinde
tasarlanmış, yüzeyi yazılmamıştı.**

## 2. Altı uç nokta

```
PUT    /workspaces/{w}/menu-categories/{c}              ad düzelt
DELETE /workspaces/{w}/menu-categories/{c}              kategoriyi kaldır
PUT    /workspaces/{w}/menu-items/{i}                   ad düzelt
DELETE /workspaces/{w}/menu-items/{i}                   ürünü kaldır
PUT    /workspaces/{w}/menu-categories/{c}/item-order   ürün sırası
PUT    /workspaces/{w}/menu/{m}/category-order          kategori sırası
```

## 3. Üç karar

### 3.1 Silme geçmişi bozmaz

Yayınlanmış sürüm bir **anlık görüntüdür** ve JSON olarak saklanır. Bugün
silinen bir ürün, dün yayınlanmış menüde durmaya devam eder — basılı QR'ı
tarayan misafir bugünün taslağını değil, o günün gerçeğini görür.

Bir test bunu **bayt bayt** donduruyor: silmeden önceki ve sonraki snapshot
aynı olmalı ve silinen ürünün adı hâlâ içinde bulunmalı.

`products` satırı da silinmez: aynı ürün başka bir menüde ya da geçmiş bir
yayında yaşıyor olabilir. Silinen şey, ürünün **bu menüdeki yeridir**.

### 3.2 Sıralama toplu ve tam

`unique(parent, position)` yüzünden satırları tek tek güncellemek yolun
ortasında çakışır: ikinci ürünü birinci sıraya taşımak, birinci ürün hâlâ
oradayken imkânsızdır. Sıralama **iki aşamada** uygulanır — önce hepsi
çakışmayacak geçici konumlara, sonra hedeflerine. Geçici aralık mevcut en
büyük konumun üstünden başlar; sabit bir sayı yeterince uzun bir menüde
çakışırdı.

Liste **tam** olmalıdır. Kısmî bir sıralama, listelenmeyen satırları
öngörülemez bir yere bırakır ve kullanıcı bunu ekranda değil, yayınladıktan
sonra misafirin menüsünde fark eder. Eksik liste 422 alır.

12 ürünlü bir kategoride tam ters sıralama **tek istekte** başarılı olur —
test bunu donduruyor.

### 3.3 Silme ayrı bir izin istemez

Silme `menu.manage` iznine bağlıdır ve gerekçesi ölçülebilir: silme yalnız
taslağı etkiler, yayınlanmış sürüm bayt bayt aynı kalır. Yani silme,
yayınlamak gibi misafirin gördüğünü değiştiren bir iş değildir. Salt okunur
üye yine de silemez.

Bir "silme izni" uydurmak, planda olmayan bir kural eklemek olurdu.

## 4. Arayüz

Her satırda dört eylem: yukarı, aşağı, ad düzelt, kaldır.

**Sürükle-bırak yok.** Sürükleme dokunmatik ekranda ve klavyeyle güvenilir
değildir ve ayrı bir erişilebilirlik sözleşmesi ister. Yukarı/aşağı düğmesi
her girdi yöntemiyle çalışır.

**Dördü de metin taşır** (`aria-label`), yalnız simge değil: bir çöp kutusu
simgesi neyin silineceğini söylemez ve ekran okuyucu kullanıcısı listedeki beş
"sil" düğmesini birbirinden ayırt edemez.

Ad düzeltme `prompt` ile yapılıyor ve bu bilinçli bir **ara adımdır**: satır
içi düzenleme daha iyi bir deneyimdir, ama bu paketin sorunu "düzeltmenin YOLU
YOK"tu. Yolu açmak, güzelleştirmekten önce gelir.

### 4.1 İptal ile boş bırakmak aynı şey değil

Depo muhafızı (`forms.guard`) ilk denemeyi yakaladı: boş adda sessizce
vazgeçiyordum. `null` iptaldir ve sessiz kalmak doğrudur; boş bir metin ise
bir **niyettir** — kullanıcı Tamam'a bastı — ve sessizce yutmak, düğmeye
basılıp hiçbir şey olmaması demektir (`docs/47` Kural 5).

## 5. Yol boyunca bulunan bir altyapı kusuru

Yeni testler eklendiğinde tam takım **çöktü**: "Premature end of PHP process",
görüntü işleyen bir QR testinin ortasında. İzole çalıştırıldığında geçiyordu.

Sebep bellek tavanıydı: `memory_limit` **128M**, yani PHP'nin varsayılanı — bu
takım için seçilmiş bir değer değil. 1030 teste ve bellek-içi SQLite'a ulaşan
bir takımda 11 test daha eklemek sınırı aşıyordu, ve hata mesajı asıl sebebi
söylemiyordu.

`phpunit.xml` artık sınırı **açıkça** yazıyor. Bir gün yeniden çökerse neyin
değiştiği görünür olacak.

## 6. Kalan

- Satır içi ad düzenleme (`prompt` yerine).
- Ürünü kategoriler arasında taşımak: bugün yalnız kategori içinde sıralama
  var.
- Toplu seçim ve toplu silme.
